# ADR-006: Redis as the Primary Cache Backend (HUB-02)

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

`docs/blueprints/Hub/HUB-02.md` ("Sovereign Hub Cache") specifies a coordination layer above CORE-15 (Cache Abstraction) that provides three concrete features: **Cache Tags** for bulk invalidation, **Atomic Locks** for distributed mutex / stampede protection, and **Write-Through / Read-Through** cache patterns. The HUB-02 blueprint's "Context7 Research" section names Redis as the primary distributed backend, with PSR-16 local cache (typically APCu) as a fallback. The Hub taxonomy's cache-solution-selector (`docs/hub-taxonomy/cache-solution-selector.md`) routes every distributed cache need to "HUB-02 (Redis) — sub-1ms" and every distributed-locking need to "HUB-02 (Atomic Locks) — Redlock."

Finding 19 in `00_CRITIQUE.md` flags "Why Redis over Memcached (HUB-02 assumes Redis)" as undocumented. The HUB-02 blueprint declares Redis without justifying the choice over Memcached, APCu, or a no-cache posture. This ADR records the justification. Redis also appears in two adjacent decisions: HUB-09 (Sovereign Signal / Event Bus) uses Redis Pub/Sub for real-time fan-out, and HUB-10 (Sovereign Queue) uses Redis Streams (or a Redis-backed driver) for delayed/priority job queues. This convergence — Redis as cache *and* message broker *and* session store *and* rate-limit counter store — is itself a load-bearing architectural decision.

