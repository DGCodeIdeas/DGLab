# DGLab Old-to-New Architecture Migration Map
## Deliverable I — Verification Checklist (implementation acceptance)

Every item must pass on a **clean clone of the migrated tree** before the restructure is considered complete. Items are grouped and each names the exact command/check and the stage that first satisfies it.

---

## I-1. Composer and autoload

- [ ] `composer validate --strict` — passes on root composer.json (no warnings).
- [ ] `composer dump-autoload -o` — succeeds; **then** autoload smoke script instantiates one class from each of the 21 PSR-4 prefixes (Config\Repository, Container\Container, Crypto\Encrypter, Database\Connection, ErrorHandler\ErrorHandler, EventDispatcher\EventDispatcher, Filesystem\Filesystem, Http\Response, Middleware\MiddlewarePipeline, Kernel\Kernel, Logger\Logger, NeoSpx\NeoSPXLexer, Router\Router, View\View, Hub\Config\HubConfigRegistry, Hub\Identity\IdentityApplicationService, Bridge\Vanguard\Vanguard, Spokes\Internal\Lms\…, Spokes\Internal\Codex\…, Spokes\Internal\Showcase\…, Spokes\External\Canvas\Canvas, Products\LMS\Bootstrap).
- [ ] Root `composer.lock` exists and is committed; `composer install --dry-run` reports no drift.
- [ ] **Zero per-component composer.json** outside protected dirs: `find . -name composer.json -not -path './vendor/*' -not -path './orchestrator/*' -not -path './archive/*'` returns exactly `./composer.json`.
- [ ] **Zero path repositories**: `grep -c '"type": *"path"' composer.json` = 0; `grep -c 'sovereign-stack/' composer.json` = 0 (require section empty of internal packages).
- [ ] Unique namespace ownership: no PSR-4 prefix maps to more than one path; no two prefixes overlap (`DGLab\Core\Http` ≠ `DGLab\Core\Middleware` roots verified by the smoke script loading `Http\Response` AND `Middleware\MiddlewarePipeline`).

## I-2. Namespace hygiene

