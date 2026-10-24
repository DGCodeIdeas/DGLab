# DGLab Fresh-Session Handoff and Build Plan
**Prepared:** 2026-10-20  
**Audience:** SAAI coordinator, fresh Z.ai session, Kilo Code, founder, and later Claude review  
**Status:** Handoff draft based on user-pasted summaries and agent reports. No repository changes are implied.

## 0. Mission

The founder no longer wants a page-ready LMS prototype. The founder wants the **complete LMS backend** and will build the UI/UX independently. At the same time, pending research—including SPX/NeoSPX—must be completed first and must not be lost during implementation pauses.

Order of operations:
1. Re-establish the real repository state and preserve local work.
2. Complete research and reconcile the agenda drafts.
3. Present research findings and the proposed canonical agenda to the owner.
4. Wait for owner approval before publishing the agenda or changing governance records.
5. Resume LMS backend implementation in small, reviewable increments.
6. Build and run real-MySQL integration tests, then end-to-end tests.
7. Prepare the backend/API handoff for the founder and independent Claude review.

Research deliverables should be developed before new LMS implementation increments. Read-only inspection, PR review, and test-contract research can proceed in parallel if they do not mutate shared artifacts.

## 1. Epistemic contract

Every material claim must be labeled:
- **V — Verified:** confirmed directly against the current repository, current CI run, or primary source. Cite path, SHA, command, or URL.
- **R — Reported:** appears in an agent report or user-pasted summary but has not been independently checked in this session.
- **P — Proposed:** a recommendation or draft, not approved.
- **O — Owner decision required:** must not proceed until the owner decides.

A previous report's “verified” marker does not automatically make the claim verified in this fresh session. Re-check moving facts. Treat the supplied baseline `ae410ea09fcd7a4c0c2c77cc980ff7d3e4984ac3` as historical until current `origin/main` is inspected.

## 2. Fresh-session startup protocol (Z.ai and Kilo Code)

First task is read-only reconnaissance. Do not start implementation, change files, or create a branch before reporting the state.

Run/check:
- `pwd`, `git status --short --branch`, `git branch --show-current`, `git log -5 --oneline --decorate`
- `git remote -v` (redact any credentials; never echo tokens)
- fetch/read current remote refs only if allowed by environment and credential safety; identify actual `origin/main` SHA
- `git branch -avv`, `git worktree list`, `git stash list`
- locate all applicable `AGENTS.md`, tool-specific instructions, `FROZEN-CONTRACTS.md`, ADR index, architecture docs, and SDLC protocol
- inspect `composer.json` and package manifests for PHP/runtime/test commands
- inspect workflows, especially package CI, architecture checks, MySQL services, migration/test runners
- inspect open PRs #363/#364/#310 through live remote evidence if available
- confirm whether local commits `47e1ef2`, `581945d`, `82ddde7` or patches are present in the current environment; do not assume they are
- record whether `Architecture/CrossCutting/RESEARCH-AGENDA.md` exists on current main
- preserve all uncommitted/local work; do not reset, clean, rebase, cherry-pick, amend, push, or merge.

Deliver a short state report with current SHA, dirty state, branch/worktrees, available local-only artifacts, and mismatches against this handoff. Stop for owner/SAAI direction if a destructive or credential-sensitive issue is encountered.

## 3. Research work package — do this first

### 3.1 SPX / NeoSPX architecture and naming
Research the design question, not just the name:
- Syntax drivers enable multiple authoring syntaxes.
- Proposed pipeline: authoring syntax → syntax driver → public component document → AST → validation/semantic analysis/compilation.
- Public component document is intended to be stable, language-neutral, and usable by third-party tools.
- Information model should be format-neutral; serialization bindings are separate.
- Compare JSON-first against JSON + YAML from day one.
- Evaluate JSON Schema 2020-12, YAML 1.2.2 typing edge cases, canonicalization, semantic validation, source maps/provenance, error diagnostics, round-tripping/source preservation, extension/versioning strategy, security boundaries, compatibility policy, and conformance fixtures.
- Study existing comparable systems and primary specifications; cite sources, version/date, and limitations. Separate fact from design recommendation.
- Provide example component document and AST only as illustrative proposals, clearly labeled.
- Do not code the runtime, create an ADR, rename SuperPHP (historical), or amend ADR-005.

