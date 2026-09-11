<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Immutable configuration repository.
 *
 * Worker-scoped per ADR-017: a single instance is built at worker boot
 * and reused across all Pulses in that worker. Mutating the repository
 * after construction is forbidden — rebuild via {@see ConfigBuilder}
 * if configuration must change.
 *
 * @package SovereignStack\Core\Config
 */
interface ConfigInterface
{
    /**
     * Retrieve a configuration value using dot-notation.
     *
     * @param string $key Dot-separated path, e.g. "app.name" or "db.connections.primary.host".
     * @param mixed $default Returned when the key is absent. Default null.
     *
     * @return mixed The stored value, or $default when absent.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieve a configuration value, throwing when absent.
     *
     * Use this for keys whose absence is a boot-time error (DB DSN, app key, etc.).
     * Blueprint CI criterion: "Must fail to boot if a 'Required' environment
     * variable is missing."
     *
     * @param string $key Dot-separated path.
     *
     * @throws Exception\MissingConfigurationException When the key is absent.
     *
     * @return mixed The stored value. Never returns the default.
     */
    public function getOrFail(string $key): mixed;

    /**
     * Whether the key exists in the repository.
     *
     * @param string $key Dot-separated path.
     */
    public function has(string $key): bool;

    /**
     * All configuration values as a nested array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}
