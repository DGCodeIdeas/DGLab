<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Thrown by isEnabled() / getVariant() when the flag key is absent and no
 * default is supplied.
 *
 * @package SovereignStack\Hub\Config
 */
final class UnknownFlagException extends \RuntimeException
{
    public static function forFlag(string $flag): self
    {
        return new self(\sprintf('Unknown feature flag [%s].', $flag));
    }
}