Naming/collision audit previously reported, to be rechecked:
| Candidate | Prior reported finding | Verification required |
|---|---|---|
| Forge | Collision with CORE-20 “Sovereign Forge” | Inspect current decision register/ADR |
| Foundry | No internal collision found | Search full docs/code/naming registers |
| Nexus | Collision with HUB-17 and HUB-21 | Inspect source records |
| SPX | No internal collision found; possible external acronym collision | Search project and external ecosystem |
| Sovereign | Widely used as Hub-tier prefix | Verify scope and ambiguity |

A previous local draft included SPX as a sixth section; another agenda rendition explicitly said “Handle SuperPHP separately.” Do not resolve that contradiction silently. Deliver a separate SPX report plus a recommendation on how it should be linked from the product-research agenda.

### 3.2 Product research track 1: Platform integrity, copyright, ownership, fair disputes
Investigate:
- abuse, impersonation, fraud, coordinated manipulation
- copyright, licensing, attribution, provenance, derivative works
- takedowns, counterclaims, appeals, fraudulent complaints
- transparent and reviewable moderation
- creator portability and exit
- commercial-incentive distortions
- distinction between ability to enforce a rule and legitimate authority to decide whether it is fair

Output: evidence-backed problem map, relevant laws/platform mechanisms where applicable, design constraints, abuse cases, trade-offs, unresolved questions. Do not turn research into legal advice or an implementation mandate.

### 3.3 Product research track 2: Avoiding the YouTube model
Investigate platform lock-in, centralized control, switching costs, dark patterns, asymmetric commitment, engagement incentives, portability, governance, and creator dependency. Explicitly test the counter-hypothesis: decentralization alone does not solve abuse, copyright, or governance problems.

Output: failure modes, evidence, mitigations and their costs, platform design constraints, unresolved questions.

### 3.4 Product research track 3: Half Exceptions
The phrase is owner-originated and **not an established software-engineering term**. Do not present it as standard terminology. Research two proposed meanings:
1. partial exceptions to general preferences/rules;
2. partially negotiable preferences.

Explore potential analogies—configuration defaults, authorization policy exceptions, feature flags, fault tolerance—without asserting equivalence. Assess scope, authority, priority, expiry, reviewability, auditability, conflicts, inheritance, and revocation. Distinguish a product/design concept from a technical primitive.

Output: terminology caution, two-meaning comparison, analogies and where they break, possible taxonomy, questions for the owner.

### 3.5 Product research track 4: Cancel-anytime paradox
Preserve the owner-supplied sentence verbatim as a **hypothesis, not a verified empirical claim**:
> “Cancel anytime is the most repeated promise in the history of commerce. It is also the one most rarely acted upon.”

Investigate what makes exitability real or illusory: data export, deletion, migration, interoperability, switching costs, contract friction, sunk cost, social/network lock-in, cancellation UX, and retained dependencies. Seek empirical evidence for any behavioral claim; do not infer truth from the quote.

Output: operational definition of exitability, evidence review, product/system design implications, metrics that could test the hypothesis, counterarguments.

### 3.6 Connecting-theme investigation
Three incompatible formulations were reported:
- “How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?”
- Procedural: identify shared concepts and potential implications for DGLab without prematurely creating abstractions.
- “The difference between a right that exists on paper and a right that a person can exercise in practice.”

Do not merge them as if synonymous. Compare whether each is a research question, a research method, or a substantive thesis. Recommend one primary question plus a separate method statement if appropriate, but leave final wording to the owner.

### 3.7 Research output format
For each track:
1. Executive summary (brief, non-prescriptive).
2. Research questions and scope.
3. Evidence table: claim, source, date, population/context, strength/limitations.
4. Counterarguments and failure cases.
5. Possible implications for DGLab (clearly marked as implications, not decisions).
6. Questions requiring owner input.
7. Decisions explicitly **not** made.
8. Bibliography with primary sources prioritized.

Cross-track deliverables:
- Rendition reconciliation matrix V1–V7, including what changed and why.
- Clear separation between four product-research tracks and SPX.
- A proposed canonical agenda draft for review.
- Standalone SPX architecture/naming report.
- An index showing report status and evidence status.

Do not write/push `Architecture/CrossCutting/RESEARCH-AGENDA.md` until the owner explicitly authorizes it. Do not treat local commit `581945d` or patch 0002 as authoritative just because it applies cleanly. Re-check patch applicability only after confirming the artifact exists and the current base SHA. Do not blindly apply it.

## 4. Research review gate

Before LMS implementation resumes, deliver:
- all five research reports (SPX + four product tracks)
- connecting-theme analysis
- V1–V7 reconciliation
- proposed agenda
- source and uncertainty register
- owner decision list

