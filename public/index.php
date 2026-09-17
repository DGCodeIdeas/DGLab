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

// === DIAGNOSTIC INSTRUMENTATION (Task 40) ===
// Comprehensive error_log() tracing to pinpoint where execution stops.
// All output goes to STDERR → FrankenPHP captures → journald.
error_log('[DGLab] === public/index.php LOADED ===');
error_log('[DGLab] APP_ENV env: ' . ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '(unset)'));
error_log('[DGLab] frankenphp_handle_request exists: ' . (function_exists('frankenphp_handle_request') ? 'YES' : 'NO'));
error_log('[DGLab] PHP version: ' . PHP_VERSION);
error_log('[DGLab] SAPI: ' . PHP_SAPI);

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
error_log('[DGLab] === Kernel booted successfully ===');

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

/**
 * FrankenPHP worker handler.
 *
 * CRITICAL: Per the official FrankenPHP worker documentation, the handler
 * takes NO arguments. FrankenPHP calls it with zero parameters. The request
 * must be created from superglobals INSIDE the handler via
 * ServerRequestFactory::fromGlobals(). The response must be emitted via
 * echo + http_response_code() + header() — NOT returned.
 *
 * Exceptions thrown inside frankenphp_handle_request() are intercepted by
 * FrankenPHP BEFORE reaching PHP's set_exception_handler (per docs:
 * "set_exception_handler is called only when the worker script ends").
 * This try/catch is the ONLY place to capture + log + render exceptions.
 */
$handler = static function () use ($kernel, $responseFactory, $isDevMode): void {
    error_log('[DGLab] >>> handler closure ENTERED');
    @file_put_contents('/tmp/dglab-closure.log', date('c') . ' ENTERED' . "\n", FILE_APPEND);

    try {
        // Create the PSR-7 ServerRequest from superglobals.
        // FrankenPHP resets superglobals ($_GET, $_POST, $_SERVER, etc.)
        // before calling the handler.
        $request = ServerRequestFactory::fromGlobals();
        error_log('[DGLab] request: ' . $request->getMethod() . ' ' . $request->getUri()->getPath());

        $response = $kernel->handle($request);
        error_log('[DGLab] kernel->handle() returned status: ' . $response->getStatusCode());
    } catch (\Throwable $e) {
        error_log('[DGLab] !!! CAUGHT exception: ' . $e::class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        $trace = $e->getTraceAsString();
        error_log('[DGLab] Stack trace:' . PHP_EOL . $trace);

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
    error_log('[DGLab] === Dispatching: WORKER MODE (frankenphp_handle_request) ===');
    // Official FrankenPHP worker pattern (per docs):
    //   https://frankenphp.dev/docs/worker/
    //
    // The handler takes NO arguments and must NOT return a value.
    // It creates the request from superglobals, handles it, and emits
    // the response via echo + http_response_code() + header().
    //
    // frankenphp_handle_request() returns true when the worker should
    // continue (request was handled), false when the worker should exit.
    // The for loop with MAX_REQUESTS bounds memory leaks in long-lived
    // workers — PHP libraries were not originally designed for long-running
    // processes, so restarting after N requests is a known mitigation.
    $maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0);
    for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
        error_log('[DGLab] worker iteration ' . ($nbRequests + 1) . '/' . ($maxRequests ?: '∞'));
        $keepRunning = frankenphp_handle_request($handler);
        error_log('[DGLab] request handled, keepRunning=' . ($keepRunning ? 'true' : 'false'));

        // Call the garbage collector to reduce the chances of it being
        // triggered in the middle of a page generation.
        gc_collect_cycles();

        if (!$keepRunning) {
            break;
        }
    }
    error_log('[DGLab] worker loop exited (shutdown signal), terminating kernel...');
    $kernel->terminate();
} else {
    error_log('[DGLab] === Dispatching: FPM FALLBACK MODE ===');
    $request = ServerRequestFactory::fromGlobals();
    try {
        $response = $kernel->handle($request);
    } catch (\Throwable $e) {
        error_log('[DGLab] !!! CAUGHT (FPM): ' . $e::class . ': ' . $e->getMessage());
        $response = $responseFactory->createResponse(500);
        $response->getBody()->write($isDevMode ? $e->getMessage() : 'Internal Server Error');
    }
    $emitResponse($response);
    $kernel->terminate();
}
