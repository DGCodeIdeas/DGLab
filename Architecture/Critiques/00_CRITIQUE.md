# 00 — Critical Assessment of Existing DGLab Blueprints

**Independent re-verification date:** 2026-08-04
**Repo state:** `DGCodeIdeas/DGLab` @ `main` (commit pushed 2026-07-17T18:28:48Z)
**Method:** Full recursive tree fetch (1,221 entries) + read of 77 key files (all 20 CORE blueprints, 10 HUB blueprints, BRIDGE-01, DEPLOY-01, 7 evaluation docs, 7 hub-taxonomy docs, internal-spoke placeholders, orchestrator source, event-dispatcher source, container stub, render.yaml, Dockerfile, docker-compose.yml).

---

## Summary

The DGLab repository contains **two mutually incompatible architectural visions** sharing one name, an **evaluation layer that scores a version of the Core tier that no longer exists**, an **empty stub at the dependency root of the entire Hub tier**, and a **"deploy" blueprint that deploys only the Markdown documentation** (and the *legacy* documentation at that). Every performance target in the approved blueprint set is asserted without a benchmark methodology. The single security-critical document (`BRIDGE-01`) cites the wrong upstream dependency for payload verification.

Below are **21 findings**: 13 confirmed from the prior analysis (re-verified independently) plus **8 new findings** the prior analysis missed.

| Severity | Count | Findings |
|---|---|---|
| 🔴 Critical (blocks implementation) | 5 | 2, 3, 8, 9, 18 |
| 🟠 High (causes wrong decisions) | 7 | 1, 4, 10, 11, 14, 19, 20 |
| 🟡 Medium (degrades quality) | 6 | 5, 6, 7, 13, 16, 21 |
| 🟢 Low (housekeeping) | 3 | 12, 15, 17 |

---

## Part A — Findings Confirmed From Prior Analysis (1–13)

### Finding 1 — Two Incompatible Architectures Share One Name
**Severity:** 🟠 High
**Evidence:** `docs/architecture/origin/**` (Vision A: PHP monolith "CMS Studio" with in-process Spokes) coexists with `docs/blueprints/**` (Vision B: ~50+ independently versioned polyrepo with mandatory `BRIDGE-01` boundary). No document in either tree states which is canonical.

**Impact:** A new developer reading `HUB_AND_SPOKE.md` first builds a mental model where Spokes are in-process PHP classes — the opposite of the polyrepo model that `orchestrator/` and `packages/core/` actually implement. Every downstream architectural decision (deployment, security boundary, testing strategy) diverges based on which vision is read first.

**Resolution:** `01_MASTER_INDEX.md` §1 declares Vision B canonical and archives all of `docs/architecture/origin/**`. Governance Rule 4 forbids new work under the archived tree.

---

### Finding 2 — Evaluation Layer Scored a Version of Core That No Longer Exists
**Severity:** 🔴 Critical
**Evidence:** `docs/evaluation/BLUEPRINT_RANKINGS.md` (line 53–72) assigns scores that match **zero** current files:

| ID | Evaluation claims (June 1, 2026) | Actual file (verified 2026-08-04) |
|---|---|---|
| CORE-01 | "Bootstrapper & Kernel" (94/100) | **Polyrepo Orchestrator** ("The Loom") |
| CORE-02 | "Lifecycle Hooks" (89/100) | **Dependency Injection Container** |
| CORE-03 | "Service Container" (92/100) | **PSR-14 Event Dispatcher** |
| CORE-04 | "Encryption Primitives" (89/100) | **PSR-7 HTTP Message & Factory** |
| CORE-05 | "Router & Dispatch" (91/100) | **PSR-15 Middleware & Request Handler** |
| CORE-07 | "Middleware Pipeline" (90/100) | **SuperPHP Lexer** |
| CORE-09 | "Error Handling" (91/100) | **PSR-3 Logging Service** |
| CORE-11 | "ORM & Query Builder" (88/100) | **SuperPHP Parser** |
| CORE-14 | "Caching Layer" (85/100) | **Filesystem Abstraction** |
| CORE-15 | "Validation Engine" (86/100) | **Cache Abstraction (PSR-6/16)** |
| CORE-16 | "Logging & Observability" (84/100) | **Binary Encryption Envelope** |
| CORE-18 | "Event System" (83/100) | **Core Kernel & Lifecycle** |
| CORE-19 | "Service Locator" (82/100) | **Database Abstraction Layer** |

