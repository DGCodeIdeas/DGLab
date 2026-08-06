# OPEN-DECISIONS.md

**Purpose.** This file records every fork the consolidation pass encountered but could **not** close
without an owner's decision. Per governance Rule 9, open questions are recorded here, never silently
resolved. When a decision is made, move the entry to *Resolved* and cite the deciding artefact.

> Entries are intentionally concise. Each lists: the fork, why it is open, the options, the owner, and
> the decision route.

---

## Open

### OD-01 — HUB-31 (Real-Time Analytics & Metrics Ledger): accept as a full Hub tier?
- **Fork.** Seven spoke blueprints referenced an analytics/ledger capability. Three of them
  (`ISPOKE-05/10/13`) were pointing at a phantom `HUB-28` analytics engine; they are currently corrected
  to `HUB-31 (pending)`. `ISPOKE-15` lists it as a Hard Dependency.
- **Why open.** No Hub exists yet; the topology (in-Hub aggregation vs. hub-local counters shipped to a
  deploy-time collector) is undecided.
- **Options.**
  1. Accept `ADRs/ADR-011-hub-31-real-time-analytics.md` as-is → Hub count becomes 31, total 97.
  2. Fold the metrics requirement into `HUB-23` (Reporter) or `HUB-06` (Auditor) and drop HUB-31.
  3. Reject and require spokes to expose Prometheus-style `/metrics` only, aggregated by `DEPLOY-03`.
- **Owner.** Architecture lead.
- **Decision route.** Accept/Reject `ADR-011`; if rejected, edit the three `HUB-31 (pending)` references.

### OD-02 — Post-quantum / algorithm-agility for JWT signing
- **Fork.** `THREAT_MODEL.md` §10 recommends "ADR-011" for a post-quantum / algorithm-agility JWT
  roadmap. That ADR number is already taken (HUB-31). The recommendation is reallocated to **ADR-012**
  (to be authored).
- **Why open.** `ADR-003` (ES256) is Accepted and implemented-dependent on `HUB-04`; a migration path to
  a hybrid / post-quantum scheme is not yet specified.
- **Options.** (a) Author ADR-012 with a key-rotation + `alg` agility plan now; (b) defer to Phase 2.
- **Owner.** Security lead.
- **Decision route.** Author `ADRs/ADR-012-*.md`; update `THREAT_MODEL.md` §10 reference.

### OD-03 — "Soft" component-name collisions inside a single document
- **Fork.** Five spoke blueprints reused a real ID for the wrong role (e.g. `HUB-12` used as an event
  bus). These are resolved by cross-reference redirection (Finding 15, patterns C–H), but the *root*
  cause — authors editing spoke blueprints without a live `INDEX.md` in front of them — is a process
  gap, not a one-off.
- **Why open.** The fix is procedural; no artefact change closes it.
- **Options.** (a) Mandate `INDEX.md` open during blueprint edits + lint in CI (done); (b) add a
  pre-commit hook that runs `Verification/lint/run.php`.
- **Owner.** Architecture lead.
- **Decision route.** Confirm CI enforcement is sufficient; consider a pre-commit hook.

### OD-04 — Exemplar count
- **Fork.** `ISPOKE-01` (Command Center) and `ESPOKE-01` (Sovereign Canvas) are both labelled the
  canonical exemplar. Two documents carrying the title "exemplar" is internally inconsistent.
- **Why open.** Cosmetic, but the word *exemplar* should be unique or qualified.
- **Options.** (a) `ISPOKE-01` = "internal exemplar", `ESPOKE-01` = "external exemplar"; (b) drop the
  label from one.
- **Owner.** Docs lead.
- **Decision route.** Edit the header of the non-canonical file.

### OD-05 — `ISPOKE-*` folder names vs. `INDEX.md` component names
- **Fork.** Internal Spoke *filenames* use `ISPOKE-01..25`; the human-readable component names live in
  `INDEX.md` §2.3 and `GLOSSARY.md` §1.3. Some chat-era names (`Real-Time Analytics`) are not the
  §2.3 name.
- **Why open.** Filename↔name drift is low-risk but should be reconciled before Phase 2 re-authoring.
- **Options.** (a) Keep ID-only filenames, rely on `INDEX.md`; (b) rename files to include the
  component slug.
- **Owner.** Docs lead.
- **Decision route.** Decide alongside OD-04.

### OD-06 — `ADR-010` opcache scope vs. CORE-02 blocker interaction
- **Fork.** `ADR-010` baselines a ~5 ms opcache warm-boot over `Core/Hub` only; `CORE-02` (DI Container)
  is the build blocker. The warm-boot benchmark currently has no reference implementation to measure
  against.
- **Why open.** Performance target (`STRUCTURE-09`) is provisional until `CORE-02` lands and a harness
  exists (governance Rule 2).
- **Options.** (a) Keep target provisional and gated behind `CORE-02`; (b) re-baseline after `CORE-02`
  ships.
- **Owner.** Performance lead.
- **Decision route.** Verify `STRUCTURE-09` is flagged provisional; revisit post-CORE-02.

---

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
  `HUB-27` "Sentinel" collision. `INDEX.md` §4 now reports 96 documented / 0 placeholder and the CI lint
  is green. This closes the "below the fidelity bar" gap (OD-05's concern about placeholder content is
  resolved); the filename↔name mapping question in OD-05 remains a cosmetic open item.
