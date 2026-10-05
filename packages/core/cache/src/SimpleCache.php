<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrSimpleCacheInvalidArgumentException;

/**
 * PSR-16 simple cache — a thin adapter over {@see CachePool}.
 *
 * Exposes the flat `get($key, $default)`, `set($key, $value, $ttl)`,
 * `delete($key)`, `clear()`, `getMultiple()`, `setMultiple()`,
 * `deleteMultiple()`, `has($key)` API. Each method delegates to the
 * underlying pool's PSR-6 method and unwraps the {@see CacheItem} for
 * the caller.
 *
 * PSR-16 TTL semantics (per spec):
 *  - null  = no expiration
 *  - int   = seconds (0 or negative = immediate delete per PSR-16)
 *  - DateInterval = converted to seconds
 *
 * PSR-16 spec compliance: invalid keys throw
 * {@see PsrSimpleCacheInvalidArgumentException}. The package's
 * {@see InvalidArgumentException} class implements both
 * `Psr\Cache\InvalidArgumentException` and
 * `Psr\SimpleCache\InvalidArgumentException`, so the same exception
 * type can be thrown by both the PSR-6 pool path and the PSR-16
 * simple-cache path.
 *
 * Per CORE-15 §"Security Properties" invariants: backend failure is
 * signalled, not silent. The underlying pool delegates to the
 * adapter which throws {@see CacheException} on backend failure. The
 * PSR-16 spec's "return false on backend failure" is interpreted
 * strictly: a thrown `CacheException` is observable to the caller,
 * not swallowed. Callers that want fail-open semantics must
 * explicitly catch `CacheException` and degrade.
 */
final class SimpleCache implements CacheInterface
{
    public function __construct(
        private readonly CachePool $pool
    ) {
    }

    /**
     * Fetch a value from the cache, returning $default on miss.
     *
     * @param string $key     The cache key.
     * @param mixed  $default Default value to return on miss.
     *
     * @return mixed The cached value, or $default on miss.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If the key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);
        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Store a value in the cache with an optional TTL.
     *
     * @param string                   $key   The cache key.
     * @param mixed                    $value The value to store.
     * @param int|\DateInterval|null   $ttl   TTL: null = forever;
     *                                       int = seconds (0 or
     *                                       negative = delete);
     *                                       DateInterval = converted.
     *
     * @return bool True on success, false on backend failure.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If the key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $item = $this->pool->getItem($key);
        $item->set($value);
        // CacheItem::expiresAfter accepts int|\DateInterval|null directly
        // (PSR-6 CacheItemInterface signature). null clears the TTL
        // (no expiration); int = seconds; DateInterval = converted.
        $item->expiresAfter($ttl);
        return $this->pool->save($item);
    }

    /**
     * Delete a value from the cache.
     *
     * @return bool True if the value was deleted or did not exist.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If the key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function delete(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    /**
     * Wipes clean the entire cache.
     *
     * Delegates to the pool's clear(), which delegates to the
     * adapter's clear(). The {@see RedisAdapter} refuses to call
     * FLUSHDB unless explicitly constructed with `allowFlush=true`.
     */
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * Fetch multiple values from the cache.
     *
     * Returns an iterable keyed by cache key. Missing keys map to
     * $default in the result.
     *
     * @param iterable<string> $keys    List of cache keys.
     * @param mixed            $default Default value for missing keys.
     *
     * @return iterable<string, mixed>
     *
     * @throws PsrSimpleCacheInvalidArgumentException If any key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            // Delegate to get() so key validation and unwrapping are consistent.
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * Store multiple values in the cache with an optional TTL.
     *
     * @param iterable<string, mixed> $values Map of key => value.
     * @param int|\DateInterval|null  $ttl    TTL applied to all values.
     *
     * @return bool True if all values were saved; false if any failed.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If any key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            // PSR-16 spec: keys are strings. Iterable maps may yield non-string
            // keys (e.g. int from array iteration); cast to string for safety.
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }
        return $ok;
    }

    /**
     * Delete multiple values from the cache.
     *
     * @param iterable<string> $keys List of cache keys.
     *
     * @return bool True if all keys were deleted; false if any failed.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If any key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete((string) $key) && $ok;
        }
        return $ok;
    }

    /**
     * Whether the cache has a fresh value for $key.
     *
     * @return bool True if the key exists and is fresh; false otherwise.
     *
     * @throws PsrSimpleCacheInvalidArgumentException If the key is invalid.
     * @throws CacheException                          If the backend fails.
     */
    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }
}