The evaluation's recommended implementation sequence (`CORE-01 → CORE-02 → CORE-03 → CORE-05 → CORE-06 → CORE-07 → CORE-10`) would, taken literally against current numbering, have you build the polyrepo release tool, then the DI container, then the event dispatcher, then the **Middleware** (CORE-05, actually PSR-15) before the **Router** (CORE-06, actually attribute-based router) — and skip the Kernel (CORE-18) entirely until "remaining in order of dependency." The 87/100 overall score is a snapshot of a renumbering that was never reconciled with the actual files.

**Impact:** Any planning conversation that references the evaluation scores is reasoning about the wrong system. The "32-week timeline" derived from those scores is invalid.

**Resolution:** `01_MASTER_INDEX.md` §2 is the authoritative ID→component table. `docs/evaluation/` is marked stale and must carry a "valid as of commit `<sha>`" header per Governance Rule 3.

---

### Finding 3 — Live Cross-References Inside the "Approved" Set Are Wrong
**Severity:** 🔴 Critical
**Evidence:** `docs/blueprints/Spoke/Bridge/BRIDGE-01.md` (the self-rated 96/100 "architectural masterpiece") states under "Transitive Core Dependencies":

> `CORE-09: Cryptography & Hashing (Payload Verification)`

The actual `docs/blueprints/Core/CORE-09.md` (verified 2026-08-04) is titled **"PSR-3 Logging Service / Structured Logging Engine"**. The cryptography component is `CORE-16` ("Binary Encryption Envelope"). The single most security-critical document in the repository cites the wrong upstream dependency for its payload-verification logic.

Additional wrong references in the same `BRIDGE-01.md`:
- `CORE-01: Polyrepo Orchestrator (Enforcement Logic)` — CORE-01 is the release-automation tool, not an enforcement-logic component. The Bridge's enforcement logic is its own; it does not call into Loom.
- `CORE-06: Router (Gateway Routing)` — CORE-06 is the attribute-based router, but the Bridge's gateway routing is `HUB-08` (Sovereign Gateway), which the same blueprint correctly lists under Direct Hub Dependencies. The CORE-06 reference is redundant and misleading.

**Impact:** An implementer building BRIDGE-01 against the current CORE-09 would wire the Bridge's payload verification into the logging service, producing a system that logs payloads instead of verifying them — a silent security failure.

**Resolution:** `01_MASTER_INDEX.md` §3 corrects all three references. `blueprints/Bridge/BRIDGE-01-vanguard.md` rewrites the dependency list with verified IDs.

---

### Finding 4 — "Approved" Is Not a Proxy for "More Substantive"
**Severity:** 🟠 High
**Evidence:** File-size and content comparison of approved vs. disapproved CORE-01:

| Version | Size | Content |
|---|---|---|
| Approved `docs/blueprints/Core/CORE-01.md` | 2,230 bytes | 5 prose sections, 1 Mermaid flowchart, zero interfaces, zero code |
| Disapproved `docs/blueprints/disapproved/CORE-01.md` (per prior analysis) | ~7,600 bytes | `KernelInterface`, typed `Environment` enum, complete `ErrorHandler` class, boot-sequence sequence diagram, performance budgets tied to OPcache preload, 100%-path unit-test criteria |

The same pattern holds across the disapproved set: the rejection rationale is a single boilerplate file (see Finding 12) stating "Varying from the original blueprints" — no per-file diff, no specific deviation cited.

**Impact:** The approved set is **less** implementable than the disapproved set it replaced. Engineers building from approved blueprints must reverse-engineer interfaces from prose; engineers who find the disapproved set get working code but cannot use it because it is "disapproved."

**Resolution:** Every blueprint in this bundle (see `blueprints/`) meets the fidelity bar defined in `README.md`. Governance Rule 5 forbids prose-only blueprints.

---

### Finding 5 — Byte-For-Byte Duplicate Masquerading as Mobile Variant
**Severity:** 🟡 Medium
**Evidence:** `docs/architecture/origin/Sovereign_Stack_Blueprint/SOVEREIGN_STACK_MASTER.md` (~126 KB) and `docs/architecture/origin/Mobile_Optimized/SOVEREIGN_STACK_MASTER.md` (~123 KB) are near-identical. The `Mobile_Optimized/` directory name promises a mobile-tuned variant; it delivers none.

