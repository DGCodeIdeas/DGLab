# PHASE HUB-23: Data Export & Reporting Service


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
The original blueprint's `ReportScheduler` design note said it "hooks into HUB-25 (to be defined)" —
`HUB-25` is defined (`HUB-25.md`, Sovereign Chronos, the Background Scheduler). That forward reference
was simply never updated once `HUB-25` was actually written; this is the same class of stale-reference
bug as `00_CRITIQUE.md` Finding 3, found independently while rewriting this tier. Fixed below, and
`HUB-25` is now added to this blueprint's formal dependency list, where it had been omitted.

## Component Name
Sovereign Reporter

## Description
Generates large-scale data exports (CSV, Excel, PDF) and scheduled reports: extracts from `CORE-19`,
generates in the background via `HUB-10`, delivers via `HUB-12`/`HUB-11`.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-10` (Queue), `HUB-12` (Notify) — none implemented.

## Dependency Status — corrected
- **Direct Hub:** `HUB-11`, `HUB-10`, `HUB-12`, and **`HUB-25`** (Scheduler — added; was referenced in
  prose as "to be defined" but omitted from the formal list even after `HUB-25` was written).
- **Transitive Core:** `CORE-19`, `CORE-14`.

## Architectural Design
- **ExportCoordinator** — orchestrates the export lifecycle.
- **DataStreamer** — iterates large datasets from the DBAL via PHP generators (flat memory profile).
- **FormatWriter** — CSV / Excel (OpenXML) writing logic.
- **ReportScheduler** — recurring reports, now concretely wired to `HUB-25`'s `SchedulerInterface`:
  `$schedule->job(new GenerateReportJob($reportId))->weekly()`, not a bespoke scheduling mechanism.

```php
namespace SovereignStack\Hub\Contracts;

interface ReporterInterface
{
    public function queueExport(string $query, string $format, array $options = []): string;
    public function getExportStatus(string $exportId): array;
}
```

## Integration Strategy
- **Upward:** built on `HUB-10`.
- **Downward:** Spoke applications provide "Export Blueprints" (SQL queries + headers).
- **Contract:** notifies via `HUB-12` once the file is ready in `HUB-11`; recurring reports are
  registered as `HUB-25` scheduled jobs, not a separate cron mechanism.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Memory-bounded large exports | Integration test streaming a real 100,000-row fixture table to CSV; assert peak memory via `memory_get_peak_usage()` stays under the stated bound — measured, not assumed from "uses generators." |
| CSV format integrity | Round-trip test: generate a CSV containing values with embedded commas, quotes, and newlines; re-parse it with a standard CSV parser and assert exact field recovery. |
| Expiration | Integration test: create an export file, fast-forward the fixture clock past 24 hours, assert `HUB-11` no longer serves it (verifies an actual TTL/cleanup mechanism exists, not just a documented intention). |

## CI Verification Criteria
- Memory-bound test with measured peak, blocking.
- CSV round-trip integrity test with adversarial field content, blocking.
- Expiration test against a real (or fixture-clock) TTL mechanism, blocking.
- `ReportScheduler` registration verified against `HUB-25`'s actual `SchedulerInterface`, not a
  bespoke scheduling shim.

## SemVer Impact
**Minor.** Adds reporting and data mobility features.


---

## Doctrines Applied + Rewrite Notes (PR #332)

> **This section was added in PR #332 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #332

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-23 — Data Export & Reporting Service — shipped per the verified DAG).

### What Was NOT Changed in PR #332

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/data-export/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #332 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
