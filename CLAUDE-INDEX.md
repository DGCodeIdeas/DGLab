# DGLab — Claude Session Context Index

**Last verified:** 2026-10-22 09:00 UTC  
**Git baseline:** `main` at `8858e165a76db7b14650298ca17b2265da1e4115`  
**Remote verified:** Yes — `git fetch origin` completed; remote refs resolve to reported SHAs  
**Verification limitations:** GitHub web UI may lag behind `git fetch` results; CI run details confirmed via GitHub Actions. Some PR status claims are REPORTED, not independently verified.

---

## 1. Project Identity and Mission

### What DGLab Is

DGLab is a **PHP 8.4 Sovereign Stack monorepo** using a custom **Core/Hub/Spoke architecture within a six-ring Wheel** model. It is a modular, independently versioned system designed for sovereign software infrastructure.

### Product Objectives (Founder-Reported)

- **Complete, usable LMS backend** — The founder changed the LMS objective from a page-ready prototype to a complete, usable backend so the founder can build UI/UX independently.
- **LMS target capabilities:** Identity/auth + RBAC; course/module CRUD + publish; published-course discovery; learner enrollment; progress recording/retrieval; versioned REST/JSON API; composition-root/DI wiring; middleware; migrations; real-MySQL integration tests; end-to-end learner/admin flow; founder handoff documentation.
- **NeoSPX template language** — A component/syntax architecture with multiple authoring syntaxes, two-stage representation (syntax → public component document → AST → compilation), and a language-neutral interchange contract.
- **Founder owns frontend/UI/UX** — No JavaScript framework or polished frontend is part of the backend handoff.

### Current Development Direction

