# DGLab Wheel Architecture
## Structure 04: Event-Driven & Async Architecture

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Tangential Event Flow

---

## 1. Beyond Radial Flow: Tangential Events

The Pulse Lifecycle (Structure 02) describes **radial** flow: inward toward the Core, then outward to the Rim. But the wheel is not merely a request-response pipeline. It is a living system where **events flow tangentially** — Spokes communicate with each other through the Hub without direct coupling.

```
                    ESPOKE-01 (CMS)
                         │
                         ▼
    ISPOKE-07 ◄── HUB-09 ◄── Event Bus
         │                  │
         ▼                  ▼
    HUB-14 (Search)    HUB-12 (Notify)
         │                  │
         ▼                  ▼
    ESPOKE-06         ESPOKE-03
    (Discover)        (Account Hub)
```

In this example:
1. `ISPOKE-07` (Content Studio) publishes a new article
2. It emits `ContentPublishedEvent` to `HUB-09`
3. `HUB-14` (Search) listens and indexes the content
4. `HUB-12` (Notify) listens and emails subscribers
5. `ESPOKE-06` (Discover) eventually serves the indexed content

**No spoke knows about the other spokes.** They only know about events.

---

## 2. The Event Bus (HUB-09)

`HUB-09` is the **nervous system** of the wheel. It is not a message queue — it is an in-process event dispatcher with async fan-out capabilities.

### 2.1 Event Types

| Type | Delivery | Use Case | Example |
|---|---|---|---|
| **Sync** | Immediate, blocking | Critical chain reactions | `HUB-06` audit logging |
| **Async** | Queued via `HUB-10` | Non-critical side effects | Email sending, search indexing |
| **Broadcast** | All listeners | Cross-cutting notifications | `HUB-15` health degradation |
| **Targeted** | Specific listener | Directed commands | `HUB-30` kill switch propagation |

### 2.2 Event Interface Contract

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Contracts;

/**
 * Base interface for all events dispatched through HUB-09.
 * Every event carries the PulseContext of its originator.
 */
interface EventInterface
{
    /** The event type identifier, used for routing listeners. */
    public function eventType(): string;

    /** The PulseContext from the originating Pulse. */
    public function context(): \SovereignStack\Core\Pulse\PulseContext;

    /** When the event occurred. */
    public function occurredAt(): \DateTimeImmutable;

    /** Serializable payload — no closures, no resources. */
    public function payload(): array;

    /** Whether this event should be dispatched asynchronously. */
    public function isAsync(): bool;
}

/**
 * Events that implement this interface are automatically audited by HUB-06.
 */
interface AuditableEventInterface extends EventInterface
{
    public function auditAction(): string;
    public function auditActor(): string;
    public function auditTenantId(): ?string;
    public function auditChanges(): ?array;
    public function auditContext(): array;
}
```

### 2.3 Listener Registration

Listeners are registered by Hub service providers during `boot()`:

```php
// HUB-14 (Search) ServiceProvider
public function boot(ContainerInterface $container): void
{
    $events = $container->get(EventBusInterface::class);

    $events->listen(
        eventType: 'content.published',
        listener: new IndexContentListener($container->get(SearchEngineInterface::class)),
        async: true, // Queue via HUB-10
    );

    $events->listen(
        eventType: 'content.archived',
        listener: new RemoveFromIndexListener($container->get(SearchEngineInterface::class)),
        async: true,
    );
}
```

### 2.4 Event Dispatch Flow

```
Spoke Controller
    │
    ├──► Business logic executes
    │
    ├──► $events->dispatch(new ContentPublishedEvent($article, $context))
    │         │
    │         ├──► Sync listeners fire immediately (same Pulse)
    │         │         ├──► HUB-06 (Audit) — log the publish
    │         │         └──► HUB-15 (Health) — increment content counter
    │         │
    │         └──► Async listeners are serialized and pushed to HUB-10
    │                   ├──► HUB-14 (Search) — index job queued
    │                   ├──► HUB-12 (Notify) — email job queued
    │                   └──► HUB-02 (Cache) — cache invalidation job queued
    │
    └──► Controller returns response (does not wait for async listeners)
