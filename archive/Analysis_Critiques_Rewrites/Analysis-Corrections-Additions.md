## Merged Files List
- 1. 00_CRITIQUE(2).md (19.5 KB)
- 2. 01_MASTER_INDEX(2).md (17.9 KB)
- 3. HUB-01(1).md (6 KB)
- 4. HUB-06(1).md (4.6 KB)
- 5. HUB-21.md (4.2 KB)
- 6. HUB-22.md (3.3 KB)
- 7. HUB-23.md (3.3 KB)
- 8. HUB-24.md (3.1 KB)
- 9. HUB-25.md (3 KB)
- 10. HUB-26.md (4.1 KB)
- 11. HUB-27.md (3.2 KB)
- 12. HUB-28.md (2.5 KB)
- 13. HUB-29.md (3.2 KB)
- 14. HUB-30.md (3.1 KB)
- 15. ISPOKE-02.md (2.4 KB)
- 16. ISPOKE-03.md (2.3 KB)
- 17. ISPOKE-04.md (2.3 KB)
- 18. ISPOKE-05.md (3.9 KB)


## 1. 00_CRITIQUE(2).md

```md
# DGLab Blueprint System — Critique & Findings

**Scope of review:** `docs/architecture/origin/**`, `docs/blueprints/**`, `docs/evaluation/**`,
`docs/hub-taxonomy/**`, `docs/internal-spokes/**`, `packages/**`, `orchestrator/**`, `render.yaml`,
`SESSION_STATE.md`, plus supporting cross-checks against real code in the repo.

Every finding below is traceable to specific files/lines in `DGCodeIdeas/DGLab` (`main` branch, as of
this review). This is a technical audit, not a restatement of the repo's own self-assessment — several
findings directly contradict the repo's own `docs/evaluation/` scores.

---

## Finding 1 — Two incompatible architectures share the "Sovereign Stack / Hub-and-Spoke" name

The repository contains **two mutually exclusive systems**, both calling themselves "Sovereign Stack"
and both calling themselves "Hub-and-Spoke," with no document anywhere stating that one supersedes
the other.

**Vision A — the monolith rebuild** (`docs/architecture/origin/HUB_AND_SPOKE.md`,
`STRATEGIC_OVERVIEW.md`, `DETAILED_SYSTEM_ANALYSIS.md`, `ComponentBlueprints/**`, `PhasedBlueprints/**`,
`Strategic/DGLAB_STRATEGIC_BLUEPRINT.md`, `Strategic/STRATEGIC_BLUEPRINT.md`,
`Sovereign_Stack_Blueprint/**`):
- "Hub" = **CMS Studio**, a single monolithic PHP application, the *only* service exposed to the
  internet.
- "Spokes" = plain PHP classes living in `app/Spokes/`, resolved in-process
  (`Application::getInstance()->get(MangaScriptSpoke::class)`), with **zero independent routing**.
- Front end = SuperPHP + "Superpowers" SPA (server-side DOM morphing), Nexus (Swoole WebSockets),
  MangaScript (AI novel→manga), matching the actual code under `Legacy/app/**`.

**Vision B — the polyrepo rebuild** (`docs/blueprints/Core|Hub|Spoke|Bridge|Deploy/**`,
`docs/hub-taxonomy/**`, `docs/internal-spokes/**`, `docs/external-spokes/**`, `orchestrator/**`,
`packages/core/**`):
- "Hub" = a *tier* of ~30 independent shared-service repos (config, cache, auth, queue…).
- "Spokes" = separate Internal/External **applications**, each a distinct deployable, isolated by a
  mandatory `BRIDGE-01` security boundary.
- `CORE-01` is literally a **Polyrepo Orchestrator** — i.e., the system explicitly assumes multiple
  git repositories, the opposite of Vision A's single codebase.

These aren't variations on a theme — Vision A is a modular monolith, Vision B is a distributed,
tier-isolated system with a completely different security model, deployment topology, and dependency
direction. A developer or agent reading `docs/architecture/origin/HUB_AND_SPOKE.md` first will build
the wrong mental model for everything under `docs/blueprints/`, and vice versa. Nothing in either tree
cross-references the other or explains which one is current. (Circumstantial evidence, e.g. the
`orchestrator/` and `packages/core/*` code and this project's own recent working history, indicates
Vision B is the active effort — but that is inference from the filesystem, not documentation.)

## Finding 2 — The evaluation layer scored a version of Core that no longer exists

`docs/evaluation/BLUEPRINT_RANKINGS.md` and `EVALUATION_SUMMARY.md` assign scores and descriptions to
`CORE-01`…`CORE-20` that **do not match a single one of the current files** in `docs/blueprints/Core/`:

| ID | Evaluation doc claims | Actual current file content |
|----|------------------------|------------------------------|
| CORE-01 | "Bootstrapper & Kernel" (94/100) | **Polyrepo Orchestrator ("The Loom")** |
| CORE-02 | "Lifecycle Hooks" (89/100) | **Dependency Injection Container** |
| CORE-03 | "Service Container" (92/100) | **PSR-14 Event Dispatcher** |
| CORE-04 | "Encryption Primitives" (89/100) | **PSR-7 HTTP Message & Factory** |
| CORE-05 | "Router & Dispatch" (91/100) | **PSR-15 Middleware & Request Handler** |
| CORE-06 | "Request/Response" (89/100) | **Attribute-Based Router** |
| CORE-07 | "Middleware Pipeline" (90/100) | **SuperPHP Lexer** |
| CORE-08 | "Filesystem Abstraction" (89/100) | **Global Error & Exception Handler** |
| CORE-09 | "Error Handling" (91/100) | **PSR-3 Logging Service** |
| CORE-11 | "ORM & Query Builder" (88/100) | **SuperPHP Parser** |
| CORE-14 | "Caching Layer" (85/100) | **Filesystem Abstraction** |
| CORE-15 | "Validation Engine" (86/100) | **Cache Abstraction (PSR-6/16)** |
| CORE-16 | "Logging & Observability" (84/100) | **Binary Encryption Envelope** |
| CORE-17 | "Testing Framework" (85/100) | **Service Provider System** |
| CORE-18 | "Event System" (83/100) | **Core Kernel & Lifecycle ("The Sovereign Kernel")** |
| CORE-19 | "Service Locator" (82/100) | **Database Abstraction Layer** |

Every score, every "why it's critical" rationale, and the recommended "Implementation Sequence"
(`CORE-01 → CORE-02 → CORE-03 → CORE-05 → CORE-06 → CORE-07...`) in the ranking doc is built on the
**old** numbering, where the kernel was `CORE-01`. In the **current** numbering the kernel is
`CORE-18` — second-to-last in the tier. Taken literally, the evaluation's own recommended sequence
would have you build the polyrepo release-automation tool and a router *before* the kernel that boots
the application at all. The 87/100 "overall quality score" is not a reflection of what's actually in
`docs/blueprints/` today; it's a snapshot of an earlier renumbering that was never reconciled.

## Finding 3 — Cross-references inside the *current* canonical set are already wrong

This isn't only inherited drift from an old numbering — the "approved" blueprints contradict each
other today:

- `docs/blueprints/Spoke/Bridge/BRIDGE-01.md` lists a Core dependency as
  `CORE-09: Cryptography & Hashing (Payload Verification)`.
- The actual `docs/blueprints/Core/CORE-09.md` is titled **"PSR-3 Logging Service / Structured Logging
  Engine"** — it has nothing to do with cryptography.
- The component that *is* cryptography is `CORE-16` ("Binary Encryption Envelope / Cryptographic
  Foundation").

**Addendum (discovered while rewriting the Internal Spoke tier — this finding was originally
undercounted):** the initial review of this finding checked only `ISPOKE-01`, `ESPOKE-01`, and
`BRIDGE-01` and found those three internally consistent, leading to the conclusion that "the drift is
concentrated in the Core tier's internal renumbering, not a general symptom across every tier." Reading
the full `docs/blueprints/Spoke/Internal/ISPOKE-02.md`–`ISPOKE-15.md` set shows that conclusion was
wrong — the same mislabeling recurs repeatedly across the tier, in at least four distinct, independently
recurring patterns:

| Pattern | Wrong ID used as | Actually is | Occurrences |
|---|---|---|---|
| A | `CORE-09` = "Cryptography & Hashing" | PSR-3 Logging Service (crypto is `CORE-16`) | `BRIDGE-01`, `ISPOKE-05`, `ISPOKE-06`, `ISPOKE-10`, `ISPOKE-13`, `ISPOKE-15` — **6 files** |
| B | `HUB-28` = "Distributed Ledger & Analytics Engine" | Hub API Versioning Strategy — **no Hub blueprint anywhere actually covers this description** | `ISPOKE-05`, `ISPOKE-10`, `ISPOKE-12`, `ISPOKE-13`, `ISPOKE-15` — **5 files** (see Finding 14 — this one isn't just a wrong pointer, it's a missing component) |
| C | `HUB-11` = "Job Queue / Background Processing" | Cloud Storage (Queue is `HUB-10`) | `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md`, `ISPOKE-07`, `ISPOKE-09` — **3 files** |
| D | `HUB-12` = "Event-driven Messaging & Pub/Sub" | Notification Service (Event Bus/pub-sub is `HUB-09`) | `ISPOKE-07`, `ISPOKE-08`, `ISPOKE-15` — **3 files** |
| E | `HUB-13`/`HUB-14` swapped for Search/Media | `HUB-13`=I18n, `HUB-14`=Search, Media is `HUB-18` | `ISPOKE-09` (both directions), `ISPOKE-14` (asset storage pointed at `HUB-14` instead of `HUB-11`) |

Pattern A alone appears in 6 independent files, including the single most security-sensitive document
in the system (`BRIDGE-01`). This is not isolated drift — it indicates whatever process generated these
blueprints was working from a consistently different (and itself internally-inconsistent — patterns A
through E don't agree with each other on what the "old" numbering was) mental model of the Hub/Core
tiers than what actually ended up in the files. All instances of all five patterns are corrected in the
`02_EXEMPLARS/` rewrites in this delivery; see `01_MASTER_INDEX.md` §3 for the consolidated correction
table. By contrast, cross-references to `HUB-04`, `HUB-05`, `HUB-06`, `HUB-15`, `HUB-21`, `HUB-26` were
checked across the same file set and are consistently correct — the drift is concentrated in a specific
subset of IDs (`CORE-09`, `HUB-11`–`14`, `HUB-28`), not spread evenly across every reference.

## Finding 4 — "Approved" isn't a proxy for "more substantive"

Compare `docs/blueprints/disapproved/CORE-01.md` (rejected) with `docs/blueprints/Core/CORE-01.md`
(approved):

- **Disapproved CORE-01** ("Foundational Bootstrapper & Kernel"): defines a `KernelInterface`, a typed
  `Environment` enum, a complete `ErrorHandler` class with real PHP 8 code (`set_error_handler`,
  `set_exception_handler`, `register_shutdown_function`), a boot-sequence **sequence diagram**, named
  performance budgets tied to a specific mechanism (OPcache preload), and 100%-path unit test criteria.
  ~7.6 KB.
- **Approved CORE-01** ("Polyrepo Orchestrator"): five short prose sections, one Mermaid flowchart,
  zero code, zero interfaces. ~2.2 KB.

The disapproval note for the entire 72-file `disapproved/` folder is a single generic sentence —
see Finding 12 — so there's no record of *why* the more code-complete, more rigorous document was the
one rejected. Whatever the actual reason, "disapproved" is being used here as a synonym for
"doesn't match the current template," not "lower engineering quality," and the evaluation layer treats
the 81 "approved" docs as uniformly higher quality than the 72 rejected ones without ever comparing them
side by side.

## Finding 5 — A byte-for-byte duplicate masquerading as a mobile-optimized variant

`docs/architecture/origin/Sovereign_Stack_Blueprint/SOVEREIGN_STACK_MASTER.md` and
`docs/architecture/origin/Mobile_Optimized/SOVEREIGN_STACK_MASTER.md` are **identical**
(`md5sum` match, `diff` produces zero lines) — both 126,420 bytes. The `Mobile_Optimized/` directory
name promises a variant tuned for mobile reading/consumption; it contains none. This is either an
abandoned task or a copy left in place by mistake, but either way it doubles the maintenance surface
of a 126 KB document with zero benefit, and it will silently drift out of sync the next time only one
copy gets edited.

## Finding 6 — Sibling documents in the same folder contradict each other's completion claims

Within `docs/architecture/origin/PhasedBlueprints/`:
- `README.md`'s status table marks **Nexus** as `✅ COMPLETED (CORE)`.
- `ANALYSIS_REPORT.md`, sitting in the same directory, states **Nexus is 40% complete** ("L3: Building
  … Phases 1-2 Completed").

Within the wider `docs/architecture/origin/ComponentBlueprints/` tree:
- `README.md` marks **AdminPanel** as `✅ COMPLETED (LEGACY)`.
- `DECOMMISSIONING_PLAN.md`, in the same folder, lists AdminPanel under components being actively
  decommissioned, and the top-level `ComponentBlueprints/README.md`'s own "Legacy & Superseded" section
  separately marks it `🚫 SUPERSEDED`.

Also in `ANALYSIS_REPORT.md`, the footer literally reads:
```
*Report Generated: $(date)*
```
— an unexpanded shell command left in a static Markdown file. It was never actually regenerated
dynamically; the "generated" timestamp is a placeholder, not a date.

## Finding 7 — The "81-phase" claim doesn't match its own category table

`docs/architecture/origin/PhasedBlueprints/README.md` titles itself "81-Phased Developer's
Specifications Blueprint" and states the directory covers "81 distinct phases." Its own category
table sums to **99**:

```
AuthService 5 + SuperPHP Engine 10 + Superpowers SPA 10 + DownloadService 5 + EventDispatcher 5
+ AssetBundler 5 + TestSuite 10 + CMS Studio 10 + MangaScript 5 + Nexus 5 + StudioExpansion 6
+ AdminPanel 5 + DocumentationService 18 = 99
```

Whichever number is right, the document is internally inconsistent about the size of its own scope —
a basic arithmetic check that was never run before publishing.

## Finding 8 — The tier everything depends on has zero implementation, and nothing flags it

`docs/hub-taxonomy/hub-blueprint-taxonomy.md` marks `CORE-02` (DI Container) as a dependency of nearly
every Hub blueprint (`HUB-01`, `HUB-02`, and by extension almost everything downstream). Checking the
actual package:

- `packages/core/container/composer.json` declares itself: *"CORE-02: PSR-11 compliant Dependency
  Injection Container with autowiring, compiler passes, and circular dependency detection."*
- `packages/core/container/src/` contains **only `.gitkeep`** — no implementation at all.
- By contrast, `packages/core/event-dispatcher/src/` (CORE-03) has real, tested classes
  (`Event.php`, `EventDispatcher.php`, `ListenerProvider.php`, plus a full `tests/` suite), and
  `orchestrator/src/` (CORE-01) has real, tested classes (`RepoManager.php`, `CIMonitor.php`,
  `DependencyGraph.php`, `VersionBumpEngine.php`).

So two of the three earliest Core components are actually built and tested, and the one everything
else structurally depends on is an empty stub — yet no blueprint, roadmap, or evaluation document
identifies this as the critical-path risk it is. `BLUEPRINT_RANKINGS.md` calls `HUB-01` "CRITICAL... all
services depend on this" without noting that `HUB-01` itself depends on an unbuilt component.

## Finding 9 — The only "Deploy" blueprint doesn't deploy the application

`docs/blueprints/Deploy/DEPLOY-01.md` and the repo's `render.yaml` describe and configure exactly one
thing: a free-tier Render web service (`sovereign-stack-blueprints`) that serves the **Markdown
documentation** over PHP's built-in development server. There is no blueprint anywhere for deploying
the Core services, the ~30 Hub services, the Internal/External Spokes, the Bridge, or any datastore
(MySQL/Postgres, Redis, queue broker) that the other 116 documents describe building. For an "81-phase,"
multi-tier, polyrepo architecture, having a single deployment blueprint — and having it target the
*documentation site* rather than the *system* — is a scope gap large enough to call the tier
effectively unaddressed.

## Finding 10 — Performance targets are asserted, never grounded

Nearly every blueprint states a hyper-specific latency budget as settled fact:
"boot in < 0.15ms," "flag evaluation < 0.005ms," "10-repo dependency check in < 2 seconds," "cache hit
< 5ms," "DTO transformation + audit logging adds no more than 2ms." None of these numbers are tied
anywhere in the repo to a benchmark harness, a hardware/runtime baseline (PHP version, opcache state,
CPU class, network hop count), a load model, or measured results. `SESSION_STATE.md` is honest about
this for the one component that *was* actually built (Anvil): *"The full Anvil stack was not executed
against a live Docker or AWS environment... runtime validation has not been performed here."* That
same honesty doesn't appear anywhere in the 190+ architecture documents that assert millisecond-level
numbers as design constraints.

## Finding 11 — A "solutions" document was written, but never merged back into the blueprints it fixes

`docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` is a genuinely useful, self-identified list of real gaps —
e.g. *"Only 15 of Planned Spokes Documented,"* *"Bridge Single Point of Failure; No Redundancy
Strategy,"* *"Sparse Architectural Details for Cache (HUB-02) and Queue (HUB-11)."* These are accurate
observations. But none of the proposed fixes appear to have been folded back into `BRIDGE-01.md`,
`HUB-02.md`, or `HUB-11.md` themselves — the "solutions" live in a separate 36 KB document that the
actual blueprints don't reference and don't reflect. The critique found the problems; nothing
integrated the fixes.

## Finding 12 — 72 rejected blueprints share one boilerplate rejection reason

`docs/blueprints/disapproved/` contains 72 files (all of Core, Hub, and Spoke) plus a single file whose
entire content is: `Reason: Varying from the original blueprints.` That one sentence is presented (via
`EVALUATION_SUMMARY.md`) as covering all 72 rejections uniformly, with no per-file diff, no specific
deviation cited, and no reviewer notes. `EVALUATION_SUMMARY.md` calls this corpus a *"Learning Asset"*
that documents *"decision-making rigor"* — but there is no rigor recorded, just one generic line
reused for everything. (As Finding 4 shows, at least one of these 72 rejections was measurably more
technically complete than the version that replaced it.)

## Finding 13 — The Internal Spoke tier is under-counted in every score and timeline

Every evaluation document (`EVALUATION_SUMMARY.md`, `BLUEPRINT_RANKINGS.md`) states Internal Spokes as
**"15 blueprints."** But `docs/internal-spokes/placeholder-blueprints.md` documents **10 additional**
planned spokes (`ISPOKE-16` through `ISPOKE-25`) that exist only as `TBD` / "Placeholder" stubs with
estimated documentation dates — i.e., the tier's real scope is **25**, and 40% of it was excluded from
every quality score, every roadmap week-count, and the "32-week" master implementation timeline.

---

## Finding 14 — Five Internal Spoke blueprints depend on a Hub component that was never actually specified

`ISPOKE-05`, `ISPOKE-10`, `ISPOKE-12`, `ISPOKE-13`, and `ISPOKE-15` all declare a dependency on
`HUB-28: Distributed Ledger & Analytics Engine` — described consistently across all five files as
providing real-time metrics, MRR/Churn/LTV dashboards, feature-rollout impact monitoring, and security
analytics. No such blueprint exists anywhere in `docs/blueprints/Hub/` — the real `HUB-28` is "Hub API
Versioning Strategy," a completely unrelated component, and none of the other 29 Hub blueprints cover
this functionality either (`HUB-23`, Reporter, is the closest fit but is scoped to async CSV/PDF
export, not real-time metrics — redirecting these five dependencies to it would be a worse
misrepresentation than leaving the gap explicit). Five independent Spoke blueprints assuming a
component exists, with matching descriptions of what it does, is strong evidence this was a real,
intended Hub-tier phase that was simply never written — not a numbering slip. `01_MASTER_INDEX.md` §4
now tracks this as a required but unspecified Hub component (provisionally `HUB-31`) rather than
silently redirecting the five dependent Spokes to an ill-fitting substitute.

## Net assessment

None of the individual documents reviewed here are badly *written* — the prose is fluent, the Mermaid
diagrams render, the section templates are consistent. The failures are structural and factual:
**two incompatible architectures share a name with no disambiguation; the self-graded evaluation layer
describes a version of the Core tier that no longer exists; live cross-references inside the current,
"approved" set are wrong; a critical-path dependency has zero implementation with no flag anywhere; the
one deployment blueprint deploys the wrong thing; and known, self-identified weaknesses were written
down but never actually applied.** A blueprint system's entire value is being a reliable map of the
territory — right now this one has at least two conflicting maps, several waypoints on the current map
point to the wrong place, and one clearly marked road (Deploy) leads somewhere other than the city it
claims to.

The rebuilt blueprint set in this delivery (see `01_MASTER_INDEX.md` onward) treats **Vision B**
(`docs/blueprints/Core|Hub|Spoke|Bridge`, the polyrepo model with real code in `orchestrator/` and
`packages/core/`) as canonical, since it's the one with working, tested implementation behind it, and
resolves every numbered finding above explicitly.
```

## 2. 01_MASTER_INDEX(2).md

```md
# DGLab / Sovereign Stack — Master Blueprint Index (v2)

**Status:** Canonical. Supersedes all documents listed in "Archived Predecessors" below.
**Applies to:** the polyrepo, tier-isolated architecture (Core → Hub → Bridge → Spokes).
**Resolves:** Findings 1–13 in `00_CRITIQUE.md`.

---

## 1. Single-source-of-truth declaration

This index formally designates **one** architecture as canonical, ending the two-architecture
ambiguity in Finding 1.

**Canonical (this document tree + the following existing paths):**
- `docs/blueprints/Core/CORE-01..20`
- `docs/blueprints/Hub/HUB-01..30`
- `docs/blueprints/Spoke/Internal/ISPOKE-01..25` (see §4 for the corrected count)
- `docs/blueprints/Spoke/External/ESPOKE-01..15`
- `docs/blueprints/Spoke/Bridge/BRIDGE-01`
- `docs/blueprints/Deploy/*` (expanded — see §6)
- `orchestrator/` (CORE-01 reference implementation)
- `packages/core/*` (CORE-tier reference implementations)

**Archived (Vision A — the CMS-Studio monolith rebuild). Kept for historical reference only, moved
under `docs/archive/vision-a-monolith/`, and prefixed with a banner pointing here:**
- `docs/architecture/origin/HUB_AND_SPOKE.md`
- `docs/architecture/origin/STRATEGIC_OVERVIEW.md`
- `docs/architecture/origin/DETAILED_SYSTEM_ANALYSIS.md`
- `docs/architecture/origin/ComponentBlueprints/**`
- `docs/architecture/origin/PhasedBlueprints/**`
- `docs/architecture/origin/Strategic/**`
- `docs/architecture/origin/Sovereign_Stack_Blueprint/**` and its byte-identical
  `Mobile_Optimized/` twin (delete the twin outright — see §7)
- `Legacy/**` (the actual code for Vision A; already named `Legacy`, which is correct — just needs the
  docs pointing at it archived alongside it instead of living next to the active docs)

**Rule going forward:** nothing may be added under `docs/architecture/origin/` without a header
banner stating which vision it belongs to. In practice, new work should not be added there at all —
new canonical content goes under `docs/blueprints/`.

---

## 2. Corrected Core-tier map (resolves Finding 2)

The table below is the **authoritative** CORE-01..20 mapping. Anything in
`docs/evaluation/BLUEPRINT_RANKINGS.md` or `EVALUATION_SUMMARY.md` that contradicts this table is
stale and is superseded by this document. (Those two files should be deleted or explicitly marked
`ARCHIVED — describes a pre-renumbering Core tier` rather than edited in place, so there is no
ambiguity about which version is authoritative going forward.)

| ID | Component | Real implementation | Build status |
|----|-----------|---------------------|---------------|
| CORE-01 | Polyrepo Orchestrator ("Loom") | `orchestrator/` | ✅ Implemented + tested |
| CORE-02 | Dependency Injection Container | `packages/core/container/` | ❌ **Stub only — `.gitkeep`, blocking** |
| CORE-03 | PSR-14 Event Dispatcher | `packages/core/event-dispatcher/` | ✅ Implemented + tested |
| CORE-04 | PSR-7 HTTP Message & Factory | — | 📝 Not started |
| CORE-05 | PSR-15 Middleware & Request Handler | — | 📝 Not started |
| CORE-06 | Attribute-Based Router | — | 📝 Not started |
| CORE-07 | SuperPHP Lexer | — | 📝 Not started |
| CORE-08 | Global Error & Exception Handler | — | 📝 Not started |
| CORE-09 | PSR-3 Logging Service | — | 📝 Not started |
| CORE-10 | Configuration & Environment Loader | — | 📝 Not started |
| CORE-11 | SuperPHP Parser | — | 📝 Not started |
| CORE-12 | SuperPHP Compiler | — | 📝 Not started |
| CORE-13 | CLI Engine (Console) | — | 📝 Not started |
| CORE-14 | Filesystem Abstraction | — | 📝 Not started |
| CORE-15 | Cache Abstraction (PSR-6/16) | — | 📝 Not started |
| CORE-16 | Binary Encryption Envelope | — | 📝 Not started |
| CORE-17 | Service Provider System | — | 📝 Not started |
| CORE-18 | Core Kernel & Lifecycle | — | 📝 Not started |
| CORE-19 | Database Abstraction Layer | — | 📝 Not started |
| CORE-20 | Developer CLI Toolchain ("Sovereign Forge") | — | 📝 Not started |

**Critical-path correction:** the true build-blocking dependency is **CORE-02**, not the kernel
(CORE-18) and not the orchestrator (CORE-01, already done). Every Hub blueprint that lists CORE-02 as
a dependency (nearly all of them, per `hub-taxonomy/hub-blueprint-taxonomy.md`) is currently blocked.
§5 of this index makes closing CORE-02 the top priority, ahead of continuing Hub-tier documentation.

---

## 3. Cross-reference corrections (resolves Finding 3)

What began as a single fix (`BRIDGE-01`'s `CORE-09`) turned out to be five recurring, independently-
introduced mislabeling patterns once the full Internal Spoke tier was read. This table is now the
single correction reference for all of them — every `02_EXEMPLARS/ISPOKE-*.md` file applies it.

| Pattern | Wrong reference | Corrected reference | Files affected |
|---|---|---|---|
| A | `CORE-09: Cryptography & Hashing` | `CORE-16: Binary Encryption Envelope` | `BRIDGE-01`, `ISPOKE-05`, `06`, `10`, `13`, `15` |
| B | `HUB-28: Distributed Ledger & Analytics Engine` | No existing Hub ID matches — see §4, provisional `HUB-31` | `ISPOKE-05`, `10`, `12`, `13`, `15` |
| C | `HUB-11` used for Queue/background jobs | `HUB-10: Queue & Job Dispatcher` (`HUB-11` is Cloud Storage) | `SOLUTIONS_TO_WEAKNESSES.md`, `ISPOKE-07`, `09` |
| D | `HUB-12` used for Event Bus/pub-sub | `HUB-09: Event Bus / Message Broker` (`HUB-12` is Notify) | `ISPOKE-07`, `08`, `15` |
| E | `HUB-13`/`HUB-14` swapped for Search/Media | `HUB-14`=Search, Media is `HUB-18` (`HUB-13` is I18n) | `ISPOKE-09`, `14` |

Any future audit of this kind should be re-run whenever a Core/Hub-tier ID is reassigned — a one-line
CI check (`grep -R "CORE-[0-9]\+\|HUB-[0-9]\+" docs/blueprints | validate-against(§2/§4 tables)`) is
specified in §8 to make this class of bug impossible to reintroduce silently.

---

## 4. Corrected tier inventory (resolves Finding 13, Finding 14)

| Tier | Documented | Placeholder-only | **True total** |
|------|-----------|-------------------|-----------------|
| Core | 20 | 0 | 20 |
| Hub | 30 | **1 (`HUB-31`, new — see below)** | **31** |
| Internal Spoke | 15 | 10 (`ISPOKE-16`–`25`) | **25** |
| External Spoke | 15 | 0 | 15 |
| Bridge | 1 | 0 | 1 |
| Deploy | 1 | expand — see §6 | see §6 |

**`HUB-31` (new, provisional): Real-Time Analytics & Metrics Ledger.** Registered per
`00_CRITIQUE.md` Finding 14 — five Internal Spoke blueprints (`ISPOKE-05`, `10`, `12`, `13`, `15`)
independently and consistently describe a dependency matching this description (real-time metrics,
MRR/Churn/LTV, feature-rollout impact monitoring, security analytics feeds) under the wrong ID
(`HUB-28`, which is actually API Versioning). No existing Hub blueprint covers this scope — `HUB-23`
(Reporter) is the nearest neighbor but is scoped to async batch export, not real-time metrics streams,
and redirecting five dependents to a component that doesn't actually do what they need would just be a
different flavor of the same problem this whole exercise is fixing. `HUB-31` is tracked here as a
required component with an open, not-yet-written blueprint — the five dependent `ISPOKE` files in this
delivery reference it as `HUB-31 (pending)` rather than either the wrong `HUB-28` pointer or a
silently-wrong redirect to `HUB-23`.

All roadmap week-counts and "32-week total timeline" claims in `BLUEPRINT_RANKINGS.md` assumed 15
Internal Spokes; with the corrected count of 25, Phase 3 (Internal Spokes) should be re-estimated at
roughly 1.6× its previous duration, or explicitly scoped to ship the 15 documented spokes first and
treat `ISPOKE-16`–`25` as a distinct Phase 3b. The Hub-tier estimate should add one phase for `HUB-31`.

---

## 5. Revised implementation sequence

Unlike the stale sequence in `BLUEPRINT_RANKINGS.md` (built on the old CORE numbering), this sequence
is derived from the **current** dependency table and from what is *actually* built vs. stubbed:

1. **Close CORE-02** (DI Container) — every Hub blueprint depends on it; it is the one truly blocking
   gap in the entire system today. This is the single highest-leverage next PR.
2. **CORE-10, CORE-09, CORE-08** (Config, Logging, Error Handling) — no interdependencies among these
   three beyond CORE-02; can be parallelized once CORE-02 lands.
3. **CORE-18** (Kernel) — depends on Config + Error Handler + Container being real, not stubs.
4. **CORE-04/05/06** (HTTP Message → Middleware → Router) — the request pipeline, in that literal
   order (Router depends on Middleware depends on Message).
5. **CORE-19, CORE-15, CORE-14, CORE-16** (DBAL, Cache, Filesystem, Encryption) — the data/IO layer.
6. **CORE-07/11/12** (SuperPHP Lexer → Parser → Compiler) — only needed once a Spoke that renders UI
   is scheduled; can slip behind Hub-tier work if the first Spokes are API-only.
7. **CORE-13/17/20** (CLI, Service Providers, Dev Toolchain) — developer-experience layer; valuable
   early but not blocking.
8. **Hub tier**, in the order already given in `docs/hub-taxonomy/hub-dependency-graph.md` (that
   document's *internal* Hub-to-Hub and Hub-to-Core sequencing was checked in this review and is
   internally consistent — the only defect found in the Hub tier was the downstream Bridge citation
   in Finding 3, now fixed).
9. **BRIDGE-01**, before any External Spoke.
10. **Internal Spokes 01–15**, then **16–25** as Phase 3b (§4).
11. **External Spokes**.

---

## 6. Deploy tier — expanded scope (resolves Finding 9)

`DEPLOY-01` is renamed **`DEPLOY-00: Documentation Site`** and kept as-is (it correctly, if modestly,
describes hosting the Markdown docs on Render's free tier — nothing wrong with that blueprint on its
own terms, it was simply mislabeled as covering more than it does).

New blueprints added to close the actual gap:

- **`DEPLOY-01: Core & Hub Service Deployment`** — containerized deployment of the Core/Hub tier
  (PHP-FPM + Nginx, one image per Hub service, health checks wired to `HUB-15`).
- **`DEPLOY-02: Datastore Provisioning`** — MySQL/Postgres + Redis + queue broker provisioning,
  connection-secret management, backup/restore runbook.
- **`DEPLOY-03: Bridge & External Spoke Deployment`** — the public-facing tier, CDN/edge caching
  integration with `HUB-02`, and the network-isolation rules the Bridge's "Zero-Exposure Test"
  (see `BRIDGE-01.md`) needs to actually be enforceable in a real environment (network policy, not
  just a namespace-import scan).
- **`DEPLOY-04: Multi-Environment & Promotion Pipeline`** — how a change moves dev → staging →
  production across ~50+ independently deployable repos, tying into `orchestrator/` (CORE-01) for
  version-bump and release-gate automation.

Full specs for `DEPLOY-01`–`DEPLOY-04` are out of scope for this delivery's exemplar set but are
sequenced in the roadmap; `02_EXEMPLARS/` in this package includes a fleshed-out `DEPLOY-01` as a
demonstration of the expected fidelity.

---

## 7. Immediate cleanup actions

1. Delete `docs/architecture/origin/Mobile_Optimized/SOVEREIGN_STACK_MASTER.md` (Finding 5) — it is
   byte-identical to `Sovereign_Stack_Blueprint/SOVEREIGN_STACK_MASTER.md` and adds nothing.
2. Fix the literal `$(date)` placeholder in `PhasedBlueprints/ANALYSIS_REPORT.md`, or mark the whole
   file archived per §1 (recommended, since it belongs to Vision A anyway).
3. Reconcile the Nexus and AdminPanel completion-status contradictions (Finding 6) — pick one status
   per component and delete the other claim; both live in files being archived per §1, so this can be
   done as part of the archival pass rather than a separate fix.
4. Correct the arithmetic in `PhasedBlueprints/README.md` (Finding 7) — again, archived under §1, but
   the corrected category table should be preserved in the archive banner for historical accuracy.
5. Replace the single-sentence `disapproved/` rejection note (Finding 12) with a per-file rationale —
   at minimum, a one-line diff summary ("varies from template by including implementation code; kept
   for reference, not because the code was wrong") for each of the 72 files, so the corpus can actually
   serve as the "learning asset" it's claimed to be.
6. Merge `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` line-by-line into the specific blueprint files it
   discusses (Finding 11), then delete or archive the standalone solutions doc — a proposed fix that
   isn't merged into the artifact it fixes has no effect.

---

## 8. Governance rules for this blueprint set going forward

These close the *mechanism* that produced Findings 1–13, not just the current instances of them:

1. **One numbering authority.** This file (§2, §4) is the only place a Core/Hub/Spoke ID's identity is
   defined. Any blueprint that changes a component's ID or scope must update this table in the same
   commit. A blueprint and this index disagreeing is treated as a broken build.
2. **No performance target without a method.** Every "CI Verification Criteria" section must name: the
   hardware/runtime baseline, the benchmark tool, and the load model — or state "target is provisional,
   unverified" explicitly. Bare millisecond claims (Finding 10) are no longer permitted as-is.
3. **Evaluation docs are dated snapshots, not living scores.** Any `docs/evaluation/*` file must carry
   a "valid as of commit `<sha>`" header. A quality score with no commit anchor cannot be trusted to
   describe current content (this is exactly how Finding 2 happened).
4. **Rejections need a real reason.** A `disapproved/` entry must state, specifically, what about it
   was rejected — "deviates from template" is not sufficient on its own if the alternative was also
   compared on technical merit (Finding 4).
5. **Solutions must land in the artifact, not beside it.** A weakness write-up is not resolved until
   the referenced blueprint file is edited. `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md`-style
   documents should be issue trackers, not permanent parallel content.
6. **Duplicate directories require a diff check before merge.** Nothing named `*_Optimized`,
   `*_v2`, or similar should be committed without CI verifying it is not byte-identical to its source
   (Finding 5).

---

## 9. What's in this delivery

```
DGLab-Blueprints-v2/
├── 00_CRITIQUE.md              # This review's findings (read first)
├── 01_MASTER_INDEX.md          # This file — governance + corrected maps + §10 ID policy
└── 02_EXEMPLARS/
    ├── CORE-01.md               # Polyrepo Orchestrator (matches orchestrator/ code)
    ├── CORE-02.md               # DI Container (the actual blocking gap; full contracts)
    ├── HUB-01.md .. HUB-30.md   # Full Hub tier — complete
    ├── BRIDGE-01.md             # The Vanguard (adds failover, fixes CORE-16 reference)
    ├── ISPOKE-01.md             # Admin Panel
    ├── ESPOKE-01.md             # Public CMS
    └── DEPLOY-01.md             # Core & Hub Service Deployment (closes Finding 9)
```

The exemplars share a single, enforced fidelity bar: real interface contracts (not prose-only
descriptions), a corrected and cited dependency list, an explicit benchmark methodology per Governance
Rule 2, and a "Resolves" line naming which finding(s) each addresses. Beyond the per-file fixes, the
full Hub-tier pass surfaced and corrected three cross-file bugs the original per-file review couldn't
have caught in isolation:
- `HUB-02`/`HUB-10` link the genuinely good existing pattern docs (`docs/cache-patterns/`,
  `docs/queue-patterns/`) instead of duplicating them, and in the process caught
  `SOLUTIONS_TO_WEAKNESSES.md` mislabeling the Queue blueprint as `HUB-11` (actually Cloud Storage)
  instead of `HUB-10`.
- `HUB-23` referenced `HUB-25` as "to be defined" even though `HUB-25` was already written elsewhere in
  the same tier — a stale forward-reference, now fixed and added to `HUB-23`'s formal dependency list.
- `HUB-21` is now the explicit single authority for the tenant-ID format (ULID) that `HUB-01` and
  `HUB-06` both depend on — see §10 below, added after a self-check found `HUB-06`'s own schema had
  drifted from it (`int`/`uuid` vs. `HUB-21`'s ULID).

Still outstanding: the remaining Internal/External Spokes (`ISPOKE-02`–`25`, `ESPOKE-02`–`15`), and
`DEPLOY-02`–`04`. Recommend Spokes next, in dependency order — `ISPOKE`/`ESPOKE` blueprints depend on
nearly the full Hub tier, which is now fully specified for them to build against.

## 10. Stack-wide entity ID policy

Added after a self-review of this delivery's own exemplars found the same class of drift the critique
documents elsewhere: `HUB-06.md`'s audit-record schema originally typed `user_id`/`tenant_id` as `int`
and its own `id` as `uuid`, while `HUB-01.md` and `HUB-21.md` typed `tenant_id` as a 26-character ULID.
Three different identifier schemes for what should be one concept, inside a delivery whose entire
purpose is fixing exactly this. Both have been corrected; this section is now the single place the
policy is stated so it doesn't drift again:

**Every entity primary/foreign-key identifier in the Sovereign Stack — tenant, user, audit record,
tenant-config override row, or otherwise — is a ULID** (26-character, Crockford Base32,
lexicographically sortable), not a database auto-increment integer and not a UUID. ULID is chosen over
UUID specifically because its sortability keeps index locality reasonable for high-write tables like
`HUB-06`'s audit log. The one documented exception: surrogate/internal-only primary keys with no
external reference or cross-service meaning (e.g., `HUB-01.md`'s `hub_config_overrides.id`, which is
never referenced outside that single table) may remain `BIGINT AUTO_INCREMENT` — the distinction is
whether an ID is ever passed between services or exposed to a client (ULID) versus purely local to one
table's own row identity (integer is fine).

Any blueprint introducing a new entity type must state which of the two categories its primary key
falls into, rather than picking a type ad hoc. This is a direct application of Governance Rule 1 (one
numbering/identity authority) extended to data types, not just component IDs.
```

## 3. HUB-01(1).md

```md
# PHASE HUB-01: Global Configuration & Feature Flags

## Tier
Hub (Shared Services)

## Resolves
`00_CRITIQUE.md` Finding 8 (this blueprint's dependency on `CORE-02` is now explicitly marked
**blocked** rather than silently assumed available) and Finding 10 (verification criteria now state a
method, not just a number).

## Component Name
Sovereign Hub Config & Flags

## Description
A global configuration management service that extends `CORE-10` (Config & Environment Loader) to
support multi-tenant configuration overrides, dynamic feature flags, and remote settings, so that Hub
and Spoke applications can toggle functionality without redeploying.

## Build Status
🔴 **Blocked.** Depends on `CORE-02` (DI Container), which per `01_MASTER_INDEX.md` §2 has zero
implementation. Depends on `CORE-10` (Config & Environment Loader), also not yet started. Do not begin
implementation work on this blueprint until both land — see the revised sequence in
`01_MASTER_INDEX.md` §5.

## Dependency Status
- **Upward:** `CORE-10` (Config & Env Loader), `CORE-02` (DI Container), `CORE-19` (DBAL — for
  tenant-specific dynamic overrides persisted to a database rather than static files).
- **Downward:** every subsequent Hub service and all Spokes consume `GlobalConfigInterface`.

## Sequencing Rationale
First Hub-tier phase because every later Hub service (Identity, Cache, Asset Pipeline) needs a single,
consistent way to read shared settings and evaluate feature toggles.

## Architectural Design

- **HubConfigRegistry** — merges static defaults (from `CORE-10`) with tenant-specific overrides
  (persisted via `CORE-19`). Merge direction is strict: tenant overrides may only *add or replace* keys
  explicitly present in their own override table; a tenant override can never introduce a key absent
  from the global default schema (prevents silent, unvalidated schema drift per tenant).
- **FeatureFlagManager** — evaluates toggle states against a `Context` (user, tenant, environment,
  and optionally a rollout-percentage bucket computed from a stable hash of the context's identifier,
  so a given user consistently lands on the same side of a percentage rollout across requests).
- **FlagEvaluator** — the rule engine behind `FeatureFlagManager`; supports boolean toggles,
  percentage rollouts, and allow/deny lists by tenant or user ID.

### Interface Contracts

```php
namespace SovereignStack\Hub\Config\Contracts;

interface GlobalConfigInterface
{
    /** Get a configuration value with tenant-aware fallback to the global default. */
    public function get(string $key, mixed $default = null, ?string $tenantId = null): mixed;

    /** Check whether a feature flag is active for the current resolved context. */
    public function feature(string $flag): bool;
}

