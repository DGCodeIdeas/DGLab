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
use App\Controller\HealthController;
use App\Controller\HelloController;
use Psr\Log\NullLogger;
use Psr\Http\Message\ResponseInterface;

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

// Register the /health contract — used by Tengine's active health check
// and future monitoring systems. Pass-through DTO transformer.
$contractRegistry->registerContract('/health', new DefaultDtoTransformer());

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

                // Register the /health route (Tengine health check + monitoring).
                $router->addRoute(new Route(
                    path: '/health',
                    methods: ['GET'],
                    name: 'health',
                    controllerClass: HealthController::class,
                    controllerMethod: 'handle',
                ));

                // Bind the controllers into the container.
                $kernel->getContainer()->bind(HelloController::class);
                $kernel->getContainer()->bind(HealthController::class);
            }
        },
    ],
);

$kernel->boot();

// --- 4. Request handler (runs per-request in worker mode) ---

/**
 * FrankenPHP worker handler.
 *
 * Per the official FrankenPHP worker documentation, the handler:
 *   1. Takes NO arguments (FrankenPHP calls it with zero parameters)
 *   2. Creates the request from superglobals INSIDE via fromGlobals()
 *   3. Emits the response via echo + http_response_code() + header()
 *      (NOT by returning a Response — the handler returns void)
 *
 * Exceptions thrown inside frankenphp_handle_request() are intercepted by
 * FrankenPHP BEFORE reaching PHP's set_exception_handler (per docs:
 * "set_exception_handler is called only when the worker script ends").
 * This try/catch is the ONLY place to capture + log + render exceptions.
 */
$isDevMode = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') === 'dev';

$handler = static function () use ($kernel, $responseFactory, $isDevMode): void {
    try {
        $request = ServerRequestFactory::fromGlobals();
        $response = $kernel->handle($request);
    } catch (\Throwable $e) {
        // Log the exception — FrankenPHP captures error_log() as JSON.
        error_log(sprintf(
            '[DGLab] Uncaught %s: %s at %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));

        // Build a PSR-7 500 response. In dev mode, include the error
        // details so curl/journalctl show the actual problem.
        $response = $responseFactory->createResponse(500);
        if ($isDevMode) {
            $body = sprintf(
                "Internal Server Error\n\n%s: %s\n\nat %s:%d\n\nStack trace:\n%s\n",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );
            $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        } else {
            $response->getBody()->write('Internal Server Error');
        }
    }

    // Emit the response via SAPI functions (echo + headers).
    // In FrankenPHP worker mode, this output is captured by FrankenPHP
    // and sent as the HTTP response. The handler must NOT return a value.
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }
    echo $response->getBody();
};

/**
 * Emit a PSR-7 Response to the SAPI (headers + body).
 * Only used in PHP-FPM fallback mode.
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
    // Official FrankenPHP worker pattern (per docs):
    //   https://frankenphp.dev/docs/worker/
    //
    // The for loop with MAX_REQUESTS bounds memory leaks in long-lived
    // workers — PHP libraries were not originally designed for long-running
    // processes, so restarting after N requests is a known mitigation.
    $maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0);
    for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
        $keepRunning = frankenphp_handle_request($handler);

        // Call the garbage collector to reduce the chances of it being
        // triggered in the middle of a page generation.
        gc_collect_cycles();

        if (!$keepRunning) {
            break;
        }
    }
    $kernel->terminate();
} else {
    // PHP-FPM / CLI fallback: handle one request, emit, terminate.
    $request = ServerRequestFactory::fromGlobals();
    try {
        $response = $kernel->handle($request);
    } catch (\Throwable $e) {
        error_log(sprintf(
            '[DGLab] Uncaught %s: %s at %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));
        $response = $responseFactory->createResponse(500);
        $response->getBody()->write($isDevMode ? $e->getMessage() : 'Internal Server Error');
    }
    $emitResponse($response);
    $kernel->terminate();
}
