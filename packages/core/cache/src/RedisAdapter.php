<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

/**
 * Redis-backed cache adapter.
 *
 * Values are JSON-encoded on write and JSON-decoded on read — never
 * `serialize()`/`unserialize()`. The historical PHP object-injection
 * CVE vector (`__wakeup`/`__destruct` invocation on
 * attacker-controlled bytes) is closed by construction.
 *
 * The constructor accepts a pre-connected `ext-redis` client
 * (`\Redis` or `\RedisCluster`); connection lifecycle is owned by
 * CORE-02 / CORE-10, not by the cache package. The constructor's
 * `allowFlush` flag defaults to false; calling clear() with
 * `allowFlush=false` throws {@see CacheException}. This guard prevents
 * a misconfigured pool from calling FLUSHDB on a shared Redis instance
 * and wiping every consumer's data.
 *
 * Per CORE-15 §"Security Properties" invariants: JSON-only
 * serialisation (no `unserialize()`), explicit `allowFlush` guard
 * (fail-closed clear), and corrupt-JSON-is-a-miss resilience.
 *
 * This adapter is OPTIONAL. The package's `composer.json` `suggest`s
 * `ext-redis`; if the extension is not loaded, simply do not construct
 * a RedisAdapter. The {@see CachePool} only references
 * {@see AdapterInterface}, so loading `CachePool` does not autoload
 * this class.
 *
 * To gracefully tolerate a missing `ext-redis` at the class-file
 * level, the constructor parameter is typed `object` (rather than the
 * union `\Redis|\RedisCluster` from the reference implementation in
 * CORE-15.md) and the concrete class is checked at runtime against
 * the string constants `\Redis::class` / `\RedisCluster::class` —
 * which compile to plain strings without autoloading. This is a
 * deliberate, minimal deviation from the reference implementation
 * to satisfy the task's "gracefully handle missing ext-redis"
 * constraint.
 */
final class RedisAdapter implements AdapterInterface
{
    /**
     * @param object $redis      A connected `\Redis` or `\RedisCluster`
     *                           instance. Typed `object` (not the union
     *                           `\Redis|\RedisCluster`) so that the class
     *                           file can be autoloaded without ext-redis
     *                           loaded; the concrete class is checked at
     *                           runtime.
     * @param bool   $allowFlush When false (default), {@see self::clear()}
     *                           throws {@see CacheException} instead of
     *                           calling FLUSHDB.
     */
    public function __construct(
        private readonly object $redis,
        private readonly bool $allowFlush = false,
    ) {
        $class = $redis::class;
        if ($class !== \Redis::class && $class !== \RedisCluster::class) {
            throw new CacheException(
                sprintf(
                    'RedisAdapter requires \\Redis or \\RedisCluster instance, got %s.',
                    $class
                )
            );
        }
    }

    public function get(string $key): mixed
    {
        $this->validateKey($key);
        $raw = $this->redis->get($key);
        if ($raw === false || $raw === null) {
            return null;
        }
        try {
            return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Corrupt value in Redis — treat as miss, not crash.
            // The corrupt value is left in Redis; the next save() overwrites it.
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        $this->validateKey($key);
        if ($ttl !== null && $ttl <= 0) {
            // PSR-16 semantics: TTL <= 0 means immediate delete.
            return (bool) $this->redis->del($key);
        }
        try {
            $raw = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new CacheException(
                sprintf('Cannot JSON-encode value for key "%s": %s', $key, $e->getMessage()),
                0,
                $e
            );
        }

        return $ttl === null
            ? (bool) $this->redis->set($key, $raw)
            : (bool) $this->redis->setex($key, $ttl, $raw);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        // PSR-16: delete returns true on success including "key did not exist".
        $this->redis->del($key);
        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        $exists = $this->redis->exists($key);
        // Redis returns int(0) when the key does not exist and int(>=1)
        // when it does. ext-redis may return false on transport failure;
        // cast through (int) so both 0 and false mean "not present".
        return (int) $exists > 0;
    }

    public function clear(): bool
    {
        if (!$this->allowFlush) {
            throw new CacheException(
                'RedisAdapter::clear() refused; construct with allowFlush=true to permit FLUSHDB.'
            );
        }
        return (bool) $this->redis->flushDb();
    }

    /**
     * Validate a cache key against the allowed character set.
     *
     * Allowed: A-Z a-z 0-9 _ : .
     * Length: 1–255 characters.
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