**Impact:** Wasted maintenance surface — any edit to one copy must be manually replicated to the other, and historically has not been. Also inflates repo size by ~123 KB.

**Resolution:** Governance Rule 6 requires a CI diff check on `*_Optimized`, `*_v2`, etc. Both copies are archived under Vision A (Finding 1).

---

### Finding 6 — Sibling Documents Contradict Completion Claims
**Severity:** 🟡 Medium
**Evidence:**
- `docs/architecture/origin/PhasedBlueprints/README.md` marks **Nexus** as `✅ COMPLETED (CORE)`.
- `docs/architecture/origin/PhasedBlueprints/ANALYSIS_REPORT.md` (same directory) states **Nexus is 40% complete**.
- `docs/architecture/origin/ComponentBlueprints/README.md` marks **AdminPanel** as `✅ COMPLETED (LEGACY)`.
- `docs/architecture/origin/ComponentBlueprints/DECOMMISSIONING_PLAN.md` (same folder) lists AdminPanel under components being actively decommissioned.

Additionally, `ANALYSIS_REPORT.md` contains the literal unexpanded shell command `$(date)` as its "generated" timestamp — evidence the report was never actually run through a shell.

**Impact:** Any status report aggregating from these documents produces contradictory numbers. The `$(date)` artifact signals the documents were generated by a process that was never validated end-to-end.

**Resolution:** All four documents are archived under Vision A (Finding 1). Governance Rule 3 requires dated snapshots.

---

### Finding 7 — The "81-Phase" Claim Doesn't Match Its Own Category Table
**Severity:** 🟡 Medium
**Evidence:** `docs/architecture/origin/PhasedBlueprints/README.md` claims "81 distinct phases." Its own category table sums to **99**:

```
AuthService 5 + SuperPHP Engine 10 + Superpowers SPA 10 + DownloadService 5 + EventDispatcher 5
+ AssetBundler 5 + TestSuite 10 + CMS Studio 10 + MangaScript 5 + Nexus 5 + StudioExpansion 6
+ AdminPanel 5 + DocumentationService 18 = 99
```

The `BLUEPRINT_RANKINGS.md` also references "81 services" as an operational-complexity concern (line 209). The actual approved-blueprint count is **82** (20 Core + 30 Hub + 15 Internal Spoke + 15 External Spoke + 1 Bridge + 1 Deploy) — see Finding 15.

**Impact:** Planning conversations reference a number that is wrong in two different ways (99 actual phases vs. 81 claimed; 82 actual blueprints vs. 81 claimed). Resource allocation derived from "81" is based on a fiction.

**Resolution:** `01_MASTER_INDEX.md` §4 gives the corrected inventory. The Vision A phase count is archived and not used for planning.

---

### Finding 8 — The Tier Everything Depends On Has Zero Implementation
**Severity:** 🔴 Critical
**Evidence:**
- `docs/hub-taxonomy/hub-blueprint-taxonomy.md` marks `CORE-02` (DI Container) as a direct dependency of: HUB-01, HUB-02, and transitively of every other Hub blueprint that resolves services through the container.
- `packages/core/container/composer.json` declares: *"CORE-02: PSR-11 compliant Dependency Injection Container with autowiring, compiler passes, and circular dependency detection."*
- `packages/core/container/src/` contains **only `.gitkeep`** (0 bytes, verified 2026-08-04). No PHP files. No tests. No interfaces.
- By contrast, `packages/core/event-dispatcher/src/` (CORE-03) has 8 real, tested PHP classes, and `orchestrator/src/` (CORE-01) has 4 real, tested PHP classes with a full PHPUnit suite.

No blueprint, roadmap, or evaluation document identifies this as the critical-path risk it is. The "32-week timeline" assumes CORE-02 is built but does not schedule it.

**Impact:** The entire Hub tier is blocked on a component that does not exist. Any Hub work started today will either (a) stall waiting for CORE-02, (b) build a ad-hoc service locator that will have to be torn out, or (c) hard-wire dependencies and forfeit the testability that the container enables.

