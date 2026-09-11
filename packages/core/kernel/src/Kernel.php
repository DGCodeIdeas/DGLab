<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Config\ConfigInterface;
use SovereignStack\Core\Container\ContainerInterface;
use SovereignStack\Core\ErrorHandler\ErrorHandlerInterface;
use SovereignStack\Core\EventDispatcher\EventDispatcherInterface;
use SovereignStack\Core\Http\MiddlewarePipelineInterface;
use SovereignStack\Core\Http\MiddlewareResolverInterface;
use SovereignStack\Core\Kernel\Event\BootEvent;
use SovereignStack\Core\Kernel\Event\RequestReceivedEvent;
use SovereignStack\Core\Kernel\Event\ResponseReadyEvent;
use SovereignStack\Core\Kernel\Event\TerminateEvent;
use SovereignStack\Core\Kernel\Stub\ProviderRegistryInterface;
use SovereignStack\Core\Logger\LoggerInterface as DgLoggerInterface;
use SovereignStack\Core\Router\RouterInterface;

/**
 * The reference Kernel implementation.
 *
 * Worker-scoped per ADR-017: a single instance is built at worker boot
 * and reused across all Pulses (requests) in that worker. The handle()
 * method is designed for re-entrancy across requests but not within a
 * single request — recursive handle() calls throw.
 *
 * Dependencies are injected as factory closures so that:
 *   1. Heavy resources (container, logger, error handler) are only
 *      initialized when boot() is called, not at construction time.
 *   2. Tests can inject mock factories without booting a real worker.
 *   3. The kernel can be constructed cheaply and discarded if boot()
 *      is never called (e.g. in a CLI command that doesn't need HTTP).
 */
final class Kernel implements KernelInterface
{
    private KernelState $state = KernelState::Unbooted;

    /** @var list<BootstrapperInterface> */
    private readonly array $bootstrappers;

    /** @var callable(): ContainerInterface */
    private $containerFactory;

    /** @var callable(): ConfigInterface */
    private $configFactory;

    /** @var callable(): ErrorHandlerInterface */
    private $errorHandlerFactory;

    /** @var callable(): ProviderRegistryInterface */
    private $providerRegistryFactory;

    /** @var callable(): EventDispatcherInterface */
    private $eventDispatcherFactory;

    /** @var callable(): DgLoggerInterface */
    private $loggerFactory;

    /** @var callable(): RouterInterface */
    private $routerFactory;

    // Initialized during boot():
    private ?ContainerInterface $container = null;
    private ?ConfigInterface $config = null;
    private ?ErrorHandlerInterface $errorHandler = null;
    private ?ProviderRegistryInterface $providerRegistry = null;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?DgLoggerInterface $logger = null;
    private ?RouterInterface $router = null;
    private ?MiddlewarePipelineInterface $pipeline = null;

    /**
     * @param callable(): ContainerInterface $containerFactory
     * @param callable(): ConfigInterface $configFactory
     * @param callable(): ErrorHandlerInterface $errorHandlerFactory
     * @param callable(): ProviderRegistryInterface $providerRegistryFactory
     * @param callable(): EventDispatcherInterface $eventDispatcherFactory
     * @param callable(): DgLoggerInterface $loggerFactory
     * @param callable(): RouterInterface $routerFactory
     * @param list<BootstrapperInterface> $bootstrappers
     */
    public function __construct(
        callable $containerFactory,
        callable $configFactory,
        callable $errorHandlerFactory,
        callable $providerRegistryFactory,
        callable $eventDispatcherFactory,
        callable $loggerFactory,
        callable $routerFactory,
        array $bootstrappers = [],
    ) {
        $this->containerFactory = $containerFactory;
        $this->configFactory = $configFactory;
        $this->errorHandlerFactory = $errorHandlerFactory;
        $this->providerRegistryFactory = $providerRegistryFactory;
        $this->eventDispatcherFactory = $eventDispatcherFactory;
        $this->loggerFactory = $loggerFactory;
        $this->routerFactory = $routerFactory;
        $this->bootstrappers = $bootstrappers;
    }

