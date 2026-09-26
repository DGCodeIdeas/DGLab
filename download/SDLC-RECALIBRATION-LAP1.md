# SDLC Recalibration — Lap 1 Measurement + Lap 2 Framework

**Status:** Canonical. Closes Cooldown 1 measurement/governance portion.
**Created:** 2026-09-26
**Verified against:** DGLab HEAD `72a9f47`

---

## 1. Observed Lap 1 Data

| Metric | Observed value | Interpretation |
|---|---|---|
| Work units completed | 5 | 3 new capabilities + 2 deepening units |
| Calendar duration | ~2 days | Solo tech lead, AI-agent augmented |
| Observed build throughput | **2.5 build units/day** | Measured Lap 1 rate |
| Build throughput unit | depth 1 → 2 work | Applies only to observed work |
| Deepening throughput | **Unknown** | No valid depth 2 → 3+ baseline exists |
| Lap cadence | 1 completed lap | Insufficient for long-term cadence projection |

### Throughput separation rule (LOCKED)

```
BUILD THROUGHPUT
1 → 2
2.5 work units/day
        │
        └── measured

DEEPENING THROUGHPUT
2 → 3 → 4 → 5
unknown
        │
        └── must be measured in Lap 2
```

The observed 2.5 build units/day MUST NOT be converted into a depth-3, depth-4, or depth-5 throughput estimate.

No statement such as `2.5 units/day × remaining blueprints` will be used as a project-duration forecast.

No conversion such as `2.5 build units/day ≈ 2.5 depth levels/day` is permitted.

> **Only throughput measured in the same work unit may be used to estimate future work in that unit.**

The first depth-2 → depth-3 work completed in Lap 2 establishes the initial deepening-throughput observation.

---

## 2. Lap 2 Admission Strategy

Lap 2 follows the canonical Spiral Deepening rule:

```
Lap 2
 ├── Widen
 │    └── admit next dependency-supported blueprint(s)
 │
 └── Deepen
      └── existing Lap 1 blueprints by +1 depth
```

### Deepening candidates (Lap 1 cohort → Lap 2 target)

| Lap 1 result | Lap 2 target | Notes |
|---|---|---|
| Filesystem depth 2 | depth 3 | Error-path tests: traversal, integrity, stream limit |
| Identity depth 2 | depth 3 | Auth failure tests, JWT interoperability, transaction boundaries |
| Showcase depth 2 | depth 3 | Error paths: DuplicateSku, NotFound, authorization consistency |
| RequestContext | next defined depth | Per its blueprint/status record |
| Health | next defined depth | Per its blueprint/status record |

This cohort is the mechanism for measuring the previously unknown deepening throughput.

### Widening constraint

The canonical dependency DAG (`INDEX.md` §5.2) covers only 37 of 102 declared blueprints. The current implementation has evolved beyond that graph (Showcase, Identity, Filesystem are now real implementation boundaries not represented in §5.2).

Therefore Lap 2 MUST NOT select a widening candidate solely because it appears early in the historical §5.2 graph.

Before the widening decision is considered authoritative, the dependency evidence MUST be reconciled with the current implementation state.

---

## 3. Dependency-Graph Reconciliation Gate (Lap 2 entry gate)

This is a **Lap 2 entry gate**, not a Cooldown 1 deliverable. It MUST:

- Identify all currently implemented packages (18 packages)
- Map each to its canonical component ID
- Identify current inward dependencies (from composer.json require blocks)
- Identify implemented components absent from the current §5.2 graph
- Identify graph components whose implementation/status is stale
- Preserve the existing tier-direction rule
- Produce a deterministic dependency graph suitable for admission decisions

It MUST NOT become a redesign of the 102-blueprint catalogue.

```
historical dependency graph (INDEX.md §5.2, 37 blueprints)
        +
current implementation evidence (18 packages, composer.json deps)
        ↓
usable Lap 2 admission graph
```

---

## 4. Lap 2 Throughput Measurement Plan

Lap 2 MUST record at least two separate measurements:

### Build measurement
```
new blueprint admission
depth 1 → 2
units / elapsed engineering day
```

### Deepening measurement
```
existing blueprint
depth 2 → 3
units / elapsed engineering day
```

They MUST NOT be merged into one throughput number.

At the end of Lap 2:
```
build throughput:
    Lap 1 observed = 2.5 units/day
    Lap 2 observed = TBD

deepening throughput:
    Lap 2 first measurement = TBD

combined project throughput:
    not defined
```

---

## 5. No Long-Range Schedule Projection

Cooldown 1 produces no new project completion date.

The following remain explicitly unknown:
- depth-2 → depth-3 throughput
- depth-3 → depth-4 throughput
- depth-4 → depth-5 throughput
- widening rate
- future lap cadence
- cooldown overhead
- total number of laps

Per SDLC §9: these unknowns remain recorded rather than silently resolved with assumptions.

---

## 6. Lap 2 Admission Decision (framework, not final)

```
ADMITTED (framework):
    Deepening: Lap 1 implementation cohort → +1 depth where applicable
    Widening: dependency-supported candidate(s) after graph reconciliation

MEASURE:
    First valid depth-2 → depth-3 throughput

DO NOT ADMIT:
    Work selected solely from stale historical ordering
    AI App Architect product (deferred)
    Unrelated P0/P1 backlog fixes (they are candidates, not auto-admitted)
    Broad architectural refactors
```

The findings backlog (Task 69) remains available as candidate work, but does not automatically enter Lap 2 merely because it has P0/P1 classification.

Admission remains an SDLC decision based on dependency, depth, and current evidence.

---

## 7. Evidence Sources for Lap 2 Admission

Lap 2 admission MUST use the following evidence in order:

1. Current generated architecture state (`ARCHITECTURE_BASELINE.md`)
2. Current package implementation state (18 packages, depth assessment)
3. Current declared dependency edges (composer.json require blocks)
4. Current blueprint/INDEX mapping (INDEX.md, reconciled with implementation)
5. Lap 1 depth + throughput measurements (this document)
6. Lap 2 admission decision

The architecture graph is an input to the decision, not an unquestioned authority when it conflicts with generated repository state.

---

## 8. Cooldown 1 Exit Criteria

| Criterion | Status |
|---|---|
| Worklog reconciliation completed | ✅ (PR #272) |
| ADR discrepancy reconciliation completed | ✅ (PR #273) |
| Architecture findings backlog recorded | ✅ (PR #274) |
| Three fitness functions implemented and passing | ✅ (PR #275) |
| Lap 1 build throughput recorded as 2.5 build units/day | ✅ (this document) |
| Deepening throughput explicitly recorded as unknown | ✅ (this document) |
| No build-rate extrapolation into depth 3-5 work | ✅ (this document) |
| Current dependency-graph fidelity limitation explicitly recorded | ✅ (this document) |
| Lap 2 dependency graph reconciled | ⏳ Lap 2 entry gate (not Cooldown 1 scope) |
| Lap 2 admission recorded from reconciled evidence | ⏳ Lap 2 entry gate (not Cooldown 1 scope) |

### Cooldown 1 closure

> The measurement/governance portion of Cooldown 1 is complete. The reconciled admission graph is a Lap 2 entry gate, not an excuse to reopen Cooldown 1 indefinitely.

> **DGLab has measured its initial build throughput (2.5 build units/day), but has not yet measured deepening throughput. Lap 2 is therefore the first measurement lap for depth-2 → depth-3 work. Widening must be dependency-driven, but the historical dependency graph must first be reconciled with the current implementation state. No long-range schedule or depth-throughput extrapolation is authorized.**

---

*End of SDLC Recalibration — Lap 1.*