interface FeatureManagerInterface
{
    public function isEnabled(string $flag, ?Context $context = null): bool;

    /** For multi-variant flags (e.g., A/B/C), returns the variant key, not just on/off. */
    public function getVariant(string $flag, ?Context $context = null): string;
}

/** Immutable evaluation context — construct once per request. */
final class Context
{
    public function __construct(
        public readonly ?string $userId = null,
        public readonly ?string $tenantId = null,
        public readonly string $environment = 'production',
    ) {}
}
```

### Tenant Override Data Model

```
hub_config_overrides
  id            BIGINT PK
  tenant_id     CHAR(26)   -- ULID; format owned by HUB-21 (Tenancy), not HUB-04 — see HUB-21.md
                           -- and 01_MASTER_INDEX.md §10 for the stack-wide ID policy
  config_key    VARCHAR(191)
  config_value  JSON
  updated_at    TIMESTAMP
  UNIQUE (tenant_id, config_key)
```

### Merge Logic Guarantee
A tenant override row may only be applied if `config_key` already exists in the static default
configuration schema loaded from `CORE-10`. `HubConfigRegistry::get()` must validate this at read time
(or, preferably, `CompilerPassInterface`-style validation at boot, rejecting invalid override rows
loudly rather than silently ignoring them) — this is the concrete mechanism behind the "tenant
overrides must never leak into the global pool" requirement below.

## Integration Strategy
- **Upward:** consumes `CORE-10` for static defaults, `CORE-19` for dynamic tenant overrides,
  `CORE-02` to be constructed and injected as a singleton.
- **Downward:** injected as a singleton into every Hub and Spoke service provider via the container's
  `singleton()` binding (see `CORE-02`).

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Tenant overrides never leak into the global default pool | Integration test: write an override for tenant A, then assert `get($key, tenantId: 'B')` and `get($key, tenantId: null)` both still return the unmodified default. |
| A dynamic flag change (written via `CORE-19`) is observable within a bounded, documented window | Integration test measuring wall-clock between a direct DB write and the next `feature()` call observing the new value, across a cache-invalidation cycle if `HUB-02` caching is in front of this service. State the actual measured number here once `HUB-02` is implemented — do not assert "within 1 second" without running this test, per Governance Rule 2. |
| Percentage rollout is stable per user across repeated evaluations | Unit test: call `getVariant()` 100 times for the same `Context.userId` and assert identical results every time (verifies the hash-bucket approach is deterministic, not re-randomized per call). |

## CI Verification Criteria
- Merge logic: automated test asserting cross-tenant isolation (see above) — this is a security
  property, not just a correctness one, and should be treated with the same rigor as `BRIDGE-01`'s
  isolation tests.
- Consistency: cache-invalidation window is measured and documented, not asserted from memory.
- No override may reference a `config_key` absent from the `CORE-10` default schema (validated at
  write time, not just read time, to fail fast).

## SemVer Impact
**Minor.** Extends `CORE-10`'s configuration surface without changing its contract.
```

