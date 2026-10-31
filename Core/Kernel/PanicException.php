<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

/**
 * Thrown when the Kernel detects an invariant violation — i.e., the system
 * itself is broken, not just the caller doing something wrong.
 *
 * Per NUCLEAR-GRADE-DOCTRINE §2 taxonomy, PanicException is class Panic:
 *   - NOT retryable
 *   - Trips the circuit breaker immediately
 *   - Operator is paged via ISPOKE-17
 *   - Worker process MUST exit non-zero per §6.2 panic procedure
 *
 * Distinguished from KernelException (which is class Permanent-Local — the
 * caller did something wrong, the system is fine). KernelException covers the
 * 9 illegal state transitions; PanicException covers the 4 invariant-violation
 * paths identified in doctrine §4.5.4:
 *
 *   1. releaseReferences() detects a property is unexpectedly already null
 *      while state is Booted/Handling (should be set, but isn't).
 *   2. boot() completes a factory call but the returned instance is null
 *      (factory contract is non-null; null return = invariant violation).
 *   3. handle() enters Handling state but $this->pipeline is null despite
 *      assertBooted() passing (bootstrapper didn't wire the pipeline).
 *   4. handle()'s finally block detects state was mangled during the request
 *      (state-recovery gap — e.g., a parallel Fiber called terminate()).
 *
 * Frozen per SDLC-AGRD §2.1 on first implementation (this commit). Adding
 * PanicException is a SemVer-minor change (additive — no existing throw-point
 * changes class from Permanent-Local to Panic). Per FROZEN-CONTRACTS.md's
 * doctrine-imposed constraints section, the 4 throw-points this exception
 * covers MUST remain PanicException throws — downgrading any of them to
 * KernelException is SemVer-major.
 *
 * @package SovereignStack\Core\Kernel
 */
class PanicException extends \RuntimeException
{
    /**
     * Construct a PanicException for an invariant violation.
     *
     * @param string $invariant  Short description of the violated invariant.
     * @param string $context    Additional context (state, property name, etc.).
     */
    public static function forInvariantViolation(string $invariant, string $context = ''): self
    {
        $message = "Panic: invariant violation — {$invariant}";
        if ($context !== '') {
            $message .= " (context: {$context})";
        }
        $message .= '. Worker MUST exit non-zero per doctrine §6.2; ISPOKE-17 paged.';
        return new self($message);
    }

    /**
     * Specific factory: a factory returned null despite its non-null contract.
     *
     * @param string $factoryName  Human-readable name of the offending factory.
     */
    public static function forNullFactoryResult(string $factoryName): self
    {
        return self::forInvariantViolation(
            "{$factoryName} returned null despite its non-null contract",
            'factory contracts are non-null per Kernel::__construct docblock',
        );
    }

    /**
     * Specific factory: a property is unexpectedly already null when
     * releaseReferences() tries to nullify it.
     *
     * @param string $propertyName  The property name (e.g. 'container').
     * @param string $stateValue     The Kernel state when the violation was detected.
     */
    public static function forUnexpectedNullProperty(string $propertyName, string $stateValue): self
    {
        return self::forInvariantViolation(
            "{$propertyName} is already null when releaseReferences() tried to nullify it",
            "state={$stateValue} (property should be set in Booted/Handling states)",
        );
    }

    /**
     * Specific factory: handle()'s finally block detected the state was
     * mangled during the request (state-recovery gap).
     *
     * Parameters are typed `mixed` (not `string`) because KernelState->value
     * returns `int|string` per PHPStan strict rules (the union of all backed
     * enum value types), even though KernelState is string-backed at runtime.
     * The internal cast handles both cases safely.
     *
     * @param mixed $expectedState  The state we expected to be in (Handling).
     * @param mixed $actualState     The state we found when transitioning back.
     */
    public static function forStateRecoveryGap(mixed $expectedState, mixed $actualState): self
    {
        /** @phpstan-ignore-next-line mixed→string cast is safe — backed enum value is int|string, both cast cleanly to string for the message. */
        $expected = (string) $expectedState;
        /** @phpstan-ignore-next-line see above. */
        $actual = (string) $actualState;
        return self::forInvariantViolation(
            'handle() finally cannot transition state back to Booted',
            "expected={$expected}, actual={$actual} (state was mangled during request)",
        );
    }

    /**
     * Specific factory: handle() entered Handling state but $this->pipeline
     * is null despite assertBooted() passing (bootstrapper didn't wire pipeline).
     */
    public static function forNullPipelineInHandlingState(): self
    {
        return self::forInvariantViolation(
            'handle() entered Handling state but $this->pipeline is null despite assertBooted() passing',
            'HttpBootstrapper (or equivalent) failed to call setPipeline() during boot',
        );
    }
}
