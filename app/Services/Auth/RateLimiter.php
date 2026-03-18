<?php

namespace DGLab\Services\Auth;

use DGLab\Core\Cache;

class RateLimiter
{
    protected Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $count = $this->cache->increment($key);
        if ($count === 1) {
            $this->cache->set($key, 1, $decaySeconds);
        }
        return $count;
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    public function resetAttempts(string $key): void
    {
        $this->cache->delete($key);
    }

    public function availableIn(string $key, int $decaySeconds): int
    {
        // Simple implementation: return decaySeconds if too many attempts
        // Real implementation would track first hit time
        return $decaySeconds;
    }
}
