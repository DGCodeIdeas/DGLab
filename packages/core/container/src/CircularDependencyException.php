<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Thrown by {@see ContainerInterface::make()} when the resolution stack
 * detects that the requested service is already being resolved higher up
 * the call chain — i.e. a circular dependency.
 *
 * The full resolution chain is preserved in the exception so callers can
 * log, display, or assert on it. The chain always ends with the id that
 * closed the cycle, e.g. [A, B, C, B] for the cycle B → C → B reached
 * while resolving A.
 */
final class CircularDependencyException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface
{
    /**
     * @param list<string>  $chain    The resolution chain, in order, ending
     *                                with the id that closed the cycle.
     * @param string|null   $message  Optional custom message; defaults to
     *                                a human-readable rendering of $chain.
     * @param int           $code     Numeric code.
     * @param \Throwable|null $previous Previous exception, if any.
     */
    public function __construct(
        private readonly array $chain,
        ?string $message = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? 'Circular dependency detected: ' . implode(' -> ', $chain),
            $code,
            $previous,
        );
    }

    /**
     * Convenience factory.
     *
     * @param list<string> $chain
     */
    public static function fromChain(array $chain): self
    {
        return new self($chain);
    }

    /**
     * @return list<string>
     */
    public function getResolutionChain(): array
    {
        return $this->chain;
    }
}