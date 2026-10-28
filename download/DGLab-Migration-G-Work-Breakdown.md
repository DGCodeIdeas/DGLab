# DGLab Old-to-New Architecture Migration Map
## Deliverable G — Implementation Work Breakdown (ordered stages)

**Not implemented by this task.** Each stage = one reviewable PR-equivalent (atomic commit group). No transitional aliases (no BC requirement). Rollback = `git revert` of the stage commit group (unreleased repo).

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

- **Scope:** `git mv packages/core/<x>` → `Core/<X>/` per mapping (dbal→Database; middleware→Middleware with OD-01 namespace split); rewrite `namespace`/`use` in all src+tests; **same-commit caller updates** in hub/identity, bridge/vanguard, spokes (lms/showcase), app/, tests/, scripts (naming-drift map, allowlist FQCNs), root composer autoload (new `DGLab\Core\*` prefixes — packages still exist for Hub/Bridge/Spokes path repos until Stage 9? **No — see note**).
- **Sequencing note (important):** because the root composer currently wires packages via path repos, moving a package directory breaks its path repo. Two options: (a) do Core..Products moves with **per-stage root composer.json rewrites** (path repos + PSR-4 updated each stage — the recommended path, keeping the tree consistent at every stage boundary), or (b) one giant Stage 2-9 cutover. **Recommended: (a)** — each stage ends with a green composer validate + CI; the "single cutover" of Deliverable F applies to the *final dissolution* (removing path repos + per-pkg manifests) which still lands as its own atomic stage 9 after all directories have already moved.
- **Wave order within stage:** leaves (config, container, crypto, Database, filesystem, neospx(+add to composer), event-dispatcher, view) → logger, error-handler → router + **Http/Middleware split** → kernel (last).
- **Files:** 14 package trees (~287 files), callers (~30 files), root composer.json, `.github/architecture-export-allowlist.yaml`, `scripts/fitness/naming-drift.py`, packages-ci matrix paths, Loom contract test pin (`packages/core/logger` → `Core/Logger`).
- **Tests:** all 14 component suites + hub/bridge/spoke/app suites (import-renames) + boundary lint + fitness scripts (updated maps).
- **Acceptance:** `grep -r "SovereignStack\\\\Core" --include=*.php | wc -l` = 0; composer dump-autoload -o; CI green; no public-signature diffs except namespace lines (verified by `git diff -w` review discipline).
- **Risks:** Http/Middleware split caller misses (grep gate), vendor-symlink reliance disappearing mid-flight (each stage's composer update re-links).

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
