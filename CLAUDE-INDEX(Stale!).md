# DGLab Claude Session Context Index

> **Purpose:** Onboard a fresh Claude session to DGLab without relying
> on prior conversation history.
>
> **Status:** Draft bootstrap based on the founder/agent handoff
> available to SAAI. It has **not** been generated from a live
> repository checkout. Verify all paths, code, remote refs, PR heads,
> and CI results before relying on them.
>
> **Prepared:** 2026-10-22\
> **Owner:** Founder / DGLab maintainers

## 1. Start here

This index is a navigation aid, not a substitute for the repository's
active instructions or current source code.

### First-session checklist

1.  Confirm the repository root and current branch.
2.  Start with read-only Git checks:
    -   `git status --short --branch`
    -   `git branch --show-current`
    -   `git log -1 --oneline --decorate`
    -   `git remote -v` (do not expose credentials embedded in remote
        URLs)
3.  If authorized and network access is available, run
    `git fetch origin` to refresh remote-tracking refs; do not merge
    anything.
4.  Check `git status --short --branch` again. Do not reset, clean,
    rebase, cherry-pick, merge, force-push, or discard local changes.
5.  Read root `AGENTS.md` and all applicable nested `AGENTS.md` files.
    Check whether `CLAUDE.md`, `.claude/` rules, or project settings
    affect which instructions load.
6.  Find and read the approved SDLC v5.1 governance source.
7.  Verify current `main`, relevant branches, open PRs, remote heads,
    and latest CI runs.
8.  Trace important claims to current source code and tests.
9.  Report verified facts, reported claims, open risks, and the next
    decision needed. Ask no more than two focused questions at a time.

## 2. Evidence labels

Use these labels consistently:

-   **VERIFIED** --- checked directly against current source, Git state,
    CI output, or another named primary source.
-   **APPROVED** --- explicitly accepted by the founder or an authorized
    governance process.
-   **REPORTED** --- stated by an agent or handoff but not independently
    verified.
-   **PROPOSED** --- suggested, not approved.
-   **HISTORICAL** --- useful past context, not necessarily current.
-   **UNVERIFIED** --- evidence is unavailable or insufficient.

For important claims, include a path, symbol, commit SHA, CI URL, or
other evidence and a verification date. Repeating a reported claim does
not make it verified.

## 3. Project identity and mission

### Reported context

-   **REPORTED:** DGLab is a PHP 8.4 monorepo referred to in handoffs as
    the "Sovereign Stack."
-   **REPORTED:** The founder is pursuing a full LMS backend and owns
    product direction and frontend/UI/UX.
-   **REPORTED:** Kilo Code is the primary implementation agent. Claude
    may contribute backend work when given a clearly scoped,
    independently testable task. Z.ai may be assigned research or
    review. SAAI coordinates architecture, contracts, acceptance
    criteria, and independent review.
-   **UNVERIFIED:** Exact product boundaries, package inventory,
    runtime/deployment topology, supported database versions, and
    current LMS roadmap must be confirmed from repository documentation
    and the founder.

Verify the root README, package manifests, architecture overview,
product specifications, roadmap, and relevant decision records. Add
verified relative links to the file index below.

## 4. Governance and roles

The following rules are reported from prior handoffs and must be checked
against the current approved governance documents:

-   **Founder:** owns priorities, scope, product decisions, acceptance,
    and merge authorization.
-   **Kilo Code:** primary implementer on assigned work.
-   **Claude:** implements only an explicitly authorized scope. Confirm
    contracts and ownership before changing shared interfaces,
    migrations, dependency injection, or common services.
-   **Z.ai:** research, analysis, or review support as assigned.
-   **SAAI:** architecture coordination, technical contracts, acceptance
    criteria, and independent review.
-   Do not run parallel implementation without explicit authorization.
-   Reviewers should not silently modify the implementation they review.
-   Separate worktrees isolate working directories but do not resolve
    incompatible shared-contract changes.
-   CI passing is evidence, not proof of correctness.
-   Technical completion does not grant merge authorization.
-   Never expose tokens, credentials, private keys, or other secrets in
    prompts, logs, commits, or handoffs.