SAAI/owner reviews the research package. The agenda remains a draft until approved. Research findings may inform future architecture, but they do not authorize changes to code, ADRs, policy, or the product roadmap.

## 5. LMS backend — next phase after research gate

### 5.1 Scope
Backend-complete LMS, not polished UI:
- identity and authentication
- authorization/roles
- course CRUD, publishing, discovery, details
- module management
- published course content retrieval
- learner enrollment and queries
- learning progress write/read/list
- stable versioned REST/JSON API
- validation and consistent error response model
- pagination, filtering, authorization and ownership checks
- DI/composition-root wiring and middleware
- schema/migrations, runnable environment
- real-MySQL integration tests and end-to-end flow
- API and frontend handoff docs

### 5.2 Reported state to verify
- PR #364 (published-course discovery) is open and reported CI-green. It adds repository/application discovery (`findPublished(limit, offset)` and application service). Tests were reported mock-based: 63 mock references, 0 PDO. It is not real-DB evidence and should not be reimplemented.
- `ApplicationFactory` was reported to register only `/` and `/health`, with no LMS/identity services in the DI container.
- `AuthMiddleware` was reported present but not wired.
- Enrollment entity/value objects/interface/migration reported present; MySQL repository and application service missing.
- Progress entity/migration reported present; repository interface, MySQL implementation, and application service missing.
- No MySQL service in CI was reported.
- No LMS HTTP controllers/routes or shared JSON response layer were reported.
- Identity domain/application/repository/JWT primitives were reported present but authentication route/wiring is absent. Verify exact current files and approved ADRs before designing transport.
- PR #363 combines a temporary formatting audit workflow and `external/ELQ` dangling-gitlink removal. It is open and requires a merge/separation decision.
- PR #310 is a parked CORE-15 cache PR.
- MySQL test contract proposal exists only as an agent-delivered proposal unless confirmed in repository.

### 5.3 Mandatory decisions before HTTP implementation
Do not assume these are settled:
- same-origin vs separate-origin frontend/backend deployment
- session cookies vs JWT bearer vs both and explicit precedence
- CSRF protection, cookie flags, token lifecycle/revocation, CORS if cross-origin
- authentication identity and role/permission model
- API versioning and DTO/error envelope
- transaction boundaries and idempotency
- pagination limits and validation rules
- MySQL test DSN, migration runner, isolation/reset, CI service.

Do not create ADR-022 before the owner ratifies the authentication choice. A prior suggestion was PHP session cookies for same-origin browser UI, but that remains a proposal, not a decision. Existing ADRs reportedly include ES256 JWT (ADR-003), Argon2id (ADR-008), and post-quantum JWT agility (ADR-012); inspect them and reconcile before proposing auth transport.

### 5.4 Build order
**Gate 0 — current-state review**
1. Re-review current `origin/main`, open PRs, package boundaries, migrations, tests, frozen contracts, ADRs, CI.
2. Review PR #364 diff and tests; decide accept/revise/hold. No duplicate implementation.
3. Reconfirm current LMS capability matrix with four evidence levels: implemented, unit-tested, real-DB integration-tested, end-to-end demonstrated.

**Gate 1 — API/security/test contracts**
4. Owner decides auth transport and same-origin/cross-origin assumption.
5. Approve API versioning, roles/permissions, error envelope, pagination, validation, transactions, and security controls.
6. Ratify or revise the MySQL integration-test contract: `TEST_MYSQL_DSN` shape, migration runner, command, isolation/reset strategy, CI MySQL service, and failure diagnostics.

**Gate 2 — domain/persistence/application**
7. Enrollment repository implementation and integration tests.
8. Enrollment application service and authorization/business-rule tests.
9. Progress repository port, MySQL implementation, migration review, integration tests.
10. Progress application service and tests.
11. Course/module/detail read models and use cases needed by the API; avoid duplicating PR #364.
12. Verify transactions, uniqueness, foreign keys, ordering, concurrency, and ownership invariants.

**Gate 3 — HTTP/API**
13. Composition-root and DI registration.
14. JSON request parsing/response serialization, DTOs, validation, consistent error taxonomy.
15. Login/logout/session/token endpoint and middleware according to approved ADR.
16. Public course discovery/detail routes.
17. Admin course/module CRUD and publish routes with role checks.
18. Enrollment routes and learner/course access rules.
19. Progress routes with enrollment and content authorization.
20. API contract tests and OpenAPI or equivalent documentation.

