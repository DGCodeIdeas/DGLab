# Generated Architecture Evidence Snapshot

**Authority:** Repository implementation state at a point in time.
**Not authoritative for:** Architectural intent, SDLC admission policy, capability requirements.
**Generated:** 2026-10-01T04:14:19Z
**Commit:** `84d68da06a85804e0c8da6f005411ad0f62bd924`
**Short SHA:** `84d68da`
**Branch:** `main`
**Commit date:** 2026-10-01T05:13:38+01:00
**Reproducible by:** `python3 scripts/generate-architecture-baseline-v2.py` at the commit above.

> **This is an evidence snapshot, not an architectural authority.**
> Baseline = what the repository contains. ADR/SPEC = what the architecture says should exist. SDLC = admission/process authority.

---

## 1. Runtime Constraints

| Source | PHP Constraint |
|---|---|
| Root `composer.json` | `^8.4` |
| `sovereign-stack/core-config` | `^8.4` |
| `sovereign-stack/core-container` | `^8.4` |
| `sovereign-stack/core-crypto` | `^8.4` |
| `sovereign-stack/core-dbal` | `^8.4` |
| `sovereign-stack/core-error-handler` | `^8.4` |
| `sovereign-stack/core-event-dispatcher` | `^8.4` |
| `sovereign-stack/core-filesystem` | `^8.4` |
| `sovereign-stack/core-http-message` | `^8.4` |
| `sovereign-stack/core-kernel` | `^8.4` |
| `sovereign-stack/core-logger` | `^8.4` |
| `sovereign-stack/core-middleware` | `^8.4` |
| `sovereign-stack/core-router` | `^8.4` |
| `sovereign-stack/hub-config` | `^8.4` |
| `sovereign-stack/hub-identity` | `^8.4` |
| `sovereign-stack/spoke-canvas` | `^8.4` |
| `sovereign-stack/spoke-codex` | `^8.4` |
| `sovereign-stack/spoke-lms` | `^8.4` |
| `sovereign-stack/spoke-showcase` | `^8.4` |
| `sovereign-stack/bridge-vanguard` | `^8.4` |
| `sovereign-stack/orchestrator` | `^8.4` |

## 2. Core Implementation Inventory

**Total Core implementations:** 13
(12 under `packages/core/` + 1 under `orchestrator/`)

| Package | Path | Src Files | Test Files | Composer Deps (sovereign) |
|---|---|---|---|---|
| `sovereign-stack/core-config` | `/home/z/my-project/packages/core/config` | 10 | 5 | — |
| `sovereign-stack/core-container` | `/home/z/my-project/packages/core/container` | 7 | 6 | — |
| `sovereign-stack/core-crypto` | `/home/z/my-project/packages/core/crypto` | 8 | 4 | — |
| `sovereign-stack/core-dbal` | `/home/z/my-project/packages/core/dbal` | 11 | 4 | — |
| `sovereign-stack/core-error-handler` | `/home/z/my-project/packages/core/error-handler` | 5 | 2 | sovereign-stack/core-logger:* |
| `sovereign-stack/core-event-dispatcher` | `/home/z/my-project/packages/core/event-dispatcher` | 7 | 3 | — |
| `sovereign-stack/core-filesystem` | `/home/z/my-project/packages/core/filesystem` | 10 | 2 | — |
| `sovereign-stack/core-http-message` | `/home/z/my-project/packages/core/http-message` | 14 | 11 | — |
| `sovereign-stack/core-kernel` | `/home/z/my-project/packages/core/kernel` | 15 | 4 | sovereign-stack/core-container:*, sovereign-stack/core-event-dispatcher:*, sovereign-stack/core-http-message:*, sovereign-stack/core-middleware:*, sovereign-stack/core-router:*, sovereign-stack/core-config:*, sovereign-stack/core-logger:*, sovereign-stack/core-error-handler:* |
| `sovereign-stack/core-logger` | `/home/z/my-project/packages/core/logger` | 9 | 8 | sovereign-stack/core-config:* |
| `sovereign-stack/core-middleware` | `/home/z/my-project/packages/core/middleware` | 8 | 7 | sovereign-stack/core-http-message:*, sovereign-stack/core-router:* |
| `sovereign-stack/core-router` | `/home/z/my-project/packages/core/router` | 13 | 6 | sovereign-stack/core-http-message:* |
| `sovereign-stack/orchestrator` | `/home/z/my-project/orchestrator` | 7 | 7 | — |

