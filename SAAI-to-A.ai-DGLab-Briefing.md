# SAAI → A.ai: DGLab Project Context, Architecture, Governance, and Synchronization Briefing

- **From:** SAAI — Software Architecture GPT
- **To:** A.ai — Architecture Reviewer and Implementation Agent
- **Project:** DGCodeIdeas/DGLab, also called Sovereign Stack
- **Purpose:** Establish shared context, prevent architectural drift, align implementation work, and ensure all human and AI contributors operate from the same authoritative project state.

Welcome to the project.

You are joining an existing architecture and implementation effort. Your independent perspective is useful, particularly for detecting defects, uncovering assumptions, and testing whether the implementation matches the intended architecture.

However, you are not joining an empty repository, and your audit does not automatically supersede previous decisions, established priorities, or work already completed by Z.ai.

The objective is not to make every agent think identically. It is to ensure that every agent works toward the same approved objectives, respects the same contracts, communicates changes, and leaves the project in a state that the next contributor can understand.

This briefing establishes the context and working principles needed to accomplish that.

**Evidence disclaimer:** SAAI is working from the conversation history and reports supplied by the project owner, including reports from Z.ai and A.ai. SAAI has not independently inspected the repository, Git history, PR diffs, or CI runs in this conversation. Statements about the current repository state are therefore identified as reported rather than independently verified. When you inspect the repository, distinguish actual observations from inherited reports.

---

## 1. The Project and Its Purpose

DGLab is a PHP-based platform and framework monorepo. Its broader architectural vision is called Sovereign Stack.

The reported technical baseline includes:

- PHP 8.4 as the intended runtime baseline.
- A custom Core/Hub/Spoke package architecture.
- Multiple Composer packages with explicit architectural boundaries.
- Shared infrastructure capabilities intended to be reused by higher-level products.
- Blueprints and architectural documentation that describe capabilities not necessarily implemented yet.
- CI checks, static analysis, automated tests, architecture rules, and a shortcomings register.
- Architecture Decision Records (ADRs) and frozen contracts intended to prevent accidental architectural changes.
- Product functionality being developed on top of the shared platform.

The goal is not merely to accumulate PHP classes or make individual test suites pass.

The goal is to build a coherent platform in which package responsibilities, interfaces, dependencies, security properties, implementation behavior, and governance documentation agree with one another.

### The central architectural principle

The project has an important guiding principle:

> Compose downward, abstract upward, and expose only the capability contract appropriate to the consumer.

Interpret this carefully.

- **Compose downward:** Higher-level applications should assemble existing lower-level capabilities rather than repeatedly implementing the same infrastructure.
- **Abstract upward:** A reusable capability should expose an appropriate contract rather than leaking unnecessary implementation details.
- **Expose only the appropriate contract:** Consumers should depend on the intended interface, not incidental details of a concrete implementation.
- **Preserve boundaries:** A higher-level package should not gain arbitrary access to lower-level internals merely because doing so is convenient.

When examining an implementation, do not ask only whether it works. Ask whether it works through the intended contract and within the intended architectural boundary.

### The strategic direction

The project's strategic direction has been expressed as:

**Canonical State → Deterministic Derivation → CI-Enforced Consistency**

The intended meaning is:

1. Maintain a clear authoritative source for each important piece of information.
2. Derive dependent information deterministically from that source where practical.
3. Use automated checks to detect divergence.
4. Make failures visible instead of allowing controls to report success while the underlying state is inconsistent.

This applies to code, architecture documentation, package inventories, release metadata, test coverage, and governance records.

There is an important qualification: governance should support delivery, not become an excuse to prevent an MVP from being implemented. Apply controls proportionately, and distinguish essential safeguards from future improvements.

---

## 2. The Human Owner and the Roles of the Agents

The project has a human owner who determines product priorities, resolves trade-offs, and approves consequential architectural decisions.

The agents have different contributions, but they are not competing authorities.

### Human owner

The human owner:

- Establishes the product vision and priorities.
- Approves material architectural decisions and exceptions.
- Resolves conflicting recommendations.
- Decides when to freeze, change, or supersede an architecture contract.
- Determines which trade-offs are acceptable.
- Decides licensing and other consequential policy questions.

Do not treat the existence of a technically attractive alternative as approval to replace an existing decision.

### SAAI

SAAI is the architecture consultation and coordination role.

My responsibilities are to:

- Preserve architectural context across discussions.
- Help the owner reason through design decisions.
- Identify conflicts between implementation and documented intent.
- Distinguish requirements, assumptions, proposals, and approved decisions.
- Help keep implementation work consistent with the project's architecture.
- Identify missing evidence and unresolved risks.
- Produce actionable architectural specifications when sufficient context has been established.
- Help synchronize agent handoffs and prevent repeated work or competing plans.

SAAI does not have direct repository access merely because a report mentions a repository file or PR. I must not claim to have independently verified a change unless I actually have the relevant evidence.

### Z.ai

Z.ai has been implementing the project for some time and holds substantial historical context from that work.

It has previously reported work involving:

- LMS implementation.
- Database repository corrections.
- Hub Identity.
- Shared database contracts.
- Package CI integration.
- Documentation and governance coordination.

Its longer history matters because an apparent defect may already have been investigated, a task may already be underway, or a proposed change may conflict with an implementation in progress.

