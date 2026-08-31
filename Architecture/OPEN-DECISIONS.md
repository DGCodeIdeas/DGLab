# OPEN-DECISIONS.md

**Purpose.** This file records every fork the consolidation pass encountered but could **not** close
without an owner's decision. Per governance Rule 9, open questions are recorded here, never silently
resolved. When a decision is made, move the entry to *Resolved* and cite the deciding artefact.

> Entries are intentionally concise. Each lists: the fork, why it is open, the options, the owner, and
> the decision route.

---

## Open

### OD-08 — Async I/O library choice (ReactPHP vs Amp vs Swoole)
- **Fork:** The Fiber-based runtime (ADR-017) requires an event loop / async I/O library as its "hardware abstraction layer." Three candidates.
- **Option A — ReactPHP:** Mature, largest ecosystem, PSR-7/15/17 native. Blocking-implicit model (promise chains). Largest community.
- **Option B — Amp:** Fiber-native design, cleaner API, smaller ecosystem. Better alignment with PHP 8.1+ Fiber semantics.
- **Option C — Swoole:** Runtime replacement (not a library), highest performance, but locks DGLab to Swoole's runtime model. Incompatible with FrankenPHP.
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Deferred until ADR-017 interfaces are proven in Phase 0. `DGLAB-AS-OS-RUNTIME.md` defines a library-agnostic `EventLoopInterface` as the abstraction boundary.

## Resolved

### OD-01 — HUB-31 (Real-Time Analytics & Metrics Ledger): accepted as full Hub tier
- **Decision:** Accept `ADR-011` as-is. HUB-31 promoted from Proposed to accepted.
- **Action:** `INDEX.md` updated — HUB-31 added to Hub tier table; count updated to 97 blueprints.
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-011-hub-31-real-time-analytics.md` (already existed, now accepted).

### OD-02 — Post-quantum / algorithm-agility for JWT signing
- **Decision:** Author `ADR-012` with a three-phase key-rotation + `alg` agility plan (Phase 1 = registry infrastructure during Cooldown 1; Phase 2 = hybrid experiment when PHP supports ML-DSA; Phase 3 = full PQ migration in project Phase 2).
- **Action:** `ADR-012-post-quantum-jwt-agility.md` created; `THREAT_MODEL.md` §10 reference updated.
- **Decided by:** Security lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-012-post-quantum-jwt-agility.md`.

### OD-03 — "Soft" component-name collisions inside a single document
- **Decision:** CI enforcement (`run.php` check 1 + `.github/workflows/architecture-lint.yml`) is sufficient. No pre-commit hook needed for solo operation.
- **Action:** Mark resolved; no file changes required.
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `SDLC-AGRD.md` §6, `REPO-STATE-AUDIT.md` §6.

### OD-04 — Exemplar count
- **Decision:** Split the label. `ISPOKE-01` = "Internal Exemplar", `ESPOKE-01` = "External Exemplar".
- **Action:** Both file headers updated with qualified exemplar labels.
- **Decided by:** Docs lead (DGCI), 2026-08-12.
- **Citing artefact:** `ISPOKE-01.md`, `ESPOKE-01.md`.

### OD-05 — `ISPOKE-*` folder names vs. `INDEX.md` component names + Sovereign Forge collision
- **Decision:** Keep ID-only filenames (Governance Rule 1 — `INDEX.md` is naming authority). Resolve the 4-way "Sovereign Forge" collision by assigning distinct names:
  - `CORE-20` → "Sovereign Forge" (kept as canonical)
  - `ISPOKE-02` → "A1 Atlas"
  - `ISPOKE-11` → "B1 Penumbra"
  - `ESPOKE-12` → "C1 Pulsar"
- **Action:** `INDEX.md` §2.3 updated; 3 blueprint files renamed in content (filenames unchanged per decision).
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `INDEX.md` §2.3, `ISPOKE-02.md`, `ISPOKE-11.md`, `ESPOKE-12.md`.


### OD-07 — Runtime model: Fiber-based cooperative scheduler vs. multi-process worker pool
- **Decision:** Accept Option A (Fibers). Ratified as `ADR-017`.
- **Action:** `ADR-017-fiber-based-cooperative-runtime.md` created and accepted. `DGLAB-AS-OS-RUNTIME.md` updated to reflect Accepted status. `CORE-02.md` updated with `pulse()` scope and `WeakMap` cache. `DEPLOY-01.md` FrankenPHP runtime confirmed canonical; PHP-FPM excluded.
- **Decided by:** Architecture lead (DGCI), 2026-08-24.
- **Citing artefact:** `ADR-017-fiber-based-cooperative-runtime.md`, `DGLAB-AS-OS-RUNTIME.md`.

