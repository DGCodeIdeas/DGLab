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
use SovereignStack\Core\Providers\ProviderRegistry;
use SovereignStack\Core\Router\Router;
use SovereignStack\Core\Router\Route;
use SovereignStack\Bridge\Vanguard;
use SovereignStack\Bridge\ContractRegistry;
use SovereignStack\Bridge\WafInspector;
use SovereignStack\Bridge\DefaultDtoTransformer;
use SovereignStack\Core\Http\ResponseFactory;
use App\Controller\HelloController;
use Psr\Log\NullLogger;

// --- 1. Build the Kernel dependencies ---

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

// --- 3. Boot the Kernel ---

$kernel = new Kernel(
    containerFactory: fn () => $container,
    configFactory: fn () => new ConfigRepository([]),
    errorHandlerFactory: fn () => $errorHandler,
    providerRegistryFactory: fn () => new ProviderRegistry(),
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

// --- 4. Handle the request ---

$request = ServerRequestFactory::fromGlobals();
$response = $kernel->handle($request);

// --- 5. Emit the response ---

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
echo $response->getBody();

// --- 6. Terminate ---

$kernel->terminate();
