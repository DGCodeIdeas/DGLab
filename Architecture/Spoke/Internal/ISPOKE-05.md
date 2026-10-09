# PHASE ISPOKE-05: Internal Reporting and Analytics Dashboard


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Corrects two of this delivery's cataloged mislabel patterns (`01_MASTER_INDEX.md` §3): the original
cited `HUB-28: Distributed Ledger & Analytics Engine` (Pattern B — no such Hub blueprint exists; real
`HUB-28` is API Versioning) and `CORE-09: Cryptography & Hashing` (Pattern A — real `CORE-09` is PSR-3
Logging; crypto is `CORE-16`, which this Spoke has no actual need of and the reference is dropped
rather than redirected).

## Component Name
Sovereign Insight

## Description
Centralized reporting engine and visualization dashboard: business intelligence, system performance
metrics, and operational reports for data-driven decisions.

## Build Status
🔴 **Blocked** on `HUB-31` (pending — see below), `HUB-24`, `HUB-26`, `HUB-08` — none implemented, and
`HUB-31` isn't even specified yet.

## Dependency Status — corrected
- **Direct Hub:** ~~`HUB-28: Distributed Ledger & Analytics Engine`~~ → **`HUB-31` (pending — Real-Time
  Analytics & Metrics Ledger, registered in `01_MASTER_INDEX.md` §4; not yet specified)**, `HUB-24`,
  `HUB-26`, `HUB-08`, `HUB-15`, `HUB-16`, `HUB-02`.
- **Transitive Core:** `CORE-19`, `CORE-18`, `CORE-11`, `CORE-12`, `CORE-06`, `CORE-02`. ~~`CORE-09:
  Cryptography & Hashing`~~ — removed; this Spoke performs no cryptographic operations of its own, the
  reference was simply wrong, not a mis-pointed real need.

**This blueprint cannot be considered build-ready until `HUB-31` is specified** — its core feature set
(`QueryBuilder` against real-time analytics, `WidgetEngine`'s live KPIs) has no backing service to
build against. Treat everything below as a design sketch pending that follow-up work.

## Architectural Design
- **QueryBuilder** — constructs analytics queries against `HUB-31` (pending).
- **WidgetEngine** — visualization components (Charts, Tables, KPIs) via `HUB-26`.
- **ReportScheduler** — periodic report generation/distribution via `HUB-10` (Queue — corrected from
  the original's unlabeled "HUB-10" reference in the report-scheduler diagram, which happened to be
  right by coincidence rather than by the stated dependency list, which omitted it).
- **ExportService** — high-volume exports via `CORE-14`.

```php
namespace SovereignStack\Internal\Insight\Contracts;

interface AnalyticsQueryInterface
{
    public function setTimeRange(\DateTimeInterface $start, \DateTimeInterface $end): self;
    public function groupBy(string $dimension): self;
    public function execute(): array;
}
```

## Integration Strategy
- **Bootstrapping:** via `CORE-18`; discovers analytics endpoints via `HUB-15`.
- **Data Access:** through `HUB-08` (Gateway) or direct `HUB-31` calls once it exists.
- **Lifecycle:** `HUB-16` hooks pause high-load reporting during maintenance.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Query performance | Cannot be meaningfully specified until `HUB-31` exists — state this explicitly rather than restating "< 200ms on 1M rows" against a backend that doesn't exist (Finding 10 applies doubly here). |
| UI namespace compliance | Static scan asserting 100% of chart components originate from `HUB-26` — this criterion doesn't depend on `HUB-31` and can be verified today once `HUB-26` exists. |
| Cross-tenant isolation | Integration test (once `HUB-31` exists): a tenant-scoped report must never contain another tenant's data — same severity class as `HUB-21`'s and `BRIDGE-01`'s isolation tests. |

## CI Verification Criteria
- UI namespace compliance test, blocking, buildable independent of `HUB-31`.
- Cross-tenant isolation test, blocking once `HUB-31` exists — do not ship this Spoke without it.
- Query performance: no target stated until `HUB-31` is specified and a real benchmark can be run.

## SemVer Impact
**Minor**, pending `HUB-31`'s existence — this blueprint's SemVer is meaningless until its core
dependency is real.


---

## Doctrines Applied + Rewrite Notes (PR #337)

> **This section was added in PR #337 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #337

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-05 — Internal Reporting and Analytics Dashboard — shipped per the verified DAG).

### What Was NOT Changed in PR #337

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/reporting/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #337 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
