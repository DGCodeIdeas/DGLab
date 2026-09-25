# ARCHITECTURE-SDLC-FUSION.md

**Status:** Canonical. Locks the relationship between SPEC-001 and SDLC-AGRD.
**Created:** 2026-09-26
**Verified against:** DGLab HEAD `b111ce3`

---

## 1. Fusion Principle (LOCKED)

> **SDLC drives the process; SPEC defines the contracts.**

The SDLC-AGRD determines when work is admitted, its target depth, sequencing at the lap level, throughput calibration, cooldowns, and recalibration. SPEC-001 defines what the resulting architecture and implementation must satisfy. Neither document replaces the other.

### Authority Matrix

| Concern | Authority |
|---|---|
| Process, laps, admission, depth, throughput, cooldown | **SDLC-AGRD** |
| Architecture contracts and implementation constraints | **SPEC-001** |
| Repository implementation state | **Generated architecture baseline** (`ARCHITECTURE_BASELINE.md`) |
| Merge acceptance | **Executable fitness functions / CI** |
| Future product scope | **Deferred until admitted by SDLC** |

### Additional Rules

1. **A package's SDLC depth is evidence of implementation maturity, not a declaration that its design is correct.** A depth-2 package can still have a bad boundary; a depth-5 package can still violate the architecture.

2. **SPEC milestones are informational, not the process authority.** The SPEC's M0-M7 milestones describe what work looks like when admitted; they do not determine when work is admitted. The SDLC's lap model does.

3. **The SDLC determines the lap from evidence.** Lap content is selected based on: current capacity (N/W throughput), admission criteria (dependency graph), per-blueprint depth floors, and cooldown status — not on SPEC milestone sequencing.

4. **Spokes are application entry points.** Applications consume Hub capabilities and reach inward toward Core as required. Hub is not the application entry boundary. ApplicationFactory wires Spoke applications to their Hub capabilities.

---

## 2. Dependency Direction (LOCKED)

```text
Spoke Application
    │
    ├── application / controller / API concerns
    │
    ▼
Hub Capability
    │
    ├── domain
    ├── application contracts
    └── infrastructure adapters
    │
    ▼
Core
```

Therefore:
- **Showcase application → Spoke** (`packages/spoke/internal/showcase/`)
- **LMS application → Spoke** (`packages/spoke/internal/lms/`)
- Spokes may consume Hub capabilities (Identity, Config, Audit).
- Hub MUST NOT become the application entry point.
- `ApplicationFactory` wires the Spoke application to its Hub capabilities.
- Controllers belong under the Spoke application boundary.

---

## 3. SDLC ↔ SPEC Mapping

### 3.1 Laps → SPEC Milestones (informational, not process authority)

| SDLC Lap | SPEC Milestones | Status |
|---|---|---|
| Milestone 0 (walking skeleton) | M0-M3 (baseline, ring CI, RequestContext, ApplicationFactory) | ✅ Complete |
| Lap 1 (widen + deepen) | M4 (health) + Phase 1 (Filesystem, Identity) + M5a (Showcase) | ✅ Complete |
| Cooldown 1 | Worklog reconciliation, ADR discrepancies, lint expansion, recalibration | ⏳ Starting now |
| Lap 2 | Determined by SDLC from evidence (not by SPEC milestone list) | Not yet planned |

### 3.2 Fitness Functions → SDLC §6 Linter Expansion

| SDLC §6 Check | SPEC Implementation | Status |
|---|---|---|
| Soft-Freeze violations (§2.1) | `architecture-boundary-lint.py` + export-allow-list (SPEC §41) | ✅ Live |
| Blueprint-fidelity drift | `generate-architecture-baseline.py` → `ARCHITECTURE_BASELINE.md` (SPEC §50) | ✅ Live |
| Ring-boundary enforcement | `architecture-boundary-lint.py` ring checks (SPEC §40) | ✅ Live |
| Worker contamination safety | `WorkerContaminationTest` + `RequestContext` (SPEC §43) | ✅ Live |
| Service-locator prohibition | `architecture-boundary-lint.py` container-specific patterns (SPEC §41) | ✅ Live |

### 3.3 Depth Scale → SPEC Contracts

The SDLC's depth scale (§4.1) determines target depth per package. The SPEC determines what each depth level means contractually:

| Depth | SDLC meaning | SPEC contract that must hold |
|---|---|---|
| 1 | Stub — interface exists | Interface is frozen (§2.1); export-allow-list enforced (§41) |
| 2 | Happy path — intended case works | Application service template (9 dimensions); error taxonomy defined (§25) |
| 3 | Error paths — failure modes handled | Error-path tests pass; state-machine invariants tested; SPEC §25 taxonomy enforced |
| 4 | Observability | Request correlation (§8-§9); structured logging (§30); health split (§29) |
| 5 | Production hardening | Full-stack release verification (§32-§33); worker recycling verified (§35); rollback tested (§34) |
| 6 | At-scale verified | Load-tested against real targets; measured SLO data (§56) |

---

## 4. Current-State Snapshot (evidence-based)

