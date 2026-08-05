# ADR-011: HUB-31 — Real-Time Analytics & Metrics Ledger

**Status:** **Proposed** — not accepted. Do not treat `HUB-31` as part of the Hub tier until this ADR
is accepted and `INDEX.md` §2/§4 are updated in the same commit (Governance Rule 1).
**Date:** 2026-08-05
**Deciders:** DGLab architecture team (pending)
**Supersedes:** nothing.
**Related:** `Critiques/00_CRITIQUE.md` (Finding 14 in critique revisions 2 and 3 — see the appendix
of that file), `INDEX.md` §3 Pattern B, `CrossCutting/STRUCTURE-01-Wheel.md` §A.8,
`OPEN-DECISIONS.md` OD-01.

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
nothing to do with analytics. This is Pattern B of the eight cross-reference drift patterns catalogued
in `INDEX.md` §3.

Reading each dependent's actual described need shows the five references are **not** five instances of
one problem. They are three different situations:

1. **A genuinely missing component.** `ISPOKE-05` (Sovereign Insight — BI dashboards, live KPIs),
   `ISPOKE-12` (Sovereign Toggle — real-time experiment/rollout impact monitoring), and `ISPOKE-13`
   (Sovereign Ledger — real-time MRR / churn / LTV) each describe a **live, streaming** business-metrics
   need. Three further External Spokes reference the same gap: `ESPOKE-05` (conversion tracking),
   `ESPOKE-11`, and `ESPOKE-14` (public metrics).
2. **A mislabelled pointer to a component that already exists.** `ISPOKE-10`'s real need is async
   PDF/CSV export, which is exactly `HUB-23` (Sovereign Reporter). Already redirected to `HUB-23`.
3. **An orphaned declaration.** `ISPOKE-15` listed the dependency but never referenced it in its own
   design section. Already dropped rather than remapped.

So the open question is narrow: **does the stack need a real-time business-metrics component, or not?**

Two existing components are near neighbours and neither fits:

- **`HUB-15` (Sovereign Pulse — Health Check & Service Discovery)** is scoped to *operational* health:
  is a service up, is its dependency reachable, what is its streak of failed probes. It has no notion
  of tenant-scoped business events, no retention model for metric series, and no query surface for
  "MRR over the last 30 days by plan."
- **`HUB-23` (Sovereign Reporter)** is scoped to *asynchronous batch* export — CSV/Excel/PDF generation
  through `HUB-10` (Queue), written to `HUB-11` (Cloud Storage). Its latency model is minutes, not
  seconds. Redirecting real-time dependents to it would reintroduce exactly the class of mislabelling
  this consolidation exists to eliminate.

Doing nothing is also not free. Six blueprints currently declare a `pending` dependency on an
unspecified component, which means six blueprints cannot be built and `INDEX.md` §4's Hub total is
contested (`30` in the canonical index versus `31` in the rewrite set).

## Decision (proposed)

**Register `HUB-31: Real-Time Analytics & Metrics Ledger` as a committed-but-unspecified Hub-tier
component.**

Proposed scope, sufficient to distinguish it from `HUB-15` and `HUB-23`:

- **Ingest.** Subscribes to `HUB-09` (Sovereign Signal — Event Bus) for business events carrying a
  `lane` (tenant ULID) and a metric name. Ingest is fire-and-forget from the producer's perspective;
  back-pressure is handled inside `HUB-31`, never propagated to the producer's request path.
- **Store.** A tenant-scoped, append-only metric series. PostgreSQL 16 per ADR-007, `CHAR(26)` ULID
  primary keys per ADR-009, `JSONB` + GIN for metric dimensions, and time-based partitioning for the
  series table.
- **Query.** A read API returning windowed aggregates (`sum`, `count`, `p50/p95/p99`, rate) with a
  freshness target measured in seconds, not minutes. The freshness target is **provisional,
  unverified** until a harness, baseline, and load model exist (Governance Rule 2).
- **Non-goals.** Service health (`HUB-15`), batch export artefacts (`HUB-23`), the tamper-evident
  compliance record (`HUB-06` — `HUB-31` is *not* an audit log and must never be used as one), and
  raw log storage (`CORE-09`).

Until this ADR is accepted:

- `INDEX.md` §4 counts the Hub tier as **30**, with `HUB-31` listed separately as *proposed*.
- Dependents cite `HUB-31 (pending)` and are marked blocked.
- No `Hub/HUB-31.md` blueprint file is created. `Verification/lint/run.php` therefore permits
  `HUB-31` as a *reference* while no file exists, but only if this ADR is present.

## Alternatives considered

| Option | Pros | Cons |
|---|---|---|
| **Accept `HUB-31` as a new Hub component** (this proposal) | Matches six independently-written blueprints' actual described need; keeps `HUB-15` and `HUB-23` correctly scoped; unblocks the Internal Spoke tier | Adds a 31st Hub service — roughly one additional Hub-tier phase in `Migration/04_MIGRATION_PLAN.md`; more operational surface |
| **Extend `HUB-23` (Reporter) to cover real-time** | No new component; reuses an existing team/package | Fuses two latency models (minutes vs seconds) into one service; the batch export path and the streaming path have opposite storage, retention, and back-pressure requirements; recreates the mislabelling problem in a new form |
| **Extend `HUB-15` (Health) to cover business metrics** | No new component; already has a polling loop and a Redis-backed state store | Conflates service health with business metrics; `HUB-15` is deliberately stateless-across-restarts, which is wrong for a metrics ledger; would make a Critical-tier operational component depend on tenant business semantics |
| **Push the requirement to each dependent spoke** | Zero Hub-tier change | Six independent, incompatible metrics implementations; no cross-spoke aggregation; violates the "Hub owns shared services" rule that defines the tier |
| **Reject the requirement; strike all six references** | Smallest system | Six blueprints independently and consistently described this need. Deleting the references does not delete the requirement — it just hides it until implementation time |

## Consequences if accepted

- `INDEX.md` §2 gains a `HUB-31` row; §4's Hub total becomes **31** and the grand total becomes **97**.
- `Migration/04_MIGRATION_PLAN.md` gains one Hub-tier phase.
- `ISPOKE-05`, `ISPOKE-12`, `ISPOKE-13`, `ESPOKE-05`, `ESPOKE-11`, and `ESPOKE-14` move from
  "blocked on an unspecified component" to "blocked on an unwritten blueprint" — a materially better
  state, because the dependency becomes schedulable.
- A `Hub/HUB-31.md` blueprint must be authored to the `AUTHORING_GUIDE.md` fidelity bar before any
  dependent can be built.
- `HUB-31` becomes a downstream consumer of `HUB-09`, adding one more consumer to the event bus's
  load model.

## Consequences if rejected

- All six `HUB-31 (pending)` references must be individually re-decided and rewritten — each dependent
  must either drop the capability, absorb it locally, or be redirected with an explicit rationale.
- `CrossCutting/STRUCTURE-01-Wheel.md` §A.1 and §A.8 must be updated to state the Hub ring is
  permanently `HUB-01..30`.
- `OPEN-DECISIONS.md` OD-01 closes as "rejected", and the rejection rationale must be recorded here.

## Compliance / verification

`Verification/lint/run.php` enforces the following while this ADR is `Proposed`:

- `HUB-31` may be referenced only with an adjacent `pending`/`proposed` marker.
- No `Hub/HUB-31.md` file may exist.
- `INDEX.md` §4 must count the Hub tier as 30.

If the status changes to `Accepted`, those three rules invert — and the lint must be updated in the
same commit.
