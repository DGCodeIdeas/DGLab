<?php

declare(strict_types=1);

namespace Anvil\Web\Api;

/**
 * Immutable value object holding the outcome of an anvilctl invocation.
 */
final class AnvilResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }

    public function isOk(): bool
    {
        return $this->exitCode === 0;
    }
}