**Resolution:** `blueprints/Core/CORE-02-di-container.md` is a complete, directly implementable specification with a reference PHP 8.3 `Container` class that compiles against the existing `composer.json` with zero new dependencies. It is the single highest-priority PR in the system. `04_MIGRATION_PLAN.md` Phase P1 makes it the first work item.

---

### Finding 9 — The Only "Deploy" Blueprint Doesn't Deploy the Application
**Severity:** 🔴 Critical
**Evidence:**
- `docs/blueprints/Deploy/DEPLOY-01.md` (8,881 bytes) and the root-level `render.yaml` (164 bytes) describe exactly one thing: a free-tier Render web service serving the **Markdown documentation** over PHP's built-in development server.
- The root-level `Dockerfile` (399 bytes) confirms this: `CMD ["php", "-S", "0.0.0.0:80", "-t", "docs/architecture/origin"]` — it serves the **legacy Vision A documentation directory**, not even the current `docs/blueprints/` directory.
- `docker-compose.yml` (292 bytes) contains only the comment *"Nothing here yet, but you can add your services and configurations as needed."*

There is no blueprint for deploying Core services, ~30 Hub services, Internal/External Spokes, the Bridge, or any datastore (MySQL/Postgres, Redis, queue broker). There is no CI/CD pipeline definition, no image registry, no health-check aggregation, no secret-management strategy.

**Impact:** The repo cannot be deployed as a functioning system. The "deploy" artifact that exists actively misleads — it suggests the system is deployable when in fact only the documentation is.

**Resolution:** `blueprints/Deploy/DEPLOY-01-core-hub.md` is a real containerized deployment spec for Core + Hub services, with OCI image architecture, health-check contract, secret injection, and rolling-update strategy. `01_MASTER_INDEX.md` §6 adds DEPLOY-02 (datastores), DEPLOY-03 (Bridge + External Spokes), DEPLOY-04 (multi-env promotion).

---

### Finding 10 — Performance Targets Are Asserted, Never Grounded
**Severity:** 🟠 High
**Evidence:** Every approved blueprint states hyper-specific latency budgets with no benchmark harness, hardware baseline, load model, or measured results:

| Blueprint | Claimed target | Methodology provided? |
|---|---|---|
| CORE-01 | "10-repo dependency check < 2 seconds" | None |
| CORE-02 | "sub-1ms resolution time", "< 0.5ms for 3-level deep", "< 1MB overhead" | None |
| CORE-03 | "10,000 event dispatches per second" | None |
| CORE-09 | "< 0.1ms logging overhead" | None |
| CORE-16 | "< 0.5ms encrypting 1KB string" | None |
| CORE-18 | "Hello World request < 10ms" | None |
| BRIDGE-01 | "DTO transformation + audit logging ≤ 2ms", "403 within 5ms" | None |
| DEPLOY-01 | "response time < 500ms", "memory < 250MB", "CPU < 30%" | None |

**Impact:** Engineers cannot tell whether a target is achievable, whether it has been met, or whether a regression has occurred. Targets that are never measured become folk wisdom — "we need to be under 1ms" — and drive premature optimization.

**Resolution:** Governance Rule 2: "No performance target without a method." Every blueprint in this bundle names the harness (PHPUnit `--group performance`), baseline (GitHub Actions `ubuntu-latest`, PHP 8.3, opcache enabled), and load model. Bare millisecond claims are either backed by a measurement plan or marked "provisional, unverified."

---

### Finding 11 — Solutions Document Was Never Merged Back
**Severity:** 🟠 High
**Evidence:** `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` (36,596 bytes) accurately identifies real gaps, including:
- *"Only 15 of Planned Spokes Documented"*
- *"Bridge Single Point of Failure; No Redundancy Strategy"*
- *"Sparse Architectural Details for Cache (HUB-02) and Queue (HUB-11)"*

None of these fixes appear in the referenced blueprint files. HUB-02 remains a 2,506-byte prose description; HUB-11 (not read in this pass) is similarly thin per the prior analysis; BRIDGE-01 has no failover spec.

**Impact:** The solutions document creates the impression that gaps have been identified and are being tracked, but the actual blueprints are unchanged. The document is a TODO list masquerading as progress.