**Gate 4 — real verification**
21. Add MySQL 8 CI service and run migrations in isolated test DB.
22. Real-MySQL integration tests for every repository and transaction boundary.
23. End-to-end journey: authenticate as admin → create course → add modules/content → publish → discover/detail → authenticate as learner → enroll → retrieve content → record/read progress.
24. Negative tests: unauthenticated, unauthorized role, cross-user access, unpublished course, invalid payload, duplicate enrollment, missing records, malformed IDs, database failure, pagination bounds, CSRF/CORS behavior as applicable.
25. Independent review, fix findings, owner acceptance and merge.

**Gate 5 — founder handoff**
26. Provide current commit and capability matrix, local setup, env var reference, migrations, API spec, sample curl requests/responses, auth instructions, error catalogue, test commands, known limitations, and extension points.
27. Ask Claude to independently review the code; do not frame the task as approval or rubber-stamping.

### 5.5 Implementation ownership
SDLC v5.1 applies:
- one designated implementer per implementation task by default
- reviewers are read-only and report findings
- parallel implementation only with explicit Tech Lead authorization and documented boundaries/integration owner/test plan
- research and independent review can run in parallel
- each PR states scope, non-goals, tests, evidence, risks, and remaining decisions
- no direct push to main, no self-merge, no implicit merge authorization.

## 6. Other pending records and owner decisions

Maintain a live register with status/evidence/owner/next action:
1. **PR #363 / `external/ELQ`:** choose separation/merge route for gitlink removal versus temporary formatting audit workflow.
2. **D-01 / S-003:** re-scope closure condition (c) or commission `PulseFiberIsolationTest.php`.
3. **Auth / ADR-022:** owner decision; blocks HTTP routes.
4. **MySQL integration test contract:** owner verdict.
5. **Research agenda:** reconcile V1–V7 and authorize publishing.
6. **`82ddde7` SKILL.md provenance:** determine authorization before reapplying.
7. **PR #364:** review implementation and mock-only evidence; owner decides merge.
8. **Formatting artifact/scope:** recover artifact if possible; formatter exit 8 means drift exists but does not identify exact files.
9. **Temporary formatting workflow:** keep/delete/separate decision.
10. **Readability Phase A and Phase C:** review audit; no formatter run or formatting changes without authorization.
11. **D-09 PSR-4 collision vs register corrections vs ADR-011:** verify live defect and prioritize.
12. **PR #310:** resume or leave parked.
13. **Stale credential-bearing Git config:** handle as a separate security hygiene issue; never print the secret, revoke/rotate if active, and remove credential-bearing config safely.
14. **Other register/ADR issues:** D-01–D-12 reconciliation, stale ADR-011 status, historical CORE-02 references, license metadata, and findings omitted from register. Do not edit frozen governance records without scoped owner authorization.

## 7. Founder / Claude handoff package

Claude will analyze the code after the founder receives it. Prepare a skeptical-review package:
- repository commit SHA and branch
- system map and dependency boundaries
- authoritative ADR/frozen-contract index
- feature/API matrix separating implementation, unit tests, MySQL integration tests, and E2E evidence
- schema/migration overview
- auth/session/JWT/cookie/CSRF/CORS/authorization behavior
- transaction and data-integrity invariants
- API contract, DTOs, validation and error examples
- exact setup and test commands
- CI links/results and limitations
- known gaps and unresolved decisions
- list of files changed by each PR

### Suggested Claude review prompt
“You are an independent senior architect and security reviewer. Do not assume the implementation is correct because tests pass or because this handoff claims a feature is complete. Inspect the code and tests. Verify each claim against the repository and label findings by severity with file/path and reproduction evidence. Focus on architecture-boundary violations, broken dependency direction, missing authorization and cross-user access, session/JWT/CSRF/CORS mistakes, transaction/data-integrity bugs, race conditions, API-contract inconsistencies, migrations and rollback safety, validation/error leaks, pagination abuse, missing real-MySQL coverage, and mismatch between the capability matrix and actual evidence. Identify both confirmed defects and questions requiring runtime verification. Do not modify files or merge anything; return findings and a prioritized test plan.”

## 8. Stop conditions
Stop and ask the owner/SAAI when:
- a choice changes an approved architecture contract or ADR
- a frozen contract must change
- an agenda or governance record is to be published
- implementation must be parallelized
- a merge, push, rebase, branch deletion, formatter run, or destructive DB operation is proposed
- credential exposure is suspected
- a current repository fact conflicts with this handoff materially.

Otherwise, continue with safe read-only research and produce reviewable drafts. Report exactly what is done and what remains.
