# Lap 2 Entry Gate — Dependency-Graph Reconciliation

**Status:** Lap 2 entry gate. Required before Lap 2 admission.
**Created:** 2026-09-26
**Verified against:** DGLab HEAD `0de1166`

---

## 1. Purpose

Per SDLC recalibration (PR #276): the historical §5.2 graph in INDEX.md covers only 37 of 102 blueprints and predates the current 18-package implementation. Lap 2 widening MUST NOT select candidates from the stale graph. This document reconciles the graph with implementation evidence.

---

## 2. Actual Dependency Graph (from composer.json require blocks)

This is the ground-truth dependency graph, derived from `composer.json` files of all 18 implemented packages:

```
LEAF PACKAGES (no internal dependencies — Core foundation):
  CORE-02 Container        (no deps)
  CORE-03 EventDispatcher  (no deps)
  CORE-04 HTTP Message     (no deps)
  CORE-10 Config           (no deps)
  CORE-14 Filesystem       (no deps)
  CORE-16 Crypto           (no deps)
  CORE-19 DBAL             (no deps)

CORE WITH DEPENDENCIES:
  CORE-09 Logger           → CORE-10 Config
  CORE-08 ErrorHandler     → CORE-09 Logger
  CORE-06 Router           → CORE-04 HTTP Message
  CORE-05 Middleware       → CORE-04 HTTP Message, CORE-06 Router
  CORE-18 Kernel           → CORE-02, CORE-03, CORE-04, CORE-05, CORE-06, CORE-08, CORE-09, CORE-10

HUB (shared platform capabilities):
  HUB-30 Config            → CORE-02 Container, CORE-10 Config
  HUB-04 Identity          → CORE-02, CORE-03, CORE-04, CORE-09, CORE-10, CORE-16, CORE-18, CORE-19

BRIDGE:
  BRIDGE-01 Vanguard       → CORE-04, CORE-05, CORE-06

SPOKE/INTERNAL (application entry points):
  ISPOKE-09 Codex          → (no deps — stub)
  Showcase                 → CORE-02, CORE-03, CORE-04, CORE-14, CORE-18, CORE-19, HUB-04

SPOKE/EXTERNAL:
  ESPOKE-01 Canvas         → CORE-04
```

**Direction rule (LOCKED):** All edges point inward (Spoke → Hub → Core). No Core package depends on Hub/Spoke/Bridge.

---

## 3. Discrepancies: §5.2 Graph vs Actual Implementation

| §5.2 edge | Actual state | Classification |
|---|---|---|
| C02 → C10 (Container → Config) | Container has NO deps | Wrong direction |
| C02 → C09 (Container → Logger) | Container has NO deps | Stale edge |
| C10 → C08 (Config → ErrorHandler) | Config has NO deps | Stale edge |
| C09 → C18 (Logger → Kernel) | Logger → Config (C10), not Kernel | Wrong edge |
| C08 → C18 (ErrorHandler → Kernel) | ErrorHandler → Logger (C09), not Kernel | Wrong edge |
| C02 → C18 (Container → Kernel) | Kernel → Container (reverse) | Wrong direction |
| C03 → C18 (EventDispatcher → Kernel) | Kernel → EventDispatcher (reverse) | Wrong direction |
| C04 → C05 (HTTP → Middleware) | Middleware → HTTP (reverse) | Wrong direction |
| C10 → H01 (Config → Hub Config) | Hub Config → Config (reverse) | Wrong direction |
| C19 → H01 (DBAL → Hub Config) | Hub Config has no DBAL dep | Stale edge |
| C19 → H04 (DBAL → Identity) | Correct ✅ | Match |
| C16 → H04 (Crypto → Identity) | Correct ✅ | Match |
| C18 → C06 (Kernel → Router) | Correct ✅ | Match |
| C05 → C06 (Middleware → Router) | Correct ✅ | Match |
| Showcase | NOT IN GRAPH | Missing component |
| C07, C11, C12, C13, C15, C17, C20 | NOT IMPLEMENTED | Blueprint only |
| H02, H03, H06, H08, H11, H15, H19, H20 | NOT IMPLEMENTED | Blueprint only |

**Summary:** 10 wrong-direction/stale edges, 1 missing component (Showcase), ~15 unimplemented blueprint-only components in the §5.2 graph.

---

## 4. Reconciled Admission Graph

The reconciled graph for Lap 2 admission purposes:

```
IMPLEMENTED (18 packages, 5 tiers):

Core leaves (depth 2-3, deepen in Lap 2):
  Container, EventDispatcher, HTTP Message, Config, Filesystem, Crypto, DBAL

Core with deps (depth 2-3):
  Logger → Config
  ErrorHandler → Logger
  Router → HTTP Message
  Middleware → HTTP Message, Router
  Kernel → Container, EventDispatcher, HTTP, Middleware, Router, ErrorHandler, Logger, Config

Hub (depth 2, deepen in Lap 2):
  Config(HUB-30) → Container, Config(CORE-10)
  Identity(HUB-04) → Container, EventDispatcher, HTTP, Logger, Config, Crypto, Kernel, DBAL

Bridge (depth 2):
  Vanguard → HTTP Message, Middleware, Router

Spoke/Internal (depth 1-2):
  Codex (depth 1 — stub)
  Showcase (depth 2) → Container, EventDispatcher, HTTP, Filesystem, Kernel, DBAL, Identity

Spoke/External (depth 2):
  Canvas → HTTP Message
```

---

## 5. Lap 2 Admission Analysis

### Deepening cohort (Lap 1 → depth 3)

Per the per-blueprint floor model: packages admitted at Lap 1 target depth 3 in Lap 2.

| Package | Current depth | Lap 2 target | Deepening work |
|---|---|---|---|
| CORE-14 Filesystem | 2 | 3 | P0-3 PathGuard prefix collision fix + P1-4 writeStream integrity + error-path tests |
| HUB-04 Identity | 2 | 3 | P0-1 RequestContext integration + P0-2 JWT ES256/RFC 7518 + P1-3 transaction boundaries |
| Showcase Spoke | 2 | 3 | P1-1 publishProduct authorization + P1-2 hydration fix + error-path tests |

These 3 deepening units are the **first measurement** of depth-2→3 throughput.

### Widening candidates

The reconciled graph shows what's needed but not implemented:

1. **LMS Spoke** — next product in the Showcase+LMS plan. Depends on the same already-implemented Hub capabilities (Identity, DBAL, Filesystem, Events, Kernel). No new Hub capability needed — parallel product Spoke. **Strongest widening candidate.**

2. **CORE-15 Cache** — referenced in §5.2 graph (C15 → C14, C16 → C15) but not implemented. Would unblock HUB-02 (Cache & State). **Secondary candidate — depends on whether HUB-02 is needed in Lap 2.**

3. **CORE-14 deepen before CORE-15 widen** — CORE-14 is at depth 2 with known P0 security issues. Deepening it is higher priority than widening to CORE-15.

4. **No new Hub capabilities needed for LMS** — LMS uses the same Hub platform (Identity, Config) as Showcase. It's a parallel product, not a new platform capability.

### Evidence-based admission recommendation (not final — SDLC decides)

```
Lap 2 admitted work:
  DEEPEN (3 units):
    Filesystem depth 2→3 (P0 path guard + P1 stream integrity)
    Identity depth 2→3 (P0 JWT + P0 RequestContext + P1 transactions)
    Showcase depth 2→3 (P1 authorization + P1 hydration + error paths)

  WIDEN (1 unit):
    LMS Spoke depth 1-2 (parallel product, same Hub deps as Showcase)

  MEASURE:
    First deepening throughput (depth 2→3 units/day)

  NOT ADMITTED:
    AI App Architect (deferred)
    CORE-15 Cache (not needed by admitted work)
    Broad P2 refactors (AST lint, CI auto-discovery)
```

---

## 6. Lap 2 Exit Criteria (framework)

| Criterion | Status |
|---|---|
| Reconciled dependency graph produced | ✅ (this document) |
| Lap 2 admission recorded from evidence | ⏳ (pending SDLC decision) |
| First deepening throughput measured | ⏳ (Lap 2 work) |
| Build throughput recalibrated | ⏳ (if widening occurs) |

---

*End of Lap 2 Dependency-Graph Reconciliation.*
