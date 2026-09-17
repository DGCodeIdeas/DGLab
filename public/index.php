<?php
/**
 * DGLab — Public Web Entry Point
 *
 * Boots the Sovereign Stack Kernel (CORE-18), pipes the Vanguard (BRIDGE-01)
 * as the outermost PSR-15 middleware on the external-facing pipeline, and
 * dispatches the incoming PSR-7 ServerRequest through the full Pulse trace:
 *
 *   Outer Rim (Vanguard) → Inner Rim (Kernel pipeline → router → controller) → Response
 *
 * This entry point satisfies the AGRD §4 success criterion: "a real HTTP
 * request enters at the Outer Rim, crosses the Inner Rim, resolves against
 * the Inner Spoke, and returns — the actual synchronous-radial Pulse trace."
 *
 * FrankenPHP worker mode: when running under FrankenPHP's worker{} directive,
 * this file is loaded ONCE as the worker bootstrap. The Kernel is booted
 * once, then frankenphp_handle_request() loops the handler for each request.
 * Under PHP-FPM (no frankenphp_handle_request function), falls back to the
 * traditional per-request bootstrap.
 *
 * Depth-2 scope: the Vanguard enforces contract lookup (default-deny 403)
 * and WAF inspection. JWT verification, rate limiting, and HUB-06 audit are
 * pass-through stubs. When HUB-02/HUB-04/HUB-06 land, the stubs are replaced
 * — the chain order and public/index.php are unchanged.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Core\ErrorHandler\ErrorHandler;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\EventDispatcher\EventDispatcher;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\Http\ServerRequestFactory;
use SovereignStack\Core\Logger\Logger;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\Stub\EmptyProviderRegistry;
use SovereignStack\Core\Router\Router;
use SovereignStack\Core\Router\Route;
use SovereignStack\Bridge\Vanguard;
use SovereignStack\Bridge\ContractRegistry;
use SovereignStack\Bridge\WafInspector;
use SovereignStack\Bridge\DefaultDtoTransformer;
use SovereignStack\Core\Http\ResponseFactory;
use App\Controller\HelloController;
use Psr\Log\NullLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// --- 1. Build the Kernel dependencies (runs once in worker mode) ---

$container = new Container();
$provider  = new ListenerProvider();
$dispatcher = new EventDispatcher($provider);
$logger    = new Logger();
$errorHandler = new ErrorHandler($logger, new PlainTextRenderer(), debug: false);

// Pre-bind the services the HttpBootstrapper expects.
$container->instance(
    \SovereignStack\Core\EventDispatcher\EventDispatcherInterface::class,
    $dispatcher,
);
$container->instance(
    \SovereignStack\Core\Logger\LoggerInterface::class,
    $logger,
);
$container->instance(
    \SovereignStack\Core\ErrorHandler\ErrorHandlerInterface::class,
    $errorHandler,
);

// --- 2. Build the Vanguard (BRIDGE-01) ---

$contractRegistry = new ContractRegistry();
$waf = new WafInspector();
$responseFactory = new ResponseFactory();

// Register the root contract — the "Hello World" route.
$contractRegistry->registerContract('/', new DefaultDtoTransformer());

$vanguard = new Vanguard(
    contracts: $contractRegistry,
    waf: $waf,
    responseFactory: $responseFactory,
    logger: new NullLogger(),
);

// --- 3. Boot the Kernel (runs once in worker mode) ---

$kernel = new Kernel(
    containerFactory: fn () => $container,
    configFactory: fn () => new ConfigRepository([]),
    errorHandlerFactory: fn () => $errorHandler,
    providerRegistryFactory: fn () => new EmptyProviderRegistry(),
    eventDispatcherFactory: fn () => $dispatcher,
    loggerFactory: fn () => $logger,
    routerFactory: fn () => new Router(),
    bootstrappers: [
        // Custom bootstrapper: wires the Vanguard as outermost middleware,
        // then delegates to the HttpBootstrapper for the router + pipeline.
        new class ($vanguard) implements BootstrapperInterface {
            public function __construct(
                private readonly Vanguard $vanguard,
            ) {}

            public function bootstrap(\SovereignStack\Core\Kernel\KernelInterface $kernel): void
            {
                // Run the standard HttpBootstrapper to wire pipeline + router.
                (new \SovereignStack\Core\Kernel\HttpBootstrapper())->bootstrap($kernel);

                // Pipe the Vanguard as the OUTERMOST middleware.
                $pipeline = $kernel->getPipeline();
                $pipeline->pipe($this->vanguard);

                // Register the "Hello World" route (Milestone 0 success criterion).
                $router = $kernel->getRouter();
                $router->addRoute(new Route(
                    path: '/',
                    methods: ['GET'],
                    name: 'hello',
                    controllerClass: HelloController::class,
                    controllerMethod: 'handle',
                ));

                // Bind the controller into the container.
                $kernel->getContainer()->bind(HelloController::class);
            }
        },
    ],
);

$kernel->boot();

// --- 4. Request handler (runs per-request in worker mode) ---

/**
 * Handle a single HTTP request through the Kernel pipeline.
 * Returns the PSR-7 Response for the caller to emit.
 *
 * CRITICAL: Under FrankenPHP worker mode, exceptions thrown inside
 * frankenphp_handle_request() are intercepted by FrankenPHP's runtime
 * BEFORE reaching PHP's set_exception_handler. The ErrorHandler's
 * registered handler never fires — FrankenPHP converts the exception
 * to "Internal server error" with an empty body, and the actual error
 * is silently lost. This try/catch is the ONLY place the exception
 * can be captured, logged, and converted to a proper PSR-7 Response.
 */