Z.ai should not be treated as infallible. Its reports and changes still need verification. Equally, its existing work must not be disregarded simply because a newly joined agent discovers a different approach.

### A.ai

A.ai is both a reviewer and an implementer.

That means you can independently:

- Audit implementation against requirements and contracts.
- Identify defects and missing coverage.
- Propose technical improvements.
- Implement approved, appropriately scoped changes.
- Add tests that expose real defects.
- Improve documentation and CI enforcement.
- Challenge assumptions with evidence.

However, you should not independently establish a competing architecture or roadmap.

Your mandate is to improve the existing system, not silently redefine it.

A new defect discovery may justify changing implementation priorities, but the impact should be assessed and communicated before expanding the scope of work.

---

## 3. The Rules for Architectural Authority

DGLab uses governance documents and ADRs to preserve decisions over time.

The following distinction is fundamental.

### 3.1 Approved decisions are not casual suggestions

An approved ADR or frozen contract is the current governing decision until it is explicitly superseded through the project's decision process.

If you discover a better alternative:

1. Document the current decision.
2. Explain the limitation or defect in the current approach.
3. Present the proposed alternative.
4. Identify affected packages, interfaces, tests, and documentation.
5. Explain migration and compatibility implications.
6. Request approval where the change crosses an established architectural boundary.
7. Implement the approved direction.
8. Update the authoritative records as required.

Do not silently modify a frozen contract to make a failing implementation pass.

Do not rewrite an ADR merely because a different approach seems cleaner.

Do not assume that a newly discovered implementation defect proves the underlying architecture is wrong.

Sometimes the correct fix is simply to make the implementation comply with the existing interface.

### 3.2 Distinguish four kinds of information

Throughout your work, explicitly distinguish:

#### A. Established requirement

A behavior the project is expected to provide.

#### B. Approved architectural decision

An agreed approach or boundary that governs implementation.

#### C. Reported repository state

A claim from a worklog, agent report, PR description, or previous discussion that still needs verification when current correctness matters.

#### D. New proposal

A recommendation that has not yet been approved.

These categories must not be conflated.

For example, "A.ai recommends PostgreSQL" would not supersede a recorded decision to use MySQL. It would be a proposal requiring an explicit decision.

Likewise, a test passing against a mock does not establish that the real database integration works.

### 3.3 Important reported governance artifacts

The following files have been mentioned as important project records:

- "Architecture/OPEN-DECISIONS.md"
- "Architecture/FROZEN-CONTRACTS.md"
- "Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md"
- "Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md"
- "Architecture/CrossCutting/OBSERVABILITY.md"
- "Architecture/CrossCutting/THREAT_MODEL.md"
- "Architecture/CrossCutting/DGLAB-AS-OS.md"
- "Verification/SHORTCOMINGS-REGISTER.md"

There are also reported ADRs 001–021.

These paths and the described contents come from prior reports. Verify the actual current files and their contents before relying on them.

In particular:

- Read the open decisions before starting architectural work.
- Read the frozen contracts before modifying shared interfaces.
- Read the relevant ADR before changing an established technology choice.
- Read the shortcomings register before adding, closing, or reclassifying a finding.
- Read the relevant cross-cutting doctrine before proposing changes to security, observability, governance, or other system-wide behavior.

Avoid creating duplicate documents that express the same policy with slightly different wording.

If a required record is missing or contradictory, identify the conflict and propose a correction rather than inventing a parallel source of truth.

---

## 4. Important Existing Architecture Decisions

The following decisions have been reported in prior discussions. Verify their current status and wording in the repository before making changes.

| ADR | Reported subject | Practical implication |
|---|---|---|
| ADR-003 | ES256 JWT | Preserve the approved JWT signing algorithm unless the decision is formally changed. |
| ADR-005 | SuperPHP versus Blade/Twig | SuperPHP is the selected direction for the custom template language; do not silently substitute another engine. |
| ADR-008 | Argon2id | Preserve the approved password-hashing approach. |
| ADR-011 | Status needs reconciliation | Reports indicate contradictory status information across several documents. Verify before editing. |
| ADR-012 | Post-quantum JWT agility | Preserve the intended cryptographic agility requirements. |
| ADR-013 | MySQL as primary datastore | A.ai has reported that this supersedes ADR-007's PostgreSQL choice. Verify the actual supersession record. |
| ADR-017 | FrankenPHP worker mode | Respect the intended runtime direction and its lifecycle implications. |
| ADR-019 | Versioning | The reported version format has four numeric components, with an additional build or commit identifier in some representations. Verify the actual contract. |
| ADR-021 | Two-DAG model | The reported model distinguishes Declared from Verified state. Preserve that distinction. |

This list is a starting point, not a substitute for reading the ADRs.

### The two-DAG model

ADR-021 has been described as separating:

- **Declared state:** What the project says should exist or be true.
- **Verified state:** What has actually been demonstrated to exist or be true.

This distinction is particularly important for a repository with many architectural documents, blueprints, CI controls, and implementation claims.

A blueprint is not an implementation.

A test file is not proof that the test runs.

A CI matrix entry is not proof that the relevant package passes CI.

A mocked database test is not proof that the real database behaves correctly.

A documented security control is not proof that the control is enforced.

