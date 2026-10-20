# DGLab Research-First LMS Backend Plan

**Created:** 2026-10-20  
**Author:** Architect (Kilo)  
**Status:** Updated 2026-10-20 post-git-pull — Research gate still blocks LMS implementation; Enrollment persistence DONE  
**Baseline:** `origin/main` at `a18a3e86ae6249ab58a39c07ca375bc0852a69f5` (verified current HEAD)  
**Previous baseline:** `ae410ea09fcd7a4c0c2c77cc980ff7d3e4984ac3` (historical, superseded)

---

## Executive Summary

This plan synthesizes the codebase analysis, three handoff documents (`AGENTS.md`, `DGLab-FRESH-SESSION-HANDOFF.md`, `FRESH-SESSION-PROMPTS.md`), and the `RESEARCH-AGENDA.md` into a single execution sequence. The critical constraint: **research must complete before any new LMS implementation increments** (AGENTS.md §5, Handoff §3).

**Critical update (2026-10-20, post git pull):** PR #364's enrollment persistence work was **merged via PR #366** into `main` at commit `a18a3e8`. Enrollment repository + application service are **complete with unit tests**. The remaining LMS backend gaps are: **Progress persistence**, **HTTP/API layer**, **Auth middleware wiring**, **MySQL in CI**, and **real-MySQL integration tests**.

The plan has two phases:
1. **Research Gate** — Complete 5 research tracks + SuperPHP naming, produce reconciliation artifacts, obtain owner approval
2. **LMS Backend** — 21-step build order across 5 gates (enrollment steps removed), delivering complete backend with real-MySQL integration tests and E2E flow

---

## Phase 1: Research Gate (Blocks All LMS Implementation)

### 1.1 Required Deliverables (Per Handoff §3.7, AGENTS.md §5.6)

| Deliverable | Status | Owner Action Required |
|-------------|--------|----------------------|
| Track 1 report: Platform integrity/copyright/fair disputes | **In RESEARCH-AGENDA.md** (Track 1) | Review hypotheses & deliverables |
| Track 2 report: Avoiding YouTube model | **In RESEARCH-AGENDA.md** (Track 2) | Review hypotheses & deliverables |
| Track 3 report: Half Exceptions (2 meanings) | **In RESEARCH-AGENDA.md** (Track 3) | Review terminology note & taxonomy |
| Track 4 report: Cancel-Anytime paradox | **In RESEARCH-AGENDA.md** (Track 4) | Verify owner sentence preserved verbatim |
| Track 5 report: Connecting principles | **In RESEARCH-AGENDA.md** (Track 5) | Add missing variants 2 & 3 side-by-side |
| SPX/SuperPHP naming investigation | **In RESEARCH-AGENDA.md** (SuperPHP section) | Extract to standalone report |
| V1–V7 reconciliation matrix | **MISSING** — must create | **Required before agenda publication** |
| Proposed canonical agenda | **RESEARCH-AGENDA.md is draft** | Authorize move to `Architecture/CrossCutting/` |
| Source/uncertainty register | Partial in each track | Consolidate into single index |

### 1.2 Critical Gaps to Close Before Research Gate Passes

| Gap | Location | Action |
|-----|----------|--------|
| **V1–V7 reconciliation matrix** | Not in repo | Reconstruct from handoff docs + SAAI briefing + RESEARCH-AGENDA.md |
| **Connecting-theme variants 2 & 3** | Only variant 1 in Track 5 | Add side-by-side comparison with differences explained |
| **Four Half Exceptions formulations** | Track 3 covers 2 meanings | Explicitly enumerate 4 formulations per Prompt 1 |
| **SuperPHP placement divergence** | Documented in SuperPHP section | Add to reconciliation matrix |
| **Standalone SPX report** | Embedded in agenda | Extract SuperPHP section to separate document |
| **Owner authorization to publish** | File in repo root, not canonical | Explicit approval needed for `Architecture/CrossCutting/RESEARCH-AGENDA.md` |

### 1.3 Research Gate Acceptance Criteria

- [ ] All 5 track reports + SuperPHP report delivered with evidence tables
- [ ] V1–V7 reconciliation matrix complete with owner-confirmed/agent-interpreted/unresolved labels
- [ ] Three connecting-theme variants presented side-by-side with reconciliation recommendation
- [ ] Standalone SPX naming/collision report with verification status labels
- [ ] Research index linking all reports, separating evidence from recommendations
- [ ] Owner approves canonical agenda wording and authorizes publication
- [ ] No research deliverables mixed into LMS implementation PRs

---

## Phase 2: LMS Backend — 25 Steps Across 5 Gates

### Gate 0: Current-State Review (Steps 1–3)

