# PHASE HUB-07: Rate Limiter & Throttle Engine

## Tier
Hub

## Component Name
Sovereign Throttle

## Description
A high-performance rate limiting and request throttling engine. It protects Hub services and Spoke applications from abuse, brute-force attacks, and API over-consumption. It implements various algorithms like Token Bucket, Leaky Bucket, and Fixed Window.

## Context7 Research
- **Depends on**: `HUB-02: Cache`, `CORE-04: HTTP Message`.
- **Algorithms**: Token Bucket (smooth traffic), Fixed Window (simple quotas).
- **Storage**: Strictly uses `HUB-02` (Redis/APCu) for sub-millisecond counter incrementing.

## Architectural Design
- **Limiter**: Core logic for evaluating "Allowed" vs "Denied".
- **BucketStore**: Interface for persisting counter state across requests.
- **ThrottleMiddleware**: A PSR-15 middleware (extending CORE-05) that automatically applies limits based on route attributes.
- **DynamicQuota**: Resolves limits based on user roles or tenant tiers (referencing HUB-05).

### Rate Limit Attribute
```php
#[Route('/api/search', method: 'GET')]
#[Throttle(limit: 60, per: 'minute', by: 'ip')]
public function search() { ... }
```

## Interface Contracts

### RateLimiterInterface
```php
namespace Sovereign\Hub\Contracts;

interface RateLimiterInterface
{
    /**
     * Check if a specific key has exceeded its limit.
     */
    public function check(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Increment the counter for a key and return the current count.
     */
    public function hit(string $key, int $decaySeconds): int;

    /**
     * Reset the counter for a specific key.
     */
    public function clear(string $key): void;

    /**
     * Get the number of remaining attempts for a key.
     */
    public function remaining(string $key, int $maxAttempts): int;
}
```

## Integration Strategy
- **Upward**: Consumes `HUB-02` for fast state management.
- **Downward**: Applied globally via `HUB-08` (API Gateway) and individually in Spoke applications via middleware.
- **HTTP Headers**: Automatically appends `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `Retry-After`.

## CI Verification Criteria
- **Precision**: Must allow exactly 100 requests if the limit is 100, and deny the 101st request within the same window.
- **Concurrency**: Must handle 10 concurrent requests for the same key without "double-counting" errors.
- **Overhead**: Evaluating a rate limit must add < 0.2ms to total request time.

## SemVer Impact
**Minor**. Enhances system stability and security.
