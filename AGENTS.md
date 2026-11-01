# DGLab — Instructions for AI Coding and Research Agents

> Status: active repository instructions. Operational status below is a snapshot last verified against `origin/main` at `11244ebb5d3d11b7f38ee51100ed069668fbd251` on 2026-11-01; re-verify moving facts at session start.

## 1. Mission and immediate priority

DGLab is a PHP 8.4 Sovereign Stack monorepo. The founder has changed the LMS objective from a page-ready prototype to a **complete, usable backend** so the founder can build the UI/UX independently.

There are two parallel concerns, in this order:

1. **First complete and reconcile the research package for owner review.** Root research reports and agenda materials exist, but source review, the V1–V7 reconciliation, connecting-theme wording, NeoSPX placement, and authorization to publish the canonical agenda remain unresolved. Research is separate from implementation.
2. **Then resume LMS backend delivery** against approved architecture, security, API, and testing decisions.

Do not let the LMS backlog erase research priorities. Do not let research delay unrelated read-only review or preparation, but do not mix research deliverables into LMS implementation PRs.

## 2. Source-of-truth and evidence rules

Use this authority order:
1. Explicit, current owner decisions.
2. Merged repository records and frozen contracts.
3. Approved ADRs and current architecture documentation.
4. Current source code, tests, CI configuration, and actual CI runs.
5. Open PRs and unmerged branches, clearly labeled as proposed.
6. Agent reports and chat summaries, labeled as reported until independently verified.

Never describe a chat statement, local-only commit, draft, open PR, or agent report as a merged repository fact. Label important statements **Verified**, **Reported**, **Proposed**, or **Owner decision required**. Include the commit SHA, path, command, test, or link that supports verification where possible.

