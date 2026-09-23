<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\KernelException;
use SovereignStack\Core\Kernel\KernelInterface;
use SovereignStack\Core\Kernel\KernelState;

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