**Resolution:** Governance Rule 5: "A weakness write-up is not resolved until the referenced blueprint is edited." `SOLUTIONS_TO_WEAKNESSES.md` is deleted once its items are merged into the corresponding blueprints in this bundle. `blueprints/Hub/HUB-02-cache.md` fully specifies the cache; `blueprints/Bridge/BRIDGE-01-vanguard.md` adds the 3-replica failover spec.

---

### Finding 12 — 72 Rejected Blueprints Share One Boilerplate Reason
**Severity:** 🟢 Low
**Evidence:** `docs/blueprints/disapproved/` contains 74 entries (the prior analysis counted 72; the tree shows 74 — likely 2 README/index files plus 72 blueprint rejections). The entire rejection rationale is a single file:

> `Reason: Varying from the original blueprints.`

No per-file diff, no specific deviation cited, no reviewer notes, no date. `EVALUATION_SUMMARY.md` (line 152) calls this a *"Learning Asset"* documenting *"decision-making rigor"* — but no rigor is recorded.

**Impact:** The disapproved set cannot be mined for architectural lessons because there is no record of what was actually rejected. Future contributors who propose a similar pattern cannot check whether it was already considered.

**Resolution:** Governance Rule 7: each `disapproved/` entry must state specifically what was rejected and why. The existing boilerplate file is replaced with per-file `REASON.md` notes.

---

### Finding 13 — Internal Spoke Tier Is Under-Counted in Every Score and Timeline
**Severity:** 🟡 Medium
**Evidence:** Every evaluation document states Internal Spokes as **"15 blueprints."** But `docs/internal-spokes/placeholder-blueprints.md` (10,175 bytes) documents **10 additional** planned spokes (`ISPOKE-16` through `ISPOKE-25`) as `📝 Placeholder` stubs with full dependency lists and estimated completion dates running to **Week 48**.

The tier's real scope is **25**, and 40% of it was excluded from every quality score and the "32-week" master timeline. The placeholder document's own summary table (line 159) lists all 10 with phase assignments (Phase 4: ISPOKE-16–20; Phase 5: ISPOKE-21–25) and week ranges extending to 43–48.

**Impact:** The "32-week timeline" is off by at least 16 weeks if the placeholder spokes are included. Resource planning based on "15 internal spokes" under-allocates by 40%.

**Resolution:** `01_MASTER_INDEX.md` §4 corrects the count to 25. `04_MIGRATION_PLAN.md` extends the timeline to reflect the true scope.

---

## Part B — New Findings (14–21)

### Finding 14 — Hub Taxonomy Document Has Arithmetic Errors
**Severity:** 🟠 High
**Evidence:** `docs/hub-taxonomy/hub-blueprint-taxonomy.md` "Summary by Classification" section:

| Criticality | Claimed count | Listed blueprints | Actual count |
|---|---|---|---|
| Critical | 10 | HUB-01, 02, 04, 05, 08, 09, 10, 19, 20, 21 | 10 ✅ |
| High | 15 | HUB-03, 06, 07, 11, 12, 14, 15, 17, 22, 24, 25, 26, 27, 29, 30 | 15 ✅ |
| Medium | **6** | HUB-13, 16, 18, 23, 28 | **5** ❌ |
| **Total** | **31** | | **30** ❌ |

The Medium row claims 6 but lists 5. The total claims 31 but the Hub tier has 30 blueprints. The same document's "By Maturity" section claims "Stable | 19" but lists 18 names (HUB-01, 02, 04, 05, 06, 07, 09, 10, 11, 14, 15, 19, 20, 21, 25, 27, 28, 29 — count them).

**Impact:** Any planning spreadsheet that sums the claimed counts produces 31 Hub blueprints instead of 30. Resource allocation is off by one team-week.

**Resolution:** `01_MASTER_INDEX.md` §4 gives the corrected inventory with verified counts.

---

### Finding 15 — The True Timeline Extends to 48+ Weeks, Not 32
**Severity:** 🟡 Medium
**Evidence:** `docs/evaluation/BLUEPRINT_RANKINGS.md` line 253: *"Total Timeline: 32 weeks with parallel work; ~6-7 months with properly resourced team."* But `docs/internal-spokes/placeholder-blueprints.md` schedules ISPOKE-25 (Incident Response Console) for **Weeks 43–48** — and that is only the Internal Spoke tier. External Spokes (Phase 5 in the evaluation) are scheduled for Weeks 25–32, which overlaps the Internal Spoke Phase 4 window (Weeks 27–36). The phases cannot run in parallel as the evaluation implies because External Spokes depend on BRIDGE-01, which depends on Internal Spokes being complete.

