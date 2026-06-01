<?php

namespace DGLab\Services\Auth;

use DGLab\Core\Cache;

/**
 * Simple Rate Limiter
 */
class RateLimiter
{
    protected Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->cache->get($key, 0) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $hits = $this->cache->get($key, 0) + 1;
        $this->cache->set($key, $hits, $decaySeconds);
        return $hits;
    }

    public function clear(string $key): void
    {
        $this->cache->forget($key);
    }
}
