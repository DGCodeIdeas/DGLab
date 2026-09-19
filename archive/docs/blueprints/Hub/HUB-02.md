# PHASE HUB-02: Shared Cache Coordination

## Tier
Hub

## Component Name
Sovereign Hub Cache

## Description
A coordination layer built on `CORE-15` (Cache Abstraction) that manages shared cache pools for Hub services and Spoke applications. It introduces "Cache Tags" for bulk invalidation and "Atomic Locks" for preventing race conditions in distributed environments.

## Context7 Research
- **Depends on**: `CORE-15: Cache Abstraction`, `CORE-02: DI Container`.
- **Patterns**: Cache-Aside, Write-Through, and Distributed Locking (Redlock-inspired).
- **Drivers**: Primarily Redis for distributed state, with fallback to PSR-16 Local Cache.

## Architectural Design
- **HubCacheManager**: Factory that provides tagged cache instances.
- **TaggableStore**: Wraps PSR-16 stores to support tag-based invalidation.
- **LockManager**: Provides mutex locks to prevent "cache stampedes" and ensure single-execution of critical tasks.

### Distributed Lock Example
```php
namespace Sovereign\Hub\Cache;

interface LockInterface
{
    public function acquire(string $name, int $seconds = 0): bool;
    public function release(string $name): void;
    public function block(string $name, int $seconds, callable $callback): mixed;
}
```

## Interface Contracts

### CacheInterface (Hub Extension)
```php
namespace Sovereign\Hub\Contracts;

use Psr\SimpleCache\CacheInterface as PsrCache;

interface HubCacheInterface extends PsrCache
{
    /**
     * Return a cache instance scoped to specific tags.
     */
    public function tags(array $tags): self;

    /**
     * Invalidate all items associated with the given tags.
     */
    public function flushTags(array $tags): void;

    /**
     * Get an atomic lock instance.
     */
    public function lock(string $name, int $seconds = 0): LockInterface;
}
```

## Integration Strategy
- **Upward**: Consumes `CORE-15`.
- **Downward**: Used by `HUB-04` (Identity) for session storage and `HUB-07` (Rate Limiter) for bucket tracking.
- **Contract**: Spoke applications interact with `HubCacheInterface` for all performance-related data persistence.

## CI Verification Criteria
- **Atomic Integrity**: Locks must never be acquired by two concurrent processes on the same resource.
- **Tag Isolation**: Flashing tag `A` must not affect items associated only with tag `B`.
- **Performance**: Tag-based retrieval should add < 0.1ms overhead compared to raw PSR-16 `get()`.

## SemVer Impact
**Minor**. Adds advanced caching features to the foundational abstraction.
