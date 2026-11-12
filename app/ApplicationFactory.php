<?php

declare(strict_types=1);

namespace App;

use App\Controller\HealthController;
use App\Controller\HelloController;
use App\Controller\TemplateController;
use App\Controller\Lms\CourseController;
use App\Controller\Lms\EnrollmentController;
use App\Controller\Lms\ProgressController;
use App\Controller\Lms\ModuleController;
use App\Runtime\RequestLoop;
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
use SovereignStack\Core\View\ViewFactory;
use SovereignStack\Core\View\ViewInterface;
use SovereignStack\Hub\Identity\Http\AuthMiddleware;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;
use SovereignStack\Hub\Identity\Session\RedisSessionStore;
use SovereignStack\Hub\Identity\Session\SessionStoreInterface;

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
 * Lifecycle:
 *   - ApplicationFactory::create() runs ONCE per worker (FrankenPHP worker mode)
 *     or ONCE per request (PHP-FPM fallback). Constructs all services and returns
 *     a fully composed Application in READY state.
 *   - Application::run() delegates to RequestLoop which boots the Kernel,
 *     then either:
 *     (a) enters the frankenphp_handle_request loop (worker mode), or
 *     (b) handles a single request and terminates (FPM fallback).
 *
 * Per SPEC §44: "The factory MUST NOT become a service locator." — this class
 * exposes only create(), and never leaks the container to callers.
 *
 * @package App
 */
final class ApplicationFactory
{
    /**
     * Compose the application from existing Core/Bridge components.
     *
     * Steps:
     *   1. Build Container, EventDispatcher, Logger, ErrorHandler.
     *   2. Pre-bind the services the HttpBootstrapper expects.
     *   3. Build the Vanguard (BRIDGE-01) with ContractRegistry + WafInspector.
     *   4. Register the root + /health contracts.
     *   5. Build the Kernel with a custom bootstrapper that wires Vanguard
     *      as outermost middleware + registers the hello + /health routes.
     *   6. Build RequestLoop with the Kernel.
     *   7. Return a fully composed Application in READY state.
     *
     * This method is the composition boundary per SPEC §44. It MAY depend on
     * all enabled application rings — Core, Bridge, and App — because it is
     * the root, not a runtime/domain layer.
     *
     * @throws \Throwable if composition fails; Application is cleaned up before rethrow
     */
    public static function create(): Application
    {
        $application = Application::createInstance();
        $application->markComposing();

        $log = static function (string $message, array $context = []): void {
            if (function_exists('frankenphp_log')) {
                frankenphp_log($message, FRANKENPHP_LOG_LEVEL_ERROR, $context);
            } else {
                $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_THROW_ON_ERROR) : '';
                error_log('[DGLab] ' . $message . $contextStr);
            }
        };

        try {
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

            // Register the /hello contract for template rendering.
            $contractRegistry->registerContract('/hello', new DefaultDtoTransformer());

            // Register the /health contract — used by Tengine's active health check
            // and future monitoring systems. Pass-through DTO transformer.
            $contractRegistry->registerContract('/health', new DefaultDtoTransformer());

            // Three-tier health contracts per SPEC §29 (M4 — Production Release Gate).
            $contractRegistry->registerContract('/health/live', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/health/ready', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/health/dependencies', new DefaultDtoTransformer());

            // Register LMS API contracts (ADR-024: dual transport auth).
            // Public read endpoints (discovery)
            $contractRegistry->registerContract('/api/v1/courses', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}/modules', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}/versions', new DefaultDtoTransformer());
            // Authenticated endpoints
            $contractRegistry->registerContract('/api/v1/courses', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}/publish', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}/archive', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/enrollments', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/enrollments/{id}', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/enrollments/{id}/reenroll', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/progress', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/enrollments/{id}/progress', new DefaultDtoTransformer());
            $contractRegistry->registerContract('/api/v1/courses/{id}/modules', new DefaultDtoTransformer());

            $vanguard = new Vanguard(
                contracts: $contractRegistry,
                waf: $waf,
                responseFactory: $responseFactory,
                logger: new NullLogger(),
            );