A merged PR description is not sufficient evidence for every behavioral claim in that description.

Where practical, verification should be executable and reproducible.

---

## 5. The MVP and SuperPHP Context

The project has an LMS MVP under development, alongside the shared platform infrastructure.

The reported MVP assumptions are:

- Shared platform and shared database.
- Single deployment.
- Product-namespaced tables.
- Reuse of existing Core and Hub capabilities.
- Minimal server-rendered PHP interface.
- No JavaScript framework requirement.
- MySQL for persisted course and module data.
- A straightforward browser flow for administrators and learners.

The intended basic LMS flow is:

1. An administrator creates a course.
2. The administrator adds modules.
3. The administrator publishes the course.
4. A learner authenticates.
5. The learner views a course and its modules.
6. The learner enrolls.
7. The learner records progress.
8. The learner can view that progress.

The initial module content can be stored as text in MySQL for demonstration purposes, provided the data model and authorization rules support the required behavior.

Do not prematurely introduce a distributed architecture, separate deployment, complex frontend framework, or additional datastore without a concrete requirement.

### SuperPHP

SuperPHP is a particularly important example of the distinction between architectural intent and current implementation.

The reported position is:

- ADR-005 selected the custom template-language direction over Blade/Twig.
- SuperPHP is associated with planned components CORE-07 Lexer, CORE-11 Parser, and CORE-12 Compiler.
- These components have been described as blueprints rather than a completed runtime.
- The current application reportedly does not yet have a functioning SuperPHP runtime wired into "Kernel" or "ApplicationFactory".
- Plain PHP templates are currently considered sufficient for the MVP.
- The project owner considers SuperPHP a core necessity, even though the LMS MVP does not yet require its full runtime.

These are not contradictory statements.

The architectural direction can be approved while implementation remains incomplete.

The MVP can use a temporary, appropriate rendering path without pretending SuperPHP exists. Conversely, the fact that plain PHP is sufficient for the first demo does not authorize abandoning SuperPHP.

The correct approach is to make the implementation status explicit and plan the runtime work according to the owner's priorities.

### Naming

The owner has considered renaming SuperPHP but has not approved a replacement.

Names discussed have included Forge, Foundry, Nexus, SPX, and Sovereign.

There are possible collisions with existing project terminology, including CORE-20 Sovereign Forge.

Do not rename SuperPHP, edit ADR-005, or create new naming conventions without a deliberate naming and collision review and the owner's approval.

---

## 6. Shared Contracts and the Recurring DBAL Problem

One of the most important implementation lessons is that package implementations have reportedly drifted from the frozen database connection contract.

The project uses a shared "ConnectionInterface" whose reported method set is:

- "prepare"
- "query"
- "exec"
- "beginTransaction"
- "commit"
- "rollBack"
- "lastInsertId"
- "quote"
- "getTransactionNestingLevel"

The exact current interface must be read from the repository.

The reported problem is that individual repositories attempted to use methods such as "fetchOne()" and "execute()" directly on "ConnectionInterface", even though those methods were not part of the frozen contract.

Similar issues were reported in LMS, Hub Identity, and the internal Showcase Spoke.

This pattern matters because it may indicate a systemic problem rather than three unrelated mistakes.

The project needs to establish that implementations consume the canonical contract consistently.

When a new defect is found:

1. Inspect the actual interface.
2. Inspect the implementation that violates it.
3. Check other packages for the same pattern.
4. Fix the implementation against the established contract where appropriate.
5. Add regression tests that would fail if the invalid usage returned.
6. Verify the relevant package is included in CI.
7. Consider a reusable enforcement mechanism if the pattern recurs.
8. Update documentation only where the authoritative contract or its explanation actually needs correction.

Do not add convenient methods to a frozen interface simply to accommodate one implementation.

Do not make every package invent its own database abstraction.

Do not claim end-to-end database verification from unit tests alone.

### Reported PR #355

Z.ai previously reported PR #355, commit "9b9f576", as correcting the LMS "MySQLCourseRepository" to use the frozen connection and statement API. It reportedly included tests and passed 19 CI checks.

### Reported PR #356

Z.ai previously reported PR #356, commit "15805f4", as implementing LMS modules, including:

- A "module_content" table.
- A module repository.
- An "AddModule" command.
- Draft-only module changes.
- Transactional publishing.
- A "ModuleId" equality implementation.
- LMS integration into the CI matrix.

It reportedly passed 20 CI checks.

### Reported PR #357

Z.ai reported PR #357, commit "a604241", titled:

"fix(hub-identity): DBAL contract drift + CI + PHPStan + PHPUnit fixes (#357)"

The reported changes included:

- Correcting "MySQLUserRepository" to use the established "prepare()" and statement APIs.
- Correcting "rollBack()" casing.
- Adding "MySQLUserRepositoryTest.php", reportedly with seven tests.
- Adding "hub/identity" to ".github/workflows/packages-ci.yml".
- Keeping the frozen connection contract unchanged.

The report also said Composer was unavailable in Z.ai's environment, so tests were not rerun there; GitHub Actions was reported as passing.

The absence of real-MySQL integration testing was explicitly identified as a limitation.

These PR descriptions are historical reports, not independently verified facts in this briefing. Inspect their actual merged status, diffs, and CI evidence before relying on them.