## 4. HUB-06(1).md

```md
# PHASE HUB-06: Audit Log & Activity Tracker

## Tier
Hub (Shared Services)

## Resolves
This is the audit component `BRIDGE-01` depends on for its "Tier-Crossing" metadata flag and its
fail-closed audit requirement (`02_EXEMPLARS/BRIDGE-01.md` §5) — this rewrite makes that dependency
explicit and adds the availability contract `BRIDGE-01` needs from it.

## Component Name
Sovereign Auditor

## Description
Centralized logging for system-wide activity: a tamper-evident record of "who did what, and when"
across the polyrepo stack, with searchable audit trails for compliance and forensics.

## Build Status
🔴 **Blocked** on `CORE-19` (DBAL), `HUB-04` (Identity), `CORE-03` (Event Dispatcher — the one Core
component already implemented, see `packages/core/event-dispatcher/`).

## Dependency Status
- **Upward:** `CORE-19`, `HUB-04`, `CORE-03`. *(Matches taxonomy.)*
- **Downward:** `BRIDGE-01` (tier-crossing audit — critical), `HUB-16` (release gating), `ISPOKE-01`
  (Audit Viewer UI), `HUB-20` (Vault access logging).

## Architectural Design
- **AuditManager** — listens for system events, decides which require auditing.
- **LogWriter** — writes audit records asynchronously to a dedicated store.
- **ActivityTracker** — trait for Spoke models to auto-track CRUD operations.
- **AuditViewer** — Hub-level query/filter API by user, tenant, or action type.

```json
{
  "id": "ulid",
  "user_id": "ulid",
  "tenant_id": "ulid",
  "action": "document.update",
  "resource_type": "Document",
  "resource_id": "123",
  "changes": {"title": ["Old", "New"]},
  "ip_address": "string",
  "user_agent": "string",
  "timestamp": "iso8601",
  "signature": "sha256"
}
```

*(Corrected from an earlier draft of this file, which typed `user_id`/`tenant_id` as `int` and `id` as
`uuid` — inconsistent with `HUB-21`'s ULID policy for tenant identifiers and with the stack-wide ID
policy in `01_MASTER_INDEX.md` §10. All three fields are ULIDs now, matching `HUB-21.md` and
`HUB-01.md`'s `tenant_id CHAR(26)` schema.)*

```php
namespace SovereignStack\Hub\Contracts;