The verified `origin/main` baseline on 2026-11-01 is `11244ebb5d3d11b7f38ee51100ed069668fbd251` (PR #376). Earlier SHAs in handoffs are historical, not current baselines. At session start, fetch/read the actual current state and report any divergence before work. Never assume a local branch or worktree exists remotely.

## 3. Architecture principles

- DGLab uses a custom Core/Hub/Spoke architecture within a six-ring Wheel monorepo.
- Architectural principle: **Compose downward, abstract upward, and expose only the capability contract appropriate to the consumer.**
- Strategic direction: **Canonical State → Deterministic Derivation → CI-Enforced Consistency**, without allowing governance work to block the MVP.
- Respect dependency direction and existing boundaries. Do not create new cross-ring dependencies for convenience.
- Hub owns domain capabilities; persistence is accessed through ports/interfaces; application services orchestrate use cases.
- Treat `FROZEN-CONTRACTS.md`, `Architecture/Core/`, approved ADRs, package manifests, and current code as authoritative constraints. Inspect them before proposing or modifying contracts.
- PHP baseline reported by the repo is PHP `^8.4`; verify current `composer.json`. Do not repeat stale claims that the baseline is PHP 8.3.
- Do not introduce a JavaScript framework or polished frontend as part of the backend handoff. The founder owns UI/UX.
- NeoSPX is not yet a runtime dependency for LMS. SPX research must not block LMS backend delivery after the research handoff is completed.

## 4. Collaboration and SDLC v5.1

The owner approved **Single Implementer, Multiple Independent Reviewers**:
- One human owner, one designated implementer per implementation task by default, and independent reviewers.
- Reviewers inspect and report findings; they do not edit implementation artifacts unless the Tech Lead explicitly authorizes a role change.
- Findings go back to the implementer for correction.
- The human owner/Tech Lead controls scope, architectural decisions, acceptance, and merge.
- Contributors use their own branch, do not push directly to the main/integration branch, do not self-merge, and open a PR explaining what changed and how it was verified.
- Parallel implementation is prohibited by default unless the Tech Lead explicitly authorizes it in advance. Any exception must state rationale, task/file/interface boundaries, integration owner, and verification plan.
- Parallel research, read-only analysis, test preparation, and independent review are allowed.
- CI-green is evidence, not proof that behavior is complete or correctly integrated.

Never infer authorization to merge, push, format the repository, amend frozen contracts, create an ADR, or change governance records from a broad product goal alone.

## 5. Research-first work order

Complete research before new LMS implementation increments.

### Research track SPX / NeoSPX
Research the component/syntax architecture, not just the name:
- Multiple authoring syntaxes through syntax drivers.
- Two-stage representation: authoring syntax → syntax driver → **public, language-neutral component document** → AST → validation/semantic analysis/compilation.
- The component document is intended as a stable interchange contract for third-party tools.
- The semantic information model should be format-neutral; serialization bindings are a separate decision.
- JSON Schema 2020-12 and JSON/YAML interoperability are research candidates, not approved decisions.
- Compare JSON-first against supporting JSON and YAML from day one; address YAML scalar typing, schema validation, canonicalization, source provenance, error reporting, versioning, round-trip/source-preservation needs, security, and extension rules.
- Compare relevant existing designs and primary documentation. Do not infer that a candidate design has been approved.
- Keep naming research separate. Candidate collision status reported in an agent audit: Forge collides with CORE-20 “Sovereign Forge”; Nexus collides with HUB-17 and HUB-21; Sovereign is widely used as a Hub-tier prefix; Foundry and SPX had no internal collisions in that audit, while SPX may collide with external PHP-extension acronyms. Re-verify all findings against current ADRs/registers. No rename is approved; do not amend ADR-005 or create a naming ADR without owner authorization.
- An earlier agenda rendition said “handle SuperPHP separately”; a later local-only rendition added it as a sixth section. Flag this divergence. Do not silently decide where it belongs in the canonical research agenda.

### Four product-research tracks
1. **Platform integrity, copyright, ownership, and fair dispute resolution**: abuse, impersonation, fraud, coordinated manipulation; copyright/licensing/attribution/provenance/derivative works; takedowns, counterclaims, appeals, fraudulent complaints; transparent moderation; creator portability; commercial incentive distortions. Distinguish enforcement power from legitimate authority to determine fairness.
2. **Avoiding the YouTube model**: lock-in, centralized control, switching costs, dark patterns, asymmetric commitment, engagement incentives. Do not assume decentralization alone solves abuse, copyright, or governance.
3. **Half Exceptions**: user-originated hypothesis, not an established software-engineering term. Investigate both proposed meanings: partial exceptions to general preferences and partially negotiable preferences. Explore software analogies (configuration defaults, authorization policies, feature flags, fault tolerance) only as analogies, and assess scope, authorization, expiry, reviewability, and auditability.
4. **Cancel-Anytime paradox**: preserve the owner-supplied statement exactly as a hypothesis: “Cancel anytime is the most repeated promise in the history of commerce. It is also the one most rarely acted upon.” Do not present it as an established empirical finding. Investigate exitability as a product/system property rather than a policy promise.

### Connecting-theme investigation
The wording is unresolved. Three variants were reported:
- “How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?”
- A procedural formulation about identifying shared concepts and potential DGLab implications without prematurely creating abstractions.
- “The difference between a right that exists on paper and a right that a person can exercise in practice.”

These are not interchangeable. Present them side-by-side, explain differences, and propose a reconciliation for owner review. Do not attribute a wording to the owner unless the original record verifies that attribution.

### Research deliverables
- A source-backed report per track, including scope, research questions, evidence, counterarguments/limitations, implications for DGLab, open questions, and decisions explicitly **not** made.
- A reconciliation table for all known agenda renditions, including the NeoSPX placement conflict.
- A proposed canonical agenda for owner review; **do not commit/push/create `Architecture/CrossCutting/RESEARCH-AGENDA.md` without explicit owner authorization**.
- A standalone SPX naming/collision finding, labeled with verification status.
- A final research index that links reports and clearly separates evidence from recommendations.

Research must remain independent of LMS implementation PRs.

## 6. LMS backend direction (after research handoff)

The deliverable is a complete backend, not a page-ready UI. Target capabilities include:
- Identity/authentication and role-based authorization.
- Course create/read/update/publish and published-course discovery/detail.
- Module management and learning-content retrieval.
- Learner enrollment and enrollment queries.
- Progress recording, retrieval, and listing.
- Versioned, documented REST/JSON API with stable DTOs, validation errors, pagination, and consistent error semantics.
- Composition-root/DI wiring, middleware, migrations, runnable environment, and real-MySQL integration tests.
- End-to-end test of a complete journey once the pieces exist.
- A founder handoff with API/OpenAPI documentation, setup instructions, schema/migrations, auth behavior, examples, and known limitations.

### 6.1 Verified current state (2026-11-01; origin/main `11244eb`)
- PR #364 (published-course discovery) merged at `b03306c`; PR #366 (enrollment persistence and application service) separately merged at `a18a3e8`.
- Current LMS source is under `Spokes/Internal/Lms/`. Course, module, and enrollment entities, application services, MySQL repositories, and migrations are present; component/unit tests are not real-MySQL integration evidence.
- The progress entity and migration exist, but `ProgressRepositoryInterface`, `MySQLProgressRepository`, and a progress application service are absent from the current LMS tree. PR #369 closed unmerged; it targets the retired `packages/spoke/internal/lms/` layout and must not be cherry-picked without a reviewed port.
- `Hub/Identity/Http/AuthMiddleware.php` exists, but `app/ApplicationFactory.php` does not wire LMS routes or authentication middleware. No auth transport/API contract is approved; do not assume sessions or JWT.
- The active CI workflows do not provision a MySQL service for LMS integration tests. PR #370's migration runner is closed and remains in the legacy `packages/core/migration-runner/` layout; no current LMS MySQL contract is thereby approved.
- PR #363 is closed; the `external/ELQ` gitlink remains in the current tree. Do not change it as part of unrelated work.
- PR #310 is closed unmerged. PRs #368–#372 and #375–#376 are closed/merged as of this snapshot; verify current PR state before relying on this list. No open PRs were reported by GitHub at verification time.
- PHPStan is 2.3.4 in the root and orchestrator Composer constraints. CI has component tests, root integration, static analysis, and a legacy Packages CI gate; use current workflow definitions rather than old package-matrix counts.
- NeoSPX lexer source/tests exist under `Core/NeoSpx/`; local verification on 2026-11-01 was 27 tests / 74 assertions passing. ADR-023 is absent; lexer presence or test success is not approval of the grammar/specification.
- Research agenda and connecting-theme decisions remain owner-review items. Keep research documents separate from LMS implementation PRs.

### LMS build order
1. Review current main, frozen contracts, ADRs, and current CI/test evidence. PRs #364 and #366 are already merged; inspect current source rather than relying on their old diffs.
2. Agree and record auth transport, API versioning, role/permission matrix, error format, pagination, and same-origin/separate-origin assumptions. Do not assume PHP sessions or JWT are approved.
3. Approve the MySQL integration-test contract: env var/DSN, migration runner, test command, isolation/reset strategy, CI MySQL service.
4. **Enrollment persistence and application service are present on main (PR #366).** Re-verify current source and tests.
5. Port and implement progress persistence under `Spokes/Internal/Lms/` only after the research gate and MySQL integration-test contract are accepted; PR #369 is closed and targets a retired path.
7. Implement composition-root registration, HTTP controllers/routes, validation/serialization, authentication middleware, and authorization enforcement.
8. Add real-MySQL integration tests and a complete end-to-end learner/admin flow.
9. Produce API docs, OpenAPI if agreed, setup guide, sample requests/responses, migration instructions, and frontend handoff.
10. Run independent review, correct findings, owner acceptance, and merge only with explicit approval.

Keep increments reviewable. Do not promise a calendar duration until the current code, CI capacity, and task boundaries have been checked.

Keep increments reviewable. Do not promise a calendar duration until the current code, CI capacity, and task boundaries have been checked.

## 7. Pending decisions and repository risks

Track these explicitly; do not resolve them by assumption:
- Authentication transport and authorization to create ADR-022.
- MySQL integration-test contract.
- Whether to remove or properly register the remaining `external/ELQ` gitlink; PR #363 is closed and does not settle current tree state.
- D-01: re-scope S-003 closure condition or commission the missing `PulseFiberIsolationTest.php`.
- D-09 reported PSR-4 namespace collision versus findings-register corrections and ADR-011 priority.
- Research agenda wording/placement and authorization to publish.
- Provenance/authorization for local-only `82ddde7` SKILL.md change.
- Formatting audit artifact/scope, readability Phase A verdict, and any Phase C authorization.
- Whether to reauthorize/re-scope CORE-15 cache after PR #310 was closed unmerged.
- Whether other reported baseline facts still hold.

The `external/ELQ` path was reported to be a dangling gitlink, not a properly registered submodule: no `.gitmodules` existed in the examined history, and four of six submodule commands failed before removal. This is reported evidence; verify current PR and main before acting. Do not add `.gitmodules` reflexively.

A stale credential-bearing Git remote was reported in a pre-existing branch config. Do not print or copy any token. If it may still be valid, recommend revocation/rotation and remove the credential-bearing URL safely; investigate separately from feature work.

## 8. Repository operating rules

- At session start: inspect `git status`, current branch, `git log -5 --oneline`, remotes, current `origin/main`, nested `AGENTS.md`/tool rules, frozen contracts, ADR index, CI workflows, and relevant package manifests.
- Preserve uncommitted and local-only work. Do not reset, clean, rebase, cherry-pick, or overwrite another agent’s work without explicit authorization.
- Never assume local branch names or SHAs exist remotely.
- Do not run formatters repository-wide unless explicitly authorized.
- Do not change frozen contracts, ADR status, finding registers, research agenda, or governance files without an explicit scoped task.
- Do not commit, push, merge, or create branches unless the task/owner explicitly authorizes it.
- Before any implementation, report the exact scope, files/interfaces likely affected, dependencies, tests, and non-goals. If one essential decision blocks the task, ask one concise question rather than inventing a decision.
- After work, report changed files, exact verification commands/results, limitations, unverified assumptions, branch/commit/PR status, and decisions still needed.

## 9. Independent founder/Claude review

The founder plans to use Claude to analyze the code after handoff. Prepare for a skeptical independent review:
- Give Claude a concise system map, current commit/branch, ADR/frozen-contract index, run/test instructions, API contracts, migration/schema notes, and exact evidence for what works.
- Clearly separate implemented, unit-tested, integration-tested against MySQL, and end-to-end demonstrated capabilities.
- List security boundaries, auth/session/token behavior, role checks, input validation, transaction semantics, failure behavior, and known gaps.
- Do not ask Claude to rubber-stamp the implementation. Ask it to find architectural inconsistencies, authorization gaps, data-integrity bugs, API contract mismatches, missing tests, migration hazards, and unsupported completion claims.
- Provide a review prompt and a machine-readable or tabular capability matrix in the handoff.