## 3. Hub/Spoke/Bridge Implementation Inventory

| Tier | Implemented Packages | Total Src Files | Total Test Files |
|---|---|---|---|
| Hub | 2 | 28 | 10 |
| Spoke | 4 | 42 | 5 |
| Bridge | 1 | 6 | 4 |

### Hub packages:

| Package | Src Files | Test Files |
|---|---|---|
| `sovereign-stack/hub-config` | 13 | 5 |
| `sovereign-stack/hub-identity` | 15 | 5 |

### Spoke packages:

| Package | Src Files | Test Files |
|---|---|---|
| `sovereign-stack/spoke-canvas` | 5 | 1 |
| `sovereign-stack/spoke-codex` | 3 | 1 |
| `sovereign-stack/spoke-lms` | 20 | 1 |
| `sovereign-stack/spoke-showcase` | 14 | 2 |

### Bridge packages:

| Package | Src Files | Test Files |
|---|---|---|
| `sovereign-stack/bridge-vanguard` | 6 | 4 |

## 4. Aggregate Counts

| Metric | Count |
|---|---|
| Total implemented packages | 20 |
| Total PHP source files | 200 |
| Total PHP test files | 88 |
| Total SQL migrations | 8 |

## 5. ADR Inventory

**Total ADR files:** 21
**Accepted:** 20

| File | Title | Status | Date |
|---|---|---|---|
| `ADR-001-polyrepo-vs-monorepo.md` | ADR-001: Polyrepo with CORE-01 (Loom) Orchestration | Accepted | 2026-08-04 |
| `ADR-002-psr11-container-scope.md` | ADR-002: Build a Custom PSR-11 Container (CORE-02) | Accepted | 2026-08-04 |
| `ADR-003-es256-jwt-signing.md` | ADR-003: ES256 (ECDSA P-256 + SHA-256) for JWT Signing | Accepted | 2026-08-04 |
| `ADR-004-tier-enforcement-dag.md` | ADR-004: Kahn's Algorithm with Tier-Priority Re-Sort for DependencyGraph | Accepted | 2026-08-04 |
| `ADR-005-superphp-vs-blade-twig.md` | ADR-005: Build SuperPHP (Custom Lexer + Parser + Compiler) over Blade / Twig / Plates | Accepted | 2026-08-04 |
| `ADR-006-redis-over-memcached.md` | ADR-006: Redis as the Primary Cache Backend (HUB-02) | Accepted | 2026-08-04 |
| `ADR-007-postgresql-over-mysql.md` | ADR-007: PostgreSQL as the Primary Relational Datastore | Superseded by ADR-013 | 2026-08-04 |
| `ADR-008-argon2id-password-hashing.md` | ADR-008: Argon2id for Password Hashing (HUB-04 Identity) | Accepted | 2026-08-04 |
| `ADR-009-ulid-over-uuid.md` | ADR-009: ULID for All Entity IDs | Accepted | 2026-08-04 |
| `ADR-010-opcache-preload-strategy.md` | ADR-010: OPcache Preload Strategy for Core-Tier Classes | Accepted | 2026-08-04 |
| `ADR-011-hub-31-real-time-analytics.md` | ADR-011: HUB-31 — Real-Time Analytics & Metrics Ledger | **Accepted** (2026-08-13) | 2026-08-05 (Proposed); 2026-08-13 (Accepted) |
| `ADR-012-post-quantum-jwt-agility.md` | ADR-012: Post-Quantum / Algorithm-Agility Roadmap for JWT Signing | Accepted | 2026-08-12 |
| `ADR-013-mysql-primary-datastore.md` | ADR-013: MySQL (InnoDB) as the Primary Relational Datastore | Accepted | 2026-08-05 |
| `ADR-014-ratify-agrd-canonical-sdlc.md` | ADR-014: Ratify SDLC-AGRD v3.4(3) as Canonical Software Development Lifecycle | Accepted | 2026-08-12 |
| `ADR-015-hospitality-vertical-promotion.md` | ADR-015: Promote the Hospitality Vertical from Design to Canonical | **Proposed** — not yet Accepted. Ratification as Accepted is deferred until lap 1 | 2026-08-12 |
| `ADR-016-library-app-boundary-split.md` | ADR-016: Library/Application Boundary — Split `packages/` from `app/` | **Proposed** — not yet Accepted. Ratification as Accepted is deferred until | 2026-08-17 |
| `ADR-017-fiber-based-cooperative-runtime.md` | ADR-017: Ratify Fiber-Based Cooperative Runtime | Accepted | 2026-08-24 |
| `ADR-018-centralized-per-tier-releases.md` | ADR-018: Centralized per-tier release model | Accepted (extended by ADR-019) | 2026-09-08 |
| `ADR-019-pre-muwv-version-scheme.md` | ADR-019: Pre-MUWV version scheme (v0.X.Y.Z) | Accepted — **MUWV FLIPPED (2026-09-18) — see §8 Flip Log | 2026-09-11 |
| `ADR-020-stable-and-bleeding-edge-release-channels.md` | ADR-020: Stable and Bleeding Edge release channels | Accepted | 2026-09-17 |
| `ADR-021-tier-stratified-build-order.md` | ADR-021: Tier-Stratified Build Order with Typed-Edge DAGs | Accepted | 2026-09-30 |

