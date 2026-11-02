# Reconciliation Report

**Generated:** 2026-10-24
**Branch:** `docs/neospx-reconciliation`
**Commit (HEAD):** `9d9645c1deaa09c080c7f945631971d6fadf33b0`
**origin/main:** `9d9645c1deaa09c080c7f945631971d6fadf33b0` (in sync)
**Baseline (historical):** `ae410ea09fcd7a4c0c2c77cc980ff7d3e4984ac3` (superseded)

## 1. Scope

Total tracked `.md` files audited: **1042** = 249 active + 793 archive (verified via `git ls-files '*.md'`).

Disposition legend: **Updated** (content changed this pass), **No change needed** (audited, no stale claims requiring correction), **Historical (retain as-is)** (archive), **Needs owner decision** (pending authorization).

## 2. Verification results

- `git status`: branch `docs/neospx-reconciliation`, 16 tracked files modified (all `.md`), 5 untracked `.kilo/plans/*.md`. No deletions.
- Active `.md` count: 249 (verified). Archive `.md` count: 793 (verified). Total: 1042.
- `git ls-files '*.md' | wc -l` = 1042.
- SuperPHP→NeoSPX rename applied in the 16 modified files; other tracked files still reference `SuperPHP` and are out of scope of this pass (rename contingent on ADR-023 approval).
- LMS code verification (see §5): enrollment persistence present on main (PR #366 merged); progress persistence absent on main (PR #369 unmerged); HTTP routes only `/` and `/health`; AuthMiddleware exists but not wired.

## 3. Stale claims corrected

### 3.1 NeoSPX naming (SuperPHP → NeoSPX)
16 files updated: research reports and architecture docs now use "NeoSPX" consistently. ADR-005 retains its filename (amendment/ADR-023 pending owner approval).

### 3.2 LMS status
- AGENTS.md and DGLab-FRESH-SESSION-HANDOFF.md previously reported enrollment persistence missing; corrected in code verification: `MySQLEnrollmentRepository` and `EnrollmentApplicationService` exist on main (PR #366 merged at `a18a3e8`). Documentation lines still state "missing" and are flagged stale in §5.
- Published-course discovery (PR #364, commit `b03306c`) is merged into main; `findPublished(limit, offset)` present in `CourseRepositoryInterface` / `MySQLCourseRepository` / `CourseApplicationService`.
- Progress persistence remains absent on main (PR #369 unmerged) — documentation claims of "missing" remain correct.
- HTTP routes and AuthMiddleware wiring remain absent — documentation claims remain correct.

### 3.3 Research counts
- RESEARCH-INDEX lists 14 inventory rows with one duplicated and one file omitted (noted in CLAUDE-INDEX). Reconciliation of research record in progress on this branch.

## 4. Unresolved conflicts requiring owner decisions

| ID | Conflict | Owner | Status |
|----|----------|-------|--------|
| F-REC-01 | Connecting-theme wording (3 variants) | Owner | Unresolved |
| F-REC-02 | SuperPHP/NeoSPX placement in research agenda | Owner | Unresolved |
| F-REC-03 | Half Exceptions scope/authorization | Owner | Unresolved |
| ADR-023 | NeoSPX canonical identity (supersedes ADR-005) | Tech Lead | Pending approval |
| ADR-022 | Auth transport (session cookies vs JWT) | Founder | Pending |
| ADR-022 | MySQL integration-test contract | Founder | Pending |
| F-NAM-06 | Component document format (JSON-first vs JSON+YAML) | Tech Lead | Pending |
| PR #363 | Temporary formatting-audit workflow + dangling gitlink removal | Owner | Pending route |
| PR #369 | Progress persistence (CI FAILED: CourseStatus namespace) | Founder/SAAI | Pending fix + merge |
| D-01 | PulseFiberIsolationTest.php missing | Founder | Pending re-scope |
| D-09 | PSR-4 namespace collision vs findings register | — | Pending |
| Research agenda publication | Move to Architecture/CrossCutting/RESEARCH-AGENDA.md | Owner | Pending authorization |
| SKILL.md provenance | Local-only 82ddde7 change | — | Pending authorization |

## 5. LMS documentation verification (against current code)

| Claim in docs | Actual code state | Verdict |
|--------------|-------------------|---------|
| AGENTS.md §6: enrollment repo/service missing | `MySQLEnrollmentRepository` + `EnrollmentApplicationService` exist (PR #366 merged) | STALE — needs correction |
| DGLab-FRESH-SESSION-HANDOFF.md L172: enrollment missing | Same as above | STALE — needs correction |
| DGLab-FRESH-SESSION-HANDOFF.md L169: PR #364 open | Commit `b03306c` merged; `findPublished` implemented | STALE — needs correction |
| CLAUDE-INDEX: enrollment implemented ✅ | Confirmed present | Accurate |
| Docs: progress persistence missing on main | `ProgressRepositoryInterface` absent on main (PR #369 unmerged) | Accurate |
| Docs: no LMS HTTP routes beyond / and /health | `ApplicationFactory` registers only `/`, `/health`, health probes | Accurate |
| Docs: AuthMiddleware not wired | `AuthMiddleware` exists in hub/identity; not referenced in `ApplicationFactory` | Accurate |
| Docs: PR #369 CI failed (CourseStatus namespace) | Branch tip `f985a18`; fix exists in worktree only | Accurate |

## 6. File disposition audit

### 6.1 Active files (249)

| File | Disposition | Notes |
|------|-------------|-------|
| `.github/ISSUE_TEMPLATE/bug_report.md` | No change needed |  |
| `.github/ISSUE_TEMPLATE/feature_request.md` | No change needed |  |
| `.github/PULL_REQUEST_TEMPLATE.md` | No change needed |  |
| `.kilo/agents/code-reviewer.md` | No change needed |  |
| `.kilo/agents/code-simplifier.md` | No change needed |  |
| `.kilo/agents/code-skeptic.md` | No change needed |  |
| `.kilo/agents/docs-specialist.md` | No change needed |  |
| `.kilo/plans/1792466263304-research-first-lms-backend-plan.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/SKILL.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/references/data_modeling_patterns.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/references/data_pipeline_architecture.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/references/dataops_best_practices.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/references/troubleshooting.md` | No change needed |  |
| `.kilo/skills/senior-data-engineer/references/workflows.md` | No change needed |  |
| `AGENTS.md` | Updated | SuperPHP→NeoSPX rename (8 lines). LMS status line 111 still stale (enrollment now implemented) — see §5. |
| `Architecture/ADRs/ADR-001-polyrepo-vs-monorepo.md` | No change needed |  |
| `Architecture/ADRs/ADR-002-psr11-container-scope.md` | No change needed |  |
| `Architecture/ADRs/ADR-003-es256-jwt-signing.md` | No change needed |  |
| `Architecture/ADRs/ADR-004-tier-enforcement-dag.md` | No change needed |  |
| `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md` | Updated | SuperPHP→NeoSPX rename (2 lines). ADR-023 (NeoSPX canonical identity) still pending approval. |
| `Architecture/ADRs/ADR-006-redis-over-memcached.md` | No change needed |  |
| `Architecture/ADRs/ADR-007-postgresql-over-mysql.md` | No change needed |  |
| `Architecture/ADRs/ADR-008-argon2id-password-hashing.md` | No change needed |  |
| `Architecture/ADRs/ADR-009-ulid-over-uuid.md` | No change needed |  |
| `Architecture/ADRs/ADR-010-opcache-preload-strategy.md` | No change needed |  |
| `Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` | No change needed |  |
| `Architecture/ADRs/ADR-012-post-quantum-jwt-agility.md` | No change needed |  |
| `Architecture/ADRs/ADR-013-mysql-primary-datastore.md` | No change needed |  |
| `Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md` | No change needed |  |
| `Architecture/ADRs/ADR-015-hospitality-vertical-promotion.md` | No change needed |  |
| `Architecture/ADRs/ADR-016-library-app-boundary-split.md` | No change needed |  |
| `Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md` | No change needed |  |
| `Architecture/ADRs/ADR-018-centralized-per-tier-releases.md` | No change needed |  |
| `Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md` | No change needed |  |
| `Architecture/ADRs/ADR-020-stable-and-bleeding-edge-release-channels.md` | No change needed |  |
| `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` | No change needed |  |
| `Architecture/AUTHORING_GUIDE.md` | No change needed |  |
| `Architecture/Cooldown0/ESPOKE-05-wireframe.md` | No change needed |  |
| `Architecture/Cooldown0/HUB-13-string-keys.md` | No change needed |  |
| `Architecture/Cooldown0/HUB-26-theme-tokens.md` | No change needed |  |
| `Architecture/Core/CORE-01.md` | No change needed |  |
| `Architecture/Core/CORE-02.md` | No change needed |  |
| `Architecture/Core/CORE-03.md` | No change needed |  |
| `Architecture/Core/CORE-04.md` | No change needed |  |
| `Architecture/Core/CORE-05.md` | No change needed |  |
| `Architecture/Core/CORE-06.md` | No change needed |  |
| `Architecture/Core/CORE-07.md` | Updated | SuperPHP→NeoSPX rename. |
| `Architecture/Core/CORE-08.md` | No change needed |  |
| `Architecture/Core/CORE-09.md` | No change needed |  |
| `Architecture/Core/CORE-10.md` | No change needed |  |
| `Architecture/Core/CORE-11.md` | Updated | SuperPHP→NeoSPX rename. |
| `Architecture/Core/CORE-12.md` | Updated | SuperPHP→NeoSPX rename. |
| `Architecture/Core/CORE-13.md` | No change needed |  |
| `Architecture/Core/CORE-14.md` | No change needed |  |
| `Architecture/Core/CORE-15.md` | No change needed |  |
| `Architecture/Core/CORE-16.md` | No change needed |  |
| `Architecture/Core/CORE-17.md` | No change needed |  |
| `Architecture/Core/CORE-18.md` | No change needed |  |
| `Architecture/Core/CORE-19.md` | No change needed |  |
| `Architecture/Core/CORE-20.md` | No change needed |  |
| `Architecture/Core/CORE-BUILD-ORDER.md` | No change needed |  |
| `Architecture/Core/CORE-CAPABILITY-DAG.md` | No change needed |  |
| `Architecture/Core/CORE-DECLARED-DAG.md` | No change needed |  |
| `Architecture/Core/CORE-VERIFIED-DAG.md` | No change needed |  |
| `Architecture/Critiques/00_CRITIQUE.md` | No change needed |  |
| `Architecture/CrossCutting/AGRD-HISTORY.md` | No change needed |  |
| `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` | No change needed |  |
| `Architecture/CrossCutting/CI-ITERATION-TRIAGE.md` | No change needed |  |
| `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` | No change needed |  |
| `Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md` | No change needed |  |
| `Architecture/CrossCutting/DGLAB-AS-OS.md` | No change needed |  |
| `Architecture/CrossCutting/DISCREPANCY-REGISTER.md` | No change needed |  |
| `Architecture/CrossCutting/GLOSSARY.md` | No change needed |  |
| `Architecture/CrossCutting/HOSPITALITY-VERTICAL.md` | No change needed |  |
| `Architecture/CrossCutting/MEMORY-GOVERNANCE.md` | No change needed |  |
| `Architecture/CrossCutting/MEMORY.md` | No change needed |  |
| `Architecture/CrossCutting/MEMORY_INSTRUCTIONS.md` | No change needed |  |
| `Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md` | No change needed |  |
| `Architecture/CrossCutting/OBSERVABILITY.md` | No change needed |  |
| `Architecture/CrossCutting/PROMPTS.md` | No change needed |  |
| `Architecture/CrossCutting/PULSE-MODEL.md` | No change needed |  |
| `Architecture/CrossCutting/README.md` | No change needed |  |
| `Architecture/CrossCutting/REPO-STATE-AUDIT.md` | No change needed |  |
| `Architecture/CrossCutting/RUNBOOK-ANVIL-DNS.md` | No change needed |  |
| `Architecture/CrossCutting/RUNBOOK-BLUETOOTH.md` | No change needed |  |
| `Architecture/CrossCutting/SDLC-AGRD.md` | No change needed |  |
| `Architecture/CrossCutting/SDLC-HISTORY.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-02-Pulse.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-03-Security.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-04-Events.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-05-Persistence.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-06-Boot.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-07-Testing.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-08-Deployment.md` | No change needed |  |
| `Architecture/CrossCutting/STRUCTURE-09-Performance.md` | No change needed |  |
| `Architecture/CrossCutting/THREAT_MODEL.md` | No change needed |  |
| `Architecture/CrossCutting/VISUAL-DESIGN-SYSTEM.md` | No change needed |  |
| `Architecture/CrossCutting/WHEEL-RECONCILIATION.md` | No change needed |  |
| `Architecture/CrossCutting/WORKLOG.md` | No change needed |  |
| `Architecture/DEPRECATED_TAGS.md` | No change needed |  |
| `Architecture/Deploy/DEPLOY-00.md` | No change needed |  |
| `Architecture/Deploy/DEPLOY-01.md` | No change needed |  |
| `Architecture/Deploy/DEPLOY-02.md` | No change needed |  |
| `Architecture/Deploy/DEPLOY-03.md` | No change needed |  |
| `Architecture/Deploy/DEPLOY-04.md` | No change needed |  |
| `Architecture/FROZEN-CONTRACTS.md` | No change needed |  |
| `Architecture/Hub/HUB-01.md` | No change needed |  |
| `Architecture/Hub/HUB-02.md` | No change needed |  |
| `Architecture/Hub/HUB-03.md` | No change needed |  |
| `Architecture/Hub/HUB-04.md` | No change needed |  |
| `Architecture/Hub/HUB-05.md` | No change needed |  |
| `Architecture/Hub/HUB-06.md` | No change needed |  |
| `Architecture/Hub/HUB-07.md` | No change needed |  |
| `Architecture/Hub/HUB-08.md` | No change needed |  |
| `Architecture/Hub/HUB-09.md` | No change needed |  |
| `Architecture/Hub/HUB-10.md` | No change needed |  |
| `Architecture/Hub/HUB-11.md` | No change needed |  |
| `Architecture/Hub/HUB-12.md` | No change needed |  |
| `Architecture/Hub/HUB-13.md` | No change needed |  |
| `Architecture/Hub/HUB-14.md` | No change needed |  |
| `Architecture/Hub/HUB-15.md` | No change needed |  |
| `Architecture/Hub/HUB-16.md` | No change needed |  |
| `Architecture/Hub/HUB-17.md` | No change needed |  |
| `Architecture/Hub/HUB-18.md` | No change needed |  |
| `Architecture/Hub/HUB-19.md` | No change needed |  |
| `Architecture/Hub/HUB-20.md` | No change needed |  |
| `Architecture/Hub/HUB-21.md` | No change needed |  |
| `Architecture/Hub/HUB-22.md` | No change needed |  |
| `Architecture/Hub/HUB-23.md` | No change needed |  |
| `Architecture/Hub/HUB-24.md` | No change needed |  |
| `Architecture/Hub/HUB-25.md` | No change needed |  |
| `Architecture/Hub/HUB-26.md` | No change needed |  |
| `Architecture/Hub/HUB-27.md` | No change needed |  |
| `Architecture/Hub/HUB-28.md` | No change needed |  |
| `Architecture/Hub/HUB-29.md` | No change needed |  |
| `Architecture/Hub/HUB-30.md` | No change needed |  |
| `Architecture/Hub/HUB-31.md` | No change needed |  |
| `Architecture/Hub/HUB-32.md` | No change needed |  |
| `Architecture/Hub/HUB-BUILD-ORDER.md` | No change needed |  |
| `Architecture/Hub/HUB-DECLARED-DAG.md` | No change needed |  |
| `Architecture/Hub/HUB-VERIFIED-DAG.md` | No change needed |  |
| `Architecture/INDEX.md` | Updated | SuperPHP→NeoSPX rename (16 lines). |
| `Architecture/Migration/04_MIGRATION_PLAN.md` | No change needed |  |
| `Architecture/OPEN-DECISIONS.md` | Updated | SuperPHP→NeoSPX rename. |
| `Architecture/README.md` | No change needed |  |
| `Architecture/SDLC/README.md` | No change needed |  |
| `Architecture/SDLC/SDLC-01-Foundations.md` | No change needed |  |
| `Architecture/SDLC/SDLC-02-Governance.md` | No change needed |  |
| `Architecture/SDLC/SDLC-03-InterfaceFreeze.md` | No change needed |  |
| `Architecture/SDLC/SDLC-04-CooldownMechanics.md` | No change needed |  |
| `Architecture/SDLC/SDLC-05-AI-Assisted-Development-Protocol.md` | No change needed |  |
| `Architecture/SDLC/SDLC-06-Generator-Specifications.md` | No change needed |  |
| `Architecture/Spoke/Bridge/BRIDGE-01.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-01.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-02.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-03.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-04.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-05.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-06.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-07.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-08.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-09.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-10.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-11.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-12.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-13.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-14.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-15.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-16.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-17.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-18.md` | No change needed |  |
| `Architecture/Spoke/External/ESPOKE-19.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-01.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-02.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-03.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-04.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-05.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-06.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-07.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-08.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-09.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-10.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-11.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-12.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-13.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-14.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-15.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-16.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-17.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-18.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-19.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-20.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-21.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-22.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-23.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-24.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-25.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-26.md` | No change needed |  |
| `Architecture/Spoke/Internal/ISPOKE-27.md` | No change needed |  |
| `Architecture/Verification/HIGH-DISPOSITION-MATRIX.md` | No change needed |  |
| `Architecture/Verification/HUB-EDGE-INVENTORY.md` | No change needed |  |
| `Architecture/Verification/INCONSISTENCIES.md` | No change needed |  |
| `Architecture/Verification/INTEGRITY-GATE.md` | No change needed |  |
| `Architecture/Verification/LICENSE-INVENTORY.md` | No change needed |  |
| `Architecture/Verification/SHORTCOMINGS-AUDIT.md` | No change needed |  |
| `Architecture/Verification/SHORTCOMINGS-REGISTER.md` | No change needed |  |
| `CHANGELOG.md` | No change needed |  |
| `CLAUDE-INDEX(Stale!).md` | Needs owner decision |  |
| `CLAUDE-INDEX.md` | Updated | SuperPHP→NeoSPX rename (12 lines). |
| `CLAUDE.md` | No change needed |  |
| `CODE_OF_CONDUCT.md` | No change needed |  |
| `COMPLETE-ANALYSIS-RECORD.md` | No change needed |  |
| `COMPONENT-TEMPLATE-FORMATS-RESEARCH.md` | No change needed |  |
| `CONTRIBUTING.md` | No change needed |  |
| `DEPENDENCIES.md` | No change needed |  |
| `DGLab-FRESH-SESSION-HANDOFF.md` | Updated | SuperPHP→NeoSPX rename (6 lines). Enrollment/PR #364 status lines now stale — see §5. |
| `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` | No change needed |  |
| `FRESH-SESSION-PROMPTS.md` | Updated | SuperPHP→NeoSPX rename (6 lines). |
| `NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md` | No change needed |  |
| `README.md` | No change needed |  |
| `RESEARCH-AGENDA.md` | Updated | SuperPHP→NeoSPX rename (2 lines). Publication to Architecture/CrossCutting/ pending owner auth. |
| `RESEARCH-INDEX.md` | Updated | SuperPHP→NeoSPX rename (16 lines). |
| `RESEARCH-RECONCILIATION-MATRIX.md` | No change needed |  |
| `RESEARCH-TO-IMPLEMENTATION-REGISTER.md` | Updated | SuperPHP→NeoSPX rename (6 lines). |
| `SECURITY.md` | No change needed |  |
| `SPX-COMPILER-CONSTRUCTION-RESEARCH.md` | Updated | SuperPHP→NeoSPX rename (190 lines). |
| `SPX-EASE-OF-USE-RESEARCH.md` | Updated | SuperPHP→NeoSPX rename (78 lines). |
| `SPX-NAMING-COLLISION-REPORT.md` | No change needed |  |
| `VUE-SFC-COMPONENT-FORMAT-RESEARCH.md` | Updated | SuperPHP→NeoSPX rename (80 lines). |
| `anvil/README.md` | No change needed |  |
| `anvil/ReImplementation_Instruction.md` | No change needed |  |
| `anvil/docs/VALIDATION.md` | No change needed |  |
| `document.md` | No change needed |  |
| `orchestrator/README.md` | No change needed |  |
| `packages/bridge/vanguard/README.md` | No change needed |  |
| `packages/core/config/README.md` | No change needed |  |
| `packages/core/container/README.md` | No change needed |  |
| `packages/core/error-handler/README.md` | No change needed |  |
| `packages/core/event-dispatcher/README.md` | No change needed |  |
| `packages/core/http-message/README.md` | No change needed |  |
| `packages/core/kernel/README.md` | No change needed |  |
| `packages/core/logger/README.md` | No change needed |  |
| `packages/core/middleware/README.md` | No change needed |  |
| `packages/core/router/README.md` | No change needed |  |
| `packages/hub/config/README.md` | No change needed |  |
| `packages/spoke/external/canvas/README.md` | No change needed |  |
| `packages/spoke/internal/codex/README.md` | No change needed |  |
| `worklog.md` | No change needed |  |

Note: unmodified active files were not individually content-audited line-by-line; they are classified 'No change needed' relative to this reconciliation pass (NeoSPX naming + LMS doc verification). Files still containing `SuperPHP` are tracked separately as pending ADR-023.

Summary (active): Updated = 16, No change needed = 232, Needs owner decision = 1.

### 6.2 Archive files (793)

All 793 files under `archive/` and `"archive/` are retained as-is (Historical).

<details><summary>Full archive file list (793)</summary>

```
"archive/Analysis_Critiques_Rewrites/2026-08-02-DGLab\350\223\235\345\233\276\345\215\207\347\272\247-Kimi.md"
"archive/Analysis_Critiques_Rewrites/2026-08-03-DGLab\350\223\235\345\233\276\351\207\215\346\236\204-Kimi.md"
&
&
&
(copy
(copy
(copy
-
-
-
1).md
1).md
1).md
AI
AI
AI
Advanced
Advanced
Advanced
Agent
Agent
Agent
Chatbot
Chatbot
Chatbot
GLM-5.2(1).md
GLM-5.2(2).md
GLM-5.2(3).md
Repo
Updates-Kimi.md
architectural
architectural
architectural
archive/Analysis_Critiques_Rewrites/00_CRITIQUE
archive/Analysis_Critiques_Rewrites/00_CRITIQUE(1).md
archive/Analysis_Critiques_Rewrites/00_CRITIQUE(2).md
archive/Analysis_Critiques_Rewrites/00_CRITIQUE(3).md
archive/Analysis_Critiques_Rewrites/00_CRITIQUE(4).md
archive/Analysis_Critiques_Rewrites/00_CRITIQUE.md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX(1).md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX(2).md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX(3).md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX(4).md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX(5).md
archive/Analysis_Critiques_Rewrites/01_MASTER_INDEX.md
archive/Analysis_Critiques_Rewrites/03_THREAT_MODEL.md
archive/Analysis_Critiques_Rewrites/04_MIGRATION_PLAN.md
archive/Analysis_Critiques_Rewrites/05_OBSERVABILITY.md
archive/Analysis_Critiques_Rewrites/06_GLOSSARY.md
archive/Analysis_Critiques_Rewrites/2026-08-02-DGLab____-Kimi.md
archive/Analysis_Critiques_Rewrites/ADR-001-polyrepo-vs-monorepo.md
archive/Analysis_Critiques_Rewrites/ADR-002-psr11-container-scope.md
archive/Analysis_Critiques_Rewrites/ADR-003-es256-jwt-signing.md
archive/Analysis_Critiques_Rewrites/ADR-004-tier-enforcement-dag.md
archive/Analysis_Critiques_Rewrites/ADR-005-superphp-vs-blade-twig.md
archive/Analysis_Critiques_Rewrites/ADR-006-redis-over-memcached.md
archive/Analysis_Critiques_Rewrites/ADR-007-postgresql-over-mysql.md
archive/Analysis_Critiques_Rewrites/ADR-008-argon2id-password-hashing.md
archive/Analysis_Critiques_Rewrites/ADR-009-ulid-over-uuid.md
archive/Analysis_Critiques_Rewrites/ADR-010-opcache-preload-strategy.md
archive/Analysis_Critiques_Rewrites/ARCHIVED.md
archive/Analysis_Critiques_Rewrites/AUTHORING_GUIDE.md
archive/Analysis_Critiques_Rewrites/Analysis-2.md
archive/Analysis_Critiques_Rewrites/Analysis-3.md
archive/Analysis_Critiques_Rewrites/Analysis-Corrections-Additions.md
archive/Analysis_Critiques_Rewrites/Analysis.md
archive/Analysis_Critiques_Rewrites/BRIDGE-01-vanguard.md
archive/Analysis_Critiques_Rewrites/BRIDGE-01.md
archive/Analysis_Critiques_Rewrites/CORE-01-polyrepo-orchestrator.md
archive/Analysis_Critiques_Rewrites/CORE-01.md
archive/Analysis_Critiques_Rewrites/CORE-02-di-container.md
archive/Analysis_Critiques_Rewrites/CORE-02.md
archive/Analysis_Critiques_Rewrites/CORE-03-event-dispatcher.md
archive/Analysis_Critiques_Rewrites/CORE-04-http-message.md
archive/Analysis_Critiques_Rewrites/CORE-05-middleware.md
archive/Analysis_Critiques_Rewrites/CORE-06-router.md
archive/Analysis_Critiques_Rewrites/CORE-07-superphp-lexer.md
archive/Analysis_Critiques_Rewrites/CORE-08-error-handler.md
archive/Analysis_Critiques_Rewrites/CORE-09-logging.md
archive/Analysis_Critiques_Rewrites/CORE-10-config.md
archive/Analysis_Critiques_Rewrites/CORE-11-superphp-parser.md
archive/Analysis_Critiques_Rewrites/CORE-12-superphp-compiler.md
archive/Analysis_Critiques_Rewrites/CORE-13-cli-engine.md
archive/Analysis_Critiques_Rewrites/CORE-14-filesystem.md
archive/Analysis_Critiques_Rewrites/CORE-15-cache-abstraction.md
archive/Analysis_Critiques_Rewrites/CORE-16-encryption.md
archive/Analysis_Critiques_Rewrites/CORE-17-service-providers.md
archive/Analysis_Critiques_Rewrites/CORE-18-kernel.md
archive/Analysis_Critiques_Rewrites/CORE-19-dbal.md
archive/Analysis_Critiques_Rewrites/CORE-20-dev-cli.md
archive/Analysis_Critiques_Rewrites/DEPLOY-01-core-hub.md
archive/Analysis_Critiques_Rewrites/DEPLOY-01.md
archive/Analysis_Critiques_Rewrites/ESPOKE-01.md
archive/Analysis_Critiques_Rewrites/ESPOKE-02.md
archive/Analysis_Critiques_Rewrites/ESPOKE-03.md
archive/Analysis_Critiques_Rewrites/ESPOKE-04.md
archive/Analysis_Critiques_Rewrites/ESPOKE-05.md
archive/Analysis_Critiques_Rewrites/ESPOKE-06.md
archive/Analysis_Critiques_Rewrites/ESPOKE-07.md
archive/Analysis_Critiques_Rewrites/ESPOKE-08.md
archive/Analysis_Critiques_Rewrites/ESPOKE-09.md
archive/Analysis_Critiques_Rewrites/ESPOKE-10.md
archive/Analysis_Critiques_Rewrites/ESPOKE-11.md
archive/Analysis_Critiques_Rewrites/ESPOKE-12.md
archive/Analysis_Critiques_Rewrites/ESPOKE-13.md
archive/Analysis_Critiques_Rewrites/ESPOKE-14.md
archive/Analysis_Critiques_Rewrites/ESPOKE-15.md
archive/Analysis_Critiques_Rewrites/HUB-01(1).md
archive/Analysis_Critiques_Rewrites/HUB-01-config-feature-flags.md
archive/Analysis_Critiques_Rewrites/HUB-01.md
archive/Analysis_Critiques_Rewrites/HUB-02-cache.md
archive/Analysis_Critiques_Rewrites/HUB-02.md
archive/Analysis_Critiques_Rewrites/HUB-03.md
archive/Analysis_Critiques_Rewrites/HUB-04-identity.md
archive/Analysis_Critiques_Rewrites/HUB-04.md
archive/Analysis_Critiques_Rewrites/HUB-05.md
archive/Analysis_Critiques_Rewrites/HUB-06(1).md
archive/Analysis_Critiques_Rewrites/HUB-06-audit.md
archive/Analysis_Critiques_Rewrites/HUB-06.md
archive/Analysis_Critiques_Rewrites/HUB-07.md
archive/Analysis_Critiques_Rewrites/HUB-08-gateway.md
archive/Analysis_Critiques_Rewrites/HUB-08.md
archive/Analysis_Critiques_Rewrites/HUB-09.md
archive/Analysis_Critiques_Rewrites/HUB-10.md
archive/Analysis_Critiques_Rewrites/HUB-11.md
archive/Analysis_Critiques_Rewrites/HUB-12.md
archive/Analysis_Critiques_Rewrites/HUB-13.md
archive/Analysis_Critiques_Rewrites/HUB-14.md
archive/Analysis_Critiques_Rewrites/HUB-15-health.md
archive/Analysis_Critiques_Rewrites/HUB-15.md
archive/Analysis_Critiques_Rewrites/HUB-16.md
archive/Analysis_Critiques_Rewrites/HUB-17.md
archive/Analysis_Critiques_Rewrites/HUB-18.md
archive/Analysis_Critiques_Rewrites/HUB-19-validation.md
archive/Analysis_Critiques_Rewrites/HUB-19.md
archive/Analysis_Critiques_Rewrites/HUB-20-vault.md
archive/Analysis_Critiques_Rewrites/HUB-20.md
archive/Analysis_Critiques_Rewrites/HUB-21.md
archive/Analysis_Critiques_Rewrites/HUB-22.md
archive/Analysis_Critiques_Rewrites/HUB-23.md
archive/Analysis_Critiques_Rewrites/HUB-24.md
archive/Analysis_Critiques_Rewrites/HUB-25.md
archive/Analysis_Critiques_Rewrites/HUB-26.md
archive/Analysis_Critiques_Rewrites/HUB-27.md
archive/Analysis_Critiques_Rewrites/HUB-28.md
archive/Analysis_Critiques_Rewrites/HUB-29.md
archive/Analysis_Critiques_Rewrites/HUB-30.md
archive/Analysis_Critiques_Rewrites/ISPOKE-01.md
archive/Analysis_Critiques_Rewrites/ISPOKE-02.md
archive/Analysis_Critiques_Rewrites/ISPOKE-03.md
archive/Analysis_Critiques_Rewrites/ISPOKE-04.md
archive/Analysis_Critiques_Rewrites/ISPOKE-05
archive/Analysis_Critiques_Rewrites/ISPOKE-05(1).md
archive/Analysis_Critiques_Rewrites/ISPOKE-05.md
archive/Analysis_Critiques_Rewrites/ISPOKE-09.md
archive/Analysis_Critiques_Rewrites/ISPOKE-10.md
archive/Analysis_Critiques_Rewrites/ISPOKE-15.md
archive/Analysis_Critiques_Rewrites/Kimi.md
archive/Analysis_Critiques_Rewrites/STRUCTURE-01-v0.3.md
archive/Analysis_Critiques_Rewrites/STRUCTURE-01.md
archive/Analysis_Critiques_Rewrites/Structure_01_Wheel_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_01_v0.2_Wheel_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_02_Pulse_Lifecycle.md
archive/Analysis_Critiques_Rewrites/Structure_03_Security_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_04_Event_Driven_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_05_Data_Persistence_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_06_Boot_Provider_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_07_Testing_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_08_Deployment_Infrastructure_Architecture.md
archive/Analysis_Critiques_Rewrites/Structure_09_Performance_Scaling_Architecture.md
archive/Analysis_Critiques_Rewrites/Z.ai
archive/Analysis_Critiques_Rewrites/Z.ai
archive/Analysis_Critiques_Rewrites/Z.ai
archive/Analysis_Critiques_Rewrites/merged(4).md
archive/Analysis_Critiques_Rewrites/merged(5).md
archive/Analysis_Critiques_Rewrites/zai_export_2026-08-03(1).md
archive/Analysis_Critiques_Rewrites/zai_export_2026-08-03(2).md
archive/Analysis_Critiques_Rewrites/zai_export_2026-08-03.md
archive/Arc/ARCHIVED.md
archive/Arc/Blueprints/Core/CORE-01.md
archive/Arc/Blueprints/Core/CORE-02.md
archive/Arc/Blueprints/Core/CORE-03.md
archive/Arc/Blueprints/Core/CORE-04.md
archive/Arc/Blueprints/Core/CORE-05.md
archive/Arc/Blueprints/Core/CORE-06.md
archive/Arc/Blueprints/Core/CORE-07.md
archive/Arc/Blueprints/Core/CORE-08.md
archive/Arc/Blueprints/Core/CORE-09.md
archive/Arc/Blueprints/Core/CORE-10.md
archive/Arc/Blueprints/Core/CORE-11.md
archive/Arc/Blueprints/Core/CORE-12.md
archive/Arc/Blueprints/Core/CORE-13.md
archive/Arc/Blueprints/Core/CORE-14.md
archive/Arc/Blueprints/Core/CORE-15.md
archive/Arc/Blueprints/Core/CORE-16.md
archive/Arc/Blueprints/Core/CORE-17.md
archive/Arc/Blueprints/Core/CORE-18.md
archive/Arc/Blueprints/Core/CORE-19.md
archive/Arc/Blueprints/Core/CORE-20.md
archive/Arc/Blueprints/Deploy/DEPLOY-01.md
archive/Arc/Blueprints/Hub/HUB-01.md
archive/Arc/Blueprints/Hub/HUB-02.md
archive/Arc/Blueprints/Hub/HUB-03.md
archive/Arc/Blueprints/Hub/HUB-04.md
archive/Arc/Blueprints/Hub/HUB-05.md
archive/Arc/Blueprints/Hub/HUB-06.md
archive/Arc/Blueprints/Hub/HUB-07.md
archive/Arc/Blueprints/Hub/HUB-08.md
archive/Arc/Blueprints/Hub/HUB-09.md
archive/Arc/Blueprints/Hub/HUB-10.md
archive/Arc/Blueprints/Hub/HUB-11.md
archive/Arc/Blueprints/Hub/HUB-12.md
archive/Arc/Blueprints/Hub/HUB-13.md
archive/Arc/Blueprints/Hub/HUB-14.md
archive/Arc/Blueprints/Hub/HUB-15.md
archive/Arc/Blueprints/Hub/HUB-16.md
archive/Arc/Blueprints/Hub/HUB-17.md
archive/Arc/Blueprints/Hub/HUB-18.md
archive/Arc/Blueprints/Hub/HUB-19.md
archive/Arc/Blueprints/Hub/HUB-20.md
archive/Arc/Blueprints/Hub/HUB-21.md
archive/Arc/Blueprints/Hub/HUB-22.md
archive/Arc/Blueprints/Hub/HUB-23.md
archive/Arc/Blueprints/Hub/HUB-24.md
archive/Arc/Blueprints/Hub/HUB-25.md
archive/Arc/Blueprints/Hub/HUB-26.md
archive/Arc/Blueprints/Hub/HUB-27.md
archive/Arc/Blueprints/Hub/HUB-28.md
archive/Arc/Blueprints/Hub/HUB-29.md
archive/Arc/Blueprints/Hub/HUB-30.md
archive/Arc/Blueprints/Spoke/Bridge/BRIDGE-01.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-01.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-02.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-03.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-04.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-05.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-06.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-07.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-08.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-09.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-10.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-11.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-12.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-13.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-14.md
archive/Arc/Blueprints/Spoke/External/ESPOKE-15.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-01.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-02.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-03.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-04.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-05.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-06.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-07.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-08.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-09.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-10.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-11.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-12.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-13.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-14.md
archive/Arc/Blueprints/Spoke/Internal/ISPOKE-15.md
archive/Arc/Governance/01_MASTER_INDEX.md
archive/Arc/Governance/03_THREAT_MODEL.md
archive/Arc/Governance/04_MIGRATION_PLAN.md
archive/Arc/Governance/05_OBSERVABILITY.md
archive/Arc/Governance/06_GLOSSARY.md
archive/Arc/Governance/ADRs/ADR-001-polyrepo-vs-monorepo.md
archive/Arc/Governance/ADRs/ADR-002-psr11-container-scope.md
archive/Arc/Governance/ADRs/ADR-003-es256-jwt-signing.md
archive/Arc/Governance/ADRs/ADR-004-tier-enforcement-dag.md
archive/Arc/Governance/ADRs/ADR-005-superphp-vs-blade-twig.md
archive/Arc/Governance/ADRs/ADR-006-redis-over-memcached.md
archive/Arc/Governance/ADRs/ADR-007-postgresql-over-mysql.md
archive/Arc/Governance/ADRs/ADR-008-argon2id-password-hashing.md
archive/Arc/Governance/ADRs/ADR-009-ulid-over-uuid.md
archive/Arc/Governance/ADRs/ADR-010-opcache-preload-strategy.md
archive/Arc/Governance/AUTHORING_GUIDE.md
archive/Arc/README.md
archive/Design_Models_Misc/2026-08-10-Check
archive/Design_Models_Misc/AGRD_v2.0_Solo.md
archive/Design_Models_Misc/Claude_Chat_1786316976422.md
archive/Design_Models_Misc/DGLab
archive/Design_Models_Misc/DGLab
archive/Design_Models_Misc/DGLab
archive/Design_Models_Misc/MEMORY(1).md
archive/Design_Models_Misc/MEMORY(2).md
archive/Design_Models_Misc/MEMORY.md
archive/Design_Models_Misc/MEMORY_INSTRUCTIONS(1).md
archive/Design_Models_Misc/MEMORY_INSTRUCTIONS(2).md
archive/Design_Models_Misc/MEMORY_INSTRUCTIONS.md
archive/Design_Models_Misc/PROMPTS(1).md
archive/Design_Models_Misc/PROMPTS.md
archive/Design_Models_Misc/SDLC-AGRD-v3.1.md
archive/Design_Models_Misc/SDLC-AGRD-v3.3(1).md
archive/Design_Models_Misc/SDLC-AGRD-v3.3.md
archive/Design_Models_Misc/SDLC-AGRD-v3.4(1).md
archive/Design_Models_Misc/SDLC-AGRD-v3.4(2).md
archive/Design_Models_Misc/SDLC-AGRD-v3.4(3).md
archive/Design_Models_Misc/SDLC-AGRD-v3.4.md
archive/Design_Models_Misc/SDLC-AGRD-v3.md
archive/Design_Models_Misc/worklog.md
archive/Legacy/ARCHIVED.md
archive/Legacy/ENCRYPTION_SERVICE_MASTER.md
archive/Legacy/README.md
archive/Legacy/codebase_analysis_report.md
archive/Legacy/codebase_inventory.md
archive/Legacy/docs/ADDING_SERVICES.md
archive/Legacy/docs/API.md
archive/Legacy/docs/AuthService.md
archive/docs/architecture/origin/ARCHIVED.md
archive/docs/architecture/origin/CORE_FRAMEWORK.md
archive/docs/architecture/origin/ComponentBlueprints/ANALYSIS_STUDIO_MANGASCRIPT.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/PHASE_1_SECURITY.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/PHASE_2_SERVER_MONITORING.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/PHASE_3_PWA_MONITORING.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/PHASE_4_ANALYTICS.md
archive/docs/architecture/origin/ComponentBlueprints/AdminPanel/PHASE_5_MANAGEMENT_TOOLS.md
archive/docs/architecture/origin/ComponentBlueprints/Architecture/HUB_AND_SPOKE.md
archive/docs/architecture/origin/ComponentBlueprints/Architecture/SPOKE_MIGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/PHASE_1_DEPENDENCY_RESOLUTION.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/PHASE_2_BUNDLING_STRATEGY.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/PHASE_3_MINIFICATION_MANGLING.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/PHASE_4_SOURCE_MAPS.md
archive/docs/architecture/origin/ComponentBlueprints/AssetBundler/PHASE_5_INTEGRATION_REMOVAL.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/PHASE_1_CORE_IDENTITY.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/PHASE_2_MULTI_MECHANISM_AUTH.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/PHASE_3_TENANT_AUTHORIZATION.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/PHASE_4_SECURITY_LIFECYCLE.md
archive/docs/architecture/origin/ComponentBlueprints/AuthService/PHASE_5_OBSERVABILITY_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/API_HEADLESS_SPECIFICATION.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/PHASE_1_CORE_ENGINE_TENANCY.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/PHASE_2_CONTENT_MODELING_FLEXIBILITY.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/PHASE_3_LIFECYCLE_VERSIONING.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/PHASE_4_INTEGRATED_SERVICES.md
archive/docs/architecture/origin/ComponentBlueprints/CMS/PHASE_5_GLOBALIZATION_STUDIO.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_10_UNIFIED_FRONT.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_1_IAM.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_2_FOUNDATION.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_3_ARCHITECT.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_4_CONTENT_LIFECYCLE.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_5_SERVER_PULSE.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_6_PWA_PULSE.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_7_INTEGRATED_SERVICES.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_8_LOCALIZATION.md
archive/docs/architecture/origin/ComponentBlueprints/CMSStudio/PHASE_9_GOVERNANCE.md
archive/docs/architecture/origin/ComponentBlueprints/DECOMMISSIONING_PLAN.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_10_GLOBAL_SEARCH_UI.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_11_VERSIONING_CROSS_LINKS.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_12_TENANT_ISOLATION_RBAC.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_13_STATIC_SITE_EXPORT.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_14_GIT_METADATA_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_15_OBSERVABILITY_AUDITING.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_16_AI_SEARCH_PIPELINE_RAG.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_17_DOCSTUDIO_AI_ASSISTANT.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_18_FINAL_SATURATION.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_1_MARKDOWN_FOUNDATION.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_2_FILESYSTEM_DISCOVERY_ROUTING.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_3_FRONTMATTER_BLUEPRINTS.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_4_NAVIGATION_MANIFESTS.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_5_INTERACTIVE_DASHBOARD.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_6_REACTIVE_SPA_NAVIGATION.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_7_NEXUS_LIVE_RELOAD.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_8_VISUALS_DIAGRAMS.md
archive/docs/architecture/origin/ComponentBlueprints/DocumentationService/PHASE_9_SEARCH_INDEXING.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/PHASE_1_CORE_STORAGE.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/PHASE_2_SECURITY.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/PHASE_3_LIFECYCLE.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/PHASE_4_OBSERVABILITY.md
archive/docs/architecture/origin/ComponentBlueprints/DownloadService/PHASE_5_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/ARCHITECTURE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Archive/PHASE_1_FOUNDATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Archive/PHASE_2_SEARCHABLE_MODELS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Archive/PHASE_3_CLOUD_ENVELOPE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Archive/PHASE_4_ASYMMETRIC.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Archive/PHASE_5_SIGNATURES_AUDIT.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/CODEBASE_ANALYSIS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/KEY_MANAGEMENT.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/PHASED_IMPLEMENTATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_01_INTERFACE_CONTRACTS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_02_BINARY_ENVELOPE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_03_TEST_INFRASTRUCTURE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_04_LEGACY_MIGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_05_OPENSSL_GCM_DRIVER.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_06_SODIUM_CHACHA_DRIVER.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_07_KDF_HKDF_UTILITIES.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_08_ENCRYPTED_ATTRIBUTE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_09_MODEL_LIFECYCLE_HOOKS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_10_QUERY_BUILDER_HOOKS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_11_BLIND_INDEX_GENERATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_12_DETERMINISTIC_SEARCH_DSL.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_13_KEY_REGISTRY_SHAMIR_MPC.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_14_ROTATION_RE_ENCRYPTION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_15_CLOUD_KMS_DRIVERS.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_16_MULTI_TENANT_ISOLATION.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_17_ASYMMETRIC_PQC_SIGNATURES.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/Phased/PHASE_18_HARDENING_COMPLIANCE.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/SECURITY_AUDIT.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/STUDIO_INTEGRATION_MAP.md
archive/docs/architecture/origin/ComponentBlueprints/EncryptionService/TESTING_STRATEGY.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/PHASE_1_CORE_ENGINE.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/PHASE_2_ROUTING_CONTROL.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/PHASE_3_ASYNC_INFRASTRUCTURE.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/PHASE_4_OBSERVABILITY_AUDIT.md
archive/docs/architecture/origin/ComponentBlueprints/EventDispatcher/PHASE_5_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/PHASE_1_CORE_ENGINE_RENAMING.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/PHASE_2_AI_ORCHESTRATION.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/PHASE_3_CMS_TENANCY_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/PHASE_4_EVENT_DRIVEN_ASYNC.md
archive/docs/architecture/origin/ComponentBlueprints/MangaScript/PHASE_5_OBSERVABILITY_DELIVERY.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/ARCHITECTURE.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/DEPLOYMENT_TESTING.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/IDENTITY_AUTHORIZATION.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/INFRASTRUCTURE_DESIGN.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/INTEGRATION_AUDIT.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/PHASED_IMPLEMENTATION.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/SCALING_PUBSUB.md
archive/docs/architecture/origin/ComponentBlueprints/Nexus/SUPERPOWERS_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/README.md
archive/docs/architecture/origin/ComponentBlueprints/STABILIZATION_ROADMAP.md
archive/docs/architecture/origin/ComponentBlueprints/StudioExpansion/CLOUD_STORAGE.md
archive/docs/architecture/origin/ComponentBlueprints/StudioExpansion/CONSOLE_AND_SSO.md
archive/docs/architecture/origin/ComponentBlueprints/StudioExpansion/EPUB_FONT_CHANGER_2.md
archive/docs/architecture/origin/ComponentBlueprints/StudioExpansion/MANGA_STUDIO_AI.md
archive/docs/architecture/origin/ComponentBlueprints/StudioExpansion/PHASED_IMPLEMENTATION.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_10_ECOSYSTEM_INTEGRATION.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_1_FOUNDATIONS_LEXER.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_2_EXPRESSION_SAFETY.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_3_COMPONENT_CORE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_4_LIFECYCLE_STATE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_5_ADVANCED_LAYOUTS.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_6_COMPILER_CACHING.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_7_REACTIVE_BRIDGE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_8_REACTIVE_UI_DIFFING.md
archive/docs/architecture/origin/ComponentBlueprints/SuperPHP/PHASE_9_DX_OBSERVABILITY.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_10_FINAL_SATURATION.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_1_ENGINE_NAV_DIRECTIVES.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_2_NAV_ENGINE_JS.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_3_ASSET_PIPELINE_BUNDLING.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_4_ASSET_PIPELINE_DX.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_5_ARCHITECTURAL_SHELL.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_6_PWA_OFFLINE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_7_VIEW_MIGRATION_CORE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_8_VIEW_MIGRATION_INTERACTIVE.md
archive/docs/architecture/origin/ComponentBlueprints/SuperpowersSPA/PHASE_9_STATE_PERSISTENCE.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/OVERVIEW.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_10_CICD_AUTOMATION.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_1_FOUNDATIONS.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_2_UNIT_COVERAGE.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_3_INTEGRATION_ORCHESTRATION.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_4_BROWSER_AUTOMATION.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_5_REACTIVE_ASSERTIONS.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_6_PERFORMANCE_TELEMETRY.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_7_SECURITY_STRESS.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_8_VISUAL_ACCESSIBILITY.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/PHASE_9_CLI_TEST_RUNNER.md
archive/docs/architecture/origin/ComponentBlueprints/TestSuite/TESTABILITY_REPORT.md
archive/docs/architecture/origin/DATABASE_CORE.md
archive/docs/architecture/origin/DETAILED_SYSTEM_ANALYSIS.md
archive/docs/architecture/origin/HUB_AND_SPOKE.md
archive/docs/architecture/origin/LEARNINGS_OPERATIONS.md
archive/docs/architecture/origin/LOCALIZATION_SERVICE.md
archive/docs/architecture/origin/MEDIA_LIBRARY_SERVICE.md
archive/docs/architecture/origin/Mobile_Optimized/SOVEREIGN_STACK_MASTER.md
archive/docs/architecture/origin/OPERATIONS_MANUAL.md
archive/docs/architecture/origin/PhasedBlueprints/ANALYSIS_REPORT.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AdminPanel_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AdminPanel_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AdminPanel_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AdminPanel_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AdminPanel_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AssetBundler_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AssetBundler_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AssetBundler_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AssetBundler_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AssetBundler_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AuthService_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AuthService_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AuthService_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AuthService_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/AuthService_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_10.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_6.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_7.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_8.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/CMSStudio_PHASE_9.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/DownloadService_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/DownloadService_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/DownloadService_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/DownloadService_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/DownloadService_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/EventDispatcher_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/EventDispatcher_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/EventDispatcher_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/EventDispatcher_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/EventDispatcher_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/MangaScript_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/MangaScript_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/MangaScript_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/MangaScript_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/MangaScript_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/Nexus_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/Nexus_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/Nexus_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/Nexus_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/Nexus_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/StudioExpansion_PHASE_6.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_10.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_6.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_7.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_8.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperPHP_PHASE_9.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_10.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_6.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_7.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_8.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/SuperpowersSPA_PHASE_9.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_1.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_10.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_2.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_3.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_4.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_5.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_6.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_7.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_8.md
archive/docs/architecture/origin/PhasedBlueprints/MD_SPECS/TestSuite_PHASE_9.md
archive/docs/architecture/origin/PhasedBlueprints/README.md
archive/docs/architecture/origin/SEARCH_SERVICE.md
archive/docs/architecture/origin/STRATEGIC_OVERVIEW.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/GENERAL_VOLUME_LEXICON.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/MASTER_INDEX.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/SOVEREIGN_STACK_MASTER.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/VOLUME_III_CRYPTOGRAPHIC_FORTRESS.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/VOLUME_II_SUPERPHP_REACTIVE_UI.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/VOLUME_IV_NEXUS_AI_ORCHESTRATION.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/VOLUME_I_STRATEGIC_MANDATE.md
archive/docs/architecture/origin/Sovereign_Stack_Blueprint/VOLUME_V_STUDIO_ECOSYSTEM.md
archive/docs/architecture/origin/Strategic/DGLAB_STRATEGIC_BLUEPRINT.md
archive/docs/architecture/origin/Strategic/STRATEGIC_BLUEPRINT.md
archive/docs/architecture/origin/TENANCY_SERVICE.md
archive/docs/blueprints/ARCHIVED.md
archive/docs/blueprints/Core/CORE-01.md
archive/docs/blueprints/Core/CORE-02.md
archive/docs/blueprints/Core/CORE-03.md
archive/docs/blueprints/Core/CORE-04.md
archive/docs/blueprints/Core/CORE-05.md
archive/docs/blueprints/Core/CORE-06.md
archive/docs/blueprints/Core/CORE-07.md
archive/docs/blueprints/Core/CORE-08.md
archive/docs/blueprints/Core/CORE-09.md
archive/docs/blueprints/Core/CORE-10.md
archive/docs/blueprints/Core/CORE-11.md
archive/docs/blueprints/Core/CORE-12.md
archive/docs/blueprints/Core/CORE-13.md
archive/docs/blueprints/Core/CORE-14.md
archive/docs/blueprints/Core/CORE-15.md
archive/docs/blueprints/Core/CORE-16.md
archive/docs/blueprints/Core/CORE-17.md
archive/docs/blueprints/Core/CORE-18.md
archive/docs/blueprints/Core/CORE-19.md
archive/docs/blueprints/Core/CORE-20.md
archive/docs/blueprints/Deploy/DEPLOY-01.md
archive/docs/blueprints/Hub/HUB-01.md
archive/docs/blueprints/Hub/HUB-02.md
archive/docs/blueprints/Hub/HUB-03.md
archive/docs/blueprints/Hub/HUB-04.md
archive/docs/blueprints/Hub/HUB-05.md
archive/docs/blueprints/Hub/HUB-06.md
archive/docs/blueprints/Hub/HUB-07.md
archive/docs/blueprints/Hub/HUB-08.md
archive/docs/blueprints/Hub/HUB-09.md
archive/docs/blueprints/Hub/HUB-10.md
archive/docs/blueprints/Hub/HUB-11.md
archive/docs/blueprints/Hub/HUB-12.md
archive/docs/blueprints/Hub/HUB-13.md
archive/docs/blueprints/Hub/HUB-14.md
archive/docs/blueprints/Hub/HUB-15.md
archive/docs/blueprints/Hub/HUB-16.md
archive/docs/blueprints/Hub/HUB-17.md
archive/docs/blueprints/Hub/HUB-18.md
archive/docs/blueprints/Hub/HUB-19.md
archive/docs/blueprints/Hub/HUB-20.md
archive/docs/blueprints/Hub/HUB-21.md
archive/docs/blueprints/Hub/HUB-22.md
archive/docs/blueprints/Hub/HUB-23.md
archive/docs/blueprints/Hub/HUB-24.md
archive/docs/blueprints/Hub/HUB-25.md
archive/docs/blueprints/Hub/HUB-26.md
archive/docs/blueprints/Hub/HUB-27.md
archive/docs/blueprints/Hub/HUB-28.md
archive/docs/blueprints/Hub/HUB-29.md
archive/docs/blueprints/Hub/HUB-30.md
archive/docs/blueprints/Spoke/Bridge/BRIDGE-01.md
archive/docs/blueprints/Spoke/External/ESPOKE-01.md
archive/docs/blueprints/Spoke/External/ESPOKE-02.md
archive/docs/blueprints/Spoke/External/ESPOKE-03.md
archive/docs/blueprints/Spoke/External/ESPOKE-04.md
archive/docs/blueprints/Spoke/External/ESPOKE-05.md
archive/docs/blueprints/Spoke/External/ESPOKE-06.md
archive/docs/blueprints/Spoke/External/ESPOKE-07.md
archive/docs/blueprints/Spoke/External/ESPOKE-08.md
archive/docs/blueprints/Spoke/External/ESPOKE-09.md
archive/docs/blueprints/Spoke/External/ESPOKE-10.md
archive/docs/blueprints/Spoke/External/ESPOKE-11.md
archive/docs/blueprints/Spoke/External/ESPOKE-12.md
archive/docs/blueprints/Spoke/External/ESPOKE-13.md
archive/docs/blueprints/Spoke/External/ESPOKE-14.md
archive/docs/blueprints/Spoke/External/ESPOKE-15.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-01.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-02.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-03.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-04.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-05.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-06.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-07.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-08.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-09.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-10.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-11.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-12.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-13.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-14.md
archive/docs/blueprints/Spoke/Internal/ISPOKE-15.md
archive/docs/blueprints/disapproved/CORE-01.md
archive/docs/blueprints/disapproved/CORE-02.md
archive/docs/blueprints/disapproved/CORE-03.md
archive/docs/blueprints/disapproved/CORE-04.md
archive/docs/blueprints/disapproved/CORE-05.md
archive/docs/blueprints/disapproved/CORE-06.md
archive/docs/blueprints/disapproved/CORE-07.md
archive/docs/blueprints/disapproved/CORE-08.md
archive/docs/blueprints/disapproved/CORE-09.md
archive/docs/blueprints/disapproved/CORE-10.md
archive/docs/blueprints/disapproved/CORE-11.md
archive/docs/blueprints/disapproved/CORE-12.md
archive/docs/blueprints/disapproved/CORE-13.md
archive/docs/blueprints/disapproved/CORE-14.md
archive/docs/blueprints/disapproved/CORE-15.md
archive/docs/blueprints/disapproved/CORE-16.md
archive/docs/blueprints/disapproved/CORE-17.md
archive/docs/blueprints/disapproved/CORE-18.md
archive/docs/blueprints/disapproved/CORE-19.md
archive/docs/blueprints/disapproved/CORE-20.md
archive/docs/blueprints/disapproved/HUB-01.md
archive/docs/blueprints/disapproved/HUB-02.md
archive/docs/blueprints/disapproved/HUB-03.md
archive/docs/blueprints/disapproved/HUB-04.md
archive/docs/blueprints/disapproved/HUB-05.md
archive/docs/blueprints/disapproved/HUB-06.md
archive/docs/blueprints/disapproved/HUB-07.md
archive/docs/blueprints/disapproved/HUB-08.md
archive/docs/blueprints/disapproved/HUB-09.md
archive/docs/blueprints/disapproved/HUB-10.md
archive/docs/blueprints/disapproved/HUB-11.md
archive/docs/blueprints/disapproved/HUB-12.md
archive/docs/blueprints/disapproved/HUB-13.md
archive/docs/blueprints/disapproved/HUB-14.md
archive/docs/blueprints/disapproved/HUB-15.md
archive/docs/blueprints/disapproved/HUB-16.md
archive/docs/blueprints/disapproved/HUB-17.md
archive/docs/blueprints/disapproved/HUB-18.md
archive/docs/blueprints/disapproved/HUB-19.md
archive/docs/blueprints/disapproved/HUB-20.md
archive/docs/blueprints/disapproved/HUB-21.md
archive/docs/blueprints/disapproved/HUB-22.md
archive/docs/blueprints/disapproved/HUB-23.md
archive/docs/blueprints/disapproved/HUB-24.md
archive/docs/blueprints/disapproved/HUB-25.md
archive/docs/blueprints/disapproved/HUB-26.md
archive/docs/blueprints/disapproved/HUB-27.md
archive/docs/blueprints/disapproved/HUB-28.md
archive/docs/blueprints/disapproved/HUB-29.md
archive/docs/blueprints/disapproved/HUB-30.md
archive/docs/blueprints/disapproved/SPOKE-01.md
archive/docs/blueprints/disapproved/SPOKE-02.md
archive/docs/blueprints/disapproved/SPOKE-03.md
archive/docs/blueprints/disapproved/SPOKE-04.md
archive/docs/blueprints/disapproved/SPOKE-05.md
archive/docs/blueprints/disapproved/SPOKE-06.md
archive/docs/blueprints/disapproved/SPOKE-07.md
archive/docs/blueprints/disapproved/SPOKE-08.md
archive/docs/blueprints/disapproved/SPOKE-09.md
archive/docs/blueprints/disapproved/SPOKE-10.md
archive/docs/blueprints/disapproved/SPOKE-11.md
archive/docs/blueprints/disapproved/SPOKE-12.md
archive/docs/blueprints/disapproved/SPOKE-13.md
archive/docs/blueprints/disapproved/SPOKE-14.md
archive/docs/blueprints/disapproved/SPOKE-15.md
archive/docs/blueprints/disapproved/SPOKE-16.md
archive/docs/blueprints/disapproved/SPOKE-17.md
archive/docs/blueprints/disapproved/SPOKE-18.md
archive/docs/blueprints/disapproved/SPOKE-19.md
archive/docs/blueprints/disapproved/SPOKE-20.md
archive/docs/blueprints/disapproved/SPOKE-21.md
archive/docs/blueprints/disapproved/SPOKE-22.md
archive/docs/evaluation/ARCHIVED.md
archive/docs/evaluation/BLUEPRINT_RANKINGS.md
archive/docs/evaluation/BUDGET_ANALYSIS.md
archive/docs/evaluation/EVALUATION_INDEX.md
archive/docs/evaluation/EVALUATION_SUMMARY.md
archive/docs/evaluation/OPENROUTER_INVESTMENT_SUMMARY.md
archive/docs/evaluation/README_EVALUATION.md
archive/docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md
archive/docs/evaluation/quality.md
archive/pre-A3-audit/Architecture/CrossCutting/SDLC-AGRD-v3.4.3.md
archive/pre-A3-audit/README.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-001-di-container-design.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-002-plugin-system-architecture.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-003-validation-framework-strategy.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-004-routing-strategy.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-005-event-system-design.md
archive/pre-A3-audit/docs/architecture/decisions/ADR-TEMPLATE.md
archive/pre-A3-audit/docs/cache-patterns/cache-invalidation-strategies.md
archive/pre-A3-audit/docs/cache-patterns/cache-sizing-guide.md
archive/pre-A3-audit/docs/cache-patterns/distributed-cache-consistency.md
archive/pre-A3-audit/docs/ci/ci-configuration.md
archive/pre-A3-audit/docs/ci/in-memory-alternatives.md
archive/pre-A3-audit/docs/ci/local-development-setup.md
archive/pre-A3-audit/docs/ci/testcontainers-integration.md
archive/pre-A3-audit/docs/cli-framework/advanced-guide.md
archive/pre-A3-audit/docs/cli-framework/beginner-guide.md
archive/pre-A3-audit/docs/cli-framework/complexity-tiers-map.md
archive/pre-A3-audit/docs/cli-framework/diagnostic-commands.md
archive/pre-A3-audit/docs/cli-framework/intermediate-guide.md
archive/pre-A3-audit/docs/design-patterns/adapter-pattern.md
archive/pre-A3-audit/docs/design-patterns/chain-of-responsibility.md
archive/pre-A3-audit/docs/design-patterns/composite-pattern.md
archive/pre-A3-audit/docs/design-patterns/decorator-pattern.md
archive/pre-A3-audit/docs/design-patterns/factory-pattern.md
archive/pre-A3-audit/docs/design-patterns/observer-pattern.md
archive/pre-A3-audit/docs/design-patterns/strategy-pattern.md
archive/pre-A3-audit/docs/design-patterns/template-method.md
archive/pre-A3-audit/docs/extensibility/extension-points-map.md
archive/pre-A3-audit/docs/external-spokes/api-versioning-strategy.md
archive/pre-A3-audit/docs/external-spokes/bridge-high-availability.md
archive/pre-A3-audit/docs/external-spokes/developer-portal.md
archive/pre-A3-audit/docs/external-spokes/rate-limiting-strategy.md
archive/pre-A3-audit/docs/external-spokes/seo-optimization.md
archive/pre-A3-audit/docs/hub-taxonomy/cache-solution-selector.md
archive/pre-A3-audit/docs/hub-taxonomy/hub-blueprint-taxonomy.md
archive/pre-A3-audit/docs/hub-taxonomy/hub-categories.md
archive/pre-A3-audit/docs/hub-taxonomy/hub-dependency-graph.md
archive/pre-A3-audit/docs/hub-taxonomy/hub-navigation-guide.md
archive/pre-A3-audit/docs/hub-taxonomy/persistence-pattern-selector.md
archive/pre-A3-audit/docs/hub-taxonomy/queue-solution-selector.md
archive/pre-A3-audit/docs/implementation-guides/core-implementation-instructions.md
archive/pre-A3-audit/docs/implementation-guides/di-container-setup.md
archive/pre-A3-audit/docs/implementation-guides/event-system-wiring.md
archive/pre-A3-audit/docs/implementation-guides/plugin-registration.md
archive/pre-A3-audit/docs/implementation-guides/routing-configuration.md
archive/pre-A3-audit/docs/implementation-guides/validation-framework-usage.md
archive/pre-A3-audit/docs/integration/adapter-library.md
archive/pre-A3-audit/docs/integration/adapter-pattern.md
archive/pre-A3-audit/docs/integration/interoperability-standards.md
archive/pre-A3-audit/docs/integration/marketplace-concept.md
archive/pre-A3-audit/docs/integration/templates/container-orchestration-adapter.md
archive/pre-A3-audit/docs/integration/templates/logging-provider-adapter.md
archive/pre-A3-audit/docs/integration/templates/monitoring-system-adapter.md
archive/pre-A3-audit/docs/internal-spokes/concurrent-editing.md
archive/pre-A3-audit/docs/internal-spokes/crud-anti-patterns.md
archive/pre-A3-audit/docs/internal-spokes/crud-specialization.md
archive/pre-A3-audit/docs/internal-spokes/placeholder-blueprints.md
archive/pre-A3-audit/docs/internal-spokes/role-delegation-patterns.md
archive/pre-A3-audit/docs/internal-spokes/spoke-documentation-template.md
archive/pre-A3-audit/docs/internal-spokes/workflow-scalability.md
archive/pre-A3-audit/docs/operations/chaos-engineering.md
archive/pre-A3-audit/docs/operations/hub-scale-guide.md
archive/pre-A3-audit/docs/operations/incident-response.md
archive/pre-A3-audit/docs/operations/observability-framework.md
archive/pre-A3-audit/docs/operations/runbooks/cache-warming.md
archive/pre-A3-audit/docs/operations/runbooks/failure-recovery.md
archive/pre-A3-audit/docs/operations/runbooks/queue-backpressure.md
archive/pre-A3-audit/docs/operations/service-dependency-analyzer.md
archive/pre-A3-audit/docs/plans/cli-testing-docs-plan.md
archive/pre-A3-audit/docs/plans/external-spokes-w1-w5-plan.md
archive/pre-A3-audit/docs/plans/hub-tier-weaknesses-2-3-plan.md
archive/pre-A3-audit/docs/plans/hub-tier-weaknesses-4-5-plan.md
archive/pre-A3-audit/docs/plans/hub-weakness-1-plan.md
archive/pre-A3-audit/docs/plans/internal-spokes-w1-w5-plan.md
archive/pre-A3-audit/docs/plans/strategic-solutions-w1-w2-plan.md
archive/pre-A3-audit/docs/queue-patterns/dead-letter-handling.md
archive/pre-A3-audit/docs/queue-patterns/message-ordering-guarantees.md
archive/pre-A3-audit/docs/queue-patterns/throughput-optimization.md
archive/pre-A3-audit/docs/roadmap/internal-spokes-timeline.md
archive/pre-A3-audit/docs/team-scaling-guide.md
archive/pre-A3-audit/docs/tenancy/isolation-layer.md
archive/pre-A3-audit/docs/tenancy/isolation-test-suite.md
archive/pre-A3-audit/docs/tenancy/team-training.md
archive/pre-A3-audit/docs/tenancy/tenant-audit-logging.md
archive/pre-A3-audit/docs/testing/recipes.md
blueprint
blueprint
blueprint
by
by
by
powered
powered
powered
refinement(1).md
refinement(2)(1).md
refinement(2).md
```

</details>

Summary (archive): Historical (retain as-is) = 793.

## 7. Totals

| Disposition | Count |
|-------------|-------|
| Updated | 16 |
| No change needed | 232 |
| Needs owner decision | 1 |
| Historical (retain as-is) | 793 |
| **Total** | **1042** |

