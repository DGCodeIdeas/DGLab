# PHASE HUB-07: Rate Limiter & Throttle Engine

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) for the precision/concurrency/overhead claims.

## Component Name
Sovereign Throttle

## Description
High-performance rate limiting and request throttling, protecting Hub services and Spoke applications
from abuse, brute-force, and API over-consumption via Token Bucket, Leaky Bucket, and Fixed Window
algorithms.

## Build Status
🔴 **Blocked** on `HUB-02` (Cache) and `CORE-04` (HTTP Message) — neither implemented. Note: `HUB-04`
(Identity)'s brute-force-throttling CI criterion is itself blocked transitively on this component.

## Dependency Status
- **Upward:** `HUB-02`, `CORE-04`. *(Matches taxonomy.)*
- **Downward:** `HUB-04` (login throttling), `HUB-08` (global gateway throttling), `HUB-12`
  (webhook-dispatch throttling).

## Architectural Design
- **Limiter** — core "allowed vs. denied" evaluation.
- **BucketStore** — persists counter state across requests.
- **ThrottleMiddleware** — PSR-15 middleware (extending `CORE-05`) applying limits from route
  attributes.
- **DynamicQuota** — resolves limits by user role or tenant tier (via `HUB-05`).

```php
#[Route('/api/search', method: 'GET')]
#[Throttle(limit: 60, per: 'minute', by: 'ip')]
public function search() { /* ... */ }
```

```php
namespace SovereignStack\Hub\Contracts;

interface RateLimiterInterface
{
    public function check(string $key, int $maxAttempts, int $decaySeconds): bool;
    public function hit(string $key, int $decaySeconds): int;
    public function clear(string $key): void;
    public function remaining(string $key, int $maxAttempts): int;
}
```

## Integration Strategy
- **Upward:** consumes `HUB-02` for fast state management (see `HUB-02.md`'s Redlock-based
  `LockManager` for the atomic-increment guarantee this needs under concurrency).
- **Downward:** applied globally via `HUB-08` and individually via middleware in Spokes.
- **HTTP headers:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Exact-boundary precision | Integration test: hit a limit=100 key exactly 100 times, assert all succeed; hit a 101st, assert denial — a true boundary test, not a "roughly around 100" tolerance test. |
| No double-counting under concurrency | Integration test firing 10 concurrent requests at the same key against a real `HUB-02` Redis backend (not a mock, per the same reasoning as `HUB-02`'s lock test); assert the final count is exactly 10, not more or fewer. |
| Overhead per evaluation | State the reference environment before citing "< 0.2ms" — measure via microbenchmark once `HUB-02` exists; this is currently a target, not a result (Finding 10). |

## CI Verification Criteria
- Exact-boundary test, blocking.
- No-double-counting concurrency test, blocking.
- Overhead measured and reported with environment stated once implementable.

## SemVer Impact
**Minor.** Enhances system stability and security.
