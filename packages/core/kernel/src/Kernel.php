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
    /**
     * Per doctrine §4.5.3: hard ceiling for the per-bootstrapper wall-clock
     * budget. Frozen — changing this is a SemVer-major break because callers
     * (worker supervisors, deployment scripts) depend on the documented
     * 5s ceiling for their own process-level watchdogs
     * (e.g., systemd TimeoutStartSec=30s for the aggregate boot budget).
     */
    public const BOOTSTRAPPER_TIMEOUT_SECONDS = 5.0;

    /**
     * Per-bootstrapper wall-clock budget. Defaults to
     * BOOTSTRAPPER_TIMEOUT_SECONDS (the hard ceiling). Tests override via
     * reflection to a small value (0.001s) and use a sleeping bootstrapper
     * to trigger the timeout without actually waiting 5+ seconds in the
     * test suite. Production code MUST NOT modify this property — only
     * the doctrine's hard ceiling (the constant) is part of the public
     * contract.
     */
    protected float $bootstrapperTimeoutSeconds = self::BOOTSTRAPPER_TIMEOUT_SECONDS;

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

        try {
            // Initialize core dependencies via factories. Each factory returns a
            // non-null instance; we assign to local variables first so PHPStan
            // can narrow the types before storing on the nullable properties.
            $container = ($this->containerFactory)();
            $config = ($this->configFactory)();
            $logger = ($this->loggerFactory)();
            $errorHandler = ($this->errorHandlerFactory)();
            $providerRegistry = ($this->providerRegistryFactory)();
            $eventDispatcher = ($this->eventDispatcherFactory)();
            $router = ($this->routerFactory)();

            $this->container = $container;
            $this->config = $config;
            $this->logger = $logger;
            $this->errorHandler = $errorHandler;
            $this->eventDispatcher = $eventDispatcher;
            $this->router = $router;

            // Register the error handler (forces display_errors=Off per CORE-08).
            $errorHandler->register();

            // Register service providers into the container.
            $providerRegistry->registerAll($container);

            // Run bootstrappers (they wire the pipeline, router, final handler, etc.).
            // Per doctrine §4.5.3: each bootstrap() call is wrapped in a
            // per-bootstrapper wall-clock budget. Exceeding the budget throws
            // BootstrapperTimeoutExceeded (KernelException) which the catch
            // block below transitions to Terminated + releaseReferences + rethrow.
            // The bootstrapper chain is not retryable (P4) — the worker supervisor
            // MUST restart the process on boot failure, not retry boot() on the
            // same Kernel instance.
            //
            // Note: microtime() before/after only catches bootstrappers that
            // COMPLETE in > $bootstrapperTimeoutSeconds seconds (slow but not
            // infinite). Infinite loops or forever-hanging I/O are NOT caught
            // by this mechanism — production deployments need a process-level
            // watchdog (e.g., systemd TimeoutStartSec) for those. The doctrine
            // §4.5.7 chaos scenario 3 (sleep 6s) is what this guard catches.
            foreach ($this->bootstrappers as $bootstrapper) {
                $start = \microtime(true);
                $bootstrapper->bootstrap($this);
                $elapsed = \microtime(true) - $start;
                if ($elapsed > $this->bootstrapperTimeoutSeconds) {
                    throw KernelException::bootstrapperTimeoutExceeded(
                        $bootstrapper::class,
                        $elapsed,
                        $this->bootstrapperTimeoutSeconds,
                    );
                }
            }

            // Boot service providers (post-bootstrapper initialization).
            $providerRegistry->bootAll($container);

            $this->state = KernelState::Booted;

            // Dispatch BootEvent.
            $eventDispatcher->dispatch(new BootEvent($this));
        } catch (\Throwable $e) {
            // Boot failure: transition to Terminated so the Kernel is unusable
            // and can be safely discarded. Without this, the Kernel would be
            // stuck in Booting forever — no handle(), no terminate(), no recovery.
            $this->state = KernelState::Terminated;
            $this->releaseReferences();
            throw $e;
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        match ($this->state) {
            KernelState::Booted => null,
            KernelState::Unbooted => throw KernelException::handleBeforeBoot(),
            KernelState::Booting => throw KernelException::handleDuringBoot(),
            KernelState::Handling => throw KernelException::handleDuringHandling(),
            KernelState::Terminating => throw KernelException::terminateDuringBoot(),
            KernelState::Terminated => throw KernelException::handleAfterTerminate(),
        };

        // Resolve services BEFORE transitioning state — if either is null,
        // throw before $this->state = Handling (P2: state-recovery gap fix).
        $eventDispatcher = $this->eventDispatcher ?? throw $this->notInitialized('event dispatcher');
        $pipeline = $this->pipeline ?? throw new \LogicException(
            'Middleware pipeline is not configured. Register the HttpBootstrapper.',
        );

        $this->state = KernelState::Handling;

        try {
            // Dispatch RequestReceivedEvent (listeners may enrich the request).
            $requestEvent = new RequestReceivedEvent($this, $request);
            $eventDispatcher->dispatch($requestEvent);
            $request = $requestEvent->request;

            // Run the middleware pipeline.
            $response = $pipeline->handle($request);

            // Dispatch ResponseReadyEvent (listeners may transform the response).
            $responseEvent = new ResponseReadyEvent($this, $request, $response);
            $eventDispatcher->dispatch($responseEvent);
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

        try {
            $eventDispatcher = $this->eventDispatcher ?? throw $this->notInitialized('event dispatcher');
            $errorHandler = $this->errorHandler ?? throw $this->notInitialized('error handler');

            // Dispatch TerminateEvent (listeners flush logs, close connections, etc.).
            $eventDispatcher->dispatch(new TerminateEvent($this));

            // Unregister the error handler.
            $errorHandler->unregister();
        } finally {
            // Release all service references so PHP's GC can reclaim the entire
            // boot graph. This is critical for long-lived workers that re-boot
            // the Kernel (e.g., during a deploy). Without this, the old Kernel's
            // container, router, pipeline, logger, etc. leak until process exit.
            $this->state = KernelState::Terminated;
            $this->releaseReferences();
        }
    }

    public function getState(): KernelState
    {
        return $this->state;
    }

    public function getContainer(): ContainerInterface
    {
        $this->assertBooted();
        return $this->container ?? throw $this->notInitialized('container');
    }

    public function getRouter(): RouterInterface
    {
        $this->assertBooted();
        return $this->router ?? throw $this->notInitialized('router');
    }

    public function getConfig(): ConfigInterface
    {
        $this->assertBooted();
        return $this->config ?? throw $this->notInitialized('config');
    }

    public function getLogger(): DgLoggerInterface
    {
        $this->assertBooted();
        return $this->logger ?? throw $this->notInitialized('logger');
    }

    public function getErrorHandler(): ErrorHandlerInterface
    {
        $this->assertBooted();
        return $this->errorHandler ?? throw $this->notInitialized('error handler');
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
        return $this->pipeline ?? throw new \LogicException(
            'Middleware pipeline is not configured. Register the HttpBootstrapper '
            . '(or an equivalent bootstrapper that calls setPipeline()) during kernel construction.',
        );
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
            throw new KernelException(
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
        return $this->eventDispatcher ?? throw $this->notInitialized('event dispatcher');
    }

    private function assertBooted(): void
    {
        // Allow access during Booting (bootstrappers need it) and Booted/Handling.
        // Reject Unbooted (nothing initialized yet), Terminating (in teardown),
        // and Terminated (torn down).
        if ($this->state === KernelState::Unbooted) {
            // Use a service-access-specific message rather than the misleading
            // "Cannot handle() before boot()" — the caller never called handle()
            // here, they called getContainer()/getRouter()/getLogger()/etc.
            throw KernelException::accessBeforeBoot();
        }
        if ($this->state === KernelState::Terminating) {
            throw new KernelException(
                'Cannot access kernel services during terminate(). '
                . 'Listeners should not resolve services from the kernel.'
            );
        }
        if ($this->state === KernelState::Terminated) {
            throw KernelException::handleAfterTerminate();
        }
    }

    /**
     * Helper for accessor methods — thrown when a nullable property is accessed
     * before it's been initialized. This should never happen in practice
     * (assertBooted() guards the entry), but PHPStan needs proof that the
     * property is non-null.
     */
    private function notInitialized(string $property): \LogicException
    {
        return new \LogicException(
            "Kernel {$property} is not initialized. This should not happen — "
            . 'assertBooted() should have prevented this call.',
        );
    }

    /**
     * Release all service references so PHP's GC can reclaim the entire
     * boot graph (container, router, pipeline, logger, etc.).
     *
     * Called from terminate() and from boot()'s catch block. The factory
     * closures are NOT nulled — they're cheap and needed if the Kernel is
     * re-booted (though that's currently prevented by the state machine).
     */
    private function releaseReferences(): void
    {
        $this->container = null;
        $this->config = null;
        $this->logger = null;
        $this->errorHandler = null;
        $this->eventDispatcher = null;
        $this->router = null;
        $this->pipeline = null;
    }
}