**Confirmed consequences:**
1. FrankenPHP is the canonical PHP runtime. PHP-FPM is incompatible (one process per request); RoadRunner is theoretically compatible but untested.
2. `singleton()` semantics are worker-scoped (shared across all Pulses in a worker). `pulse()` (per-Fiber scope) added to `ContainerInterface` to replace the old "per-request" mental model.
3. Singleton audit remains a gated sub-decision — all existing `singleton()` bindings must be classified as worker-scoped vs. pulse-scoped.

### OD-06 — `ADR-010` opcache scope vs. `CORE-02` blocker interaction
- **Decision:** Keep target provisional and gated behind `CORE-02`. `STRUCTURE-09` already flags the ~5 ms target as provisional.
- **Action:** Mark resolved; no file changes required (provisional flag already present).
- **Decided by:** Performance lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-010-opcache-preload-strategy.md`, `STRUCTURE-09`.

---

## Previously Resolved (during consolidation)

## Resolved (during consolidation)

- **Two-architecture ambiguity (Vision A vs. Vision B).** Resolved by `INDEX.md` §1 declaring
  `Architecture/` the sole source of truth and archiving `docs/architecture/origin/` (Vision A) and the
  `Legacy/` code.
- **`HUB-28` = API Versioning, not analytics.** Resolved in `INDEX.md` §2.2; the five spokes were
  corrected (Finding 15, Pattern B).
- **`HUB-09` "Sovereign Pulse" → "Sovereign Signal".** Resolved; "Sovereign Pulse" is reserved for
  `HUB-15`, and *Pulse* is the reserved architectural noun (`STRUCTURE-01-Wheel.md`).
- **`DEPLOY-00` rename.** The docs-only `DEPLOY-01` was renamed `DEPLOY-00` and its document root
  corrected to `Architecture/`; the application deployment is now `DEPLOY-01`.
 - **MySQL → PostgreSQL → MySQL (decision shift 2026-08-05).** The first consolidation adopted PostgreSQL
   16 as primary per `ADR-013`; a later decision shift **reversed** it — `ADR-013` now makes **MySQL 8
   (InnoDB)** the primary datastore, with the PostgreSQL driver **relegated behind CORE-19 and disabled by
   default** (re-enabled only at the next decision scale). `STRUCTURE-05/07/08/09`, `CORE-19`, `DEPLOY-02`,
   and the spoke/datastore blueprints are aligned to MySQL; the `docs/blueprints/` tree (MySQL) is archived
   and never merged. The reversal is recorded in `INCONSISTENCIES.md` #1.
- **ADR number collision.** `THREAT_MODEL.md` §10's "ADR-011" → ADR-012 (pending); `Migration/04`'s
  "ADR-011" SuperPHP reference → ADR-005 (already Accepted).
- **Placeholder/stub blueprints promoted to full fidelity.** On 2026-08-05, `ISPOKE-16`–`25` (10
  Internal Spokes) and `DEPLOY-02`–`04` (3 Deploy) were rewritten from placeholders/stubs into
  implementation-ready blueprints (class maps, PHP interface contracts, MySQL/InnoDB DDL, integration
  strategy, security properties, CI criteria). `ISPOKE-21` was renamed **Sovereign Scan** to avoid the
  `HUB-27` "Sentinel" collision. `INDEX.md` §4 reported 96 documented / 0 placeholder and the CI lint
  was green at that point. This closes the "below the fidelity bar" gap (OD-05's concern about
  placeholder content is resolved); the filename↔name mapping question in OD-05 remains a cosmetic
  open item.
- **Hospitality vertical promoted to canonical.** On 2026-08-12, per `ADR-015` (Proposed; ratification
  deferred until V1 ships against Bet 3 Hub Full), 5 hospitality blueprints (`ISPOKE-26` Sovereign
  Reservations, `ISPOKE-27` Sovereign Front Desk, `ESPOKE-16` Sovereign Booking Portal, `ESPOKE-17`
  Sovereign Concierge, `ESPOKE-18` Sovereign Mobile Check-in) were promoted from design-only (tracked
  in `HOSPITALITY-VERTICAL.md`) to canonical. `INDEX.md` §4 now reports **101 documented / 0
  placeholder**. `Verification/lint/run.php` `buildValidIds()` + `checkStructure()` extended to cover
  the new ranges; lint check 1 passes cleanly on every doc that references the hospitality IDs. This
  closes D-02 (96 vs 101 count) and D-15 (hospitality references fail lint by design); see
  `DISCREPANCY-REGISTER.md` for the closure record.