Three forces push toward Redis. First, Cache Tags are not natively supported by Memcached (which has no concept of tag → key reverse index), and the standard workaround (a separate tag → keys map, scanned on invalidation) is racy and slow. Redis's `SET` + `SMEMBERS` pattern makes tags a first-class citizen. Second, distributed locks require compare-and-set semantics (`SET NX EX`); Memcached's `add` operation approximates this but does not support TTL-bounded atomic release, and Redlock (the de-facto distributed-lock algorithm) is implemented natively against Redis. Third, the Sovereign Stack runs across multiple PHP-FPM nodes behind a load balancer (per DEPLOY-01's rolling-update strategy), so single-node caches like APCu cannot satisfy the HUB-02 contract for distributed state.

## Decision

We adopt **Redis 7.x** as the primary cache backend for HUB-02, with APCu (via CORE-15's PSR-16 driver) as a per-node L1 fallback for ephemeral, single-process caches. The HUB-02 `HubCacheInterface` extends PSR-16's `CacheInterface` and adds `tags(array $tags): self`, `flushTags(array $tags): void`, and `lock(string $name, int $seconds = 0): LockInterface`. The Redis driver uses `phpredis` (`ext-redis`) for performance (sub-millisecond `GET`/`SET`), with `predis/predis` as a pure-PHP fallback for environments where the extension cannot be installed. Memcached is explicitly rejected as a primary backend; it remains supported by CORE-15's PSR-6/16 abstraction for legacy integrations but is not recommended.

Redis is also the message broker for HUB-09 (Pub/Sub) and HUB-10 (Queue, using Redis Streams for durability), and the session store for HUB-04 Identity. This concentrates operational responsibility on a single well-understood service.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **Memcached 1.6.x** (`ext-memcached`) | Simpler (key-value only); lower memory overhead per key; battle-tested; very fast for plain `GET`/`SET` (sub-100μs on loopback) | No native Cache Tags (would require a parallel tag→keys index, racy on invalidation); no atomic `SET NX EX` (Memcached's `add` does not combine with TTL cleanly); no native Pub/Sub (HUB-09 would need a separate broker); no Streams (HUB-10 would need a separate queue); no persistence (cache is lost on restart); 1MB value size limit | Rejected — three of HUB-02's four features (Tags, Locks, Write-Through with atomic compare-and-set) are either impossible or workarounds-only on Memcached |
| **APCu only** (`ext-apcu`, single-node shared memory) | Zero network overhead (sub-100μs reads); no daemon to operate; PSR-16 driver is trivial; perfect for ephemeral per-process caches | Single-node only — a second PHP-FPM server cannot see the cache; violates the multi-node rolling-update deployment in DEPLOY-01; no Cache Tags (would need to roll our own); no distributed locks | Rejected as primary; kept as L1 fallback for hot, ephemeral, single-process data |
| **No distributed cache (database-only)** | Zero new infrastructure; data is always consistent | Database becomes the bottleneck for every read; HUB-07 rate limiting would hit the DB on every request; HUB-04 session storage would hit the DB on every request; HUB-02's stated < 0.1ms tag-overhead budget is impossible | Rejected — performance is non-negotiable for the Hub tier |
| **KeyDB / DragonflyDB** (Redis-compatible alternatives) | Wire-compatible with Redis (drop-in `ext-redis` driver); DragonflyDB claims 25× throughput on multi-core; KeyDB has multi-threading | Smaller community than Redis (fewer runbooks, fewer incident postmortems); not available at parity on managed services (AWS ElastiCache, GCP Memorystore, Upstash) — must be self-hosted | Rejected for the primary recommendation; allowed as a drop-in for self-hosted deployments if the ops team chooses |
| **Valkey** (Linux Foundation Redis fork) | License clarity (BSD vs. Redis 7.4's SSPL/RSALv2); community-backed; wire-compatible | Identical API to Redis — this is a reimplementation, not an alternative. If Valkey achieves feature parity with Redis 7.x, we adopt it transparently via the same `ext-redis` driver | Not an alternative; documented as a viable Redis-compatible option for self-hosters concerned about the Redis 7.4 license change |

## Consequences

**Positive:**
- Cache Tags, Atomic Locks, Write-Through, and Pub/Sub all use the same Redis instance. One connection pool, one set of metrics, one alerting threshold, one backup procedure. Operational simplicity compounds.
- HUB-09 (Event Bus) uses Redis Pub/Sub for sub-millisecond fan-out; HUB-10 (Queue) uses Redis Streams for durable, replayable job queues. Both reuse the HUB-02 Redis connection — no new infra.
- Redis persistence (RDB snapshots + AOF) means the cache survives Redis restarts. Combined with HUB-04's session storage on Redis, a Redis crash no longer logs out every user (a real win over Memcached).

**Negative:**
- Redis is a single point of failure if deployed as a single instance. Production deployments must use Redis Sentinel or Redis Cluster for HA — operational complexity that Memcached (with client-side hashing) hides but does not actually solve.
- Redis memory is RAM-priced. A naive "cache everything forever" strategy will OOM the Redis instance. HUB-02 must enforce TTL defaults and a maxmemory-policy (`allkeys-lru` recommended); HUB-11 (Cloud Storage) must be used for large blobs.
- The Redis 7.4 license change (SSPL/RSALv2) is a concern for some downstream consumers. Self-hosted deployments can switch to Valkey transparently; managed-service deployments (ElastiCache, Memorystore) are unaffected because the cloud provider bears the license burden.

**Neutral:**
- The HUB-02 `HubCacheInterface` becomes a load-bearing contract: every Hub service and every Spoke depends on its semantics. Breaking the interface is a SemVer major for the entire Hub tier.
- Redlock (the distributed-lock algorithm) is *not* formally proven safe under all network partitions (see Martin Kleppmann's critique). For locks that must be safe under partition, HUB-02 should expose a fencing token; this is a future enhancement.

## Links
- Related ADRs: ADR-013 (MySQL — Redis and Postgres together cover the persistence tiers), ADR-009 (ULID for cache keys — sortable keys help LRU locality), ADR-010 (OPcache preload — Redis client classes must be preloaded)
- Related blueprints: CORE-15 (Cache Abstraction — PSR-6/16 layer), HUB-02 (Sovereign Hub Cache), HUB-09 (Sovereign Signal / Event Bus — Redis Pub/Sub), HUB-10 (Sovereign Queue — Redis Streams), HUB-07 (Rate Limiter — Redis atomic counters)
- Related findings: Finding 19 (no ADRs existed), Finding 10 (HUB-02's "< 0.1ms tag overhead" target has no benchmark methodology)
- External references: Redis documentation (redis.io/docs); PSR-16 Simple Cache (php-fig.org/psr/psr-16); Redlock algorithm (redis.io/topics/distlock); Martin Kleppmann, "How to do distributed locking" (2016)