**Source-of-truth hierarchy:** Determine the actual approved hierarchy
from repository governance. Check active instructions, approved
governance, accepted ADRs/specifications, current implementation and
tests, then historical notes and agent reports. If sources conflict,
surface the conflict instead of silently selecting a winner.

## 5. Architecture and technical foundations

Discover and verify the actual repository paths. Do not invent paths or
link to nonexistent files.

  -------------------------------------------------------------------------------------------------
  Topic                   Locate the authoritative file(s)                  What to establish
  ----------------------- ------------------------------------------------- -----------------------
  Instructions            Root/nested `AGENTS.md`, any `CLAUDE.md`,         Which instructions
                          `.claude/` rules                                  apply and how they load

  SDLC/governance         Approved SDLC v5.1 and policy documents           Roles, gates, review,
                                                                            acceptance, merge
                                                                            authority

  Architecture            Current architecture overview/design              System boundaries and
                                                                            component
                                                                            responsibilities

  ADRs                    Accepted architecture decision records            Decisions, rationale,
                                                                            status, superseded
                                                                            records

  Dependency boundaries   Architecture rules, fitness tests, boundary-lint  Allowed dependencies
                          config                                            and enforcement

  Dependency injection    DI/container docs and composition roots           Registration,
                                                                            lifecycle,
                                                                            bootstrapping

  PHP/tooling             `composer.json`, package manifests,               Supported versions and
                          PHPStan/PHPUnit config                            canonical commands

  Database                Migration configuration, migrations, schema docs  Schema ownership,
                                                                            migration order, MySQL
                                                                            support

  Authentication          Auth design, identity interfaces,                 Trusted actor identity
                          middleware/context                                and authorization

  API entry points        Routes/controllers/bootstrap/ApplicationFactory   Existing entry points
                                                                            and missing wiring

  CI                      `.github/workflows/` and invoked scripts          What CI executes,
                                                                            skips, or excludes

  Research                Research index and research documents             Findings versus
                                                                            approved decisions
  -------------------------------------------------------------------------------------------------

For each file added to the index, record its exact relative path,
purpose, authority/status, when Claude should read it, and dependencies.

## 6. LMS implementation map

Build a source-backed map of the actual LMS implementation. Search the
repository rather than assuming directory names.

  --------------------------------------------------------------------------
  Layer                   Locate                     Verify
  ----------------------- -------------------------- -----------------------
  Domain                  Course, Enrollment,        Where invariants are
                          Progress entities/value    enforced
                          objects/enums/exceptions   

  Application             Progress/enrollment        End-to-end application
                          services, commands,        execution path
                          handlers                   

  Persistence contracts   Repository interfaces      Interface alignment
                                                     with use cases

  MySQL adapters          Repository implementations SQL, transaction, and
                          and connection setup       lock semantics

  Schema                  LMS migrations and test    Primary keys and
                          schema setup               nullable-field
                                                     behaviour

  Composition             DI registrations and       Production
                          application factories      construction/wiring

  API                     Routes, controllers,       Whether HTTP/API entry
                          request validation, actor  points are implemented
                          context                    

  Tests                   Unit, integration,         What each testing layer
                          architecture, end-to-end   proves

  CI                      Package and integration    Whether tests run
                          workflows                  against MySQL or skip
  --------------------------------------------------------------------------

### Progress persistence requirements to verify

These constraints were reported in prior reviews; verify them against
approved specifications, current code, and tests:

1.  A course must be in the required published state before progress can
    be recorded.
2.  Enrollment must exist and be in the required active state.
3.  The module must belong to the course associated with the enrollment.
4.  Actor identity must come from trusted authentication context, not
    merely from an ID in a request DTO.
5.  The domain transition function is authoritative for permitted
    progress-state transitions.
6.  A completed module must not regress to `in_progress` or
    `not_started`.
7.  A `not_started` request may be a no-op if no row exists; if a row
    exists, it must pass through domain transition rules.
8.  The composite progress key was reported as
    `(enrollment_id, module_id)`; verify the migration.
