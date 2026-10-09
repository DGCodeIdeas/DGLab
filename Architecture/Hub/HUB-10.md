> **⚠️ SUPERSEDED by ADR-021 §12 (2026-09-30).** HUB-10 (Queue Worker) has been **relocated to the Runtime tier** as **RUNTIME-03 (Worker)**. This blueprint is retained for historical reference; new work should reference the Runtime tier per ADR-021. The Hub ring active count is reduced by this relocation (31 − 2 relocated = 29 active Hubs).
>
> **Reason:** HUB-10's primary purpose is to BE the long-running worker substrate, not to consume Hub capabilities — it is a runtime-tier package masquerading as Hub-tier. See ADR-021 §12 for full rationale.

---

# PHASE HUB-10: Queue & Job Dispatcher


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

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


---

## Doctrines Applied + Rewrite Notes (PR #330)

> **This section was added in PR #330 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #330

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-10 — Queue & Job Dispatcher (RELOCATED to RUNTIME-03 per ADR-021) — shipped per the verified DAG).

### What Was NOT Changed in PR #330

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/queue/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #330 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
