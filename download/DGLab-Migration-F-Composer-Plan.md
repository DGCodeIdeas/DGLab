# DGLab Old-to-New Architecture Migration Map
## Deliverable F — Root Composer Migration Plan

**Baseline:** `origin/main @ 4ba7e7a` · current root `composer.json` = `sovereign-stack/dglab`, requires 20 `sovereign-stack/*` path-repo packages + 8 PSR/ext requires, 19+7 PSR-4 mappings, **no committed lock** (gitignored).

---

## F-1. Before → After

### BEFORE (current, verified)

```jsonc
{
  "name": "sovereign-stack/dglab",
  "require": {
    "php": "^8.4", "ext-mbstring": "*", "ext-pcre": "*",
    "psr/container": "^2.0", "psr/event-dispatcher": "^1.0",
    "psr/http-factory": "^1.0", "psr/http-message": "^2.0",
    "psr/http-server-handler": "^1.0", "psr/http-server-middleware": "^1.0",
    "psr/log": "^3.0",
    "sovereign-stack/core-config": "*", "... 19 more sovereign-stack/* path packages ..."
  },
  "require-dev": { "phpstan/*": "...", "phpunit/phpunit": "^11.0" },
  "autoload": { "psr-4": {
      "App\\": "app/",
      "SovereignStack\\Core\\Container\\": "packages/core/container/src/",
      "SovereignStack\\Core\\Http\\": "packages/core/http-message/src/",   // ⚠ single owner of a SHARED NS
      "SovereignStack\\Core\\Database\\": "packages/core/dbal/src/",
      "... 16 more SovereignStack mappings ..."
  }},
  "autoload-dev": { "psr-4": { "Tests\\": "tests/", "App\\Tests\\": "app/tests/", "... 4 pkg test NS ..." }},
  "repositories": [ {"type":"path","url":"packages/*/*"}, {"type":"path","url":"packages/*/*/*"} ],
  "minimum-stability": "dev", "prefer-stable": true
}
```

Problems: dual-root `Core\Http` (middleware resolves only via path-repo vendor dir); `neospx` unmapped (orphan); `crypto` mapped? — yes via vendor path-repo only; **20 simulated package boundaries**; no lock committed; `App\Tests` mapped to a directory with no CI runner.

### AFTER (target)

```jsonc
{
  "name": "dglab/dglab",                       // or keep sovereign-stack/* — owner naming call (D-H2-adjacent)
  "require": {
    "php": "^8.4",
    "ext-mbstring": "*", "ext-pcre": "*",
    "ext-pdo": "*", "ext-openssl": "*", "ext-sodium": "*", "ext-hash": "*",
    "ext-fileinfo": "*", "ext-json": "*",
    "psr/container": "^2.0", "psr/event-dispatcher": "^1.0",
    "psr/http-factory": "^1.0", "psr/http-message": "^2.0",
    "psr/http-server-handler": "^1.0", "psr/http-server-middleware": "^1.0",
    "psr/log": "^3.0",
    "twig/twig": "^3.22"
    // ZERO sovereign-stack/* requires; ZERO path repositories
  },
  "require-dev": { "phpstan/extension-installer": "^1.4", "phpstan/phpstan": "^2.2",
                   "phpstan/phpstan-strict-rules": "^2.0", "phpunit/phpunit": "^11.0" },
  "autoload": { "psr-4": {
      "DGLab\\Core\\Config\\":        "Core/Config/src/",
      "DGLab\\Core\\Container\\":     "Core/Container/src/",
      "DGLab\\Core\\Crypto\\":        "Core/Crypto/src/",
      "DGLab\\Core\\Database\\":      "Core/Database/src/",
      "DGLab\\Core\\ErrorHandler\\":  "Core/ErrorHandler/src/",
      "DGLab\\Core\\EventDispatcher\\":"Core/EventDispatcher/src/",
      "DGLab\\Core\\Filesystem\\":    "Core/Filesystem/src/",
      "DGLab\\Core\\Http\\":          "Core/Http/src/",
      "DGLab\\Core\\Middleware\\":    "Core/Middleware/src/",   // OD-01 split — unique owner per NS
      "DGLab\\Core\\Kernel\\":        "Core/Kernel/src/",
      "DGLab\\Core\\Logger\\":        "Core/Logger/src/",
      "DGLab\\Core\\NeoSpx\\":        "Core/NeoSpx/src/",       // was orphaned — now first-class
      "DGLab\\Core\\Router\\":        "Core/Router/src/",
      "DGLab\\Core\\View\\":          "Core/View/src/",
      "DGLab\\Hub\\Config\\":         "Hub/Config/src/",
      "DGLab\\Hub\\Identity\\":       "Hub/Identity/src/",
      "DGLab\\Bridge\\Vanguard\\":    "Bridge/Vanguard/src/",
      "DGLab\\Spokes\\Internal\\Lms\\":      "Spokes/Internal/Lms/src/",
      "DGLab\\Spokes\\Internal\\Codex\\":    "Spokes/Internal/Codex/src/",
      "DGLab\\Spokes\\Internal\\Showcase\\": "Spokes/Internal/Showcase/src/",
      "DGLab\\Spokes\\External\\Canvas\\":   "Spokes/External/Canvas/src/",
      "DGLab\\Products\\LMS\\":       "Products/LMS/src/"       // Bootstrap/controllers live under src/ or PSR-4 root at Products/LMS/ — see note
  }},
  "autoload-dev": { "psr-4": {
      "Testsuite\\":  "Testsuite/",
      "DGLab\\Tests\\Core\\":          "Core/*/tests/" /* only if centralized variant chosen (D-H3) */,
      "DGLab\\Products\\LMS\\Tests\\": "Products/LMS/tests/",
      "DGLab\\Hub\\*\\Tests\\": ..., "DGLab\\Spokes\\*\\*\\Tests\\": ..., "DGLab\\Bridge\\Vanguard\\Tests\\": ...
      // per-component test namespaces (co-located variant, recommended)
  }},
  // repositories: REMOVED entirely
  // minimum-stability: can move to "stable" once twig is the only 3rd-party dep (owner call)
  "config": { "sort-packages": true, "allow-plugins": { "phpstan/extension-installer": true } },
  "scripts": { /* see F-4 */ }
}
```