9.  Nullable `started_at` must remain nullable during hydration; do not
    fabricate timestamps.
10. Duplicate-key recovery should handle only the expected MySQL
    duplicate-key error, reload current state with an appropriate
    locking read, reapply domain transition rules, and commit or roll
    back correctly.
11. Mock tests do not prove MySQL locking, transaction isolation, or
    concurrent insert behaviour.
12. Genuine concurrency tests must use distinct connections/processes
    and assert persisted outcomes rather than simulate requests
    sequentially.

For every requirement, separately record: **Implemented**,
**Unit-tested**, **MySQL integration-tested**, **CI-executed**, or
**UNVERIFIED**.

## 7. Git and pull-request status

**Everything in this section is historical handoff information until
refreshed from Git/GitHub.**

### Previously reported state

-   `main` was reported at `6620788a8f17dc9392c50ac5076892cb8739dd55` on
    2026-10-22.
-   PR #368, branch `docs/update-agents-md-status`, was reported at
    `c1baea799ecf48064f91e8a478d183de41a2109a`.
-   PR #369, branch `lms-progress-persistence`, was reported at
    `f985a180e8866e6d1de424eb7d05ceebcc035ad9`.
-   Both PRs were reported open and unmerged.
-   PR #369 was reported to have a successful CI run identified as
    `39744759924`, with 22 jobs passing.
-   Earlier handoffs described discrepancies between reported commit
    SHAs and the GitHub web UI. Independent remote verification is
    therefore especially important.

### Required verification for each PR

-   Actual remote branch SHA and PR head SHA.
-   Current commit list and final diff.
-   Review comments and unresolved conversations.
-   Latest CI run associated with the current head SHA.
-   Whether MySQL integration tests ran, passed, or skipped.
-   Whether cache artifacts remain tracked.
-   Whether the branch is behind or conflicts with `main`.

Do not merge, rebase, reset, or force-push during context gathering.

## 8. Risk and decision register

Rebuild this register from current evidence. Remove or update rows once
verified.

  ------------------------------------------------------------------------------------------
  ID             Issue                       Evidence        Status from    Next action
                                             required        available      
                                                             handoff        
  -------------- --------------------------- --------------- -------------- ----------------
  R-01           Current PR heads and CI     Live Git/GitHub UNVERIFIED     Refresh before
                 state                       verification                   work

  R-02           Progress concurrency        PR #369 code,   REPORTED,      Inspect test and
                 guarantees                  MySQL tests and recheck        SQL behaviour
                                             CI                             

  R-03           Authentication transport    ADR/design      UNVERIFIED     Confirm if still
                 decision                    records;                       open
                                             earlier handoff                
                                             said ADR-022                   
                                             may be absent                  

  R-04           MySQL version/isolation     Workflow and    REPORTED,      Verify
                                             integration     recheck        configuration
                                             configuration                  and execution

  R-05           HTTP                        Current source  UNVERIFIED     Establish
                 routes/ApplicationFactory   search                         implementation
                 wiring                                                     state

  R-06           Governance/document         `AGENTS.md`,    UNVERIFIED     Resolve
                 consistency                 SDLC and policy                conflicts with
                                             docs                           evidence
  ------------------------------------------------------------------------------------------

For each issue record evidence/source, impact, owner, and next action.
Do not carry historical blockers forward as current facts without
rechecking them.

## 9. Founder roadmap and scope boundaries

### Suitable first steps for Claude

-   Conduct read-only repository reconnaissance.
-   Read applicable instructions and architecture decisions.
-   Trace a narrowly scoped use case end to end.
-   Identify mismatches between specifications, implementation, and
    tests.
-   Propose a change plan listing touched files, contracts, risks, and
    acceptance criteria.
-   Implement only after the founder explicitly authorizes the scope and
    required shared interfaces are agreed.

### Requires founder authorization or an explicit decision

-   Product scope or priority changes.
-   Choosing unresolved authentication transport or other architectural
    alternatives.
-   Changing shared contracts, migrations, DI/composition, or common
    services while another implementer is active.
-   Starting parallel implementation.
-   Accepting a feature as complete.
-   Merging PRs, rebasing shared branches, force-pushing, or destructive
    Git operations.