## 6. Blueprint Inventory (declared, not implemented)

**Total blueprint files:** 102

| Tier | Blueprint Count | Files |
|---|---|---|
| Core | 20 | CORE-01.md, CORE-02.md, CORE-03.md, CORE-04.md, CORE-05.md... |
| Hub | 31 | HUB-01.md, HUB-02.md, HUB-03.md, HUB-04.md, HUB-05.md... |
| Spoke/Internal | 27 | ISPOKE-01.md, ISPOKE-02.md, ISPOKE-03.md, ISPOKE-04.md, ISPOKE-05.md... |
| Spoke/External | 18 | ESPOKE-01.md, ESPOKE-02.md, ESPOKE-03.md, ESPOKE-04.md, ESPOKE-05.md... |
| Spoke/Bridge | 1 | BRIDGE-01.md |
| Deploy | 5 | DEPLOY-00.md, DEPLOY-01.md, DEPLOY-02.md, DEPLOY-03.md, DEPLOY-04.md |

### Core DAG/Build-Order files (derived, not blueprints):

- `Architecture/Core/CORE-DEPENDENCY-DAG.md`
- `Architecture/Core/CORE-CAPABILITY-DAG.md`
- `Architecture/Core/CORE-BUILD-ORDER.md`

## 7. Implemented vs Declared

| Status | Core | Hub | Spoke | Bridge | Total |
|---|---|---|---|---|---|
| Implemented | 13 | 2 | 4 | 1 | 20 |
| Declared (blueprints) | 20 | 31 | 45 | 1 | 102 |
| Declared but not implemented | 7 | 29 | 41 | 0 | 82 |

## 8. Known Discrepancies (evidence-based)

### INDEX.md discrepancies:

- INDEX.md freshness stamp says 2026-08-12 but §9 changelog records edits through 2026-09-24
- INDEX.md has contradictory CORE-02 status: 'stub only (.gitkeep)' (§1) vs 'Implemented + tested, v1.0.0' (§2.1)
- INDEX.md §5.3 still uses 'parallelizable' labels despite ADR-014 retiring them
- INDEX.md §5.3 Step 8 says '30 blueprints' but should be 31 (HUB-31 accepted 2026-08-13)

### README.md discrepancies:

- README.md mentions PHP 8.3 but root composer.json requires PHP ^8.4
- README.md says '8 Core-tier packages' but actual implementation has 13 (12 under packages/core/ + 1 under orchestrator/)

## 9. Authority Statement

```
Baseline = repository implementation evidence at a point in time.
ADR/SPEC = architectural intent.
SDLC = admission/process authority.

This snapshot is NOT authoritative for:
  - Architectural intent (what should exist)
  - SDLC admission policy (what to build next)
  - Capability requirements (what's needed for delivery)
  - Dependency direction (use the DAGs for this)

This snapshot IS authoritative for:
  - What the repository actually contains
  - Package/file/test counts
  - PHP version constraints
  - ADR inventory and status
  - Blueprint file inventory
```
