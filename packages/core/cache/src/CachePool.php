<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * PSR-6 cache pool over a pluggable {@see AdapterInterface}.
 *
 * The pool owns:
 *  - one {@see AdapterInterface} instance (the backend),
 *  - a map of deferred {@see CacheItemInterface} instances keyed by key,
 *  - a map of in-flight {@see CacheItem} instances from the current
 *    request (so repeated getItem() calls within a request do not
 *    re-hit the adapter).
 *
 * The pool is the only writer to the adapter. Application code that
 * wants to write to the cache must go through {@see self::save()} or
 * {@see self::saveDeferred()} + {@see self::commit()}; it cannot call
 * the adapter directly.
 *
 * Null-is-a-valid-value note: the reference implementation in
 * CORE-15.md uses `$isHit = $value !== null` in getItem(). That
 * pattern collapses "cached null" into "miss", which violates the
 * AdapterInterface docblock ("callers that need to distinguish cached
 * null from miss should use has() before get(), or use the
 * CachePool's getItem() which returns a CacheItem with an explicit
 * isHit() flag"). This implementation uses a "lazy-has" pattern: when
 * the adapter returns null, the pool calls has() to disambiguate
 * stored-null from miss. Non-null values do not incur the extra
 * has() call. This is a minimal, contract-faithful deviation from
 * the reference implementation.
 */
final class CachePool implements CacheItemPoolInterface
{
    /** @var array<string, CacheItemInterface> */
    private array $deferred = [];

    /** @var array<string, CacheItem> */
    private array $inflight = [];

    public function __construct(
        private readonly AdapterInterface $adapter
    ) {
    }

    /**
     * Fetch a cache item by key.
     *
     * Returns a {@see CacheItem} that is either a hit (the adapter had
     * a fresh value) or a miss (the adapter did not, or returned null
     * AND has() returned false). On a miss, the returned item's value
     * is null and isHit() is false; the caller typically sets a value
     * and calls {@see self::save()} or {@see self::saveDeferred()}.
     *
     * @param string $key The cache key.
     *
     * @return CacheItem
     *
     * @throws PsrInvalidArgumentException If the key is empty or
     *         does not match [A-Za-z0-9_:.]{1,255}.
     */
    public function getItem(string $key): CacheItem
    {
        $this->validateKey($key);

        if (isset($this->inflight[$key])) {
            return $this->inflight[$key];
        }

        $value = $this->adapter->get($key);
        // Lazy-has disambiguation: stored-null from miss. Only pay the
        // extra adapter.has() round-trip when the value is null.
        $isHit = $value !== null || $this->adapter->has($key);

        $item = new CacheItem($key, $value, $isHit);
        $this->inflight[$key] = $item;
        return $item;
    }

    /**
     * Fetch a batch of cache items.
     *
     * Returns an iterable of {@see CacheItem} keyed by key. Adapters
     * that support batch reads (RedisAdapter MGET) can override this
     * in a future subclass; the default implementation calls
     * {@see self::getItem()} for each key, populating the inflight
     * cache as a side effect.
     *
     * @param list<string> $keys
     *
     * @return iterable<string, CacheItem>
     *
     * @throws PsrInvalidArgumentException If any key is invalid.
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    /**
     * Whether the pool has a fresh value for $key.
     *
     * Delegates to the adapter. Does not populate the inflight cache.
     */
    public function hasItem(string $key): bool
    {
        $this->validateKey($key);
        return $this->adapter->has($key);
    }

    /**
     * Clear all items from the pool.
     *
     * Delegates to the adapter's clear() (which may refuse, see
     * {@see AdapterInterface::clear()}). Also resets the inflight and
     * deferred maps. Returns true only if the adapter reports success.
     */
    public function clear(): bool
    {
        $this->inflight = [];
        $this->deferred = [];
        return $this->adapter->clear();
    }

    /**
     * Delete a single item.
     *
     * @return bool True if the key was deleted or did not exist;
     *              false on backend failure.
     */
    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);
        unset($this->inflight[$key], $this->deferred[$key]);
        return $this->adapter->delete($key);
    }

    /**
     * Delete a batch of items.
     *
     * @param list<string> $keys
     *
     * @return bool True if all keys were deleted; false if any
     *              deletion failed (others may still have succeeded).
     */
    public function deleteItems(array $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $this->validateKey($key);
            unset($this->inflight[$key], $this->deferred[$key]);
            $ok = $this->adapter->delete($key) && $ok;
        }
        return $ok;
    }

    /**
     * Save a {@see CacheItem} immediately.
     *
     * Writes the item's value to the adapter with the item's TTL.
     * Updates the inflight cache so a subsequent getItem() returns
     * the saved value without an adapter round-trip.
     *
     * @param CacheItemInterface $item
     *
     * @return bool True on success, false on backend failure.
     *
     * @throws PsrInvalidArgumentException If the item's key is invalid.
     */
    public function save(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->validateKey($key);

        $ttl = $item instanceof CacheItem ? $item->getTtl() : null;

        $ok = $this->adapter->set($key, $item->get(), $ttl);

        if ($ok) {
            // The adapter has the value; reflect this in inflight.
            $this->inflight[$key] = $item instanceof CacheItem
                ? $item->asHit()
                : new CacheItem($key, $item->get(), true);
            unset($this->deferred[$key]);
        }

        return $ok;
    }

    /**
     * Queue a {@see CacheItem} for batched save at commit() time.
     *
     * Does not call the adapter. The item is held in memory; if the
     * request ends without commit(), the deferred writes are lost.
     * This is intentional — deferred saves are an optimisation, not
     * a durability guarantee.
     *
     * @return bool Always true (the queue cannot fail).
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->validateKey($key);
        $this->deferred[$key] = $item;
        return true;
    }

    /**
     * Flush all deferred saves to the adapter.
     *
     * Iterates the deferred map and calls {@see self::save()} for each
     * item. Returns true only if every save succeeded. The deferred
     * map is cleared on return, regardless of success — partial
     * commits are not retried (a failed save is lost; the caller can
     * detect the failure via the return value).
     */
    public function commit(): bool
    {
        $ok = true;
        foreach ($this->deferred as $item) {
            $ok = $this->save($item) && $ok;
        }
        $this->deferred = [];
        return $ok;
    }

    /**
     * Validate a cache key against the allowed character set.
     *
     * Allowed: A-Z a-z 0-9 _ : .
     * Length: 1–255 characters.
     *
     * @throws PsrInvalidArgumentException If the key is empty, too
     *         long, or contains characters outside the allowed set.
     */
    private function validateKey(string $key): void
    {
        if ($key === '' || strlen($key) > 255) {
            throw new InvalidArgumentException(
                sprintf('Cache key length must be 1-255, got %d.', strlen($key))
            );
        }
        if (!preg_match('/^[A-Za-z0-9_:.]+$/', $key)) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" contains characters outside [A-Za-z0-9_:.].', $key)
            );
        }
    }
}