**Research-first, then LMS backend.** Per `AGENTS.md` §5, research tracks must complete before new LMS implementation increments. The research phase is substantially complete (9 research report files in root: 5 `*-RESEARCH*.md` + 3 `RESEARCH-*.md` + `COMPLETE-ANALYSIS-RECORD.md`; `RESEARCH-INDEX.md` lists 14 inventory rows but one is duplicated and one file is omitted). The LMS backend is in-progress: enrollment persistence is done (PR #366 merged); progress persistence is on a feature branch (PR #369, unmerged); HTTP/API layer and auth wiring are not started.

### Claude's Intended Role

Claude is an **additional implementation agent** working only within an explicitly authorized scope. Claude does not own product direction, scope, priority, acceptance, or merge authorization. Claude must not begin parallel implementation without explicit prior authorization.

---

## 2. How Claude Should Start a New Session

1. **Inspect Git state without modifying the working tree:**
   - `git status --short --branch`
   - `git rev-parse HEAD` and `git rev-parse origin/main`
   - `git log -5 --oneline --decorate`
   - `git branch -a` and `git worktree list`
   - `git stash list`

2. **Read current root `AGENTS.md`** — the active repository instructions (see §3 for status note).

3. **Read applicable nested instructions** — check for `AGENTS.md` or tool rules in subdirectories.

4. **Read `CLAUDE-INDEX.md`** (this document) for navigation and context.

5. **Follow the minimum reading set** for the requested task (see §9).

6. **Inspect actual implementation and tests** before making technical claims — do not infer from filenames.

7. **Refresh time-sensitive information:** PR heads, CI results, branch state, active task ownership.

8. **Identify uncertainties and shared-interface risks** before implementing.

9. **Confirm authorized scope** when the requested work could affect shared contracts or architecture.

10. **Report findings and proposed changes** before proceeding when authorization or architectural scope is ambiguous.

**Epistemic rule:** Previous agent reports, conversation summaries, and historical handoffs are **leads to verify** — not automatically authoritative sources. Label statements as VERIFIED, REPORTED, PROPOSED, or UNVERIFIED.

---

## 3. Governance and Agent Responsibilities

### Role Allocation (REPORTED — Founder-Provided; No Authoritative Repository Record Found)

| Role | Responsibility |
|------|----------------|
| **Founder** | Owns product direction, scope, priority, acceptance, and merge authorization |
| **Kilo Code** | Primary implementation agent |
| **Claude** | Additional implementation agent working only within an explicitly authorized scope |
| **Z.ai** | Research, analysis, and review support as assigned |
| **SAAI** | Architecture coordination, technical contracts, acceptance criteria, and independent review |

**Note:** This role allocation is reported by the founder. No authoritative repository record (ADR, governance file, or frozen contract) was found that formally assigns these roles. Verify against `Architecture/SDLC/` documentation.

### SDLC v5.1 Principles (from `AGENTS.md` §4)

- **No parallel implementation** without explicit prior authorization from the Tech Lead.
- **Single implementer, multiple independent reviewers** — reviewers inspect and report; they do not edit implementation artifacts unless explicitly authorized.
- **Findings go back to the implementer** for correction.
- **Human owner/Tech Lead controls scope, architectural decisions, acceptance, and merge.**
- **Contributors use their own branch**, do not push to main/integration, do not self-merge, open PRs with explanation and verification.
- **CI-green is evidence, not proof** that behavior is complete or correctly integrated.
- **Separate Git worktrees do not remove shared-interface or integration risks.**

### Source-of-Truth Hierarchy

See §10 for the full hierarchy. In brief:

1. Explicit, current owner decisions
2. Merged repository records and frozen contracts
3. Approved ADRs and current architecture documentation
4. Current source code, tests, CI configuration, and actual CI runs
5. Open PRs and unmerged branches (clearly labeled as proposed)
6. Agent reports and chat summaries (labeled as reported until verified)

### Authoritative Governance Documents

| Path | Purpose | Authority |
|------|---------|-----------|
| `AGENTS.md` | Active repository instructions for AI agents | APPROVED (current) — but see §6 re: PR #368 |
| `Architecture/SDLC/` | SDLC v5.1 governance documentation | APPROVED |
| `Architecture/FROZEN-CONTRACTS.md` | 31 frozen interfaces, 2 frozen enums | APPROVED (frozen) |
| `Architecture/ADRs/` | 21 ADRs (ADR-001 through ADR-021) | APPROVED where status = Accepted |

---

## 4. Architecture and Technical Foundations

### Core/Hub/Spoke/Wheel (Six-Ring Monorepo)

**Normative model:** `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` v0.4

| Ring | Type | Components | Role |
|------|------|------------|------|
| Core | Layer | `CORE-01`–`20` | Nucleus — independently versioned, system-only |
| Hub | Layer | `HUB-01`–`30` (+ `HUB-31` proposed) | Inner ring — internal services, mandatory traversal |
| Inner Rim | Checkpoint | `BRIDGE-01` (Vanguard) | Single stateless gate — transform + forward |
| Inner Spokes | Layer | `ISPOKE-01`–`25` | Staff-only, authenticated + authorized |
| Outer Spokes | Layer | `ESPOKE-01`–`15` | Public-facing |
| Outer Rim | Checkpoint | `HUB-08` (Gateway) | Public ingress enforcement |

**Principle:** Compose downward, abstract upward, expose only the capability contract appropriate to the consumer.

### Dependency Direction

- Core ↛ Hub (Core does not depend on Hub)
- Hub ↛ Spoke (Hub does not depend on Spoke)
- Bridge (`BRIDGE-01`) is the sole gate between Hub and Spokes
- Gateway (`HUB-08`) is the sole public ingress checkpoint

### Key ADRs

| ADR | Title | Status | Relevance |
|-----|-------|--------|-----------|
| ADR-003 | ES256 JWT Signing | Accepted | Auth transport |
| ADR-005 | NeoSPX vs Blade/Twig | Accepted | Template language |
| ADR-008 | Argon2id Password Hashing | Accepted | Auth |
| ADR-009 | ULID over UUID | Accepted | Identifiers |
| ADR-010 | Opcache Preload Strategy | Accepted | Performance |
| ADR-012 | Post-Quantum JWT Agility | Accepted | Auth |
| ADR-013 | MySQL Primary Datastore | Accepted | Persistence (supersedes ADR-007) |
| ADR-014 | Ratify AGRD Canonical SDLC | Accepted | Governance |
| ADR-016 | Library/App Boundary Split | Accepted | Architecture |
| ADR-017 | Fiber-Based Cooperative Runtime | Accepted | Runtime |
| ADR-021 | Tier-Stratified Build Order | Accepted | Build order |
| **ADR-022** | **Auth transport + authorization** | **NOT YET WRITTEN** | **Blocks all HTTP layer work** |

### Frozen Contracts

`Architecture/FROZEN-CONTRACTS.md` — 30 frozen interfaces, 1 frozen enum (31 frozen of 32 total) across Core/Hub/Bridge/Spoke. These are immutable without an ADR.

### Architecture Documentation (Topic Index)

| Topic | Path | Authority | Read When |
|-------|------|-----------|-----------|
| Architecture principles | `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` | APPROVED | Any architecture work |
| Architecture fitness | `scripts/` (Python/PHP linters) | CURRENT | CI failures |
| Boundary lint | `.github/workflows/architecture-boundary-lint.yml` | CURRENT | Ring-boundary issues |
| ADR index | `Architecture/ADRs/` | APPROVED | Any architectural decision |
| Frozen contracts | `Architecture/FROZEN-CONTRACTS.md` | FROZEN | Interface changes |
| Open decisions | `Architecture/OPEN-DECISIONS.md` | CURRENT | Pending decisions |
| Glossary | `Architecture/CrossCutting/GLOSSARY.md` | CURRENT | Terminology |
| Component index | `Architecture/INDEX.md` | CANONICAL | Component identity |

---

## 5. LMS Architecture and Implementation Map

### Current State (Verified on `main` at `8858e16`)

| Component | Implemented | Unit-Tested | MySQL Integration | E2E Demo |
|-----------|-------------|-------------|-------------------|----------|
| LMS Domain/Application | ✅ | ✅ | ❌ | ❌ |
| LMS Course/Module Persistence | ✅ | ✅ (mock) | ❌ | ❌ |
| LMS Enrollment Persistence | ✅ | ✅ | ❌ | ❌ |
| LMS Enrollment Application Service | ✅ | ✅ | ❌ | ❌ |
| LMS Progress Persistence | ❌ (on PR #369 branch) | ❌ | ❌ | ❌ |
| LMS Progress Application Service | ❌ (on PR #369 branch) | ❌ | ❌ | ❌ |
| LMS HTTP Routes/Controllers | ❌ | ❌ | ❌ | ❌ |
| Auth Middleware Wired | ❌ (exists in Hub) | ❌ | ❌ | ❌ |
| Real MySQL in CI | ❌ | ❌ | ❌ | ❌ |
| Complete Learner/Admin Flow | ❌ | ❌ | ❌ | ❌ |

### Progress-Recording Path (on PR #369 branch, not yet merged)

```
RecordProgressCommand(enrollmentId, moduleId, newStatus)
  → ProgressApplicationService::recordProgress(cmd, actor)
    → Validate: enrollment exists + Active
    → Validate: course Published
    → Validate: module belongs to course
    → Validate: actor is learner or admin
    → BEGIN TRANSACTION
      → getForUpdate(enrollmentId, moduleId)  [SELECT ... FOR UPDATE]
      → If null + NotStarted → commit, return (no-op)
      → If null + other status → insert() [plain INSERT, throws on duplicate key 1062]
        → On duplicate key: re-read with getForUpdate → transition() → save() [upsert]
      → If not null → transition() → save() [upsert]
      → COMMIT
    → On failure: ROLLBACK, rethrow
```

**Domain invariants enforced by `Progress::transition()`:**
- Forward-only: `not_started → in_progress → completed`
- Same-status is idempotent
- `completed` is terminal (no regression)
- `in_progress → not_started` is rejected

### Progress-Persistence Invariants (Verification Targets)

| Invariant | Implementation | Unit Test | Integration Test | CI Verified |
|-----------|---------------|-----------|-----------------|-------------|
| Course must be Published | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (unit) |
| Enrollment must be Active | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (unit) |
| Module belongs to course | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (unit) |
| Actor is learner or admin | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (unit) |
| Actor identity from trusted auth context | ⚠️ Partial | `recordProgress` receives `UserId $actor` as a separate parameter (not embedded in the command), which is correct design. The concern is whether the future HTTP layer will extract actor identity from the auth context rather than trusting client input. HTTP layer is not yet implemented. | ⚠️ (partial) | ⚠️ (partial) |
| Domain transition rules enforced centrally | ✅ `Progress::transition()` | ✅ | ❌ | ✅ (unit) |
| Completed module cannot regress | ✅ `Progress::transition()` | ✅ | ❌ | ✅ (unit) |
| NotStarted no-op when no row exists | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (unit) |
| NotStarted bypasses validation when row exists | ✅ `Progress::transition()` throws | ✅ | ❌ | ✅ (unit) |
| Duplicate-key recovery (1062 → re-read → transition → save) | ✅ `ProgressApplicationService` | ✅ (mock) | ❌ | ✅ (mock) |
| Transaction rollback on failure | ✅ `ProgressApplicationService` | ✅ | ❌ | ✅ (mock) |
| Timestamps not fabricated/overwritten | ⚠️ Partial | `startedAt` is set to the recording time (first progress record), not an arbitrary value. `completedAt` is set only when transitioning to `Completed`. The `update()` method sets `completedAt` on completion but does not overwrite `startedAt`. `startedAt` is not updated on subsequent transitions. | ⚠️ (partial) | ⚠️ (partial) |

**Legend:** ✅ Verified | ⚠️ Partial/Uncertain | ❌ Not implemented/tested

### LMS Source Files (on `main`)

| Path | Purpose |
|------|---------|
| `packages/spoke/internal/lms/src/Domain/Entity/Course.php` | Course aggregate root |
| `packages/spoke/internal/lms/src/Domain/Entity/Module.php` | Module entity |
| `packages/spoke/internal/lms/src/Domain/Entity/Enrollment.php` | Enrollment entity |
| `packages/spoke/internal/lms/src/Domain/Entity/Progress.php` | Progress entity (on PR #369 branch only) |
| `packages/spoke/internal/lms/src/Application/Service/CourseApplicationService.php` | Course use cases |
| `packages/spoke/internal/lms/src/Application/Service/EnrollmentApplicationService.php` | Enrollment use cases |
| `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLCourseRepository.php` | Course persistence |
| `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLEnrollmentRepository.php` | Enrollment persistence |
| `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLModuleRepository.php` | Module persistence |
| `app/ApplicationFactory.php` | Composition root — only `/` and `/health` routes |

### LMS Migrations

| File | Table | Key Details |
|------|-------|-------------|
| `001_create_courses_table.sql` | `lms_courses` | id (CHAR 26), title, slug (unique), status, timestamps |
| `002_create_modules_table.sql` | `lms_modules` | id, course_id (FK), title, sort_order |
| `003_create_enrollments_table.sql` | `lms_enrollments` | id, course_id, learner_id, status, enrolled_at, completed_at; unique(course_id, learner_id) |
| `004_create_progress_table.sql` | `lms_progress` | PK(enrollment_id, module_id), status, started_at, completed_at; FKs |
| `005_add_module_content.sql` | `lms_modules` | `ALTER TABLE ... ADD COLUMN content MEDIUMTEXT NULL` |

### LMS Tests

| Test File | Type | Coverage |
|-----------|------|----------|
| `CourseApplicationServiceTest.php` | Unit | Course CRUD, publish, archive |
| `EnrollmentApplicationServiceTest.php` | Unit | Enrollment create, cancel, list |
| `MySQLCourseRepositoryTest.php` | Unit (mock) | Course persistence queries |
| `MySQLEnrollmentRepositoryTest.php` | Unit (mock) | Enrollment persistence queries |
| `MySQLModuleRepositoryTest.php` | Unit (mock) | Module persistence queries |
| `ProgressApplicationServiceTest.php` | Unit (mock) | Progress service (on PR #369 branch) |
| `MySQLProgressRepositoryTest.php` | Unit (mock) | Progress persistence (on PR #369 branch) |
| `ProgressConcurrencyTest.php` | Integration (real MySQL) | Concurrency (on PR #369 branch) |

---

## 6. Git, Pull Requests, and CI

### Current Git State (Verified 2026-10-22)

| Field | Value |
|-------|-------|
| Branch | `main` |
| HEAD | `8858e165a76db7b14650298ca17b2265da1e4115` |
| origin/main | `8858e165a76db7b14650298ca17b2265da1e4115` (matches) |
| Working tree | Clean (tracked). Untracked: `.phpunit.cache/`, `CLAUDE-INDEX(Stale!).md`, `CLAUDE-INDEX.md`, `CLAUDE.md` |
| Stash | `stash@{0}: On main: SPX Stash` (modifies RESEARCH-AGENDA.md, RESEARCH-INDEX.md, SPX-NAMING-COLLISION-REPORT.md) |
| Local branches | `main`, `docs/update-agents-md-status`, `lms-progress-persistence` (worktree) |
| Worktrees | `/home/dgci/www/DGLab` (main), `/home/dgci/.local/share/kilo/worktree-pool/dglab-7f4f6ae19e3a/plume-astrodon` (detached), `/home/dgci/www/DGLab-concurrent-tests` (detached), `/home/dgci/www/dglab-progress-fix` (lms-progress-persistence) |

### PR #368 — `AGENTS.md` Reconciliation

| Field | Value |
|-------|-------|
| Branch | `docs/update-agents-md-status` |
| Remote HEAD (VERIFIED) | `c1baea799ecf48064f91e8a478d183de41a2109a` |
| Previously reported | `c1baea7` (matches verified) |
| Status | Ready for review, not approved for merge |
| Changes | Draft-status removed, active-instruction status, baseline verification requirement, transient status labeled |
| CI | Passed |

### PR #369 — LMS Progress Persistence

| Field | Value |
|-------|-------|
| Branch | `lms-progress-persistence` |
| Remote HEAD (VERIFIED) | `f985a180e8866e6d1de424eb7d05ceebcc035ad9` |
| Previously reported | `f985a18` (matches verified) |
| Status | Open, unapproved |
| Latest CI run | ❌ FAILED (Run #39744074200, 2026-10-22T07:56:33Z). Failing job: `Root integration (public entry point smoke)`. Error: `Class "SovereignStack\Spoke\Lms\Domain\ValueObject\CourseStatus" not found` at `tests/Integration/ProgressConcurrencyTest.php:137`. Root cause: `CourseStatus` is declared as an enum in `Domain\Entity\Course.php`, not in `Domain\ValueObject`. A separate run (#39744759924) on `main` succeeded but does not reflect the PR branch state. Fix committed locally (unstaged) in worktree `/tmp/wt-progress-3`. |
| Changes | Progress persistence + service, concurrent integration tests (7 tests), MySQL 8.0 CI service, cache cleanup |

### CI Workflows

| Workflow | Purpose | Triggers on PR? |
|----------|---------|-----------------|
| `packages-ci.yml` | PHPStan + PHPUnit per package (20+ entries) | Yes |
| `architecture-lint.yml` | Documentation/reference structure checks | Yes (paths) |
| `architecture-fitness.yml` | Pulse consistency, naming drift, blueprint fidelity | Yes |
| `architecture-boundary-lint.yml` | Ring-boundary enforcement + service-locator prohibition | Yes |
| `release.yml` | SemVer release automation via Loom | No (push to main only) |
| `secret-scan.yml` | Security scanning | Yes |
| `pr-title-lint.yml` | PR title conventions | Yes |

**CI on `main`:** No MySQL service. All LMS tests are unit tests with mocked connections. Integration tests exist only on PR #369 branch.

---

## 7. Risks, Blockers, and Unresolved Decisions

| ID | Issue | Evidence | Status | Impact | Decision Owner | Next Action |
|----|-------|----------|--------|--------|----------------|-------------|
| R-01 | Auth transport undecided (ADR-022 not written) | No ADR-022 exists; AGENTS.md §6 step 4 | Open decision | Blocks all HTTP/API work | Founder | Choose session cookies / JWT / both |
| R-02 | MySQL integration-test contract undefined | No DSN convention, no migration runner in CI, no isolation strategy | Open decision | Blocks real-DB tests | Founder | Define env var, runner, isolation, CI service |
| R-03 | PR #369 not merged | Open PR, unapproved, CI FAILED (CourseStatus namespace error in test) | Active blocker | Progress persistence unavailable on main; CI failing | Founder/SAAI | Review, fix test, re-run CI, then approve |
| R-04 | PR #368 not merged | Open PR, unapproved | Active blocker | AGENTS.md draft status remains | Founder/SAAI | Review and approve |
| R-05 | No LMS HTTP routes/controllers | `ApplicationFactory` only has `/` and `/health` | Active blocker | No API surface | Kilo (after R-01) | Implement controllers/routes |
| R-06 | AuthMiddleware not wired | Exists in Hub Identity, not in pipeline | Active blocker | No auth enforcement | Kilo (after R-01) | Wire into middleware stack |
| R-07 | No Progress persistence on main | `ProgressRepositoryInterface` not found anywhere | Active blocker | No progress tracking | Kilo (after R-03) | Merge PR #369 |
| R-08 | Research agenda not published | `Architecture/CrossCutting/RESEARCH-AGENDA.md` does not exist | Open decision | Research gate not closed | Founder | Authorize publication |
| R-09 | D-01/S-003 governance item | `PulseFiberIsolationTest.php` missing | Open decision | SDLC compliance | Founder | Re-scope or commission test |
| R-10 | D-09 PSR-4 collision vs ADR-011 | ADR-011 is Proposed; no findings register found | Open decision | ADR-011 priority | Founder | Resolve collision |
| R-11 | PR #363 route (formatting/gitlink) | Reported closed in PR #368 branch AGENTS.md, but not independently verified | UNVERIFIED | CI hygiene | Founder | Verify resolution independently |
| R-12 | PR #310 (CORE-15 cache) parked | CORE-15 package exists, no cache impl | Open decision | Core tier | Founder | Resume or leave parked |
| R-13 | Stale git credential | Reported in pre-existing branch config | Unverified | Security hygiene | Founder | Revoke/rotate if valid |
| R-14 | SKILL.md provenance (`82ddde7`) | Local-only change, modified `.kilo/skills/` | Unverified | Governance | Founder | Authorize or revert |
| R-15 | NeoSPX naming undecided (historical: SuperPHP) | 5 candidates, collisions identified | Open decision | Future rename | Founder | Accept candidate or keep SuperPHP |
| R-16 | Connecting-theme variant undecided | 3 variants, only 1 in agenda | Open decision | Research direction | Founder | Choose variant |
| R-17 | Half Exceptions formulations 3 & 4 unconfirmed | Agent interpretations, not owner-confirmed | Open decision | Research scope | Founder | Confirm or reject |
| R-18 | No real MySQL on main CI | No MySQL service in packages-ci.yml | Active blocker | Cannot verify DB behavior | Founder (R-02) | Define test contract |

---

## 8. Founder's Immediate Next Steps

### A. Independently Actionable (No Authorization Needed)

- Inspect repository structure and documentation
- Review research deliverables (9 report files in root)
- Read LMS source code and tests
- Identify test gaps and missing coverage
- Research and analyze without modifying code
- Verify claims from agent reports against source

### B. Implementation Work Requiring Explicit Scope

| Candidate Task | Interfaces Affected | Tests Needed | Dependencies |
|----------------|-------------------|--------------|--------------|
| Progress persistence (if PR #369 not merged) | `ProgressRepositoryInterface`, `ProgressApplicationService` | Unit + integration | PR #369 merge or reimplementation |
| HTTP/API layer | Controllers, routes, DTOs, validators | Contract + integration | R-01 (auth), R-02 (MySQL) |
| Auth middleware wiring | `AuthMiddleware`, middleware stack | Auth flow tests | R-01 (auth transport) |
| Course/module read models | Read models, use cases | Unit + integration | PR #364 scope |

### C. Founder Decisions Required

1. **Auth transport (ADR-022)** — Session cookies / JWT / both
2. **MySQL integration-test contract** — DSN, runner, isolation, CI service
3. **Research agenda publication** — Authorize `Architecture/CrossCutting/RESEARCH-AGENDA.md`
4. **NeoSPX naming** — Accept Foundry/SPX or keep SuperPHP (historical)
5. **Connecting-theme variant** — A, B, C, or combination
6. **PR #368/#369 review and merge** — Approve or request changes
7. **D-01/D-09 governance items** — Re-scope or commission tests

### D. Blocked Work

- All HTTP/API implementation (blocked by R-01)
- All real-MySQL integration tests (blocked by R-02)
- Progress persistence on main (blocked by R-03)
- AGENTS.md reconciliation on main (blocked by R-04)

---

## 9. Detailed Multi-File Context Index

### 9.1 Repository Instructions and Governance

**Minimum reading set:**
- `AGENTS.md` — Active repository instructions (170 lines)
- `Architecture/FROZEN-CONTRACTS.md` — 31 frozen interfaces

**Expanded reading set:**
- `Architecture/SDLC/` (7 files) — SDLC v5.1 governance
- `Architecture/INDEX.md` — Canonical component registry
- `Architecture/OPEN-DECISIONS.md` — Pending decisions
- `Architecture/ADRs/ADR-014-*` — Ratify AGRD Canonical SDLC

### 9.2 Architecture Principles and ADRs

**Minimum reading set:**
- `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` — Six-ring wheel model
- `Architecture/ADRs/ADR-013-*` — MySQL primary datastore
- `Architecture/ADRs/ADR-005-*` — NeoSPX vs Blade/Twig

**Expanded reading set:**
- All 21 ADRs in `Architecture/ADRs/`
- `Architecture/CrossCutting/DGLAB-AS-OS.md` — DGLab as OS
- `Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md` — Nuclear-grade doctrine
- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — Blind-spot doctrine

### 9.3 Framework and DI

**Minimum reading set:**
- `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` — Framework structures, DI, DX, accessibility (70 sources)

**Expanded reading set:**
- `packages/core/container/` — Core container implementation
- `app/ApplicationFactory.php` — Composition root
- `Architecture/ADRs/ADR-002-*` — PSR-11 Container Scope

### 9.4 LMS Domain

**Minimum reading set:**
- `packages/spoke/internal/lms/src/Domain/Entity/Course.php`
- `packages/spoke/internal/lms/src/Domain/Entity/Enrollment.php`
- `packages/spoke/internal/lms/src/Domain/Entity/Progress.php` (PR #369 branch only)
- `packages/spoke/internal/lms/src/Domain/Entity/Module.php`

**Expanded reading set:**
- `packages/spoke/internal/lms/src/Domain/ValueObject/` (7 value objects)
- `packages/spoke/internal/lms/src/Domain/Exception/` (4 exceptions)
- `packages/spoke/internal/lms/src/Domain/Repository/` (interfaces)

### 9.5 LMS Application Services

**Minimum reading set:**
- `packages/spoke/internal/lms/src/Application/Service/CourseApplicationService.php`
- `packages/spoke/internal/lms/src/Application/Service/EnrollmentApplicationService.php`
- `packages/spoke/internal/lms/src/Application/ProgressApplicationInterface.php` (PR #369 branch)

**Expanded reading set:**
- `packages/spoke/internal/lms/src/Application/Command/` (4 commands)
- `packages/spoke/internal/lms/src/Application/ProgressApplicationService.php` (PR #369 branch)

### 9.6 Persistence and Migrations

**Minimum reading set:**
- `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLCourseRepository.php`
- `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLEnrollmentRepository.php`
- `packages/spoke/internal/lms/migrations/` (5 SQL files)

**Expanded reading set:**
- `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLModuleRepository.php`
- `packages/spoke/internal/lms/src/Infrastructure/Persistence/MySQLProgressRepository.php` (PR #369 branch)
- `packages/core/dbal/` — Database abstraction layer

### 9.7 Authentication and Authorization

**Minimum reading set:**
- `packages/hub/identity/src/Http/AuthMiddleware.php` (exists, not wired)
- `Architecture/ADRs/ADR-003-*` — ES256 JWT
- `Architecture/ADRs/ADR-008-*` — Argon2id

**Expanded reading set:**
- `packages/hub/identity/` (full package)
- `Architecture/ADRs/ADR-012-*` — Post-Quantum JWT Agility
- `Architecture/FROZEN-CONTRACTS.md` (HUB-01 Config frozen interfaces)

### 9.8 API and Application Wiring

**Minimum reading set:**
- `app/ApplicationFactory.php` — Composition root (only `/` and `/health`)
- `public/index.php` — Web entry point

**Expanded reading set:**
- `app/Controllers/` — HelloController, HealthController
- `app/Bridge/` — Vanguard bridge
- `app/Resources/` — Resource definitions

### 9.9 Unit and Integration Testing

**Minimum reading set:**
- `packages/spoke/internal/lms/tests/Unit/Application/CourseApplicationServiceTest.php`
- `packages/spoke/internal/lms/tests/Unit/Application/EnrollmentApplicationServiceTest.php`
- `packages/spoke/internal/lms/phpunit.xml.dist`

**Expanded reading set:**
- All LMS test files (7 unit test files)
- `tests/Integration/PublicIndexSmokeTest.php` (root)
- `ProgressConcurrencyTest.php` (PR #369 branch)
- `RESEARCH-INDEX.md` — Research test coverage

### 9.10 CI and Developer Tooling

**Minimum reading set:**
- `.github/workflows/packages-ci.yml` — Main test matrix
- `.github/workflows/architecture-boundary-lint.yml` — Ring boundaries

**Expanded reading set:**
- All 7 workflows in `.github/workflows/`
- `scripts/` — CI/architecture tooling (Python/PHP)
- `anvil/` — Infrastructure tooling
- `orchestrator/` — Loom release automation

### 9.11 Research and Analysis

**Minimum reading set:**
- `RESEARCH-INDEX.md` — Links 14 inventory rows (13 distinct reports; row #13 duplicated; omits `COMPONENT-TEMPLATE-FORMATS-RESEARCH.md`)
- `RESEARCH-AGENDA.md` — Canonical agenda draft (5 tracks + NeoSPX)

**Expanded reading set:**
- `RESEARCH-RECONCILIATION-MATRIX.md` — V1–V7 reconciliation
- `SPX-NAMING-COLLISION-REPORT.md` — SPX naming audit
- `SPX-EASE-OF-USE-RESEARCH.md` — Ease-of-use & Human/AI (44 sources)
- `VUE-SFC-COMPONENT-FORMAT-RESEARCH.md` — Vue SFC comparison (21 sources)
- `COMPONENT-TEMPLATE-FORMATS-RESEARCH.md` — Component template formats (renamed from corrupted path per commit ee361d8)
- `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` — Framework structures (70 sources)
- `SPX-COMPILER-CONSTRUCTION-RESEARCH.md` — Compiler construction (CIT 316 + online)
- `COMPLETE-ANALYSIS-RECORD.md` — Master analysis record
- `.kilo/plans/1792466263304-research-first-lms-backend-plan.md` — LMS build plan

### 9.12 Git, PR, and Review History

**Minimum reading set:**
- This document (§6) — Current Git/PR/CI status
- `AGENTS.md` §6–§7 — LMS build order and pending decisions

**Expanded reading set:**
- PR #368 diff (AGENTS.md reconciliation)
- PR #369 diff (Progress persistence + concurrency)
- `FRESH-SESSION-PROMPTS.md` — Agent tasking prompts
- `DGLab-FRESH-SESSION-HANDOFF.md` — Master handoff

---

## 10. Source-of-Truth Hierarchy

Based on `AGENTS.md` §2, the following hierarchy applies:

| Priority | Source | Notes |
|----------|--------|-------|
| 1 | Explicit, current owner decisions | Highest authority |
| 2 | Merged repository records and frozen contracts | `FROZEN-CONTRACTS.md` is immutable without ADR |
| 3 | Approved ADRs and current architecture documentation | ADR status must be "Accepted" |
| 4 | Current source code, tests, CI configuration, and actual CI runs | Code demonstrates behavior; does not establish approved intent |
| 5 | Open PRs and unmerged branches | Clearly labeled as proposed |
| 6 | Agent reports and chat summaries | Reported until independently verified |

**Important caveats:**
- Code can demonstrate current behavior without establishing approved product intent.
- An ADR or design document can be stale even when it was once approved (e.g., ADR-007 superseded by ADR-013).
- A successful CI run is evidence, not proof of correctness (especially for concurrency guarantees).
- Research documents are proposals, not decisions.

**Unresolved precedence conflicts:**
- `AGENTS.md` references an obsolete baseline (`ae410ea`) and describes itself as a draft (PR #368 addresses this, unmerged).
- Research agenda exists in repo root but not in canonical `Architecture/CrossCutting/` location.
- Some reported items (AuthMiddleware, findings register, formatting artifact) were not found in the repository.

---

## 11. Freshness and Maintenance

### Time-Sensitive Status (Refresh Every Session)

- Git HEAD and working-tree state
- Branch and PR heads (especially #368, #369)
- Latest CI run results and pass/fail counts
- Active task ownership and blockers
- Whether historical blockers have been resolved

### Durable Context (Changes Infrequently)

- Architecture principles and boundaries
- Frozen contracts and ADRs
- Source code structure and interfaces
- Research findings and analysis

### What Must Be Refreshed

Every new Claude session must re-verify:
1. `git status --short --branch`
2. `git rev-parse HEAD` vs `git rev-parse origin/main`
3. `git log -3 --oneline`
4. Relevant PR heads (fetch + rev-parse)
5. Latest CI run status
6. Whether reported blockers have been resolved
7. Whether untracked files or stash entries exist

---

## 12. Ready-to-Paste Claude Opening Prompt

```
You are working in the DGLab repository at /home/dgci/www/DGLab.

BEFORE ANY WORK:
1. Establish current Git state (read-only, no destructive operations):
   - git status --short --branch
   - git rev-parse HEAD && git rev-parse origin/main
   - git log -5 --oneline --decorate
   - git branch -a && git worktree list && git stash list
2. Read AGENTS.md (root) — the active repository instructions.
3. Read CLAUDE-INDEX.md (root) — this navigation document.
4. Read applicable nested instructions (check subdirectories for AGENTS.md or tool rules).
5. Follow the minimum reading set for the requested task (see CLAUDE-INDEX.md §9).
6. Independently verify relevant implementation and CI claims against source code and tests.
7. Separate facts (VERIFIED), assumptions (UNVERIFIED), proposals (PROPOSED), and unresolved decisions (OPEN).
8. Respect the founder's authority over scope, acceptance, and merges.
9. Do not begin parallel work that conflicts with Kilo's active implementation.
10. Identify shared-interface risks before editing any code.
11. Ask no more than two focused questions at a time.
12. Do not merge, reset, rebase shared history, or perform destructive actions without explicit permission.
13. Report your understanding of the task and proposed plan before proceeding when authorization or architectural scope is ambiguous.

GOVERNANCE REMINDERS:
- Single implementer, multiple independent reviewers (SDLC v5.1).
- CI-green is evidence, not proof of correctness.
- Research documents are proposals, not decisions.
- Code demonstrates behavior; does not establish approved product intent.
- The founder controls acceptance and merging.

CURRENT STATE (verified 2026-10-22):
- Main: 8858e165a76db7b14650298ca17b2265da1e4115
- PR #368 (AGENTS.md): c1baea7 (open, unapproved)
- PR #369 (Progress): f985a18 (open, unapproved, CI FAILED — CourseStatus namespace error in test)
- LMS: Enrollment done, Progress on branch, HTTP layer not started
- Auth transport (ADR-022): NOT YET WRITTEN
- MySQL test contract: NOT DEFINED
- Research gate: NOT CLOSED (publication not authorized)

MY TASK: [describe your task here]

Please confirm your understanding of the repository state and proposed approach before proceeding.
```

---

## Optional `CLAUDE.md` Recommendation

A root `CLAUDE.md` file would materially improve context loading for Claude Code sessions by automatically loading instructions without manual pasting. It should be **minimal** and reference the detailed documents:

**Proposed `CLAUDE.md` content (for founder review):**
```markdown
# DGLab — Claude Code Instructions

Read AGENTS.md and CLAUDE-INDEX.md at the start of every session.

Follow the source-of-truth hierarchy in CLAUDE-INDEX.md §10.
Verify Git state before relying on any reported facts.
The founder controls scope, acceptance, and merges.
Do not modify AGENTS.md, frozen contracts, or governance files without explicit authorization.
```

**Why it would help:** Automatic context loading eliminates the need to paste the opening prompt manually. It ensures Claude always starts with governance awareness.

**Potential conflicts:** Must not duplicate AGENTS.md content. Should reference, not replace, CLAUDE-INDEX.md. Must be kept minimal to avoid instruction bloat.

**Recommendation:** Create a minimal `CLAUDE.md` (4-5 lines) that points to `AGENTS.md` and `CLAUDE-INDEX.md`. This is a separate authorization decision for the founder.

---

## Completion Report

1. **Deliverable:** `CLAUDE-INDEX.md` at repository root. File remains local (not committed). Size: ~25KB.
2. **Repository baseline:** `main` at `8858e165a76db7b14650298ca17b2265da1e4115`.
3. **Sources inspected:** AGENTS.md, Architecture/FROZEN-CONTRACTS.md, Architecture/INDEX.md, Architecture/STRUCTURE-01-Wheel.md, Architecture/ADRs/ (21 files), .github/workflows/packages-ci.yml, packages/spoke/internal/lms/ (full structure), tests, migrations, research documents (9 files), git history and branches, PR branches #368 and #369.
4. **PR verification:**
   - PR #368: Remote HEAD `c1baea799ecf48064f91e8a478d183de41a2109a` (VERIFIED)
   - PR #369: Remote HEAD `f985a180e8866e6d1de424eb7d05ceebcc035ad9` (VERIFIED). Latest CI FAILED (Run #39744074200, CourseStatus namespace error in test). Fix committed locally (unstaged) in worktree `/tmp/wt-progress-3`.
    - Latest CI on main: Run #39744759924 — SUCCESS (22 jobs, all passed). Note: this run is on `main`, not on the PR #369 branch. The PR branch's latest run (#39744074200) FAILED.
5. **Documentation conflicts:**
- AGENTS.md describes itself as a draft and references obsolete baseline `ae410ea` (PR #368 addresses this, unmerged)
- Research agenda exists in repo root, not canonical location (authorization pending)
- Some reported items (AuthMiddleware, findings register, formatting artifact) were not found in the repository
- `CLAUDE-INDEX(Stale!).md` exists as untracked draft (superseded by this document)
- Research report count is inconsistent across documents (CLAUDE-INDEX says 9 root files, RESEARCH-INDEX says 14 inventory rows with one duplicate; actual file count is 9). RESEARCH-INDEX.md should be reconciled.
6. **Outstanding risks:** All 18 items in §7 risk register, including 8 open decisions and 5 active blockers.
7. **Claude context-loading recommendation:** A minimal `CLAUDE.md` (4-5 lines) would materially improve context loading. See optional recommendation above.
8. **Opening prompt:** Included in §12 of this document.

**No code, ADR, frozen contract, or governance file was modified.** `CLAUDE-INDEX.md` remains local and uncommitted. The founder may commit it through their normal workflow.