### 4.1 Package Inventory (18 packages)

**Core (12):** config, container, crypto, dbal, error-handler, event-dispatcher, filesystem, http-message, kernel, logger, middleware, router

**Hub (2):** config (HUB-30), identity (HUB-04)

**Spoke/Internal (2):** codex (ISPOKE-09), showcase

**Spoke/External (1):** canvas (ESPOKE-01)

**Bridge (1):** vanguard (BRIDGE-01)

**Source files:** 157 PHP files
**Test files:** 94 PHP files
**Migrations:** 4 SQL files
**CI workflows:** 5
**Download docs:** 4 (SPEC-001, ARCHITECTURE_BASELINE, ADR-DISCREPANCY-REGISTER, SHOWCASE-LMS-IMPLEMENTATION-PLAN)

### 4.2 PR Timeline

| PR | Date | Description |
|---|---|---|
| #261 | Sep 24 | M0 baseline + M1 ring-boundary CI + SPEC-001 |
| #262 | Sep 24 | M2 RequestContext + contamination tests |
| #263 | Sep 24 | M3 ApplicationFactory + thin public/index.php |
| #264 | Sep 24 | Showcase + LMS implementation plan (docs) |
| #265 | Sep 24 | RequestContext userId + export-allow-list lint |
| #266 | Sep 25 | CORE-14 Filesystem plug |
| #267 | Sep 25 | ApplicationFactory $log readonly hotfix |
| #268 | Sep 25 | HUB-04 Identity plug |
| #269 | Sep 25 | M4 three-tier health split |
| #270 | Sep 25 | Showcase Spoke — Product domain at depth 2 |

**Total: 10 PRs merged in ~2 calendar days (AI-augmented, solo dev)**

### 4.3 Honest Depth Assessment

| Package | Depth | Evidence | Gap to next depth |
|---|---|---|---|
| CORE-02 Container | 3 | pulse/transient + WeakMap + cycle detection + 17 tests | Observability |
| CORE-03 EventDispatcher | 2 | PSR-14 + stamps | Listener failure classification (§25) |
| CORE-04 HTTP Message | 3 | full PSR-7/17 + immutability + security tests | Observability |
| CORE-05 Middleware | 3 | frozen pipeline + PerRequestHandler + order tests | Observability |
| CORE-06 Router | 3 | frozen router + compiler + path traversal tests | Observability |
| CORE-08 ErrorHandler | 4 | worker-safe + recursion guard + taxonomy doctrine | Taxonomy CI enforcement |
| CORE-09 Logger | 2 | PSR-3 | Structured fields (request_id/trace_id/error_class) |
| CORE-10 Config | 3 | immutable + frozen builder + env + tests | Observability |
| CORE-14 Filesystem | 2 | happy path + basic tests | Error-path tests |
| CORE-16 Crypto | 2 | AES-256-GCM + Argon2id + HKDF | Tamper/replay tests |
| CORE-18 Kernel | 3 | state machine + lifecycle + contamination tests | Observability |
| CORE-19 DBAL | 2 | Connection + QueryBuilder + Transaction | Error-path tests |
| HUB-04 Identity | 2 | User + JWT + roles + MySQL repo | Auth failure tests |
| HUB-30 Config | 2 | feature flags + config overrides | Error paths |
| BRIDGE-01 Vanguard | 2 | WAF + contract registry | Error paths |
| ISPOKE-09 Codex | 1 | in-memory stub | Real implementation |
| ESPOKE-01 Canvas | 2 | Canvas + SEO validator | Error paths |
| Showcase Spoke | 2 | Product entity + repo + app service | Error paths |

**Summary:** 1 at depth 1, 11 at depth 2, 5 at depth 3, 1 at depth 4. No depth 5 packages yet.

### 4.4 Throughput Data

```
Lap 1:
  Widened: 3 new packages (Filesystem, Identity, Showcase)
  Deepened: 2 existing (RequestContext userId, HealthController 3-tier)
  Total: 5 work units

  Wall-clock: ~2 calendar days (Sep 24-25, 2026)
  AI-augmented (Cline/Z.ai), solo tech lead

  Build throughput: 2.5 units/day (depth 1→2)
  Deepening throughput: UNKNOWN (no real deepening lap yet)

CAVEAT (per SDLC §5):
  Build rate ≠ deepening rate. Depth 3-5 work (error paths,
  observability, hardening, security review) may be slower than
  happy-path build. Do not extrapolate 2.5/day into deepening.
  Recalibrate after Lap 2 produces real deepening data.
```

### 4.5 SDLC Lap Status

| Milestone/Lap | Status |
|---|---|
| Milestone 0 (walking skeleton) | ✅ Complete (8 blueprints, Pulse trace verified) |
| Lap 1 (widen + deepen) | ✅ Complete (3 widened, 2 deepened) |
| Cooldown 1 | ⏳ Starting now |
| Lap 2 | Not yet planned (SDLC determines from evidence) |

---

## 5. Cooldown 1 Plan (per SDLC §7)

Cooldown focuses on four things (per the locked plan):

