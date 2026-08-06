# INCONSISTENCIES — Scan Report

**Purpose.** Human-readable mirror of `Verification/lint/run.php`. This report records every
contradiction found in the DGLab architecture corpus, how it was reconciled, and where the authoritative
statement now lives. It is generated from the consolidation pass of 2026-08-05 and is kept in sync with
`Architecture/INDEX.md`.

**Scope scanned:** the entire `Architecture/` tree + the five archived provenance trees
(`Arc/`, `Analysis_Critiques_Rewrites/`, `docs/blueprints/`, `docs/architecture/origin/`,
`docs/evaluation/`, `Legacy/`).

**Verification:** every reconciliation below is enforced mechanically by the CI lint
(`Verification/lint/run.php`, wiring in `Verification/lint/architecture-lint.yml`). Run it locally:

```bash
php Architecture/Verification/lint/run.php
```

---

## Summary

| # | Class | Contradiction | Status |
|---|---|---|---|
| 1 | Datastore | MySQL/InnoDB/JSON/AUTO_INCREMENT vs PostgreSQL 16 / JSONB / ULID | Resolved (MySQL) → **reversed 2026-08-05** by revised `ADR-013` (MySQL/InnoDB primary; PostgreSQL via disabled driver) |
| 2 | Core map | Evaluation scores a Core tier that no longer exists | Resolved (archived) |
| 3 | Cross-ref | `BRIDGE-01` cites `CORE-09` for crypto; real crypto is `CORE-16` | Resolved |
| 4 | Quality | "Approved" thinner than "disapproved" | Documented; Phase 2 |
| 5 | Duplication | Byte-near-identical `Mobile_Optimized/` copies | Archived + Rule 6 |
| 6 | Completion | Sibling docs contradict completion claims | Resolved (single index) |
| 7 | Phasing | "81-phase" claim vs 99 phases | Resolved (11-step build) |
| 8 | Blocker | `CORE-02` (DI Container) is an empty stub | Flagged critical |
| 9 | Deploy | Only "deploy" blueprint deploys docs, not the app | Resolved (`DEPLOY-01`) |
| 10 | Perf targets | Bare ms claims, no method | Re-baselined (`ADR-010`) |
| 11 | Solutions | `SOLUTIONS_TO_WEAKNESSES.md` never merged | Merged into blueprints |
| 12 | Rejections | 72 rejections share one boilerplate | Rule 4 |
| 13 | Inventory | Internal Spokes under-counted (15 vs 25) | Resolved (`INDEX` §4) |
| 14 | DAG | Mutual-downward cycles in dependency graph | Resolved |
| 15 | Cross-ref (8 patterns) | Spoke blueprints reused real IDs for wrong roles | Resolved |
| 16 | Namespace | `Sovereign\` vs `SovereignStack\` | Resolved |
| 17 | Compose | Root `docker-compose.yml` empty | Replaced |
| 18 | Dockerfile | Root Dockerfile serves legacy docs | Replaced |
| 19 | ADRs | Decisions taken without ADR | Rule 8 |
| 20 | Deps | `thephpleague/event` cited, not a dependency | Resolved |

---

## Detailed findings

### #1 — Datastore engine conflict
- **Conflict (original).** `CrossCutting/STRUCTURE-05/07/08/09` (predecessor) specified MySQL 8 / InnoDB / JSON /
  `AUTO_INCREMENT`; the first pass of this consolidation adopted `ADR-013` mandating PostgreSQL 16 / JSONB / ULID, and
  `STRUCTURE-05` DDL was rewritten to JSONB + ULID + partial GIN indexes + RLS; `docs/blueprints/` still uses MySQL.
- **Decision shift (2026-08-05).** `ADR-013` was **superseded** by a new `ADR-013` (MySQL/InnoDB primary; PostgreSQL
  relegated behind the CORE-19 driver, disabled by default). MySQL 8 (InnoDB) is now the canonical primary datastore;
  PostgreSQL remains available via the DBAL `DriverInterface` and is re-enabled only at the next decision scale.
- **Resolution.** MySQL 8 (InnoDB) is canonical per the revised `ADR-013`. `STRUCTURE-05` DDL uses `ENGINE=InnoDB`, the
  `JSON` column type with generated-column + functional indexes, `CHAR(26)` ULID keys, and tenant scoping enforced by the
  DBAL `TenantScope` (CORE-19 + HUB-21) rather than engine RLS. `STRUCTURE-07/08/09`, `CORE-19`, `DEPLOY-02`, and the
  spoke/datastore blueprints are aligned to MySQL. The `docs/blueprints/` MySQL tree is archived and must never be merged
  (governance Rule 1).
- **Authority.** `ADR-013` (revised), `Architecture/CrossCutting/STRUCTURE-05.md`.

### #2 — Evaluation layer describes a dead Core tier
- **Conflict.** `docs/evaluation/*` scores `CORE-01..20` against names that match no current file
  (e.g. "CORE-04 = Encryption Primitives" while the file is "PSR-7 HTTP Message").
- **Resolution.** The evaluation tree is archived with an `ARCHIVED.md` banner and a "do not use for
  planning" warning (governance Rule 3). Canonical Core map lives in `INDEX.md` §2.1.
- **Authority.** `Architecture/INDEX.md` §2.1.

### #3 — `BRIDGE-01` mislabels its crypto dependency
- **Conflict.** `BRIDGE-01` listed `CORE-09: Cryptography & Hashing (Payload Verification)`. `CORE-09`
  is the PSR-3 Logging Service; cryptography is `CORE-16` (Binary Encryption Envelope). It also listed
  `CORE-06` (router) for gateway routing — that is `HUB-08` — and `CORE-01` as an "enforcement"
  component, which it is not.
- **Resolution.** `BRIDGE-01` rewritten: `CORE-16` for payload verification, `HUB-08` for routing,
  `CORE-09` only for diagnostic logging, `CORE-01` removed. `CORE-09.md` now opens by making its
  identity unambiguous and quoting the original mistake as a correction.
- **Authority.** `Architecture/Spoke/Bridge/BRIDGE-01.md`, `Architecture/Core/CORE-09.md`.

### #4 — "Approved" is thinner than "disapproved"
- **Conflict.** Disapproved blueprints (~7–13 KB, real interfaces, sequence diagrams) were more
  substantive than approved ones (~1–2 KB prose).
- **Resolution.** Not auto-fixed (would require re-authoring 17 files). Recorded as a Phase 2 task;
  `AUTHORING_GUIDE.md` sets the fidelity bar every blueprint must meet before it is "approved".
- **Authority.** `Architecture/AUTHORING_GUIDE.md`.

### #5 — Byte-near-identical duplicates
- **Conflict.** `Mobile_Optimized/SOVEREIGN_STACK_MASTER.md` ≈ `Sovereign_Stack_Blueprint/…` (within 5%).
- **Resolution.** Both trees archived. Governance Rule 6 now forbids duplicate directories without a CI
  size-delta check.
- **Authority.** `Architecture/INDEX.md` §7 Rule 6.

### #6 — Contradictory completion claims
- **Conflict.** Sibling documents claimed both "complete" and "TBD" for the same components.
- **Resolution.** `INDEX.md` §4 is the single inventory with explicit documented / placeholder / stub
  counts. Per-component status is stated once, in the blueprint's own header.
- **Authority.** `Architecture/INDEX.md` §4.

### #7 — "81-phase" vs 99 phases
- **Conflict.** A phase count was asserted that matched neither the blueprint set nor the actual plan.
- **Resolution.** Replaced the phase vocabulary with an 11-step build sequence derived from the tier DAG
  (`INDEX.md` §5.3).
- **Authority.** `Architecture/INDEX.md` §5.3, `Architecture/Migration/04_MIGRATION_PLAN.md`.

### #8 — `CORE-02` (DI Container) is an empty stub
- **Conflict.** `packages/core/container/src/` contains only `.gitkeep`; the Hub tier lists `CORE-02` as
  a dependency, so the whole Hub build is blocked, yet the evaluation called Core "implemented".
- **Resolution.** **Flagged as the top build-blocking dependency.** `INDEX.md` §2.1 and §5.3 make
  `CORE-02` the literal first build step; `CORE-18` (Kernel) is re-sequenced to depend on it. This is the
  real critical path, not `CORE-01` (which is already done).
- **Owner.** Core team.

### #9 — The only "Deploy" blueprint deploys docs, not the app
- **Conflict.** Predecessor `DEPLOY-01` + `render.yaml` hosted the Markdown documentation on a free tier.
- **Resolution.** Renamed to `DEPLOY-00` (Documentation Site) with document root corrected to
  `Architecture/`; the application deployment is now `DEPLOY-01` (Core & Hub Service Deployment).
  `DEPLOY-02/03/04` added as stubs (datastores, edge, promotion).
- **Authority.** `Architecture/Deploy/DEPLOY-00.md`, `DEPLOY-01.md`.

### #10 — Performance targets asserted, never grounded
- **Conflict.** Blueprints claimed `<50ms` opcache warm-boot, `p99 < 200ms`, etc., with no harness,
  baseline, or load model.
- **Resolution.** `ADR-010` re-baselines opcache warm-boot to a ~5 ms target, scoped to Core + Hub
  preload only (not the full spoke tree). `STRUCTURE-09` requires every target to name its harness,
  baseline, and load model (governance Rule 2); unverifiable targets are written "provisional".
- **Authority.** `Architecture/ADRs/ADR-010-opcache-preload-strategy.md`.

### #11 — `SOLUTIONS_TO_WEAKNESSES.md` never merged
- **Conflict.** 35 KB of fixes sat beside the blueprints instead of in them.
- **Resolution.** The relevant fixes (HUB-02 cache, HUB-11 queue, CORE-16 encryption, etc.) are merged
  into the referenced blueprint files; the standalone doc is archived. Governance Rule 5 now requires a
  solution to land in the artefact.
- **Authority.** `Architecture/INDEX.md` §7 Rule 5.

### #12 — 72 rejections share one boilerplate reason
- **Conflict.** `docs/blueprints/disapproved/…` used a single file "Varying from the original blueprints"
  for 72 rejections.
- **Resolution.** Governance Rule 4 requires each `disapproved/` entry to state the specific rejection
  and reason. The directory is archived.
- **Authority.** `Architecture/INDEX.md` §7 Rule 4.

### #13 — Internal Spoke tier under-counted
- **Conflict.** Evaluation said 15 internal spokes; `docs/internal-spokes/placeholder-blueprints.md`
  defines `ISPOKE-16..25` as placeholders.
- **Resolution.** `INDEX.md` §4 counts 25 internal spokes (15 documented, 10 placeholder files).
  `ISPOKE-16..25` generated as explicit placeholder blueprints. Phase 3 timeline re-estimated (Finding
  15).
- **Authority.** `Architecture/INDEX.md` §4, `Architecture/Spoke/Internal/ISPOKE-16.md`…`25.md`.

### #14 — Mutual-downward cycles in the dependency DAG
- **Conflict.** `HUB-03` and `HUB-11` each listed the other as *Downward* (a cycle); `DEPLOY-01`'s
  Integration Strategy inverted the Upward/Downward labels.
- **Resolution.** Edge direction is now binding (`INDEX.md` §5.1): *Upward* = consumed IDs, *Downward* =
  consumers. `HUB-03`↔`HUB-11` re-pointed; `DEPLOY-01` labels corrected; `CORE-01` confirmed upstream of
  `DEPLOY-01`. The lint detects mutual-downward pairs.
- **Authority.** `Architecture/INDEX.md` §5.1, `Architecture/Hub/HUB-03.md`, `HUB-11.md`.

### #15 — Eight recurring cross-reference mislabelling patterns
- **Conflict.** Beyond `BRIDGE-01`, the full Spoke tiers reused real IDs for the wrong role — e.g.
  `HUB-28` (actually API Versioning) cited as "Distributed Ledger & Analytics Engine", `HUB-12`
  (Notify) used as an event bus (that is `HUB-09`), `CORE-07` (SuperPHP Lexer) used for the Event
  Dispatcher (`CORE-03`), and references to non-existent Core "phases".
- **Resolution.** All eight patterns corrected against `INDEX.md` §3. The mechanical fix: `CORE-09`↔
  `CORE-16` (14 files), `HUB-28` analytics → `HUB-31(pending)` / `HUB-23` / dropped (6 files), queue →
  `HUB-10`, event bus → `HUB-09`, search/media swap fixed, `CORE-07`→`CORE-03`/`CORE-04`, phantom
  "phases" dropped, wrong Internal Spoke IDs fixed. The CI lint re-derives every reference against
  `INDEX.md` §2 and fails on a mismatch, so this class of bug cannot regress.
- **Authority.** `Architecture/INDEX.md` §3, `Architecture/Verification/lint/run.php`.

### #16 — Namespace prefix conflict
- **Conflict.** Seven predecessor files used `Sovereign\Internal\...`; canonical is `SovereignStack\`.
- **Resolution.** All namespaces normalized to `SovereignStack\*`; `CORE-01`→`SovereignStack\Orchestrator`,
  `CORE-20`→`SovereignStack\Forge`. The bare `Sovereign\` prefix is withdrawn.
- **Authority.** `Architecture/INDEX.md` §2.

### #17 — Root `docker-compose.yml` is empty
- **Conflict.** The root compose file contained no services (Finding 17).
- **Resolution.** Replaced by the dev-environment compose file referenced from `DEPLOY-01.md`.
- **Authority.** `Architecture/Deploy/DEPLOY-01.md`.

### #18 — Root `Dockerfile` serves the legacy docs
- **Conflict.** The root Dockerfile built the stale `docs/` site, not the architecture or application.
- **Resolution.** Superseded by `DEPLOY-00` (docs, pointing at `Architecture/`) and `DEPLOY-01`
  (application image). The root Dockerfile remains only as legacy and is not part of the canonical
  deploy path.
- **Authority.** `Architecture/Deploy/DEPLOY-00.md`, `DEPLOY-01.md`.

### #19 — Decisions taken without an ADR
- **Conflict.** Several non-obvious decisions (SuperPHP over Blade/Twig, Redis over Memcached, PostgreSQL
  over MySQL) had no decision record in the candidate set.
- **Resolution.** All ten are recorded as `ADR-001..010` (Accepted). Governance Rule 8 requires an ADR
  before a non-obvious decision merges. `HUB-31` is filed as `ADR-011` (Proposed) and tracked in
  `OPEN-DECISIONS.md`.
- **Authority.** `Architecture/ADRs/`.

### #20 — `thephpleague/event` cited as a dependency
- **Conflict.** Predecessor `CORE-03` referenced `thephpleague/event`; only `psr/event-dispatcher` is a
  dependency.
- **Resolution.** `CORE-03` corrected: removed the league reference, added the listener-exception
  logging cross-ref to `CORE-09` (not `CORE-08`). The `docs/blueprints/` tree (still citing the league
  package) is archived.
- **Authority.** `Architecture/Core/CORE-03.md`.

---

## Security note

A plaintext GitHub PAT (`ghp_…`) was present in the provenance corpus
(`Analysis_Critiques_Rewrites/`). It is **already redacted** (`ghp_[Redacted]`) in both archived source
files; a repo-wide scan (`grep -rE 'ghp_[A-Za-z0-9]{20,}'`) finds no live token. The token should still
be rotated/revoked as a precaution. See the `ARCHIVED.md` banner in that tree.

---

## Open decisions carried forward

See `Architecture/OPEN-DECISIONS.md`: HUB-31 acceptance (OD-01), post-quantum JWT / ADR-012 (OD-02),
process gap for cross-ref reuse (OD-03), exemplar count (OD-04), `ISPOKE-*` filenames (OD-05), and the
`ADR-010` ↔ `CORE-02` benchmark interaction (OD-06).