    public function boot(): void
    {
        match ($this->state) {
            KernelState::Unbooted => null,
            KernelState::Booting => throw KernelException::bootDuringBoot(),
            KernelState::Booted => null, // Idempotent — boot() on an already-booted kernel is a no-op.
            KernelState::Terminating => throw KernelException::terminateDuringBoot(),
            KernelState::Terminated => throw KernelException::bootAfterTerminate(),
            KernelState::Handling => throw KernelException::handleDuringBoot(),
        };

        if ($this->state === KernelState::Booted) {
            return;
        }

        $this->state = KernelState::Booting;

        // Initialize core dependencies via factories.
        $this->container = ($this->containerFactory)();
        $this->config = ($this->configFactory)();
        $this->logger = ($this->loggerFactory)();
        $this->errorHandler = ($this->errorHandlerFactory)();
        $this->providerRegistry = ($this->providerRegistryFactory)();
        $this->eventDispatcher = ($this->eventDispatcherFactory)();
        $this->router = ($this->routerFactory)();

        // Register the error handler (forces display_errors=Off per CORE-08).
        $this->errorHandler->register();

        // Register service providers into the container.
        $this->providerRegistry->registerAll($this->container);

        // Run bootstrappers (they wire the pipeline, router, final handler, etc.).
        foreach ($this->bootstrappers as $bootstrapper) {
            $bootstrapper->bootstrap($this);
        }

        // Boot service providers (post-bootstrapper initialization).
        $this->providerRegistry->bootAll($this->container);

        $this->state = KernelState::Booted;

        // Dispatch BootEvent.
        $this->eventDispatcher->dispatch(new BootEvent($this));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        match ($this->state) {
            KernelState::Booted => null,
            KernelState::Unbooted => throw KernelException::handleBeforeBoot(),
            KernelState::Booting => throw KernelException::handleDuringBoot(),
            KernelState::Handling => throw KernelException::handleDuringBoot(),
            KernelState::Terminating => throw KernelException::terminateDuringBoot(),
            KernelState::Terminated => throw KernelException::handleAfterTerminate(),
        };

        $this->state = KernelState::Handling;

        try {
            // Dispatch RequestReceivedEvent (listeners may enrich the request).
            $requestEvent = new RequestReceivedEvent($this, $request);
            $this->eventDispatcher->dispatch($requestEvent);
            $request = $requestEvent->request;

            // Run the middleware pipeline.
            $response = $this->pipeline->handle($request);

            // Dispatch ResponseReadyEvent (listeners may transform the response).
            $responseEvent = new ResponseReadyEvent($this, $request, $response);
            $this->eventDispatcher->dispatch($responseEvent);
            $response = $responseEvent->response;

            return $response;
        } finally {
            $this->state = KernelState::Booted;
        }
    }

    public function terminate(): void
    {
        match ($this->state) {
            KernelState::Booted => null,
            KernelState::Unbooted => throw KernelException::terminateBeforeBoot(),
            KernelState::Booting => throw KernelException::terminateDuringBoot(),
            KernelState::Handling => throw KernelException::terminateDuringHandling(),
            KernelState::Terminating => throw KernelException::doubleTerminate(),
            KernelState::Terminated => throw KernelException::doubleTerminate(),
        };

        $this->state = KernelState::Terminating;

        // Dispatch TerminateEvent (listeners flush logs, close connections, etc.).
        $this->eventDispatcher->dispatch(new TerminateEvent($this));

        // Unregister the error handler.
        $this->errorHandler->unregister();

        $this->state = KernelState::Terminated;
    }

    public function getState(): KernelState
    {
        return $this->state;
    }

    public function getContainer(): ContainerInterface
    {
        $this->assertBooted();
        return $this->container;
    }

    public function getRouter(): RouterInterface
    {
        $this->assertBooted();
        return $this->router;
    }

    public function getConfig(): ConfigInterface
    {
        $this->assertBooted();
        return $this->config;
    }

    public function getLogger(): DgLoggerInterface
    {
        $this->assertBooted();
        return $this->logger;
    }

    public function getErrorHandler(): ErrorHandlerInterface
    {
        $this->assertBooted();
        return $this->errorHandler;
    }

    /**
     * The middleware pipeline (available after boot()).
     *
     * Exposed for bootstrappers that need to pipe() middleware during boot.
     *
     * @throws KernelException If called before boot().
     */
    public function getPipeline(): MiddlewarePipelineInterface
    {
        $this->assertBooted();
        if ($this->pipeline === null) {
            throw new \LogicException(
                'Middleware pipeline is not configured. Register the HttpBootstrapper '
                . '(or an equivalent bootstrapper that calls setPipeline()) during kernel construction.',
            );
        }
        return $this->pipeline;
    }

    /**
     * Set the middleware pipeline. Called by HttpBootstrapper (or equivalent)
     * during boot() to wire the pipeline with the container + router.
     *
     * Internal method — NOT part of KernelInterface. Only callable during
     * the Booting state (inside a bootstrapper).
     *
     * @internal
     */
    public function setPipeline(MiddlewarePipelineInterface $pipeline): void
    {
        if ($this->state !== KernelState::Booting) {
            throw new \LogicException(
                'setPipeline() can only be called during boot() (inside a bootstrapper). '
                . 'Current state: ' . $this->state->value,
            );
        }
        $this->pipeline = $pipeline;
    }

    /**
     * The event dispatcher (available after boot()).
     *
     * Exposed for bootstrappers that need to register listeners during boot.
     *
     * @throws KernelException If called before boot().
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        $this->assertBooted();
        return $this->eventDispatcher;
    }

    private function assertBooted(): void
    {
        // Allow access during Booting (bootstrappers need it) and Booted/Handling.
        // Reject Unbooted (nothing initialized yet) and Terminated (torn down).
        if ($this->state === KernelState::Unbooted) {
            throw KernelException::handleBeforeBoot();
        }
        if ($this->state === KernelState::Terminated) {
            throw KernelException::handleAfterTerminate();
        }
    }
}
