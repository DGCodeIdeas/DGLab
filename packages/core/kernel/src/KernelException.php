<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

/**
 * Thrown when the Kernel is asked to perform an action that is illegal
 * given its current state.
 *
 * Each named constructor corresponds to a specific illegal transition,
 * making the exception self-documenting and easy to assert on in tests.
 *
 * Frozen per SDLC-AGRD §2.1.
 */
class KernelException extends \RuntimeException
{
    public static function bootAfterTerminate(): self
    {
        return new self(
            'Cannot boot() after terminate(). The kernel has been shut down and cannot be restarted. '
            . 'Create a new Kernel instance for a fresh lifecycle.',
        );
    }

    public static function handleBeforeBoot(): self
    {
        return new self(
            'Cannot handle() before boot(). Call boot() first to initialize the container, '
            . 'register service providers, and run bootstrappers.',
        );
    }

    public static function handleAfterTerminate(): self
    {
        return new self(
            'Cannot handle() after terminate(). The kernel has been shut down.',
        );
    }

    public static function handleDuringBoot(): self
    {
        return new self(
            'Cannot handle() during boot(). The kernel is still initializing — wait for boot() to return.',
        );
    }

    public static function handleDuringHandling(): self
    {
        return new self(
            'Cannot handle() while already handling a request. Recursive handle() calls are not allowed '
            . '— the kernel is not re-entrant within a single request. Wait for the outer handle() to return.',
        );
    }

    public static function accessBeforeBoot(): self
    {
        return new self(
            'Cannot access kernel services before boot(). Call boot() first to initialize the container, '
            . 'register service providers, and run bootstrappers.',
        );
    }

    public static function terminateBeforeBoot(): self
    {
        return new self(
            'Cannot terminate() before boot(). The kernel was never started; there is nothing to terminate.',
        );
    }

    public static function doubleTerminate(): self
    {
        return new self(
            'Cannot terminate() twice. The kernel has already been shut down.',
        );
    }

    public static function bootDuringBoot(): self
    {
        return new self(
            'Cannot boot() during boot(). Recursive boot calls are not allowed.',
        );
    }

    public static function terminateDuringBoot(): self
    {
        return new self(
            'Cannot terminate() during boot(). The kernel is still initializing.',
        );
    }

    public static function terminateDuringHandling(): self
    {
        return new self(
            'Cannot terminate() while handling a request. Wait for handle() to return.',
        );
    }
}
