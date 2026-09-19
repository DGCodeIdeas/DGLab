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
 * Security: {@see all()} returns a redacted view — keys matching
 * {@see SECRET_PATTERN} (password, secret, key, token) are replaced
 * with '***REDACTED***'. Use {@see allRaw()} for the unredacted tree
 * (trusted internal callers only — NEVER log or expose to clients).
 *
 * @package SovereignStack\Core\Config
 */
interface ConfigInterface
{
    /**
     * Regex pattern for keys whose values must be redacted in {@see all()}.
     * Matches: password, passwd, secret, key, token, authorization, cookie.
     */
    public const SECRET_PATTERN = '/password|passwd|secret|token|authorization|cookie|private_key|api_key|secret_key|\bkey\b/i';

    /**
     * Retrieve a configuration value using dot-notation.
     *
     * Returns the RAW value — secrets are NOT redacted on direct lookup.
     * Callers that log the return value MUST pass it through
     * {@see ConfigRepository::redactValue()} first.
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
     * All configuration values as a nested array, with secrets REDACTED.
     *
     * Any key matching {@see SECRET_PATTERN} is replaced with
     * '***REDACTED***'. Safe to log, serialize, or expose to diagnostic
     * endpoints. For the unredacted tree (trusted callers only), use
     * {@see allRaw()}.
     *
     * @return array<mixed, mixed>
     */
    public function all(): array;

    /**
     * All configuration values as a nested array, WITHOUT redaction.
     *
     * Returns the raw configuration tree including passwords, tokens, and
     * secrets. ONLY call this from trusted internal code that needs the
     * actual value (e.g. establishing a DB connection). NEVER log the
     * return value or expose it to clients.
     *
     * @return array<mixed, mixed>
     */
    public function allRaw(): array;
}
