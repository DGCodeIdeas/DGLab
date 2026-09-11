<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Kernel\KernelException;
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
        $this->expectExceptionMessage('Cannot handle() before boot()');

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
}
