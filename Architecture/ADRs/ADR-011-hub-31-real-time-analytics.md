# ADR-011: HUB-31 — Real-Time Analytics & Metrics Ledger

**Status:** **Accepted** (2026-08-13)

**Date:** 2026-08-05 (Proposed); 2026-08-13 (Accepted)

**Author:** DGCI (solo tech lead)

**Deciders:** DGCI (architecture lead)

**Closes:** `OPEN-DECISIONS.md` OD-01

**Supersedes:** nothing.

**Related:** `Critiques/00_CRITIQUE.md` (Finding 14 in critique revisions 2 and 3), `INDEX.md` §3 Pattern B,
`CrossCutting/STRUCTURE-01-Wheel.md` §A.8, `OPEN-DECISIONS.md` OD-01.

> **Numbering note.** Two predecessor documents also claimed the identifier `ADR-011`:
> `CrossCutting/THREAT_MODEL.md` §10 recommended filing "ADR-011" for post-quantum JWT migration, and
> `Migration/04_MIGRATION_PLAN.md` cited "ADR-011" for the SuperPHP design rationale. The migration
> plan's citation was a misnumbering — SuperPHP is **ADR-005**, corrected during migration
> (`Verification/INCONSISTENCIES.md` #3). The threat model's recommendation is a *future* ADR and has
> been reallocated to **ADR-012** (`OPEN-DECISIONS.md` OD-02). `ADR-011` is therefore free and is used
> here, matching the consolidation plan.

---

## Context

Five Internal/External Spoke blueprints independently declared a dependency on
`HUB-28: Distributed Ledger & Analytics Engine`. **No such component exists.** The real `HUB-28` is
**Sovereign Versioner** (API versioning: URL-, header-, and Accept-header-based schemes) and has
nothing to do with analytics.

This mislabeling was one of eight recurring drift patterns (Finding 14, `INDEX.md` §3 Pattern B). The
five affected spokes were corrected:
- `ISPOKE-05`, `ISPOKE-12`, `ISPOKE-13` → `HUB-31 (pending)`
- `ISPOKE-15` → `HUB-23` (Reporter, async batch export)
- `ESPOKE-05` → `HUB-23` (Reporter)

Three spokes — `ISPOKE-05` (Sovereign Insight), `ISPOKE-12` (Sovereign Impact), and `ISPOKE-13`
(Sovereign Ledger, Billing) — have carried a hard dependency on a real-time analytics and metrics
capability since that correction. Each blueprint explicitly marked itself "blocked, `HUB-31` not yet
specified." This gap was tracked as `OPEN-DECISIONS.md` OD-01.

The blueprint itself (`Architecture/Hub/HUB-31.md`) was authored on 2026-08-13 and committed in
`5d070d0a`. It was designed against the *existing* interface expectations of the three consumer
spokes — not by inventing a new contract that would require rewriting them. The
`RealTimeMetricsInterface` (`record()`, `query()`, `currentValue()`) maps directly to the consumer
shapes already assumed in those blueprints.

## Decision

**Accept `HUB-31: Sovereign Ledger (Metrics)` — Real-Time Analytics & Metrics Ledger as a canonical
Hub-tier blueprint.**

- **ID:** HUB-31
- **Name:** Sovereign Ledger (Metrics) — distinct from `HUB-22` Sovereign Ledger (Billing) and
  `ISPOKE-13` Sovereign Ledger (Billing) by tier context. If "Sovereign Ledger" comes up in
  conversation, confirm which tier before assuming which one.
- **Namespace:** `SovereignStack\Hub\Contracts\RealTimeMetricsInterface`
- **Canonical count impact:** 101 → **102** (Hub tier: 30 → 31)
- **Blocked spokes unblocked (design level):** `ISPOKE-05`, `ISPOKE-12`, `ISPOKE-13` — their
  "blocked on HUB-31" status is lifted at the design level. Implementation remains gated by HUB-31's
  own dependency chain.

## Consequences

### Positive

- **Three spokes unblocked at design level.** `ISPOKE-05`, `ISPOKE-12`, and `ISPOKE-13` no longer carry
  a phantom dependency. Their blueprints can be deepened without rewriting their analytics contracts.
- **No new datastore technology.** HUB-31 uses the existing two-tier stack: `HUB-02` (Redis, hot tier)
  + `CORE-19` (MySQL 8/InnoDB, durable/rollup tier) per `ADR-013`. No time-series database, no third
  datastore to maintain solo.
- **Fits existing contracts.** The `RealTimeMetricsInterface` was reverse-engineered from the three
  consumer blueprints' existing assumptions. No consumer rewrite required.
- **Tenant isolation by default.** Every table and query is `tenant_id`-scoped via `HUB-21`, matching
  the isolation severity class of `HUB-21` and `BRIDGE-01`.

### Negative / Risks

- **Implementation backlog grows.** HUB-31 is 🔴 **Blocked** on `CORE-02` (DI Container), `CORE-19`
  (DBAL), `HUB-02` (Cache), `HUB-10` (Queue), and `HUB-25` (Scheduler). None of these are implemented
  yet. HUB-31 cannot be built until its dependencies are in the matrix and deepened.
- **Not part of Milestone 0.** Per `SDLC-AGRD.md` §4, Milestone 0 is 8 blueprints: `CORE-02`,
  `CORE-04/05/06`, `HUB-01`, `BRIDGE-01`, `ISPOKE-09`, `ESPOKE-01`. HUB-31 is a lap-admission
  candidate once `HUB-02` and `HUB-10` are in the matrix — not earlier.
- **Count inflation.** 102 blueprints for 1 engineer is a large surface. Mitigation: Spiral Deepening
  (§4.3) admits blueprints only when their dependencies are ready; HUB-31 will not be admitted until
  its dependency chain is satisfied.

### Neutral

- HUB-31 does not change any existing blueprint's interface. It is a new blueprint, not a modification.
- The hospitality vertical (ADR-015) and HUB-31 are independent decisions. ADR-015 added 5 Spokes;
  ADR-011 adds 1 Hub. Both are recorded separately.

## Rejected Alternative

**Fold metrics into HUB-23 (Reporter).** Rejected because `HUB-23` is explicitly scoped as an *async
batch export* service (CSV/JSON/Parquet snapshots, scheduled reports). Real-time metrics (sub-second
`currentValue()`, live dashboards, feature-rollout impact monitoring) require synchronous hot-tier
reads and a different data model (sliding-window aggregates vs. full-table scans). Folding both
concerns into HUB-23 would violate single-responsibility and produce a bloated, hard-to-reason-about
component. The two-tier design (hot/durable) is cleaner as a separate blueprint.

## Related

- `Architecture/Hub/HUB-31.md` (the blueprint this ADR accepts)
- `OPEN-DECISIONS.md` OD-01 (resolution of this OD)
- `ADR-013` (MySQL 8 InnoDB primary datastore — HUB-31's DDL follows this)
- `ADR-006` (Redis 7 caching — HUB-31's hot tier follows this)
- `ISPOKE-05.md`, `ISPOKE-12.md`, `ISPOKE-13.md` (the three unblocked consumers)
- `HUB-23.md` (Reporter — the rejected fold target)
- `HUB-22.md` (Sovereign Ledger, Billing — naming disambiguation note)
- `SDLC-AGRD.md` §4.3 (lap admission — HUB-31 is a candidate once dependencies are ready)

---

### Provenance

Originally drafted 2026-08-05 as Proposed, pending blueprint specification. Blueprint authored and
committed in `5d070d0a` (2026-08-13). Accepted 2026-08-13 per architecture lead decision on OD-01.
This ADR ratifies the acceptance decision and updates the canonical count from 101 to 102.