interface AuditorInterface
{
    public function record(string $action, ?string $resourceType = null, ?string $resourceId = null, array $metadata = []): void;
    public function search(array $criteria): array;
}
```

## Availability Contract (new — required by BRIDGE-01)
`BRIDGE-01`'s fail-closed policy means a Bridge instance that can't reach this service must reject the
request rather than skip logging. That makes `record()`'s availability, not just its correctness, a
security-relevant property. This blueprint therefore commits to:
- `record()` for tier-crossing events must have a documented, bounded timeout (not "best effort").
- A synchronous write path for tier-crossing events specifically (not the general `HUB-10`-queued path
  used for lower-stakes audit entries) — logging a Bridge crossing after the fact defeats the purpose
  if the process crashes between the crossing and an async flush.

## Integration Strategy
- **Upward:** `CORE-03` for `HubEvent` listening, `CORE-19` for persistence.
- **Downward:** Spoke applications use the `Auditable` trait. High-volume, non-critical audits may be
  queued via `HUB-10`; tier-crossing audits (Bridge) use the synchronous path above.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Tamper detection | Utility test: mutate one record in a chained-hash fixture set; assert the chain-verification utility flags exactly that record and everything downstream of it. |
| Zero-drop under load | Load test at a stated, reproducible rate (e.g., `k6` generating N logs/sec against a seeded fixture) — report the actual sustained rate the implementation handles rather than asserting "1000 logs/sec" unmeasured (Finding 10). |
| PII stripping | Unit test asserting a `changes` payload containing a `password` or `ssn` key is redacted before persistence, across at least one nested-object case, not just top-level keys. |
| Bridge audit synchronous-path latency | Measured directly as part of `BRIDGE-01`'s own DTO-transformation-latency benchmark (see `BRIDGE-01.md`) — not a separate unmeasured claim. |

## CI Verification Criteria
- Tamper-chain verification test, blocking.
- PII redaction test including nested payloads, blocking.
- Load test with a stated, reproducible target rate and actual measured throughput reported in the
  test output (not just pass/fail).
- Synchronous tier-crossing write path has explicit test coverage separate from the general
  queued-audit path.

## SemVer Impact
**Minor** for general audit features; **treat as Major** if the synchronous tier-crossing path's
timeout or failure behavior changes, since `BRIDGE-01`'s security posture depends on it.
```