$isDevMode = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') === 'dev';

$handleRequest = function (ServerRequestInterface $request) use ($kernel, $responseFactory, $isDevMode): ResponseInterface {
    try {
        return $kernel->handle($request);
    } catch (\Throwable $e) {
        // Log to STDERR — FrankenPHP captures this and routes to journald.
        // This is the ONLY way to see the actual exception in worker mode.
        $trace = $e->getTraceAsString();
        error_log(sprintf(
            '[DGLab] Uncaught %s: %s at %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));
        error_log('[DGLab] Stack trace:' . PHP_EOL . $trace);

        // Build a PSR-7 500 response. In dev mode, include the error
        // message so curl shows it without needing journalctl.
        $response = $responseFactory->createResponse(500);
        if ($isDevMode) {
            $body = sprintf(
                "Internal Server Error\n\n%s: %s\n\nat %s:%d\n\nStack trace:\n%s\n",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $trace,
            );
            $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        } else {
            $body = 'Internal Server Error';
        }
        $response->getBody()->write($body);
        return $response;
    }
};

/**
 * Emit a PSR-7 Response to the SAPI (headers + body).
 * In FrankenPHP worker mode, the response is returned to the worker
 * and FrankenPHP handles emission — so this is only used in FPM mode.
 */
$emitResponse = function (ResponseInterface $response): void {
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }
    echo $response->getBody();
};

// --- 5. Dispatch: FrankenPHP worker mode OR PHP-FPM fallback ---

if (function_exists('frankenphp_handle_request')) {
    // FrankenPHP worker mode: the handler is called in a loop by FrankenPHP.
    // The Kernel is booted once above; each request gets handle() + the
    // response is returned to FrankenPHP which handles SAPI emission.
    frankenphp_handle_request($handleRequest);

    // After the worker loop exits (shutdown signal), terminate the Kernel.
    $kernel->terminate();
} else {
    // PHP-FPM / CLI fallback: handle one request, emit, terminate.
    $request = ServerRequestFactory::fromGlobals();
    $response = $handleRequest($request);
    $emitResponse($response);
    $kernel->terminate();
}