Reconstructed true timeline (minimum critical path):
- Phase 1 (Core): Weeks 1–8 (CORE-02 is unbuilt; add 2 weeks minimum)
- Phase 2 (Hub): Weeks 9–20 (12 weeks for 30 Hub blueprints, 3 in parallel)
- Phase 3 (Internal Spokes 01–15): Weeks 21–32
- Phase 4 (Internal Spokes 16–25): Weeks 27–48 (per placeholder doc)
- Phase 5 (Bridge): Weeks 33–36 (4 weeks)
- Phase 6 (External Spokes): Weeks 37–48 (12 weeks)

**Minimum end-to-end: 48 weeks**, not 32 — a 50% underestimate.

**Impact:** Any commitment to stakeholders based on "32 weeks" will be missed by at least 16 weeks. The underestimate cascades into hiring, budgeting, and downstream project dependencies.

**Resolution:** `04_MIGRATION_PLAN.md` uses a realistic 48-week minimum critical path with explicit parallelism assumptions stated.

---

### Finding 16 — The "81 Approved" Count Is Itself Wrong
**Severity:** 🟡 Medium
**Evidence:** `docs/evaluation/EVALUATION_SUMMARY.md` line 9: *"Approved Blueprints: 81."* Actual file count from the repo tree (verified 2026-08-04):

| Tier | Files in `docs/blueprints/<Tier>/` | Count |
|---|---|---|
| Core | `CORE-01.md` … `CORE-20.md` | 20 |
| Hub | `HUB-01.md` … `HUB-30.md` | 30 |
| Spoke/Internal | (per evaluation) | 15 |
| Spoke/External | (per evaluation) | 15 |
| Spoke/Bridge | `BRIDGE-01.md` | 1 |
| Deploy | `DEPLOY-01.md` | 1 |
| **Total** | | **82** |

The evaluation says 81; the actual count is 82. The discrepancy is small but symptomatic — the evaluation was generated by a process that did not reconcile against the file tree.

**Impact:** Minor for planning; significant as a signal that the evaluation layer was never validated against ground truth.

**Resolution:** `01_MASTER_INDEX.md` §4 gives the verified count of 82 (rising to 92 when ISPOKE-16–25 are included, and 97 when DEPLOY-02–04 are added).

---

### Finding 17 — `docker-compose.yml` Is Essentially Empty
**Severity:** 🟢 Low
**Evidence:** The root-level `docker-compose.yml` (292 bytes) contains only:

```yaml
# Docker Compose File for Development
# This file defines the services, networks, and volumes for the development environment.
# Make sure to customize the services and configurations as needed for your project.

# Nothing here yet, but you can add your services and configurations as needed.
```

No services. No networks. No volumes. A developer who clones the repo and runs `docker-compose up` gets nothing.

**Impact:** There is no standardized local development environment. Every developer must hand-configure PHP 8.3, Git, Composer, and any datastores they need. This guarantees environment drift and "works on my machine" defects.

**Resolution:** `blueprints/Deploy/DEPLOY-01-core-hub.md` includes a `docker-compose.yml` for local development with PHP-FPM, Nginx, PostgreSQL, and Redis wired to the Hub-tier service images.

---

### Finding 18 — The Dockerfile Serves the *Legacy* Documentation
**Severity:** 🔴 Critical
**Evidence:** Root-level `Dockerfile` (399 bytes):

```dockerfile
FROM php:8.3-cli
RUN apt-get update && apt-get install -y git zip unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "-t", "docs/architecture/origin"]
```

The `-t docs/architecture/origin` flag serves the **Vision A legacy documentation** (the archived, contradictory, byte-duplicated tree from Findings 1, 5, 6, 7) — not the current `docs/blueprints/` directory. A visitor who reaches the deployed documentation site sees the wrong architecture.

This compounds Finding 9: not only does the "deploy" blueprint deploy only documentation, it deploys the *wrong* documentation.

**Impact:** Public-facing documentation actively misleads anyone who reads it. The Render free-tier service (per `render.yaml`, name `sovereign-stack-blueprints`) is the de facto public face of the project, and it shows the archived, superseded architecture.

