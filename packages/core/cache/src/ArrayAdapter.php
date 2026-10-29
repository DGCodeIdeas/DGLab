<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

/**
 * In-process cache backed by a PHP array.
 *
 * Values are stored as PHP values (no serialisation — they are already
 * in memory). TTL is enforced via a parallel `expiresAt` map checked on
 * every get() and has(). clear() resets both maps.
 *
 * This adapter is the default in tests and the recommended L1 cache for
 * hot, single-process data in production. It is NOT shared across
 * processes — a second PHP-FPM worker cannot see writes from the first.
 * For cross-process caching, use {@see RedisAdapter} (or wrap with
 * HUB-02).
 *
 * Per CORE-15 §"Security Properties": this adapter stores values
 * directly without serialisation (no `unserialize()` ever — the PHP
 * object-injection CVE vector is closed by construction here).
 *
 * Null-is-a-valid-value note: the reference implementation in
 * CORE-15.md uses `isset($this->values[$key])` in get() and
 * `return $this->get($key) !== null` in has(). That pattern collapses
 * "cached null" into "miss", which violates CORE-15 §"Interface
 * Contracts" AdapterInterface docblock ("callers that need to
 * distinguish cached null from miss should use has() before get()").
 * This implementation uses `array_key_exists` in get() and a direct
 * existence+expiry check in has() so a stored null is correctly
 * reported as present. This is a minimal, contract-faithful deviation
 * from the reference implementation.
 */
final class ArrayAdapter implements AdapterInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, int> */
    private array $expiresAt = [];

    public function get(string $key): mixed
    {
        $this->validateKey($key);
        if (!array_key_exists($key, $this->values)) {
            return null;
        }
        if (isset($this->expiresAt[$key]) && $this->expiresAt[$key] <= time()) {
            unset($this->values[$key], $this->expiresAt[$key]);
            return null;
        }
        return $this->values[$key];
    }

    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        $this->validateKey($key);
        if ($ttl !== null && $ttl <= 0) {
            unset($this->values[$key], $this->expiresAt[$key]);
            return true;
        }
        $this->values[$key] = $value;
        if ($ttl === null) {
            unset($this->expiresAt[$key]);
        } else {
            $this->expiresAt[$key] = time() + $ttl;
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->values[$key], $this->expiresAt[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        if (!array_key_exists($key, $this->values)) {
            return false;
        }
        if (isset($this->expiresAt[$key]) && $this->expiresAt[$key] <= time()) {
            return false;
        }
        return true;
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->expiresAt = [];
        return true;
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