            // --- 3. Build the Kernel with the wiring bootstrapper ---
            $kernel = new Kernel(
                containerFactory: fn () => $container,
                configFactory: fn () => new ConfigRepository([
                    'view' => [
                        'driver' => 'twig',
                        'path' => __DIR__ . '/Resources/views',
                        'options' => [],
                    ],
                ]),
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

                            // Pipe the AuthMiddleware AFTER the Vanguard (after contract lookup).
                            // Dual transport: Session cookie (same-origin) + JWT Bearer (API clients).
                            $config = $kernel->getConfig();
                            $jwtVerifier = null;
                            if ($config->has('jwt.public_key')) {
                                $jwtVerifier = new \SovereignStack\Hub\Identity\Jwt\JwtVerifier($config->get('jwt.public_key'));
                            }
                            
                            $sessionStore = null;
                            if ($config->has('redis.host')) {
                                $sessionStore = new RedisSessionStore(
                                    new \Predis\Client([
                                        'scheme' => 'tcp',
                                        'host'   => $config->get('redis.host'),
                                        'port'   => $config->get('redis.port', 6379),
                                        'password' => $config->get('redis.password') ?? null,
                                        'database' => $config->get('redis.database', 0),
                                    ]),
                                    'dglab:session:',
                                    86400,
                                );
                            }
                            
                            $authMiddleware = new AuthMiddleware(
                                jwtVerifier: $jwtVerifier,
                                sessionStore: $sessionStore,
                            );
                            $pipeline->pipe($authMiddleware);

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
                            $kernel->getContainer()->bind(TemplateController::class);
                            $kernel->getContainer()->bind(CourseController::class);
                            $kernel->getContainer()->bind(EnrollmentController::class);
                            $kernel->getContainer()->bind(ProgressController::class);
                            $kernel->getContainer()->bind(ModuleController::class);

                            // Register the template rendering route.
                            $router->addRoute(new Route(
                                path: '/hello',
                                methods: ['GET'],
                                name: 'template.hello',
                                controllerClass: TemplateController::class,
                                controllerMethod: 'hello',
                            ));

                            // --- LMS API Routes ---
                            // Course endpoints
                            $router->addRoute(new Route(
                                path: '/api/v1/courses',
                                methods: ['POST'],
                                name: 'lms.courses.create',
                                controllerClass: CourseController::class,
                                controllerMethod: 'create',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses',
                                methods: ['GET'],
                                name: 'lms.courses.list',
                                controllerClass: CourseController::class,
                                controllerMethod: 'list',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}',
                                methods: ['GET'],
                                name: 'lms.courses.get',
                                controllerClass: CourseController::class,
                                controllerMethod: 'get',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}',
                                methods: ['PUT'],
                                name: 'lms.courses.update',
                                controllerClass: CourseController::class,
                                controllerMethod: 'update',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}/publish',
                                methods: ['POST'],
                                name: 'lms.courses.publish',
                                controllerClass: CourseController::class,
                                controllerMethod: 'publish',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}/archive',
                                methods: ['POST'],
                                name: 'lms.courses.archive',
                                controllerClass: CourseController::class,
                                controllerMethod: 'archive',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}/versions',
                                methods: ['GET'],
                                name: 'lms.courses.versions',
                                controllerClass: CourseController::class,
                                controllerMethod: 'versions',
                            ));

                            // Module endpoints
                            $router->addRoute(new Route(
                                path: '/api/v1/courses/{id}/modules',
                                methods: ['GET'],
                                name: 'lms.modules.list',
                                controllerClass: ModuleController::class,
                                controllerMethod: 'list',
                            ));

                            // Enrollment endpoints
                            $router->addRoute(new Route(
                                path: '/api/v1/enrollments',
                                methods: ['POST'],
                                name: 'lms.enrollments.create',
                                controllerClass: EnrollmentController::class,
                                controllerMethod: 'enroll',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/enrollments/{id}',
                                methods: ['DELETE'],
                                name: 'lms.enrollments.cancel',
                                controllerClass: EnrollmentController::class,
                                controllerMethod: 'cancel',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/enrollments/{id}/reenroll',
                                methods: ['POST'],
                                name: 'lms.enrollments.reenroll',
                                controllerClass: EnrollmentController::class,
                                controllerMethod: 'reenroll',
                            ));

                            // Progress endpoints
                            $router->addRoute(new Route(
                                path: '/api/v1/progress',
                                methods: ['POST'],
                                name: 'lms.progress.record',
                                controllerClass: ProgressController::class,
                                controllerMethod: 'record',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/enrollments/{id}/progress',
                                methods: ['GET'],
                                name: 'lms.progress.get',
                                controllerClass: ProgressController::class,
                                controllerMethod: 'get',
                            ));
                            $router->addRoute(new Route(
                                path: '/api/v1/enrollments/{id}/progress',
                                methods: ['GET'],
                                name: 'lms.progress.list',
                                controllerClass: ProgressController::class,
                                controllerMethod: 'list',
                            ));

                            // --- View subsystem integration ---
                            $config = $kernel->getConfig();
                            if ($config->has('view.driver')) {
                                $view = ViewFactory::fromConfig($config);
                                $kernel->getContainer()->instance(ViewInterface::class, $view);
                            }
                        }
                    },
                ],
            );

            $isDevMode = self::isDevMode();

            $requestLoop = new RequestLoop(
                kernel: $kernel,
                responseFactory: $responseFactory,
                log: $log,
                isDevMode: $isDevMode,
            );

            // Set kernel and request loop on Application (transitions to READY)
            $application->setKernelAndRequestLoop($kernel, $requestLoop);

            return $application;
        } catch (\Throwable $e) {
            // Composition failed - cleanup application resources and rethrow
            $application->shutdown();
            throw $e;
        }
    }

    /**
     * Determine whether the application is in dev mode (APP_ENV=dev).
     * In dev mode, 500 error responses include exception details for debugging.
     * In production mode, 500 responses return only "Internal Server Error"
     * per SPEC §27 (Safe Error Responses).
     */
    private static function isDevMode(): bool
    {
        return (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== false ? ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) : 'production') === 'dev';
    }
}