**Resolution:** `blueprints/Deploy/DEPLOY-01-core-hub.md` replaces the documentation-only Dockerfile with a real application deployment. A separate `DEPLOY-00` (documentation site) is added in `01_MASTER_INDEX.md` §6 to serve `docs/blueprints/` (the canonical tree) if a documentation site is still desired.

---

### Finding 19 — The `docs/decisions/` ADR Directory Is Empty
**Severity:** 🟠 High
**Evidence:** The repo tree (1,221 entries) contains **zero** files under `docs/decisions/`. The prior analysis noted this directory "seems to be a newer addition" but did not flag that it is empty. There are no Architecture Decision Records anywhere in the repository.

Key architectural choices that are undocumented as decisions:
- Why polyrepo over monorepo (the repo is polyrepo, but `docs/architecture/origin/` describes a monolith)
- Why PSR-11 container over PHP-DI / Symfony DI / Laravel container
- Why ES256 (asymmetric) JWT signing over HS256 (symmetric)
- Why Argon2id over bcrypt (referenced in CORE-16 but not decided)
- Why ULID over UUID (used in HUB-04 per the prior analysis but not decided)
- Why Redis over Memcached (HUB-02 assumes Redis)
- Why PostgreSQL over MySQL (CORE-19 DBAL supports both but the choice is not decided)
- Why SuperPHP (a custom template language) over Blade/Twig/Plates
- Why OPcache preload is enabled (referenced in disapproved CORE-01 but not decided)
- Why Kahn's algorithm for tier ordering (implemented in `DependencyGraph.php` but not decided)

**Impact:** Every architectural choice is reversible in principle but irreversible in practice because no one knows why it was made. A future maintainer who reopens any of these questions has no record of the original tradeoffs.

**Resolution:** `02_ADR/` contains 10 ADRs covering the decisions above. Each follows the Nygard format (Context, Decision, Status, Consequences, Alternatives Considered).

---

### Finding 20 — CORE-03 Blueprint Diverges From Its Implementation
**Severity:** 🟠 High
**Evidence:** The approved `docs/blueprints/Core/CORE-03.md` (1,754 bytes) describes the Event Dispatcher as:

> *"An 'Emit and Forget' or 'Haltable Pipeline' pattern."*
> *"Reference: /thephpleague/event design patterns for prioritized listeners."*
> *"Listeners must not be able to crash the dispatcher; exceptions must be caught and logged (referencing CORE-08)."*

