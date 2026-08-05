# PHASE HUB-02: Shared Cache Coordination

## Tier
Hub (Shared Services)

## Resolves
`docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` Hub Weakness 2 ("Sparse Architectural Details for Cache")
— per Governance Rule 5, the fix is merged here rather than left in the standalone solutions doc. The
detailed content already exists and is genuinely good (`docs/cache-patterns/*`); it was simply never
linked from this blueprint. This file now references it directly instead of duplicating 38KB of
content inline.

## Component Name
Sovereign Hub Cache

## Description
A coordination layer built on `CORE-15` (Cache Abstraction) that manages shared cache pools for Hub
services and Spoke applications. Introduces cache tags for bulk invalidation and atomic locks for
distributed race-condition prevention.

## Build Status
🔴 **Blocked** on `CORE-02` (DI Container — zero implementation, see `01_MASTER_INDEX.md` §2) and
`CORE-15` (Cache Abstraction, not yet started).

## Dependency Status
- **Upward:** `CORE-15` (Cache Abstraction), `CORE-02` (DI Container).
- **Downward:** `HUB-04` (Identity — session storage), `HUB-07` (Rate Limiter — bucket tracking), and
  by extension most of the Hub tier (verified against `docs/hub-taxonomy/hub-blueprint-taxonomy.md` —
  no drift found for this ID).

## Architectural Design
- **HubCacheManager** — factory providing tagged cache instances.
- **TaggableStore** — wraps PSR-16 stores to support tag-based invalidation.
- **LockManager** — mutex locks preventing cache stampedes and ensuring single-execution of critical
  tasks.

```php
namespace SovereignStack\Hub\Cache;

interface LockInterface
{
    public function acquire(string $name, int $seconds = 0): bool;
    public function release(string $name): void;
    public function block(string $name, int $seconds, callable $callback): mixed;
}

interface HubCacheInterface extends \Psr\SimpleCache\CacheInterface
{
    public function tags(array $tags): self;
    public function flushTags(array $tags): void;
    public function lock(string $name, int $seconds = 0): LockInterface;
}
```

## Deep-Dive References (merged, not duplicated)
This blueprint intentionally stays at the interface-contract level. For implementation-grade detail,
this is the authoritative reading order — these documents already exist in the repo and are of good
quality; they were simply orphaned from this blueprint until now:

1. **`docs/cache-patterns/cache-invalidation-strategies.md`** — TTL (fixed/sliding/randomized),
   write-through, write-behind, cache-aside, and invalidation-by-version, with PHP implementations and
   trade-off tables. `TaggableStore` should implement invalidation-by-version for tag flushes
   specifically (fastest and simplest correct option for the "flush tag A must not affect tag B"
   CI criterion below).
2. **`docs/cache-patterns/cache-sizing-guide.md`** — working-set estimation formulas, a TTL decision
   tree by data-freshness class, and eviction-policy comparison (including a ready-to-use `redis.conf`
   snippet). Use this, not guesswork, to set default TTLs per data class in `HubCacheManager`'s config.
3. **`docs/cache-patterns/distributed-cache-consistency.md`** — eventual vs. strong consistency
   trade-offs, quorum-based (Redlock) consistency for `LockManager`, conflict resolution (LWW, CRDTs,
   version vectors), and split-brain detection/recovery for Redis Sentinel/Cluster. `LockManager`'s
   `acquire()`/`block()` implementation should follow the Redlock pattern documented here rather than a
   single-node lock, given this is meant to be safe under distributed deployment (see `DEPLOY-01`'s
   N+1 requirement).

## Integration Strategy
- **Upward:** consumes `CORE-15`.
- **Downward:** used by `HUB-04` for session storage and `HUB-07` for bucket tracking.
- **Contract:** Spoke applications interact with `HubCacheInterface` for all performance-related data
  persistence.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Locks never double-acquired on the same resource under concurrency | Integration test spawning N concurrent processes/fibers attempting `acquire()` on the same key against a real Redis instance (not a mock — lock correctness under real network timing is the property being tested); assert exactly one succeeds. |
| Tag isolation: flushing tag A doesn't affect tag B | Unit test per the invalidation-by-version strategy in `cache-invalidation-strategies.md` — assert version counters are scoped per tag. |
| Tag-based retrieval overhead vs. raw PSR-16 `get()` | Micro-benchmark on the reference runner (state PHP version, opcache state); report the actual delta once measured — the original "< 0.1ms" figure had no attached method (Finding 10) and should not be restated until re-measured against this implementation. |

## CI Verification Criteria
- Atomic integrity (Redlock-based concurrency test, above), blocking.
- Tag isolation (above), blocking.
- Split-brain behavior: a test that partitions a Redis Sentinel fixture and asserts `LockManager`
  fails safe (denies new locks) rather than allowing two nodes to both believe they hold a lock —
  directly exercising the split-brain section of `distributed-cache-consistency.md`.

## SemVer Impact
**Minor.** Adds advanced caching features to the foundational `CORE-15` abstraction.
