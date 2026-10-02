# SHORTCOMINGS-AUDIT — Comprehensive Repository Shortcomings Report


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This verification/audit document is a **starting point, not a complete inventory**. The number of findings found is not the number of findings that exist. The audit was conducted by a single auditor with systematic blind spots (runtime-only issues, cross-tier drift, architectural assumptions, missing tests, auditor biases, unknown unknowns). **No audit is declared complete.** Findings are closed only when their verification condition passes — not when code changes. See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Task ID:** SHORTCOMINGS-AUDIT-76
**Date:** 2026-10-02
**Auditor:** General-purpose subagent (per tech-lead halt order)
**Repo HEAD:** `ce27388` (Hub DAG Phase 2 — HUB-DECLARED-DAG + HUB-VERIFIED-DAG + HUB-04 update (#292))
**Scope:** All categories — CI failures, doc drift, latent defects, governance decisions, architectural coherence.
**Method:** Static analysis + code reading + cross-reference verification. No code modifications performed. No CI runs performed (PHP not installed in sandbox — kernel failure mode inferred from code analysis).

## Summary

| Severity | Count | Categories |
|---|---|---|
| **FATAL** | 4 | CI (4) |
| **HIGH** | 25 | Doc-Drift (14), Latent-Defect (3), Governance (7), Coherence (1) |
| **MEDIUM** | 14 | Doc-Drift (3), Coherence (11) |
| **LOW** | 4 | Doc-Drift (2), Coherence (2) |
| **Total** | **47** | CI=4, Doc-Drift=19, Latent-Defect=3, Governance=7, Coherence=14 |

**Top critical finding:** 4 FATAL CI blockers — 2 architecture-lint failures (HUB-32 and ESPOKE-19 references in 5 and 3 files respectively) + 2 kernel PHPUnit test failures (WorkerContaminationTest concurrent-fiber + completed-fiber tests). The architecture-lint failures are pure mechanical violations of the linter's `validIds` range (HUB-31 max, no HUB-32). The kernel test failures trace to a real Container defect: `Container::pulse()` mutates a global definition rather than per-Fiber state, so the second Fiber's `pulse()` wipes the first Fiber's cached pulse-scoped instance.

---

## Category 1: CI Failures (FATAL — blocks merging code PRs)

### S-001 — architecture-lint fails on HUB-32 references outside code blocks

- **ID:** S-001
- **Category:** CI
- **Severity:** FATAL
- **Description:** ADR-021 §13 ratified "HUB-32 AI Inference Hub" but the lint script (`Architecture/Verification/lint/run.php` line 60) declares `HUB => range(1, 30)` plus an explicit `HUB-31` allow (line 72). `HUB-32` is therefore NOT in `validIds`. Multiple committed files reference `HUB-32` in regular markdown body text (outside fenced code blocks, which the lint strips via `preg_replace('/```.*?```/s', '', $text)` at line 89). Every such reference triggers an "undefined reference" error.
- **Evidence:** Files with `HUB-32` outside code blocks:
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` lines 45, 250, 252, 254, 258, 308, 348, 364 (10+ hits)
  - `Architecture/INDEX.md` lines 5, 45, 54, 225 (4 hits)
  - `Architecture/Hub/HUB-DECLARED-DAG.md` lines 66, 702, 705 (3 hits)
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` lines 4, 16, 30, 86, 396, 398, 400, 409, 419 (9+ hits)
  - `Architecture/Core/CORE-BUILD-ORDER.md` lines 254, 256, 318 (3 hits)
- **Impact:** `architecture-lint` CI check fails on every push to `main` and every PR that touches `Architecture/**`. Pre-existing per worklog (PR #288 onward). Currently NOT a required check (per PR #288 worklog), but blocks the lint's ability to catch real reference drift. Tech lead's halt order makes this blocking.
- **Remediation:** Either (a) extend `validIds` in `run.php` line 60 to include HUB-32, or (b) author `Architecture/Hub/HUB-32.md` blueprint file (currently nonexistent) and add `HUB-32` to the structural expected list at line 175. Option (b) is the proper fix per ADR-021 §13.

### S-002 — architecture-lint fails on ESPOKE-19 references outside code blocks

- **ID:** S-002
- **Category:** CI
- **Severity:** FATAL
- **Description:** ADR-021 §14 ratified "ESPOKE-19 Eloq" but the lint script (`run.php` line 62) declares `ESPOKE => range(1, 18)`. `ESPOKE-19` is NOT in `validIds`. Multiple committed files reference `ESPOKE-19` in regular markdown body text.
- **Evidence:** Files with `ESPOKE-19` outside code blocks:
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` lines 46, 256, 258, 260, 274, 349, 364 (7+ hits)
  - `Architecture/INDEX.md` lines 5, 47, 54 (3 hits)
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 409 (1 hit)
- **Impact:** Same as S-001 — `architecture-lint` fails on every push/PR. Pre-existing. Blocks lint CI signal.
- **Remediation:** Either (a) extend `validIds` in `run.php` line 62 to include ESPOKE-19, or (b) author `Architecture/Spoke/External/ESPOKE-19.md` blueprint file (currently nonexistent) and add `ESPOKE-19` to structural expected list at line 183.

### S-003 — kernel PHPUnit testConcurrentFibersObserveIndependentPulseState fails (Container pulse() global state leak)

- **ID:** S-003
- **Category:** CI
- **Severity:** FATAL
- **Description:** The test `WorkerContaminationTest::testConcurrentFibersObserveIndependentPulseState` expects `Container::pulse()` to register a per-Fiber value. The actual implementation (`packages/core/container/src/Container.php` line 134-151) sets a GLOBAL definition (`$this->definitions[$id]`) and calls `invalidatePulseInstances($id)` which iterates ALL Fibers' cached values and wipes the entry. So when Fiber B calls `pulse(RequestContext::class, $contextB)` after Fiber A's `pulse(RequestContext::class, $contextA)` + `Fiber::suspend()`, Fiber A's pulse scope is destroyed. When Fiber A resumes and calls `make(RequestContext::class)`, it resolves to `$contextB` (the global definition was overwritten), not `$contextA`. The assertion `assertSame(REQUEST_ID_A, $contextA_viewOfSelf->requestId)` fails.
- **Evidence:**
  - Test: `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` lines 60-142
  - Container defect: `packages/core/container/src/Container.php` line 134 (`pulse()` — global mutation, not per-Fiber)
  - Container cache invalidation: `packages/core/container/src/Container.php` lines 391-400 (`invalidatePulseInstances` — iterates all Fibers, wipes pulse-scoped cache for the re-bound id)
- **Impact:** `PHPUnit + PHPStan (core/kernel)` CI check fails on every PR that touches `packages/**` (gate job triggers). Pre-existing per worklog (line 1350). Blocks code PRs from merging.
- **Remediation:** Either (a) make `Container::pulse()` per-Fiber (store pulse-scoped definitions in `WeakMap<Fiber, array<string, ServiceDefinition>>` instead of global `$definitions`), or (b) rewrite the test to use a different mechanism (e.g., `Container::instance()` per-Fiber, or rewrite the test's helper closure to be unreachable from cross-Fiber `make()`). Option (a) is the architecturally correct fix per ADR-017 §"Fiber-based cooperative runtime" and SPEC §42 "WeakMap<Fiber, ...> → pulse() → request/Fiber-scoped instances".

### S-004 — kernel PHPUnit testCompletedFiberStateIsNotVisibleToNewFiber fails (same Container defect)

- **ID:** S-004
- **Category:** CI
- **Severity:** FATAL
- **Description:** The test `WorkerContaminationTest::testCompletedFiberStateIsNotVisibleToNewFiber` expects that after Fiber A completes (its pulse-scoped state is GC'd via WeakMap eviction), a new Fiber B calling `make(RequestContext::class)` without setting its own pulse scope should NOT see A's state. But the implementation has a global definition side-effect: Fiber A's `pulse(RequestContext::class, $contextA)` call sets `definitions[RequestContext::class].concrete = $contextA` (a global mutation). After Fiber A completes and is `unset()`, the WeakMap cache for Fiber A is evicted — BUT the global definition persists. Fiber B's `make(RequestContext::class)` finds no cache for itself, falls through to `build($contextA)` (which returns the object as-is), and caches `$contextA` for Fiber B. The assertion `assertNull($contextFromRequestB)` fails.
- **Evidence:**
  - Test: `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` lines 281-314
  - Container defect (same as S-003): `packages/core/container/src/Container.php` line 134-151
- **Impact:** Same as S-003 — `PHPUnit + PHPStan (core/kernel)` CI check fails. Same root cause; same remediation.
- **Remediation:** Same as S-003. The test's helper closure (lines 386-395) is unreachable once `pulse()` is called with an instance — the global definition wins over the closure path. The fundamental fix is making `pulse()` per-Fiber.

---

## Category 2: Documentation Drift (HIGH — stale docs vs actual code)

### S-005 — README.md says PHP 8.3 (actual is PHP ^8.4)

- **ID:** S-005
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `README.md` line 1, line 144 (Prerequisites), and line 188 (Built with) all say "PHP 8.3". The actual `composer.json` (root + all 12 package composer.json files) declares `"php": "^8.4"`. The CI workflow `packages-ci.yml` line 114 uses `php-version: '8.4'`. The architecture-lint workflow `architecture-lint.yml` line 24 still uses `php-version: '8.3'` (stale relative to the actual requirement).
- **Evidence:**
  - `README.md` line 1: "A from-scratch PHP 8.3 application framework"
  - `README.md` line 144: "PHP 8.3+"
  - `README.md` line 188: "PHP 8.3 | Runtime + all packages"
  - `composer.json` line 7: `"php": "^8.4"`
  - `packages/core/kernel/composer.json` line 8: `"php": "^8.4"`
- **Impact:** New contributors install PHP 8.3, fail to `composer install`, file false bug reports. Architecture-lint workflow uses wrong PHP version (may not match packages-CI behavior).
- **Remediation:** Find-replace "PHP 8.3" → "PHP 8.4" in README.md (3 occurrences); bump `architecture-lint.yml` line 24 from `'8.3'` to `'8.4'`.

### S-006 — README.md says "8 Core-tier packages" (actual is 12 on disk)

- **ID:** S-006
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `README.md` line 11 claims "**8 Core-tier packages** (PSR-7, PSR-15, PSR-11, PSR-14, attribute router, PSR-3 logging, config, error handler)". The actual `packages/core/` directory contains 12 packages: container, event-dispatcher, http-message, middleware, router, config, logger, error-handler, kernel, dbal, crypto, filesystem. The README was written at Milestone-0 completion (depth-2 of the original 8) and never updated when CORE-18 Kernel, CORE-19 DBAL, CORE-14 Filesystem, and CORE-16 Encryption were added.
- **Evidence:**
  - `README.md` line 11: "8 Core-tier packages"
  - `README.md` lines 53-60: structural listing shows only 8 packages
  - `packages/core/` directory listing: 12 packages (config, container, crypto, dbal, error-handler, event-dispatcher, filesystem, http-message, kernel, logger, middleware, router)
- **Impact:** Undercount by 4 packages. The README also lists the structure with only 8 packages — the missing 4 (kernel, dbal, crypto, filesystem) are unrepresented in the canonical structure diagram.
- **Remediation:** Update README.md line 11 to "12 Core-tier packages", and add the 4 missing entries to the structure tree at lines 53-60.

### S-007 — README.md says "20 ADRs" (actual is 21 ADRs)

- **ID:** S-007
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `README.md` line 12 ("20 Architecture Decision Records") and line 64 ("ADRs/ # 20 Architecture Decision Records") both say 20. The actual `Architecture/ADRs/` directory contains 21 ADR files: ADR-001 through ADR-021. ADR-021 was added in PR #287 (Amendment 1) and amended in PR #288 (Amendment 2).
- **Evidence:**
  - `README.md` line 12: "**20 Architecture Decision Records**"
  - `README.md` line 64: "ADRs/ # 20 Architecture Decision Records"
  - `Architecture/ADRs/` directory listing: 21 ADR files (ADR-001..021)
- **Impact:** New contributors undercount the ADR set by 1; ADR-021 (the most consequential recent ADR — tier-stratified build order, two-DAG governance model) is invisible to README readers.
- **Remediation:** Update README.md line 12 to "21 Architecture Decision Records" and line 64 to "ADRs/ # 21 Architecture Decision Records".

### S-008 — README.md does not mention ADR-021 / two-DAG governance model / HUB-32 / ESPOKE-19

- **ID:** S-008
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `README.md` "Key design decisions" table (lines 129-138) lists ADR-001, 005, 014, 017, 018, 019 — but omits ADR-021 (the tier-stratified build order + two-DAG governance model + Eligible(X) admission rule + 4-edge-type dimension model + multigraph semantics + HUB-32/ESPOKE-19 ratification + HUB-10/HUB-25 relocation to Runtime tier). This is the most architecturally consequential ADR of the last 2 laps and is invisible in the README. README also says "102 component blueprints" (line 13) which is pre-ADR-021 count (relocations + pending ratifications don't change the canonical 102 count per INDEX.md §4, but the README doesn't acknowledge the relocations).
- **Evidence:**
  - `README.md` lines 129-138: ADR table missing ADR-021 row
  - `README.md` line 13: "102 component blueprints — full implementation specs for Core, Hub, Bridge, Spoke, and Deploy tiers"
  - `README.md` line 66: "Hub/ # 31 Hub-tier blueprints" (should mention 2 SUPERSEDED + 1 pending per ADR-021)
- **Impact:** Anyone reading the README gets an outdated architectural picture — they don't know about the two-DAG model, the Runtime tier, the relocations, or the new ratifications. This is the canonical entry-point document and is stale.
- **Remediation:** Add an ADR-021 row to the "Key design decisions" table; update the structure tree's Hub/ line to note SUPERSEDED + pending; add a paragraph in "What is this?" describing the two-DAG governance model.

### S-009 — README.md says "PHPUnit 10.5" (actual is PHPUnit ^11.0)

- **ID:** S-009
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `README.md` line 192 says "PHPUnit 10.5 | Testing". The actual root `composer.json` line 41 declares `phpunit/phpunit: ^11.0`, and all 12 package `composer.json` files declare `^11.0`. PHPUnit 11 has breaking changes from 10.x (different test attributes, different assertion APIs in places).
- **Evidence:**
  - `README.md` line 192: "PHPUnit 10.5 | Testing"
  - `composer.json` line 41: `"phpunit/phpunit": "^11.0"`
  - `packages/core/kernel/composer.json` line 24: `"phpunit/phpunit": "^11.0"`
- **Impact:** Contributors install PHPUnit 10.5, get different behavior than CI, may produce tests that pass locally but fail CI (or vice versa).
- **Remediation:** Update README.md line 192 to "PHPUnit 11.x | Testing".

### S-010 — DEPLOY-01.md describes PHP-FPM + Nginx + Supervisor (should be FrankenPHP per ADR-017)

- **ID:** S-010
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `Architecture/Deploy/DEPLOY-01.md` describes a deployment based on "PHP-FPM 8.3, Nginx, Supervisor" (line 7, line 15, line 29, etc.) — but ADR-017 (ratified 2026-09-23) ratifies "FrankenPHP 1.12" as the application server (per README.md line 188 itself: "FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017)"). The deployment blueprint contradicts the ratified ADR.
- **Evidence:**
  - `DEPLOY-01.md` line 7: "shared PHP-FPM + Nginx + Supervisor base"
  - `DEPLOY-01.md` line 15: "bundles PHP-FPM 8.3, Nginx, Supervisor"
  - `DEPLOY-01.md` line 29: "Runtime: PHP 8.3-FPM, Nginx 1.27, Supervisor 4, Alpine 3.20"
  - `ADRs/ADR-017-fiber-based-cooperative-runtime.md` (ratified)
  - `README.md` line 188: "FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017)"
- **Impact:** Anyone following DEPLOY-01 builds a PHP-FPM stack that contradicts ADR-017's fiber-based cooperative runtime. The deployment blueprint and the architecture ADR disagree; the ADR wins per doctrine, but operators following the blueprint ship the wrong stack.
- **Remediation:** Rewrite DEPLOY-01.md to use FrankenPHP (replacing PHP-FPM + Nginx + Supervisor with FrankenPHP + Caddy + Anvil v3 substrate). Or split DEPLOY-01 into two blueprints: legacy PHP-FPM (deprecated) and FrankenPHP (canonical).

### S-011 — DEPLOY-01.md does not mention the runtime substrate (Anvil v3)

- **ID:** S-011
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** ADR-021 §12 introduces a new "Runtime" tier with `RUNTIME-01..04` (Anvil v3 = Caddy + Tengine + FrankenPHP, systemd timers, Queue Worker relocated from HUB-10, Chronos relocated from HUB-25). DEPLOY-01.md should describe how the deployment blueprint relates to the runtime substrate (Anvil v3), but it doesn't mention Anvil v3 or the Runtime tier at all. The deployment blueprint predates the ADR-021 Runtime tier ratification.
- **Evidence:**
  - `DEPLOY-01.md` — no mention of "Anvil v3" or "Runtime tier" or "RUNTIME-01..04" anywhere in the file
  - `ADRs/ADR-021-tier-stratified-build-order.md` line 43: Runtime tier = "Anvil v3 (Caddy+Tengine+FrankenPHP), systemd timers, Queue Worker (relocated from HUB-10), Chronos (relocated from HUB-25)"
  - INDEX.md line 144: HUB-10 SUPERSEDED → RUNTIME-03; HUB-25 SUPERSEDED → RUNTIME-04
- **Impact:** Deployment blueprint and ADR-021 disagree on what the substrate layer is. Operators don't know whether to deploy DEPLOY-01's PHP-FPM/Nginx/Supervisor stack or ADR-021's Anvil v3 stack.
- **Remediation:** Update DEPLOY-01.md to reference the Runtime tier (Anvil v3) as the substrate; or split DEPLOY-01 into DEPLOY-01 (FrankenPHP service images) and a new RUNTIME-01 (Anvil v3 substrate) blueprint.

### S-012 — anvil/app/php/preload.php references 9 nonexistent classes

- **ID:** S-012
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `anvil/app/php/preload.php` lines 27-41 list 11 classes to opcache-preload. Of these, only 2 actually exist on disk: `SovereignStack\Core\Http\Request` and `SovereignStack\Core\Http\Response` (both in `packages/core/http-message/src/`). The other 9 are nonexistent:
  - `SovereignStack\Core\Contracts\PulseInterface` — no file
  - `SovereignStack\Core\Contracts\SchedulerInterface` — no file
  - `SovereignStack\Core\Contracts\TenantScopeInterface` — no file
  - `SovereignStack\Core\Fiber\Pulse` — no file
  - `SovereignStack\Core\Fiber\Scheduler` — no file
  - `SovereignStack\Core\Middleware\Pipeline` — no file (real name is `SovereignStack\Core\Http\MiddlewarePipeline` in `packages/core/middleware/src/`)
  - `SovereignStack\Core\Http\Kernel` — no file (real namespace is `SovereignStack\Core\Kernel\Kernel` in `packages/core/kernel/src/`)
  - `SovereignStack\Hub\Hub` — no file
  - `SovereignStack\Hub\Registry` — no file
- **Evidence:**
  - `anvil/app/php/preload.php` lines 27-41: `$preload_classes = [...]`
  - `find packages -name Pulse.php -o -name Scheduler.php -o -name Kernel.php -o -name Hub.php -o -name Registry.php` returns only `packages/core/kernel/src/Kernel.php` (namespace `SovereignStack\Core\Kernel`, not `SovereignStack\Core\Http`)
  - SDLC-AUDIT-1 worklog (Task 68) flagged this issue
- **Impact:** The preload runs in production. Each nonexistent class triggers `is_file($path)` returning false (silent no-op at line 50). The preload effectively preloads nothing useful — production workers don't get the hot-path class preloading that ADR-010 promises. False sense of optimization.
- **Remediation:** Update preload.php to reference the actual class names: `SovereignStack\Core\Http\MiddlewarePipeline`, `SovereignStack\Core\Kernel\Kernel`. Remove references to nonexistent Contracts/*, Fiber/*, Hub/*, Hub\Registry classes (or implement them). Also fix the path resolution bug (S-013).

### S-013 — anvil/app/php/preload.php path resolution is broken (won't load existing classes either)

- **ID:** S-013
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** `preload.php` lines 44-54 resolve class names to file paths by:
  ```php
  $relative = str_replace(['SovereignStack\\', '\\'], ['/', '/'], $class);
  $candidates = [
      $releaseRoot . '/packages/core/src' . $relative . '.php',
      $releaseRoot . '/packages/hub/src' . $relative . '.php',
  ];
  ```
  This resolves `SovereignStack\Core\Http\Request` → `/Core/Http/Request.php` and looks for `<releaseRoot>/packages/core/src/Core/Http/Request.php`. But the actual file is at `packages/core/http-message/src/Request.php` (the PSR-4 root is `packages/core/http-message/src/` mapped to `SovereignStack\Core\Http\`). The preload path `<releaseRoot>/packages/core/src/Core/Http/Request.php` does not exist — `is_file()` returns false, the class is silently skipped.
- **Evidence:**
  - `anvil/app/php/preload.php` lines 43-55
  - `packages/core/http-message/composer.json` line 27: `"SovereignStack\\Core\\Http\\": "src/"` (PSR-4 root: `packages/core/http-message/src/`)
- **Impact:** Even the 2 EXISTING classes (`Request`, `Response`) are never actually preloaded because the path resolution is wrong. Preload.php is a complete dead-letter — preloads zero classes.
- **Remediation:** Use the Composer autoloader's `vendor/autoload.php` to resolve class-to-file mappings, or hardcode the correct paths per package. Better: use `opcache_compile_file()` with paths derived from each package's composer.json PSR-4 mappings.

### S-014 — INDEX.md §1 line 45 active Hub count contradiction ("29 + 1 pending = 30")

- **ID:** S-014
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §1 line 45 reads: "active Hub count = 31 − 2 relocated = 29 + 1 pending = 30". The arithmetic conflates "active" with "active + pending". SAAI flagged that HUB-32 (pending canonical publication, no blueprint file) should NOT be counted in the active inventory. Per ADR-021 §13 line 254: "INDEX.md still says 31 Hubs. This is intentional — the decision is ratified; the canonical blueprint is deferred to the implementation phase." Per INDEX.md §4 line 218: "31 declared (29 active + 2 superseded)" — that's 29 active, NOT 30. §1 line 45 contradicts §4 line 218.
- **Evidence:**
  - `INDEX.md` line 45: "active Hub count = 31 − 2 relocated = 29 + 1 pending = 30"
  - `INDEX.md` line 218: "31 declared (29 active + 2 superseded)" — 29 active, no mention of pending in the count
  - `INDEX.md` line 225: "HUB-32 AI Inference Hub ratified pending canonical publication (not yet counted in active inventory)"
- **Impact:** Two contradictory counts of the same canonical inventory within the same document. Readers can't tell whether the active Hub count is 29 or 30. Breaks the "single source of truth" promise of §1.
- **Remediation:** Change line 45 to: "active Hub count = 31 − 2 superseded = 29 active; HUB-32 ratified pending canonical publication (not counted in active inventory until blueprint lands)".

### S-015 — INDEX.md §1 line 60 says CORE-02 is "stub only (.gitkeep)" — contradicts §2.1

- **ID:** S-015
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §1 line 60: "`packages/core/container/` | CORE-02 reference implementation — **stub only (`.gitkeep`)**; full spec in `Core/CORE-02.md`". But §2.1 line 95 says: "CORE-02 | Dependency Injection Container | `SovereignStack\Core\Container` | `packages/core/container/` | ✅ **Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance**". The §1 line 60 description is from the pre-MUWV era (pre-2026-09-23) when CORE-02 was a stub. It was implemented in PR #127 (per README.md line 209) — but §1 line 60 was never updated.
- **Evidence:**
  - `INDEX.md` line 60: "stub only (.gitkeep)"
  - `INDEX.md` line 95: "Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance"
  - `Architecture/Verification/INCONSISTENCIES.md` line 107-112: "#8 — CORE-02 (DI Container) is an empty stub — **Flagged as the top build-blocking dependency.**" — INCONSISTENCIES.md is also stale (still flagged critical, but CORE-02 is implemented).
- **Impact:** Internal contradiction in the canonical index. Reader doesn't know whether CORE-02 is a stub or implemented. INCONSISTENCIES.md still flags it as "Flagged critical" — pre-MUWV state.
- **Remediation:** Update §1 line 60 to "CORE-02 reference implementation — **implemented + tested, v1.0.0**"; update INCONSISTENCIES.md #8 status to "Resolved (PR #127, 2026-09-18)".

### S-016 — INDEX.md §1 missing 5 ADR entries (ADR-016 through ADR-020)

- **ID:** S-016
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §1 lists the canonical ADRs: ADR-001..010 (line 50), ADR-011 (line 56), ADR-012 (line 51), ADR-013 (line 52), ADR-014 (line 53), ADR-015 (line 55), ADR-021 (line 54). That's 16 ADRs listed. But the `Architecture/ADRs/` directory contains 21 ADR files (ADR-001 through ADR-021). Missing from §1: ADR-016 (Proposed), ADR-017 (Accepted), ADR-018 (Accepted), ADR-019 (Accepted), ADR-020 (Accepted). All 5 are ratified decisions; 4 of them are Accepted; their absence from the canonical index means readers don't know they exist.
- **Evidence:**
  - `INDEX.md` lines 50-56: lists 16 ADRs only
  - `Architecture/ADRs/` directory: 21 ADR files
  - `ADRs/ADR-016-library-app-boundary-split.md` line 3: "Status: Proposed"
  - `ADRs/ADR-017-fiber-based-cooperative-runtime.md` line 3: "Status: Accepted"
  - `ADRs/ADR-018-centralized-per-tier-releases.md` line 3: "Status: Accepted"
  - `ADRs/ADR-019-pre-muwv-version-scheme.md` line 3: "Status: Accepted"
  - `ADRs/ADR-020-stable-and-bleeding-edge-release-channels.md` line 3: "Status: Accepted"
- **Impact:** INDEX.md is the "single source of truth" per §1 header. 5 ADRs (including ADR-017 Fiber runtime, ADR-018 per-tier releases, ADR-019 version scheme — all critical) are missing. README.md line 134 references ADR-017 as Accepted — so the ADR exists and is in force, just not in INDEX.md.
- **Remediation:** Add 5 new rows to INDEX.md §1 canonical table for ADR-016, ADR-017, ADR-018, ADR-019, ADR-020 with their status and one-line description.

### S-017 — INDEX.md §5.2 still contains the old Mermaid DAG (superseded but content not collapsed)

- **ID:** S-017
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §5.2 (lines 281-407) contains the full 18-edge monolithic Mermaid DAG. A SUPERSEDED banner was added at §5 (lines 251-262) declaring the section superseded by ADR-021 per-tier DAGs — but the content was NOT collapsed/removed. The full 126-line Mermaid block remains, including the "selected critical" Hub subset (10 Hubs: H01, H02, H03, H04, H06, H08, H11, H15, H19, H20) which is inconsistent with §4's Critical set (10 Hubs: H01, 02, 04, 05, 08, 09, 10, 19, 20, 21).
- **Evidence:**
  - `INDEX.md` lines 251-262: SUPERSEDED banner
  - `INDEX.md` lines 281-407: full Mermaid DAG content still present (not collapsed)
  - `INDEX.md` line 327-336: §5.2 selected critical = {H01, H02, H03, H04, H06, H08, H11, H15, H19, H20}
  - `INDEX.md` line 235: §4 Critical = {H01, 02, 04, 05, 08, 09, 10, 19, 20, 21}
- **Impact:** Document is bloated (126 lines of stale Mermaid); §5.2 contradicts §4 even though both are in the same file; readers may follow the stale DAG. ADR-021's supersession banner is insufficient — the content should be moved to a separate archive file or shortened to a one-paragraph summary with a link to ADR-021.
- **Remediation:** Replace the §5.2 Mermaid block with a one-paragraph summary + link to `Architecture/Core/CORE-VERIFIED-DAG.md` and `Architecture/Hub/HUB-DECLARED-DAG.md`. Move the full Mermaid to `Architecture/Archive/INDEX-5.2-snapshot-2026-09-29.md`.

### S-018 — INDEX.md §5.3 still contains the 11-step build sequence (superseded but content still present)

- **ID:** S-018
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §5.3 (lines 409-445) contains the full 11-step global build sequence with effort estimates and "parallelizable" labels. A SUPERSEDED banner was added at line 411 — but the content was NOT collapsed/removed. Step 8 line 434 says "Hub tier (30 blueprints)" which is stale (post-ADR-021: 31 declared, 29 active, 1 pending).
- **Evidence:**
  - `INDEX.md` line 411: SUPERSEDED banner
  - `INDEX.md` lines 425-437: full 11-step table still present
  - `INDEX.md` line 434: "Hub tier (30 blueprints)" — stale (should be 29 active or 31 declared per ADR-021)
- **Impact:** Same as S-017 — bloat + contradiction + reader confusion. The 11-step sequence has 4 known wrong steps (per CORE-BUILD-ORDER.md §6: Step 2 false-parallelism, Step 3 inverted order, Step 5 over-cautious entry criterion, Step 6 arbitrary SuperPHP) — leaving this content in INDEX invites regression.
- **Remediation:** Replace §5.3 with a one-paragraph summary + link to `Architecture/Core/CORE-BUILD-ORDER.md`. Move the 11-step table to archive.

### S-019 — INDEX.md §4 vs §5.2 criticality inconsistency (different 10-Hub subsets)

- **ID:** S-019
- **Category:** Doc-Drift
- **Severity:** HIGH
- **Description:** INDEX.md §4 (line 235) lists "Critical | 10 | HUB-01, 02, 04, 05, 08, 09, 10, 19, 20, 21". INDEX.md §5.2 (lines 327-336) lists a "selected critical" subset = {H01, H02, H03, H04, H06, H08, H11, H15, H19, H20} — a different 10 Hubs. ADR-021 §5 line 20 (the rationale) explicitly flags this: "4 of §4's 10 Critical Hubs (HUB-05, HUB-09, HUB-10, HUB-21) are missing from §5.2; 4 'High' Hubs are in the DAG instead."
- **Evidence:**
  - `INDEX.md` line 235: §4 Critical = {01, 02, 04, 05, 08, 09, 10, 19, 20, 21}
  - `INDEX.md` lines 327-336: §5.2 selected critical = {01, 02, 03, 04, 06, 08, 11, 15, 19, 20}
  - `ADRs/ADR-021-tier-stratified-build-order.md` line 20: explicitly calls out this inconsistency
- **Impact:** Two different definitions of "Critical Hub" within the same canonical document. Cross-tier derivation work (e.g., Hub DAG Phase 2) cannot pick a definitive source.
- **Remediation:** Once §5.2 content is collapsed per S-017, this contradiction is automatically resolved. Otherwise, align the §5.2 subset to §4's Critical set OR explicitly rename §5.2's subset to "build-priority subset" (not "critical").

### S-020 — README.md "Build order (INDEX.md §5)" table is stale (superseded by ADR-021)

- **ID:** S-020
- **Category:** Doc-Drift
- **Severity:** MEDIUM
- **Description:** README.md lines 90-96 shows a "Build order" table claiming "all 8 Milestone 0 blueprints shipped" with status "✅ Complete". The §5 build sequence has been superseded by ADR-021 (per the SUPERSEDED banner in INDEX.md §5). The README table also says "8 of 8 Milestone 0 blueprints shipped" in step 4 line 95 — but this count is post-Milestone-0 and doesn't reflect the post-ADR-021 build order (4-wave topological, not 4-step).
- **Evidence:**
  - `README.md` lines 90-96: Build order table
  - `INDEX.md` line 251-262: §5 superseded by ADR-021
- **Impact:** README points to INDEX §5 as the source of build order — but INDEX §5 is itself superseded. Reader follows stale path.
- **Remediation:** Replace README §"Build order (INDEX.md §5)" with a one-line pointer to `Architecture/Core/CORE-BUILD-ORDER.md` (per ADR-021 §11). Remove the local 4-row table.

### S-021 — README.md "Prerequisites" line 147 lists ext-pcre instead of ext-mbstring + ext-xml

- **ID:** S-021
- **Category:** Doc-Drift
- **Severity:** MEDIUM
- **Description:** README.md line 147 lists prerequisites: "`ext-mbstring`, `ext-fileinfo`, `ext-pcre`". But the CI workflow `packages-ci.yml` line 116 specifies `extensions: mbstring, xml, dom` (not pcre, not fileinfo). The actual extensions used by packages differ from the README list.
- **Evidence:**
  - `README.md` line 147: "`ext-mbstring`, `ext-fileinfo`, `ext-pcre`"
  - `.github/workflows/packages-ci.yml` line 116: `extensions: mbstring, xml, dom`
- **Impact:** Contributors install wrong extensions. CI passes (CI uses the right list), but local development may fail.
- **Remediation:** Align README's ext list with the CI list: `mbstring, xml, dom` (fileinfo is usually bundled; pcre is enabled by default).

### S-022 — README.md says "8 of 8 Milestone 0 blueprints shipped" — but actually 9 were shipped (CORE-17 stub also shipped)

- **ID:** S-022
- **Category:** Doc-Drift
- **Severity:** MEDIUM
- **Description:** README.md line 95 says "**all 8 Milestone 0 blueprints shipped**" — but per README.md line 218, the "Also shipped but not in the Milestone 0 scope" includes CORE-17 (Service Providers, stub). Also, the line 218 list omits CORE-14 (Filesystem), CORE-16 (Encryption), CORE-19 (DBAL) which are implemented per packages/core/ directory listing.
- **Evidence:**
  - `README.md` line 95: "all 8 Milestone 0 blueprints shipped"
  - `README.md` line 218: "Also shipped but not in the Milestone 0 scope: CORE-03, CORE-10, CORE-09, CORE-08, CORE-17"
  - `packages/core/` directory: 12 packages (8 Milestone-0 + 4 additional: kernel, dbal, crypto, filesystem — but README line 218 only mentions CORE-17 stub, not CORE-14/16/19)
- **Impact:** Implementation status is misrepresented in the canonical README. The "Also shipped" list is itself stale (only mentions CORE-17, omits CORE-14/16/19).
- **Remediation:** Update README.md line 218 "Also shipped" list to: "CORE-03, CORE-10, CORE-09, CORE-08, CORE-17 (stub), CORE-14, CORE-16, CORE-19".

---

## Category 3: Known Latent Defects (documented but unfixed)

### S-023 — C04↔C05 PSR-4 namespace collision still present

- **ID:** S-023
- **Category:** Latent-Defect
- **Severity:** HIGH
- **Description:** Both `packages/core/http-message/composer.json` and `packages/core/middleware/composer.json` declare `"SovereignStack\\Core\\Http\\": "src/"` as their PSR-4 root. ADR-021 §21 line 294 documents this as a known latent defect. The remediation is "namespace split in a future ADR. The namespace root lint rule (§16) will catch this going forward." But §16 of ADR-021 doesn't actually exist (the ADR goes up to §14), and no namespace root lint rule has been implemented in `Architecture/Verification/lint/run.php`.
- **Evidence:**
  - `packages/core/http-message/composer.json` line 27: `"SovereignStack\\Core\\Http\\": "src/"`
  - `packages/core/middleware/composer.json` line ~10 (similar): `"SovereignStack\\Core\\Http\\": "src/"`
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 294: documents this as known defect
  - `composer.json` (root) line 48: only registers `packages/core/http-message/src/` (not middleware/src/) for `SovereignStack\\Core\\Http\\`
- **Impact:** Composer's PSR-4 generator merges both paths into an array — runtime works (autoloader searches both). But the architecture is muddled: the namespace doesn't cleanly indicate which package "owns" it. ADR-021 §16 lint rule (which doesn't exist yet) is supposed to catch this, but the lint rule was never implemented.
- **Remediation:** Either (a) author ADR-022 to split the namespace (e.g., `SovereignStack\Core\Http\Message\` for C04, `SovereignStack\Core\Http\Middleware\` for C05 — both breaking changes), or (b) implement the namespace root lint rule (extend `run.php` to detect two packages claiming the same PSR-4 prefix).

### S-024 — C17 forward-declaration stub still exists (kernel/src/Stub/ProviderRegistryInterface.php + EmptyProviderRegistry.php)

- **ID:** S-024
- **Category:** Latent-Defect
- **Severity:** HIGH
- **Description:** `packages/core/kernel/src/Stub/ProviderRegistryInterface.php` (40 lines) and `packages/core/kernel/src/Stub/EmptyProviderRegistry.php` (30 lines) still exist as forward-declaration placeholders for CORE-17 (Service Provider System), which is unimplemented. C18's boot calls `$providerRegistry->registerAll($container)` and `$providerRegistry->bootAll($container)` (Kernel.php lines 267, 298). `EmptyProviderRegistry`'s both methods are no-ops. ADR-021 §21 line 295 documents this as a known latent defect. Per ADR-021 §6, C18's `integration_completeness` is PARTIALLY WIRED and `production_gate` requires C17.
- **Evidence:**
  - `packages/core/kernel/src/Stub/ProviderRegistryInterface.php` (40 lines, full file)
  - `packages/core/kernel/src/Stub/EmptyProviderRegistry.php` (30 lines, full file)
  - `packages/core/kernel/src/Kernel.php` line 267: `$providerRegistry->registerAll($container);` (no-op call)
  - `packages/core/kernel/src/Kernel.php` line 298: `$providerRegistry->bootAll($container);` (no-op call)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 295: documents this as known defect
  - `Architecture/INDEX.md` line 110: "CORE-17 | Service Provider System | 📝 Not started"
- **Impact:** C18 cannot reach `integration_completeness = FULLY WIRED` or `production_gate = SATISFIED` until C17 ships and the stub is replaced with a real ServiceProviderRegistry. Service providers don't actually register or boot — production workers boot without provider wiring.
- **Remediation:** Implement CORE-17 (`packages/core/providers/`) per `Architecture/Core/CORE-17.md`. Replace `EmptyProviderRegistry` with a real `ServiceProviderRegistry` that discovers and boots providers from a configured list. Delete the `Stub/` directory.

### S-025 — ADR-021 §21 line 296 documents "H05/H07 Rate Limiter duplication" but HUB-05 is RBAC (not Rate Limiter)

- **ID:** S-025
- **Category:** Latent-Defect
- **Severity:** HIGH
- **Description:** ADR-021 §21 line 296 documents "H05/H07 Rate Limiter duplication" as a known latent defect: "H05 and H07 may be duplicate Rate Limiter Hubs. Tech-lead decision pending." But the actual HUB-05 blueprint is `# PHASE HUB-05: RBAC & Permission Engine` (Sovereign Guardian) — it has nothing to do with Rate Limiting. HUB-07 is `# PHASE HUB-07: Rate Limiter & Throttle Engine` (Sovereign Throttle) — that IS the rate limiter. The "duplication" claim is wrong: HUB-05 and HUB-07 are entirely different services. Either the claim was wrong from the start, or HUB-05 used to mention rate limiting and was edited.
- **Evidence:**
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 296: "H05/H07 Rate Limiter duplication"
  - `Architecture/Hub/HUB-05.md` line 1: "# PHASE HUB-05: RBAC & Permission Engine"
  - `Architecture/Hub/HUB-05.md` line 12: "Sovereign Guardian"
  - `Architecture/Hub/HUB-05.md`: grep for "Rate|Throttle|throttle|rate limit" returns NO matches
  - `Architecture/Hub/HUB-07.md` line 1: "# PHASE HUB-07: Rate Limiter & Throttle Engine"
- **Impact:** ADR-021 documents a defect that doesn't exist. Tech lead may waste time investigating a non-issue. Or: the underlying defect (whatever it was originally) has been resolved by editing HUB-05 to remove rate limiting — but ADR-021's documentation wasn't updated.
- **Remediation:** Either (a) delete ADR-021 §21 line 296 (defect doesn't exist), or (b) document the historical context (e.g., "HUB-05 originally mentioned rate limiting in v0.1; that language was removed in PR #XXX; this entry is retained as a historical note"). Investigate git history for HUB-05.md to determine which case applies.

### S-026 — Old `generate-architecture-baseline.py` v1 script still exists alongside v2

- **ID:** S-026
- **Category:** Latent-Defect
- **Severity:** MEDIUM
- **Description:** `scripts/generate-architecture-baseline.py` (v1, 15 KB, broken per SDLC-AUDIT-1) still exists alongside `scripts/generate-architecture-baseline-v2.py` (v2, 19 KB, the new authoritative version). ADR-021 line 352 only references v2 as "NEW — evidence-collection script for baseline generation." The v1 script is undocumented in ADR-021 — neither deprecated nor referenced. The companion `scripts/generate-architecture-baseline.php` (PHP version, 18 KB) also still exists with the original 2026-09-24 docstring.
- **Evidence:**
  - `scripts/generate-architecture-baseline.py` (15 KB, mtime 2026-09-24)
  - `scripts/generate-architecture-baseline-v2.py` (19 KB, mtime 2026-10-01)
  - `scripts/generate-architecture-baseline.php` (18 KB, mtime 2026-09-24)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 352: only v2 is referenced
- **Impact:** Three scripts that do overlapping things — operators don't know which to run. The v1 script is broken (per SDLC-AUDIT-1); running it produces wrong output. ADR-021 doesn't mention deprecation.
- **Remediation:** Either (a) delete `generate-architecture-baseline.py` (v1) and `generate-architecture-baseline.php` (legacy PHP version) and update ADR-021 to note the deletion, or (b) add a deprecation header to both files pointing to v2.

---

## Category 4: Hub DAG Governance Decisions (7 flagged, unresolved)

### S-027 — Gap 1: HUB-06→HUB-11 "Queue" label vs INDEX §2.2 (Cloud Storage)

- **ID:** S-027
- **Category:** Governance
- **Severity:** HIGH
- **Description:** HUB-06.md line 40 lists HUB-11 in its Upward section with the label "Queue". But INDEX.md §2.2 says HUB-11 = "Cloud Storage", and HUB-10 was the Queue (now SUPERSEDED → RUNTIME-03). The label is wrong: either the edge should point to HUB-10 (now RUNTIME-03), or the label "Queue" should be corrected to "Cloud Storage". ADR-021 deferred this to "Phase 2 governance decision". The HUB-DECLARED-DAG.md row 16 (line 93) records the edge with `UNKNOWN` edge_type and `UNKNOWN` requiredness, noting "Phase 1 §7 Gap 1 contradiction" — unresolved.
- **Evidence:**
  - `Architecture/Hub/HUB-06.md` line 40: "Upward: CORE-19, CORE-03, CORE-02, CORE-09, CORE-14, HUB-04, HUB-11 (labelled 'Queue' — see §7 Gap 1)"
  - `Architecture/INDEX.md` §2.2: HUB-11 = Cloud Storage
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 93: row 16 records the edge as UNKNOWN/UNKNOWN
- **Impact:** Hub DAG has an edge with unclassified edge_type and requiredness. Build-order derivation (Phase 3) cannot proceed until this is resolved.
- **Remediation:** Tech-lead decision: either (a) change HUB-06.md line 40 from "HUB-11 (Queue)" to "RUNTIME-03 (Queue)" and remove from Hub DAG, or (b) change "Queue" to "Cloud Storage" and update HUB-06 to reflect that HUB-11 is Cloud Storage.

### S-028 — Gap 3: HUB-15 "reverse Downward" placement inconsistency (source blueprint unchanged)

- **ID:** S-028
- **Category:** Governance
- **Severity:** HIGH
- **Description:** HUB-15.md line 89-90 declares 6 Hub→Hub edges in its **Downward** section that are semantically **Upward** (HUB-15 polls HUB-01, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20's `/health` endpoints — HUB-15 is the consumer). HUB-DECLARED-DAG.md Resolution 1 captures these as RUNTIME edges in the DAG (rows 34-40). But the SOURCE blueprint HUB-15.md is unchanged — the prose still says "Downward". Future readers of HUB-15.md see the inconsistent placement.
- **Evidence:**
  - `Architecture/Hub/HUB-15.md` lines 89-90: "Downward (reverse — see §7 Gap 3): Every Hub service (HUB-01, HUB-02, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20)..."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` lines 100-127: rows 34-40 record these as RUNTIME edges with `HUB-15 reverse-Down — Resolution 1 cycle split`
  - Phase 1 inventory §7 Gap 3: "Phase 2 recommendation: Move these 6 entries from HUB-15's Downward section to its Upward section in the blueprint. Until then, treat them as DECLARED_ONLY with the placement inconsistency flagged."
- **Impact:** Source blueprint contradicts the DAG. Until the source is updated, the DAG is a "post-processed" view that doesn't match what operators see when reading HUB-15.md.
- **Remediation:** Move the 6 entries from HUB-15.md Downward to Upward section. Update HUB-15.md to reflect that HUB-15 polls (consumes) those Hubs' `/health` endpoints.

### S-029 — Gap 5: HUB-16 Downward is generic ("every other Hub component") — not enumerable

- **ID:** S-029
- **Category:** Governance
- **Severity:** HIGH
- **Description:** HUB-16.md line 169 declares: "Downward: every other Hub component — this is the 'Merge Gate' for the tier per the original design intent" — too generic to enumerate. The HUB-DECLARED-DAG.md excludes this generic declaration (correctly, since it's not enumerable). But the source blueprint still has the generic text — readers don't know which Hubs HUB-16 actually consumes. Phase 1 inventory §7 Gap 5: "Phase 2 recommendation: Author HUB-16's Downward section to enumerate the specific Hub consumers (likely all 28 other active Hubs, since HUB-16 is the 'Merge Gate' — but this should be explicit in the blueprint, not inferred)."
- **Evidence:**
  - `Architecture/Hub/HUB-16.md` line ~169: "Downward: every other Hub component — this is the 'Merge Gate'..."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 115: only 1 outbound edge from HUB-16 (→ HUB-15); the generic "every other Hub" is excluded
  - Phase 1 inventory §7 Gap 5: "not enumerable"
- **Impact:** HUB-16's actual consumer set is unknown. Build-order derivation cannot determine whether HUB-16 must be built before or after specific other Hubs. The "Merge Gate" semantics imply HUB-16 is built LAST among Hubs, but this is not explicit.
- **Remediation:** Author HUB-16.md Downward section with explicit Hub enumeration (likely all 28 other active Hubs, or a subset that HUB-16 specifically gates).

### S-030 — Gap 8: 26 asymmetric downward-only declarations (blueprint drift)

- **ID:** S-030
- **Category:** Governance
- **Severity:** HIGH
- **Description:** 26 of 87 Hub→Hub edges (30%) are downward-only declarations: the producer acknowledges the consumer in its Downward section, but the consumer doesn't acknowledge the producer in its Upward section. This is blueprint drift — the producer knew about the consumer, but the consumer's blueprint was never updated. HUB-DECLARED-DAG.md captures all 26 in the DAG (rows with "↓-only" markers), but the source blueprints are unchanged.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 660 area: "Gap 8 — 26 asymmetric downward-only declarations (blueprint drift)"
  - `download/HUB-EDGE-INVENTORY.md` §7 Gap 8 (lines 660-672): full analysis
  - Specifically, the 6 reverse-Down edges from HUB-15 (rows 34, 36, 37, 38, 39, 40 in HUB-DECLARED-DAG) are downward-only because HUB-15's Upward list doesn't formally declare HUB-01/HUB-04/HUB-06/HUB-08/HUB-19/HUB-20 as dependencies (Gap 3 + Gap 8 combined)
- **Impact:** DAG captures the drift, but source blueprints remain inconsistent. Future blueprint authors don't know to add the Upward declaration on the consumer side. Drift will compound.
- **Remediation:** Reconcile each downward-only edge by either (a) adding the Upward declaration to the consumer's blueprint, or (b) removing the Downward declaration from the producer's blueprint if the edge doesn't actually exist. 26 blueprints need editing.

### S-031 — Gap 9: 136 of 150 declared edges have edge_type = UNKNOWN

- **ID:** S-031
- **Category:** Governance
- **Severity:** HIGH
- **Description:** HUB-DECLARED-DAG.md §7 line 685: "136 declared edges have edge_type = UNKNOWN. These are conventional Hub→Hub and Hub→Core declarations where the blueprint author did not specify the edge_type." Only 14 edges have explicit edge_type (10 VERIFIED → COMPILE, 4 cycle-split edges → RUNTIME). The remaining 136 are UNKNOWN — Phase 1 inventory §7 Gap 9 recommends a default heuristic (UNKNOWN → COMPILE for Hub→Core, UNKNOWN → RUNTIME for Hub→Hub service-call patterns) but this hasn't been applied.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 685: "136 declared edges have edge_type = UNKNOWN"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §7 (lines 678-692): decomposition of UNKNOWN edges + default heuristic
  - Phase 1 inventory §7 Gap 9: original analysis
- **Impact:** Phase 3 HUB-BUILD-ORDER.md derivation is deferred per SAAI (per HUB-DECLARED-DAG line 692): "The Phase 3 HUB-BUILD-ORDER.md derivation (deferred per SAAI) will need to apply this heuristic to convert UNKNOWN → COMPILE for the build-order analysis (Kahn's algorithm requires an edge_type-tagged DAG; the COMPILE subgraph is what build-order topologically sorts)."
- **Remediation:** Apply the default heuristic to all 136 UNKNOWN edges, OR have blueprint authors add explicit edge_type annotations to each Upward/Downward entry. The heuristic is faster; the explicit annotation is more correct.

### S-032 — Gap 2: 11 relocated Hub→Runtime edges preserved as historical declarations in source blueprints

- **ID:** S-032
- **Category:** Governance
- **Severity:** HIGH
- **Description:** Per ADR-021 §12, HUB-10 (Queue) and HUB-25 (Chronos) are SUPERSEDED in the Hub tier and relocated to Runtime tier as RUNTIME-03 and RUNTIME-04. 11 declared edges point to HUB-10 (8 edges) and HUB-25 (3 edges). HUB-DECLARED-DAG.md §4 (line 355-369) records these as "Relocated to Runtime-tier DAG (pending)" with their new target. But the source Hub blueprints (HUB-09, HUB-12, HUB-14, HUB-17, HUB-18, HUB-20, HUB-23, HUB-30, HUB-31) still reference HUB-10 / HUB-25 in their Upward / Direct Hub sections. HUB-DECLARED-DAG line 369 explicitly states: "The historical declarations in the source Hub blueprints are NOT edited — the original prose remains ('HUB-10' still appears in HUB-09.md line 59 etc.)".
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §4 lines 344-369: 11 edges relocated table
  - `Architecture/Hub/HUB-09.md` line 59: still references HUB-10 (per HUB-DECLARED-DAG line 357)
  - `Architecture/Hub/HUB-12.md` line 72: still references HUB-10
  - `Architecture/Hub/HUB-14.md` line 83: still references HUB-10
  - `Architecture/Hub/HUB-17.md` line 101: still references HUB-10
  - `Architecture/Hub/HUB-18.md` line 107: still references HUB-10
  - `Architecture/Hub/HUB-20.md` line 118: still references HUB-25
  - `Architecture/Hub/HUB-23.md` line 137: still references HUB-10 and HUB-25
  - `Architecture/Hub/HUB-30.md` line 169: still references HUB-10
  - `Architecture/Hub/HUB-31.md` line 174: still references HUB-10 and HUB-25
- **Impact:** Source blueprints contradict the DAG. A future Runtime-tier DAG has not been created to absorb these 11 edges. Future readers of HUB-09.md see "HUB-10" in Upward — but HUB-10 is SUPERSEDED; they have to know to look at the DAG file to find the redirection to RUNTIME-03.
- **Remediation:** Either (a) edit each source Hub blueprint to replace "HUB-10" with "RUNTIME-03" (and "HUB-25" with "RUNTIME-04") in their Upward / Direct Hub sections, or (b) author the Runtime-tier DAG (`Architecture/Runtime/RUNTIME-DECLARED-DAG.md` + `RUNTIME-VERIFIED-DAG.md`) to absorb these 11 edges as Hub→Runtime INTEGRATION edges.

### S-033 — HUB-32 pending canonical publication (no blueprint file exists)

- **ID:** S-033
- **Category:** Governance
- **Severity:** HIGH
- **Description:** ADR-021 §13 ratified HUB-32 (AI Inference Hub) on 2026-09-30. The decision is ratified; the canonical blueprint file is deferred to the implementation phase. The HUB-32.md blueprint file does NOT exist in `Architecture/Hub/` (verified). Multiple documents (ADR-021, INDEX.md, HUB-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md) reference HUB-32 as "ratified pending canonical publication" — but no blueprint exists. This triggers architecture-lint failures (S-001) and creates an "inferred edges" problem (CORE-CAPABILITY-DAG.md §4.2 infers HUB-32's capability edges from `ELQ-ANALYSIS-6` because the blueprint doesn't exist to verify against).
- **Evidence:**
  - `Architecture/Hub/` directory listing: NO `HUB-32.md` file (HUB-01.md through HUB-31.md exist, but no HUB-32.md)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 254: "The HUB-32 blueprint file does not yet exist in `Architecture/Hub/`."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 66: "HUB-32 (AI Inference Hub) is **ratified pending canonical publication** — no blueprint file exists; the inventory tracks it but it appears as a future addition. Excluded from this DAG until the file lands."
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 398: "HUB-32's blueprint does not yet exist; the edges below are inferred from the `ELQ-ANALYSIS-6` cherry-pick analysis"
- **Impact:** Architecture-lint fails (S-001). HUB-32 is excluded from HUB-DECLARED-DAG (active Hub count = 29, not 30). CORE-CAPABILITY-DAG has inferred (not verified) edges for HUB-32. The "ratified pending canonical publication" state is a holding pattern that must resolve before the next lap.
- **Remediation:** Author `Architecture/Hub/HUB-32.md` (AI Inference Hub blueprint) per ADR-021 §13 + the inferred edges from `ELQ-ANALYSIS-6`. Then add HUB-32 to the architecture-lint expected structural list (line 175: extend `range(1, 30)` to `range(1, 32)`). Then re-derive HUB-DECLARED-DAG to include HUB-32's declared edges.

---

## Category 5: Architectural Coherence (MEDIUM/LOW)

### S-034 — HUB-VERIFIED-DAG.md + HUB-DECLARED-DAG.md reference nonexistent CORE-DEPENDENCY-DAG.md

- **ID:** S-034
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** The Core DAG file was renamed from `CORE-DEPENDENCY-DAG.md` to `CORE-VERIFIED-DAG.md` in PR #287 (Amendment 1, two-DAG governance model). ADR-021 line 344 explicitly notes the rename. But two Hub DAG files still reference the OLD filename as if it exists:
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 114 (inside Mermaid block): "Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for the canonical Core DAG)"
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 150 (body): "Blue-filled box = Core target (referenced; canonical Core DAG is in `Architecture/Core/CORE-DEPENDENCY-DAG.md`)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 414 (inside Mermaid block): "Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for canonical Core DAG)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 662 (body): "Blue-filled box = Core target (canonical Core DAG is `Architecture/Core/CORE-DEPENDENCY-DAG.md`)"
- **Evidence:** Direct grep results — see Description.
- **Impact:** Readers following the Hub DAGs' references land on a 404 (the file doesn't exist under that name). Cross-tier navigation is broken.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` in both Hub DAG files (4 occurrences total). Keep the historical mention in ADR-021 line 344 ("renamed from CORE-DEPENDENCY-DAG.md").

### S-035 — CORE-VERIFIED-DAG.md footer says "End of CORE-DEPENDENCY-DAG.md" (stale footer)

- **ID:** S-035
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** `CORE-VERIFIED-DAG.md` line 657 (footer): "*End of CORE-DEPENDENCY-DAG.md. See sibling documents `CORE-CAPABILITY-DAG.md` and `CORE-BUILD-ORDER.md` for the capability-edge view and the topological-wave build order.*" — the footer still uses the OLD filename. The file is now CORE-VERIFIED-DAG.md, not CORE-DEPENDENCY-DAG.md.
- **Evidence:** `Architecture/Core/CORE-VERIFIED-DAG.md` line 657
- **Impact:** Cosmetic — readers see the old filename in the footer and may think the file is misnamed. Confusion.
- **Remediation:** Change "End of CORE-DEPENDENCY-DAG.md" → "End of CORE-VERIFIED-DAG.md".

### S-036 — CORE-CAPABILITY-DAG.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)

- **ID:** S-036
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** `CORE-CAPABILITY-DAG.md` references the old filename `CORE-DEPENDENCY-DAG.md` in 3 places (body + footer):
  - Line 421: "...BRIDGE-01's Core consumption is documented in CORE-DEPENDENCY-DAG.md §3..."
  - Line 425: "...the indirect chain `C07 → C11 → C12 → H12/H26` is in the dependency DAG (`CORE-DEPENDENCY-DAG.md §4`)..."
  - Line 475: "*End of CORE-CAPABILITY-DAG.md. See sibling documents `CORE-DEPENDENCY-DAG.md` (typed-edge DAG) and `CORE-BUILD-ORDER.md` (topological waves).*"
- **Evidence:** Direct grep results — see Description.
- **Impact:** Same as S-035 — readers follow stale references.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` (3 occurrences).

### S-037 — CORE-BUILD-ORDER.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)

- **ID:** S-037
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** `CORE-BUILD-ORDER.md` references the old filename `CORE-DEPENDENCY-DAG.md` in 3 places:
  - Line 279: "...The DAG in §4 of `CORE-DEPENDENCY-DAG.md` shows all 45..."
  - Line 319: "...use the 45-edge declared DAG from `CORE-DEPENDENCY-DAG.md` §4..."
  - Line 325: "*End of CORE-BUILD-ORDER.md. See sibling documents `CORE-DEPENDENCY-DAG.md` (typed-edge DAG) and `CORE-CAPABILITY-DAG.md` (capability edges).*"
- **Evidence:** Direct grep results — see Description.
- **Impact:** Same as S-035/S-036.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` (3 occurrences).

### S-038 — Core DAGs use pre-Amendment-2 edge model (OPTIONAL as edge_type, not requiredness)

- **ID:** S-038
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** ADR-021 Amendment 2 (PR #288, 2026-10-01) refined the edge dimension model: OPTIONAL moved from `edge_type` to a separate `requiredness` dimension. The Core DAGs (CORE-VERIFIED-DAG.md, CORE-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md) were created in PR #287 (Amendment 1, 2026-09-30) — BEFORE Amendment 2. They still treat OPTIONAL as an edge_type:
  - `CORE-VERIFIED-DAG.md` line 24-31: legend lists "Edge type: COMPILE / RUNTIME / INTEGRATION / OPTIONAL"
  - `CORE-CAPABILITY-DAG.md` line 8: "5 typed-edge categories per APP-MODEL-REFINEMENT-5's restored edge typing: COMPILE / RUNTIME / INTEGRATION / CAPABILITY / OPTIONAL"
  - The HUB-DECLARED-DAG.md and HUB-VERIFIED-DAG.md (created 2026-10-01 after Amendment 2) correctly use edge_type + requiredness as separate dimensions
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` §1 legend (lines 24-31)
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 8: 5 typed-edge categories including OPTIONAL
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 64-101: per-edge table has separate `Edge Type` and `Requiredness` columns
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` Amendment 2 status: "made 7 scoped amendments"
- **Impact:** Core DAGs and Hub DAGs use INCOMPATIBLE edge dimension models. Cross-tier derivation tooling cannot process them uniformly. The Core DAGs are out of sync with the ADR-021 Amendment 2 model.
- **Remediation:** Update all 4 Core DAG files: remove OPTIONAL from edge_type legend; add a separate requiredness dimension; re-tag all OPTIONAL edges as `edge_type: COMPILE/RUNTIME/INTEGRATION/CAPABILITY, requiredness: OPTIONAL`.

### S-039 — Core DAGs have 11-field per-blueprint master table; Hub DAGs have per-edge columns (structural asymmetry)

- **ID:** S-039
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** Core DAGs use an 11-field per-blueprint master table (CORE-VERIFIED-DAG.md §3 — one block per Core blueprint with 11 fields like "ID / capability", "Declared dependencies", "Dependency type per edge", etc.). Hub DAGs use per-edge tables (HUB-VERIFIED-DAG.md §2 — one row per edge with columns: Source | Target | Edge Type | Requiredness | Gates | Verification evidence). The two representations are structurally different — tooling to process them uniformly would need different parsers.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` §3 line 64: "11-field master table — one row per Core blueprint"
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 64: "| # | Source | Target | Edge Type | Requiredness | Gates | Verification evidence |"
- **Impact:** Generator scripts (planned for SDLC v4.0) need to handle two different schemas. Cross-tier drift detection requires adapters. Not blocking, but architecturally inconsistent.
- **Remediation:** Either (a) re-author Core DAGs to use per-edge tables (matching Hub DAGs), or (b) re-author Hub DAGs to use per-blueprint master tables (matching Core DAGs). Option (a) is more aligned with the edge dimension model — each edge has 5 dimensions, which fit naturally into per-edge columns.

### S-040 — Core has CAPABILITY-DAG + BUILD-ORDER; Hub only has VERIFIED-DAG + DECLARED-DAG (missing CAPABILITY + BUILD-ORDER for Hub)

- **ID:** S-040
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** The Core tier has 4 DAG files: CORE-VERIFIED-DAG.md, CORE-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md. The Hub tier has only 2 DAG files: HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md. The Hub tier is missing HUB-CAPABILITY-DAG.md (Core→Hub capability edges would be redundant with CORE-CAPABILITY-DAG.md, but Hub→Spoke capability edges don't exist yet) and HUB-BUILD-ORDER.md (Phase 3, deferred per SAAI per HUB-DECLARED-DAG.md line 692).
- **Evidence:**
  - `Architecture/Core/` directory: 4 DAG files (CORE-VERIFIED-DAG.md, CORE-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md)
  - `Architecture/Hub/` directory: 2 DAG files (HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md) + 31 blueprint files (HUB-01..31.md)
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 692: "The Phase 3 HUB-BUILD-ORDER.md derivation (deferred per SAAI)..."
- **Impact:** Hub tier lacks the build-order artifact that Core has. Phase 3 build order derivation deferred. Tech lead has no Hub-tier topological waves to gate Hub implementation work.
- **Remediation:** Author `Architecture/Hub/HUB-BUILD-ORDER.md` after resolving governance decisions S-027 through S-032 (apply UNKNOWN → COMPILE heuristic, resolve Gap 1, enumerate Gap 5, etc.). HUB-CAPABILITY-DAG.md is optional (Hub→Spoke capability edges can wait for Spoke-tier DAG derivation).

### S-041 — CORE-CAPABILITY-DAG.md "Status: DRAFT" banner is stale (file is committed to Architecture/Core/)

- **ID:** S-041
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** `CORE-CAPABILITY-DAG.md` line 5: "**Status:** DRAFT — saved to `/home/z/my-project/download/` for tech-lead review before commit to `Architecture/Core/`." But the file IS at `Architecture/Core/CORE-CAPABILITY-DAG.md` (committed in PR #287). The DRAFT banner is stale — the document has been committed and is authoritative per ADR-021 line 54 (which lists it as a companion doc).
- **Evidence:**
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 5: "Status: DRAFT — saved to /home/z/my-project/download/"
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 54: lists `Core/CORE-CAPABILITY-DAG.md` as a companion doc
- **Impact:** Readers think the document is still in draft / awaiting review — but it's been ratified as a companion to ADR-021. Undermines its authority.
- **Remediation:** Change line 5 to: "**Status:** Authoritative (ratified as a companion to ADR-021 per INDEX.md §1 line 54)."

### S-042 — HUB-DECLARED-DAG.md lists Hub nodes including RUNTIME-03 / RUNTIME-04 references (cross-tier edge placement)

- **ID:** S-042
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** HUB-DECLARED-DAG.md §4 (lines 344-369) records 11 "relocated edges" with `New Target (Runtime-tier)` = `RUNTIME-03` or `RUNTIME-04`. But the DAG's node set (§1, line 28) only includes "29 active Hub blueprints" — RUNTIME-03 and RUNTIME-04 are NOT Hub nodes (they're Runtime-tier). The table mixes Hub-tier source nodes with Runtime-tier target nodes — which is technically a cross-tier integration inventory, not a Hub-tier DAG edge set.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §1 line 28: "Node set (29 active Hub blueprints)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §4 line 355-369: 11 relocated edges with `New Target (Runtime-tier)` = `RUNTIME-03` / `RUNTIME-04`
- **Impact:** The DAG file mixes tiers. Per ADR-021 §11, "the Hub DAG only contains Hub-internal + Hub→Core edges" — these 11 cross-tier Hub→Runtime edges belong in either the Runtime-tier DAG (incoming edges) or a separate cross-tier integration inventory, NOT the Hub DAG.
- **Remediation:** Author `Architecture/Runtime/RUNTIME-DECLARED-DAG.md` and `RUNTIME-VERIFIED-DAG.md` to absorb these 11 edges as incoming Hub→Runtime INTEGRATION edges. Remove the 11 relocated edges from HUB-DECLARED-DAG.md (or replace with a one-paragraph pointer to the Runtime DAG).

### S-043 — Lint script's PREFIXES list excludes RUNTIME (added in ADR-021 but never integrated)

- **ID:** S-043
- **Category:** Coherence
- **Severity:** MEDIUM
- **Description:** `Architecture/Verification/lint/run.php` line 30: `private const PREFIXES = ['CORE', 'HUB', 'ISPOKE', 'ESPOKE', 'BRIDGE', 'DEPLOY'];` — RUNTIME is NOT in the list. ADR-021 §12 introduced the Runtime tier (RUNTIME-01..04). Multiple files reference RUNTIME-NN (HUB-10.md, HUB-25.md, ADR-021, INDEX.md, HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md). The lint's regex `/\b(CORE|HUB|ISPOKE|ESPOKE|BRIDGE|DEPLOY)-(\d{1,3})\b/` does NOT match `RUNTIME-NN` references — so the lint silently ignores them. This means the lint would not catch a typo like "RUNTIME-99" or a reference to a nonexistent "RUNTIME-05".
- **Evidence:**
  - `Architecture/Verification/lint/run.php` line 30: PREFIXES list (no RUNTIME)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 43: introduces RUNTIME-01..04
  - 6 files reference RUNTIME-NN (per Cat 1a analysis)
- **Impact:** Lint provides false sense of safety for RUNTIME references. If a future ADR ratifies RUNTIME-05, the lint won't catch references until the structural list is updated. Latent coverage gap.
- **Remediation:** Add `'RUNTIME'` to the PREFIXES array (line 30) and add a `range(1, 4)` entry for RUNTIME in `buildValidIds()` (line 58). Optionally extend `checkStructure()` (line 169) to expect `Runtime/RUNTIME-0X.md` files (which don't exist yet — until Runtime-tier blueprints are authored).

### S-044 — ADR-021 line 344 references "renamed from CORE-DEPENDENCY-DAG.md" (historical, but echoes old filename)

- **ID:** S-044
- **Category:** Coherence
- **Severity:** LOW
- **Description:** ADR-021 line 344: "| `Architecture/Core/CORE-VERIFIED-DAG.md` | **NEW** (renamed from `CORE-DEPENDENCY-DAG.md`) — 13-edge verified implementation DAG. |" — this is a historical mention, technically OK. But it echoes the old filename, which can confuse readers/searches.
- **Evidence:** `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 344
- **Impact:** Cosmetic — readers may search for `CORE-DEPENDENCY-DAG.md` and not find it.
- **Remediation:** Leave as-is (historical mention) OR rephrase to "(originally authored as CORE-DEPENDENCY-DAG.md, renamed 2026-10-01 per two-DAG model)".

### S-045 — INDEX.md §1 line 50 says "ADR-001..010 | 10 Accepted" — accurate, but ambiguous about totals

- **ID:** S-045
- **Category:** Coherence
- **Severity:** LOW
- **Description:** INDEX.md §1 line 50: "| `Architecture/ADRs/ADR-001..010` | 10 Accepted Architecture Decision Records |" — this is accurate for ADR-001..010 (all 10 are Accepted). But it doesn't acknowledge that the total Accepted count is 18 (per S-016 analysis). A casual reader sees "10 Accepted" and thinks the project has 10 Accepted ADRs total — when actually 8 more (ADR-012, 013, 014, 017, 018, 019, 020, 021) are also Accepted and listed individually below.
- **Evidence:** `Architecture/INDEX.md` line 50
- **Impact:** Cosmetic — readers may undercount Accepted ADRs.
- **Remediation:** Change line 50 to: "| `Architecture/ADRs/ADR-001..010` | 10 of 18 Accepted ADRs (the original decathlon; 8 more Accepted ADRs listed individually below) |"

### S-046 — INDEX.md §1 line 56 says "1 Proposed ADR (HUB-31)" — but there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)

- **ID:** S-046
- **Category:** Coherence
- **Severity:** LOW
- **Description:** INDEX.md §1 line 56: "| `Architecture/ADRs/ADR-011` | 1 **Proposed** ADR (HUB-31) — not accepted, not counted |" — this only counts ADR-011 as Proposed. But ADR-015 (hospitality vertical promotion) is also Proposed (per line 55), and ADR-016 (library-app boundary split) is also Proposed (per the ADR file's own status). So there are 3 Proposed ADRs total, not 1.
- **Evidence:**
  - `Architecture/INDEX.md` line 56: "1 Proposed ADR (HUB-31)"
  - `Architecture/INDEX.md` line 55: ADR-015 Proposed
  - `Architecture/ADRs/ADR-016-library-app-boundary-split.md` line 3: "Status: Proposed"
- **Impact:** Cosmetic — readers may undercount Proposed ADRs.
- **Remediation:** Either update line 56 to mention all 3 Proposed ADRs explicitly, OR remove the count and rely on the individual row entries.

### S-047 — INCONSISTENCIES.md #8 still flagged "critical" but CORE-02 is implemented (stale)

- **ID:** S-047
- **Category:** Coherence
- **Severity:** LOW
- **Description:** `Architecture/Verification/INCONSISTENCIES.md` line 107-112: "#8 — `CORE-02` (DI Container) is an empty stub — Flagged as the top build-blocking dependency." But CORE-02 is now implemented (per INDEX.md §2.1 line 95: "Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance"). The INCONSISTENCIES.md entry is stale — it still says "Flagged critical" with status "Flagged critical" instead of "Resolved".
- **Evidence:**
  - `Architecture/Verification/INCONSISTENCIES.md` line 107-112: #8 "Flagged as the top build-blocking dependency"
  - `Architecture/INDEX.md` line 95: CORE-02 "Implemented + tested, v1.0.0, 97.2% coverage"
- **Impact:** Stale inconsistency record. Readers think CORE-02 is still blocking.
- **Remediation:** Update INCONSISTENCIES.md #8 status to "Resolved (PR #127, 2026-09-18) — CORE-02 implemented + tested + PSR-11 conformance".

---

## Summary of Shortcomings by Severity

### FATAL (4)
- S-001: architecture-lint fails on HUB-32 references (5 files)
- S-002: architecture-lint fails on ESPOKE-19 references (3 files)
- S-003: kernel PHPUnit testConcurrentFibersObserveIndependentPulseState fails (Container pulse() global state leak)
- S-004: kernel PHPUnit testCompletedFiberStateIsNotVisibleToNewFiber fails (same root cause)

### HIGH (25)
- S-005: README says PHP 8.3 (actual 8.4)
- S-006: README says "8 Core-tier packages" (actual 12)
- S-007: README says "20 ADRs" (actual 21)
- S-008: README doesn't mention ADR-021 / two-DAG / HUB-32 / ESPOKE-19
- S-009: README says "PHPUnit 10.5" (actual 11.0)
- S-010: DEPLOY-01 describes PHP-FPM + Nginx + Supervisor (should be FrankenPHP per ADR-017)
- S-011: DEPLOY-01 doesn't mention runtime substrate (Anvil v3)
- S-012: preload.php references 9 nonexistent classes
- S-013: preload.php path resolution is broken (won't load existing classes either)
- S-014: INDEX.md §1 line 45 active Hub count contradiction
- S-015: INDEX.md §1 line 60 says CORE-02 is "stub only" — contradicts §2.1
- S-016: INDEX.md §1 missing 5 ADR entries (ADR-016 through ADR-020)
- S-017: INDEX.md §5.2 still contains old Mermaid DAG (superseded but content not collapsed)
- S-018: INDEX.md §5.3 still contains 11-step build sequence (superseded but content still present)
- S-019: INDEX.md §4 vs §5.2 criticality inconsistency (different 10-Hub subsets)
- S-023: C04↔C05 PSR-4 namespace collision still present
- S-024: C17 forward-declaration stub still exists
- S-025: ADR-021 §21 line 296 documents "H05/H07 Rate Limiter duplication" but HUB-05 is RBAC
- S-027: Gap 1 — HUB-06→HUB-11 "Queue" label vs INDEX §2.2 (Cloud Storage)
- S-028: Gap 3 — HUB-15 "reverse Downward" placement inconsistency (source unchanged)
- S-029: Gap 5 — HUB-16 Downward is generic
- S-030: Gap 8 — 26 asymmetric downward-only declarations (blueprint drift)
- S-031: Gap 9 — 136 of 150 declared edges have edge_type = UNKNOWN
- S-032: Gap 2 — 11 relocated Hub→Runtime edges preserved as historical declarations in source blueprints
- S-033: Gap 7 — HUB-32 pending canonical publication (no blueprint file exists)

### MEDIUM (14)
- S-020: README "Build order (INDEX.md §5)" table is stale (superseded by ADR-021)
- S-021: README "Prerequisites" line 147 lists ext-pcre instead of ext-mbstring + ext-xml
- S-022: README says "8 of 8 Milestone 0 blueprints shipped" — but actually 9+ were shipped
- S-026: Old `generate-architecture-baseline.py` v1 script still exists alongside v2
- S-034: HUB-VERIFIED-DAG.md + HUB-DECLARED-DAG.md reference nonexistent CORE-DEPENDENCY-DAG.md
- S-035: CORE-VERIFIED-DAG.md footer says "End of CORE-DEPENDENCY-DAG.md" (stale)
- S-036: CORE-CAPABILITY-DAG.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)
- S-037: CORE-BUILD-ORDER.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)
- S-038: Core DAGs use pre-Amendment-2 edge model (OPTIONAL as edge_type, not requiredness)
- S-039: Core DAGs have 11-field per-blueprint master table; Hub DAGs have per-edge columns (structural asymmetry)
- S-040: Core has CAPABILITY-DAG + BUILD-ORDER; Hub only has VERIFIED-DAG + DECLARED-DAG (missing CAPABILITY + BUILD-ORDER for Hub)
- S-041: CORE-CAPABILITY-DAG.md "Status: DRAFT" banner is stale
- S-042: HUB-DECLARED-DAG.md lists Hub nodes including RUNTIME-03 / RUNTIME-04 references (cross-tier edge placement)
- S-043: Lint script's PREFIXES list excludes RUNTIME (added in ADR-021 but never integrated)

### LOW (4)
- S-044: ADR-021 line 344 references "renamed from CORE-DEPENDENCY-DAG.md" (historical, but echoes old filename)
- S-045: INDEX.md §1 line 50 says "ADR-001..010 | 10 Accepted" — accurate, but ambiguous about totals
- S-046: INDEX.md §1 line 56 says "1 Proposed ADR (HUB-31)" — but there are 3 Proposed ADRs
- S-047: INCONSISTENCIES.md #8 still flagged "critical" but CORE-02 is implemented (stale)

---

## Recommended Remediation Order

1. **Fix the 4 FATAL CI blockers first (S-001 through S-004):**
   - Author `Architecture/Hub/HUB-32.md` + `Architecture/Spoke/External/ESPOKE-19.md` and extend lint `validIds` to include HUB-32 and ESPOKE-19. (resolves S-001, S-002, and S-033)
   - Fix `Container::pulse()` to be per-Fiber (store pulse-scoped definitions in `WeakMap<Fiber, array<string, ServiceDefinition>>` instead of global `$definitions`). (resolves S-003, S-004)
2. **Then address HIGH doc drift in README.md (S-005 through S-009):** single PR updating PHP version, package count, ADR count, ADR-021 mention, PHPUnit version.
3. **Then address HIGH doc drift in INDEX.md (S-014 through S-019):** reconcile the Hub count contradiction, the CORE-02 status contradiction, add 5 missing ADR rows, collapse §5.2 and §5.3 to summaries + archive pointers, reconcile §4 vs §5.2 criticality.
4. **Then address HIGH doc drift in DEPLOY-01.md (S-010, S-011):** rewrite to FrankenPHP + Anvil v3.
5. **Then fix preload.php (S-012, S-013):** reference actual classes; fix path resolution.
6. **Then resolve the 7 governance decisions (S-027 through S-033):** this is a multi-PR effort requiring tech-lead decisions on each Gap.
7. **Then address latent defects (S-023 through S-026):** the C04↔C05 namespace split is a future ADR; the C17 stub is waiting on CORE-17 implementation; the ADR-021 §21 stale claim and the v1 baseline script can be cleaned up immediately.
8. **Then address coherence issues (S-034 through S-043):** find-replace old filename references; update Core DAGs to Amendment 2 model; align Core/Hub DAG schemas; author Hub CAPABILITY/BUILD-ORDER; fix lint PREFIXES.
9. **Finally, cleanup LOW items (S-044 through S-047).**

**Total effort estimate:** ~15-20 PRs if scoped tightly (1-2 files per PR). The 4 FATAL items should be done first as a single focused PR each (per the established PR pattern of #287-#292).

---

## Appendix: Files Inspected

- `/home/z/my-project/worklog.md` (1427 lines — full read)
- `/home/z/my-project/Architecture/Verification/lint/run.php` (full read — 248 lines)
- `/home/z/my-project/Architecture/Verification/lint/architecture-lint.yml` (full read)
- `/home/z/my-project/Architecture/Verification/INCONSISTENCIES.md` (full read)
- `/home/z/my-project/Architecture/INDEX.md` (full read — 536 lines)
- `/home/z/my-project/Architecture/ADRs/ADR-021-tier-stratified-build-order.md` (selected sections)
- `/home/z/my-project/Architecture/Hub/HUB-DECLARED-DAG.md` (selected sections — 705+ lines)
- `/home/z/my-project/Architecture/Hub/HUB-VERIFIED-DAG.md` (selected sections — 216 lines)
- `/home/z/my-project/Architecture/Hub/HUB-05.md` (full read)
- `/home/z/my-project/Architecture/Hub/HUB-07.md` (selected sections)
- `/home/z/my-project/Architecture/Hub/HUB-10.md` (SUPERSEDED banner — line 1)
- `/home/z/my-project/Architecture/Hub/HUB-15.md` (selected lines)
- `/home/z/my-project/Architecture/Hub/HUB-16.md` (selected lines)
- `/home/z/my-project/Architecture/Hub/HUB-25.md` (SUPERSEDED banner — line 1)
- `/home/z/my-project/Architecture/Core/CORE-VERIFIED-DAG.md` (selected sections — 658 lines)
- `/home/z/my-project/Architecture/Core/CORE-DECLARED-DAG.md` (header only)
- `/home/z/my-project/Architecture/Core/CORE-CAPABILITY-DAG.md` (selected sections)
- `/home/z/my-project/Architecture/Core/CORE-BUILD-ORDER.md` (selected sections)
- `/home/z/my-project/Architecture/Deploy/DEPLOY-01.md` (selected sections — ~600 lines)
- `/home/z/my-project/README.md` (full read — 225 lines)
- `/home/z/my-project/composer.json` (root, full read)
- `/home/z/my-project/packages/core/kernel/composer.json` (full read)
- `/home/z/my-project/packages/core/kernel/src/Kernel.php` (full read — 685 lines)
- `/home/z/my-project/packages/core/kernel/src/KernelException.php` (selected sections)
- `/home/z/my-project/packages/core/kernel/src/Stub/ProviderRegistryInterface.php` (full read — 40 lines)
- `/home/z/my-project/packages/core/kernel/src/Stub/EmptyProviderRegistry.php` (full read — 30 lines)
- `/home/z/my-project/packages/core/kernel/tests/Unit/KernelStateMachineTest.php` (full read — 932 lines)
- `/home/z/my-project/packages/core/kernel/tests/Unit/TestKernelFactory.php` (full read — 121 lines)
- `/home/z/my-project/packages/core/kernel/tests/Integration/HelloWorldTest.php` (full read — 207 lines)
- `/home/z/my-project/packages/core/kernel/tests/Integration/WorkerContaminationTest.php` (full read — 399 lines)
- `/home/z/my-project/packages/core/kernel/phpstan.neon` (full read)
- `/home/z/my-project/packages/core/kernel/phpunit.xml.dist` (full read)
- `/home/z/my-project/packages/core/kernel/ci/run.php` (full read)
- `/home/z/my-project/packages/core/container/src/Container.php` (selected sections — 504 lines)
- `/home/z/my-project/packages/core/http-message/composer.json` (selected lines)
- `/home/z/my-project/packages/core/middleware/composer.json` (selected lines)
- `/home/z/my-project/anvil/app/php/preload.php` (full read — 55 lines)
- `/home/z/my-project/scripts/generate-architecture-baseline.py` (header only)
- `/home/z/my-project/scripts/generate-architecture-baseline-v2.py` (header only)
- `/home/z/my-project/scripts/generate-architecture-baseline.php` (header only)
- `/home/z/my-project/.github/workflows/packages-ci.yml` (full read — 156 lines)
- `/home/z/my-project/.github/workflows/architecture-lint.yml` (full read)
- `/home/z/my-project/.github/workflows/architecture-boundary-lint.yml` (selected sections)
- `/home/z/my-project/.github/workflows/architecture-fitness.yml` (selected sections)
- `/home/z/my-project/download/HUB-EDGE-INVENTORY.md` (selected sections — 756 lines)

---

*End of SHORTCOMINGS-AUDIT.md. Saved to `/home/z/my-project/download/SHORTCOMINGS-AUDIT.md` (gitignored, local-only artifact). Worklog entry appended separately.*
