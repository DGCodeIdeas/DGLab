<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

/**
 * Backend contract for cache adapters.
 *
 * An adapter is a thin wrapper around a storage engine (in-process
 * array, Redis, Memcached, file). It owns three responsibilities:
 *
 *  1. Serialisation. The pool passes a PHP value to set(); the adapter
 *     serialises it for the backend (JSON for Redis, native for array).
 *     The reverse on get(). The pool never sees raw bytes.
 *
 *  2. TTL enforcement. If $ttl is null, the value never expires. If
 *     $ttl is 0 or negative, the adapter MUST treat this as an
 *     immediate delete (PSR-16 semantics). If $ttl is a positive
 *     integer, the value expires after that many seconds.
 *
 *  3. Key validation. The adapter MUST reject keys that do not match
 *     the allowed pattern: [A-Za-z0-9_:.]{1,255}. This prevents Redis
 *     key injection (spaces, newlines, control characters that would
 *     break SCAN or KEYS commands) and bounds key size for memory
 *     accounting.
 *
 * Implementations MUST throw {@see CacheException} on backend failure
 * (Redis unreachable, disk full) and {@see InvalidArgumentException} on
 * invalid keys. They MUST NOT throw on cache misses — a miss is
 * signalled by returning null, not by exception.
 *
 * Package-local: not part of the public PSR-6/PSR-16 surface. Consumers
 * code against `Psr\Cache\CacheItemPoolInterface`; the adapter is a
 * wiring detail owned by the DI container.
 */
interface AdapterInterface
{
    /**
     * Fetch a value from the cache.
     *
     * @param string $key The cache key, validated against [A-Za-z0-9_:.]{1,255}.
     *
     * @return mixed The cached value, or null on miss.
     *               Callers that need to distinguish "cached null" from
     *               "miss" should use has() before get(), or use the
     *               {@see CachePool::getItem()} which returns a
     *               {@see CacheItem} with an explicit isHit() flag.
     *
     * @throws InvalidArgumentException If the key is invalid.
     * @throws CacheException           If the backend is unreachable.
     */
    public function get(string $key): mixed;

    /**
     * Store a value in the cache with an optional TTL.
     *
     * @param string   $key   The cache key, validated.
     * @param mixed    $value The value to store. Adapters that serialise
     *                        via JSON MUST reject values that cannot be
     *                        JSON-encoded (resources, closures) with
     *                        {@see CacheException}.
     * @param int|null $ttl   Time-to-live in seconds. null = forever;
     *                        0 or negative = delete the key (PSR-16
     *                        semantics). Positive = expire after N seconds.
     *
     * @return bool True on success, false on backend failure (the
     *              caller decides whether to retry or ignore).
     *
     * @throws InvalidArgumentException If the key is invalid.
     * @throws CacheException           If the value cannot be serialised.
     */
    public function set(string $key, mixed $value, ?int $ttl): bool;

    /**
     * Delete a key from the cache.
     *
     * @param string $key The cache key, validated.
     *
     * @return bool True on success (including "key did not exist"),
     *              false on backend failure.
     *
     * @throws InvalidArgumentException If the key is invalid.
     * @throws CacheException           If the backend is unreachable.
     */
    public function delete(string $key): bool;

    /**
     * Check whether a key exists and has not expired.
     *
     * @param string $key The cache key, validated.
     *
     * @return bool True if the key exists and is fresh;
     *              false if it does not exist, has expired, or
     *              the backend is unreachable (fail-closed).
     *
     * @throws InvalidArgumentException If the key is invalid.
     */
    public function has(string $key): bool;

    /**
     * Flush all keys owned by this adapter.
     *
     * For {@see ArrayAdapter}, this resets the in-memory map. For
     * {@see RedisAdapter}, this calls FLUSHDB on the selected database —
     * the constructor MUST take an explicit `allowFlush: bool` flag,
     * defaulting to false; calling clear() with allowFlush=false
     * throws {@see CacheException}. This guard prevents a misconfigured
     * pool from wiping a shared Redis instance.
     *
     * @return bool True on success, false on backend failure.
     *
     * @throws CacheException If flush is not allowed or the backend fails.
     */
    public function clear(): bool;
}
