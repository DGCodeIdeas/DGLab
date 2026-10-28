# DGLab Old-to-New Architecture Migration Map
## Deliverable A — Executive Migration Report

**Repository:** https://github.com/DGCodeIdeas/DGLab
**Baseline commit (authoritative):** `4ba7e7a4cfb47191a2288b5c7d5723b51c36db2c` (origin/main, 2026-10-28 18:53:00 +0100, "Add files via upload")
**Analysis type:** Read-only design analysis. No files were modified; no commits, branches, PRs, merges, or releases were created.

---

## 1. Branch/commit discrepancy record (mandatory disclosure)

| Item | Value | Note |
|---|---|---|
| GitHub `origin/main` at analysis time | `4ba7e7a4cfb4...` | Authoritative baseline for this report |
| Previously reported baseline (handoff doc + earlier sessions) | `ff439a4` | One commit behind; `4ba7e7a` adds only `A3-ARCH-DAG-REPORT.md` (459 lines) at the repo root |
| Open PR #370 (`migration-runner-impl`, based on `ff439a4`) | **NOT merged** | `packages/core/migration-runner` does **not** exist on `origin/main`. PR #370 is a separate workstream (per handoff §8); its proposed architectural ownership is mapped in Deliverable C but its code was not reviewed or amended. |
| Local sandbox working tree | diverged from origin/main | All evidence was taken from git objects via `git show origin/main:<path>`; the working tree was not used as evidence. |

---

## 2. Current architecture (verified)

The repository is a **PHP 8.4 monorepo using a polyrepo-style package layout under `packages/`**, with 20 path-repository Composer packages wired into one root `composer.json` (package `sovereign-stack/dglab`):

