# DGLab Old-to-New Architecture Migration Map
## Deliverable G — Implementation Work Breakdown (ordered stages)

**Not implemented by this task.** Each stage = one reviewable PR-equivalent (atomic commit group). No transitional aliases (no BC requirement). Rollback = `git revert` of the stage commit group (unreleased repo).

---

## G-0. Cross-cutting contracts (apply to every stage)

### G-0.1 Per-stage Composer state (Issue 4)

Every stage that moves a component MUST satisfy the **Per-stage Composer state contract** defined in Deliverable F-7. In summary:

- Path repositories remain in root `composer.json` until Stage 9 (no premature removal).
- Each stage appends new `DGLab\<Tier>\<Component>\` PSR-4 prefixes to root `autoload.psr-4` AND removes the corresponding `SovereignStack\<...>\` prefixes AND deletes the per-package `composer.json` for moved components — all in the same commit.
- `sovereign-stack/*` requires shrink stage-by-stage as their packages move.
- **Mandatory validation at every stage boundary:**
  ```bash
  composer validate --strict
  composer dump-autoload -o
  composer install --dry-run
  # plus autoload smoke per new prefix:
  php -r 'require "vendor/autoload.php"; class_exists("DGLab\<Tier>\<Component>\<Class>") or exit(1);'
  ```
- **Acceptance:** `composer install` succeeds; previously-passing tests still pass; new component tests pass from new paths; no half-migrated PSR-4 root; CI green.
- **No stage ends in a broken-Composer state.** If a stage cannot satisfy this contract, it is split into substages that each leave the tree consistent.

See F-7 for the full per-stage Composer state table (Stages 2–9).

### G-0.2 Protected-directory scope (Issue 5)

The protected directories `Architecture/`, `orchestrator/`, `anvil/`, and `archive/` remain physically in place during the entire restructure. The migration touches them ONLY for **reference updates**, never for internal code modifications:

- `Architecture/` — adds `component-inventory.yaml` (Stage 1); updates INDEX/DAG derived artifacts per D-H9 scope decision (deferred unless owner folds into Stage 1).
- `orchestrator/` — updates path globs (`packages/*/*/composer.json` → tier-layout globs) in Stage 9; its internal namespace `SovereignStack\Orchestrator\*` is **grandfathered** as a sanctioned exception (see I-9) and is NOT rewritten by the migration. Decision D-H2 (post-migration workstream) decides whether/how the orchestrator's namespace eventually moves.
- `anvil/` — updates Caddyfile/frankenphp.sh/deploy.sh docroot references in Stage 6; anvil contains no PHP namespace declarations (shell scripts + Caddyfile + systemd units only); nothing internal to rewrite.
- `archive/` — byte-identical (`git diff --stat -- archive/` empty); zero migration touches.

**Distinguishing reference updates from internal modifications (Issue 5 reconciliation):** the migration's zero-`SovereignStack\` grep gate (I-2) applies to non-protected code ONLY. The protected dirs' internal `SovereignStack\` references are listed in the inventory's `grandfathered_exceptions` field (Deliverable D-5 rule 10). The migration does NOT introduce NEW `SovereignStack\` references inside protected dirs (only existing references are grandfathered). This reconciles I-2 (zero `SovereignStack\`) with I-9 (protected dirs physically retained) — they are not in conflict because the grep rule's scope is non-protected code only.

### G-0.3 Products/Codex boundary (Issue 1)

`Products/Codex/` is a **reserved proposed Product boundary** (blueprint-only; no application code today). No stage creates, moves, or modifies Codex application code. The migration does NOT invent Codex application code to fill the directory (per owner directive). When a Codex application is built (post-migration workstream), it will compose `Spokes/Internal/Codex/` and live at `Products/Codex/`. See C-6b and D-H6 (updated).

### G-0.4 Counting conventions (Issue 2)

All stage-level action counts use the conventions defined in A §8:
- *Move+Rename* = 1:1 source→target mapping (paired `git mv` + namespace rewrite).
- *Split* = 1→many mapping (counted ONCE under splits, never under moves).
- *Merge* = many→1 mapping.
- *Removal* = file/dir deletion.

Totals (reconciled across A, B, C): 42 components / 21 moves / 2 splits / 1 merge / 11 removals.

---

### Stage 0 — CI pre-flight repairs (independent of the moves)

- **Scope:** fix `branches: ain]` → `branches: [main]` in all 6 workflows; add `packages/core/neospx` + `packages/core/view` to packages-ci matrix (or exclusion entries with reasons); delete dangling `scripts/architecture-boundary-lint.php` references in architecture-boundary-lint.yml; untrack `.phpunit.cache/`.
- **Prerequisites:** none.
- **Files:** `.github/workflows/*.yml` (6), `scripts/ci-matrix-exclusions.txt`, `.gitignore`.
- **Tests:** workflows must run green on push to main (they currently never fire on push).
- **Acceptance:** push-triggered CI executes; matrix completeness check passes with 22 packages.
- **Risk/why first:** the migration's safety net is CI; it is currently half-broken. Fixing it before any move means every later stage is actually gated.

### Stage 1 — Governance inventory skeleton

- **Scope:** create `Architecture/component-inventory.yaml` from Deliverable B evidence (schema per Deliverable D) + `Tools/ci/inventory-validate.php` + CI job `inventory-validate`. Record corrected counts (32 Hub files / 19 ESPOKE / 104 blueprints) as inventory facts.
- **Prerequisites:** Stage 0.
- **Files:** `Architecture/component-inventory.yaml` (new), `Tools/` (or scripts/) validator, one workflow.
- **Acceptance:** validator green on current tree (with `migration_from` fields populated; filesystem-consistency rule run in pre-migration mode).
- **Note:** INDEX.md/DAG text repairs = owner decision D-H9 (scope); the inventory is authoritative going forward regardless.

### Stage 2 — Core tier move (14 components)

- **Scope:** `git mv packages/core/<x>` → `Core/<X>/` per mapping (dbal→Database; middleware→Middleware with OD-01 namespace split); rewrite `namespace`/`use` in all src+tests; **same-commit caller updates** in hub/identity, bridge/vanguard, spokes (lms/showcase), app/, tests/, scripts (naming-drift map, allowlist FQCNs), root composer autoload (new `DGLab\Core\*` prefixes added; `SovereignStack\Core\*` prefixes removed; per-package manifests for the 14 Core components DELETED; `sovereign-stack/core-*` requires REMOVED).
- **Composer state at stage exit (per F-7):** 14 new `DGLab\Core\*` PSR-4 prefixes in root autoload; 14 per-pkg manifests deleted; 14 `core-*` requires removed; path repos still present (Hub/Bridge/Spokes not yet moved); validation: `composer validate --strict` + `composer dump-autoload -o` + autoload smoke over 14 new prefixes; CI green.
- **Sequencing note (important):** because the root composer currently wires packages via path repos, moving a package directory breaks its path repo. The recommended path is **per-stage root composer.json rewrites** (path repos + PSR-4 updated each stage — keeping the tree consistent at every stage boundary per F-7). The "single cutover" of Deliverable F-5/F-7 applies ONLY to Stage 9's final dissolution (removing path repos entirely + committing `composer.lock`), NOT to the per-stage moves.
- **Wave order within stage:** leaves (config, container, crypto, Database, filesystem, neospx(+add to composer), event-dispatcher, view) → logger, error-handler → router + **Http/Middleware split** → kernel (last).
- **Files:** 14 package trees (~287 files), callers (~30 files), root composer.json, `.github/architecture-export-allowlist.yaml`, `scripts/fitness/naming-drift.py`, packages-ci matrix paths, Loom contract test pin (`packages/core/logger` → `Core/Logger`).
- **Tests:** all 14 component suites + hub/bridge/spoke/app suites (import-renames) + boundary lint + fitness scripts (updated maps).
- **Acceptance:** `grep -r "SovereignStack\\\\Core" --include=*.php --exclude-dir=vendor --exclude-dir=archive --exclude-dir=orchestrator --exclude-dir=anvil | wc -l` = 0 (protected-dir grandfathered exceptions per I-9); `composer validate --strict` + `composer dump-autoload -o` + autoload smoke (14 prefixes) + CI green; no public-signature diffs except namespace lines (verified by `git diff -w` review discipline).
- **Risks:** Http/Middleware split caller misses (grep gate); vendor-symlink reliance disappearing mid-flight (each stage's composer update re-links).

### Stage 3 — Hub tier (2 components)

- Same pattern: `packages/hub/{config,identity}` → `Hub/{Config,Identity}`; callers = lms (11 files), showcase (3), app? (none), allowlist (6 FQCNs), naming-drift. Identity's 3 SQL migrations move to `Database/Migrations/Identity/` **in this stage** (D-H4 layout) — file moves only, no SQL edits.
- **Acceptance:** identity+lms+showcase suites green; migrations loadable from new path (migration-runner workstream note: PR #370 rebases later).

### Stage 4 — Bridge (1 component)

- `packages/bridge/vanguard` → `Bridge/Vanguard/`; namespace `SovereignStack\Bridge\` → `DGLab\Bridge\Vanguard\`; callers = app/ApplicationFactory (4 imports), PublicIndexSmokeTest, FROZEN-CONTRACTS refs, Vanguard tests.
- **Acceptance:** Vanguard suite + PublicIndexSmokeTest green (skeleton still at old app/ path — it imports the NEW namespace; allowed since app/ is not yet moved).

### Stage 5 — Spokes (4 components + migrations)

- `packages/spoke/internal/{lms,codex,showcase}` → `Spokes/Internal/{Lms,Codex,Showcase}`; `packages/spoke/external/canvas` → `Spokes/External/Canvas`; namespace mapping incl. `SovereignStack\Internal\Codex` → `DGLab\Spokes\Internal\Codex` and `SovereignStack\External\Canvas` → `DGLab\Spokes\External\Canvas` (tier-prefix corrections); LMS's 5 + Showcase's 1 migrations → `Database/Migrations/{Lms,Showcase}/`; allowlist (21 FQCNs), naming-drift, packages-ci matrix paths.
- **Acceptance:** 4 suites green; grep gates; autoload smoke over 4 namespaces.

### Stage 6 — Products/LMS composition (app/ + public/ move)

- **Scope (D-H1 recommended variant):** `app/ApplicationFactory.php` → `Products/LMS/Bootstrap.php` (class `DGLab\Products\LMS\Bootstrap`; keep create()/run()/getKernel() API); controllers → `Products/LMS/src/Controller/` (or Products/LMS/Controller per target tree — pick one, document); `public/index.php` → `Products/LMS/public/index.php`; `app/Resources/views/hello.twig` → `Products/LMS/resources/views/hello.twig`; inline view config → `Products/LMS/Config/view.php` loaded via ConfigRepository (behavior-identical values); route table extracted to `Products/LMS/Routes/web.php` (same 3 routes; **plus D-4 decision: route or drop `/health/{live,ready,dependencies}`**); `app/tests/Unit` → `Products/LMS/tests/Unit` + added to a runner.
- **SAME-COMMIT anvil updates:** `anvil/dev/Caddyfile.dev` (`../public/index.php` → `../Products/LMS/public/index.php` + root), `anvil/lib/frankenphp.sh` dev app-root math, `anvil/lib/deploy.sh` release-shape check (`<release>/Products/LMS/public/index.php` — **release packaging layout change; flag for deploy runbook**), smoke-probe paths unchanged relative to docroot.
- **SAME-COMMIT test updates:** `tests/Integration/PublicIndexSmokeTest` + `ViewIntegrationTest` require-paths → new front controller (they then move to Testsuite/ in Stage 8).
- **Acceptance:** integration tests green from new path; `anvilctl start` (dev, collapsed) boots and deploy-smoke passes `/health` + headers + XFF; `/hello` Twig renders; grep `App\\` = 0.
- **Risks:** anvil geometry (mitigated: same-stage updates + dev boot verification); FrankenPHP worker `MAX_REQUESTS` loop preserved byte-equivalent.

### Stage 7 — CLI / Database / Tools / Docs

- **Scope:** delete empty `config/`, `cli/` scaffolding + root `docker-compose.yml`/`.env.example` placeholders (D-H7 scope); move `scripts/` → `Tools/{lint,fitness,ci,generators}` with **same-commit workflow path updates** (3 workflows' `paths:` filters + run steps); move root-doc corpus → `Docs/{Research,Reports,Operations}` (keep README.md, AGENTS.md, CLAUDE.md, CLAUDE-INDEX.md, SECURITY.md, CONTRIBUTING.md, LICENSE, CHANGELOG.md at root); `document.md` → `Docs/Research/sources/`; `worklog.md` policy D-H11; root `Dockerfile` re-point (docs server keeps serving `Architecture/`; only comment updates) or retire (owner call); `Public/` root: delete (D-H8) since Products/LMS owns its public dir now.
- **Acceptance:** workflow path filters match new tool locations; doc links updated (grep old paths); CI green.

### Stage 8 — Testsuite consolidation

- **Scope:** `tests/Integration/*` → `Testsuite/Integration/*` (namespace `Tests\Integration` → `DGLab\Testsuite\Integration`); root phpunit.xml.dist testsuites: Unit (component dirs), Integration (Testsuite/); add `Products/LMS/tests` to a suite (fixes the orphaned-runner defect); orchestrator tests untouched.
- **Acceptance:** test count before/after = 177 + 1 orphaned suite now counted; all suites green.

### Stage 9 — Root Composer dissolution + full CI/Loom re-point (THE cutover)

- **Scope:** delete 20 per-pkg `composer.json`/`ci/run.php`/`phpstan.neon` (+ READMEs absorbed into Docs or kept per-component); root composer.json final form (Deliverable F-1); **remove path repositories + all `sovereign-stack/*` requires**; commit root `composer.lock` (remove gitignore line); phpstan root config covers all component `src/`; packages-ci matrix re-pointed at component dirs (22→21 entries after dissolution; keep workflow NAME `packages-ci.yml` — Loom's `--ci-workflow` default); release.yml per-package jobs **disabled pending D-H2** (explicit comment, not silent); Loom `MonorepoPackage::discover()` taught the tier layout (or pinned to inventory) — its contract test re-pinned to `Core/Logger`; boundary-lint + fitness scripts read tier layout + inventory; `.github/architecture-export-allowlist.yaml` FQCNs final (`DGLab\*`).
- **Acceptance (the big gate):** `composer validate --strict`; `composer dump-autoload -o`; autoload smoke (21 namespaces); grep gates: `SovereignStack\\` = 0, `packages/` in CI/scripts/composer = 0 (documented exceptions only), `sovereign-stack/` requires = 0; FULL CI green (all suites + 3 arch lints + inventory validation); PublicIndexSmokeTest green; anvil dev boot green.
- **Rollback:** revert the cutover commit.

### Stage 10 — Legacy cleanup & closure

- **Scope:** remove obsolete generators (S-026) + one-shot doc-append helpers; delete `app/` husk (if anything remains); archive classification labels recorded in inventory (`archive/*` entries `status: archived` + extraction-candidate notes from C-9); `orchestrator/repos.json` regen policy; final `component-inventory.yaml` pass drops `migration_from` fields; regenerate INDEX/DAG derived artifacts from inventory (D-H9 scope).
- **Acceptance:** verification checklist (Deliverable I) fully green; inventory `migration_action` fields all removed/`none`.

---

## Completion criteria (overall)

1. Deliverable I checklist passes end-to-end on a clean clone.
2. 177+ tests green (counted before/after).
3. Grep gates zero.
4. Anvil dev deploy-smoke green from the new layout.
5. Inventory validates; generated artifacts fresh.
6. No protected-dir mutations (only their references).
7. Decision register (Deliverable H) items either resolved by owner or explicitly deferred with recorded consequences.

*Deliverable G complete.*