## 5. HUB-21.md

```md
# PHASE HUB-21: Multi-tenancy Coordination Layer

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and makes this blueprint the explicit authority that
`ISPOKE-01`'s `TenantSwitcher` and `HUB-01`'s tenant-override merge logic (`HUB-01.md`) both build on —
previously the three documents referenced each other loosely without one being the clear source of
truth for "what is a tenant ID."

## Component Name
Sovereign Nexus (Tenancy)

## Description
Coordination layer for multi-tenant applications: tenant resolution (domain, header, or user),
database connection switching, and scope isolation for shared Hub services, guaranteeing Tenant A's
data never leaks into Tenant B's.

## Build Status
🔴 **Blocked** on `HUB-01` (Config), `HUB-04` (Identity), `HUB-08` (Gateway) — none implemented. Must
land before any tenant-aware Spoke is built (`ISPOKE-01` and every External Spoke assume this exists).

## Dependency Status
- **Direct Hub:** `HUB-01`, `HUB-04`, `HUB-08`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-19`, `CORE-10`, `CORE-02`.
- **Downward:** `HUB-01` (tenant config overrides reference this tenant-ID format), `HUB-02` (cache-key
  tenant prefixing), `HUB-11` (storage-path tenant prefixing), `ISPOKE-01`, every tenant-scoped Spoke.

## Tenant ID Format (new — closes an unstated cross-reference)
`HUB-01.md`'s override schema declares `tenant_id CHAR(26)` and attributes the format to `HUB-04` —
that attribution is corrected here: tenant identity is owned by **this** blueprint (`HUB-21`), not
`HUB-04` (which owns user identity, a related but distinct concept). This blueprint is the source of
truth: **tenant IDs are ULIDs** (26-character, Crockford Base32, lexicographically sortable).
`Tenant::id` is generated at creation time by `TenantResolver` and is immutable thereafter. `HUB-01.md`
should be read with this correction; its schema type (`CHAR(26)`) was already right.

**Stack-wide ID policy (new):** per `01_MASTER_INDEX.md` §10, every entity primary/foreign-key
identifier in the Sovereign Stack — tenant, user, audit record, or otherwise — is a ULID, not a mix of
UUID and integer types. This blueprint and `HUB-06` (Audit Log) are the two places that previously
disagreed on this (see `HUB-06.md`'s corrected schema); this is now the single stated policy both
defer to.

## Architectural Design
- **TenantResolver** — identifies the current tenant from the Request.
- **TenantScope** — global state object holding the current tenant's ID/config.
- **ConnectionSwitcher** — points `CORE-19` at the tenant's specific database if configured
  (database-per-tenant supported; column-based isolation is the default).
- **StorageIsolation** — prefixes `HUB-11` file paths with the Tenant ID.

```php
namespace SovereignStack\Hub\Contracts;

interface TenancyInterface
{
    public function current(): ?Tenant;
    public function runAs(string $tenantId, callable $callback): mixed;
}
```

## Integration Strategy
- **Upward:** registered as `CORE-05` middleware in `HUB-08`.
- **Downward:** Spoke applications inject `TenancyInterface`; global models implement a
  `BelongsToTenant` trait for automatic query scoping.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Cross-tenant leak prevention | Integration test: seed Users for Tenant A and Tenant B; run a `Users` query while Tenant A is active; assert zero Tenant-B rows returned — this is the same class of test as `HUB-01.md`'s config-isolation test and `BRIDGE-01`'s boundary tests, and should be held to the same CI-blocking severity. |
| Resolution speed | State environment before citing "< 0.1ms" — measure once `HUB-01`/`HUB-04` exist (Finding 10). |
| Cache-key tenant prefixing | Integration test: write a `HUB-02` cache entry under Tenant A, assert it is unreachable via the same key under Tenant B's context — verifies `HUB-02`'s tag/key namespacing actually incorporates the ULID from this blueprint. |

## CI Verification Criteria
- Cross-tenant leak test, blocking — treat with the same severity as `BRIDGE-01`'s Zero-Exposure Test.
- Cache-key isolation test, blocking.
- Resolution speed measured and reported with environment stated.

## SemVer Impact
**Major.** Transforms the stack into a multi-tenant platform.
```

## 6. HUB-22.md

```md
# PHASE HUB-22: Billing & Subscription Abstraction Layer

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and a concrete "no card data touches the server"
enforcement mechanism instead of a design-intent statement.

## Component Name
Sovereign Ledger (Billing)

## Description
Provider-agnostic billing/subscription layer abstracting Stripe, Paddle, or a custom billing engine
into one API: plans, subscriptions, invoices, payment methods.

## Build Status
🔴 **Blocked** on `HUB-21` (Tenancy), `HUB-20` (Vault), `HUB-06` (Audit), `HUB-17` (Webhooks) — none
implemented.

## Dependency Status
- **Direct Hub:** `HUB-21`, `HUB-20`, `HUB-06`, `HUB-17`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-19`, `CORE-03`.
- **Downward:** any Spoke gating features on subscription status.

## Architectural Design
- **BillingManager** — subscription checks and checkout creation.
- **SubscriptionEngine** — tracks state (Active, Trialling, Past Due).
- **InvoiceManager** — generates/stores internal invoice records.
- **WebhookHandler** — billing-specific webhooks via `HUB-17`.

```php
namespace SovereignStack\Hub\Contracts;

interface BillingInterface
{
    public function subscribed(string $tenantId, string $plan): bool;
    public function checkout(string $tenantId, string $plan): string;
}
```

## PCI-Scope Enforcement (tightened)
"Credit card data must never touch the Sovereign server" was previously a design statement with no
mechanism. Concretely: `BillingManager::checkout()` returns a **redirect URL to the provider's hosted
checkout page** (Stripe Checkout / Paddle Checkout) — it never accepts a card-data payload as a method
parameter, and no `BillingInterface` method signature anywhere in this package accepts raw card fields.
This is enforced by interface design, not by convention, and should additionally be enforced by a
static-analysis rule flagging any parameter named/typed suggestive of raw card data (`cardNumber`,
`cvv`, etc.) anywhere in this package.

## Integration Strategy
- **Upward:** `HUB-17` for async payment updates, `HUB-20` for provider API keys.
- **Downward:** Spoke applications use `BillingInterface` to guard features and initiate payments.
- **Contract:** emits `SubscriptionUpdated` via `HUB-09` for downstream processing.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| No network calls in test suite | CI runs the full suite against a "Mock Billing Driver" with network access disabled at the test-runner level (not just an unused real driver) — a hard failure if any HTTP call is attempted. |
| State transition accuracy | Integration test simulating a webhook sequence (`checkout.session.completed` → `invoice.paid`); assert `SubscriptionEngine` transitions `trialling` → `active` in the correct order, not just the final state. |
| PCI-scope static check | The static-analysis rule described above, run in CI on every PR touching this package. |

## CI Verification Criteria
- Network-isolated mock-driver test, blocking.
- State-transition-sequence test (not just end-state), blocking.
- PCI-scope static rule, blocking — this is what makes the "card data never touches the server"
  claim enforced rather than aspirational.

## SemVer Impact
**Minor.** Adds monetization capabilities.
```

## 7. HUB-23.md

```md
# PHASE HUB-23: Data Export & Reporting Service

## Tier
Hub (Shared Services)

## Resolves
The original blueprint's `ReportScheduler` design note said it "hooks into HUB-25 (to be defined)" —
`HUB-25` is defined (`HUB-25.md`, Sovereign Chronos, the Background Scheduler). That forward reference
was simply never updated once `HUB-25` was actually written; this is the same class of stale-reference
bug as `00_CRITIQUE.md` Finding 3, found independently while rewriting this tier. Fixed below, and
`HUB-25` is now added to this blueprint's formal dependency list, where it had been omitted.

## Component Name
Sovereign Reporter

## Description
Generates large-scale data exports (CSV, Excel, PDF) and scheduled reports: extracts from `CORE-19`,
generates in the background via `HUB-10`, delivers via `HUB-12`/`HUB-11`.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-10` (Queue), `HUB-12` (Notify) — none implemented.

## Dependency Status — corrected
- **Direct Hub:** `HUB-11`, `HUB-10`, `HUB-12`, and **`HUB-25`** (Scheduler — added; was referenced in
  prose as "to be defined" but omitted from the formal list even after `HUB-25` was written).
- **Transitive Core:** `CORE-19`, `CORE-14`.

## Architectural Design
- **ExportCoordinator** — orchestrates the export lifecycle.
- **DataStreamer** — iterates large datasets from the DBAL via PHP generators (flat memory profile).
- **FormatWriter** — CSV / Excel (OpenXML) writing logic.
- **ReportScheduler** — recurring reports, now concretely wired to `HUB-25`'s `SchedulerInterface`:
  `$schedule->job(new GenerateReportJob($reportId))->weekly()`, not a bespoke scheduling mechanism.

```php
namespace SovereignStack\Hub\Contracts;

interface ReporterInterface
{
    public function queueExport(string $query, string $format, array $options = []): string;
    public function getExportStatus(string $exportId): array;
}
```

## Integration Strategy
- **Upward:** built on `HUB-10`.
- **Downward:** Spoke applications provide "Export Blueprints" (SQL queries + headers).
- **Contract:** notifies via `HUB-12` once the file is ready in `HUB-11`; recurring reports are
  registered as `HUB-25` scheduled jobs, not a separate cron mechanism.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Memory-bounded large exports | Integration test streaming a real 100,000-row fixture table to CSV; assert peak memory via `memory_get_peak_usage()` stays under the stated bound — measured, not assumed from "uses generators." |
| CSV format integrity | Round-trip test: generate a CSV containing values with embedded commas, quotes, and newlines; re-parse it with a standard CSV parser and assert exact field recovery. |
| Expiration | Integration test: create an export file, fast-forward the fixture clock past 24 hours, assert `HUB-11` no longer serves it (verifies an actual TTL/cleanup mechanism exists, not just a documented intention). |

## CI Verification Criteria
- Memory-bound test with measured peak, blocking.
- CSV round-trip integrity test with adversarial field content, blocking.
- Expiration test against a real (or fixture-clock) TTL mechanism, blocking.
- `ReportScheduler` registration verified against `HUB-25`'s actual `SchedulerInterface`, not a
  bespoke scheduling shim.

## SemVer Impact
**Minor.** Adds reporting and data mobility features.
```

## 8. HUB-24.md

```md
# PHASE HUB-24: GraphQL Schema Registry (Pure PHP)

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign GraphQL Registry

## Description
Pure-PHP GraphQL schema registry/execution engine: Hub services and Spokes register schema fragments
(Types, Queries, Mutations) unified into a single API via `webonyx/graphql-php` — no Node/Apollo.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway), `HUB-04` (Identity), `HUB-05` (RBAC) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-04`, `HUB-05`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-02`, `CORE-06`, `CORE-04`.