```

**Critical:** Async listeners do not block the HTTP response. If an async listener fails, the HTTP response has already been sent. The failure is handled by `HUB-10` retry/DLQ logic.

---

## 3. The Queue (HUB-10): Async Pulse Execution

`HUB-10` is the **muscle** that executes async events. It is not part of the HTTP request cycle — it operates in separate worker processes.

### 3.1 Job Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         JOB STATE MACHINE                                   │
│                                                                             │
│   [Dispatch] ──► [Queued] ──► [Reserved] ──► [Processing] ──► [Success]   │
│       │            │            │                  │              │         │
│       │            │            │                  │              ▼         │
│       │            │            │                  │         [Acknowledged] │
│       │            │            │                  │                          │
│       │            │            │                  └──► [Failed]             │
│       │            │            │                         │                  │
│       │            │            │                         ├──► [Retry] ──► [Reserved]
│       │            │            │                         │       (backoff)  │
│       │            │            │                         │                  │
│       │            │            │                         └──► [Max Attempts] ──► [DLQ]
│       │            │            │                                              │
│       │            │            └──► [Timeout] ──► [Released] ──► [Reserved]   │
│       │            │                     (worker died mid-job)                 │
│       │            │                                                           │
│       │            └──► [Delayed] ──► [Reserved] (after delay expires)         │
│       │                                                                        │
│       └──► [Scheduled] ──► [Queued] (at future time via HUB-24)                │
│                                                                                │
└────────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Worker Process Model

```php
// bin/worker
$kernel = new Kernel(dirname(__DIR__), getenv('APP_ENV') ?: 'production');
$kernel->boot();

$worker = $kernel->getContainer()->get(WorkerInterface::class);
$worker->daemon(
    queue: $argv[1] ?? 'default',
    memoryLimit: 128, // MB
    maxJobs: 1000,     // Restart after N jobs to prevent memory leaks
    timeout: 300,      // Seconds per job
);
```

**Worker lifecycle:**
1. Boot the Kernel (same as HTTP, but no router)
2. Connect to queue backend (Redis / SQS)
3. Loop: `pop()` → `handle()` → `ack()` / `fail()`
4. Every 1000 jobs or on memory pressure: graceful shutdown, new process starts

### 3.3 Queue PulseContext Restoration

When a worker processes a job, it restores the original PulseContext:

```php
final class Worker
{
    public function process(JobInterface $job): void
    {
        // Restore the context from when the job was dispatched
        $context = $job->pulseContext();
        ContextRegistry::setCurrent($context);

        // Now all Hub services see the original tenant, user, trace ID
        $job->handle($this->container);

        ContextRegistry::clear();
    }
}
```

This ensures that:
- Tenant isolation is maintained in async jobs
- Audit logs show the original actor, not "system"
- Trace IDs connect the HTTP request to its async side effects

---

## 4. Event Patterns by Wheel Layer

### 4.1 Spoke-Originated Events

Spokes emit events to trigger side effects without knowing the consumers:

```php
// ISPOKE-07 (Content Studio) — publishing an article
class ArticleController
{
    public function publish(string $articleId): Response
    {
        $article = $this->repository->find($articleId);
        $article->publish();
        $this->repository->save($article);

        // Emit event — ISPOKE-07 knows nothing about search, email, or cache
        $this->events->dispatch(new ContentPublishedEvent(
            article: $article,
            context: PulseRegistry::current(),
        ));

        return new JsonResponse(['status' => 'published']);
    }
}
```

### 4.2 Hub-Originated Events

Hub services emit events to notify other Hub services of state changes:

```php
// HUB-04 (Identity) — user password changed
class IdentityService
{
    public function changePassword(string $userId, string $newPassword): void
    {
        // ... hash and store password ...

        $this->events->dispatch(new PasswordChangedEvent(
            userId: $userId,
            context: PulseRegistry::current(),
        ));
        // Listeners:
        // • HUB-12 (Notify) → email user about password change
        // • HUB-06 (Audit) → log password change
        // • HUB-02 (Cache) → invalidate all user sessions
    }
}
```

### 4.3 Core-Originated Events

Core emits low-level events for observability:

```php
// CORE-19 (DBAL) — query executed
class Connection
{
    public function execute(string $sql, array $params = []): int
    {
        $start = microtime(true);
        $result = $this->pdo->execute($sql, $params);
        $duration = microtime(true) - $start;

        $this->events->dispatch(new QueryExecutedEvent(
            sql: $sql,
            durationMs: $duration * 1000,
            rowCount: $result,
            context: PulseRegistry::current(),
        ));
        // Listeners:
        // • HUB-28 (Telemetry) → span recording
        // • HUB-31 (Metrics) → query duration histogram
        // • HUB-15 (Health) → slow query detection

        return $result;
    }
}
```

### 4.4 Schedule-Originated Events

`HUB-24` emits events at scheduled times:

```php
// HUB-24 (Scheduler) — daily cleanup job
$scheduler->dailyAt('02:00', new CleanupJob());