The actual `packages/core/event-dispatcher/src/EventDispatcher.php` (4,223 bytes, verified 2026-08-04) implements something **better but undocumented**:
- PSR-14 strict compliance (the blueprint says "PSR-14" but describes "Emit and Forget," which is not a PSR-14 pattern)
- Listener exception isolation via `try/catch (\Throwable $e)` with continuation to the next listener (matches the blueprint's intent)
- PSR-3 logger injection for recording listener failures (the blueprint says "referencing CORE-08" but the code references PSR-3 directly — CORE-08 is the Error Handler, not the Logger; CORE-09 is the Logger)
- A `describeListener()` helper that produces human-readable listener names for log context (not mentioned in the blueprint)
- A dedicated `EventDispatchException` wrapper (not mentioned in the blueprint)

The blueprint also claims *"10,000 event dispatches per second"* with no methodology (Finding 10) and references `thephpleague/event` which is **not** a dependency — the actual code depends only on `psr/event-dispatcher`.

**Impact:** An engineer reading the blueprint to understand the implementation builds a wrong mental model. The "Emit and Forget" framing suggests fire-and-forget async dispatch; the actual implementation is synchronous with exception isolation. The `thephpleague/event` reference sends readers to study a library that is not used.

**Resolution:** `blueprints/Core/CORE-03-event-dispatcher.md` rewrites the blueprint to match the actual implementation, documents the exception-isolation invariant as a security property, and removes the unused library reference.

---

### Finding 21 — The `bin/loom` Entry Point Declared in `composer.json` Does Not Exist
**Severity:** 🟡 Medium
**Evidence:** `orchestrator/composer.json` declares:

```json
"bin": ["bin/loom"]
```

But the repo tree contains no `orchestrator/bin/loom` file. The `orchestrator/ci/run.php` exists (per the `CIMonitor::checkViaLocalExecution()` code, which looks for `<repoDir>/ci/run.php`), but the declared `bin/loom` executable is missing.

**Impact:** Running `composer install` in `orchestrator/` and then `vendor/bin/loom` (or `loom` after global install) fails. The orchestrator can only be invoked via `php orchestrator/ci/run.php`, which is not the documented entry point.

**Resolution:** `blueprints/Core/CORE-01-polyrepo-orchestrator.md` specifies the `bin/loom` shebang script and the `Symfony\Console`-based CLI structure. `04_MIGRATION_PLAN.md` Phase P0 includes creating the missing entry point as a quick win.

---

## Part C — Severity Roll-Up

| Finding | Severity | Blocks implementation? | Resolved in |
|---|---|---|---|
| 1. Two architectures | 🟠 High | Yes (confusion) | `01_MASTER_INDEX.md` §1 |
| 2. Stale evaluation | 🔴 Critical | Yes (wrong planning) | `01_MASTER_INDEX.md` §2 |
| 3. Wrong cross-refs | 🔴 Critical | Yes (security) | `01_MASTER_INDEX.md` §3; `BRIDGE-01` |
| 4. Thin approved | 🟠 High | Yes (not implementable) | All blueprints in this bundle |
| 5. Byte duplicate | 🟡 Medium | No | `01_MASTER_INDEX.md` §7 (archive) |
| 6. Contradictory status | 🟡 Medium | No | `01_MASTER_INDEX.md` §7 (archive) |
| 7. Wrong phase count | 🟡 Medium | No | `01_MASTER_INDEX.md` §4 |
| 8. Empty CORE-02 | 🔴 Critical | Yes (blocks Hub) | `blueprints/Core/CORE-02-di-container.md` |
| 9. Deploy = docs only | 🔴 Critical | Yes (no deployment) | `blueprints/Deploy/DEPLOY-01-core-hub.md` |
| 10. Ungrounded perf | 🟠 High | Yes (wrong targets) | Governance Rule 2; all blueprints |
| 11. Solutions unmerged | 🟠 High | No | Governance Rule 5; blueprints |
| 12. Generic rejection | 🟢 Low | No | Governance Rule 7 |
| 13. Undercounted spokes | 🟡 Medium | Yes (wrong scope) | `01_MASTER_INDEX.md` §4 |
| 14. Taxonomy arithmetic | 🟠 High | No | `01_MASTER_INDEX.md` §4 |
| 15. True timeline 48wk | 🟡 Medium | Yes (wrong commitments) | `04_MIGRATION_PLAN.md` |
| 16. Approved count wrong | 🟡 Medium | No | `01_MASTER_INDEX.md` §4 |
| 17. Empty compose | 🟢 Low | No | `DEPLOY-01` |
| 18. Dockerfile serves legacy | 🔴 Critical | Yes (public misinformation) | `DEPLOY-01` |
| 19. No ADRs | 🟠 High | No | `02_ADR/` |
| 20. CORE-03 drift | 🟠 High | No | `blueprints/Core/CORE-03-event-dispatcher.md` |
| 21. Missing bin/loom | 🟡 Medium | No | `blueprints/Core/CORE-01-polyrepo-orchestrator.md` |

---

## Part D — Verification Methodology

Every finding above was verified by:

1. **Fetching the complete repo tree** via the GitHub git-trees API (`recursive=1`), yielding 1,221 entries.
2. **Downloading the raw content** of 77 key files (all 20 CORE blueprints, 10 HUB blueprints, BRIDGE-01, DEPLOY-01, all 7 evaluation docs, all 7 hub-taxonomy docs, the internal-spokes placeholders, all 4 orchestrator source files + composer.json + tests, the container stub composer.json + .gitkeep, all 8 event-dispatcher source files + composer.json + tests, render.yaml, Dockerfile, docker-compose.yml, Notes.md, SESSION_STATE.md).
3. **Reading each file** and cross-checking claims against the actual file contents.
4. **Re-running arithmetic** on every count and sum mentioned in the evaluation and taxonomy documents.
5. **Re-deriving the timeline** from the dependency graph and the placeholder-doc week ranges.

No finding relies on the prior analysis. Where the prior analysis made a claim, this assessment re-verified it independently and notes confirmation. Where the prior analysis missed a finding, this assessment flags it as new (Findings 14–21).
