<?php

declare(strict_types=1);

namespace App;

use App\Controller\HealthController;
use App\Controller\HelloController;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use SovereignStack\Bridge\ContractRegistry;
use SovereignStack\Bridge\DefaultDtoTransformer;
use SovereignStack\Bridge\Vanguard;
use SovereignStack\Bridge\WafInspector;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\ErrorHandler\ErrorHandler;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\EventDispatcher\EventDispatcher;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\Http\ResponseFactory;
use SovereignStack\Core\Http\ServerRequestFactory;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\KernelInterface;
use SovereignStack\Core\Kernel\Stub\EmptyProviderRegistry;
use SovereignStack\Core\Logger\Logger;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\Router;

/**
 * Application composition root — extracts construction from public/index.php.
 *
 * Per SPEC-001 §44 (Phase 3 — Composition Root):
 *   "Move the composition currently performed by public/index.php into an
 *    ApplicationFactory or equivalent application-level composition module."
 *
 * Per SPEC §3: "The ApplicationFactory belongs at the application/infrastructure
 * composition boundary, NOT inside Core."
 *
 * Per SPEC §15 (Contractor Rule — Do Not Rebuild Existing Foundations): this
 * factory composes existing Core/Bridge components — it does NOT introduce
 * competing abstractions. The Kernel, Container, ErrorHandler, EventDispatcher,
 * Logger, Router, MiddlewarePipeline, and Vanguard are all preserved as-is.
 *
 * Per SPEC §58 (Existing Capabilities That MUST NOT Be Rebuilt): the existing
 * Kernel lifecycle, Router freeze, Middleware pipeline freeze, ErrorHandler,
 * Worker recycling, Blue/green deployment, and Caddy/Tengine/FrankenPHP
 * topology are all preserved — this factory merely extracts the *wiring*
 * of those components out of the entry point.
 *
 * Lifecycle (per SPEC §7):
 *   - ApplicationFactory::create() runs ONCE per worker (FrankenPHP worker mode)
 *     or ONCE per request (PHP-FPM fallback). Constructs all services, returns
 *     a new self.
 *   - ApplicationFactory::run() boots the Kernel, then either:
 *     (a) enters the frankenphp_handle_request loop (worker mode), or
 *     (b) handles a single request and terminates (FPM fallback).
 *
 * Per SPEC §44: "The factory MUST NOT become a service locator." — this class
 * holds the constructed services as private readonly fields, exposes only
 * create() + run(), and never leaks the container to callers.
 *
 * @package App
 */
final class ApplicationFactory
{
    /**
     * @param Kernel $kernel The booted Kernel (booted once per worker).
     * @param ResponseFactory $responseFactory For error fallbacks in the per-request handler.
     * @param callable $log Structured logging helper (frankenphp_log() or error_log() fallback).
     * @param bool $isDevMode Whether to expose error details in 500 responses.
     */
    private function __construct(
        private readonly Kernel $kernel,
        private readonly ResponseFactory $responseFactory,
        private readonly $log,
        private readonly bool $isDevMode,
    ) {}

    /**
     * Compose the application from existing Core/Bridge components.
     *
     * Steps (per the previous public/index.php, now extracted):
     *   1. Build Container, EventDispatcher, Logger, ErrorHandler.
     *   2. Pre-bind the services the HttpBootstrapper expects.
     *   3. Build the Vanguard (BRIDGE-01) with ContractRegistry + WafInspector.
     *   4. Register the root + /health contracts.
     *   5. Build the Kernel with a custom bootstrapper that wires Vanguard
     *      as outermost middleware + registers the hello + /health routes.
     *
     * This method is the composition boundary per SPEC §44. It MAY depend on
     * all enabled application rings — Core, Bridge, and App — because it is
     * the root, not a runtime/domain layer.
     */
    public static function create(): self
    {
        $log = static function (string $message, array $context = []): void {
            if (function_exists('frankenphp_log')) {
                frankenphp_log($message, FRANKENPHP_LOG_LEVEL_ERROR, $context);
            } else {
                $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_THROW_ON_ERROR) : '';
                error_log('[DGLab] ' . $message . $contextStr);
            }
        };

        // --- 1. Build Kernel dependencies ---
        $container    = new Container();
        $provider     = new ListenerProvider();
        $dispatcher   = new EventDispatcher($provider);
        $logger       = new Logger();
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
        $waf              = new WafInspector();
        $responseFactory  = new ResponseFactory();

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

        // --- 3. Build the Kernel with the wiring bootstrapper ---
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

                    public function bootstrap(KernelInterface $kernel): void
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

        $isDevMode = self::isDevMode();

        return new self(
            kernel: $kernel,
            responseFactory: $responseFactory,
            log: $log,
            isDevMode: $isDevMode,
        );
    }

    /**
     * Boot the Kernel and dispatch requests through it.
     *
     * Per SPEC §44: "public/index.php SHOULD primarily:
     *   1. load the application factory;
     *   2. build the application;
     *   3. invoke the kernel;
     *   4. allow the existing global error/shutdown handling to remain authoritative."
     *
     * Steps 3 + 4 happen here. Step 1 (load factory) is in public/index.php.
     * Step 2 (build) is the create() call in public/index.php.
     *
     * FrankenPHP worker mode: enters the frankenphp_handle_request loop, calling
     * the per-request $handler for each incoming request. The Kernel is booted
     * once before the loop. Worker recycling (max_requests=500) is enforced by
     * the Caddyfile.blue config per SPEC §35.
     *
     * PHP-FPM fallback: handles a single request, emits the response via SAPI
     * functions, and terminates the Kernel.
     */
    public function run(): void
    {
        // Boot once per worker (FrankenPHP) or once per request (FPM).
        $this->kernel->boot();

        // Per-request handler (closure shared by both modes).
        $handler = $this->buildRequestHandler();

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
            $this->kernel->terminate();
        } else {
            // PHP-FPM / CLI fallback: handle one request, emit, terminate.
            $request = ServerRequestFactory::fromGlobals();
            try {
                $response = $this->kernel->handle($request);
            } catch (\Throwable $e) {
                ($this->log)(sprintf('Uncaught %s: %s at %s:%d',
                    $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]);
                $response = $this->responseFactory->createResponse(500);
                $response->getBody()->write($this->isDevMode ? $e->getMessage() : 'Internal Server Error');
            }
            $this->emitResponse($response);
            $this->kernel->terminate();
        }
    }

    /**
     * Build the per-request handler closure used by frankenphp_handle_request().
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
    private function buildRequestHandler(): callable
    {
        $kernel           = $this->kernel;
        $responseFactory  = $this->responseFactory;
        $isDevMode        = $this->isDevMode;
        $log              = $this->log;

        return static function () use ($kernel, $responseFactory, $isDevMode, $log): void {
            try {
                $request  = ServerRequestFactory::fromGlobals();
                $response = $kernel->handle($request);
            } catch (\Throwable $e) {
                $log(sprintf('Uncaught %s: %s at %s:%d',
                    $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]);

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
    }

    /**
     * Emit a PSR-7 Response to the SAPI (headers + body).
     * Only used in PHP-FPM fallback mode.
     */
    private function emitResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        echo $response->getBody();
    }

    /**
     * Determine whether the application is in dev mode (APP_ENV=dev).
     * In dev mode, 500 error responses include exception details for debugging.
     * In production mode, 500 responses return only "Internal Server Error"
     * per SPEC §27 (Safe Error Responses).
     */
    private static function isDevMode(): bool
    {
        return ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') === 'dev';
    }
}
