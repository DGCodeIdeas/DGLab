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

    /**
     * Per doctrine §4.5.3 (bootstrapper chain circuit breaker): each
     * BootstrapperInterface::bootstrap() call MUST be wrapped in a
     * per-bootstrapper wall-clock budget of 5 seconds. Exceeding the
     * budget throws this exception (class Permanent-Local per doctrine
     * §2 taxonomy). The existing catch block in Kernel::boot() handles
     * the transition to Terminated + releaseReferences + rethrow.
     *
     * @param string $bootstrapperClass  The FQCN of the slow bootstrapper.
     * @param float  $elapsed           Wall-clock seconds the bootstrap call took.
     * @param float  $budget            The per-bootstrapper budget in seconds.
     */
    public static function bootstrapperTimeoutExceeded(
        string $bootstrapperClass,
        float $elapsed,
        float $budget,
    ): self {
        return new self(
            \sprintf(
                'Bootstrapper %s exceeded the per-bootstrapper wall-clock budget '
                . '(%.2fs budget, %.2fs elapsed). Kernel transitions to Terminated. '
                . 'The bootstrapper chain is not retryable — the worker supervisor '
                . 'MUST restart the process, not retry boot() on the same Kernel '
                . 'instance (doctrine §4.5.3, P4 — boot is not idempotent across failure).',
                $bootstrapperClass,
                $budget,
                $elapsed,
            ),
        );
    }

    /**
     * Per doctrine §4.5.5: boot aggregate wall-clock budget (30s outer
     * watchdog on top of §4.5.3's per-bootstrapper 5s budget). If the
     * total boot time exceeds 30s (even if no individual bootstrapper
     * exceeds 5s — e.g., 7 bootstrappers × 4.9s each = 34.3s), throw
     * this. Class Permanent-Local — the catch block in boot() handles
     * the transition to Terminated + releaseReferences + rethrow.
     *
     * @param float $elapsed  Wall-clock seconds the boot() call took.
     * @param float $budget   The aggregate boot budget in seconds.
     */
    public static function bootAggregateTimeoutExceeded(float $elapsed, float $budget): self
    {
        return new self(
            \sprintf(
                'boot() exceeded the aggregate wall-clock budget (%.2fs budget, %.2fs elapsed). '
                . 'Kernel transitions to Terminated. The bootstrapper chain is not retryable — '
                . 'the worker supervisor MUST restart the process (doctrine §4.5.5, P4).',
                $budget,
                $elapsed,
            ),
        );
    }

    /**
     * Per doctrine §4.5.5: handle() wall-clock budget (30s for the full
     * request lifecycle). Exceeding throws this (class Permanent-Local).
     * The Kernel transitions to Booted via the existing finally block
     * (state = Booted runs BEFORE the timeout check), and the caller
     * (worker loop) catches this and returns 503 to the client.
     *
     * @param float $elapsed  Wall-clock seconds the handle() call took.
     * @param float $budget   The request budget in seconds.
     */
    public static function requestTimeoutExceeded(float $elapsed, float $budget): self
    {
        return new self(
            \sprintf(
                'handle() exceeded the request wall-clock budget (%.2fs budget, %.2fs elapsed). '
                . 'Kernel transitions to Booted; caller SHOULD return 503 (doctrine §4.5.5).',
                $budget,
                $elapsed,
            ),
        );
    }

    /**
     * Per doctrine §4.5.5: terminate() wall-clock budget (5s for the
     * terminate event + handler unreg). Exceeding throws this (class
     * Permanent-Local). The Kernel force-transitions to Terminated and
     * releaseReferences() runs anyway (the timeout check is in the finally
     * block, AFTER releaseReferences + state = Terminated).
     *
     * @param float $elapsed  Wall-clock seconds the terminate() call took.
     * @param float $budget   The terminate budget in seconds.
     */
    public static function terminateTimeoutExceeded(float $elapsed, float $budget): self
    {
        return new self(
            \sprintf(
                'terminate() exceeded the wall-clock budget (%.2fs budget, %.2fs elapsed). '
                . 'Kernel force-transitioned to Terminated; releaseReferences() ran anyway '
                . '(doctrine §4.5.5).',
                $budget,
                $elapsed,
            ),
        );
    }

    /**
     * Per doctrine §4.5.5: bootstrapper count hard ceiling (32 per Kernel
     * construction). Exceeding throws this at construction time, before
     * any boot attempt. Class Permanent-Local — the caller passed too
     * many bootstrappers; the system is fine.
     *
     * @param int $count   The actual bootstrapper count passed.
     * @param int $ceiling  The hard ceiling (BOOTSTRAPPER_COUNT_CEILING).
     */
    public static function bootstrapperCountExceeded(int $count, int $ceiling): self
    {
        return new self(
            \sprintf(
                'Bootstrapper count %d exceeds the hard ceiling of %d per Kernel construction '
                . '(doctrine §4.5.5). The Kernel was not constructed; the caller MUST reduce '
                . 'the bootstrapper count.',
                $count,
                $ceiling,
            ),
        );
    }
}
