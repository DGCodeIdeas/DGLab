<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Thrown by ConfigOverrideRepositoryInterface::set() when the override key
 * is not present in CORE-10's frozen schema (prevents shadow-config).
 *
 * @package SovereignStack\Hub\Config
 */
final class InvalidOverrideKeyException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(\sprintf(
            'Config override key [%s] does not exist in the frozen CORE-10 schema. Shadow-config is not allowed.',
            $key,
        ));
    }

    public static function secretRejected(string $key): self
    {
        return new self(\sprintf(
            'Config override key [%s] matches a secret pattern (password|secret|key|token). Use HUB-20 (Vault) for per-tenant secrets.',
            $key,
        ));
    }
}
