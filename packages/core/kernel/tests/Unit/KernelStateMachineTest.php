<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\KernelException;
use SovereignStack\Core\Kernel\KernelInterface;
use SovereignStack\Core\Kernel\KernelState;
use SovereignStack\Core\Kernel\PanicException;

/**
 * Tests that every illegal state transition throws the correct KernelException.
 *
 * Legal transitions:
 *   Unbooted → Booting → Booted → Handling → Booted → Terminating → Terminated
 *
 * Illegal transitions (each has a named constructor in KernelException):
 *   - boot() after terminate()     → bootAfterTerminate()
 *   - boot() during boot()         → bootDuringBoot()
 *   - handle() before boot()       → handleBeforeBoot()
 *   - handle() during boot()       → handleDuringBoot()
 *   - handle() after terminate()   → handleAfterTerminate()
 *   - terminate() before boot()    → terminateBeforeBoot()
 *   - terminate() during boot()    → terminateDuringBoot()
 *   - terminate() during handling  → terminateDuringHandling()
 *   - terminate() twice            → doubleTerminate()
 */
final class KernelStateMachineTest extends TestCase
{
    public function testInitialstateIsUnbooted(): void
    {
        $kernel = TestKernelFactory::create();

        self::assertSame(KernelState::Unbooted, $kernel->getState());
    }

    public function testBootTransitionsToBooted(): void
    {
        $kernel = TestKernelFactory::create();

        $kernel->boot();

        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    public function testHandleTransitionsToHandlingAndBack(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        $request = TestKernelFactory::createServerRequest('GET', '/');
        $kernel->handle($request);

        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    public function testTerminateTransitionsToTerminated(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();

        $kernel->terminate();

        self::assertSame(KernelState::Terminated, $kernel->getState());
    }

    public function testBootAfterTerminateThrows(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();
        $kernel->terminate();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot boot() after terminate()');

        $kernel->boot();
    }

    public function testHandleBeforeBootThrows(): void
    {
        $kernel = TestKernelFactory::create();

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot handle() before boot()');

        $kernel->handle($request);
    }

    public function testHandleAfterTerminateThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();
        $kernel->terminate();

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot handle() after terminate()');

        $kernel->handle($request);
    }

    public function testTerminateBeforeBootThrows(): void
    {
        $kernel = TestKernelFactory::create();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot terminate() before boot()');

        $kernel->terminate();
    }

    public function testDoubleTerminateThrows(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();
        $kernel->terminate();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot terminate() twice');

        $kernel->terminate();
    }

    public function testBootIsIdempotent(): void
    {
        // boot() on an already-booted kernel is a no-op (not an error).
        // This allows defensive boot() calls in bootstrappers.
        $kernel = TestKernelFactory::create();
        $kernel->boot();
        $kernel->boot(); // Second call — no-op.

        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    public function testGetContainerBeforeBootThrows(): void
    {
        $kernel = TestKernelFactory::create();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot access kernel services before boot()');

        $kernel->getContainer();
    }

    public function testGetContainerAfterTerminateThrows(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();
        $kernel->terminate();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot handle() after terminate()');

        $kernel->getContainer();
    }

    /**
     * Recursive handle() (state already Handling) MUST throw
     * handleDuringHandling() — the new named exception with the
     * "Cannot handle() while already handling" message. Previously
     * this threw handleDuringBoot() with a misleading message.
     */
    public function testHandleDuringHandlingThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        // Force the kernel into the Handling state. In production this
        // arises when a middleware or controller re-enters $kernel->handle()
        // from inside the active request; using reflection here avoids the
        // ceremony of wiring a recursive middleware just to set up the
        // state machine corner case.
        $state = new \ReflectionProperty(\SovereignStack\Core\Kernel\Kernel::class, 'state');
        $state->setValue($kernel, KernelState::Handling);

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot handle() while already handling');

        $kernel->handle($request);
    }

    // --- Re-entrancy Tests (P11 closure, doctrine §4.5.2) ---

    /**
     * Re-entrancy: a bootstrapper that re-enters boot() mid-boot MUST throw
     * bootDuringBoot(). The Kernel is in the Booting state when bootstrap()
     * is called; calling boot() re-entrantly hits the Booting match arm.
     *
     * Uses a real bootstrapper that re-enters — not a reflection hack — per
     * NUCLEAR-GRADE-DOCTRINE §4.5.2 ("Each test MUST use a real bootstrapper
     * that re-enters so the test exercises the actual code path, not a
     * mocked one (P11)").
     *
     * Closes the gap identified in the doctrine: the docblock at the top of
     * this file (lines 18-25) listed this case as something the file covers,
     * but the file shipped zero actual test methods for it before this test.
     */
    public function testBootDuringBootThrows(): void
    {
        $reEnteringBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                // Re-enter boot() while the kernel is in Booting state.
                $kernel->boot();
            }
        };