## Architectural Design
- **SchemaRegistry** — collects schema fragments from registered providers.
- **UnifiedExecutor** — validates/executes queries against the stitched schema.
- **DirectiveEngine** — PHP-based `@auth`, `@cache`, `@tenant` directives — `@tenant` should resolve
  through `HUB-21`'s `TenancyInterface`, and `@auth` through `HUB-05`'s `GateInterface`, rather than
  each directive reimplementing authorization/tenancy logic independently.
- **BatchResolver** — Data Loader pattern, N+1 prevention.

```php
$registry->register('blog', [
    'type_defs' => 'type Post { id: ID!, title: String! }',
    'resolvers' => [
        'Query' => ['post' => fn($root, $args) => $db->find($args['id'])]
    ]
]);
```

```php
namespace SovereignStack\Hub\Contracts;

interface GraphQLInterface
{
    public function execute(string $query, array $variables = [], mixed $context = null): array;
    public function register(string $namespace, array $definition): void;
}
```

## Integration Strategy
- **Upward:** exposed via a single `/graphql` endpoint in `HUB-08`.
- **Downward:** Spoke applications provide `SchemaProvider` classes discovered at boot.
- **Contract:** resolvers return raw arrays/objects; the engine handles JSON conversion.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Namespace collision detection | Unit test: register two namespaces both defining the same root `Query` field; assert schema stitching fails loudly at registration time, not silently overwriting one. |
| Field-level RBAC enforcement | Integration test: query a field guarded by `@auth(ability: "admin.view")` as a non-admin fixture user; assert the field resolves to `null`/an authorization error per the GraphQL spec's partial-response semantics, not a full-request failure that breaks unrelated fields in the same query. |
| Data Loader N+1 prevention | Integration test with a query fetching N related records; assert the DBAL query count stays constant (not O(N)) as N grows — measured via a query-counting fixture, not asserted from "uses Data Loaders." |
| Complex-query latency | State environment before citing "< 20ms" — measure once `CORE-02`/`HUB-08` exist (Finding 10). |

## CI Verification Criteria
- Collision-detection test, blocking.
- Field-level RBAC test with correct partial-response behavior, blocking.
- Query-count (N+1) test, blocking.
- Latency measured and reported with environment stated.

## SemVer Impact
**Minor.** Enables modern, typed data fetching across the stack.
```

## 9. HUB-25.md

```md
# PHASE HUB-25: Background Scheduler & Cron Management

## Tier
Hub (Shared Services)

## Resolves
Confirms this is the component `HUB-23.md`'s `ReportScheduler` now formally depends on (see
`HUB-23.md`'s corrected dependency list), and adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Chronos (Scheduler)

## Description
Centralized scheduler for recurring background tasks: replaces crontab entries with a PHP fluent
interface, manages task overlaps, execution logs, and a unified automation dashboard.

## Build Status
🔴 **Blocked** on `HUB-10` (Queue), `HUB-02` (Cache), `HUB-06` (Audit) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-10`, `HUB-02`, `HUB-06`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-19`.
- **Downward:** `HUB-23` (Reporter — recurring report generation), any Spoke registering recurring
  tasks via `CORE-17` service providers.

## Architectural Design
- **ScheduleRegistry** — holds recurring tasks and their frequencies.
- **TaskRunner** — evaluates due tasks, dispatches to `HUB-10`.
- **LockManager** — uses `HUB-02`'s Redlock-based locking (see `HUB-02.md`) to prevent a task running
  concurrently across nodes — this reuses `HUB-02`'s `LockInterface` directly rather than a separate
  locking mechanism.
- **HistoryTracker** — records start/end/output of every execution via `HUB-06`.

```php
$schedule->command('cleanup:logs')->dailyAt('00:00')->withoutOverlapping();
$schedule->job(new DataSyncJob())->everyFiveMinutes();
```

```php
namespace SovereignStack\Hub\Contracts;

interface SchedulerInterface
{
    public function command(string $signature): TaskInterface;
    public function job(object $job): TaskInterface;
}
```

## Integration Strategy
- **Upward:** requires one system-level cron entry running `s-cli schedule:run` every minute.
- **Downward:** Spoke applications register tasks in their `CORE-17` service provider.
- **Contract:** tasks dispatch as standard `HUB-10` jobs.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Overlap prevention | Integration test: start a long-running "withoutOverlapping" task, then trigger `schedule:run` again before it finishes; assert the second invocation does not start a duplicate, verified against `HUB-02`'s real lock (not a mock) so the Redlock behavior is actually exercised. |
| Scheduling precision | State environment/clock-source before citing "within 1 second" — measure actual trigger drift over N cycles against real wall-clock time (Finding 10). |
| Failure visibility | Integration test: force a scheduled task to throw; assert the failure and its exception trace are recorded via `HUB-06`, retrievable through `AuditorInterface::search()`. |

## CI Verification Criteria
- Overlap-prevention test against a real `HUB-02` lock, blocking.
- Failure-visibility test with actual `HUB-06` retrieval, blocking.
- Scheduling precision measured over multiple cycles and reported with environment stated.

## SemVer Impact
**Minor.** Centralizes all recurring automation.
```

## 10. HUB-26.md

```md
# PHASE HUB-26: Shared UI Component Library (PHP-rendered)

## Tier
Hub (Shared Services)

## Resolves
Grounds this blueprint against `ISPOKE-01.md`'s `AdminShell` and `ESPOKE-01.md`'s "Public Theme"
consumption pattern, both of which already depend on this component — makes the two variants
(Admin vs. Public theme) an explicit, named contract instead of an implicit assumption.

## Component Name
Sovereign UI (Elements)

## Description
Reusable UI component library (Buttons, Modals, Tables, Forms) rendered entirely in PHP via SuperPHP
(`CORE-11`/`CORE-12`), ensuring visual/functional consistency across Spoke applications without
Node/NPM.

## Build Status
🔴 **Blocked** on `HUB-03` (Asset Pipeline) and `HUB-13` (I18n) — neither implemented. Also
transitively blocked on `CORE-11`/`CORE-12` (SuperPHP Parser/Compiler), which are later in the revised
Core sequence (`01_MASTER_INDEX.md` §5) — this is one of the later-buildable Hub components as a
result, despite being architecturally foundational for UI.

## Dependency Status
- **Direct Hub:** `HUB-03`, `HUB-13`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-11`, `CORE-12`.
- **Downward:** `ISPOKE-01` (Admin theme variant), `ESPOKE-01` (Public theme variant) — every Spoke.

## Theme Variant Contract (new — makes an implicit assumption explicit)
`ISPOKE-01.md` and `ESPOKE-01.md` both assume a themed variant of this library exists ("Admin theme,"
"Public theme") without this blueprint previously defining what that means concretely:

```php
namespace SovereignStack\Hub\Contracts;

interface ThemeInterface
{
    /** Design tokens (colors, spacing, typography) as CSS custom properties. */
    public function tokens(): array;

    /** Component variant overrides for this theme (e.g., denser table rows in Admin). */
    public function componentOverrides(): array;
}
```
`ComponentRegistry` resolves the active `ThemeInterface` from `HUB-01` config (per-Spoke, not
per-request) and applies token/override resolution at render time. Internal Spokes register the Admin
theme; External Spokes register the Public theme. This is the mechanism, not just the naming
convention, behind "Admin Theme" / "Public Theme" as used elsewhere in this document set.

## Architectural Design
- **ComponentRegistry** — maps tag names (`<s:ui:button />`) to SuperPHP view files.
- **ThemeEngine** — CSS variables/design tokens for the stack, resolves `ThemeInterface` per above.
- **IconLibrary** — pure-PHP SVG injector.
- **LayoutRegistry** — master shell layouts (Admin, Dashboard, Landing).

```php
namespace SovereignStack\Hub\Contracts;

