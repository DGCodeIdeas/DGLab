# Rate Limiter — Design Specification (Pass 2)

**Status:** Specification only. No implementation authorized.

## Design

### Interface

```php
interface RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;
    public function tooManyAttempts(string $key, int $maxAttempts): bool;
    public function availableIn(string $key): int;
    public function clear(string $key): void;
}
```

### Providers

| Provider | Backend | Atomicity |
|----------|---------|-----------|
| Symfony (temporary) | symfony/rate-limiter | In-memory/token bucket |
| Native (target) | MySQL atomic counter | DB-backed, distributed |

### Dependency assessment

- `symfony/rate-limiter` ^7.0 — lightweight, no heavy dependencies.
- Native: MySQL `INSERT ... ON DUPLICATE KEY UPDATE` with atomic counter.

### Conformance test outline

Shared test suite for both providers:
1. `testAttemptWithinLimit` — returns true when under limit
2. `testAttemptExceedsLimit` — returns false when over limit
3. `testDecayResetsCounter` — counter resets after decay period
4. `testClearResetsState` — clear() resets counter
5. `testAvailableIn` — returns seconds until available
6. `testConcurrentAttempts` — atomic under concurrency (native only)

### Switching/provider-selection

Config: `rate_limiter.provider = symfony|native`. Same pattern as migration runner.

### Promotion/demotion acceptance criteria

- Native provider must pass all conformance tests against real MySQL.
- Symfony provider demoted to "secondary" (not deleted).
- Config-only switch requires both deployed + compatible state.
EOF