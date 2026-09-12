<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Tenant-aware, flag-aware configuration authority for the Hub and Spoke tiers.
 *
 * EXTENDS — does not replace — CORE-10's ConfigInterface. The global-default
 * layer (frozen at Kernel boot) is always consulted first; per-tenant
 * overrides are layered on top via HubConfigRegistry. Consumers that need
 * only global defaults MAY depend on ConfigInterface directly; consumers
 * that need tenant divergence or feature flags depend on this interface.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Hub\Config
 */
interface GlobalConfigInterface
{
    /**
     * Fetch a configuration value by dot-notation key, with optional tenant override.
     *
     * Resolution order:
     *   1. If $tenantId is non-null and an override exists for (tenant_id, config_key), return it.
     *   2. Otherwise, fall back to the CORE-10 frozen default.
     *   3. If neither exists and $default is supplied, return $default.
     *   4. If neither exists and $default is null, return null (depth-2 behavior).
     *
     * @param string      $key      Dot-notation key, e.g. "cache.ttl".
     * @param mixed       $default  Returned if neither override nor default exists.
     * @param string|null $tenantId ULID of the tenant whose override applies, or null for global-only.
     *
     * @return mixed The resolved value; type depends on the key.
     */
    public function get(string $key, mixed $default = null, ?string $tenantId = null): mixed;

    /**
     * Convenience wrapper around FeatureFlagManager::isEnabled() using a Context
     * derived from the current request. Returns false for unknown flags (never
     * throws) — a missing flag is treated as "off", because kill-switch callers
     * expect a bool.
     *
     * Callers that need to distinguish "off" from "unknown" MUST call
     * FeatureManagerInterface::isEnabled() directly and catch UnknownFlagException.
     */
    public function feature(string $flag): bool;
}
