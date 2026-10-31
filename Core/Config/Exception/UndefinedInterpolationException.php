<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Exception;

/**
 * Thrown when a ${VAR} interpolation references an undefined variable
 * AND the builder is in strict-interpolation mode.
 *
 * Non-strict mode leaves the ${VAR} literal in place — useful for
 * configs that may be resolved at runtime rather than boot time.
 */
class UndefinedInterpolationException extends \RuntimeException
{
    public static function forVariable(string $varName, string $context): self
    {
        return new self(
            "Interpolation references undefined variable '\${$varName}' in: {$context}",
        );
    }
}
