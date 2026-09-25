<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * In-memory ConfigOverrideRepository for depth-2 (CORE-19 DBAL implemented at depth 2; DBAL-backed when Hub reaches depth 3+).
 *
 * Stores per-tenant overrides in a PHP array keyed by [tenant_id][config_key].
 * Validates keys against a set of known schema keys (passed at construction)
 * and rejects secret patterns.
 *
 * @package SovereignStack\Hub\Config
 */
final class InMemoryConfigOverrideRepository implements ConfigOverrideRepositoryInterface
{
    private const SECRET_PATTERN = '/password|secret|key|token/i';

    /** @var array<string, array<string, mixed>> */
    private array $overrides = [];

    /**
     * @param array<string> $knownKeys The set of valid config keys (from CORE-10's frozen schema).
     */
    public function __construct(
        private readonly array $knownKeys = [],
    ) {
    }

    public function get(string $tenantId, string $key): mixed
    {
        return $this->overrides[$tenantId][$key] ?? null;
    }

    public function set(string $tenantId, string $key, mixed $value): void
    {
        if ($this->knownKeys !== [] && !in_array($key, $this->knownKeys, true)) {
            throw InvalidOverrideKeyException::forKey($key);
        }

        if (preg_match(self::SECRET_PATTERN, $key) === 1) {
            throw InvalidOverrideKeyException::secretRejected($key);
        }

        $this->overrides[$tenantId][$key] = $value;
    }

    public function delete(string $tenantId, string $key): void
    {
        unset($this->overrides[$tenantId][$key]);
    }
}