The larger lesson is that shared contract drift should be detected systematically, not rediscovered independently by each agent.

---

## 7. Authentication and Security Context

The reported identity architecture is:

"IdentityInterface → IdentityApplicationService → UserRepositoryInterface → MySQLUserRepository → ConnectionInterface"

Verify the actual implementation before changing it.

Important previously identified requirements and concerns include:

### Authentication precedence

The application needs explicit behavior for browser sessions versus bearer JWT authentication.

Do not let the resulting identity depend accidentally on middleware ordering.

Define and test which credential source takes precedence, what happens when credentials conflict, and how invalid credentials are handled.

### Public versus protected routes

The reported "AuthMiddleware" is PSR-15-based, permits unauthenticated requests to continue, and sets a nullable "userId".

That can be appropriate as part of an authentication context mechanism, but it is not sufficient by itself to protect sensitive routes.

Public routes such as login and health checks must remain accessible according to their intended policy. Protected routes must explicitly enforce authentication and authorization.

Do not solve this by indiscriminately blocking every unauthenticated request.

### JWT key management

JWT signing keys should come from an appropriate configured secret-management mechanism.

Do not generate signing keys on every application boot.

The application must maintain stable, intentional signing and verification behavior.

### Database integration

A repository unit test using mocks does not prove browser login works with a real MySQL instance.

Real database integration should verify the actual schema, prepared-statement behavior, persistence, password verification, session or token issuance, and the relevant error paths.

Report precisely which layers have been tested.

### Security controls versus security claims

The project has reported cross-cutting threat-model and observability documents, alongside security-related components that may be partially implemented.

Do not treat a class name, blueprint, configuration placeholder, or pass-through stub as an effective security control.

For every important control, determine:

- What threat it is intended to mitigate.
- Where enforcement occurs.
- What happens on failure.
- Whether failure is observable.
- Which tests demonstrate the behavior.
- Whether the control is active in the deployed configuration.

Do not claim that a system is secure merely because its design document lists security controls.

---

## 8. Your Previous Repository Audit and the Changes You Reported

Your initial audit was reported to have identified substantial gaps in implementation, testing, documentation, release automation, licensing consistency, and secret handling.

Those findings are valuable, but their implications must be managed carefully.

### Reported repository inventory

The audit report described approximately:

- 1,699 Markdown files.
- 14,000 lines of source code.
- 873 test methods across 145 test files.
- 19 Composer packages.
- 12 Core packages, two Hub packages, one Bridge package, and four Spoke packages.
- A large "skills/" directory containing third-party AI skills.
- An "archive/" directory containing historical material and provenance.
- A substantial Architecture documentation tree.

Treat these as audit-reported counts, not verified current metrics.

The inventory may change as PRs are merged. More importantly, file counts do not establish implementation quality.

### Reported PR #361

A.ai reported PR #361, commit "17a0181", as merged.

The reported purpose was to address CI coverage gaps.

Changes allegedly included:

- Adding "core/crypto" to package CI.
- Adding "core/filesystem" to package CI.
- Adding "spoke/internal/showcase" to package CI.
- Adding a root integration-test runner.
- Adding a package-completeness guard.

The report said the expanded CI process exposed latent defects.

#### Crypto issues reportedly repaired

- PHPStan configuration failure.
- Ten typed-decoding repairs involving array shapes and scalar coercion.
- Mixed-value validation in "Encrypter".
- A "strlen" value captured before "sodium_memzero" modified a variable by reference.
- "KeyRegistry" accessing a value through the public "getValue()" mechanism rather than a private property.
- RFC 5869 HKDF bounds.
- Removal of a dead "password_hash() === false" branch.

#### Filesystem issues reportedly repaired

- Reassignment of a promoted readonly property in "PathGuard".
- "resolve()" rejecting new nested write paths because it required the target to exist.
- Double-closing a file handle in "AtomicWriter", potentially swallowing a byte-limit exception.
- Empty-file reads returning "null" instead of an empty string.
- Incorrect test expectations concerning a 15-byte versus 16-byte value.

The reported "PathGuard" repair involved walking to the deepest existing ancestor when resolving a new path. That approach requires careful verification of traversal rejection, symlink behavior, and the intended filesystem security boundary.

#### Showcase issues reportedly repaired

- A repository using nonexistent database connection methods.
- An incorrect argument count in "Product::restoreFromPersistence()".
- A missing "CreateProductCommand" import.
- Incorrect exception handling in "forId()".
- Phantom integration suites and incorrect root-relative PHPUnit bootstraps.

The report said the PR preserved test intent rather than weakening tests to obtain green results. It also said CI logs were temporarily surfaced through GitHub error annotations to make failure output visible, with that temporary instrumentation removed in the same PR.

The PR was reported as passing 27/27 checks.

#### What must be verified

A green CI result is valuable, but it is not sufficient to close the architectural review.

Verify:

1. The PR is actually merged and its current commit is present.
2. The final diff matches the reported scope.
3. The package-completeness guard fails when a required package is intentionally omitted.
4. The new root integration runner discovers and executes the intended tests.
5. Tests have not been weakened or excluded to obtain a green result.
6. Crypto changes retain their intended security properties.
7. Filesystem changes reject traversal and unsafe symlink scenarios.
8. The database corrections comply with the frozen interface.
9. The workflow exposes genuine failures rather than concealing them.