interface UIComponentInterface
{
    public function render(array $attributes = []): string;
}
```

## Integration Strategy
- **Upward:** assets bundled/served via `HUB-03`.
- **Downward:** all Spokes MUST use these components for consistent branding — enforced by
  `ISPOKE-01.md`'s and `ESPOKE-01.md`'s "100% of rendered tags originate from HUB-26" CI checks.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Void-tag compliance | Static scan of every component template for void elements (`input`, `img`) lacking explicit self-closing syntax — required by SuperPHP's parser contract (`CORE-11`). |
| Bundle size | Measure actual gzipped size of the compiled core CSS/JS via the real `HUB-03` build pipeline once it exists; report the number, don't restate "< 150KB" unmeasured (Finding 10). |
| Accessibility baseline | Automated ARIA-role/label presence check across every component's rendered output — a floor, not a substitute for manual accessibility review. |
| Theme resolution correctness | Integration test: render the same component under both Admin and Public theme configuration; assert `componentOverrides()` correctly changes rendered output where a theme-specific override is defined. |

## CI Verification Criteria
- Void-tag static scan, blocking.
- Accessibility baseline scan, blocking.
- Theme resolution test (above), blocking — new, verifies the Theme Variant Contract actually works.
- Bundle size measured and reported with the real pipeline once available.

## SemVer Impact
**Minor.** Establishes the visual language of the Sovereign Stack.
```

## 11. HUB-27.md

```md
# PHASE HUB-27: Cross-Origin (CORS) & Security Header Management

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and clarifies precedence against `HUB-01`'s tenant
config overrides, which this blueprint already assumed but didn't formally define the precedence
order for.

## Component Name
Sovereign Sentinel (Headers)

## Description
Centralized HTTP security-header and CORS-policy management: allowed origins/methods/headers, guarding
the Hub and Spokes against common web attacks.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway) and `HUB-01` (Config) — neither implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-01`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-04`, `CORE-05`.

## Architectural Design
- **HeaderManager** — injects security headers (CSP, HSTS, X-Frame-Options) on every response.
- **CorsEngine** — evaluates preflight `OPTIONS`, injects `Access-Control-*` headers.
- **PolicyRegistry** — per-tenant or per-service security policies.
- **CspGenerator** — dynamic CSP hashes for inline scripts, if any exist.

```php
namespace SovereignStack\Hub\Contracts;

interface SentinelInterface
{
    public function apply(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface;
}
```

## Config Precedence (clarified)
Global defaults come from `CORE-10`; tenant-level overrides come from `HUB-01`. Precedence: a
tenant-specific policy in `PolicyRegistry` **may only narrow** the global default (e.g., a stricter CSP
`default-src`), never broaden it (e.g., a tenant cannot add `unsafe-inline` if the global policy
forbids it). This mirrors `HUB-01`'s own "tenant overrides may only add/replace known keys" rule and
prevents a misconfigured tenant policy from weakening the platform's baseline security posture.

## Integration Strategy
- **Upward:** registered as global middleware in `HUB-08`.
- **Downward:** automatically covers all Spoke requests routed through the Gateway.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Preflight correctness | Integration test: send an `OPTIONS` preflight for an allowed origin/method combination; assert `204` with correct `Access-Control-*` headers, and a separate test for a disallowed origin asserting rejection. |
| CSP presence | Integration test asserting `Content-Security-Policy` header with `default-src 'self'` (or the configured baseline) on every response type (HTML, JSON API, error pages) — not just the happy-path route. |
| HSTS correctness | Integration test asserting `Strict-Transport-Security` includes `max-age` and `includeSubDomains` with the configured values, not just presence of the header. |
| Tenant-narrowing-only enforcement | Integration test: configure a tenant policy attempting to *broaden* the global CSP; assert `PolicyRegistry` rejects it at write time, per the Config Precedence rule above. |

## CI Verification Criteria
- Preflight allow/deny test pair, blocking.
- CSP-on-every-response-type test, blocking.
- Tenant-narrowing-only enforcement test, blocking — new, makes the precedence rule a checked
  property.

## SemVer Impact
**Minor.** Hardens the security posture of the entire stack.
```

## 12. HUB-28.md

```md
# PHASE HUB-28: Hub API Versioning Strategy

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Versioner

## Description
Formal versioning strategy/implementation for the Hub API: URL-based, header-based, and Accept-header
schemes, routing requests to the correct service version.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway) and `HUB-15` (Health Check) — neither implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-15`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-06`, `CORE-18`.

## Architectural Design
- **VersionResolver** — determines requested API version from the incoming request.
- **RouteVersioner** — decorates `CORE-06` for versioned route groups (`/v1/`, `/v2/`).
- **DeprecationManager** — injects `Deprecation`/`Link` headers for sunsetting versions.
- **CompatibilityShim** — maps old-version requests to new logic with transformation.

```php
namespace SovereignStack\Hub\Contracts;

interface VersioningInterface
{
    public function defaultVersion(): string;
    public function deprecate(string $version, \DateTimeInterface $sunsetDate): void;
}
```

## Integration Strategy
- **Upward:** integrated into the `CORE-06` routing pipeline used by `HUB-08`.
- **Downward:** Spoke applications define versioned controllers/routes.
- **Contract:** unversioned requests default to the latest stable version unless configured otherwise.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Routing precision | Integration test: request `/v1/identity`, assert it never reaches a `/v2/` controller, and vice versa — including a deliberate near-miss case (e.g., a `/v1/identity-extra` path) to catch prefix-matching bugs. |
| Accept-header resolution | Integration test with `Accept: application/vnd.sovereign.v1+json`; assert correct version resolution, and a separate test for a malformed/unknown version string asserting a clean 4xx rather than a crash. |
| Deprecation warning | Integration test: mark a version deprecated with a sunset date; assert the `Warning`/`Deprecation` header appears on every response for that version, including error responses. |

## CI Verification Criteria
- Routing-precision test with a near-miss case, blocking.
- Accept-header resolution test including the malformed-input case, blocking.
- Deprecation-header-on-every-response test (including error paths), blocking.

## SemVer Impact
**Minor.** Provides long-term stability and evolution paths for the API.
```

## 13. HUB-29.md

```md
# PHASE HUB-29: Hub-level Testing Harness

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and a concrete mock-fidelity verification mechanism
instead of an asserted-only "mocks behave identically" claim.

## Component Name
Sovereign Hub Spec (Testing)

## Description
Specialized testing harness extending `CORE-20` with integration/E2E tools for Hub services, including
mock drivers for every Hub component so Spokes can test in isolation.

## Build Status
🔴 **Blocked** on `HUB-15` (Health Check) and `HUB-16` (Orchestration Hooks) — neither implemented.
Note: as a testing tool, this can and should be built incrementally alongside each Hub component it
mocks, rather than waiting for the full tier — its `ServiceMocker` for `HUB-02`/`HUB-03` can exist as
soon as those two land, without waiting on `HUB-15`/`HUB-16`.

## Dependency Status
- **Direct Hub:** `HUB-15`, `HUB-16`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-20`, `CORE-08`.

## Architectural Design
- **ServiceMocker** — swaps real Hub services for fast in-memory mocks during tests.
- **AuthSimulator** — act as specific Users/Tenants without hitting real `HUB-04`/`HUB-21`.
- **ContractValidator** — ensures Hub service changes don't break defined Interface Contracts.
- **DuskBridge** — optional pure-PHP browser automation for E2E Spoke UI testing.

```php
namespace SovereignStack\Hub\Contracts;

interface HubTestHarnessInterface
{
    public function mockService(string $service, object $mock): void;
    public function actingAs(User $user, array $scopes = []): self;
}
```

## Mock Fidelity Contract (new)
"Mocks must behave identically to real services in terms of Interface compliance" is now a checked
property, not an assertion: `ContractValidator` runs the **same** test suite used to verify a real
service's interface compliance against its corresponding mock, on every CI run for this package. A
mock that passes a different (looser) test suite than its real counterpart is exactly the failure mode
this contract prevents.

## Integration Strategy
- **Upward:** complements `CORE-20`.
- **Downward:** every Spoke application uses this to write reliable integration tests against the Hub.
- **Contract:** mocks implement the same interfaces as real services.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Isolation | Integration test asserting the standard test suite runs with no database or Redis connection available in the test environment (enforced by literally removing network access to those services in the CI job, not just "not using" them). |
| Speed | State environment before citing "< 5 seconds for 100 tests" — measure on the actual reference CI runner (Finding 10). |
| Mock fidelity | The `ContractValidator` shared-suite mechanism above, run for every mocked service, blocking if any mock's behavior diverges from its real counterpart's test results. |

## CI Verification Criteria
- Network-isolated test-suite run, blocking.
- Mock-fidelity shared-suite check (above), blocking — this is the test that makes "mocks behave
  identically" enforced rather than hoped for.
- Speed measured and reported with environment stated.

## SemVer Impact
**Minor.** Crucial for the stability and maintainability of the entire stack.
```

## 14. HUB-30.md

```md
# PHASE HUB-30: Hub Developer CLI Toolchain

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10). Completes the Hub tier — `HUB-01` through `HUB-30`
are now all rewritten to the standard defined in `01_MASTER_INDEX.md`.

## Component Name
Sovereign Hub-CLI

## Description
Specialized CLI for Hub administrators/developers, extending `CORE-20` (Forge) with commands for
managing tenants, clearing global caches, inspecting queues, and monitoring service health across the
stack.

## Build Status
🔴 **Blocked** on `HUB-21` (Tenancy), `HUB-15` (Health Check), `HUB-10` (Queue), `HUB-02` (Cache) —
none implemented. As the tier's administrative interface, this is naturally last to build — it has no
value until the components it administers exist.

## Dependency Status
- **Direct Hub:** `HUB-21`, `HUB-15`, `HUB-10`, `HUB-02`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-20`.

## Architectural Design
- **TenantManagerCommand** — create/suspend/migrate tenants via `HUB-21`'s `TenancyInterface`.
- **PulseMonitorCommand** — real-time health dashboard from `HUB-15`.
- **QueueInspectorCommand** — view/retry/purge jobs via `HUB-10`.
- **AssetManagerCommand** — triggers Hub-level asset compilation/deployment (`HUB-03`).

```php
class CreateTenantCommand extends Command
{
    protected string $signature = 'hub:tenant:create {name} {domain}';

    public function handle(TenancyInterface $nexus): int
    {
        $tenant = $nexus->create([
            'name' => $this->argument('name'),
            'domain' => $this->argument('domain')
        ]);

        $this->info("Tenant created with ID: {$tenant->id}");
        return 0;
    }
}
```

## Interface Contracts
Inherits from `CORE-13` and `CORE-20`; no new interface surface of its own beyond individual
`Command` subclasses.

## Integration Strategy
- **Upward:** plugs into the `s-cli` entry point.
- **Downward:** used by DevOps/Hub administrators.
- **Contract:** every command supports `--json` output for scripting/automation.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Command discovery | Integration test: run `s-cli list hub`, assert output includes every registered Hub command by name — a count-based assertion (`>= 30`) is weaker than an explicit name-list assertion; use the latter so a renamed/dropped command is caught, not just a count drift. |
| Destructive-command safety | Integration test: invoke `hub:cache:clear` and `hub:tenant:delete` without `--force` in a non-interactive context; assert both refuse to proceed rather than silently completing. |
| Help documentation completeness | Static check: every registered command class has a non-empty description and at least one usage example in its help text — enforced at CI time, not left to reviewer diligence. |

## CI Verification Criteria
- Named command-discovery test (not count-only), blocking.
- Destructive-command safety test for every command tagged destructive, blocking.
- Help-documentation completeness static check, blocking.

## SemVer Impact
**Major.** Completes the Hub tier and provides the operational control plane.
```