| Step | Action | Evidence Required | Non-Goals |
|------|--------|-------------------|-----------|
| 1 | Re-review current `origin/main` (HEAD `a18a3e8`), open PRs, package boundaries, migrations, tests, frozen contracts, ADRs, CI | Verified file paths, commit SHAs, CI configs | No implementation |
| 2 | Review merged PR #366 (enrollment persistence) — verify implementation vs. original PR #364 scope; decide if course discovery still needed separately | `MySQLEnrollmentRepository.php`, `EnrollmentApplicationService.php`, unit tests | No duplicate implementation |
| 3 | Reconfirm LMS capability matrix (4 evidence levels) | Matrix with implemented/unit/integration/E2E columns | No new code |

**Dependencies:** Current `origin/main` SHA verified; merged PR #366 code in tree

### Gate 1: API/Security/Test Contracts (Steps 4–6) — **Owner Decisions Required**

| Step | Decision | Options | Owner Input |
|------|----------|---------|-------------|
| 4 | Auth transport + same-origin vs cross-origin | Session cookies / JWT bearer / both | **Required** — creates ADR-022 |
| 5 | API versioning, roles/permissions, error envelope, pagination, validation, transactions | Per AGENTS.md §5.3 list | **Required** |
| 6 | MySQL integration-test contract: DSN, migration runner, isolation, CI service | `TEST_MYSQL_DSN`, runner, reset strategy | **Required** |

**Blockers:** None of these are settled. ADR-003 (ES256 JWT), ADR-008 (Argon2id), ADR-012 (PQ JWT) exist but transport is undecided.

### Gate 2: Domain/Persistence/Application (Steps 7–10) — **Enrollment DONE (merged PR #366)**