- [ ] `grep -r "SovereignStack\\\\" --include="*.php" . | grep -v vendor | grep -v archive | wc -l` = **0** (protected orchestrator/src also migrated: its namespace `SovereignStack\Orchestrator\` → decided in D-H2 context; if kept, documented as the single sanctioned exception).
- [ ] `grep -rn "App\\\\" --include="*.php" --exclude-dir=vendor --exclude-dir=archive . | wc -l` = 0 (skeleton namespace retired).
- [ ] `grep -rni "packages/core\|packages/hub\|packages/bridge\|packages/spoke" --include="*.php" --include="*.yml" --include="*.yaml" --include="*.json" --include="*.xml" --include="*.sh" --include="Caddyfile*" . | grep -v vendor | grep -v archive | wc -l` = 0 except **documented grandfathered strings** (Loom legacy-tag regex for `core/container` v1.0.0 if retained).

## I-3. Tests

- [ ] Test count parity: total `*Test.php` discovered by all runners **≥ 178** (177 verified at baseline + the previously-orphaned `app/tests/Unit/ApplicationFactoryTest.php` now counted).
- [ ] `vendor/bin/phpunit --configuration phpunit.xml.dist` (root) — green; suites: Integration (Testsuite/Integration: PublicIndexSmokeTest, ViewIntegrationTest from `Products/LMS/public/index.php`).
- [ ] Per-component suites green via the re-pointed CI matrix: 21 component entries (14 Core + 2 Hub + 1 Bridge + 4 Spokes) + orchestrator.
- [ ] PSR-11 conformance suite (container) green.
- [ ] Loom contract test green with its package pin re-pointed (`Core/Logger`).
- [ ] Boundary-lint regression suite (13 tests) green with updated namespace map + allowlist (`DGLab\*` FQCNs).
- [ ] **No test file references a nonexistent path** (`git grep "app/tests\|tests/Integration" -- "*.php" "*.xml"` resolves only to new locations).

## I-4. Product entrypoints and document roots

- [ ] `Products/LMS/public/index.php` exists; `Products/LMS/Bootstrap.php` (or documented equivalent) reachable; routes `/`, `/health`, `/hello` return expected bodies (smoke test asserts).
- [ ] `anvil/dev/Caddyfile.dev` + `anvil/lib/frankenphp.sh` reference the new docroot; **`anvilctl start` (dev collapsed) boots**; `anvilctl verify health|headers` passes; deploy-smoke suite passes: `/health` 2xx < 5s, security headers present, `/debug/xff` reports `scheme=https` + propagated client IP.
- [ ] `anvil/lib/deploy.sh` release-shape check updated and passing against a packaged release dir containing `Products/LMS/public/index.php`.

## I-5. Web-exposure safety (brief §8 D6)

- [ ] The web docroot contains ONLY: `index.php`, static assets, `debug/xff.php` (gated). Verified: no `composer.json`, no `Config/`, no `vendor/`, no other Products' code under `Products/LMS/public/`.
- [ ] No path outside `Products/LMS/public/` is served: Caddyfile root + try_files inspected; `file_server` fallback cannot escape docroot.
- [ ] Product config/secrets: `Products/LMS/Config/` and `.env` live OUTSIDE the public dir (path check).

## I-6. Architecture governance and inventory

- [ ] `Architecture/component-inventory.yaml` validates via `Tools/ci/inventory-validate.php` in CI (job `inventory-validate` green).
- [ ] Inventory↔filesystem two-way consistency green (no component dir missing from inventory; no inventory path missing on disk).
- [ ] Inventory↔composer PSR-4 consistency green (every PHP component's namespace+path matches root autoload exactly).
- [ ] Dependency-direction rules green (tier rules; topological sort succeeds — no cycles).
- [ ] Frozen-contract refs resolve; every implemented CORE/HUB/BRIDGE/ISPOKE/ESPOKE component has contract refs or an explicit none-note.
- [ ] Generated artifacts fresh: INDEX tier tables / DAG summaries regenerated from inventory match committed files (or D-H9 defers this — then explicitly marked deferred).
- [ ] `Architecture/Verification/lint/run.php` green (its HUB-01..30/ESPOKE-01..18 structure expectations updated as part of D-H9, or documented).

## I-7. CI and workflows

- [ ] All workflows fire on push to main (Stage 0 fix verified: no `branches: ain]` remains).
- [ ] packages-ci matrix covers all component dirs + orchestrator; completeness checker passes with tier-layout globs.
- [ ] Workflow `paths:` filters reference existing paths only (`Tools/**`, `Testsuite/**`, `Products/**`, `Core/**`, `Hub/**`, `Bridge/**`, `Spokes/**`, `Database/**`, `Docs/**`).
- [ ] release.yml per-package jobs either re-pointed (D-H2 option b) or explicitly disabled with a tracking comment (D-H2 options a/c) — no silent no-op.
- [ ] secret-scan + pr-title-lint green (unchanged behavior).

## I-8. Reference hygiene

- [ ] `git grep -l "packages/" -- .github scripts Tools anvil orchestrator/src orchestrator/bin composer.json phpunit.xml.dist` returns only documented exceptions.
- [ ] Documentation links: README.md, AGENTS.md, CLAUDE-INDEX.md, DEPENDENCIES.md, Architecture blueprints referencing `packages/...` paths updated to tier paths (D-H9 scope or Stage 7 doc pass).
- [ ] `scripts/architecture-boundary-lint.php` phantom references removed (workflow + comments).
- [ ] `.phpunit.cache/` untracked; `.gitignore` updated (composer.lock line removed; cache ignored).

## I-9. Protected directories

- [ ] `git diff --stat origin/main..<migration> -- Architecture/ orchestrator/ anvil/` shows ONLY: `Architecture/component-inventory.yaml` (added, Stage 1) + documented reference updates (anvil Caddyfiles/frankenphp/deploy in Stage 6; orchestrator path-glob updates in Stage 9) — **no other file changes**; no file moved/renamed/deleted within them.
- [ ] `archive/` byte-identical (`git diff --stat -- archive/` empty).

## I-10. Behavioral parity (no BC, but no accidental drift)

- [ ] `git diff -w` review of every moved src file shows **only** `namespace`/`use` line changes (plus the two documented exceptions: view cwd-default removal if approved, health-endpoint routing if approved by D-H12).
- [ ] Public API surfaces byte-identical: interfaces/enums/constants unchanged (FROZEN-CONTRACTS spot-check on all 32 registered contracts).
- [ ] Pre-existing defects (D-H12 list of 7) still present but cataloged — the migration did not silently fix or worsen them (e.g., LMS UNIQUE conflict unchanged; progress persistence gap unchanged).
- [ ] Demo behavior: `/` → `Hello World`; `/hello?name=Ada` → Twig-rendered HTML; `/health` → JSON status (PublicIndexSmokeTest + ViewIntegrationTest green = sufficient evidence).

---

**Sign-off condition:** I-1 through I-10 fully checked, with any exception explicitly tied to a Deliverable H decision ID recorded in the migration's completion note. Passing this checklist = **restructure complete for owner review** — separate from production readiness (anvil prod deploy validation, target-MySQL evidence for the migration runner per PR #370's own acceptance tests, and NeoSPX runtime/conformance work remain outside this scope).

*Deliverable I complete. End of report series A–I.*