## 15. ISPOKE-02.md

```md
# PHASE ISPOKE-02: Internal Developer Portal and Documentation Hub

## Tier
Internal Spoke (Staff-only Application)

## Resolves
This file's own cross-references were checked against `01_MASTER_INDEX.md` §3 and found clean — no
correction needed here. Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Forge Portal

## Description
A dedicated portal for internal developers/engineers: hosts documentation (including these
blueprints), API specifications, code standards, and onboarding guides; interactive tools for testing
Hub API versioning and exploring the GraphQL schema.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-14` (Search), `HUB-24` (GraphQL), `HUB-26` (UI), `HUB-28`
(API Versioning) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-11`, `HUB-14`, `HUB-24`, `HUB-26`, `HUB-28`, `HUB-15`, `HUB-16`. *(All verified
  against `01_MASTER_INDEX.md` §2/§4 — correct.)*
- **Transitive Core:** `CORE-14`, `CORE-13`, `CORE-20`, `CORE-03`.

## Architectural Design
- **DocEngine** — parses Markdown blueprints/specs into a searchable web interface.
- **ApiExplorer** — interactive UI for exploring the Hub's REST and GraphQL APIs.
- **StandardsGuide** — living document pulling PSR-12 and internal coding standards from the repo.
- **GraphiQL_PHP** — pure-PHP GraphQL explorer using `HUB-24`.

## Integration Strategy
- **Bootstrapping:** reads architectural documents via `HUB-11`.
- **UI Rendering:** `HUB-26` documentation layouts.
- **Orchestration:** reports doc-build health via `HUB-16`/`HUB-15`.
- **Search:** full-text search via `HUB-14`.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Markdown integrity | Automated link-check across every rendered blueprint page in CI; assert zero broken internal links. |
| Search latency | State environment before citing "< 100ms" — measure against a realistic fixture set (81+ real blueprint files, not a handful) once `HUB-14` exists (Finding 10). |
| API sync | Integration test: register a schema change in `HUB-24`, assert the GraphiQL explorer's introspection output reflects it without a manual rebuild step. |

## CI Verification Criteria
- Broken-link scan across the full blueprint corpus, blocking.
- API-sync test (above), blocking.
- Search latency measured against the realistic fixture set, reported with environment stated.

## SemVer Impact
**Minor.** Enhances developer productivity and architectural governance.
```

## 16. ISPOKE-03.md

```md
# PHASE ISPOKE-03: System Health and Observability Dashboard

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Cross-references checked against `01_MASTER_INDEX.md` §3 — clean, no correction needed. Adds stated
benchmark methodology (Finding 10).

## Component Name
Sovereign Pulse Dashboard

## Description
Real-time observability/health monitoring dashboard aggregating `HUB-15` (Pulse), `HUB-06` (Audit),
and `CORE-08` (Error Handler) into a unified view of stack performance and stability.

## Build Status
🔴 **Blocked** on `HUB-15`, `HUB-06`, `HUB-02`, `HUB-09` — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-15`, `HUB-06`, `HUB-02` (real-time metrics), `HUB-09` (Event Bus, live alerts),
  `HUB-26`, `HUB-16`. *(Verified — correct, including `CORE-09` correctly identified in the original
  as "Logger," matching the real PSR-3 Logging Service.)*
- **Transitive Core:** `CORE-08`, `CORE-09`, `CORE-19`, `CORE-10`.

## Architectural Design
- **PulseWall** — grid of health tiles per Hub/Spoke service.
- **ErrorStream** — real-time feed of exceptions/fatal errors.
- **MetricCharts** — memory/response-time visualization via `HUB-26`.
- **IncidentManager** — tracks/documents system-wide incidents.

## Integration Strategy
- **Bootstrapping:** subscribes to `HUB-09` for real-time health alerts.
- **UI Rendering:** `HUB-26` dashboard/data-viz components.
- **Data Source:** `HUB-15` registry and `CORE-09` log storage.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Real-time propagation | Integration test: flip a fixture service's `HUB-15` status; measure and report actual wall-clock time to dashboard update, on a stated environment — don't restate "within 2 seconds" unmeasured (Finding 10). |
| Alert accuracy | Integration test: log a `CORE-08` critical error, assert a corresponding visual alert renders — checked via DOM/state assertion, not just "an event fired." |
| Aggregation performance | Benchmark aggregating a realistic 24h/10-service fixture dataset; report actual time, state environment. |

## CI Verification Criteria
- Alert-accuracy test (above), blocking.
- Real-time propagation and aggregation performance measured and reported with environment stated.

## SemVer Impact
**Minor.** Essential for production operations and SRE.
```

## 17. ISPOKE-04.md

```md
# PHASE ISPOKE-04: Staff Identity and Onboarding Portal

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Cross-references checked against `01_MASTER_INDEX.md` §3 — clean. Adds stated benchmark methodology
(Finding 10).

## Component Name
Sovereign Staff Hub

## Description
Internal identity-management portal: staff onboarding, SSO configuration, MFA enforcement, access
request workflows — extends `HUB-04`/`HUB-05` with internal-only security requirements.

## Build Status
🔴 **Blocked** on `HUB-04`, `HUB-05`, `HUB-12` (Notify — correctly referenced), `HUB-20` (Vault) —
none implemented.

## Dependency Status
- **Direct Hub:** `HUB-04`, `HUB-05`, `HUB-12`, `HUB-20`, `HUB-26`, `HUB-15`, `HUB-16`. *(Verified —
  correct.)*
- **Transitive Core:** `CORE-16`, `CORE-19`, `CORE-04`, `CORE-03`.

## Architectural Design
- **OnboardingWizard** — step-by-step account/MFA setup UI.
- **AccessRequester** — workflow engine for temporary/permanent permission requests.
- **ProfileManager** — staff-specific profile settings.
- **SsoConfigurator** — SAML/OIDC connection management for internal identity providers.

## Integration Strategy
- **Bootstrapping:** specialized `HUB-04` consumer with stricter internal policies.
- **UI Rendering:** `HUB-26` form/wizard components.
- **Notifications:** `HUB-12` for onboarding invitations and security alerts.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| MFA enforcement | Integration test: attempt portal access with a fixture user lacking an active MFA challenge; assert denial at the middleware level, not just a UI-hidden control. |
| Audit completeness | Integration test: perform each identity-change operation (create, role change, deactivate); assert exactly one corresponding `HUB-06` entry per operation, no gaps. |
| Notification queuing | Integration test: create a staff account; assert the onboarding email job is queued via `HUB-10` (not `HUB-12` directly — `HUB-12` routes to `HUB-10` for delivery per `HUB-12.md`) within the same transaction as account creation. |

## CI Verification Criteria
- MFA-enforcement middleware test, blocking.
- 100% audit-completeness test, blocking.
- Notification-queuing-in-same-transaction test, blocking.

## SemVer Impact
**Minor.** Secures the internal human element of the Sovereign Stack.
```

## 18. ISPOKE-05.md

```md
# PHASE ISPOKE-05: Internal Reporting and Analytics Dashboard

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Corrects two of this delivery's cataloged mislabel patterns (`01_MASTER_INDEX.md` §3): the original
cited `HUB-28: Distributed Ledger & Analytics Engine` (Pattern B — no such Hub blueprint exists; real
`HUB-28` is API Versioning) and `CORE-09: Cryptography & Hashing` (Pattern A — real `CORE-09` is PSR-3
Logging; crypto is `CORE-16`, which this Spoke has no actual need of and the reference is dropped
rather than redirected).

## Component Name
Sovereign Insight

## Description
Centralized reporting engine and visualization dashboard: business intelligence, system performance
metrics, and operational reports for data-driven decisions.

## Build Status
🔴 **Blocked** on `HUB-31` (pending — see below), `HUB-24`, `HUB-26`, `HUB-08` — none implemented, and
`HUB-31` isn't even specified yet.

## Dependency Status — corrected
- **Direct Hub:** ~~`HUB-28: Distributed Ledger & Analytics Engine`~~ → **`HUB-31` (pending — Real-Time
  Analytics & Metrics Ledger, registered in `01_MASTER_INDEX.md` §4; not yet specified)**, `HUB-24`,
  `HUB-26`, `HUB-08`, `HUB-15`, `HUB-16`, `HUB-02`.
- **Transitive Core:** `CORE-19`, `CORE-18`, `CORE-11`, `CORE-12`, `CORE-06`, `CORE-02`. ~~`CORE-09:
  Cryptography & Hashing`~~ — removed; this Spoke performs no cryptographic operations of its own, the
  reference was simply wrong, not a mis-pointed real need.

**This blueprint cannot be considered build-ready until `HUB-31` is specified** — its core feature set
(`QueryBuilder` against real-time analytics, `WidgetEngine`'s live KPIs) has no backing service to
build against. Treat everything below as a design sketch pending that follow-up work.

## Architectural Design
- **QueryBuilder** — constructs analytics queries against `HUB-31` (pending).
- **WidgetEngine** — visualization components (Charts, Tables, KPIs) via `HUB-26`.
- **ReportScheduler** — periodic report generation/distribution via `HUB-10` (Queue — corrected from
  the original's unlabeled "HUB-10" reference in the report-scheduler diagram, which happened to be
  right by coincidence rather than by the stated dependency list, which omitted it).
- **ExportService** — high-volume exports via `CORE-14`.

```php
namespace SovereignStack\Internal\Insight\Contracts;

interface AnalyticsQueryInterface
{
    public function setTimeRange(\DateTimeInterface $start, \DateTimeInterface $end): self;
    public function groupBy(string $dimension): self;
    public function execute(): array;
}
```

## Integration Strategy
- **Bootstrapping:** via `CORE-18`; discovers analytics endpoints via `HUB-15`.
- **Data Access:** through `HUB-08` (Gateway) or direct `HUB-31` calls once it exists.
- **Lifecycle:** `HUB-16` hooks pause high-load reporting during maintenance.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Query performance | Cannot be meaningfully specified until `HUB-31` exists — state this explicitly rather than restating "< 200ms on 1M rows" against a backend that doesn't exist (Finding 10 applies doubly here). |
| UI namespace compliance | Static scan asserting 100% of chart components originate from `HUB-26` — this criterion doesn't depend on `HUB-31` and can be verified today once `HUB-26` exists. |
| Cross-tenant isolation | Integration test (once `HUB-31` exists): a tenant-scoped report must never contain another tenant's data — same severity class as `HUB-21`'s and `BRIDGE-01`'s isolation tests. |

## CI Verification Criteria
- UI namespace compliance test, blocking, buildable independent of `HUB-31`.
- Cross-tenant isolation test, blocking once `HUB-31` exists — do not ship this Spoke without it.
- Query performance: no target stated until `HUB-31` is specified and a real benchmark can be run.

## SemVer Impact
**Minor**, pending `HUB-31`'s existence — this blueprint's SemVer is meaningless until its core
dependency is real.
```
