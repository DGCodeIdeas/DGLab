# DGLab — Complete Analysis Record

**Created:** 2026-10-20  
**Session:** Full codebase analysis + research phase  
**Baseline:** `origin/main` at `a18a3e86ae6249ab58a39c07ca375bc0852a69f5` (verified current HEAD)  
**Previous baseline:** `ae410ea09fcd7a4c0c2c77cc980ff7d3e4984ac3` (historical, superseded by git pull)  
**Status:** Research deliverables complete — awaiting owner review. No implementation started. No code/ADR/governance files modified.

---

## Table of Contents

1. [Codebase Structure](#1-codebase-structure)
2. [Architecture: Core/Hub/Spoke/Wheel Monorepo](#2-architecture)
3. [Key Configuration & CI](#3-key-configuration--ci)
4. [Frozen Contracts & ADRs](#4-frozen-contracts--adrs)
5. [LMS Module — Current State](#5-lms-module--current-state)
6. [Research Tracks — Repository State](#6-research-tracks--repository-state)
7. [Git State](#7-git-state)
8. [Three Handoff Documents Analysis](#8-three-handoff-documents-analysis)
9. [Research Agenda Analysis](#9-research-agenda-analysis)
10. [SPX/SuperPHP Naming & Collision Research](#10-spxsuperphp-naming--collision-research)
11. [SPX/SuperPHP Ease-of-Use & Human/AI Comprehension Research](#11-spxsuperphp-ease-of-use--humanaI-comprehension-research)
12. [Vue SFC & Component-Format Research](#12-vue-sfc--component-format-research)
13. [Research Deliverables Inventory](#13-research-deliverables-inventory)
14. [Pending Owner Decisions](#14-pending-owner-decisions)
15. [Capability Matrix (Verified)](#15-capability-matrix-verified)
16. [LMS Build Order (Updated)](#16-lms-build-order-updated)

---

## 1. Codebase Structure

### Top-Level Directories

| Path | Purpose |
|------|---------|
| `Architecture/` | Canonical architecture docs — Core (20 blueprints), Hub (32 blueprints), Spoke (Internal/External), CrossCutting (10 STRUCTURE docs, doctrines, AGRD, ADRs, registers) |
| `packages/` | Monorepo packages — `core/*` (20), `hub/*` (6+), `bridge/*` (1), `spoke/internal/*` (3: codex, lms, showcase), `spoke/external/*` (1: canvas) |
| `app/` | Application composition — `ApplicationFactory.php`, controllers (Hello/Health), Bridge, Resources, Spokes/Internal, tests/Integration |
| `anvil/` | Infrastructure tooling — DNS, provisioning, systemd, docker, migrate.sh, web server configs |
| `orchestrator/` | Loom (CORE-01) — Version bumping, release automation, `bin/loom` CLI |
| `public/` | Web entry point — `index.php` (thin, delegates to `ApplicationFactory`) |
| `tests/` | Root integration tests — `tests/Integration/PublicIndexSmokeTest.php` |
| `config/` | Empty (`.gitkeep` only) |
| `cli/` | CLI entry points (minimal) |
| `scripts/` | CI/architecture tooling (Python/PHP linters, fitness checks, matrix completeness) |
| `.github/workflows/` | 6 CI workflows |
| `.kilo/` | Kilo agent configuration (agents, skills, plans) |
| `archive/` | Historical docs, pre-A3 audit artifacts |
| `var/`, `vendor/` | Runtime artifacts (gitignored) |

---

## 2. Architecture: Core/Hub/Spoke/Wheel Monorepo

**Normative Model:** `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` v0.4

| Ring | Type | Components | Role |
|------|------|------------|------|
| **Core** | Layer | `CORE-01`–`20` (20 packages) | Nucleus — independently versioned, system-only |
| **Hub** | Layer | `HUB-01`–`30` (+ `HUB-31` proposed) | Inner ring — internal services, mandatory traversal |
| **Inner Rim** | Checkpoint | `BRIDGE-01` (Vanguard) | Single stateless gate — transform + forward |
| **Inner Spokes** | Layer | `ISPOKE-01`–`25` (thick) | Staff-only, authenticated + authorized |
| **Outer Spokes** | Layer | `ESPOKE-01`–`15` (thin) | Public-facing, anonymous or authenticated |
| **Outer Rim** | Checkpoint | `HUB-08` (Gateway) | Public ingress enforcement (auth, throttle, CORS) |
| **Frame** | — | `DEPLOY-00`–`04` | Infrastructure (Terraform, K8s, pipelines) |

**Key Principles:**
- **Four layers + two checkpoints** (not six equivalent rings)
- **Layer vs. checkpoint distinction** is load-bearing
- **Connectivity:** Free lateral mesh within layers; free radial flow between adjacent layers *except* across Inner Rim (`BRIDGE-01`)
- **Hub is mandatory layer, not a checkpoint**
- **Pulse taxonomy** (6-tuple): synchronous radial (Type A), asymmetric fan-out (Type B), origin-less/system-initiated (Type C)

**Architectural Principle:** Compose downward, abstract upward, and expose only the capability contract appropriate to the consumer.

---

## 3. Key Configuration & CI

### `composer.json` (Root)
- **PHP requirement:** `^8.4`
- **Type:** `project` (monorepo root)
- **Path repositories:** `packages/*/*` and `packages/*/*/*`
- **Autoload:** PSR-4 for `App\` (app/), all Core/Hub/Bridge/Spoke packages

### CI Workflows (`.github/workflows/`)

| Workflow | Purpose |
|----------|---------|
| `packages-ci.yml` | Main test matrix — PHPStan + PHPUnit per package (20+ entries); includes LMS |
| `architecture-lint.yml` | Documentation/reference structure checks (4 checks) |
| `architecture-fitness.yml` | Pulse consistency, naming drift, blueprint fidelity |
| `architecture-boundary-lint.yml` | Ring-boundary enforcement + service-locator prohibition (13 regression tests) |
| `release.yml` | SemVer release automation via Loom (gated by `LOOM_RELEASE_ENABLED=1`) |
| `secret-scan.yml` / `pr-title-lint.yml` | Security + PR title conventions |

---

## 4. Frozen Contracts & ADRs

### `Architecture/FROZEN-CONTRACTS.md` — 31 frozen interfaces, 2 frozen enums

| Tier | Package | Frozen Interfaces | Frozen Enums |
|------|---------|-------------------|--------------|
| Core | CORE-02 Container | 3 | 0 |
| Core | CORE-03 EventDispatcher | 2 | 0 |
| Core | CORE-04 HttpMessage | 1 | 0 |
| Core | CORE-05 Middleware | 3 | 0 |
| Core | CORE-06 Router | 1 | 0 |
| Core | CORE-08 ErrorHandler | 2 | 0 |
| Core | CORE-09 Logger | 3 | 0 |
| Core | CORE-10 Config | 3 | 0 |
| Core | CORE-18 Kernel | 3 | 1 (KernelState) |
| Hub | HUB-01 Config | 4 | 1 (Environment) |
| Bridge | BRIDGE-01 Vanguard | 2 | 0 |
| Internal Spoke | ISPOKE-09 Codex | 1 | 0 |
| External Spoke | ESPOKE-01 Canvas | 2 | 0 |

### ADRs (21 total, ADR-001–ADR-021)

| ADR | Title | Status |
|-----|-------|--------|
| ADR-001 | Polyrepo vs Monorepo | Accepted |
| ADR-002 | PSR-11 Container Scope | Accepted |
| ADR-003 | ES256 JWT Signing | Accepted |
| ADR-004 | Tier Enforcement DAG | Accepted |
| ADR-005 | SuperPHP vs Blade/Twig | Accepted |
| ADR-006 | Redis over Memcached | Accepted |
| ADR-007 | PostgreSQL over MySQL | Superseded by ADR-013 |
| ADR-008 | Argon2id Password Hashing | Accepted |
| ADR-009 | ULID over UUID | Accepted |
| ADR-010 | Opcache Preload Strategy | Accepted |
| ADR-011 | HUB-31 Real-Time Analytics | Proposed |
| ADR-012 | Post-Quantum JWT Agility | Accepted |
| ADR-013 | MySQL Primary Datastore | Accepted (supersedes ADR-007) |
| ADR-014 | Ratify AGRD Canonical SDLC | Accepted |
| ADR-015 | Hospitality Vertical Promotion | Proposed |
| ADR-016 | Library/App Boundary Split | Accepted |
| ADR-017 | Fiber-Based Cooperative Runtime | Accepted |
| ADR-018 | Centralized Per-Tier Releases | Accepted |
| ADR-019 | Pre-MUWV Version Scheme | Accepted |
| ADR-020 | Stable/Bleeding-Edge Release Channels | Accepted |
| ADR-021 | Tier-Stratified Build Order | Accepted |

**Missing (not yet written):** ADR-022 (auth transport — required for LMS HTTP layer)

---

## 5. LMS Module — Current State (Verified at `a18a3e8`)

### Package: `sovereign-stack/spoke-lms` v0.1.0.0

**Dependencies:** core-container, core-dbal, core-event-dispatcher, core-http-message, core-kernel, core-filesystem, hub-identity

### Structure

```
src/
├── Application/
│   ├── Command/ (4 commands)
│   ├── Service/ (CourseApplicationService, EnrollmentApplicationService)
│   ├── CourseApplicationInterface
│   └── EnrollmentApplicationInterface
├── Domain/
│   ├── Entity/ (Course, Module, Enrollment, Progress)
│   ├── Exception/ (4 exceptions)
│   ├── Repository/ (CourseRepositoryInterface, EnrollmentRepositoryInterface, ModuleRepositoryInterface)
│   └── ValueObject/ (8 value objects)
└── Infrastructure/Persistence/
    ├── MySQLCourseRepository
    ├── MySQLEnrollmentRepository
    └── MySQLModuleRepository
```

### Migrations (5 SQL files)
| File | Table | Key Details |
|------|-------|-------------|
| `001_create_courses_table.sql` | `lms_courses` | id (CHAR 26), title, slug (unique), description, status, timestamps |
| `002_create_modules_table.sql` | `lms_modules` | id, course_id (FK), title, sort_order, created_at |
| `003_create_enrollments_table.sql` | `lms_enrollments` | id, course_id, learner_id, status, enrolled_at, completed_at; unique(course_id, learner_id) |
| `004_create_progress_table.sql` | `lms_progress` | PK(enrollment_id, module_id), status, started_at, completed_at; FKs |
| `005_add_module_content.sql` | `lms_modules` | `ALTER TABLE ... ADD COLUMN content MEDIUMTEXT NULL` |

### Capability Matrix (Verified)

| Component | Implemented | Unit-Tested | MySQL Integration | E2E Demo |
|-----------|-------------|-------------|-------------------|----------|
| LMS Domain/Application | ✅ | ✅ | ❌ | ❌ |
| LMS Course/Module Persistence | ✅ | ✅ (mock) | ❌ | ❌ |
| LMS Enrollment Persistence | ✅ | ✅ | ❌ | ❌ |
| LMS Enrollment Application Service | ✅ | ✅ | ❌ | ❌ |
| LMS Progress Persistence | ❌ | ❌ | ❌ | ❌ |
| LMS Progress Application Service | ❌ | ❌ | ❌ | ❌ |
| LMS HTTP Routes/Controllers | ❌ | ❌ | ❌ | ❌ |
| Auth Middleware Wired | ❌ (exists in Hub) | ❌ | ❌ | ❌ |
| Real MySQL in CI | ❌ | ❌ | ❌ | ❌ |
| Complete Learner/Admin Flow | ❌ | ❌ | ❌ | ❌ |

### Key Gaps
1. **Progress persistence layer missing** — No `ProgressRepositoryInterface`, no `MySQLProgressRepository`, no `ProgressApplicationService`
2. **No LMS HTTP routes** — `ApplicationFactory` only registers `/` + `/health*`
3. **AuthMiddleware not wired** — Exists at `packages/hub/identity/src/Http/AuthMiddleware.php` (JWT-based)
4. **No MySQL in CI** — `packages-ci.yml` has no MySQL service
5. **No migration runner** — No automated migration execution

---

## 6. Research Tracks — Repository State

### `RESEARCH-AGENDA.md` (root, 34KB, created 2026-10-13)

| Track | Title | Status |
|-------|-------|--------|
| 1 | Platform Integrity, Copyright, Ownership, Fair Disputes | Research — Not an Implementation Requirement |
| 2 | Avoiding the YouTube Model | Research — Not an Implementation Requirement |
| 3 | Half Exceptions | Research — Not an Implementation Requirement |
| 4 | Cancel-Anytime Paradox | Research — Not an Implementation Requirement |
| 5 | Connecting Principles | Research — Not an Implementation Requirement |
| SuperPHP | Naming Investigation | Research — Not an Implementation Requirement. No rename approved. |

**Canonical location:** `Architecture/CrossCutting/RESEARCH-AGENDA.md` does NOT exist (authorization required per AGENTS.md §5.7)

### Research Deliverables Status (Before This Session)

| Deliverable | Status |
|-------------|--------|
| Source-backed reports per track | In agenda (hypotheses + deliverables defined) |
| V1–V7 reconciliation matrix | ❌ MISSING — now created |
| Proposed canonical agenda | Draft exists in root |
| Standalone SPX naming/collision finding | Embedded in agenda — now extracted |
| Final research index | ❌ MISSING — now created |

---

## 7. Git State (Verified at `a18a3e8`)

- **Branch:** `main`
- **HEAD:** `a18a3e86ae6249ab58a39c07ca375bc0852a69f5`
- **Status:** Clean (no uncommitted changes)
- **Recent commits:**
  - `a18a3e8` Merge pull request #366 from DGCodeIdeas/lms-enrollment-persistence
  - `370fee3` fix(lms): PHPStan strict-rules
  - `e23f836` feat(lms): enrollment persistence + application service (PR-A1)
  - `be01323` Add initial research agenda document
  - `9496d96` feat: add comprehensive handoff and research prompts
- **Remote:** `origin → https://github.com/DGCodeIdeas/DGLab.git`
- **Worktrees:** None
- **Stashes:** None
- **Local branches:** Only `main` (no PR #363, #364, #310 branches)

---

## 8. Three Handoff Documents Analysis

### Documents

| Document | Lines | Role |
|----------|-------|------|
| `AGENTS.md` | 170 | Standing instructions for all AI agents |
| `DGLab-FRESH-SESSION-HANDOFF.md` | 290 | Master handoff with full protocol, research package, LMS build order |
| `FRESH-SESSION-PROMPTS.md` | 93 | Five prompts for Z.ai, Kilo, integration, LMS phase, Claude review |

### Cross-Document Consistency

All three documents are **internally consistent**. `FRESH-SESSION-HANDOFF.md` is the most detailed expansion of `AGENTS.md`. `FRESH-SESSION-PROMPTS.md` operationalizes both into agent-specific tasks.

### Claims vs. Codebase Reality

| Claim | Status |
|-------|--------|
| PR #364 open, mock-based | ❌ **Superseded** — merged via PR #366 at `a18a3e8` |
| Enrollment repo/service missing | ❌ **Superseded** — now implemented |
| `ApplicationFactory` only `/` and `/health` | ✅ **Verified** |
| `AuthMiddleware` exists but not wired | ✅ **Verified** (exists in Hub Identity) |
| No MySQL service in CI | ✅ **Verified** |
| No LMS HTTP controllers/routes | ✅ **Verified** |
| Research agenda not in canonical location | ✅ **Verified** |

---

## 9. Research Agenda Analysis

### Structure: 5 Tracks + SuperPHP

Each track has: research questions, working hypotheses, desired deliverables, related doctrines/contracts, out-of-scope, status line ("Research — Not an Implementation Requirement").

### Governance Compliance
- ✅ Research tracks not silently inserted into ADRs/blueprints
- ✅ Agenda distinct from OPEN-DECISIONS.md
- ✅ No rename without owner approval
- ✅ File in repo root, not canonical location (authorization required)
- ✅ Promotion protocol defined (§298–303)

### Gaps Identified
1. **V1–V7 reconciliation matrix** — MISSING (now created)
2. **Connecting-theme variants 2 & 3** — Only variant 1 in Track 5 (now added)
3. **Four Half Exceptions formulations** — Track 3 covered 2 meanings (now enumerated)
4. **Standalone SPX report** — Embedded in agenda (now extracted)
5. **Owner authorization to publish** — Not granted

---

## 10. SPX/SuperPHP Naming & Collision Research

### Internal Collision Status (Verified)

| Candidate | Internal Status | Evidence |
|-----------|----------------|----------|
| Forge | ❌ COLLISION | CORE-20 = "Sovereign Forge" (CLI Toolchain) |
| Foundry | ✅ Clean | No matches in Architecture/ or docs/ |
| Nexus | ❌ COLLISION (2-way) | HUB-17, HUB-21 |
| SPX | ✅ Clean | No matches for `\bSPX\b` |
| Sovereign | ❌ MASSIVE | Hub-tier prefix (26+ components) |

### External Collision Status (Web Research)

| Candidate | External Package | Registry | Risk |
|-----------|-----------------|----------|------|
| Foundry | `coderstm/foundry` (SaaS framework) | Packagist | Medium |
| SPX | `noisebynorthwest/php-spx` (profiling extension) | Packagist | Medium-High |
| Forge | `laravel/forge`, `forge-php/forge` | Packagist | High |
| Nexus | `nexusphp/*` | Packagist | High |
| Sovereign | No direct collision | N/A | Low external, disqualifying internal |

### Recommendations (Proposed, Not Decisions)
- Disqualify Forge, Nexus, Sovereign by internal collision
- Foundry and SPX remain candidates (owner must accept external collision risk)
- No rename authorized; ADR-005 remains authoritative

---

## 11. SPX/SuperPHP Ease-of-Use & Human/AI Comprehension Research

### Core Finding
The requirement — "easier than PHP and understandable by both humans and various AIs with varying knowledge levels" — is a **dual-audience design constraint** that is not automatically satisfiable. "Human-easy" (fewer tokens) can conflict with "AI-reliable" (verbose keywords).

### Key Research Findings

| Finding | Source | Evidence |
|---------|--------|----------|
| Dual-audience design has empirical support | LLMON (arXiv 2603.22519) | Two equivalent syntaxes (human shorthand + machine form) |
| Verbose keywords → 99.9% parse success | Anka (arXiv 2512.23214) | 100% vs 60% accuracy on multi-step pipelines vs Python |
| DualCode: one AST, multiple surface syntaxes | Sun et al. (arXiv 2404.16333) | Directly matches SuperPHP's syntax-driver pipeline |
| "Reuse PHP expressions" lowers onboarding | Latte | Models and PHP devs already know PHP |
| Constrained decoding cuts syntax errors ~96% | SynCode (arXiv 2403.01632) | Requires formal EBNF grammar |
| "AI-friendly" ≠ universal | TOON benchmarks | Weak models score ~55% regardless of format |

### 10 Proposed Design Principles
1. Familiarity — PHP expressions verbatim
2. Canonical Form — one written form per construct
3. Verbose Control Flow — full English keywords
4. Explicit Structure — tags/directives, not indentation
5. Safe Defaults — escaped unless explicitly raw
6. Small Core — minimal directive set in v1
7. Progressive Disclosure — novices see subset
8. Dual Surface — human shorthand ↔ machine canonical form
9. Formal Grammar — published EBNF for constrained decoding
10. Measured Ease — verified by SUS + benchmarks across skill/model tiers

### 5 Tensions Requiring Owner Decision
| Tension | Human-easy | AI-reliable | Proposed Resolution |
|---------|------------|-------------|---------------------|
| Verbosity | Fewer tokens | Verbose keywords | Verbose directives + concise variable output |
| Structure | Indentation | Explicit tags | Explicit tags for control flow |
| Expression language | Reuse PHP | Simpler subset | PHP expressions with safe subset |
| Closing tags | Parsimonious | Explicit close | Parsimonious in machine form |
| Escaping | Automatic | Explicit (teaches) | Automatic by default |

---

## 12. Vue SFC & Component-Format Research

### Key Findings

| Finding | Source | Relevance to SuperPHP |
|---------|--------|----------------------|
| Sectioned SFC is proven pattern | Vue, Svelte, Astro, Marko | Component organization |
| Marko's dual modes validate syntax drivers | Marko | Direct precedent for multiple authoring syntaxes |
| Astro's islands = SuperPHP's Reactive Bridge | Astro | Selective hydration via s-data attributes |
| DSDS audience tagging is emerging standard | DSDS (GitHub) | `for: human\|agent\|all` in component document |
| HTML-first viable but hits reusability limits | HTMX, Alpine.js | Support HTML-first + structured directives |
| AI-friendly metadata emerging | Hashnode, AWS, Mintlify | Semantic props, structured tokens, minimal formatting |

### Format Comparison (10 Formats)
Vue SFC, Svelte 5, Astro, SolidJS, Qwik, Marko, Lit, HTMX, Alpine.js, phpsfc — all compared on base syntax, build step, dual modes, AI-friendly metadata.

### 5 Open Questions
1. Sectioned SFC vs directive-based?
2. Dual authoring modes from v1?
3. Audience tagging in component document?
4. Build-free vs compiled?
5. Scoped styles vs SCSS framework delegation?

---

## 13. Framework Structure, DI, DX & Accessibility Research

### Key Findings

| Finding | Source | Relevance to DGLab/SuperPHP |
|---------|--------|----------------------------|
| Feature-sliced beats layer-based for change locality | pragmatic-franken, FSD, FFCA | Publish within-ring file convention |
| PSR-11 standardizes fetching only; config is implementation-specific | PSR-11 META | DGLab's custom container (ADR-002) is compliant |
| Constructor injection is default; service locator discouraged | Spring, Symfony, NestJS, PHP-DI | Aligns with DGLab's reported gaps |
| Structured frameworks reduce hidden architecture costs | BSWEN 2026-03-24 | Publish conventions before LMS increments |
| Frameworks don't make apps accessible (95.9% failure rate) | WebAIM Million 2024 | a11y must be encoded in primitives + linting + CI |
| Template-level fixes yield 17–39% a11y improvement | W3C 2011 symposium | SuperPHP component tags should carry a11y defaults |
| Power vs. simplicity: "simple tasks simple, complex ones possible" | Alan Kay | Core design principle for SuperPHP |
| Complexity ≠ confusion (Norman); subtract obvious, add meaningful (Maeda) | Norman, Maeda | Design philosophy for both DGLab and SuperPHP |
| Onboarding: quickstart + working samples + SUS ≥68/100 | Sendbird, IEEE | Target for DGLab developer onboarding |
| Monorepo: apps/ + packages/, one purpose per package, strong boundaries | Turborepo, monorepo.guide | Aligns with DGLab's existing structure |

### Research Scope (70 sources)
- Framework directory structures: Laravel, Symfony, Django, Rails, Spring Boot, Express, NestJS, FastAPI
- DI patterns: PSR-11, Laravel, Symfony, NestJS, Spring Boot, FastAPI
- DX: 16 facets (JSS 2025), UEMs, learnability, API learning obstacles, onboarding
- Accessibility: WebAIM Million, template-level fixes, design systems as enforcement, Lion, WCAG
- "Easy to use" principles: Kay, Norman, Maeda, BetterExplained, Firefox
- File organization: vertical slices, feature-sliced, domain-driven, atomic design, component-focused
- Onboarding: structured vs freedom, OSS newcomer, LLM-assisted, new-hire comprehension
- Component organization: co-location, scoped folders, no barrels, 300-line threshold
- Module/package structure: Turborepo, Nx, ABP, hexagonal, Bun monorepo

### 4 Tensions Requiring Owner Decision
| Tension | Structured side | Flexible side | Proposed Resolution |
|---------|-----------------|---------------|---------------------|
| Convention vs. freedom | Enforced layers (Spring/NestJS) | Freedom (FastAPI/Express) | Publish convention but allow opt-out per ring with ADR |
| Feature-slice vs. layer | Vertical slices (one folder/feature) | Technical layers | Feature-sliced within rings; shared kernel in Core |
| Hand-wired vs. container | Miragon style (server.ts) | Symfony-style compiled container | Hand-wired composition root (matches DGLab gaps) |
| a11y default vs. freedom | Enforced primitives | Developer responsibility | Defaults + opt-out + linting |

---

## 13. Research Deliverables Inventory

### Files Created/Updated in This Session

| File | Type | Lines | Status |
|------|------|-------|--------|
| `RESEARCH-RECONCILIATION-MATRIX.md` | New | ~350 | Draft — V1–V7 reconciliation, source/uncertainty register |
| `SPX-NAMING-COLLISION-REPORT.md` | New | ~280 | Draft — standalone SPX report with collision audit |
| `SPX-EASE-OF-USE-RESEARCH.md` | New | ~368 | Draft — ease-of-use & Human/AI comprehension (44 sources) |
| `VUE-SFC-COMPONENT-FORMAT-RESEARCH.md` | New | ~320 | Draft — Vue SFC & component-format comparative analysis (21 sources) |
| `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` | New | ~361 | Draft — framework structures, DI, DX, accessibility (70 sources) |
| `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` | New | ~361 | Draft — framework structures, DI, DX, accessibility (70 sources) |
| `RESEARCH-INDEX.md` | New | ~120 | Draft — research index linking 12 reports |
| `RESEARCH-AGENDA.md` | Updated | 304+ | Added 4 Half Exceptions formulations + 3 connecting-theme variants |
| `.kilo/plans/1792466263304-research-first-lms-backend-plan.md` | Updated | 195+ | Updated for merged PR #366, enrollment done |

### Research Gate Status

| Criterion | Status |
|-----------|--------|
| All 5 track reports + SuperPHP report | ✅ In `RESEARCH-AGENDA.md` |
| V1–V7 reconciliation matrix | ✅ Created |
| Three connecting-theme variants side-by-side | ✅ Updated in agenda |
| Standalone SPX naming/collision report | ✅ Created |
| Research index linking all reports | ✅ Created (13 reports) |
| SPX ease-of-use & Human/AI research | ✅ Created |
| Vue SFC & component-format research | ✅ Created |
| Framework structure, DI, DX & accessibility research | ✅ Created |
| Owner approves canonical agenda wording | ❌ Pending |
| Owner authorizes publication | ❌ Pending |

---

## 14. Pending Owner Decisions

### Research Decisions (Must Resolve Before Research Gate Closes)

| # | Decision | Options | Impact |
|---|----------|---------|--------|
| 1 | Connecting-theme variant | A, B, C, or combination | Canonical research question |
| 2 | Half Exceptions formulations 3 & 4 | Valid or not? | Research scope |
| 3 | SuperPHP placement | Separate section vs numbered track | Agenda structure |
| 4 | Naming candidates | Foundry, SPX, or neither | Future rename |
| 5 | Authorization to publish | `Architecture/CrossCutting/RESEARCH-AGENDA.md` | Research gate closure |

### LMS Decisions (Must Resolve Before Gate 1 HTTP Work)

| # | Decision | Options | Impact |
|---|----------|---------|--------|
| 6 | Auth transport (ADR-022) | Session cookies / JWT bearer / both | All HTTP layer work |
| 7 | MySQL integration-test contract | DSN, runner, isolation, CI service | All real-DB tests |
| 8 | PR #363 route | Separate or merge formatting audit + ELQ gitlink | CI hygiene |
| 9 | D-01 / S-003 | Re-scope or commission test | SDLC governance |
| 10 | D-09 | PSR-4 collision vs findings register | ADR-011 priority |
| 11 | SKILL.md provenance | `82ddde7` authorization | Governance |
| 12 | Formatting audit | Artifact recovery, readability verdict | CI policy |
| 13 | PR #310 | Resume or leave parked | Core tier |
| 14 | Stale git credential | Revoke/rotate, remove safely | Security hygiene |

### SPX Design Decisions (Must Resolve Before SuperPHP Implementation)

| # | Decision | Options | Impact |
|---|----------|---------|--------|
| 15 | Sectioned SFC vs directive-based | Vue-style vs Blade-style | Authoring experience |
| 16 | Dual authoring modes from v1 | Marko-style vs single mode | Syntax driver complexity |
| 17 | Audience tagging in component doc | DSDS-style `for: human\|agent\|all` | Interchange contract |
| 18 | Build-free vs compiled | HTMX-style vs Vue-style | DX and performance |
| 19 | Scoped styles | Vue-style vs SCSS delegation | Style architecture |
| 20 | Dual surface (LLMON-style) | Two syntaxes vs one | AI reliability |
| 21 | Escaping default | Automatic vs explicit | Safety and teaching |
| 22 | Component document format | JSON-first vs JSON+YAML | Interoperability |

---

## 15. Capability Matrix (Verified at `a18a3e8`)

| Component | Implemented | Unit-Tested | MySQL Integration | E2E Demo |
|-----------|-------------|-------------|-------------------|----------|
| Core (20) | ✅ | ✅ | N/A | N/A |
| Hub (6+) | ✅ | ✅ | N/A | N/A |
| Bridge (Vanguard) | ✅ | ✅ | N/A | N/A |
| Codex/Canvas/Showcase | ✅ | ✅ | N/A | N/A |
| LMS Domain/Application | ✅ | ✅ | ❌ | ❌ |
| LMS Course/Module Persistence | ✅ | ✅ (mock) | ❌ | ❌ |
| LMS Enrollment Persistence | ✅ | ✅ | ❌ | ❌ |
| LMS Enrollment Application Service | ✅ | ✅ | ❌ | ❌ |
| LMS Progress Persistence | ❌ | ❌ | ❌ | ❌ |
| LMS Progress Application Service | ❌ | ❌ | ❌ | ❌ |
| LMS HTTP Routes/Controllers | ❌ | ❌ | ❌ | ❌ |
| Auth Middleware Wired | ❌ (exists in Hub) | ❌ | ❌ | ❌ |
| Real MySQL in CI | ❌ | ❌ | ❌ | ❌ |
| Complete Learner/Admin Flow | ❌ | ❌ | ❌ | ❌ |

---

## 16. LMS Build Order (Updated for Merged PR #366)

### Gate 0: Current-State Review (Steps 1–3)
1. Re-review current `origin/main` (HEAD `a18a3e8`)
2. Review merged PR #366 (enrollment persistence) — verify implementation vs. original scope
3. Reconfirm LMS capability matrix (4 evidence levels)

### Gate 1: API/Security/Test Contracts (Steps 4–6) — **Owner Decisions Required**
4. Auth transport + same-origin vs cross-origin (ADR-022)
5. API versioning, roles/permissions, error envelope, pagination, validation, transactions
6. MySQL integration-test contract: DSN, runner, isolation, CI service

### Gate 2: Domain/Persistence/Application (Steps 7–10) — **Enrollment DONE**
7. Progress repository port + MySQL implementation + migration review
8. Progress application service
9. Course/module/detail read models (no PR #364 dup)
10. Verify transactions, uniqueness, FKs, ordering, concurrency, ownership

### Gate 3: HTTP/API (Steps 11–21)
11. Composition-root + DI registration
12. JSON parsing/serialization, DTOs, validation, error taxonomy
13. Login/logout/session/token endpoints + middleware
14. Public course discovery/detail routes
15. Admin course/module CRUD + publish routes + role checks
16. Enrollment routes + learner/course access rules
17. Progress routes + enrollment/content authorization
18. API contract tests + OpenAPI documentation
19. Gate 3 review (independent review)

### Gate 4: Real Verification (Steps 20–23)
20. MySQL 8 CI service + isolated test DB
21. Real-MySQL integration tests for every repository + transaction boundary
22. E2E journey: admin→create→publish→learner→enroll→progress
23. Negative tests (unauth, unauthorized, cross-user, unpublished, invalid, duplicate, missing, malformed, DB failure, pagination, CSRF/CORS)

### Gate 5: Founder Handoff (Steps 24–25)
24. Commit SHA, capability matrix, setup guide, env vars, migrations, API spec, curl examples, auth instructions, error catalogue, test commands, known limitations, extension points
25. Claude independent review with skeptical prompt

---

## Summary

This session completed:
1. **Full codebase analysis** — structure, architecture, config, CI, frozen contracts, ADRs, LMS state, research tracks, git state
2. **Three handoff documents analysis** — consistency check, claims vs. reality verification
3. **Research agenda analysis** — gap identification, governance compliance
4. **SPX/SuperPHP naming research** — internal + external collision audit (5 candidates)
5. **SPX ease-of-use research** — dual-audience design, 44 sources, 10 proposed principles
6. **Vue SFC & component-format research** — 10 formats compared, 5 open questions
7. **V1–V7 reconciliation matrix** — all rendition changes documented
8. **Research index** — 11 reports linked, evidence vs. recommendations separated
9. **LMS plan updated** — enrollment done (PR #366), progress is next gap
10. **All findings recorded** in this comprehensive analysis document

**No implementation has been started.** Research gate remains open pending owner review. All research deliverables are drafts awaiting owner authorization. No code, ADR, frozen contract, or governance file has been modified.

---

**End of complete analysis record.**