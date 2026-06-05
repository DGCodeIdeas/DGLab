# HUB-08.md

## Phase ID

`HUB-08`

## Tier

`Hub`

## Component Name and Description

**Rate Limiting Service** – Enforces request throttling per user, IP, or API key using token bucket algorithm. Provides a PSR‑15 middleware to protect Core endpoints.

## Context7 Research

- **PHP Best Practices**: Stateless middleware, configurable limits, avoid race conditions with atomic operations.
- **PSR‑7**: Access request attributes for identifier.
- **PSR‑11**: Service container registration of `RateLimiterInterface`.
- **PSR‑15**: Middleware implementation.
- **Design Patterns**: Strategy for limit algorithms, Decorator for response headers.
- **Performance**: Aim for < 2 ms overhead per request using Redis INCR with expiry.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\RateLimit;

use Psr\Http\Message\ServerRequestInterface; // PSR‑7
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface; // PSR‑15
use Psr\Container\ContainerInterface; // PSR‑11

interface RateLimiterInterface
{
    public function allow(string $key): bool;
    public function getRemaining(string $key): int;
}

final class RedisRateLimiter implements RateLimiterInterface
{
    private \Redis $client;
    private int $limit;
    private int $window; // seconds
    public function __construct(\Redis $client, int $limit = 100, int $window = 60)
    {
        $this->client = $client;
        $this->limit = $limit;
        $this->window = $window;
    }
    public function allow(string $key): bool
    {
        $count = $this->client->incr($key);
        if ($count === 1) {
            $this->client->expire($key, $this->window);
        }
        return $count <= $this->limit;
    }
    public function getRemaining(string $key): int
    {
        $count = (int) $this->client->get($key);
        return max(0, $this->limit - $count);
    }
}

final class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterInterface $limiter;
    public function __construct(RateLimiterInterface $limiter) { $this->limiter = $limiter; }
    public function process(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        $identifier = $request->getAttribute('user_id') ?? $request->getServerParams()['REMOTE_ADDR'];
        $key = "rl:$identifier";
        if (!$this->limiter->allow($key)) {
            $response = new \GuzzleHttp\Psr7\Response(429);
            return $response->withHeader('Retry-After', (string) $this->limiter->getRemaining($key));
        }
        return $handler($request);
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component RateLimitMiddleware {
        +process(ServerRequestInterface, callable): ResponseInterface
    }
    component RateLimiter <<interface>>
    component RedisRateLimiter <<interface>>
    RateLimitMiddleware --> RateLimiter
    RateLimiter --> RedisRateLimiter
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Added to the router pipeline (`CORE-03`) for protected routes. Configuration lives in `CORE-01`.

## CI Verification Criteria

- Unit test coverage ≥ 94% for limiter logic.
- Integration tests verify 429 response after limit breach.
- Latency overhead ≤ 2 ms per request.
- Throughput: supports ≥ 10 k requests/sec with Redis backend.

## SemVer Impact

**Minor** – Introduces throttling middleware affecting request handling.