### 5.1 Worklog Reconciliation
- Bring the worklog up to `b111ce3` state
- Reconcile Task 59 onward against the 10 merged PRs
- Record actual throughput data for SDLC §5 recalibration

### 5.2 ADR Discrepancy Resolution
- Work through the 7 registered discrepancies from `ADR-DISCREPANCY-REGISTER.md`
- Resolve or document each one (don't silently change implementation)
- Discrepancies:
  1. ADR-013 claims PostgreSQL driver "shipped but disabled" — no driver file exists
  2. README claims "19 ADRs" — actual 20
  3. README claims "105 blueprints" — actual 102 canonical
  4. README MUWV status self-contradictory
  5. Hub/config docblocks say "When CORE-19 lands" — CORE-19 partially implemented
  6. SPEC §1 PlantUML implies Hub→DBAL is current — it's target-state
  7. Architecture-lint disclaims coverage gaps

### 5.3 Lint/Fitness-Function Expansion
- Expand only the agreed architectural verification scope
- Don't introduce unrelated architecture changes during cooldown
- Candidates (from SDLC §6):
  - Pulse 6-tuple consistency check
  - Naming drift check (folder vs INDEX.md)
  - Blueprint-fidelity structural diff (class/interface names present, method signatures match)

### 5.4 SDLC Recalibration
- Record Lap 1's measured 2.5 build-units/day
- Explicitly mark deepening throughput as UNKNOWN
- Don't extrapolate build rate into depth-3/4/5 work
- Use dependency graph + recalibrated data to select Lap 2

---

## 6. Deferred Product Scope

The AI App Architect/Blueprint product is recorded as **DEFERRED PRODUCT SCOPE**. It is not allowed to influence Lap 2 admission.

When eventually admitted, it goes through the same process as every other product:

```text
Product idea
    ↓
SDLC admission (based on capacity, dependency graph, depth floors)
    ↓
Requirements / blueprint
    ↓
SPEC contract (architecture constraints)
    ↓
Spoke application (self-contained app)
    ↓
Hub capabilities as required
    ↓
Core infrastructure as required
    ↓
Fitness functions (executable acceptance)
    ↓
Production verification
```

This keeps DGLab from becoming architecture-led development where increasingly elaborate infrastructure is designed before the SDLC has admitted the corresponding product capability.

---

## 7. Architecture Layer Placement (current state)

```
OUTER RIM (app/)
  ApplicationFactory.php          ← wires Spokes to Hub capabilities + Kernel
  public/index.php                 ← thin entry point
  Controller/HelloController.php   ← Milestone 0 walking skeleton
  Controller/HealthController.php  ← M4 three-tier health

SPOKES (self-contained apps — application entry points)
  spoke/internal/showcase/         ← Showcase: Product domain (depth 2)
  spoke/internal/codex/            ← Codex: knowledge base stub (depth 1)
  spoke/external/canvas/           ← Canvas: content delivery (depth 2)

HUB (shared platform capabilities — NOT application entry points)
  hub/identity/                    ← Identity: User + JWT + roles (depth 2)
  hub/config/                      ← Config: feature flags + overrides (depth 2)

BRIDGE
  bridge/vanguard/                 ← Vanguard: WAF + contracts (depth 2)

CORE (infrastructure)
  core/filesystem/                 ← Filesystem: safe blob storage (depth 2)
  core/crypto/                     ← Crypto: AES-256-GCM + Argon2id (depth 2)
  core/dbal/                       ← DBAL: Connection + QueryBuilder (depth 2)
  core/kernel/                     ← Kernel: lifecycle + RequestContext (depth 3)
  core/container/                  ← Container: pulse + WeakMap (depth 3)
  core/http-message/               ← HTTP: PSR-7/17 (depth 3)
  core/middleware/                 ← Middleware: frozen pipeline (depth 3)
  core/router/                     ← Router: frozen + compiler (depth 3)
  core/config/                     ← Config: immutable + frozen (depth 3)
  core/error-handler/              ← ErrorHandler: worker-safe (depth 4)
  core/event-dispatcher/           ← EventDispatcher: PSR-14 + stamps (depth 2)
  core/logger/                     ← Logger: PSR-3 (depth 2)
```

---

## 8. Fitness Functions Live

| Fitness function | SPEC section | Status | Enforcement |
|---|---|---|---|
| Ring-boundary import check | §40 | ✅ Live | 177 files / 285 imports / 0 violations |
| Export-allow-list | §41 | ✅ Live | Identity (6), Filesystem (7), Showcase (10) symbols |
| Service-locator prohibition | §3, §41 | ✅ Live | Container-specific patterns |
| Architecture regression tests | §41 | ✅ Live | 19 tests, all passing |
| Worker contamination tests | §43 | ✅ Live | 5 Fiber isolation tests |
| Kernel lifecycle tests | M05 | ✅ Live | 16 state-machine tests |
| Freeze tests | M05 | ✅ Live | Router + Middleware + Config |
| Generated architecture status | §50 | ✅ Live | ARCHITECTURE_BASELINE.md |

---

*End of ARCHITECTURE-SDLC-FUSION.md.*