**PSR-4 target-vs-directory check (mandatory per brief §9):** every mapping above was cross-checked against the verified namespace of each package's source (Deliverable B). Two deliberate conventions: (a) dbal keeps namespace `Database` → directory renamed to `Core/Database` for path=NS consistency; (b) `DGLab\Bridge\Vanguard` gains the `Vanguard` segment (flat `DGLab\Bridge\` would collide with any future bridge component). `App\` mapping is deleted (namespace dies with the skeleton move).

## F-2. Package-boundary dissolution inventory

| Mechanism | Current count | Disposition |
|---|---|---|
| Path repositories | 2 URL patterns (`packages/*/*`, `packages/*/*/*`) | **Remove** (Stage 9) |
| `sovereign-stack/*` requires | 20 | **Remove all** — replaced by root autoload |
| Per-package `composer.json` | 20 packages + orchestrator (protected) | Delete 20 at Stage 9; orchestrator keeps its own (self-contained tool, vendored independently — recorded exception pending D-H2) |
| Per-package `replace`/`provide`/`conflict` | none found | n/a |
| Classmaps / autoload files | none (pure PSR-4 everywhere incl. neospx tests bootstrap self-loader) | n/a |
| Package-specific composer scripts | per-pkg `ci/run.php` conventions (10 pkgs) | absorbed into root scripts + CI jobs (F-4) |
| Per-package versions (v0.x.y.z) | in each pkg composer.json | **Die at Stage 9** — version authority moves to git tags + D-H2 decision (monorepo single version recommended) |

## F-3. Third-party dependency handling

- **Kept at root:** 8 PSR interfaces + `twig/twig` (the only non-PSR runtime dep in the whole tree — verified: no other Composer libraries are imported by any package src; guzzle & friends live ONLY in orchestrator's own composer.json).
- **Extension requirements consolidated** (currently scattered across per-pkg composer.json): ext-pdo (dbal), ext-openssl+sodium+hash (crypto), ext-fileinfo (http-message, filesystem), ext-json (hub/spokes). Each was verified against actual `use`/function calls in src.
- **Dev deps unchanged**: phpstan suite + phpunit ^11.
- After dissolution the dependency boundary that per-package composer.json simulated is enforced instead by: `Architecture/component-inventory.yaml` dependency rules + boundary-lint ring checks + CI matrix per component directory (renamed from per-package).

## F-4. Composer scripts (proposed)

```jsonc
"scripts": {
  "test": "phpunit --configuration phpunit.xml.dist",
  "test:unit": "phpunit --configuration phpunit.xml.dist --testsuite Unit",
  "test:integration": "phpunit --configuration phpunit.xml.dist --testsuite Integration",
  "stan": "phpstan analyse",
  "lint:arch": "python3 Tools/lint/architecture-boundary-lint.py",
  "validate:inventory": "php Tools/ci/inventory-validate.php"   // new (Deliverable D-5)
}
```

## F-5. Migration sequence & stale-reference detection

1. **Stage 9 (single cutover)**: git-mv all components to tier paths (contents byte-identical except `namespace`/`use` lines); delete 20 per-pkg composer.json + ci/ + phpstan.neon (root phpstan config gains per-dir paths); rewrite root composer.json per F-1; `composer validate` + `composer dump-autoload -o`.
2. **Grep gates (fail CI):** `SovereignStack\\` occurrences = 0 in `**/*.php`; `packages/` references = 0 in `.github/`, `scripts/|Tools/`, `composer.json`, `phpunit*.xml*`, `anvil/`, `orchestrator/src|bin` (except protected grandfathered strings documented in the inventory); `sovereign-stack/` requires = 0.
3. **Autoload smoke:** a tiny script instantiates one class per namespace (21 mappings) — catches any mapping whose directory/namespace pair drifted.
4. **Lock policy:** after cutover, commit a root `composer.lock` (remove the gitignore line) — single-root dep tree makes the lock meaningful for the first time; CI validates `composer validate --strict` + lock freshness.
5. **Rollback:** the cutover is one commit (plus the stage commits before it); `git revert` of the cutover commit restores the package layout mechanically since no history rewriting occurs.

## F-6. Risks

| Risk | Mitigation |
|---|---|
| Dual `Core\Http` root hides middleware classes from the root autoloader today → after split, callers that *worked via vendor symlinks* must import the new NS | class-by-class split table (Deliverable C-1) + grep gate on `SovereignStack\Core\Http\Middleware` |
| per-pkg phpstan configs lost → strict-rule coverage drop | root phpstan.neon gains all component `src` paths at same level (max); strict-rules stays global |
| CI jobs that `composer install` per package lose their working-dir subjects | Stage 9 re-points matrix `working-dir` to component dirs; completeness checker learns tier layout in same commit |
| Loom release automation mis-fires on missing pkg composer.json | Stage 9 disables per-package release jobs pending D-H2 (documented, not silent) |

*Deliverable F complete.*