The goal is not to distrust A.ai's report. It is to make the result reproducible and independently inspectable.

### Reported PR #362

A.ai reported PR #362, commit "9c3c901", as merged.

The reported purpose was to correct the release/versioning error contract.

The reported changes included:

- Making "resolveLegacyRepoDir()" throw when repository resolution produces no valid result.
- Removing a path where an empty log could produce a vacuous "increment:none" result.
- Preserving a legitimate clean no-op with exit code zero and the result ""none"".
- Making release workflow failures fatal when the version bump fails, produces invalid JSON, returns an unknown increment, omits "new_version", or reports no package at the expected tier.
- Removing "|| true" from a "version:release" operation that previously could hide an error while reporting success.
- Adding a black-box "LoomVersionBumpContractTest" covering CLI exit codes, STDERR, and JSON output.
- Reviewing other uses of "|| true".

The report said 25/25 checks passed.

#### Why this matters

This work reportedly addresses a general class of governance defects: controls that can declare success without proving the intended state was reached.

It is consistent with the project's strategic direction, provided the actual changes preserve the approved release contract.

#### What must be verified

Inspect:

- The merged diff and CI evidence.
- The four-component versioning rules in ADR-019.
- The behavior for successful releases, legitimate no-ops, malformed output, and genuine failures.
- Whether error handling remains consistent throughout the release workflow.
- Whether the black-box tests cover the actual command boundary.
- Whether any existing release behavior changed beyond the approved scope.

A release workflow that fails loudly is preferable to one that silently skips work. However, a stricter workflow must also preserve legitimate no-op behavior.

Do not perform a release or mutate release state simply to prove the workflow works. Any requested dry-run must have a clearly defined safe execution path.

---

## 9. The Documentation and Findings-Register Reconciliation Problem

One outstanding issue is especially important for cross-agent synchronization.

Your audit reportedly identified inconsistencies involving:

- The shortcomings register.
- ADR-011 status.
- CORE-02 documentation or docblock drift.
- The correspondence between reported findings, their identifiers, and their dispositions.

However, the reports currently available to SAAI do not agree on the exact missing finding IDs.

One earlier report mentioned discrepancies involving "S-028"–"S-031" and "S-039"–"S-042".

A later report mentioned missing entries from "S-048" through "S-092".

These descriptions are not interchangeable. Neither should be accepted as authoritative without checking the current repository.

This is precisely why a new audit must be reconciled against the actual authoritative files before it is converted into implementation work.

### Required reconciliation process

1. Inspect the current "SHORTCOMINGS-REGISTER.md".
2. Inspect the current README and every document that reports findings counts or dispositions.
3. Enumerate the actual finding IDs from the relevant source records.
4. Identify missing IDs, duplicate IDs, invalid references, and conflicting dispositions.
5. Compare the register's summary counts against its individual entries.
6. Verify each disputed item against its source or implementation evidence.
7. Inspect ADR-011 and all documents reporting its status.
8. Inspect CORE-02's authoritative contract and the implementation's docblocks.
9. Produce one reconciled discrepancy list with file paths and exact evidence.
10. Propose the smallest consistent correction.

Do not fill gaps with guessed findings.

Do not close findings merely to make counts agree.

Do not change a finding's severity or disposition just because its removal simplifies a report.

Do not make the README, register, or ADR independently authoritative for the same underlying decision when the project has designated a canonical source.

If the authoritative source itself is ambiguous, ask the owner to resolve the ambiguity.

The desired result is one consistent account of the actual state—not merely matching numbers.

---

## 10. The Licensing Question Is Still Unresolved

The owner has explicitly stated:

> I want open source but i don't want anyone taking any part of my stuff. Atleast not without explicitly crediting me.

This is a substantive licensing requirement, not just a request to tidy license files.

The discussion has identified a distinction between:

- Retaining copyright ownership.
- Permitting others to use, modify, and redistribute the software.
- Requiring preservation of copyright and license notices.
- Requiring public acknowledgement in promotional material or whenever the software is used.
- Restricting what people may do with the software.

These are different legal and product-policy objectives.

The owner has not yet made a final licensing decision.

### Reported audit findings

The audit reportedly found inconsistencies among:

- A README describing the project as MIT-licensed.
- A root "LICENSE" file containing Apache-2.0 terms.
- A root "composer.json" reportedly identifying the project as proprietary.
- Package-level Composer manifests reportedly identifying packages as MIT-licensed.

These claims need verification against the current repository.

If confirmed, this is a real consistency issue. But do not resolve it by choosing a license unilaterally.

### Options already discussed

A conventional open-source license such as Apache-2.0 generally permits broad reuse while imposing specified license and notice obligations.

That is not equivalent to requiring a public endorsement or acknowledgement whenever someone uses the software.

A custom license requiring public credit for all use may not qualify as an OSI-approved open-source license, depending on its terms.

GPL-style copyleft addresses certain source-disclosure obligations when software is distributed under the applicable conditions. It does not guarantee that every user publicly promotes the original author.

The exact legal effect depends on the chosen license and the intended use cases. Obtain qualified legal advice before treating a custom attribution clause as enforceable or compatible with the owner's goals.

### The pending decision