- **14 Core packages** at `packages/core/*` (config, container, crypto, dbal, error-handler, event-dispatcher, filesystem, http-message, kernel, logger, middleware, neospx, router, view) — namespaces `SovereignStack\Core\*` (with two hazards: http-message and middleware **share** `SovereignStack\Core\Http\`; dbal's namespace is `SovereignStack\Core\Database` ≠ directory name).
- **2 Hub packages** at `packages/hub/*` (config=HUB-01, identity=HUB-04) — `SovereignStack\Hub\*`.
- **1 Bridge package** at `packages/bridge/vanguard` (BRIDGE-01) — `SovereignStack\Bridge\` (flat).
- **4 Spoke packages**: `packages/spoke/internal/{codex,lms,showcase}` (`SovereignStack\Internal\Codex`, `SovereignStack\Spoke\Lms`, `SovereignStack\Spoke\Showcase`) and `packages/spoke/external/canvas` (`SovereignStack\External\Canvas`).
- **Application layer**: `app/` (one composition root `ApplicationFactory` + 3 demo controllers + 1 Twig template), `public/index.php` front controller, `public/debug/xff.php` dev fixture, empty `config/` and `cli/` scaffolding.
- **Protected areas**: `orchestrator/` (The Loom, CORE-01 release-automation CLI), `anvil/` (v3 deployment stack: Caddy edge → Tengine LB → FrankenPHP), `Architecture/` (194-file governance tree) — all remain physically in place.
- **CI**: 7 GitHub workflows (packages-ci matrix of 20 packages + integration job; release.yml driven by the Loom; 3 architecture lints; pr-title-lint; secret-scan).
- **Archive**: ~1,200 files in 6 sub-trees, dominated by `archive/Legacy/` (the Vision-A PWA monolith: download manager, auth/MFA stack, job queue, SuperPHP engine).
- **23 root-level .md documents** (~800KB, incl. 367KB `document.md` course guide and a 251KB tracked `worklog.md`).
- **No `composer.lock` is committed** (gitignored); no per-package lock files either.

**What actually runs today:** the deployable application is a generic walking skeleton — `public/index.php` → `App\ApplicationFactory` → Kernel (with Vanguard as outermost middleware) serving exactly three routes (`/`, `/health`, `/hello` Twig demo). **None of the domain spokes (LMS, Codex, Showcase, Canvas) nor the Hub identity/config packages are wired into the runtime**; they are libraries exercised only by their own PHPUnit suites.

---

## 3. Agreed target architecture (constraints recap)

Tiered directory monorepo (`Core/ Hub/ Bridge/ Spokes/Internal|External/ Products/LMS|Codex/ [App/] Cli/ Testsuite/ Database/ Docs/ Tools/ Public/`), one root composer.json+lock, **no per-component packages or path repositories**, canonical `DGLab\*` namespaces, Products as deployable apps, protected dirs (`Architecture/`, `orchestrator/`, `anvil/`) physically in place, Twig permanent + NeoSPX eventual-primary (never default before its runtime is tested), no BC constraints (pre-alpha).

---

## 4. Principal mismatches (top 10)

1. **Package-layout vs directory-tier layout**: 20 Composer packages under `packages/{tier}/{name}` with path repos must dissolve into tier directories with a single root autoload — the defining change of the migration.
2. **Namespace scheme**: all code is `SovereignStack\*`; target is `DGLab\*` with tier-exact prefixes (`DGLab\Spokes\Internal\Lms` vs current `SovereignStack\Spoke\Lms`; `DGLab\Spokes\External\Canvas` vs current `SovereignStack\External\Canvas`; Bridge loses its flat namespace).
3. **Dual PSR-4 roots for `SovereignStack\Core\Http\`** (http-message + middleware) — must be separated into `DGLab\Core\Http\` and `DGLab\Core\Middleware\` (OD-01 confirmed split), with all callers/tests updated.
4. **No Products exist**: the current app/ + public/ skeleton is generic, but it is the seed of the LMS product; it must move to `Products/LMS/` with product-owned entrypoint/bootstrap/routes/config/resources. `app/Bridge|Spokes` placeholder trees die.
5. **Test layout**: 177 test files live inside packages (co-located) + 2 root integration tests + 1 orphaned `app/tests` suite with **no CI runner**; target distinguishes `Testsuite/` (cross-component) from component-owned tests.
6. **Database assets scattered**: 9 SQL migrations across 3 packages (identity 3, LMS 5, showcase 1) with two naming conventions (unprefixed `users/roles/user_roles` vs prefixed `lms_*`, `showcase_products`); target `Database/` needs a consolidated layout + the migration-runner workstream (PR #370) as its tooling core.
7. **CI hardcodes package geometry**: packages-ci matrix (20 working-dirs), release.yml loops over `packages/${TIER}/*/`, the Loom's `dirname(__DIR__,2)` + `packages/*/*/composer.json` globs, completeness-check globs — all must be re-pointed at tier directories in lockstep. Additionally **all six push triggers are dead** (`branches: ain]` corruption) and the CI matrix is missing `core/neospx` + `core/view` (completeness guard should be red).
8. **Governance drift**: INDEX.md undercounts (102 vs 104 blueprints; HUB-31/HUB-32 missing from the §2.2 identity table; ESPOKE-19 uncounted; ADR-011 status contradiction; changelog ends 2026-09-24), the C18→C06 DAG edge is misclassified (S-057), and no machine-readable component inventory exists — the target `Architecture/component-inventory.yaml` (Deliverable D) becomes the new source of truth the migration must establish.
9. **Root-file sprawl**: 23 root .md files (research corpus, ~800KB) + placeholders (`docker-compose.yml`, `.env.example`, docs-serving `Dockerfile` with PHP 8.3 mismatch) + tracked `.phpunit.cache/` — need a `Docs/` re-home and cleanup.
10. **Incomplete components riding along**: NeoSPX (lexer-only, orphaned from root composer.json), kernel CORE-17 stub, Vanguard stubs (JWT/rate-limit/audit), LMS progress-persistence gap + re-enrollment/UNIQUE-constraint conflict, unreached `/health/{live,ready,dependencies}` routes — the migration must carry these states honestly without silently changing behavior.

---

## 5. Major risks (top 8, with mitigations)

| # | Risk | Mitigation |
|---|---|---|
| R1 | **Autoload breakage at cutover**: 20 packages → 1 root autoload; dual `Core\Http` roots; `Database`≠dbal namespace; callers in app/, tests/, scripts (boundary lint allowlist), anvil smoke tests. | Single atomic cutover stage (Stage 9) preceded by a generated, evidence-based mapping table (Deliverable C) + `composer dump-autoload -o` + full CI run; grep gates for stale `SovereignStack\` and `packages/` references. |
| R2 | **CI/release pipeline silent death**: dead push triggers, matrix drift, Loom path math, release.yml tier loops. | Fix `branches:` filters + matrix membership FIRST (Stage 0 pre-flight), re-point all path references in the same stage as the moves, keep `packages-ci.yml` name stable (Loom `--ci-workflow` default). |
| R3 | **Frozen-contract violations** (32 registered contracts + doctrine-era CORE-18 constants). | Namespace-only changes; signatures/enums/constants byte-identical; FROZEN-CONTRACTS.md re-verification step + architecture-lint in acceptance. |
| R4 | **Protected-dir accidental mutation** (`Architecture/`, `orchestrator/`, `anvil/`). | Explicit no-touch list in every stage; only *reference* updates (Dockerfile CMD, docs links) land as separate reviewed commits; verification checklist item I-9. |
| R5 | **Anvil deploy contract breakage**: dev Caddyfile `../public/index.php`, `lib/frankenphp.sh` root math, deploy-smoke URL probes, `lib/deploy.sh` release-shape check (`public/index.php` must exist at release root). | Products/LMS keeps `public/index.php` at the same relative depth from repo root during Stage 6; anvil references updated in the same stage; deploy-smoke re-run in dev. |
| R6 | **Test-coverage regression**: 177 test files move or change runners; `app/tests` currently has no runner. | Keep component tests co-located with their components; add `app/tests` (→ Products/LMS tests) to a runner in the same stage; count tests before/after (177) as an acceptance metric. |
| R7 | **Behavioral drift while "just moving"**: e.g., health-route reachability, LMS UNIQUE conflict, stub semantics. | Findings recorded as defects (Deliverable H) but NOT fixed by the migration; each stage's acceptance is "same behavior, new location" (byte-diff on public API surfaces). |
| R8 | **Loom release automation loses its package subjects**: release.yml + loom assume `packages/<tier>/<short>/composer.json` per package. | Owner decision D-H2 (per-package versioning vs single monorepo version); until decided, Stage 9 disables per-package release jobs rather than silently mis-bumping. |

---

## 6. Recommended migration order (summary of Deliverable G)

Stage 0 pre-flight (CI fixes + inventory skeleton) → 1 governance inventory → 2 Core (14 comps, incl. Http/Middleware split) → 3 Hub (2) → 4 Bridge (1) → 5 Spokes (4 + migrations → Database/) → 6 Products/LMS composition (app/+public/ move, health routes) → 7 CLI/Database/Tools/Docs → 8 Testsuite + test runners → 9 **single-cutover root-Composer/autoload/CI re-point** → 10 legacy cleanup (path repos removed, scaffolding deleted, archives classified). Rationale: dependency-bottom-up (Core → Hub → Bridge → Spokes → Products) matches the verified DAG; the Composer/package-boundary dissolution is deferred to one atomic Stage 9 because half-migrated autoload is the highest-risk intermediate state. No transitional aliases are introduced (no BC requirement).

---

## 7. Decisions still required from the owner (summary of Deliverable H — 12 items)

1. **D-H1**: Does the generic walking skeleton (app/+public/) become the `Products/LMS` seed (recommended) or is a fresh LMS composition written while the skeleton moves to a neutral location?
2. **D-H2**: Release model after package dissolution — keep per-package version tags via Loom against tier dirs, or single monorepo version (ADR-018/019 currently assume per-package)?
3. **D-H3**: Testsuite layout — component tests stay co-located (recommended) vs all tests centralized under `Testsuite/`?
4. **D-H4**: Database layout — `Database/Migrations/<owner>/` (recommended) vs per-component migration retention; and whether the PR #370 migration runner lands as `Database/Runner` tooling.
5. **D-H5**: NeoSPX partial component — carry lexer forward at `Core/NeoSPX` as-is (recommended) vs defer removal until parser/compiler work resumes?
6. **D-H6**: Codex/Showcase/Canvas — confirm they remain capability spokes only (no Product apps yet; `Products/Codex` deferred).
7. **D-H7**: Root-doc corpus re-homing to `Docs/` — depth of reorganization (minimal move vs curated split).
8. **D-H8**: `Public/` legacy root — delete after Products/LMS owns its public dir (recommended) vs keep a redirect?
9. **D-H9**: Governance repair scope — does the migration task also fix INDEX/DAG drift (S-055..S-065) or only establish component-inventory.yaml?
10. **D-H10**: `App/` tier — confirmed NOT needed now (no shared app-level infra beyond the skeleton).
11. **D-H11**: `.phpunit.cache`, `document.md`, `worklog.md` at root — untrack/re-home policy.
12. **D-H12**: LMS re-enrollment vs UNIQUE(course_id, learner_id) + missing progress persistence — acknowledge as pre-existing defects to fix post-migration (separate workstream), not migration blockers.

---

## 8. Inventory totals (from Deliverables B/C)

- **Meaningful components inventoried: 41** (14 Core + 2 Hub + 1 Bridge + 4 Spokes + 1 Loom + 1 app-skeleton + 1 public front controller + 1 xff fixture + 3 test suites at root/app + 12 script tools + 7 CI workflows + 6 archive sub-trees + scaffolding set + root-doc corpus counted as groups; see Deliverable B for the full 41-row inventory with the long tail of individual scripts/workflows).
- **Proposed moves (directory+namespace): 22** (14 Core, 2 Hub, 1 Bridge, 4 Spokes, 1 app→Products/LMS group) — all namespace renames are mechanical `SovereignStack\X` → `DGLab\Y` per the mapping table.
- **Splits: 2** (Core\Http dual-root split into Http+Middleware — OD-01; app-skeleton split into Products/LMS vs Testsuite/App-tests).
- **Merges: 1** (9 SQL migrations from 3 packages consolidate under Database/ ownership layout).
- **Removals (recommended, pending owner): 11** (app/{Bridge,Spokes,Resources} placeholder trees; empty `config/`, `cli/`; root docker-compose.yml + .env.example placeholders; obsolete baseline generators v1; tracked `.phpunit.cache/`) — plus optional root-doc re-homes.
- **Retained in place: 3 protected dirs + archive + external tooling** (Architecture/, orchestrator/, anvil/, archive/, .github (path-updated), scripts/ (path-updated)).
- **Unresolved classifications: 5** (migration-runner home pending D-H4; LMS-skeleton policy D-H1; NeoSPX carry-forward D-H5; Docs re-home depth D-H7; Public/ root retirement D-H8) — plus 7 governance-drift items requiring owner scoping (D-H9).

**Confidence:** 36 components Verified (direct source evidence); 3 Partially verified (anvil web-UI latent bug, neospx test bootstrap portability, release.yml historical no-op modes); 2 Unresolved (explicitly flagged above).

---

## 9. Principal architectural risks (one-line restatement)

The migration's danger is not any single move but the **lockstep surface**: autoload, CI matrix, Loom path math, anvil document-root contracts, boundary-lint namespace allowlists, and integration-test boot paths must all flip in one consistent stage; governance drift (INDEX/DAG undercounts) means documentation will contradict the new tree unless `component-inventory.yaml` is established as the single identity source first.

---

*Deliverable A complete. Companion files: B (inventory), C (migration map), D (inventory schema), E (dependency analysis), F (Composer plan), G (work breakdown), H (decision register), I (verification checklist).*