### First-session report

Claude should provide: 1. Verified repository state and local
modifications. 2. Applicable instructions and governance sources. 3.
Relevant architecture and implementation files. 4. Current PR/CI state
with evidence. 5. Findings separated into verified, reported, proposed,
historical, and unverified. 6. At most two focused questions if a
founder decision is needed. 7. A proposed next task with acceptance
criteria, without assuming authorization.

## 10. Detailed context file index

Populate with exact relative links after inspecting the working tree.
Never present guessed paths as verified.

### Minimum reading set for every session

-   Root `AGENTS.md` and applicable nested instructions.
-   Approved SDLC v5.1 governance source.
-   Root README/project overview.
-   This `CLAUDE-INDEX.md`.
-   Current architecture overview and accepted ADRs relevant to the
    task.

### Task-specific expanded reading sets

-   **Progress persistence:** progress domain model, progress
    application service, repository interface, MySQL repository, LMS
    migrations, unit tests, MySQL concurrency tests, workflow that runs
    them.
-   **Authentication/authorization:** approved auth ADR/spec, identity
    abstraction, actor context, authorization policy, API middleware,
    related tests.
-   **API wiring:** route definitions, controllers/handlers, request
    validation, application factory/composition root, DI registrations,
    HTTP integration tests.
-   **Schema change:** migration rules, existing LMS schema, repository
    adapter, migration tests, integration fixtures, CI database service.
-   **Architecture change:** accepted ADRs, dependency rules,
    architecture-fitness tests, boundary lint, DI documentation,
    impacted package tests.
-   **CI failure:** exact workflow, invoked scripts, failing job logs,
    package-level config, integration-test configuration, triggering
    commit SHA.

For every file, record: - Exact repository-relative path and Markdown
link. - Purpose. - Authority/status: approved, current implementation,
proposal, historical, or unverified. - Why and when Claude should read
it. - Related files. - Date last checked.

## 11. Claude Code context loading

Do not assume `CLAUDE-INDEX.md` is automatically loaded by Claude Code.

Inspect the actual repository instruction setup and the founder's Claude
environment before proposing automatic-loading configuration. Anthropic
documents `CLAUDE.md` as a project instruction file automatically read
by Claude Code; `AGENTS.md` support depends on Claude Code version and
project-instruction settings. See the official [Claude Help Center
guide](https://support.claude.com/en/articles/14553240-give-claude-context-claude-md-and-better-prompts)
and [Claude Code guidance on instruction
mechanisms](https://claude.com/blog/steering-claude-code-skills-hooks-subagents-and-more).

If a root `CLAUDE.md` is later authorized, keep it concise and use it to
point to applicable governance instructions and this index. Do not
silently create or modify it as part of this index task.

## 12. Ready-to-paste Claude opening prompt

> You are joining DGLab with no prior conversation context. Start by
> reading `CLAUDE-INDEX.md`, root and applicable nested `AGENTS.md`
> instructions, and the approved SDLC/governance documents. Verify
> current Git state and relevant remote PR/CI state before relying on
> handoff claims. Do not reset, clean, rebase, merge, force-push, or
> discard changes. Trace requirements to current source files and tests.
> Label findings VERIFIED, APPROVED, REPORTED, PROPOSED, HISTORICAL, or
> UNVERIFIED. The founder controls scope, priorities, acceptance,
> architectural decisions, and merge authorization. Do not implement
> until the scope is explicitly authorized. Avoid parallel
> implementation and coordinate shared contracts first. Ask no more than
> two focused questions at a time. First provide a concise onboarding
> report covering repository state, instructions, architecture map,
> PR/CI status, risks, and a proposed next task with acceptance
> criteria.

## 13. Maintenance

At the start of each session: - Refresh Git, PR and CI state. - Check
whether governance or ADRs changed. - Revalidate claims that can go
stale. - Update the last-verified date only for sections actually
checked. - Preserve the distinction between historical handoff
information and verified current state.

After a substantive task, update relevant index entries or linked
documentation only when the update is within the authorized scope.
