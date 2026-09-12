<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Repository for per-tenant configuration overrides.
 *
 * At depth 2, implemented by InMemoryConfigOverrideRepository (no DBAL dependency).
 * When CORE-19 (DBAL) lands, replaced with a DBAL-backed implementation that
 * queries hub_config_overrides — the interface and HubConfigRegistry are unchanged.
 *
 * @package SovereignStack\Hub\Config
 */
interface ConfigOverrideRepositoryInterface
{
    /**
     * Get a tenant-specific override for a config key.
     *
     * @return mixed The override value, or null if no override exists.
     */
    public function get(string $tenantId, string $key): mixed;

    /**
     * Set a tenant-specific override for a config key.
     *
     * Validates the key exists in the frozen schema and is not a secret pattern.
     *
     * @throws InvalidOverrideKeyException If the key does not exist in the schema
     *                                     or matches the secret pattern.
     */
    public function set(string $tenantId, string $key, mixed $value): void;

    /**
     * Remove a tenant-specific override.
     */
    public function delete(string $tenantId, string $key): void;
}