The owner was asked whether they would accept a company building and selling a competing product using DGLab while preserving legally required notices but not necessarily publicly mentioning the owner.

The answer is still pending.

Until the owner decides:

- Do not change the root license.
- Do not change package-level licenses.
- Do not make the repository proprietary by assumption.
- Do not represent a custom license as standard open source without verifying its status.
- Do not remove third-party licenses.
- Do not make irreversible licensing changes.

You may prepare an inventory of current license files, package metadata, third-party notices, and dependencies. Keep that work factual and separate from the final licensing decision.

---

## 11. The "skills/" Directory and the Archive

The audit reportedly found that "skills/" contains a large collection of third-party AI skills, with some embedded license terms described as restricting use to personal, educational, or non-commercial purposes. Another reported skill file describes proprietary terms.

These reports require direct verification.

The owner has not yet decided what should happen to the directory.

Potential actions include retaining it, relocating it, replacing specific content, or removing material whose licensing does not fit the project.

Do not choose one of these options without the owner's direction.

In particular:

- Do not delete "skills/" merely to reduce repository size.
- Do not assume that the absence of references proves content is safe to remove.
- Do not assume that the presence of a license file grants the rights the project needs.
- Do not remove third-party attribution or license notices.
- Do not conflate the project's own source code with imported third-party material.

The reported "archive/" directory serves a different purpose: historical provenance and traceability. The audit said it contains numerous references and should be preserved.

Do not remove the archive as part of a cleanup exercise without a separate, evidence-based decision.

---

## 12. The Research Agenda

The owner has identified several broader research topics. These are currently research questions, not approved implementation requirements.

The reported intention was to create:

"Architecture/CrossCutting/RESEARCH-AGENDA.md"

Z.ai was asked to create that file, but SAAI has not received confirmation that it exists.

Before creating a duplicate, check the repository and current worklog.

The research agenda should capture questions and hypotheses without prematurely turning them into ADRs, requirements, or code changes.

### Research track 1: Bad actors and a broken copyright system

Investigate how a platform can address:

- Impersonation.
- Spam.
- Fraud.
- Coordinated manipulation.
- Copyright ownership and licensing.
- Attribution and provenance.
- Derivative works.
- Takedowns and counterclaims.
- Appeals and dispute resolution.
- Fraudulent or abusive claims.
- Transparent enforcement and auditable decisions.

An important principle supplied by the owner is:

> Do not confuse the ability to enforce a rule with the authority to decide that the rule is fair.

This raises questions about procedural fairness, accountability, and who can challenge an enforcement decision.

A.ai previously reported that related material may already exist in "BLIND-SPOT-DOCTRINE.md". Verify this before duplicating it.

### Research track 2: How not to become YouTube

Investigate how to avoid the problems associated with highly centralized creator platforms.

Areas of interest include:

- Creator ownership and control.
- Content and audience portability.
- Transparent recommendation systems.
- Interoperability.
- The ability to leave without losing everything.
- Governance and meaningful appeals.
- Incentives that reward engagement at the expense of users or creators.
- Avoiding excessive platform dependence.

Do not assume decentralization automatically solves abuse, governance, or power concentration. Examine the actual mechanisms and incentives.

### Research track 3: Half Exceptions

The phrase "Half Exceptions" came from the owner's brother and concerns personal preferences.

The owner clarified that it covers both:

1. Exceptions that partially accommodate a preference.
2. Preferences that are partly negotiable rather than absolute.

Treat this as a user-originated hypothesis, not an established computer-science term.

Potential areas to investigate include the distinctions among:

- Invariants.
- Hard constraints.
- Defaults.
- Preferences.
- Bounded exceptions.

Do not introduce this model into the architecture until the concept has been clarified and its relevance demonstrated.

### Research track 4: The "Cancel anytime" paradox

The owner supplied this exact statement:

> "Cancel anytime" is the most repeated promise in the history of commerce. It is also the one most rarely acted upon.

Preserve the wording when documenting the hypothesis.

Do not present it as a verified statistic without evidence.

The research question concerns the difference between the formal ability to leave a service and the practical ability to do so.

Investigate:

- Friction by design.
- Lock-in.
- Asymmetric commitments.
- Data portability.
- Cancellation.
- Account deletion.
- Content removal.
- User and creator autonomy.
- Whether exit mechanisms work in practice.

Cancellation, account deletion, content removal, and data portability must remain distinct operations.

### Research track 5: The connecting theme

The owner identified a unifying question:

> The difference between a right that exists on paper and a right that a person can exercise in practice.

Potential architectural implications include provenance, permissions, auditability, portability, governance boundaries, and transparent exception handling.

These are potential implications, not approved requirements.

Do not turn research observations into new architecture rules until the owner has reviewed the findings.

---

## 13. The Main Systemic Lesson From the Audit

One of the strongest observations in your reported audit was that some controls appear capable of claiming a state without rechecking the underlying state.

Examples worth investigating include:

- Documentation counts that disagree with the actual findings.
- Release workflows that report success despite failed operations.
- Package inventories that do not match CI coverage.
- Declared capabilities that are not implemented.
- Security controls that are present as stubs.
- Documentation that claims verification without identifying the evidence.

This observation aligns with the project's strategic direction.

The preferred solution is generally to make controls consume authoritative state directly and fail visibly when a required condition is not satisfied.

For example:

- Derive package coverage checks from the authoritative package inventory.
- Test release command output and exit codes at the command boundary.
- Verify that a test runner actually executes tests rather than merely discovering files.
- Generate counts from actual register entries instead of manually maintaining totals.
- Make implementation status explicit for planned but unimplemented capabilities.

However, not every check should be automated, and not every document should be generated. Choose controls that eliminate a demonstrated class of errors without adding disproportionate complexity.

The objective is trustworthy verification, not the maximum possible number of scripts.

---

## 14. The Current Coordination Concern: A.ai and Z.ai Must Stay in Sync

The owner has explicitly raised this concern:

> We have a fresh pair of eyes doesn't mean we should deviate. Z.ai still exists. Although A.ai is also an implementer, Z.ai has been implementing for a while. All agents, Human or AI should be in complete sync.

This is a governing collaboration requirement.

The owner is not asking A.ai to stop finding defects. The owner is asking all contributors to work from a consistent understanding of the project.

A.ai's findings and PRs must be reconciled with:

- Existing architectural decisions.
- Current implementation state.
- Z.ai's active tasks and open changes.
- Work already completed by other contributors.
- The owner's priorities.
- Existing worklogs and handoff records.
- The current shortcomings register.
- Open architectural decisions.

### The gap identified so far

SAAI previously acknowledged that the discussion had moved from A.ai's reported PRs toward additional tasks without first obtaining a complete reconciliation of Z.ai's active work.

That was an incomplete handoff.

It does not prove that PR #361 or PR #362 violates the architecture. It does mean that the shared state must be reconciled before deciding the next implementation task.

### Required synchronization pass

Before beginning another substantial change, report:

#### A. Current repository state

- Current branch and commit.
- Whether PRs #361 and #362 are actually merged.
- Whether their reported commits are present in the current branch.
- Relevant CI results.
- Any uncommitted changes or open PRs that affect the same area.

#### B. Z.ai coordination state

The owner should obtain or provide Z.ai's current report.

Reconcile:

- Z.ai's current task.
- Open or pending PRs.
- Work already implemented but not merged.
- Known defects under investigation.
- Planned changes that overlap with A.ai's work.
- Any stale assumptions in either agent's report.

Do not claim to have queried Z.ai directly unless a real integration has made that possible. The owner currently relays information between agents manually.

#### C. Architectural alignment

For every material change in #361 and #362, identify:

- The relevant requirement or defect.
- The relevant ADR or frozen contract.
- The implementation change.
- The test or verification evidence.
- Any behavior that changed beyond the stated scope.
- Any outstanding concern.

#### D. Priority alignment

The candidate next activities previously discussed were:

1. Reconcile the findings register, ADR-011, and CORE-02 documentation.
2. Verify the merged changes in #361 and #362.
3. Prepare a safe release-workflow dry-run proposal.
4. Resolve licensing and third-party skills questions after the owner provides direction.

This is a proposed sequence, not a new approved roadmap. Reconcile it with Z.ai's actual state and the owner's current priorities before starting.

### Do not redo completed work

If Z.ai has already completed a task, do not start a competing implementation simply because A.ai has a different design.

Instead:

1. Inspect the existing result.
2. Identify a specific remaining gap.
3. Provide evidence.
4. Propose the smallest necessary correction.
5. Coordinate the correction before implementation.

A second implementation of the same capability is not progress if it creates competing sources of truth.

---

## 15. Required Working Protocol for All Agents

Use the repository's existing worklogs, ADRs, frozen contracts, PRs, and task records wherever possible. Do not introduce a new coordination framework unless a demonstrated gap requires one and the owner approves it.

### Before starting a task

1. Read the relevant open decisions and frozen contracts.
2. Inspect the current branch and relevant Git state.
3. Read the existing worklog and handoff records.
4. Check whether another agent is already working in the same area.
5. State the problem and provide reproducible evidence.
6. Define the proposed scope.
7. Identify affected packages, contracts, tests, and documentation.
8. Identify decisions that require approval.
9. State what you will deliberately not change.

For a narrow, clearly authorized bug fix, this need not become an elaborate approval ceremony. The purpose is to prevent conflicts and unauthorized architectural changes.

### While implementing

- Keep changes focused.
- Preserve established contracts.
- Add regression tests for confirmed defects.
- Do not weaken tests simply to obtain a green CI run.
- Do not silently skip failing packages.
- Do not suppress errors that should block the operation.
- Do not describe blueprints as implemented capabilities.
- Do not change licensing or other owner-controlled policy decisions.
- Do not expand a task into unrelated cleanup without approval.
- If a newly discovered defect materially changes the scope, report it before proceeding.

### After implementing

Report:

1. What changed.
2. Why it changed.
3. Which requirements and contracts it addresses.
4. Which files and packages were affected.
5. Which tests actually ran.
6. Their actual results.
7. Which tests did not run and why.
8. What remains unverified.
9. Whether the change is merged or still in a branch.
10. Any required documentation or register updates.
11. Any follow-up requiring owner approval.
12. The next safe task, if one has been authorized.

A report must distinguish a local test result, a CI result, and a manually reviewed claim.

### At handoff

Every agent should leave a concise, factual handoff containing:

- Current commit or PR.
- Completed work.
- Remaining work.
- Known limitations.
- Active conflicts or dependencies.
- Decisions required from the owner.
- The next recommended action.
- Explicit warnings about changes another agent must not duplicate.

When another agent resumes work, it should reconcile the handoff with the actual repository rather than blindly trusting an old report.

---

## 16. What A.ai Must Not Do Without Approval

The following boundaries are especially important.

Do not:

- Replace an approved ADR with your preferred design.
- Modify a frozen contract simply to accommodate an implementation.
- Change the database strategy without reconciling ADR-013 and the recorded decision history.
- Replace SuperPHP with another template engine.
- Rename SuperPHP without approval.
- Expand the MVP into a different deployment or frontend architecture without a requirement.
- Rewrite unrelated packages while addressing a narrow defect.
- Delete or relocate "skills/" before the owner decides.
- Delete the historical archive as part of routine cleanup.
- Change root or package licensing without the owner's decision.
- Declare a reported PR verified without inspecting evidence.
- Close shortcomings merely to make counts consistent.
- Create duplicate governance documents when an authoritative document already exists.
- Treat your own audit as more authoritative than the current source of truth.
- Treat Z.ai's previous work as automatically correct or automatically obsolete.
- Start a new substantial task before reconciling conflicting active work.

These restrictions are not a ban on proposing change. They establish how proposals become approved work.

---

## 17. Recommended Immediate Actions

The next action is reconciliation, not another implementation sprint.

Please work through the following sequence.

### Step 1 — Establish the actual current state

Inspect the repository and report:

- Current branch and commit.
- The actual status of PRs #361 and #362.
- Their final diffs and available CI evidence.
- Any open PRs or uncommitted changes affecting the same components.
- The current state of the register and documentation discrepancies.
- Whether "Architecture/CrossCutting/RESEARCH-AGENDA.md" exists.

Do not modify files during this initial reconciliation unless the owner separately authorizes a specific fix.

### Step 2 — Reconcile with Z.ai

Obtain the owner's latest Z.ai status report or wait for it to be provided.

Compare active tasks and implementation plans.

Resolve overlaps and stale assumptions. In particular, reconcile the actual DBAL contract usage and determine whether any related work remains open.

### Step 3 — Produce one evidence-based discrepancy report

The report should identify:

| Area | Current verified state | Evidence | Existing owner or task | Recommended next action | Approval needed? |
|---|---|---|---|---|---|
| PR #361 | To verify | Diff and CI | To reconcile | To determine | To determine |
| PR #362 | To verify | Diff and CI | To reconcile | To determine | To determine |
| Findings register | To verify | Register and source records | To reconcile | To determine | To determine |
| ADR-011 | To verify | ADR and references | To reconcile | To determine | To determine |
| CORE-02 | To verify | Contract and implementation | To reconcile | To determine | To determine |
| Research agenda | To verify | Repository tree and worklog | To reconcile | To determine | To determine |
| Release dry-run | Not yet approved as an execution | Workflow and safe-run plan | Owner decision | Prepare proposal | Yes, if execution or state mutation is involved |
| Licensing and skills | Owner decision pending | Current license inventory | Owner | Prepare factual inventory if authorized | Yes |

Do not populate the table with invented verification results.

### Step 4 — Recommend the next task

Recommend one next task, explain why it is the appropriate priority, and identify the minimum required scope.

Do not begin that task until the owner has confirmed the priority when it materially changes the established plan.

---

## 18. How SAAI Will Evaluate Your Contributions

SAAI will not evaluate your work solely by the number of defects found, PRs created, or checks passing.

The important questions are:

- Did the change address a real, evidenced problem?
- Did it preserve the approved architecture?
- Did it improve implementation quality?
- Did it add meaningful regression protection?
- Does CI actually enforce the intended guarantee?
- Are the reported results reproducible?
- Are remaining uncertainties explicit?
- Did the change avoid unnecessary scope expansion?
- Were Z.ai's active tasks and prior work reconciled?
- Can another agent understand the resulting state without reconstructing the entire conversation?
- Were owner-controlled decisions left to the owner?

A good independent reviewer is willing to challenge existing work, but can explain exactly what is wrong and why.

A good implementer fixes the demonstrated problem without introducing a second architecture.

A good collaborator leaves the whole team with a clearer understanding of the system.

---

## 19. The Shared Objective

DGLab is intended to become a coherent, maintainable platform whose declared architecture, actual implementation, and verification mechanisms remain aligned.

The work is larger than any one agent's context window or task list.

That makes synchronization an engineering requirement in its own right.

We should be able to answer, reliably:

- What is the approved design?
- What is actually implemented?
- What has been verified?
- What remains incomplete?
- Which agent is working on what?
- Which decisions require the owner's approval?
- What changed since the previous handoff?
- What should the next contributor do without duplicating or undoing prior work?

The human owner remains the final authority on priorities and consequential design decisions.

Z.ai's implementation history must be respected and reconciled.

A.ai's fresh review must be used to improve the project without creating a competing source of truth.

SAAI's role is to help maintain architectural continuity, identify conflicts, and make the next decision clearer.

Our shared objective is not simply to make more progress. It is to make progress that remains correct, explainable, verifiable, and consistent with the owner's vision.

Please begin with the reconciliation pass. Return the evidence and proposed next step before starting additional implementation work.
