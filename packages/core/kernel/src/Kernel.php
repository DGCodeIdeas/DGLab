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
            //
            // Per doctrine §4.5.4 (throw-point #2): if a factory returns null
            // despite its non-null contract (e.g., via reflection-mangled
            // factory or a buggy implementation), throw PanicException.
            // Factory contracts are non-null per Kernel::__construct docblock;
            // a null return is an invariant violation — the system is broken,
            // not the caller. Worker MUST exit non-zero per §6.2.
            $container = ($this->containerFactory)();
            /** @phpstan-ignore-next-line factory contract is non-null at the type level, but runtime violation (e.g. reflection-mangled factory) is the panic case the doctrine §4.5.4 throw-point #2 guards. */
            if ($container === null) {
                throw PanicException::forNullFactoryResult('containerFactory');
            }
            $config = ($this->configFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($config === null) {
                throw PanicException::forNullFactoryResult('configFactory');
            }
            $logger = ($this->loggerFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($logger === null) {
                throw PanicException::forNullFactoryResult('loggerFactory');
            }
            $errorHandler = ($this->errorHandlerFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($errorHandler === null) {
                throw PanicException::forNullFactoryResult('errorHandlerFactory');
            }
            $providerRegistry = ($this->providerRegistryFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($providerRegistry === null) {
                throw PanicException::forNullFactoryResult('providerRegistryFactory');
            }
            $eventDispatcher = ($this->eventDispatcherFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($eventDispatcher === null) {
                throw PanicException::forNullFactoryResult('eventDispatcherFactory');
            }
            $router = ($this->routerFactory)();
            /** @phpstan-ignore-next-line see containerFactory note above. */
            if ($router === null) {
                throw PanicException::forNullFactoryResult('routerFactory');
            }

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
            //
            // Per doctrine §4.5.4: if the caught exception is itself a
            // PanicException (invariant violation — system is broken), skip
            // releaseReferences() and rethrow immediately. releaseReferences()
            // could itself throw a secondary PanicException (if a property is
            // unexpectedly already null), which would mask the original panic
            // and confuse the operator. The state transition is still done
            // because it's a simple assignment that can't throw.
            $this->state = KernelState::Terminated;
            if (!$e instanceof PanicException) {
                $this->releaseReferences();
            }
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
        // Per doctrine §4.5.4 (throw-point #3): if $this->pipeline is null
        // despite assertBooted() passing, that's an invariant violation —
        // HttpBootstrapper (or equivalent) failed to call setPipeline()
        // during boot. Throw PanicException (class Panic per §2) — the
        // system is broken, not the caller. Previously threw \LogicException
        // which the catch block in boot() would treat as Permanent-Local
        // (caller error) — wrong classification.
        $pipeline = $this->pipeline ?? throw PanicException::forNullPipelineInHandlingState();

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
            // Per doctrine §4.5.4 (throw-point #4): state-recovery gap. If
            // $this->state was mangled during the request (e.g., a parallel
            // Fiber called terminate() and transitioned to Terminating),
            // unconditionally setting it back to Booted would HIDE the
            // invariant violation. Detect it: if state isn't Handling when
            // we get here, throw PanicException instead of silently
            // overwriting it.
            /** @phpstan-ignore-next-line PHPStan infers $this->state is Handling (set above), but runtime mutation by a parallel Fiber is exactly the panic case the doctrine §4.5.4 throw-point #4 guards. */
            if ($this->state !== KernelState::Handling) {
                throw PanicException::forStateRecoveryGap(
                    KernelState::Handling->value,
                    $this->state->value,
                );
            }
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
            //
            // Order: releaseReferences() runs FIRST (while state is still
            // Terminating) so the invariant check in releaseReferences()
            // can detect reflection-mangled properties per doctrine §4.5.4
            // throw-point #1. State transitions to Terminated AFTER — this
            // distinguishes terminate()'s release path from boot()'s catch
            // path (where state is already Terminated when releaseReferences
            // runs, so the check doesn't fire — boot failure can legitimately
            // leave some properties null).
            $this->releaseReferences();
            $this->state = KernelState::Terminated;
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
        // Per doctrine §4.5.4 (throw-point #1): if a property is unexpectedly
        // already null when releaseReferences() tries to nullify it, that's
        // an invariant violation — the property should be set in Booted/
        // Handling/Terminating states (releaseReferences is called from
        // boot()'s catch block and from terminate()). A null property in
        // those states means external code mangled the kernel's properties
        // via reflection, OR releaseReferences was called twice (which the
        // state machine prevents). Either way: panic.
        //
        // Skip the check for $this->pipeline — it's set conditionally
        // (only by HttpBootstrapper) so it may legitimately be null if
        // boot failed before the bootstrapper ran.
        // Per doctrine §4.5.4 (throw-point #1): if a property is unexpectedly
        // already null when releaseReferences() tries to nullify it, that's
        // an invariant violation. Check fires for Booted/Handling/Terminating
        // states (where properties should be set). Terminated is excluded
        // because releaseReferences() has already nulled them on the first
        // call (terminate()'s finally calls releaseReferences() BEFORE
        // transitioning state to Terminated, so state is Terminating when
        // the check runs). boot()'s catch block sets state to Terminated
        // BEFORE calling releaseReferences() — so the check doesn't fire
        // for boot-failure paths (where some properties might legitimately
        // be null because boot failed mid-way).
        if ($this->state === KernelState::Booted
            || $this->state === KernelState::Handling
            || $this->state === KernelState::Terminating
        ) {
            foreach (['container', 'config', 'logger', 'errorHandler', 'eventDispatcher', 'router'] as $prop) {
                if ($this->$prop === null) {
                    throw PanicException::forUnexpectedNullProperty($prop, $this->state->value);
                }
            }
        }

        $this->container = null;
        $this->config = null;
        $this->logger = null;
        $this->errorHandler = null;
        $this->eventDispatcher = null;
        $this->router = null;
        $this->pipeline = null;
    }
}