// At 02:00 UTC, HUB-24 emits:
$events->dispatch(new ScheduledTaskEvent(
    task: 'cleanup.expired_sessions',
    context: PulseContext::system(), // system-generated context
));
// Listeners:
// • HUB-04 (Identity) → delete expired sessions
// • HUB-06 (Audit) → log cleanup completion
// • HUB-23 (Reporter) → report cleanup metrics
```

---

## 5. Cross-Spoke Communication Without Coupling

The event bus enables **cross-spoke communication** while maintaining strict decoupling:

```
Before (Coupled — Anti-pattern):
    ISPOKE-07 ──► directly calls ──► ISPOKE-05 (Analytics)
    ISPOKE-07 ──► directly calls ──► ESPOKE-01 (CMS cache clear)

    Problem: ISPOKE-07 must know about all consumers.
    Problem: Consumers cannot be added without modifying ISPOKE-07.

After (Decoupled — Event-driven):
    ISPOKE-07 ──► HUB-09 ──► ISPOKE-05 (Analytics listener)
              ──► HUB-09 ──► ESPOKE-01 (Cache listener)
              ──► HUB-09 ──► HUB-14 (Search listener)
              ──► HUB-09 ──► HUB-12 (Notify listener)

    Benefit: ISPOKE-07 emits one event.
    Benefit: New consumers add listeners without touching ISPOKE-07.
```

---

## 6. Event Sourcing vs. Event Notification

DGLab uses **event notification**, not full event sourcing:

| Aspect | Event Notification (DGLab) | Event Sourcing (Not used) |
|---|---|---|
| State storage | Current state in DB | All state derived from event log |
| Event purpose | Side effects | Source of truth |
| Replayability | Not guaranteed | Core feature |
| Complexity | Low | High |
| Use case | Decoupling | Audit-heavy domains |

**Why not event sourcing?** The audit chain (`HUB-06`) provides the compliance benefits of event sourcing without the operational complexity. Business state remains in normalized relational tables (`CORE-19`).

---

## 7. The Outbox Pattern (Critical Transactions)

For operations where **the database write and the event dispatch must both succeed or both fail**, DGLab uses the **Outbox Pattern**:

```php
// HUB-06 (Audit) — outbox implementation
class AuditService
{
    public function log(AuditableEventInterface $event): void
    {
        $this->dbal->beginTransaction();

        try {
            // 1. Write audit record to DB
            $this->dbal->execute(
                "INSERT INTO audit_log (...) VALUES (...)",
                [...]
            );

            // 2. Write event to outbox table (same transaction)
            $this->dbal->execute(
                "INSERT INTO event_outbox (event_type, payload, context) VALUES (?, ?, ?)",
                [$event->eventType(), json_encode($event->payload()), ...]
            );

            $this->dbal->commit();
        } catch (\Throwable $e) {
            $this->dbal->rollBack();
            throw $e;
        }

        // 3. Outbox relay (separate process) reads event_outbox
        //    and dispatches to HUB-09. On success, deletes from outbox.
    }
}
```

**Why outbox?** If `HUB-09` is unavailable, the audit record is still committed. The outbox relay retries until the event is dispatched. This prevents the "audit gap" identified in the BRIDGE-01 critique.

---

## 8. Event-Driven Cache Invalidation

Cache invalidation is event-driven, not time-based:

```php
// HUB-02 (Cache) listens to mutation events
$events->listen('user.updated', new CacheInvalidationListener([
    'pattern' => 'tenant:{tenantId}:user:{userId}',
    'tags' => ['users', 'profiles'],
]));

$events->listen('content.published', new CacheInvalidationListener([
    'pattern' => 'tenant:{tenantId}:cms:*',
    'tags' => ['cms', 'pages'],
    'cascade' => ['cdn.purge' => '/cms/{slug}'],
]));
```

**Benefit:** Cache is always consistent with database state. No TTL-based stale data.

---

## 9. Async Pulse Monitoring

Async Pulses (queue jobs) are monitored via:

| Metric | Source | Dashboard |
|---|---|---|
| Queue depth | `HUB-10::size()` | `ISPOKE-03` (Health Dashboard) |
| Job throughput | `HUB-10` worker stats | `ISPOKE-03` |
| DLQ size | `HUB-10::failed()` | `ISPOKE-01` (Admin Panel) |
| Job latency | `HUB-28` (Telemetry) | `ISPOKE-05` (Insight) |
| Worker memory | Worker process | `ISPOKE-11` (Ops Center) |

---

*End of Structure 04: Event-Driven & Async Architecture*