        $kernel = TestKernelFactory::create($reEnteringBootstrapper);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot boot() during boot()');

        $kernel->boot();
    }

    /**
     * Re-entrancy: a bootstrapper that calls handle() mid-boot MUST throw
     * handleDuringBoot(). The Kernel is in the Booting state when bootstrap()
     * is called; calling handle() hits the Booting match arm.
     *
     * Uses a real bootstrapper per doctrine §4.5.2.
     */
    public function testHandleDuringBootThrows(): void
    {
        $reEnteringBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                $request = TestKernelFactory::createServerRequest('GET', '/');
                // Re-enter handle() while the kernel is in Booting state.
                $kernel->handle($request);
            }
        };

        $kernel = TestKernelFactory::create($reEnteringBootstrapper);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot handle() during boot()');

        $kernel->boot();
    }

    /**
     * Re-entrancy: a bootstrapper that calls terminate() mid-boot MUST throw
     * terminateDuringBoot(). The Kernel is in the Booting state when
     * bootstrap() is called; calling terminate() hits the Booting match arm.
     *
     * Uses a real bootstrapper per doctrine §4.5.2.
     */
    public function testTerminateDuringBootThrows(): void
    {
        $reEnteringBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                // Re-enter terminate() while the kernel is in Booting state.
                $kernel->terminate();
            }
        };

        $kernel = TestKernelFactory::create($reEnteringBootstrapper);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot terminate() during boot()');

        $kernel->boot();
    }

    /**
     * Re-entrancy: terminate() called while the Kernel is in the Handling
     * state MUST throw terminateDuringHandling().
     *
     * The doctrine §4.5.7 chaos scenario 8 specifies the production failure
     * shape: "Fiber A in Handling, Fiber B calls terminate(). Expected:
     * terminateDuringHandling from B's match arm, B's call fails fast, A's
     * handle continues."
     *
     * This unit test uses the same reflection pattern as the existing
     * testHandleDuringHandlingThrows test above — driving the kernel into
     * Handling state via real parallel Fibers would require middleware or
     * listener infrastructure not yet built. The reflection approach verifies
     * the throw-point itself; the chaos test (scenario 8) is the production
     * failure shape this test guards.
     *
     * Uses reflection only to set the state (not to invoke the method under
     * test) — the actual `terminate()` call goes through the real Kernel
     * state machine. This is consistent with the doctrine's allowance for
     * state-machine corner-case setup via reflection (see comment on
     * testHandleDuringHandlingThrows above).
     */
    public function testTerminateDuringHandlingThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        // Force the kernel into the Handling state. In production this
        // arises when a parallel Fiber calls terminate() while handle()
        // is mid-flight. Using reflection here avoids the ceremony of
        // wiring parallel fibers just to set up the state machine corner
        // case — the throw-point itself is what's under test.
        $state = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'state',
        );
        $state->setValue($kernel, KernelState::Handling);

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot terminate() while handling');

        $kernel->terminate();
    }

    // --- Bootstrapper Circuit Breaker Tests (P6 closure, doctrine §4.5.3) ---

    /**
     * Per doctrine §4.5.3: each BootstrapperInterface::bootstrap() call MUST
     * be wrapped in a per-bootstrapper wall-clock budget of 5 seconds.
     * Exceeding the budget throws BootstrapperTimeoutExceeded (KernelException,
     * class Permanent-Local per doctrine §2 taxonomy). The Kernel transitions
     * to Terminated via the existing catch block in boot().
     *
     * This test uses reflection to lower the timeout threshold to 0.001s so
     * the test runs fast — no point sleeping 6s in the test suite. The
     * bootstrapper sleeps 0.02s which is > 0.001s threshold → throws.
     *
     * Verifies:
     *   1. BootstrapperTimeoutExceeded is thrown when bootstrap() exceeds the
     *      per-bootstrapper wall-clock budget.
     *   2. The Kernel transitions to Terminated (via the existing catch block).
     *   3. The exception message names the offending bootstrapper class and
     *      reports both the budget and elapsed time (provenance for debugging).
     */
    public function testBootstrapperTimeoutExceededThrows(): void
    {
        $slowBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                // Sleep 0.02s — exceeds the 0.001s threshold we'll set via
                // reflection below. In production, the threshold is 5.0s
                // (Kernel::BOOTSTRAPPER_TIMEOUT_SECONDS) so this sleep would
                // be well under budget; the test artificially lowers it.
                \usleep(20_000);  // 20ms = 0.02s
            }
        };

        $kernel = TestKernelFactory::create($slowBootstrapper);

        // Lower the per-bootstrapper timeout to 0.001s for this test so the
        // 0.02s sleep triggers the timeout. The constant
        // BOOTSTRAPPER_TIMEOUT_SECONDS (5.0s) is the production hard ceiling
        // and remains unchanged — we only override the instance property.
        $timeout = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'bootstrapperTimeoutSeconds',
        );
        $timeout->setValue($kernel, 0.001);

        try {
            $kernel->boot();
            self::fail('Expected KernelException::bootstrapperTimeoutExceeded to be thrown');
        } catch (KernelException $e) {
            self::assertStringContainsString(
                'exceeded the per-bootstrapper wall-clock budget',
                $e->getMessage(),
            );
            self::assertStringContainsString('0.00s budget', $e->getMessage());
            self::assertStringContainsString('0.02s elapsed', $e->getMessage());
        }

        // Per doctrine §4.5.3: Kernel MUST transition to Terminated via the
        // existing catch block in boot(). The boot graph MUST be released.
        self::assertSame(KernelState::Terminated, $kernel->getState());
    }

    /**
     * Sanity check: a fast bootstrapper (well under the timeout) MUST NOT
     * trigger the timeout throw. This verifies the circuit breaker doesn't
     * false-positive on normal bootstrappers.
     */
    public function testBootstrapperUnderTimeoutDoesNotThrow(): void
    {
        $fastBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                // Sleep 0.001s — under the 0.01s threshold we'll set via
                // reflection below.
                \usleep(1_000);  // 1ms = 0.001s
            }
        };

        $kernel = TestKernelFactory::create($fastBootstrapper);

        $timeout = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'bootstrapperTimeoutSeconds',
        );
        $timeout->setValue($kernel, 0.01);  // 10ms threshold

        $kernel->boot();

        // Should complete normally — no throw, Kernel in Booted state.
        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    // --- PanicException Tests (P6 §6 closure, doctrine §4.5.4) ---

    /**
     * Throw-point #1 per doctrine §4.5.4: releaseReferences() detects a
     * property is unexpectedly already null while state is Booted/Handling/
     * Terminating (should be set, but isn't). Invariant violation → panic.
     *
     * Uses reflection to nullify $container while the kernel is in Booted
     * state, then calls terminate() (which calls releaseReferences()).
     * releaseReferences() should detect that $container is already null
     * and throw PanicException.
     */
    public function testPanicOnReleaseReferencesUnexpectedNullProperty(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();

        // Mangle $container to null while kernel is Booted — invariant violation.
        $container = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'container',
        );
        $container->setValue($kernel, null);

        $this->expectException(PanicException::class);
        $this->expectExceptionMessage('container is already null when releaseReferences() tried to nullify it');

        // terminate() calls releaseReferences() which should detect the violation.
        $kernel->terminate();
    }

    /**
     * Throw-point #2 per doctrine §4.5.4: a factory returns null despite
     * its non-null contract. Invariant violation → panic. Verify the
     * catch block in boot() does NOT call releaseReferences() (which
     * would throw a secondary panic and mask the original).
     */
    public function testPanicOnNullFactoryResult(): void
    {
        // Use reflection to swap containerFactory for one that returns null.
        $kernel = TestKernelFactory::create();

        $factoryProp = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'containerFactory',
        );
        $factoryProp->setValue($kernel, fn () => null);

        try {
            $kernel->boot();
            self::fail('Expected PanicException::forNullFactoryResult to be thrown');
        } catch (PanicException $e) {
            self::assertStringContainsString(
                'containerFactory returned null despite its non-null contract',
                $e->getMessage(),
            );
        }

        // Verify state transitioned to Terminated (catch block did this).
        self::assertSame(KernelState::Terminated, $kernel->getState());
    }

    /**
     * Throw-point #3 per doctrine §4.5.4: handle() enters Handling state
     * but $this->pipeline is null despite assertBooted() passing.
     * Invariant violation → panic.
     *
     * Uses reflection to nullify $pipeline while kernel is Booted (after
     * boot completed normally), then calls handle(). The handle() method's
     * pre-state-transition null check throws PanicException.
     */
    public function testPanicOnNullPipelineInHandle(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        // Nullify $pipeline while kernel is Booted — invariant violation.
        $pipeline = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'pipeline',
        );
        $pipeline->setValue($kernel, null);

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(PanicException::class);
        $this->expectExceptionMessage(
            'handle() entered Handling state but $this->pipeline is null despite assertBooted() passing',
        );

        $kernel->handle($request);
    }

    /**
     * Throw-point #4 per doctrine §4.5.4: handle()'s finally block detects
     * state was mangled during the request (state-recovery gap). Uses a
     * custom bootstrapper that injects a pipeline which mangles state when
     * its handle() method is called — simulating a parallel Fiber calling
     * terminate() mid-request.
     */
    public function testPanicOnStateRecoveryGapInHandleFinally(): void
    {
        // Custom bootstrapper that installs a pipeline which mangles the
        // kernel's state to Terminated when its handle() is called. This
        // simulates a parallel Fiber calling terminate() during the request.
        $manglingBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                // Build a fake pipeline that mutates state during handle().
                // We can't createMock() with arbitrary behavior, so use an
                // anonymous class implementing MiddlewarePipelineInterface.
                $pipeline = new class($kernel) implements \SovereignStack\Core\Http\MiddlewarePipelineInterface {
                    public function __construct(private KernelInterface $kernel) {}

                    public function pipe(\Psr\Http\Server\MiddlewareInterface|string|callable $middleware): void
                    {
                        // No-op — this fake pipeline doesn't support piping.
                    }

                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        // Mangle state to Terminated, simulating a parallel
                        // Fiber calling terminate() during the request.
                        $state = new \ReflectionProperty(
                            \SovereignStack\Core\Kernel\Kernel::class,
                            'state',
                        );
                        $state->setValue($this->kernel, KernelState::Terminated);

                        // Return a fake response so handle() proceeds to finally.
                        return new \SovereignStack\Core\Http\Response(200);
                    }
                };
                $kernel->setPipeline($pipeline);
            }
        };

        $kernel = TestKernelFactory::create($manglingBootstrapper);
        $kernel->boot();

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(PanicException::class);
        $this->expectExceptionMessage('handle() finally cannot transition state back to Booted');

        $kernel->handle($request);
    }

    /**
     * Sanity check: handle()'s finally block does NOT throw PanicException
     * when state is still Handling at the end of the request (the normal
     * case). Verifies the state-recovery-gap check doesn't false-positive.
     */
    public function testHandleFinallySucceedsWhenStateIsHandling(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        $request = TestKernelFactory::createServerRequest('GET', '/');
        $kernel->handle($request);

        // Should complete normally — Kernel in Booted state (finally ran cleanly).
        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    // --- Resource Ceilings Tests (P3 closure, doctrine §4.5.5) ---

    /**
     * Per doctrine §4.5.5: bootstrapper count hard ceiling (32 per Kernel
     * construction). Exceeding throws BootstrapperCountExceeded at
     * construction time, before any boot attempt.
     */
    public function testBootstrapperCountExceededAtConstruction(): void
    {
        // Build 33 bootstrappers (one over the 32 ceiling).
        $bootstrappers = [];
        for ($i = 0; $i < 33; $i++) {
            $bootstrappers[] = new class implements BootstrapperInterface {
                public function bootstrap(KernelInterface $kernel): void
                {
                    // No-op.
                }
            };
        }

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Bootstrapper count 33 exceeds the hard ceiling of 32');

        // Construction throws — no boot() call needed.
        TestKernelFactory::createWithBootstrappers($bootstrappers);
    }

    /**
     * Per doctrine §4.5.5: boot aggregate wall-clock budget (30s outer
     * watchdog). If the total boot time exceeds the budget, throws
     * BootAggregateTimeoutExceeded. The Kernel transitions to Terminated
     * via the existing catch block.
     *
     * Uses reflection to lower the aggregate timeout to 0.001s and a
     * bootstrapper that sleeps 0.02s to trigger the aggregate timeout
     * (the per-bootstrapper timeout is left at 5.0s so the aggregate
     * check is what fires, not the per-bootstrapper check).
     */
    public function testBootAggregateTimeoutExceeded(): void
    {
        $slowBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                \usleep(20_000);  // 20ms — under the 5s per-bootstrapper budget
            }
        };

        $kernel = TestKernelFactory::create($slowBootstrapper);

        // Lower the aggregate boot timeout to 0.001s.
        $aggregateTimeout = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'bootAggregateTimeoutSeconds',
        );
        $aggregateTimeout->setValue($kernel, 0.001);

        try {
            $kernel->boot();
            self::fail('Expected KernelException::bootAggregateTimeoutExceeded');
        } catch (KernelException $e) {
            self::assertStringContainsString('boot() exceeded the aggregate wall-clock budget', $e->getMessage());
        }

        // Kernel MUST transition to Terminated (catch block handled it).
        self::assertSame(KernelState::Terminated, $kernel->getState());
    }

    /**
     * Per doctrine §4.5.5: handle() wall-clock budget (30s). Exceeding
     * throws RequestTimeoutExceeded. The Kernel transitions to Booted
     * via the existing finally (state = Booted runs BEFORE the timeout
     * check), and the caller (worker loop) returns 503.
     *
     * Uses a bootstrapper that installs a fake pipeline which sleeps
     * 0.02s — combined with lowering the request timeout to 0.001s,
     * this triggers the timeout check in handle()'s finally.
     */
    public function testRequestTimeoutExceeded(): void
    {
        $slowPipelineBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                $pipeline = new class implements \SovereignStack\Core\Http\MiddlewarePipelineInterface {
                    public function pipe(\Psr\Http\Server\MiddlewareInterface|string|callable $middleware): void
                    {
                        // No-op.
                    }

                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        \usleep(20_000);  // 20ms — exceeds the 0.001s threshold
                        return new \SovereignStack\Core\Http\Response(200);
                    }
                };
                $kernel->setPipeline($pipeline);
            }
        };

        $kernel = TestKernelFactory::create($slowPipelineBootstrapper);
        $kernel->boot();

        // Lower the request timeout to 0.001s.
        $requestTimeout = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'requestTimeoutSeconds',
        );
        $requestTimeout->setValue($kernel, 0.001);

        $request = TestKernelFactory::createServerRequest('GET', '/');

        try {
            $kernel->handle($request);
            self::fail('Expected KernelException::requestTimeoutExceeded');
        } catch (KernelException $e) {
            self::assertStringContainsString('handle() exceeded the request wall-clock budget', $e->getMessage());
        }

        // Kernel MUST be in Booted state (finally ran, state = Booted before timeout check).
        self::assertSame(KernelState::Booted, $kernel->getState());
    }

    /**
     * Per doctrine §4.5.5: terminate() wall-clock budget (5s). Exceeding
     * throws TerminateTimeoutExceeded. The Kernel force-transitions to
     * Terminated and releaseReferences() runs anyway.
     *
     * Lowers the terminate timeout to 0.0s via reflection — the normal
     * terminate() flow (dispatch TerminateEvent + unregister error handler)
     * takes non-zero time, so any elapsed > 0.0 triggers the timeout.
     * This tests the timeout mechanism itself; the slow-listener case is
     * covered by doctrine §4.5.7 chaos tests.
     */
    public function testTerminateTimeoutExceeded(): void
    {
        $kernel = TestKernelFactory::create();
        $kernel->boot();

        // Lower the terminate timeout to 0.0s — any elapsed time exceeds it.
        $terminateTimeout = new \ReflectionProperty(
            \SovereignStack\Core\Kernel\Kernel::class,
            'terminateTimeoutSeconds',
        );
        $terminateTimeout->setValue($kernel, 0.0);

        try {
            $kernel->terminate();
            self::fail('Expected KernelException::terminateTimeoutExceeded');
        } catch (KernelException $e) {
            self::assertStringContainsString('terminate() exceeded the wall-clock budget', $e->getMessage());
        }

        // Kernel MUST be in Terminated state (finally ran, state = Terminated
        // BEFORE timeout check — releaseReferences also ran).
        self::assertSame(KernelState::Terminated, $kernel->getState());
    }

    // --- P3 Edge-Case Tests ---

    public function testGetRouterBeforeBootThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Cannot access kernel services before boot');

        $kernel->getRouter();
    }

    public function testGetLoggerBeforeBootThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();

        $this->expectException(KernelException::class);

        $kernel->getLogger();
    }

    public function testSetPipelineDuringBootedThrows(): void
    {
        $kernel = TestKernelFactory::createWithRoutes();
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->setPipeline($this->createMock(\SovereignStack\Core\Http\MiddlewarePipelineInterface::class));
    }
}