| Step | Deliverable | Files/Interfaces | Tests |
|------|-------------|------------------|-------|
| 7 | **Progress repository port** + MySQL implementation + migration review | `ProgressRepositoryInterface`, `MySQLProgressRepository.php`, migration `004` | Integration tests vs real MySQL |
| 8 | **Progress application service** | `ProgressApplicationService.php` | Service tests |
| 9 | Course/module/detail read models (no PR #364 dup) | Read models, use cases | Unit + integration |
| 10 | Verify transactions, uniqueness, FKs, ordering, concurrency, ownership | Migration review, constraint tests | Real-DB constraint tests |

**Dependencies:** Gate 1 decisions (MySQL test contract); Enrollment persistence **already complete** in `main`

**Note:** Steps 7–8 from previous version (Enrollment repository + service) **REMOVED** — delivered in PR #366 merge `a18a3e8`.

### Gate 3: HTTP/API (Steps 13–22)

| Step | Deliverable | Files | Tests |
|------|-------------|-------|-------|
| 13 | Composition-root + DI registration | `ApplicationFactory.php` updates | Container wiring tests |
| 14 | JSON parsing/serialization, DTOs, validation, error taxonomy | DTO classes, validator, error responses | Contract tests |
| 15 | Login/logout/session/token endpoints + middleware | Auth controllers, middleware wiring per ADR-022 | Auth flow tests |
| 16 | Public course discovery/detail routes | Controllers, routes | API contract tests |
| 17 | Admin course/module CRUD + publish routes + role checks | Admin controllers, authorization | Role-based tests |
| 18 | Enrollment routes + learner/course access rules | Enrollment controllers | Access control tests |
| 19 | Progress routes + enrollment/content authorization | Progress controllers | Authorization tests |
| 20 | API contract tests + OpenAPI documentation | OpenAPI spec, contract tests | Full API test suite |
| 21 | **Gate 3 review** | All HTTP layer code | Independent review |

**Dependencies:** Gate 1 auth decision, Gate 2 services complete (Progress + read models)

### Gate 4: Real Verification (Steps 22–25)

| Step | Deliverable | Evidence |
|------|-------------|----------|
| 22 | MySQL 8 CI service + isolated test DB | `.github/workflows/packages-ci.yml` updated |
| 23 | Real-MySQL integration tests for every repository + transaction boundary | CI green with real DB |
| 24 | E2E journey: admin→create→publish→learner→enroll→progress | Documented + automated test |
| 25 | Negative tests: unauth, unauthorized, cross-user, unpublished, invalid, duplicate, missing, malformed, DB failure, pagination, CSRF/CORS | Full negative test matrix |

**Dependencies:** Gate 3 complete, CI MySQL service operational

### Gate 5: Founder Handoff (Steps 26–27)

| Step | Deliverable |
|------|-------------|
| 26 | Commit SHA, capability matrix, setup guide, env vars, migrations, API spec, curl examples, auth instructions, error catalogue, test commands, known limitations, extension points |
| 27 | Claude independent review with skeptical prompt (per Handoff §7) |

---

## Pending Decisions Register (Must Resolve Before Gates 1–2)

| ID | Decision | Blocks | Status | Owner |
|----|----------|--------|--------|-------|
| **ADR-022** | Auth transport + authorization | Gate 1, all HTTP work | **Open** | Owner |
| **MySQL Test Contract** | DSN, runner, isolation, CI service | Gate 1, all real-DB tests | **Open** | Owner |
| **PR #364/366** | Enrollment persistence merged via PR #366; verify course discovery scope | Gate 0 step 2 | **Merged** — verify only | Owner |
| **PR #363** | Separate/merge formatting audit + ELQ gitlink | CI hygiene | **Open** | Owner |
| **D-01 / S-003** | Re-scope closure or commission `PulseFiberIsolationTest.php` | SDLC governance | **Open** | Owner |
| **D-09** | PSR-4 collision vs findings register vs ADR-011 | ADR-011 priority | **Open** | Owner |
| **Research Agenda** | Authorize `Architecture/CrossCutting/RESEARCH-AGENDA.md` | Research gate closure | **Open** | Owner |
| **SKILL.md provenance** | `82ddde7` local-only change authorization | Governance | **Open** | Owner |
| **Formatting audit** | Artifact recovery, readability verdict, Phase C auth | CI policy | **Open** | Owner |
| **PR #310** | Resume or leave parked CORE-15 cache | Core tier | **Open** | Owner |
| **Stale git credential** | Revoke/rotate, remove safely | Security hygiene | **Open** | Owner |

---

## Architecture Constraints (Non-Negotiable)

| Constraint | Source | Enforcement |
|------------|--------|-------------|
| Core/Hub/Spoke/Wheel 6-ring (4 layers + 2 checkpoints) | `STRUCTURE-01-Wheel.md` v0.4 | Architecture boundary lint |
| Compose downward, abstract upward | AGENTS.md §3 | Code review |
| Hub owns domain; persistence via ports | AGENTS.md §3 | Interface placement |
| Frozen contracts immutable without ADR | `FROZEN-CONTRACTS.md` | Lint check 1 |
| ADR-013 MySQL primary (supersedes ADR-007) | ADR-013 | Migration dialect |
| No JS framework in backend handoff | AGENTS.md §3 | Scope control |
| SuperPHP not LMS runtime dependency | AGENTS.md §3 | Composer deps |
| Single implementer, multiple reviewers | AGENTS.md §4 / SDLC v5.1 | PR process |
| Parallel implementation prohibited by default | AGENTS.md §4 | Task authorization |

---

## Capability Matrix (Current Verified State at `a18a3e8`)

| Component | Implemented | Unit-Tested | MySQL Integration | E2E Demo |
|-----------|-------------|-------------|-------------------|----------|
| Core (20) | ✅ | ✅ | N/A | N/A |
| Hub (6+) | ✅ | ✅ | N/A | N/A |
| Bridge (Vanguard) | ✅ | ✅ | N/A | N/A |
| Codex/Canvas/Showcase | ✅ | ✅ | N/A | N/A |
| **LMS Domain/Application** | ✅ | ✅ | ❌ | ❌ |
| **LMS Course/Module Persistence** | ✅ | ✅ (mock) | ❌ | ❌ |
| **LMS Enrollment Persistence** | ✅ | ✅ | ❌ | ❌ |
| **LMS Enrollment Application Service** | ✅ | ✅ | ❌ | ❌ |
| **LMS Progress Persistence** | ❌ | ❌ | ❌ | ❌ |
| **LMS Progress Application Service** | ❌ | ❌ | ❌ | ❌ |
| **LMS HTTP Routes/Controllers** | ❌ | ❌ | ❌ | ❌ |
| **Auth Middleware Wired** | ❌ (exists in Hub) | ❌ | ❌ | ❌ |
| **Real MySQL in CI** | ❌ | ❌ | ❌ | ❌ |
| **Complete Learner/Admin Flow** | ❌ | ❌ | ❌ | ❌ |

---

## Next Single Action (Architect Recommendation)

**Do not start LMS implementation.** The research gate is not passed.

**Next action:** Assign research reconciliation tasks to complete the V1–V7 matrix, add missing connecting-theme variants, extract SPX report, and obtain owner authorization for the canonical agenda. This is read-only work that can proceed in parallel with PR #363 review and Gate 0 verification of merged PR #366.

**After research gate passes:** Proceed to **Gate 0 Step 1** with current `origin/main` verification at `a18a3e8`, then **Gate 1** for the two critical owner decisions (ADR-022 auth transport, MySQL test contract). Once Gate 1 decisions are made, **Gate 2 Step 7 (Progress repository)** is the next implementation increment.

---

## Plan File Location

`/home/dgci/www/DGLab/.kilo/plans/1792466263304-research-first-lms-backend-plan.md`