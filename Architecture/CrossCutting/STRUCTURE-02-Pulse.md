# DGLab Wheel Architecture
## Structure 02: Pulse Lifecycle

> **Reconciled to `STRUCTURE-01-Wheel.md` Part D** (`Verification/INCONSISTENCIES.md` #9). Two
> corrections: (1) the `Depth 0..4` list in §5 is a **penetration/cost tier**, not the Pulse `depth`
> field — it has been relabelled *Penetration Tier* and mapped to the single canonical depth scale
> (1 = Outer Rim … 6 = Core); (2) the `depth` field in the trace JSON now carries the canonical value.
> The Pulse 6-tuple, Pulse classes, and axioms this document relies on live in
> `STRUCTURE-01-Wheel.md` Part B.

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Pulse Flow

---

## 1. What Is a Pulse?

A **Pulse** is the fundamental unit of work in the DGLab wheel. It is not merely an HTTP request — it is any discrete packet of execution that enters the system, traverses radially toward the Core, and returns outward with a result.

### Pulse Types

| Type | Entry Point | Wheel Path | Example |
|---|---|---|---|
| **HTTP Pulse** | `public/index.php` | Outer Rim → Core → Outer Rim | User loads a page |
| **CLI Pulse** | `bin/console` | Core → Hub → Core | Admin runs a command |
| **Queue Pulse** | `bin/worker` | Hub → Core → Hub | Background job executes |
| **Event Pulse** | `HUB-09` listener | Hub → Hub → Hub | Cross-service notification |
| **Schedule Pulse** | `HUB-24` trigger | Core → Hub → Core | Cron-activated task |

Every Pulse carries a **PulseContext** — an immutable bag of metadata that propagates through every layer without mutation. Layers may *read* context; they may not *write* to it directly (they emit events for side effects).

---

## 2. The PulseContext

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Pulse;

/**
 * Immutable context carried by every Pulse through every layer of the wheel.
 * Injected at the Rim and propagated radially. Never mutated — new instances
 * are created when context needs to change (e.g., tenant resolution).
 */
final class PulseContext
{
    public function __construct(
        public readonly string $pulseId,           // ULID — unique per Pulse
        public readonly string $traceId,           // Distributed trace root
        public readonly ?string $parentPulseId,    // For nested Pulses (queue jobs spawning sub-jobs)
        public readonly PulseType $type,           // HTTP | CLI | QUEUE | EVENT | SCHEDULE
        public readonly \DateTimeImmutable $initiatedAt,
        public readonly ?string $tenantId = null,  // Resolved at Inner Rim
        public readonly ?string $userId = null,    // Resolved at Inner Rim
        public readonly ?string $clientIp = null,
        public readonly ?string $userAgent = null,
        public readonly array $scopes = [],        // OAuth scopes / permissions
        public readonly array $bag = [],           // Extension point for spoke-specific metadata
    ) {}

    /**
     * Create a child context for a nested Pulse.
     * The child shares the traceId but gets a new pulseId.
     */
    public function spawnChild(PulseType $type): self
    {
        return new self(
            pulseId: \SovereignStack\Core\Identity\Ulid::generate(),
            traceId: $this->traceId,
            parentPulseId: $this->pulseId,
            type: $type,
            initiatedAt: new \DateTimeImmutable(),
            tenantId: $this->tenantId,
            userId: $this->userId,
            clientIp: $this->clientIp,
            userAgent: $this->userAgent,
            scopes: $this->scopes,
            bag: $this->bag,
        );
    }

    /**
     * Create a context with tenant resolved.
     * Used by BRIDGE-01 after JWT verification.
     */
    public function withTenant(string $tenantId): self
    {
        return new self(
            pulseId: $this->pulseId,
            traceId: $this->traceId,
            parentPulseId: $this->parentPulseId,
            type: $this->type,
            initiatedAt: $this->initiatedAt,
            tenantId: $tenantId,
            userId: $this->userId,
            clientIp: $this->clientIp,
            userAgent: $this->userAgent,
            scopes: $this->scopes,
            bag: $this->bag,
        );
    }

    /**
     * Create a context with user resolved.
     * Used by BRIDGE-01 after identity verification.
     */
    public function withUser(string $userId, array $scopes): self
    {
        return new self(
            pulseId: $this->pulseId,
            traceId: $this->traceId,
            parentPulseId: $this->parentPulseId,
            type: $this->type,
            initiatedAt: $this->initiatedAt,
            tenantId: $this->tenantId,
            userId: $userId,
            clientIp: $this->clientIp,
            userAgent: $this->userAgent,
            scopes: $scopes,
            bag: $this->bag,
        );
    }
}

enum PulseType: string
{
    case HTTP = 'http';
    case CLI = 'cli';
    case QUEUE = 'queue';
    case EVENT = 'event';
    case SCHEDULE = 'schedule';
    case WEBSOCKET = 'websocket';
}
```

### Context Propagation Rules

1. **Radial Invariance:** The `pulseId` and `traceId` remain constant from Rim entry to Rim exit.
2. **Tenant Lock:** Once `tenantId` is resolved at the Inner Rim, it cannot change. All downstream layers use this tenant scope.
3. **Scope Accumulation:** Scopes are granted at the Rim and accumulate inward. No inner layer may expand scopes.
4. **Bag Immutability:** The `bag` array is write-once per layer. A layer may add keys for downstream consumption but may not modify keys added by outer layers.

---

## 3. HTTP Pulse Lifecycle — The Full Journey

### 3.1 Phase 0: Rim Touch (Outer Rim)

```
Internet ──► CDN/Edge ──► DNS ──► TLS ──► Load Balancer
```

**Responsibilities:**
- TLS termination (TLS 1.3 minimum)
- DDoS mitigation
- Static asset cache (ESPOKE-01 CMS pages, ESPOKE-12 toolkit artifacts)
- Geographic routing
- WAF rule evaluation (basic SQLi/XSS filtering)

**PulseContext state at this phase:**
```php
new PulseContext(
    pulseId: Ulid::generate(),
    traceId: $_SERVER['HTTP_X_TRACE_ID'] ?? Ulid::generate(),
    parentPulseId: null,
    type: PulseType::HTTP,
    initiatedAt: new \DateTimeImmutable(),
    clientIp: $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
);
```

**If CDN cache HIT:** Pulse terminates here. No wheel penetration. Fastest path (~5ms).

---

### 3.2 Phase 1: Outer Spoke Entry

```
Load Balancer ──► Vanguard Replica (BRIDGE-01 ingress)
```

The Load Balancer routes to a healthy `BRIDGE-01` replica. At this point, the request has not yet been authenticated — it is a raw PSR-7 `ServerRequestInterface`.

**BRIDGE-01 Vanguard Pipeline (execution order):**

| Order | Middleware | Layer | Purpose |
|---|---|---|---|
| 1 | `RequestIdMiddleware` | Core | Ensures `X-Request-Id` header exists; sets PulseContext |
| 2 | `CorsMiddleware` | Bridge | CORS preflight handling |
| 3 | `RateLimitMiddleware` | Bridge | `HUB-07` rate limit check per IP/path |
| 4 | `IpBlocklistMiddleware` | Bridge | Reject known malicious IPs |
| 5 | `JwtAuthMiddleware` | Bridge | Verify JWT signature via `CORE-16`; extract claims |
| 6 | `TenantContextMiddleware` | Bridge | Resolve `tenant_id` claim; validate tenant active |
| 7 | `ScopeMiddleware` | Bridge | Verify OAuth scopes for requested route |
| 8 | `AuditEntryMiddleware` | Bridge | Write `GatewayAccessEvent` to `HUB-06` (async) |

**At Phase 1 exit, PulseContext is fully resolved:**
```php
$context = $context
    ->withTenant($jwtClaims['tenant_id'])
    ->withUser($jwtClaims['sub'], $jwtClaims['scope'] ?? []);
```

**If any middleware rejects:** A `PulseRejection` is returned immediately. No Hub or Core touch. The rejection includes:
- `pulseId` (for support tracing)
- `traceId`
- `reason` (rate_limit_exceeded, invalid_token, insufficient_scope, etc.)
- `retry_after` (for rate limits)

---

### 3.3 Phase 2: Routing Decision (Inner Rim)

```
BRIDGE-01 ──► Route Resolution ──► {Outer Spoke | Inner Spoke | Hub Direct}
```

BRIDGE-01's router (`CORE-06`) resolves the request to a controller. The route determines which spoke (or hub service) handles the Pulse.

**Route classification:**

| Route Prefix | Destination | Auth Required | Example |
|---|---|---|---|
| `/api/public/*` | Outer Spoke (ESPOKE-02) | Optional | Public API endpoints |
| `/cms/*` | Outer Spoke (ESPOKE-01) | Optional | Public content pages |
| `/account/*` | Outer Spoke (ESPOKE-03) | Required | User self-service |
| `/admin/*` | Inner Spoke (ISPOKE-01) | Required + `super_admin` | Admin panel |
| `/staff/*` | Inner Spoke (various) | Required + staff role | Internal tools |
| `/health/*` | Hub (HUB-15) | None (liveness), Admin (detail) | Health probes |
| `/webhook/*` | Outer Spoke (ESPOKE-11) | HMAC signature | Webhook receivers |

**Critical rule:** A route prefixed `/admin/*` or `/staff/*` **must** have `super_admin` or staff role in the JWT. BRIDGE-01 rejects with `403` before the Inner Spoke is ever invoked. This is the **Zero-Exposure enforcement** at the Rim level.

---

### 3.4 Phase 3: Spoke Execution

#### Outer Spoke Path (ESPOKE example)

```
BRIDGE-01 ──► ESPOKE-03 Account Hub Controller
                  │
                  ├── HUB-04 (Identity) — profile fetch
                  │       └── CORE-19 (DBAL) — SELECT query
                  │
                  ├── HUB-22 (Billing) — subscription status
                  │       └── CORE-19 (DBAL) — SELECT query
                  │
                  └── HUB-02 (Cache) — warm profile cache
```

**Characteristics:**
- Minimal business logic
- Mostly read operations
- Heavy cache usage
- No direct Core access (always through Hub)
- Response is typically JSON or HTML

#### Inner Spoke Path (ISPOKE example)

```
BRIDGE-01 ──► ISPOKE-01 Admin Panel Controller
                  │
                  ├── ISPOKE-08 (Support Desk) — ticket context
                  │       └── HUB-04 (Identity) — user lookup
                  │
                  ├── HUB-05 (RBAC) — permission re-verification
                  │       └── CORE-19 (DBAL) — role query
                  │
                  ├── HUB-06 (Audit) — action logging
                  │       └── CORE-19 (DBAL) — INSERT audit_log
                  │
                  └── HUB-23 (Reporter) — analytics data
                          └── CORE-19 (DBAL) — aggregation query
```

**Characteristics:**
- Rich business logic
- Read/write operations
- Direct audit requirements
- May spawn Queue Pulses (e.g., bulk export)
- Response is typically HTML with `HUB-26` UI components

---

### 3.5 Phase 4: Hub Service Resolution

When a Spoke calls a Hub service, the call goes through the `HubServiceProxy` — a generated proxy that ensures:

1. **Tenant scoping** is applied automatically (`HUB-21`)
2. **Audit events** are fired for sensitive operations (`HUB-06`)
3. **Cache lookups** happen before DB queries (`HUB-02`)
4. **Rate limits** are checked for cross-service calls (`HUB-07`)

```php
// Generated proxy example (simplified)
final class IdentityServiceProxy implements IdentityServiceInterface
{
    public function __construct(
        private IdentityServiceInterface $inner,      // Real service
        private TenantScope $tenantScope,            // HUB-21
        private AuditDispatcher $audit,              // HUB-06
        private CacheManager $cache,                 // HUB-02
    ) {}

    public function findUser(string $userId): ?User
    {
        // 1. Tenant scope check
        $this->tenantScope->assertBelongs($userId);

        // 2. Cache check
        $key = "user:{$this->tenantScope->current()}:{$userId}";
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }

        // 3. Execute
        $user = $this->inner->findUser($userId);

        // 4. Cache warm
        if ($user) {
            $this->cache->set($key, $user, 300);
        }

        return $user;
    }

    public function updateUser(string $userId, array $data): User
    {
        // 1. Tenant scope
        $this->tenantScope->assertBelongs($userId);

        // 2. Execute
        $before = $this->inner->findUser($userId);
        $after = $this->inner->updateUser($userId, $data);

        // 3. Audit (mutation)
        $this->audit->log(new UserUpdatedEvent($before, $after));

        // 4. Cache invalidation
        $this->cache->delete("user:{$this->tenantScope->current()}:{$userId}");

        return $after;
    }
}
```

---

### 3.6 Phase 5: Core Execution

When a Hub service needs persistence, encryption, or routing, it calls Core primitives. Core has **no knowledge** of tenants, users, or business logic. It operates on raw data.

```php
// HUB-04 (Identity) calling CORE-19 (DBAL)
$userRow = $this->dbal->fetchOne(
    sql: "SELECT * FROM users WHERE id = ? AND tenant_id = ?",
    params: [$userId, $tenantId]  // Tenant ID injected by Hub proxy
);

// HUB-20 (Vault) calling CORE-16 (Crypto)
$encrypted = $this->crypto->encrypt($secretValue, aad: $tenantId);
```

**Core execution is always:**
- Synchronous (no events, no queues)
- Stateless (no session, no context)
- Typed (strict return types, no `mixed`)
- Fast (no network calls, no external dependencies)

---

### 3.7 Phase 6: Outbound Pulse (Response)

The return journey is not a simple reverse. Each layer may **enrich** the response or trigger **side effects**:

```
Core ──► Hub ──► Spoke ──► BRIDGE-01 ──► CDN ──► User
  │        │        │           │           │
  │        │        │           │           └── Cache-Control header
  │        │        │           │           └── ETag
  │        │        │           │           └── Compression (gzip/brotli)
  │        │        │           │
  │        │        │           └── X-RateLimit-Remaining
  │        │        │           └── X-Request-Id (echo pulseId)
  │        │        │           └── CORS headers
  │        │        │
  │        │        └── View rendering (HTML/JSON)
  │        │        └── Flash messages
  │        │
  │        └── Event dispatch (HUB-09) — async, non-blocking
  │        └── Notification queue (HUB-10) — async
  │        └── Cache warming — background
  │
  └── Query timing logged (CORE-09)
  └── Connection returned to pool (CORE-19)
```

---

## 4. Non-HTTP Pulse Lifecycles

### 4.1 Queue Pulse

```
bin/worker ──► HUB-10 (Queue::pop) ──► JobInterface::handle()
                                              │
                                              ├── PulseContext spawned from job payload
                                              ├── Executes business logic
                                              ├── May call Hub services
                                              ├── May call Core primitives
                                              └── ack() or fail()
```

**Queue Pulses are special because:**
- They have no `clientIp` or `userAgent`
- Their `userId` is often `system` or the user who queued the job
- They may spawn child Pulses (e.g., a bulk email job spawning individual send jobs)
- They are retried with exponential backoff (`HUB-10`)

### 4.2 Event Pulse

```
HUB-09 (Event Bus) ──► ListenerProvider ──► Listener callable
                                                  │
                                                  ├── PulseContext from originating Pulse
                                                  ├── May fire additional events
                                                  └── Never returns a value (fire-and-forget)
```

**Event Pulses are special because:**
- They are side-effect-only
- They may cross spoke boundaries (e.g., `HUB-04` login event → `HUB-12` notification + `HUB-06` audit)
- They are processed asynchronously when possible
- They are the primary mechanism for decoupling spokes

### 4.3 Schedule Pulse

```
HUB-24 (Scheduler) ──► cron trigger ──► HUB-10 (Queue::push) ──► Worker
                                              │
                                              └── ScheduledTaskJob
                                                    └── Executes task logic
```

**Schedule Pulses are special because:**
- They originate at the Core (scheduler is a Core-adjacent service)
- They always flow through the Queue for reliability
- They have `type: PulseType::SCHEDULE`
- They are singleton-per-schedule (HUB-24 prevents overlapping executions)

---

## 5. Pulse Penetration Tiers (cost model)

How far a Pulse penetrates is determined by **cache state** and **route design**. The tiers below are
a *cost model*, not the Pulse `depth` field — the canonical mapping to `STRUCTURE-01-Wheel.md` §D.1 is
given in the right-hand column of §5.1.

```
Penetration Tier 0: CDN Cache (Outer Rim only)
    Trigger: Cache-Control: public, max-age > 0
    Cost: ~5ms
    Touch: None of the wheel

Penetration Tier 1: Hub Cache (Outer Spoke + Hub)
    Trigger: HUB-02 cache HIT
    Cost: ~15ms
    Touch: Rim → Spoke → Hub → return

Penetration Tier 2: Database Read (Outer Spoke + Hub + Core)
    Trigger: Cache MISS, read-only query
    Cost: ~50ms
    Touch: Full stack, no audit write

Penetration Tier 3: Database Write (Full stack + Audit)
    Trigger: Mutation endpoint
    Cost: ~100ms
    Touch: Full stack + HUB-06 audit + HUB-09 event

Penetration Tier 4: Deep Internal (Inner Spoke + Full stack)
    Trigger: Admin operation
    Cost: ~200ms
    Touch: Full stack + cross-spoke calls + heavy audit

Penetration Tier 5: System (Core + Hub only, no Rim)
    Trigger: Scheduled job, queue worker
    Cost: Variable
    Touch: Core + Hub + Deploy infrastructure
```

### 5.1 Penetration tier → canonical Wheel depth

| Penetration tier | Deepest ring actually touched | Canonical `depth` (`STRUCTURE-01-Wheel.md` §D.1) |
|---|---|---|
| 0 — CDN cache | Outer Rim (`HUB-08`) / CDN in front of it | 1 |
| 1 — Hub cache hit | Hub (`HUB-02`) | 5 |
| 2 — Database read | Core (`CORE-19`) | 6 |
| 3 — Database write + audit | Core (`CORE-19`) + `HUB-06` | 6 |
| 4 — Deep internal | Inner Spoke → Hub → Core | 6 |
| 5 — System / origin-less (Pulse type C) | Hub + Core, no rim traversal | 6 (entry radius: none) |

**Design goal:** 90% of Pulses should terminate at penetration tier 1 or shallower.

---

## 6. Failure Modes by Phase

| Phase | Failure | Behavior | Recovery |
|---|---|---|---|
| Rim (CDN) | Origin timeout | CDN serves stale cache | Auto-retry to different origin |
| Rim (TLS) | Certificate expired | Connection refused | ACME auto-renewal (`DEPLOY-03`) |
| Bridge | Rate limit exceeded | `429 Too Many Requests` | Client retries with backoff |
| Bridge | JWT expired | `401 Unauthorized` | Client refreshes token |
| Bridge | Invalid tenant | `403 Forbidden` | Client re-authenticates |
| Spoke | Controller exception | `500 Internal Server Error` | CORE-08 logs + HUB-06 audits + HUB-12 alerts |
| Hub | Service unreachable | `503 Service Unavailable` | HUB-15 health check fails → orchestrator restarts |
| Hub | DB connection timeout | `500` + retry | CORE-19 connection pool retry |
| Core | Fatal error | `500` + shutdown handler | CORE-08 catches + logs |
| Queue | Job failure | DLQ after max attempts | ISPOKE-01 admin reviews DLQ |
| Schedule | Overlapping execution | Skipped | HUB-24 mutex prevents overlap |

---

## 7. Pulse Tracing & Observability

Every Pulse leaves a **trace** — a hierarchical log of every layer it touched:

```json
{
  "traceId": "01J4K...",
  "pulseId": "01J4K...",
  "type": "http",
  "duration_ms": 87,
  "depth": 6,   // canonical Wheel depth (STRUCTURE-01 §D.1): deepest ring reached is Core
  "layers": [
    {"layer": "rim.cdn", "duration_ms": 5, "cache": "MISS"},
    {"layer": "bridge.vanguard", "duration_ms": 12, "jwt": "valid", "tenant": "tenant_abc"},
    {"layer": "spoke.espoke-03", "duration_ms": 45, "controller": "ProfileController::show"},
    {"layer": "hub.identity", "duration_ms": 18, "cache": "MISS", "db_query_ms": 12},
    {"layer": "hub.billing", "duration_ms": 15, "cache": "HIT"},
    {"layer": "core.dbal", "duration_ms": 8, "query": "SELECT * FROM users WHERE id = ?"},
    {"layer": "hub.audit", "duration_ms": 3, "event": "profile.viewed"},
    {"layer": "hub.cache", "duration_ms": 2, "operation": "set", "ttl": 300}
  ],
  "outcome": "success",
  "status_code": 200
}
```

This trace is:
1. Logged to `CORE-09` (structured logging)
2. Sent to `HUB-28` (Telemetry) for aggregation
3. Stored in `HUB-31` (Metrics Engine) for latency analysis
4. Available in `ISPOKE-03` (Health Dashboard) for per-request inspection

---

*End of Structure 02: Pulse Lifecycle*
