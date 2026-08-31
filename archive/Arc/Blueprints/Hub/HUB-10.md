# PHASE HUB-10: Queue & Job Dispatcher

## Tier
Hub (Shared Services)

## Resolves
`docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` Hub Weakness 2 references "Queue (HUB-11)" throughout
(heading and body: *"Expand HUB-11 with sections: Message Ordering, Dead-Letter Patterns..."*). **That
ID is wrong.** `HUB-11` is Cloud Storage (`docs/blueprints/Hub/HUB-11.md`, "Sovereign Cloud Storage");
the Queue blueprint is `HUB-10` — this file. This is the same class of live cross-reference bug as
`00_CRITIQUE.md` Finding 3 (the `BRIDGE-01`/`CORE-09` mix-up), found independently in a different
document. Interestingly, the actual pattern docs this weakness write-up spawned
(`docs/queue-patterns/*.md`) got the ID right — they all correctly reference `HUB-10` — so the error is
isolated to the `SOLUTIONS_TO_WEAKNESSES.md` write-up itself and should be corrected there per
Governance Rule 1 (single numbering authority). This blueprint merges the actually-correct queue
pattern docs in, closing the underlying "sparse detail" weakness the same way `HUB-02.md` closes its
cache counterpart.

## Component Name
Sovereign Queue

## Description
Robust asynchronous job processing: long-running tasks (email, report generation, image processing)
offloaded from the request cycle. Supports multiple drivers, delayed jobs, retries, and job priority.

## Build Status
🔴 **Blocked** on `CORE-19` (DBAL) and `HUB-02` (Cache) — neither implemented.

## Dependency Status
- **Upward:** `CORE-19`, `HUB-02`. *(Matches taxonomy.)*
- **Downward:** `HUB-06` (async audit writes), `HUB-09` (Event Bus fan-out), `HUB-12` (Notify),
  `HUB-14` (Search indexing), `HUB-18` (Media Forge), `HUB-23` (Reporter), `HUB-25` (Scheduler) — the
  single most depended-upon Hub component after `HUB-02`.

## Architectural Design
- **QueueManager** — unified API to push jobs to Database/Redis/Sync drivers.
- **Worker** — long-running CLI process (`CORE-13`) polling and executing jobs.
- **Job** — a plain class implementing `handle()`.
- **FailedJobProvider** — manages retry-exhausted jobs for manual inspection.

```php
namespace SovereignStack\Hub\Jobs;

class SendWelcomeEmail implements JobInterface
{
    public function __construct(public int $userId) {}

    public function handle(NotificationService $notifications): void
    {
        $notifications->send($this->userId, 'welcome');
    }
}
```

```php
namespace SovereignStack\Hub\Contracts;

interface QueueInterface
{
    public function push(object $job, string $queue = 'default'): void;
    public function later(int $delay, object $job, string $queue = 'default'): void;
}
```

## Deep-Dive References (merged, not duplicated)
These already exist in the repo, correctly targeted at `HUB-10`, and are genuinely detailed — this
blueprint links rather than re-derives them:

1. **`docs/queue-patterns/message-ordering-guarantees.md`** — FIFO vs. standard-queue ordering models,
   at-most-once / at-least-once (the default for this component) / exactly-once delivery semantics,
   monotonic sequence IDs, partition keys, and deduplication. `QueueManager`'s default driver
   configuration should follow this doc's "Configuration: HUB-10 Queue Ordering" section directly.
2. **`docs/queue-patterns/dead-letter-handling.md`** — DLQ architecture, setup (including a working
   Redis driver implementation), poison-pill detection heuristics, circuit-breaker integration, and
   exponential-backoff retry schedules. `FailedJobProvider` should be built as this document's DLQ
   design, not a separate ad hoc "failed_jobs table" — this is also the pattern `HUB-09`'s
   `DeadLetterQueue` should reuse rather than duplicate.
3. **`docs/queue-patterns/throughput-optimization.md`** — bottleneck analysis, batch consumption,
   prefetch sizing, worker-pool concurrency limits, and backpressure signals. `Worker`'s polling loop
   should implement the batch-consumption pattern here rather than one-job-at-a-time polling, given the
   500 jobs/sec throughput target below.

## Integration Strategy
- **Upward:** `CORE-19` for the database driver, `HUB-02` for the Redis driver.
- **Downward:** every Hub/Spoke service dispatches async jobs via `QueueInterface`.
- **CLI:** `s-cli queue:work`, `s-cli queue:retry` (via `CORE-20`).

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Job isolation | Integration test: run two jobs that each set process-local state; assert no leakage between them (fresh process/fiber per job, per the isolation requirement). |
| Exact retry count | Configure a job with `retries: 3`, force it to always fail; assert it is attempted exactly 4 times total (initial + 3 retries) then lands in the DLQ per `dead-letter-handling.md`, not silently dropped or retried indefinitely. |
| Throughput | Load test the database driver specifically (the weakest-throughput driver by design) using the batch-consumption pattern from `throughput-optimization.md`; report the actual sustained pushes/sec on a stated reference environment — "500 jobs/sec on standard hardware" is undefined without a stated hardware baseline (Finding 10) and should be replaced with a measured number once implementable. |

## CI Verification Criteria
- Job isolation test, blocking.
- Exact-retry-count-then-DLQ test, blocking — directly verifies the merged dead-letter pattern is
  actually wired in, not just documented.
- Throughput measured against a stated reference environment, reported alongside the test rather than
  asserted separately in prose.

## SemVer Impact
**Major.** Introduces asynchronous capabilities to the entire ecosystem.
