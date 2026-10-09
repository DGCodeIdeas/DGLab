> **⚠️ SUPERSEDED by ADR-021 §12 (2026-09-30).** HUB-25 (Chronos TaskRunner) has been **relocated to the Runtime tier** as **RUNTIME-04 (Scheduler)**. This blueprint is retained for historical reference; new work should reference the Runtime tier per ADR-021. The Hub ring active count is reduced by this relocation.
>
> **Reason:** HUB-25's primary purpose is to BE the scheduled-job substrate (system cron / systemd timer running `s-cli schedule:run` every minute), not to consume Hub capabilities — it is a runtime-tier package masquerading as Hub-tier. Notably, HUB-25 needs a *different* substrate than FrankenPHP (which covers HTTP but not scheduled jobs). See ADR-021 §12 for full rationale.

---

# PHASE HUB-25: Background Scheduler & Cron Management


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Confirms this is the component `HUB-23.md`'s `ReportScheduler` now formally depends on (see
`HUB-23.md`'s corrected dependency list), and adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Chronos (Scheduler)

## Description
Centralized scheduler for recurring background tasks: replaces crontab entries with a PHP fluent
interface, manages task overlaps, execution logs, and a unified automation dashboard.

## Build Status
🔴 **Blocked** on `HUB-10` (Queue), `HUB-02` (Cache), `HUB-06` (Audit) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-10`, `HUB-02`, `HUB-06`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-19`.
- **Downward:** `HUB-23` (Reporter — recurring report generation), any Spoke registering recurring
  tasks via `CORE-17` service providers.

## Architectural Design
- **ScheduleRegistry** — holds recurring tasks and their frequencies.
- **TaskRunner** — evaluates due tasks, dispatches to `HUB-10`.
- **LockManager** — uses `HUB-02`'s Redlock-based locking (see `HUB-02.md`) to prevent a task running
  concurrently across nodes — this reuses `HUB-02`'s `LockInterface` directly rather than a separate
  locking mechanism.
- **HistoryTracker** — records start/end/output of every execution via `HUB-06`.

```php
$schedule->command('cleanup:logs')->dailyAt('00:00')->withoutOverlapping();
$schedule->job(new DataSyncJob())->everyFiveMinutes();
```

```php
namespace SovereignStack\Hub\Contracts;

interface SchedulerInterface
{
    public function command(string $signature): TaskInterface;
    public function job(object $job): TaskInterface;
}
```

## Integration Strategy
- **Upward:** requires one system-level cron entry running `s-cli schedule:run` every minute.
- **Downward:** Spoke applications register tasks in their `CORE-17` service provider.
- **Contract:** tasks dispatch as standard `HUB-10` jobs.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Overlap prevention | Integration test: start a long-running "withoutOverlapping" task, then trigger `schedule:run` again before it finishes; assert the second invocation does not start a duplicate, verified against `HUB-02`'s real lock (not a mock) so the Redlock behavior is actually exercised. |
| Scheduling precision | State environment/clock-source before citing "within 1 second" — measure actual trigger drift over N cycles against real wall-clock time (Finding 10). |
| Failure visibility | Integration test: force a scheduled task to throw; assert the failure and its exception trace are recorded via `HUB-06`, retrievable through `AuditorInterface::search()`. |

## CI Verification Criteria
- Overlap-prevention test against a real `HUB-02` lock, blocking.
- Failure-visibility test with actual `HUB-06` retrieval, blocking.
- Scheduling precision measured over multiple cycles and reported with environment stated.

## SemVer Impact
**Minor.** Centralizes all recurring automation.


---

## Doctrines Applied + Rewrite Notes (PR #333)

> **This section was added in PR #333 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #333

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-25 — Background Scheduler & Cron Management (RELOCATED to RUNTIME-04 per ADR-021) — shipped per the verified DAG).

### What Was NOT Changed in PR #333

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/cron/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #333 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
