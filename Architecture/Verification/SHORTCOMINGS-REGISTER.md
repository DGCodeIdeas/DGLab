# DGLab Architecture Integrity Register


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This verification/audit document is a **starting point, not a complete inventory**. The number of findings found is not the number of findings that exist. The audit was conducted by a single auditor with systematic blind spots (runtime-only issues, cross-tier drift, architectural assumptions, missing tests, auditor biases, unknown unknowns). **No audit is declared complete.** Findings are closed only when their verification condition passes — not when code changes. See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Created:** 2026-10-01
**Source:** SHORTCOMINGS-AUDIT-76 (47 findings)
**Status:** Living document — findings are closed only when their verification condition passes

## Paradigm Shift (Tech-Lead Directive)

> "We're no longer optimizing for making the roadmap move. We're optimizing for making every architectural claim true before allowing the roadmap to move."

This register is the canonical ledger of every architectural claim that is currently false. The roadmap does not advance while any FATAL finding remains Open. The roadmap does not advance past the current lap while any HIGH finding in the current phase's scope remains Open. Findings are closed only when their stated verification condition passes — not when code or docs are edited.

## Closure Rule

A finding is closed only after its stated verification condition passes. Code changes alone do not close a finding. CI green ≠ closed.

Closure requires three things, all recorded in the `Closure evidence` field:
1. A reference to the commit/PR that applied the remediation.
2. A reference to the verification artifact (CI run URL, test name + output, lint output, doc grep result, etc.) that proves the verification condition passes.
3. The date the verification condition passed.

Findings marked `Fixed` (code changed, verification pending) are NOT closed. They are awaiting verification.

## Disposition Values

- **Open** — identified, not yet addressed
- **In-Progress** — remediation underway
- **Fixed** — code/doc changed, verification pending
- **Closed** — verification condition passed, closure evidence recorded
- **Accepted** — tech lead accepts the risk (won't fix)
- **Deferred** — postponed to a future phase

## Summary

| Severity | Count | Open | Closed |
|---|---|---|---|
| FATAL | 4 | 4 | 0 |
| HIGH | 25 | 25 | 0 |
| MEDIUM | 14 | 14 | 0 |
| LOW | 4 | 4 | 0 |
| **Total** | **47** | **47** | **0** |

## Phase Assignments

| Phase | Finding IDs | Description |
|---|---|---|
| A0 | S-003, S-004 | Runtime isolation specification (per-Fiber state model — spec must land before fix) |
| A1 | S-001, S-002, S-033 | Canonical HUB-32 / ESPOKE-19 publication (depth 1, implementation deferred) + lint extension + INDEX update to reflect canonical status |
| A2 | S-003, S-004 | Fiber isolation remediation (Container::pulse() → per-Fiber WeakMap) — after A0 spec lands |
| A3 | (all) | Full re-audit: every finding in this register re-verified against HEAD at that time |
| HIGH-batch | S-005..S-033 | Documentation drift + latent defects + governance (29 findings, including 4 MEDIUM-severity stragglers in the same ID range for batch efficiency) |
| MEDIUM-batch | S-034..S-043 | Coherence fixes (10 findings) |
| LOW-backlog | S-044..S-047 | Cleanup (4 findings) |

---

## Findings

### S-001: architecture-lint fails on HUB-32 references outside code blocks
- **Severity:** FATAL
- **Category:** CI
- **Description:** ADR-021 §13 ratified "HUB-32 AI Inference Hub" but the lint script declares `HUB => range(1, 30)` plus an explicit `HUB-31` allow. `HUB-32` is not in `validIds`. Multiple committed files reference `HUB-32` in regular markdown body text (outside fenced code blocks, which the lint strips), triggering an "undefined reference" error on every push and PR.
- **Evidence:**
  - `Architecture/Verification/lint/run.php` line 60 (`HUB => range(1, 30)`)
  - `Architecture/Verification/lint/run.php` line 72 (explicit `HUB-31` allow)
  - `Architecture/Verification/lint/run.php` line 89 (`preg_replace('/```.*?```/s', '', $text)` strips fenced code blocks)
  - Files with `HUB-32` outside code blocks:
    - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` lines 45, 250, 252, 254, 258, 308, 348, 364
    - `Architecture/INDEX.md` lines 5, 45, 54, 225
    - `Architecture/Hub/HUB-DECLARED-DAG.md` lines 66, 702, 705
    - `Architecture/Core/CORE-CAPABILITY-DAG.md` lines 4, 16, 30, 86, 396, 398, 400, 409, 419
    - `Architecture/Core/CORE-BUILD-ORDER.md` lines 254, 256, 318
- **Affected artifact:** `Architecture/Verification/lint/run.php` (the `validIds` map); `Architecture/Hub/HUB-32.md` (does not exist — see S-033)
- **Contract violated:** ADR-021 §13 (HUB-32 ratification) is not reflected in the lint's `validIds` map; the lint is supposed to be the mechanical enforcer of ADR ratifications per the SDLC governance model.
- **Root cause:** When ADR-021 ratified HUB-32 (and ESPOKE-19) on 2026-09-30, the lint's `validIds` map was never extended. The "publish canonical blueprint before extending lint" pattern was followed loosely — the ratification landed without the canonical blueprint file, leaving the lint with no blueprint to point at and no `validIds` entry to allow the references.
- **Remediation:** Per tech-lead decision: HUB-32 and ESPOKE-19 become canonical at depth 1 (implementation deferred). (a) Publish minimal canonical `Architecture/Hub/HUB-32.md` blueprint at depth 1 (charter, scope, declared edges — full implementation spec deferred); (b) publish minimal canonical `Architecture/Spoke/External/ESPOKE-19.md` at depth 1; (c) extend `run.php` line 60 `HUB => range(1, 30)` to include `HUB-32`, and line 62 `ESPOKE => range(1, 18)` to include `ESPOKE-19`; (d) update `INDEX.md` to reflect canonical status (not pending) for both.
- **Verification test:** `Architecture/Verification/lint/run.php` exits 0 on `main` HEAD; `architecture-lint` CI workflow passes on a PR that touches `Architecture/**`. Grep verification: `rg "HUB-32" Architecture/` returns hits only from (i) the canonical blueprint file and (ii) inside fenced code blocks.
- **Disposition:** Open
- **Owner:** main agent (with tech-lead sign-off on depth-1 canonical status)
- **Target phase:** A1
- **Closure evidence:** (empty)

### S-002: architecture-lint fails on ESPOKE-19 references outside code blocks
- **Severity:** FATAL
- **Category:** CI
- **Description:** ADR-021 §14 ratified "ESPOKE-19 Eloq" but the lint script declares `ESPOKE => range(1, 18)`. `ESPOKE-19` is not in `validIds`. Multiple committed files reference `ESPOKE-19` in regular markdown body text, triggering the same "undefined reference" error as S-001.
- **Evidence:**
  - `Architecture/Verification/lint/run.php` line 62 (`ESPOKE => range(1, 18)`)
  - Files with `ESPOKE-19` outside code blocks:
    - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` lines 46, 256, 258, 260, 274, 349, 364
    - `Architecture/INDEX.md` lines 5, 47, 54
    - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 409
- **Affected artifact:** `Architecture/Verification/lint/run.php`; `Architecture/Spoke/External/ESPOKE-19.md` (does not exist)
- **Contract violated:** ADR-021 §14 (ESPOKE-19 ratification) is not reflected in the lint's `validIds` map.
- **Root cause:** Same as S-001 — the ratification landed without the canonical blueprint file, leaving the lint with no `validIds` entry to allow the references.
- **Remediation:** Per tech-lead decision: ESPOKE-19 becomes canonical at depth 1 (implementation deferred). (a) Publish minimal canonical `Architecture/Spoke/External/ESPOKE-19.md` blueprint at depth 1; (b) extend `run.php` line 62 to include `ESPOKE-19`; (c) update `INDEX.md` to reflect canonical status (not pending).
- **Verification test:** `architecture-lint` CI workflow passes on a PR that touches `Architecture/**`. Grep verification: `rg "ESPOKE-19" Architecture/` returns hits only from the canonical blueprint and inside code blocks.
- **Disposition:** Open
- **Owner:** main agent (with tech-lead sign-off on depth-1 canonical status)
- **Target phase:** A1
- **Closure evidence:** (empty)

### S-003: kernel PHPUnit testConcurrentFibersObserveIndependentPulseState fails (Container::pulse() global state leak)
- **Severity:** FATAL
- **Category:** CI
- **Description:** `WorkerContaminationTest::testConcurrentFibersObserveIndependentPulseState` expects `Container::pulse()` to register a per-Fiber value. The actual implementation mutates a GLOBAL definition (`$this->definitions[$id]`) and calls `invalidatePulseInstances($id)` which iterates ALL Fibers' cached values and wipes the entry. When Fiber B's `pulse()` runs after Fiber A's `pulse()` + `Fiber::suspend()`, Fiber A's pulse scope is destroyed. When Fiber A resumes and calls `make()`, it resolves to Fiber B's instance, not its own.
- **Evidence:**
  - Test: `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` lines 60-142
  - Container defect: `packages/core/container/src/Container.php` line 134 (`pulse()` — global mutation, not per-Fiber)
  - Container cache invalidation: `packages/core/container/src/Container.php` lines 391-400 (`invalidatePulseInstances` — iterates all Fibers, wipes pulse-scoped cache for the re-bound id)
- **Affected artifact:** `packages/core/container/src/Container.php` (`pulse()` + `invalidatePulseInstances()`)
- **Contract violated:** ADR-017 (Fiber-based cooperative runtime) and SPEC §42 (`WeakMap<Fiber, ...> → pulse() → request/Fiber-scoped instances`). The Container's `pulse()` is supposed to be Fiber-scoped per the ratified architecture; it is currently process-global.
- **Root cause:** `Container::pulse()` was implemented with a single shared `$definitions` array rather than a `WeakMap<Fiber, array<string, ServiceDefinition>>`. The cache invalidation helper iterates ALL Fibers' caches because the definition is global — there is no per-Fiber definition store to invalidate against. This is a fundamental state-model error, not a simple bug.
- **Remediation:** Phase A0: specify the per-Fiber state model (which data structures hold pulse-scoped definitions, how `WeakMap<Fiber, ...>` eviction works, what happens to pulse-scoped instances when a Fiber completes, what `invalidatePulseInstances` becomes). Phase A2: implement the spec — make `Container::pulse()` per-Fiber (store pulse-scoped definitions in `WeakMap<Fiber, array<string, ServiceDefinition>>`); rewrite `invalidatePulseInstances()` to only touch the calling Fiber's cache; update `make()` to consult the per-Fiber definition store first.
- **Verification test:** (a) `packages/core/kernel/tests/Integration/WorkerContaminationTest.php::testConcurrentFibersObserveIndependentPulseState` passes; (b) `packages/core/kernel/tests/Integration/WorkerContaminationTest.php::testCompletedFiberStateIsNotVisibleToNewFiber` passes (S-004); (c) a new unit test `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` asserting that two Fibers calling `pulse()` for the same id with different instances each get their own instance back, with explicit `Fiber::suspend()` interleavings; (d) `PHPUnit + PHPStan (core/kernel)` CI check passes on `main`.
- **Disposition:** Open
- **Owner:** tech lead (A0 spec) → subagent (A2 fix)
- **Target phase:** A0 (spec) → A2 (fix)
- **Closure evidence:** (empty)

### S-004: kernel PHPUnit testCompletedFiberStateIsNotVisibleToNewFiber fails (same Container defect)
- **Severity:** FATAL
- **Category:** CI
- **Description:** `WorkerContaminationTest::testCompletedFiberStateIsNotVisibleToNewFiber` expects that after Fiber A completes (its pulse-scoped state is GC'd via WeakMap eviction), a new Fiber B calling `make()` without setting its own pulse scope should NOT see A's state. But the global definition side-effect persists: Fiber A's `pulse(RequestContext::class, $contextA)` call sets `definitions[RequestContext::class].concrete = $contextA` globally. After Fiber A completes and is `unset()`, the WeakMap cache for Fiber A is evicted — but the global definition persists. Fiber B's `make()` finds no cache for itself, falls through to `build($contextA)` (which returns the object as-is), and caches `$contextA` for Fiber B. The assertion `assertNull($contextFromRequestB)` fails.
- **Evidence:**
  - Test: `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` lines 281-314
  - Container defect (same as S-003): `packages/core/container/src/Container.php` lines 134-151
- **Affected artifact:** `packages/core/container/src/Container.php` (same as S-003)
- **Contract violated:** ADR-017 (Fiber-based cooperative runtime) + SPEC §42 (WeakMap eviction semantics).
- **Root cause:** Same as S-003. The global definition store means a completed Fiber's `pulse()` side-effect outlives the Fiber. Per-Fiber state must be stored per-Fiber for the WeakMap eviction to actually clear the pulse-scoped binding.
- **Remediation:** Same as S-003 — A0 spec → A2 fix. Once `pulse()` is per-Fiber, Fiber A's binding is stored in `WeakMap[Fiber A]` and is evicted when Fiber A is GC'd; Fiber B's `make()` finds no definition and either resolves the underlying factory or throws — but it does not see A's instance.
- **Verification test:** `packages/core/kernel/tests/Integration/WorkerContaminationTest.php::testCompletedFiberStateIsNotVisibleToNewFiber` passes; CI `PHPUnit + PHPStan (core/kernel)` passes.
- **Disposition:** Open
- **Owner:** tech lead (A0 spec) → subagent (A2 fix)
- **Target phase:** A0 (spec) → A2 (fix)
- **Closure evidence:** (empty)

### S-005: README.md says PHP 8.3 (actual is PHP ^8.4)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `README.md` lines 1, 144 (Prerequisites), and 188 (Built with) all say "PHP 8.3". The actual `composer.json` (root + all 12 package composer.json files) declares `"php": "^8.4"`. The CI workflow `packages-ci.yml` line 114 uses `php-version: '8.4'`. The architecture-lint workflow `architecture-lint.yml` line 24 still uses `php-version: '8.3'` (stale relative to the actual requirement).
- **Evidence:**
  - `README.md` line 1: "A from-scratch PHP 8.3 application framework"
  - `README.md` line 144: "PHP 8.3+"
  - `README.md` line 188: "PHP 8.3 | Runtime + all packages"
  - `composer.json` line 7: `"php": "^8.4"`
  - `packages/core/kernel/composer.json` line 8: `"php": "^8.4"`
  - `.github/workflows/architecture-lint.yml` line 24: `php-version: '8.3'`
- **Affected artifact:** `README.md`; `.github/workflows/architecture-lint.yml`
- **Contract violated:** The README is the canonical entry-point document; its Prerequisites section is supposed to match the actual `composer.json` constraint. The architecture-lint workflow is supposed to use the same PHP version as `packages-ci.yml`.
- **Root cause:** The PHP version was bumped from 8.3 to 8.4 across all `composer.json` files but the README and architecture-lint workflow were never updated. Drift went undetected because no lint rule enforces README ↔ composer.json consistency.
- **Remediation:** Find-replace "PHP 8.3" → "PHP 8.4" in README.md (3 occurrences); bump `architecture-lint.yml` line 24 from `'8.3'` to `'8.4'`.
- **Verification test:** `rg "PHP 8\.3" README.md .github/workflows/` returns zero matches; `architecture-lint` workflow runs on PHP 8.4 and passes.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-006: README.md says "8 Core-tier packages" (actual is 12 on disk)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `README.md` line 11 claims "**8 Core-tier packages**". The actual `packages/core/` directory contains 12 packages: container, event-dispatcher, http-message, middleware, router, config, logger, error-handler, kernel, dbal, crypto, filesystem. The README was written at Milestone-0 completion (depth-2 of the original 8) and never updated when CORE-18 Kernel, CORE-19 DBAL, CORE-14 Filesystem, and CORE-16 Encryption were added.
- **Evidence:**
  - `README.md` line 11: "8 Core-tier packages"
  - `README.md` lines 53-60: structural listing shows only 8 packages
  - `packages/core/` directory listing: 12 packages (config, container, crypto, dbal, error-handler, event-dispatcher, filesystem, http-message, kernel, logger, middleware, router)
- **Affected artifact:** `README.md`
- **Contract violated:** README structural listing is supposed to match the on-disk package layout (canonical inventory contract).
- **Root cause:** README was authored at Milestone-0 completion and never refreshed when later Core packages (CORE-14/16/18/19) were implemented. No lint rule enforces README ↔ `packages/core/` directory consistency.
- **Remediation:** Update README.md line 11 to "12 Core-tier packages"; add the 4 missing entries (kernel, dbal, crypto, filesystem) to the structure tree at lines 53-60.
- **Verification test:** README.md line 11 reads "12 Core-tier packages"; the structure tree at lines 53-60 lists all 12 packages found in `packages/core/`; `ls packages/core/ | wc -l` equals the count in README.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-007: README.md says "20 ADRs" (actual is 21 ADRs)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `README.md` line 12 ("20 Architecture Decision Records") and line 64 ("ADRs/ # 20 Architecture Decision Records") both say 20. The actual `Architecture/ADRs/` directory contains 21 ADR files (ADR-001 through ADR-021). ADR-021 was added in PR #287 (Amendment 1) and amended in PR #288 (Amendment 2).
- **Evidence:**
  - `README.md` line 12: "**20 Architecture Decision Records**"
  - `README.md` line 64: "ADRs/ # 20 Architecture Decision Records"
  - `Architecture/ADRs/` directory listing: 21 ADR files (ADR-001..021)
- **Affected artifact:** `README.md`
- **Contract violated:** README ADR count is supposed to match the ADR directory inventory (canonical inventory contract).
- **Root cause:** README was authored before ADR-021 was ratified; the count was never refreshed.
- **Remediation:** Update README.md line 12 to "21 Architecture Decision Records" and line 64 to "ADRs/ # 21 Architecture Decision Records".
- **Verification test:** README.md line 12 reads "21 Architecture Decision Records"; `ls Architecture/ADRs/ADR-*.md | wc -l` equals 21; counts match.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-008: README.md does not mention ADR-021 / two-DAG governance model / HUB-32 / ESPOKE-19
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `README.md` "Key design decisions" table (lines 129-138) lists ADR-001, 005, 014, 017, 018, 019 — but omits ADR-021 (the tier-stratified build order + two-DAG governance model + Eligible(X) admission rule + 4-edge-type dimension model + multigraph semantics + HUB-32/ESPOKE-19 ratification + HUB-10/HUB-25 relocation to Runtime tier). This is the most architecturally consequential ADR of the last 2 laps and is invisible in the README. The README also says "102 component blueprints" (line 13) which is pre-ADR-021 count — relocations + pending ratifications don't change the canonical 102 count per INDEX.md §4, but the README doesn't acknowledge the relocations.
- **Evidence:**
  - `README.md` lines 129-138: ADR table missing ADR-021 row
  - `README.md` line 13: "102 component blueprints"
  - `README.md` line 66: "Hub/ # 31 Hub-tier blueprints" (should mention 2 SUPERSEDED + canonical HUB-32)
- **Affected artifact:** `README.md`
- **Contract violated:** README "Key design decisions" table is supposed to surface every Accepted ADR; ADR-021 is Accepted and is missing.
- **Root cause:** README was last meaningfully updated around ADR-019; ADR-021's ratification (PR #287) and amendment (PR #288) were never reflected in the README.
- **Remediation:** Add an ADR-021 row to the "Key design decisions" table; update the structure tree's Hub/ line to note SUPERSEDED + canonical HUB-32; add a paragraph in "What is this?" describing the two-DAG governance model and the Runtime tier.
- **Verification test:** README.md "Key design decisions" table includes an ADR-021 row; `rg "ADR-021" README.md` returns at least one match outside the structural inventory line.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-009: README.md says "PHPUnit 10.5" (actual is PHPUnit ^11.0)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `README.md` line 192 says "PHPUnit 10.5 | Testing". The actual root `composer.json` line 41 declares `phpunit/phpunit: ^11.0`, and all 12 package `composer.json` files declare `^11.0`. PHPUnit 11 has breaking changes from 10.x (different test attributes, different assertion APIs in places).
- **Evidence:**
  - `README.md` line 192: "PHPUnit 10.5 | Testing"
  - `composer.json` line 41: `"phpunit/phpunit": "^11.0"`
  - `packages/core/kernel/composer.json` line 24: `"phpunit/phpunit": "^11.0"`
- **Affected artifact:** `README.md`
- **Contract violated:** README "Built with" table is supposed to match `composer.json` dev requirements.
- **Root cause:** PHPUnit was bumped to 11.0 across the repo but the README was never updated.
- **Remediation:** Update README.md line 192 to "PHPUnit 11.x | Testing".
- **Verification test:** README.md line 192 reads "PHPUnit 11.x"; `composer.json` dev requirement matches the README statement.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-010: DEPLOY-01.md describes PHP-FPM + Nginx + Supervisor (should be FrankenPHP per ADR-017)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `Architecture/Deploy/DEPLOY-01.md` describes a deployment based on "PHP-FPM 8.3, Nginx, Supervisor" — but ADR-017 (ratified 2026-09-23) ratifies "FrankenPHP 1.12" as the application server. The README itself acknowledges this: "FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017)". The deployment blueprint contradicts the ratified ADR.
- **Evidence:**
  - `Architecture/Deploy/DEPLOY-01.md` line 7: "shared PHP-FPM + Nginx + Supervisor base"
  - `Architecture/Deploy/DEPLOY-01.md` line 15: "bundles PHP-FPM 8.3, Nginx, Supervisor"
  - `Architecture/Deploy/DEPLOY-01.md` line 29: "Runtime: PHP 8.3-FPM, Nginx 1.27, Supervisor 4, Alpine 3.20"
  - `Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md` (ratified)
  - `README.md` line 188: "FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017)"
- **Affected artifact:** `Architecture/Deploy/DEPLOY-01.md`
- **Contract violated:** ADR-017 (FrankenPHP as the ratified application server). Per doctrine, where a blueprint and an ADR disagree, the ADR wins.
- **Root cause:** DEPLOY-01.md was authored before ADR-017 was ratified; the ADR was never propagated to the deployment blueprint.
- **Remediation:** Rewrite DEPLOY-01.md to use FrankenPHP (replacing PHP-FPM + Nginx + Supervisor with FrankenPHP + Caddy + Anvil v3 substrate). Or split DEPLOY-01 into two blueprints: legacy PHP-FPM (deprecated) and FrankenPHP (canonical).
- **Verification test:** `rg "PHP-FPM|Nginx|Supervisor" Architecture/Deploy/DEPLOY-01.md` returns zero matches (or only historical/deprecation mentions); `rg "FrankenPHP" Architecture/Deploy/DEPLOY-01.md` returns matches describing the canonical runtime.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-011: DEPLOY-01.md does not mention the runtime substrate (Anvil v3)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** ADR-021 §12 introduces a new "Runtime" tier with `RUNTIME-01..04` (Anvil v3 = Caddy + Tengine + FrankenPHP, systemd timers, Queue Worker relocated from HUB-10, Chronos relocated from HUB-25). DEPLOY-01.md should describe how the deployment blueprint relates to the runtime substrate (Anvil v3), but it doesn't mention Anvil v3 or the Runtime tier at all. The deployment blueprint predates the ADR-021 Runtime tier ratification.
- **Evidence:**
  - `Architecture/Deploy/DEPLOY-01.md` — no mention of "Anvil v3" or "Runtime tier" or "RUNTIME-01..04" anywhere in the file
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 43: Runtime tier = "Anvil v3 (Caddy+Tengine+FrankenPHP), systemd timers, Queue Worker (relocated from HUB-10), Chronos (relocated from HUB-25)"
  - `Architecture/INDEX.md` line 144: HUB-10 SUPERSEDED → RUNTIME-03; HUB-25 SUPERSEDED → RUNTIME-04
- **Affected artifact:** `Architecture/Deploy/DEPLOY-01.md`
- **Contract violated:** ADR-021 §12 (Runtime tier + Anvil v3 substrate).
- **Root cause:** DEPLOY-01.md was authored before ADR-021 ratified the Runtime tier; the ADR was never propagated to the deployment blueprint.
- **Remediation:** Update DEPLOY-01.md to reference the Runtime tier (Anvil v3) as the substrate; or split DEPLOY-01 into DEPLOY-01 (FrankenPHP service images) and a new RUNTIME-01 (Anvil v3 substrate) blueprint.
- **Verification test:** `rg "Anvil v3|Runtime tier|RUNTIME-0" Architecture/Deploy/DEPLOY-01.md` returns at least one match describing the substrate.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-012: anvil/app/php/preload.php references 9 nonexistent classes
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `anvil/app/php/preload.php` lines 27-41 list 11 classes to opcache-preload. Of these, only 2 actually exist on disk: `SovereignStack\Core\Http\Request` and `SovereignStack\Core\Http\Response` (both in `packages/core/http-message/src/`). The other 9 are nonexistent: `SovereignStack\Core\Contracts\PulseInterface`, `SchedulerInterface`, `TenantScopeInterface`; `SovereignStack\Core\Fiber\Pulse`, `Scheduler`; `SovereignStack\Core\Middleware\Pipeline` (real name is `SovereignStack\Core\Http\MiddlewarePipeline`); `SovereignStack\Core\Http\Kernel` (real namespace is `SovereignStack\Core\Kernel\Kernel`); `SovereignStack\Hub\Hub`; `SovereignStack\Hub\Registry`.
- **Evidence:**
  - `anvil/app/php/preload.php` lines 27-41: `$preload_classes = [...]`
  - Filesystem: only `packages/core/kernel/src/Kernel.php` (namespace `SovereignStack\Core\Kernel`, not `SovereignStack\Core\Http`)
  - SDLC-AUDIT-1 worklog (Task 68) flagged this issue
- **Affected artifact:** `anvil/app/php/preload.php`
- **Contract violated:** ADR-010 (opcache preloading) — preload is supposed to preload hot-path classes; preloading nonexistent classes is a no-op and provides a false sense of optimization.
- **Root cause:** The preload list was authored speculatively against planned class names (Fiber\Pulse, Hub\Hub, etc.) that were never implemented under those namespaces. When CORE-18 (Kernel) landed, its namespace was `SovereignStack\Core\Kernel` not `SovereignStack\Core\Http`; preload was never corrected.
- **Remediation:** Update preload.php to reference the actual class names: `SovereignStack\Core\Http\MiddlewarePipeline`, `SovereignStack\Core\Kernel\Kernel`. Remove references to nonexistent Contracts/*, Fiber/*, Hub/*, Hub\Registry classes (or implement them). Also fix the path resolution bug (S-013).
- **Verification test:** Every class in `$preload_classes` resolves to an existing file via the Composer PSR-4 autoloader; a smoke test that runs `preload.php` and dumps `get_included_files()` shows all 11 classes preloaded (not silently skipped).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-013: anvil/app/php/preload.php path resolution is broken (won't load existing classes either)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** `preload.php` lines 44-54 resolve class names to file paths by string-replacing `SovereignStack\` and `\` with `/`, then looking under `<releaseRoot>/packages/core/src/` or `packages/hub/src/`. This resolves `SovereignStack\Core\Http\Request` → `/Core/Http/Request.php` and looks for `<releaseRoot>/packages/core/src/Core/Http/Request.php`. But the actual file is at `packages/core/http-message/src/Request.php` (the PSR-4 root is `packages/core/http-message/src/`). The preload path does not exist — `is_file()` returns false, the class is silently skipped.
- **Evidence:**
  - `anvil/app/php/preload.php` lines 43-55
  - `packages/core/http-message/composer.json` line 27: `"SovereignStack\\Core\\Http\\": "src/"` (PSR-4 root: `packages/core/http-message/src/`)
- **Affected artifact:** `anvil/app/php/preload.php`
- **Contract violated:** ADR-010 (opcache preloading).
- **Root cause:** The path-resolution logic was written assuming a flat `packages/<tier>/<package>/src/` layout (single src dir per tier), but the actual layout is `packages/<tier>/<package-name>/src/` (per-package src dir, registered individually in each package's composer.json). The two layouts are incompatible.
- **Remediation:** Use the Composer autoloader's `vendor/autoload.php` to resolve class-to-file mappings, or hardcode the correct paths per package. Better: use `opcache_compile_file()` with paths derived from each package's composer.json PSR-4 mappings.
- **Verification test:** Run `preload.php` against a built release; `get_included_files()` post-preload includes the expected Request/Response/MiddlewarePipeline/Kernel classes; no `is_file()` returns false for any entry in `$preload_classes`.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-014: INDEX.md §1 line 45 active Hub count contradiction ("29 + 1 pending = 30")
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §1 line 45 reads: "active Hub count = 31 − 2 relocated = 29 + 1 pending = 30". The arithmetic conflates "active" with "active + pending". HUB-32 (pending canonical publication, no blueprint file) should NOT be counted in the active inventory. Per ADR-021 §13 line 254: "INDEX.md still says 31 Hubs. This is intentional — the decision is ratified; the canonical blueprint is deferred to the implementation phase." Per INDEX.md §4 line 218: "31 declared (29 active + 2 superseded)" — that's 29 active, NOT 30. §1 line 45 contradicts §4 line 218.
- **Evidence:**
  - `Architecture/INDEX.md` line 45: "active Hub count = 31 − 2 relocated = 29 + 1 pending = 30"
  - `Architecture/INDEX.md` line 218: "31 declared (29 active + 2 superseded)" — 29 active, no mention of pending in the count
  - `Architecture/INDEX.md` line 225: "HUB-32 AI Inference Hub ratified pending canonical publication (not yet counted in active inventory)"
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** INDEX.md §1 promises "single source of truth"; §1 line 45 contradicts §4 line 218 within the same document.
- **Root cause:** When HUB-32 was ratified without a blueprint, the §1 count was edited to add "+1 pending = 30" — but §4 was not edited to match, and the arithmetic conflates "active" with "active + pending".
- **Remediation:** Change line 45 to: "active Hub count = 31 − 2 superseded = 29 active; HUB-32 ratified pending canonical publication (not counted in active inventory until blueprint lands)". Once S-001/S-033 close (HUB-32 becomes canonical), the line updates to "30 active".
- **Verification test:** INDEX.md §1 line 45 and §4 line 218 use the same active Hub count number; `rg "29 \+ 1 pending = 30" Architecture/INDEX.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (but contingent on S-001/S-033 for the post-canonical-publication count update)
- **Closure evidence:** (empty)

### S-015: INDEX.md §1 line 60 says CORE-02 is "stub only (.gitkeep)" — contradicts §2.1
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §1 line 60: "`packages/core/container/` | CORE-02 reference implementation — **stub only (`.gitkeep`)**; full spec in `Core/CORE-02.md`". But §2.1 line 95 says: "CORE-02 | Dependency Injection Container | `SovereignStack\Core\Container` | `packages/core/container/` | ✅ **Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance**". The §1 line 60 description is from the pre-MUWV era (pre-2026-09-23) when CORE-02 was a stub. It was implemented in PR #127 (per README.md line 209) — but §1 line 60 was never updated.
- **Evidence:**
  - `Architecture/INDEX.md` line 60: "stub only (.gitkeep)"
  - `Architecture/INDEX.md` line 95: "Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance"
  - `Architecture/Verification/INCONSISTENCIES.md` lines 107-112: "#8 — CORE-02 (DI Container) is an empty stub — **Flagged as the top build-blocking dependency.**" — INCONSISTENCIES.md is also stale (still flagged critical, but CORE-02 is implemented).
- **Affected artifact:** `Architecture/INDEX.md`; `Architecture/Verification/INCONSISTENCIES.md`
- **Contract violated:** INDEX.md §1 promises "single source of truth"; §1 line 60 contradicts §2.1 line 95 within the same document.
- **Root cause:** CORE-02 was implemented in PR #127 but the §1 line 60 description was never refreshed (only §2.1 was updated when the package shipped). INCONSISTENCIES.md #8 status was also never flipped to "Resolved".
- **Remediation:** Update §1 line 60 to "CORE-02 reference implementation — **implemented + tested, v1.0.0**"; update INCONSISTENCIES.md #8 status to "Resolved (PR #127, 2026-09-18)".
- **Verification test:** INDEX.md §1 line 60 reads "implemented + tested"; INCONSISTENCIES.md #8 status reads "Resolved"; `rg "stub only" Architecture/INDEX.md` returns zero matches at line 60.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-016: INDEX.md §1 missing 5 ADR entries (ADR-016 through ADR-020)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §1 lists the canonical ADRs: ADR-001..010 (line 50), ADR-011 (line 56), ADR-012 (line 51), ADR-013 (line 52), ADR-014 (line 53), ADR-015 (line 55), ADR-021 (line 54). That's 16 ADRs listed. But the `Architecture/ADRs/` directory contains 21 ADR files. Missing from §1: ADR-016 (Proposed), ADR-017 (Accepted), ADR-018 (Accepted), ADR-019 (Accepted), ADR-020 (Accepted). All 5 are ratified decisions; 4 of them are Accepted; their absence from the canonical index means readers don't know they exist.
- **Evidence:**
  - `Architecture/INDEX.md` lines 50-56: lists 16 ADRs only
  - `Architecture/ADRs/` directory: 21 ADR files
  - `Architecture/ADRs/ADR-016-library-app-boundary-split.md` line 3: "Status: Proposed"
  - `Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md` line 3: "Status: Accepted"
  - `Architecture/ADRs/ADR-018-centralized-per-tier-releases.md` line 3: "Status: Accepted"
  - `Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md` line 3: "Status: Accepted"
  - `Architecture/ADRs/ADR-020-stable-and-bleeding-edge-release-channels.md` line 3: "Status: Accepted"
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** INDEX.md §1 promises "single source of truth" for the ADR inventory; the on-disk ADR directory has 5 entries not listed in §1.
- **Root cause:** ADR-016 through ADR-020 were ratified across PRs that didn't touch INDEX.md §1's canonical ADR table. Each ADR landed individually; the canonical inventory was never refreshed.
- **Remediation:** Add 5 new rows to INDEX.md §1 canonical table for ADR-016, ADR-017, ADR-018, ADR-019, ADR-020 with their status and one-line description.
- **Verification test:** INDEX.md §1 lists 21 ADRs (ADR-001..021); `ls Architecture/ADRs/ADR-*.md | wc -l` equals the count in §1; each ADR has a row in §1.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-017: INDEX.md §5.2 still contains the old Mermaid DAG (superseded but content not collapsed)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §5.2 (lines 281-407) contains the full 18-edge monolithic Mermaid DAG. A SUPERSEDED banner was added at §5 (lines 251-262) declaring the section superseded by ADR-021 per-tier DAGs — but the content was NOT collapsed/removed. The full 126-line Mermaid block remains, including the "selected critical" Hub subset (10 Hubs: H01, H02, H03, H04, H06, H08, H11, H15, H19, H20) which is inconsistent with §4's Critical set (10 Hubs: H01, 02, 04, 05, 08, 09, 10, 19, 20, 21).
- **Evidence:**
  - `Architecture/INDEX.md` lines 251-262: SUPERSEDED banner
  - `Architecture/INDEX.md` lines 281-407: full Mermaid DAG content still present (not collapsed)
  - `Architecture/INDEX.md` lines 327-336: §5.2 selected critical = {H01, H02, H03, H04, H06, H08, H11, H15, H19, H20}
  - `Architecture/INDEX.md` line 235: §4 Critical = {H01, 02, 04, 05, 08, 09, 10, 19, 20, 21}
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** ADR-021 §11 (CORE-BUILD-ORDER.md is the canonical build-order artifact post-Amendment-1); the SUPERSEDED banner is insufficient — content must be collapsed.
- **Root cause:** When ADR-021 was ratified, the SUPERSEDED banner was added but the §5.2 Mermaid content was preserved "for historical reference". This was a half-measure — the content stays bloated and continues to contradict §4.
- **Remediation:** Replace the §5.2 Mermaid block with a one-paragraph summary + link to `Architecture/Core/CORE-VERIFIED-DAG.md` and `Architecture/Hub/HUB-DECLARED-DAG.md`. Move the full Mermaid to `Architecture/Archive/INDEX-5.2-snapshot-2026-09-29.md`.
- **Verification test:** INDEX.md §5.2 is ≤10 lines (1-paragraph summary + link); the archived snapshot exists at `Architecture/Archive/INDEX-5.2-snapshot-2026-09-29.md` and contains the original Mermaid; `rg "H03.*H04.*H06.*H08.*H11.*H15" Architecture/INDEX.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-018: INDEX.md §5.3 still contains the 11-step build sequence (superseded but content still present)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §5.3 (lines 409-445) contains the full 11-step global build sequence with effort estimates and "parallelizable" labels. A SUPERSEDED banner was added at line 411 — but the content was NOT collapsed/removed. Step 8 line 434 says "Hub tier (30 blueprints)" which is stale (post-ADR-021: 31 declared, 29 active, 1 pending).
- **Evidence:**
  - `Architecture/INDEX.md` line 411: SUPERSEDED banner
  - `Architecture/INDEX.md` lines 425-437: full 11-step table still present
  - `Architecture/INDEX.md` line 434: "Hub tier (30 blueprints)" — stale (should be 29 active or 31 declared per ADR-021)
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** ADR-021 §11 (CORE-BUILD-ORDER.md is the canonical build-order artifact post-Amendment-1).
- **Root cause:** Same as S-017 — SUPERSEDED banner was added but content was preserved. The 11-step sequence has 4 known wrong steps (per CORE-BUILD-ORDER.md §6: Step 2 false-parallelism, Step 3 inverted order, Step 5 over-cautious entry criterion, Step 6 arbitrary SuperPHP) — leaving this content in INDEX invites regression.
- **Remediation:** Replace §5.3 with a one-paragraph summary + link to `Architecture/Core/CORE-BUILD-ORDER.md`. Move the 11-step table to archive.
- **Verification test:** INDEX.md §5.3 is ≤10 lines (1-paragraph summary + link); the archived snapshot exists at `Architecture/Archive/INDEX-5.3-snapshot-2026-09-29.md`; `rg "Hub tier \(30 blueprints\)" Architecture/INDEX.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-019: INDEX.md §4 vs §5.2 criticality inconsistency (different 10-Hub subsets)
- **Severity:** HIGH
- **Category:** Doc-Drift
- **Description:** INDEX.md §4 (line 235) lists "Critical | 10 | HUB-01, 02, 04, 05, 08, 09, 10, 19, 20, 21". INDEX.md §5.2 (lines 327-336) lists a "selected critical" subset = {H01, H02, H03, H04, H06, H08, H11, H15, H19, H20} — a different 10 Hubs. ADR-021 §5 line 20 (the rationale) explicitly flags this: "4 of §4's 10 Critical Hubs (HUB-05, HUB-09, HUB-10, HUB-21) are missing from §5.2; 4 'High' Hubs are in the DAG instead."
- **Evidence:**
  - `Architecture/INDEX.md` line 235: §4 Critical = {01, 02, 04, 05, 08, 09, 10, 19, 20, 21}
  - `Architecture/INDEX.md` lines 327-336: §5.2 selected critical = {01, 02, 03, 04, 06, 08, 11, 15, 19, 20}
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 20: explicitly calls out this inconsistency
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** INDEX.md §1 promises "single source of truth"; §4 and §5.2 within the same file disagree on the Critical Hub set.
- **Root cause:** §5.2's "selected critical" subset was a pre-ADR-021 informal selection that predated §4's formal Critical classification. The two were never reconciled.
- **Remediation:** Once §5.2 content is collapsed per S-017, this contradiction is automatically resolved. Otherwise, align the §5.2 subset to §4's Critical set OR explicitly rename §5.2's subset to "build-priority subset" (not "critical").
- **Verification test:** After S-017 closes (§5.2 collapsed), `rg "selected critical" Architecture/INDEX.md` returns zero matches; only §4's Critical set is referenced as the canonical Critical Hub classification.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (auto-resolves when S-017 closes)
- **Closure evidence:** (empty)

### S-020: README.md "Build order (INDEX.md §5)" table is stale (superseded by ADR-021)
- **Severity:** MEDIUM
- **Category:** Doc-Drift
- **Description:** README.md lines 90-96 shows a "Build order" table claiming "all 8 Milestone 0 blueprints shipped" with status "✅ Complete". The §5 build sequence has been superseded by ADR-021 (per the SUPERSEDED banner in INDEX.md §5). The README table also says "8 of 8 Milestone 0 blueprints shipped" in step 4 line 95 — but this count is post-Milestone-0 and doesn't reflect the post-ADR-021 build order (4-wave topological, not 4-step).
- **Evidence:**
  - `README.md` lines 90-96: Build order table
  - `Architecture/INDEX.md` lines 251-262: §5 superseded by ADR-021
- **Affected artifact:** `README.md`
- **Contract violated:** ADR-021 §11 (CORE-BUILD-ORDER.md is the canonical build-order artifact).
- **Root cause:** README points to INDEX §5 as the source of build order — but INDEX §5 is itself superseded. The README was never updated to point to the post-ADR-021 canonical artifact.
- **Remediation:** Replace README §"Build order (INDEX.md §5)" with a one-line pointer to `Architecture/Core/CORE-BUILD-ORDER.md` (per ADR-021 §11). Remove the local 4-row table.
- **Verification test:** README.md "Build order" section is ≤3 lines (1-line pointer + heading); `rg "Milestone 0 blueprints shipped" README.md` returns zero matches at lines 90-96.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (despite MEDIUM severity — bundled with S-005..S-009 README updates)
- **Closure evidence:** (empty)

### S-021: README.md "Prerequisites" line 147 lists ext-pcre instead of ext-mbstring + ext-xml
- **Severity:** MEDIUM
- **Category:** Doc-Drift
- **Description:** README.md line 147 lists prerequisites: "`ext-mbstring`, `ext-fileinfo`, `ext-pcre`". But the CI workflow `packages-ci.yml` line 116 specifies `extensions: mbstring, xml, dom` (not pcre, not fileinfo). The actual extensions used by packages differ from the README list.
- **Evidence:**
  - `README.md` line 147: "`ext-mbstring`, `ext-fileinfo`, `ext-pcre`"
  - `.github/workflows/packages-ci.yml` line 116: `extensions: mbstring, xml, dom`
- **Affected artifact:** `README.md`
- **Contract violated:** README Prerequisites is supposed to match the CI extensions list.
- **Root cause:** README was authored with a different extensions list than what CI actually uses. Drift went undetected because no lint enforces README ↔ CI extensions consistency.
- **Remediation:** Align README's ext list with the CI list: `mbstring, xml, dom` (fileinfo is usually bundled; pcre is enabled by default).
- **Verification test:** README.md line 147 lists `mbstring, xml, dom`; the list matches `.github/workflows/packages-ci.yml` line 116.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (bundled with S-005..S-009 README updates)
- **Closure evidence:** (empty)

### S-022: README.md says "8 of 8 Milestone 0 blueprints shipped" — but actually 9+ were shipped (CORE-17 stub also shipped)
- **Severity:** MEDIUM
- **Category:** Doc-Drift
- **Description:** README.md line 95 says "**all 8 Milestone 0 blueprints shipped**" — but per README.md line 218, the "Also shipped but not in the Milestone 0 scope" includes CORE-17 (Service Providers, stub). Also, the line 218 list omits CORE-14 (Filesystem), CORE-16 (Encryption), CORE-19 (DBAL) which are implemented per `packages/core/` directory listing.
- **Evidence:**
  - `README.md` line 95: "all 8 Milestone 0 blueprints shipped"
  - `README.md` line 218: "Also shipped but not in the Milestone 0 scope: CORE-03, CORE-10, CORE-09, CORE-08, CORE-17"
  - `packages/core/` directory: 12 packages (8 Milestone-0 + 4 additional: kernel, dbal, crypto, filesystem — but README line 218 only mentions CORE-17 stub, not CORE-14/16/19)
- **Affected artifact:** `README.md`
- **Contract violated:** README "Also shipped" list is supposed to enumerate all packages outside Milestone-0 scope.
- **Root cause:** The "Also shipped" list was authored when only CORE-17 was outside Milestone-0 scope; later CORE-14/16/19 shipped without the list being refreshed.
- **Remediation:** Update README.md line 218 "Also shipped" list to: "CORE-03, CORE-10, CORE-09, CORE-08, CORE-17 (stub), CORE-14, CORE-16, CORE-19".
- **Verification test:** README.md line 218 lists CORE-14, CORE-16, CORE-19 in the "Also shipped" list; `ls packages/core/` count matches the README's combined Milestone-0 + Also-shipped count.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (bundled with S-005..S-009 README updates)
- **Closure evidence:** (empty)

### S-023: C04↔C05 PSR-4 namespace collision still present
- **Severity:** HIGH
- **Category:** Latent-Defect
- **Description:** Both `packages/core/http-message/composer.json` and `packages/core/middleware/composer.json` declare `"SovereignStack\\Core\\Http\\": "src/"` as their PSR-4 root. ADR-021 §21 line 294 documents this as a known latent defect. The remediation is "namespace split in a future ADR. The namespace root lint rule (§16) will catch this going forward." But §16 of ADR-021 doesn't actually exist (the ADR goes up to §14), and no namespace root lint rule has been implemented in `Architecture/Verification/lint/run.php`.
- **Evidence:**
  - `packages/core/http-message/composer.json` line 27: `"SovereignStack\\Core\\Http\\": "src/"`
  - `packages/core/middleware/composer.json` (similar): `"SovereignStack\\Core\\Http\\": "src/"`
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 294: documents this as known defect
  - `composer.json` (root) line 48: only registers `packages/core/http-message/src/` (not middleware/src/) for `SovereignStack\\Core\\Http\\`
- **Affected artifact:** `packages/core/http-message/composer.json`; `packages/core/middleware/composer.json`; `Architecture/Verification/lint/run.php` (missing namespace-root lint rule)
- **Contract violated:** ADR-021 §21 (known latent defects catalogue) promises a §16 namespace-root lint rule that does not exist.
- **Root cause:** Two packages were authored against the same PSR-4 prefix before PSR-4 ownership rules were enforced. The "future ADR for namespace split" was deferred and never landed. The promised §16 lint rule was never implemented — ADR-021 §16 does not exist (the ADR only goes up to §14).
- **Remediation:** Either (a) author ADR-022 to split the namespace (e.g., `SovereignStack\Core\Http\Message\` for C04, `SovereignStack\Core\Http\Middleware\` for C05 — both breaking changes), or (b) implement the namespace root lint rule (extend `run.php` to detect two packages claiming the same PSR-4 prefix). Option (b) doesn't fix the collision but stops future drift; option (a) is the proper fix but requires a major-version bump.
- **Verification test:** Either ADR-022 exists ratifying the namespace split AND the split is implemented (the two composer.json files declare distinct PSR-4 prefixes), OR `run.php` has a `checkNamespaceRoots()` function that fails the lint when two packages declare the same PSR-4 prefix.
- **Disposition:** Open
- **Owner:** tech lead (decision on ADR-022 vs. lint-only mitigation)
- **Target phase:** HIGH-batch (decision required; implementation may defer to next MUWV)
- **Closure evidence:** (empty)

### S-024: C17 forward-declaration stub still exists (kernel/src/Stub/ProviderRegistryInterface.php + EmptyProviderRegistry.php)
- **Severity:** HIGH
- **Category:** Latent-Defect
- **Description:** `packages/core/kernel/src/Stub/ProviderRegistryInterface.php` (40 lines) and `packages/core/kernel/src/Stub/EmptyProviderRegistry.php` (30 lines) still exist as forward-declaration placeholders for CORE-17 (Service Provider System), which is unimplemented. C18's boot calls `$providerRegistry->registerAll($container)` and `$providerRegistry->bootAll($container)` (Kernel.php lines 267, 298). `EmptyProviderRegistry`'s both methods are no-ops. ADR-021 §21 line 295 documents this as a known latent defect. Per ADR-021 §6, C18's `integration_completeness` is PARTIALLY WIRED and `production_gate` requires C17.
- **Evidence:**
  - `packages/core/kernel/src/Stub/ProviderRegistryInterface.php` (40 lines, full file)
  - `packages/core/kernel/src/Stub/EmptyProviderRegistry.php` (30 lines, full file)
  - `packages/core/kernel/src/Kernel.php` line 267: `$providerRegistry->registerAll($container);` (no-op call)
  - `packages/core/kernel/src/Kernel.php` line 298: `$providerRegistry->bootAll($container);` (no-op call)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 295: documents this as known defect
  - `Architecture/INDEX.md` line 110: "CORE-17 | Service Provider System | 📝 Not started"
- **Affected artifact:** `packages/core/kernel/src/Stub/` directory; `packages/core/kernel/src/Kernel.php` lines 267, 298
- **Contract violated:** ADR-021 §6 (production_gate criteria for CORE-18 requires CORE-17 implementation); ADR-021 §21 line 295 documents this as known defect.
- **Root cause:** C18 (Kernel) was implemented before C17 (Service Providers); a stub was created so C18's boot path could compile and ship. The stub was meant to be temporary but has persisted across multiple laps because C17 implementation kept getting deferred.
- **Remediation:** Implement CORE-17 (`packages/core/providers/`) per `Architecture/Core/CORE-17.md`. Replace `EmptyProviderRegistry` with a real `ServiceProviderRegistry` that discovers and boots providers from a configured list. Delete the `Stub/` directory.
- **Verification test:** `packages/core/providers/` exists with a real `ServiceProviderRegistry`; `packages/core/kernel/src/Stub/` directory is deleted; `Kernel.php` lines 267, 298 call the real registry (verified by integration test that boots a kernel with a real provider and asserts the provider's `register()` + `boot()` methods ran); C18's `production_gate = SATISFIED` per ADR-021 §6.
- **Disposition:** Open
- **Owner:** subagent (CORE-17 implementation) + tech lead (sign-off on production_gate status)
- **Target phase:** HIGH-batch (decision required; implementation may defer to next MUWV)
- **Closure evidence:** (empty)

### S-025: ADR-021 §21 line 296 documents "H05/H07 Rate Limiter duplication" but HUB-05 is RBAC (not Rate Limiter)
- **Severity:** HIGH
- **Category:** Latent-Defect
- **Description:** ADR-021 §21 line 296 documents "H05/H07 Rate Limiter duplication" as a known latent defect: "H05 and H07 may be duplicate Rate Limiter Hubs. Tech-lead decision pending." But the actual HUB-05 blueprint is `# PHASE HUB-05: RBAC & Permission Engine` (Sovereign Guardian) — it has nothing to do with Rate Limiting. HUB-07 is `# PHASE HUB-07: Rate Limiter & Throttle Engine` (Sovereign Throttle) — that IS the rate limiter. The "duplication" claim is wrong: HUB-05 and HUB-07 are entirely different services. Either the claim was wrong from the start, or HUB-05 used to mention rate limiting and was edited.
- **Evidence:**
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 296: "H05/H07 Rate Limiter duplication"
  - `Architecture/Hub/HUB-05.md` line 1: "# PHASE HUB-05: RBAC & Permission Engine"
  - `Architecture/Hub/HUB-05.md` line 12: "Sovereign Guardian"
  - `Architecture/Hub/HUB-05.md`: grep for "Rate|Throttle|throttle|rate limit" returns NO matches
  - `Architecture/Hub/HUB-07.md` line 1: "# PHASE HUB-07: Rate Limiter & Throttle Engine"
- **Affected artifact:** `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` §21 line 296
- **Contract violated:** ADR-021 §21 (known latent defects catalogue) — the catalogue claims a defect that doesn't exist.
- **Root cause:** Either the §21 entry was wrong from the start (someone confused HUB-05 with another Hub), or HUB-05 used to mention rate limiting and was edited without the ADR-021 §21 entry being reconciled. Requires git history investigation to determine which case applies.
- **Remediation:** Investigate `git log -p Architecture/Hub/HUB-05.md` to determine whether HUB-05 ever mentioned rate limiting. Then either (a) delete ADR-021 §21 line 296 (defect doesn't exist), or (b) document the historical context (e.g., "HUB-05 originally mentioned rate limiting in v0.1; that language was removed in PR #XXX; this entry is retained as a historical note").
- **Verification test:** ADR-021 §21 line 296 either removed or annotated with the historical context; the latent defects catalogue no longer claims a defect that contradicts the actual HUB-05 blueprint.
- **Disposition:** Open
- **Owner:** tech lead (decision + git history investigation)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-026: Old `generate-architecture-baseline.py` v1 script still exists alongside v2
- **Severity:** MEDIUM
- **Category:** Latent-Defect
- **Description:** `scripts/generate-architecture-baseline.py` (v1, 15 KB, broken per SDLC-AUDIT-1) still exists alongside `scripts/generate-architecture-baseline-v2.py` (v2, 19 KB, the new authoritative version). ADR-021 line 352 only references v2 as "NEW — evidence-collection script for baseline generation." The v1 script is undocumented in ADR-021 — neither deprecated nor referenced. The companion `scripts/generate-architecture-baseline.php` (PHP version, 18 KB) also still exists with the original 2026-09-24 docstring.
- **Evidence:**
  - `scripts/generate-architecture-baseline.py` (15 KB, mtime 2026-09-24)
  - `scripts/generate-architecture-baseline-v2.py` (19 KB, mtime 2026-10-01)
  - `scripts/generate-architecture-baseline.php` (18 KB, mtime 2026-09-24)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 352: only v2 is referenced
- **Affected artifact:** `scripts/generate-architecture-baseline.py`; `scripts/generate-architecture-baseline.php`
- **Contract violated:** ADR-021 line 352 declares v2 authoritative; v1 and the PHP version are unreferenced.
- **Root cause:** When v2 was authored, the v1 and PHP versions were left in place without deprecation headers. ADR-021 was authored to reference only v2, but no cleanup was performed on the legacy scripts.
- **Remediation:** Either (a) delete `generate-architecture-baseline.py` (v1) and `generate-architecture-baseline.php` (legacy PHP version) and update ADR-021 to note the deletion, or (b) add a deprecation header to both files pointing to v2.
- **Verification test:** Only `scripts/generate-architecture-baseline-v2.py` exists in `scripts/` (or the legacy files have an explicit `# DEPRECATED — use generate-architecture-baseline-v2.py instead` header in the first 3 lines).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch (despite being latent-defect category — it's a low-effort cleanup bundled with MEDIUM-batch coherence fixes)
- **Closure evidence:** (empty)

### S-027: Gap 1: HUB-06→HUB-11 "Queue" label vs INDEX §2.2 (Cloud Storage)
- **Severity:** HIGH
- **Category:** Governance
- **Description:** HUB-06.md line 40 lists HUB-11 in its Upward section with the label "Queue". But INDEX.md §2.2 says HUB-11 = "Cloud Storage", and HUB-10 was the Queue (now SUPERSEDED → RUNTIME-03). The label is wrong: either the edge should point to HUB-10 (now RUNTIME-03), or the label "Queue" should be corrected to "Cloud Storage". ADR-021 deferred this to "Phase 2 governance decision". The HUB-DECLARED-DAG.md row 16 (line 93) records the edge with `UNKNOWN` edge_type and `UNKNOWN` requiredness, noting "Phase 1 §7 Gap 1 contradiction" — unresolved.
- **Evidence:**
  - `Architecture/Hub/HUB-06.md` line 40: "Upward: CORE-19, CORE-03, CORE-02, CORE-09, CORE-14, HUB-04, HUB-11 (labelled 'Queue' — see §7 Gap 1)"
  - `Architecture/INDEX.md` §2.2: HUB-11 = Cloud Storage
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 93: row 16 records the edge as UNKNOWN/UNKNOWN
- **Affected artifact:** `Architecture/Hub/HUB-06.md` line 40; `Architecture/Hub/HUB-DECLARED-DAG.md` row 16
- **Contract violated:** ADR-021 §5 (Hub DAG must classify every edge with edge_type + requiredness); INDEX.md §2.2 (HUB-11 = Cloud Storage).
- **Root cause:** HUB-06's Upward list was authored before HUB-11 was re-classified as Cloud Storage (or before HUB-10 was relocated to RUNTIME-03). The label "Queue" is stale. The §7 Gap 1 deferral was a holding pattern that has not been resolved.
- **Remediation:** Tech-lead decision: either (a) change HUB-06.md line 40 from "HUB-11 (Queue)" to "RUNTIME-03 (Queue)" and remove from Hub DAG, or (b) change "Queue" to "Cloud Storage" and update HUB-06 to reflect that HUB-11 is Cloud Storage.
- **Verification test:** HUB-06.md line 40 has the corrected label/target; HUB-DECLARED-DAG.md row 16 has an explicit edge_type + requiredness (no UNKNOWN); `rg "HUB-11 \(Queue\)" Architecture/Hub/` returns zero matches.
- **Disposition:** Open
- **Owner:** tech lead (decision) + main agent (execution)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-028: Gap 3: HUB-15 "reverse Downward" placement inconsistency (source blueprint unchanged)
- **Severity:** HIGH
- **Category:** Governance
- **Description:** HUB-15.md lines 89-90 declares 6 Hub→Hub edges in its **Downward** section that are semantically **Upward** (HUB-15 polls HUB-01, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20's `/health` endpoints — HUB-15 is the consumer). HUB-DECLARED-DAG.md Resolution 1 captures these as RUNTIME edges in the DAG (rows 34-40). But the SOURCE blueprint HUB-15.md is unchanged — the prose still says "Downward". Future readers of HUB-15.md see the inconsistent placement.
- **Evidence:**
  - `Architecture/Hub/HUB-15.md` lines 89-90: "Downward (reverse — see §7 Gap 3): Every Hub service (HUB-01, HUB-02, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20)..."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` lines 100-127: rows 34-40 record these as RUNTIME edges with `HUB-15 reverse-Down — Resolution 1 cycle split`
  - Phase 1 inventory §7 Gap 3: "Phase 2 recommendation: Move these 6 entries from HUB-15's Downward section to its Upward section in the blueprint."
- **Affected artifact:** `Architecture/Hub/HUB-15.md` lines 89-90
- **Contract violated:** ADR-021 §5 (Hub DAG must classify every edge with edge_type + requiredness); the source blueprint must match the DAG.
- **Root cause:** HUB-15's author placed consumer-side polling edges in the "Downward" section (semantic error). The DAG post-processed these to RUNTIME edges with a "Resolution 1 cycle split" annotation, but the source was never corrected.
- **Remediation:** Move the 6 entries from HUB-15.md Downward to Upward section. Update HUB-15.md to reflect that HUB-15 polls (consumes) those Hubs' `/health` endpoints.
- **Verification test:** HUB-15.md Upward section includes the 6 entries (HUB-01, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20); HUB-15.md Downward section no longer contains the "reverse — see §7 Gap 3" entry; HUB-DECLARED-DAG.md rows 34-40 match the corrected source.
- **Disposition:** Open
- **Owner:** tech lead (decision) + main agent (execution)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-029: Gap 5: HUB-16 Downward is generic ("every other Hub component") — not enumerable
- **Severity:** HIGH
- **Category:** Governance
- **Description:** HUB-16.md line 169 declares: "Downward: every other Hub component — this is the 'Merge Gate' for the tier per the original design intent" — too generic to enumerate. The HUB-DECLARED-DAG.md excludes this generic declaration (correctly, since it's not enumerable). But the source blueprint still has the generic text — readers don't know which Hubs HUB-16 actually consumes. Phase 1 inventory §7 Gap 5: "Phase 2 recommendation: Author HUB-16's Downward section to enumerate the specific Hub consumers (likely all 28 other active Hubs, since HUB-16 is the 'Merge Gate' — but this should be explicit in the blueprint, not inferred)."
- **Evidence:**
  - `Architecture/Hub/HUB-16.md` line ~169: "Downward: every other Hub component — this is the 'Merge Gate'..."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 115: only 1 outbound edge from HUB-16 (→ HUB-15); the generic "every other Hub" is excluded
  - Phase 1 inventory §7 Gap 5: "not enumerable"
- **Affected artifact:** `Architecture/Hub/HUB-16.md` Downward section
- **Contract violated:** ADR-021 §5 (Hub DAG requires enumerable edges; generic declarations cannot be classified).
- **Root cause:** HUB-16's "Merge Gate" role was specified generically without enumerating which Hubs it actually gates. This makes the DAG unable to classify the edges and the build-order derivation unable to sequence HUB-16.
- **Remediation:** Author HUB-16.md Downward section with explicit Hub enumeration (likely all 28 other active Hubs, or a subset that HUB-16 specifically gates).
- **Verification test:** HUB-16.md Downward section enumerates specific Hub IDs (no "every other Hub" generic text); HUB-DECLARED-DAG.md has explicit Hub→Hub rows from HUB-16 to each enumerated consumer; `rg "every other Hub" Architecture/Hub/HUB-16.md` returns zero matches.
- **Disposition:** Open
- **Owner:** tech lead (decision on the consumer set)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-030: Gap 8: 26 asymmetric downward-only declarations (blueprint drift)
- **Severity:** HIGH
- **Category:** Governance
- **Description:** 26 of 87 Hub→Hub edges (30%) are downward-only declarations: the producer acknowledges the consumer in its Downward section, but the consumer doesn't acknowledge the producer in its Upward section. This is blueprint drift — the producer knew about the consumer, but the consumer's blueprint was never updated. HUB-DECLARED-DAG.md captures all 26 in the DAG (rows with "↓-only" markers), but the source blueprints are unchanged.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 660 area: "Gap 8 — 26 asymmetric downward-only declarations (blueprint drift)"
  - `download/HUB-EDGE-INVENTORY.md` §7 Gap 8 (lines 660-672): full analysis
  - Specifically, the 6 reverse-Down edges from HUB-15 (rows 34, 36, 37, 38, 39, 40 in HUB-DECLARED-DAG) are downward-only because HUB-15's Upward list doesn't formally declare HUB-01/HUB-04/HUB-06/HUB-08/HUB-19/HUB-20 as dependencies (Gap 3 + Gap 8 combined)
- **Affected artifact:** 26 source Hub blueprints (Downward sections need consumer-side Upward reconciliation, OR producer-side Downward removal)
- **Contract violated:** ADR-021 §5 (Hub DAG requires symmetric Upward/Downward declarations).
- **Root cause:** Hub blueprints were authored independently across multiple laps. Producer-side authors added Downward declarations when they knew about consumers; consumer-side authors never added the matching Upward declarations. Drift compounded.
- **Remediation:** Reconcile each downward-only edge by either (a) adding the Upward declaration to the consumer's blueprint, or (b) removing the Downward declaration from the producer's blueprint if the edge doesn't actually exist. 26 blueprints need editing.
- **Verification test:** HUB-DECLARED-DAG.md "↓-only" marker count = 0; every Hub→Hub edge in the DAG has both an Upward entry on the consumer and a Downward entry on the producer.
- **Disposition:** Open
- **Owner:** tech lead (decision per edge) + main agent (execution)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-031: Gap 9: 136 of 150 declared edges have edge_type = UNKNOWN
- **Severity:** HIGH
- **Category:** Governance
- **Description:** HUB-DECLARED-DAG.md §7 line 685: "136 declared edges have edge_type = UNKNOWN. These are conventional Hub→Hub and Hub→Core declarations where the blueprint author did not specify the edge_type." Only 14 edges have explicit edge_type (10 VERIFIED → COMPILE, 4 cycle-split edges → RUNTIME). The remaining 136 are UNKNOWN — Phase 1 inventory §7 Gap 9 recommends a default heuristic (UNKNOWN → COMPILE for Hub→Core, UNKNOWN → RUNTIME for Hub→Hub service-call patterns) but this hasn't been applied.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 685: "136 declared edges have edge_type = UNKNOWN"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §7 (lines 678-692): decomposition of UNKNOWN edges + default heuristic
  - Phase 1 inventory §7 Gap 9: original analysis
- **Affected artifact:** 136 edge rows in `Architecture/Hub/HUB-DECLARED-DAG.md`; source Hub blueprints
- **Contract violated:** ADR-021 §5 (every edge must have an edge_type classification).
- **Root cause:** Hub blueprint authors declared edges without specifying edge_type. The DAG inventory recorded them as UNKNOWN and proposed a heuristic but the heuristic was never applied (deferred per SAAI).
- **Remediation:** Apply the default heuristic to all 136 UNKNOWN edges, OR have blueprint authors add explicit edge_type annotations to each Upward/Downward entry. The heuristic is faster; the explicit annotation is more correct.
- **Verification test:** HUB-DECLARED-DAG.md row count with `edge_type = UNKNOWN` = 0; every row has an explicit edge_type (COMPILE/RUNTIME/INTEGRATION/CAPABILITY); the build-order derivation script (when written) can process every edge without an UNKNOWN fallback.
- **Disposition:** Open
- **Owner:** tech lead (decision on heuristic vs explicit annotation) + main agent (execution)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-032: Gap 2: 11 relocated Hub→Runtime edges preserved as historical declarations in source blueprints
- **Severity:** HIGH
- **Category:** Governance
- **Description:** Per ADR-021 §12, HUB-10 (Queue) and HUB-25 (Chronos) are SUPERSEDED in the Hub tier and relocated to Runtime tier as RUNTIME-03 and RUNTIME-04. 11 declared edges point to HUB-10 (8 edges) and HUB-25 (3 edges). HUB-DECLARED-DAG.md §4 (lines 355-369) records these as "Relocated to Runtime-tier DAG (pending)" with their new target. But the source Hub blueprints (HUB-09, HUB-12, HUB-14, HUB-17, HUB-18, HUB-20, HUB-23, HUB-30, HUB-31) still reference HUB-10 / HUB-25 in their Upward / Direct Hub sections. HUB-DECLARED-DAG line 369 explicitly states: "The historical declarations in the source Hub blueprints are NOT edited — the original prose remains ('HUB-10' still appears in HUB-09.md line 59 etc.)".
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
- **Affected artifact:** 9 source Hub blueprints (HUB-09, 12, 14, 17, 18, 20, 23, 30, 31) Upward / Direct Hub sections
- **Contract violated:** ADR-021 §12 (HUB-10/HUB-25 SUPERSEDED → RUNTIME-03/RUNTIME-04); source blueprints must reflect the relocation.
- **Root cause:** ADR-021 relocated HUB-10/HUB-25 to the Runtime tier at the ratification level, but explicitly chose not to edit the source Hub blueprints ("historical declarations preserved"). The choice was made to minimize churn at ADR ratification time; the reconciliation was deferred.
- **Remediation:** Either (a) edit each source Hub blueprint to replace "HUB-10" with "RUNTIME-03" (and "HUB-25" with "RUNTIME-04") in their Upward / Direct Hub sections, or (b) author the Runtime-tier DAG (`Architecture/Runtime/RUNTIME-DECLARED-DAG.md` + `RUNTIME-VERIFIED-DAG.md`) to absorb these 11 edges as Hub→Runtime INTEGRATION edges.
- **Verification test:** Either (a) `rg "\bHUB-10\b|\bHUB-25\b" Architecture/Hub/HUB-09.md Architecture/Hub/HUB-12.md ...` returns zero matches in Upward/Direct Hub sections, OR (b) `Architecture/Runtime/RUNTIME-DECLARED-DAG.md` exists and contains the 11 edges as Hub→Runtime INTEGRATION rows.
- **Disposition:** Open
- **Owner:** tech lead (decision on (a) vs (b)) + main agent (execution)
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-033: HUB-32 pending canonical publication (no blueprint file exists)
- **Severity:** HIGH
- **Category:** Governance
- **Description:** ADR-021 §13 ratified HUB-32 (AI Inference Hub) on 2026-09-30. The decision is ratified; the canonical blueprint file is deferred to the implementation phase. The HUB-32.md blueprint file does NOT exist in `Architecture/Hub/`. Multiple documents (ADR-021, INDEX.md, HUB-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md) reference HUB-32 as "ratified pending canonical publication" — but no blueprint exists. This triggers architecture-lint failures (S-001) and creates an "inferred edges" problem (CORE-CAPABILITY-DAG.md §4.2 infers HUB-32's capability edges from `ELQ-ANALYSIS-6` because the blueprint doesn't exist to verify against).
- **Evidence:**
  - `Architecture/Hub/` directory listing: NO `HUB-32.md` file (HUB-01.md through HUB-31.md exist, but no HUB-32.md)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 254: "The HUB-32 blueprint file does not yet exist in `Architecture/Hub/`."
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 66: "HUB-32 (AI Inference Hub) is **ratified pending canonical publication** — no blueprint file exists; the inventory tracks it but it appears as a future addition. Excluded from this DAG until the file lands."
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 398: "HUB-32's blueprint does not yet exist; the edges below are inferred from the `ELQ-ANALYSIS-6` cherry-pick analysis"
- **Affected artifact:** `Architecture/Hub/HUB-32.md` (does not exist)
- **Contract violated:** ADR-021 §13 (HUB-32 ratified pending canonical publication); the canonical blueprint is the contract artifact, not the ADR ratification alone.
- **Root cause:** HUB-32 was ratified at ADR time without authoring the canonical blueprint — the project chose to defer the blueprint authoring to the implementation phase. This created the holding pattern in which HUB-32 is "ratified but not canonical".
- **Remediation:** Per tech-lead decision: HUB-32 becomes canonical at depth 1 (implementation deferred). Author `Architecture/Hub/HUB-32.md` (minimal depth-1 blueprint: charter, scope, declared Core consumers, declared Hub consumers, inferred edges from `ELQ-ANALYSIS-6`). Add HUB-32 to the architecture-lint expected structural list (line 175: extend `range(1, 30)` to include `HUB-32`). Re-derive HUB-DECLARED-DAG to include HUB-32's declared edges. Update INDEX.md to reflect canonical status (not pending). Same approach for ESPOKE-19 (S-002).
- **Verification test:** `Architecture/Hub/HUB-32.md` exists with depth-1 content; `architecture-lint` passes (resolves S-001); INDEX.md line 45 + line 218 + line 225 updated to reflect HUB-32 canonical status (count becomes "30 active"); HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active).
- **Disposition:** Open
- **Owner:** main agent (with tech-lead sign-off on depth-1 canonical status)
- **Target phase:** A1 (bundled with S-001/S-002)
- **Closure evidence:** (empty)

### S-034: HUB-VERIFIED-DAG.md + HUB-DECLARED-DAG.md reference nonexistent CORE-DEPENDENCY-DAG.md
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** The Core DAG file was renamed from `CORE-DEPENDENCY-DAG.md` to `CORE-VERIFIED-DAG.md` in PR #287 (Amendment 1, two-DAG governance model). ADR-021 line 344 explicitly notes the rename. But two Hub DAG files still reference the OLD filename as if it exists (4 references total).
- **Evidence:**
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 114: "Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for the canonical Core DAG)"
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 150: "Blue-filled box = Core target (referenced; canonical Core DAG is in `Architecture/Core/CORE-DEPENDENCY-DAG.md`)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 414: "Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for canonical Core DAG)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 662: "Blue-filled box = Core target (canonical Core DAG is `Architecture/Core/CORE-DEPENDENCY-DAG.md`)"
- **Affected artifact:** `Architecture/Hub/HUB-VERIFIED-DAG.md`; `Architecture/Hub/HUB-DECLARED-DAG.md`
- **Contract violated:** ADR-021 line 344 (rename acknowledged but references not propagated).
- **Root cause:** When the Core DAG file was renamed, the Hub DAGs that reference it were not updated. The rename was registered in ADR-021 but the cross-file references were never reconciled.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` in both Hub DAG files (4 occurrences total). Keep the historical mention in ADR-021 line 344 ("renamed from CORE-DEPENDENCY-DAG.md").
- **Verification test:** `rg "CORE-DEPENDENCY-DAG" Architecture/Hub/HUB-VERIFIED-DAG.md Architecture/Hub/HUB-DECLARED-DAG.md` returns zero matches; the only `CORE-DEPENDENCY-DAG` reference in `Architecture/` is in ADR-021 line 344 (historical mention).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-035: CORE-VERIFIED-DAG.md footer says "End of CORE-DEPENDENCY-DAG.md" (stale footer)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** `CORE-VERIFIED-DAG.md` line 657 (footer): "*End of CORE-DEPENDENCY-DAG.md. See sibling documents `CORE-CAPABILITY-DAG.md` and `CORE-BUILD-ORDER.md` for the capability-edge view and the topological-wave build order.*" — the footer still uses the OLD filename. The file is now CORE-VERIFIED-DAG.md, not CORE-DEPENDENCY-DAG.md.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` line 657
- **Affected artifact:** `Architecture/Core/CORE-VERIFIED-DAG.md` footer
- **Contract violated:** File rename (PR #287) was not propagated to the file's own footer.
- **Root cause:** The rename changed the filename but not the footer text.
- **Remediation:** Change "End of CORE-DEPENDENCY-DAG.md" → "End of CORE-VERIFIED-DAG.md".
- **Verification test:** CORE-VERIFIED-DAG.md line 657 reads "End of CORE-VERIFIED-DAG.md"; `rg "End of CORE-DEPENDENCY-DAG" Architecture/Core/` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-036: CORE-CAPABILITY-DAG.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** `CORE-CAPABILITY-DAG.md` references the old filename `CORE-DEPENDENCY-DAG.md` in 3 places (body + footer).
- **Evidence:**
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 421: "...BRIDGE-01's Core consumption is documented in CORE-DEPENDENCY-DAG.md §3..."
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 425: "...the indirect chain `C07 → C11 → C12 → H12/H26` is in the dependency DAG (`CORE-DEPENDENCY-DAG.md §4`)..."
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 475: "*End of CORE-CAPABILITY-DAG.md. See sibling documents `CORE-DEPENDENCY-DAG.md` (typed-edge DAG) and `CORE-BUILD-ORDER.md` (topological waves).*"
- **Affected artifact:** `Architecture/Core/CORE-CAPABILITY-DAG.md`
- **Contract violated:** File rename (PR #287) was not propagated.
- **Root cause:** Same as S-035 — rename changed the filename but not the cross-references.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` (3 occurrences).
- **Verification test:** `rg "CORE-DEPENDENCY-DAG" Architecture/Core/CORE-CAPABILITY-DAG.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-037: CORE-BUILD-ORDER.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** `CORE-BUILD-ORDER.md` references the old filename `CORE-DEPENDENCY-DAG.md` in 3 places.
- **Evidence:**
  - `Architecture/Core/CORE-BUILD-ORDER.md` line 279: "...The DAG in §4 of `CORE-DEPENDENCY-DAG.md` shows all 45..."
  - `Architecture/Core/CORE-BUILD-ORDER.md` line 319: "...use the 45-edge declared DAG from `CORE-DEPENDENCY-DAG.md` §4..."
  - `Architecture/Core/CORE-BUILD-ORDER.md` line 325: "*End of CORE-BUILD-ORDER.md. See sibling documents `CORE-DEPENDENCY-DAG.md` (typed-edge DAG) and `CORE-CAPABILITY-DAG.md` (capability edges).*"
- **Affected artifact:** `Architecture/Core/CORE-BUILD-ORDER.md`
- **Contract violated:** File rename (PR #287) was not propagated.
- **Root cause:** Same as S-035/S-036.
- **Remediation:** Find-replace `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` (3 occurrences).
- **Verification test:** `rg "CORE-DEPENDENCY-DAG" Architecture/Core/CORE-BUILD-ORDER.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-038: Core DAGs use pre-Amendment-2 edge model (OPTIONAL as edge_type, not requiredness)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** ADR-021 Amendment 2 (PR #288, 2026-10-01) refined the edge dimension model: OPTIONAL moved from `edge_type` to a separate `requiredness` dimension. The Core DAGs (CORE-VERIFIED-DAG.md, CORE-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md) were created in PR #287 (Amendment 1, 2026-09-30) — BEFORE Amendment 2. They still treat OPTIONAL as an edge_type. The Hub DAGs (created 2026-10-01 after Amendment 2) correctly use edge_type + requiredness as separate dimensions.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` §1 legend (lines 24-31): "Edge type: COMPILE / RUNTIME / INTEGRATION / OPTIONAL"
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 8: "5 typed-edge categories per APP-MODEL-REFINEMENT-5's restored edge typing: COMPILE / RUNTIME / INTEGRATION / CAPABILITY / OPTIONAL"
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` lines 64-101: per-edge table has separate `Edge Type` and `Requiredness` columns
- **Affected artifact:** All 4 Core DAG files (`CORE-VERIFIED-DAG.md`, `CORE-DECLARED-DAG.md`, `CORE-CAPABILITY-DAG.md`, `CORE-BUILD-ORDER.md`)
- **Contract violated:** ADR-021 Amendment 2 (edge dimension model).
- **Root cause:** Core DAGs were authored before Amendment 2; Amendment 2 amended the model but did not update the Core DAGs (only the Hub DAGs were authored post-Amendment-2 and use the new model).
- **Remediation:** Update all 4 Core DAG files: remove OPTIONAL from edge_type legend; add a separate requiredness dimension; re-tag all OPTIONAL edges as `edge_type: COMPILE/RUNTIME/INTEGRATION/CAPABILITY, requiredness: OPTIONAL`.
- **Verification test:** All 4 Core DAG files have a legend listing edge_type ∈ {COMPILE, RUNTIME, INTEGRATION, CAPABILITY} and a separate requiredness ∈ {REQUIRED, OPTIONAL}; no edge has `edge_type: OPTIONAL`; cross-tier derivation tooling can parse Core and Hub DAGs uniformly.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-039: Core DAGs have 11-field per-blueprint master table; Hub DAGs have per-edge columns (structural asymmetry)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** Core DAGs use an 11-field per-blueprint master table (CORE-VERIFIED-DAG.md §3 — one block per Core blueprint with 11 fields like "ID / capability", "Declared dependencies", "Dependency type per edge", etc.). Hub DAGs use per-edge tables (HUB-VERIFIED-DAG.md §2 — one row per edge with columns: Source | Target | Edge Type | Requiredness | Gates | Verification evidence). The two representations are structurally different — tooling to process them uniformly would need different parsers.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` §3 line 64: "11-field master table — one row per Core blueprint"
  - `Architecture/Hub/HUB-VERIFIED-DAG.md` line 64: "| # | Source | Target | Edge Type | Requiredness | Gates | Verification evidence |"
- **Affected artifact:** All 4 Core DAG files + 2 Hub DAG files
- **Contract violated:** ADR-021 §5 (edge dimension model — each edge has 5 dimensions, which fit naturally into per-edge columns but not per-blueprint master tables).
- **Root cause:** Core DAGs and Hub DAGs were authored by different passes at different times; each chose a different structural representation. No ADR-021 §5 enforcement of a single schema.
- **Remediation:** Either (a) re-author Core DAGs to use per-edge tables (matching Hub DAGs), or (b) re-author Hub DAGs to use per-blueprint master tables (matching Core DAGs). Option (a) is more aligned with the edge dimension model — each edge has 5 dimensions, which fit naturally into per-edge columns.
- **Verification test:** Core DAGs and Hub DAGs use the same row schema (either both per-edge tables OR both per-blueprint master tables); a single parser can extract edges from both tiers.
- **Disposition:** Open
- **Owner:** tech lead (decision on (a) vs (b)) + main agent (execution)
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-040: Core has CAPABILITY-DAG + BUILD-ORDER; Hub only has VERIFIED-DAG + DECLARED-DAG (missing CAPABILITY + BUILD-ORDER for Hub)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** The Core tier has 4 DAG files: CORE-VERIFIED-DAG.md, CORE-DECLARED-DAG.md, CORE-CAPABILITY-DAG.md, CORE-BUILD-ORDER.md. The Hub tier has only 2 DAG files: HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md. The Hub tier is missing HUB-CAPABILITY-DAG.md (Core→Hub capability edges would be redundant with CORE-CAPABILITY-DAG.md, but Hub→Spoke capability edges don't exist yet) and HUB-BUILD-ORDER.md (Phase 3, deferred per SAAI per HUB-DECLARED-DAG.md line 692).
- **Evidence:**
  - `Architecture/Core/` directory: 4 DAG files
  - `Architecture/Hub/` directory: 2 DAG files + 31 blueprint files (HUB-01..31.md)
  - `Architecture/Hub/HUB-DECLARED-DAG.md` line 692: "The Phase 3 HUB-BUILD-ORDER.md derivation (deferred per SAAI)..."
- **Affected artifact:** `Architecture/Hub/` (missing `HUB-BUILD-ORDER.md` and optionally `HUB-CAPABILITY-DAG.md`)
- **Contract violated:** ADR-021 §11 (per-tier DAG model — each tier should have VERIFIED + DECLARED + CAPABILITY + BUILD-ORDER).
- **Root cause:** The Hub tier was ratified in Phase 1 + Phase 2 of the Hub DAG governance work; Phase 3 (build-order derivation) was deferred per SAAI. The build-order artifact was never authored.
- **Remediation:** Author `Architecture/Hub/HUB-BUILD-ORDER.md` after resolving governance decisions S-027 through S-032 (apply UNKNOWN → COMPILE heuristic, resolve Gap 1, enumerate Gap 5, etc.). HUB-CAPABILITY-DAG.md is optional (Hub→Spoke capability edges can wait for Spoke-tier DAG derivation).
- **Verification test:** `Architecture/Hub/HUB-BUILD-ORDER.md` exists and contains topological waves for the 29 (or 30 post-S-033) active Hub blueprints; the wave count matches what Kahn's algorithm produces on the post-S-031 classified Hub DAG.
- **Disposition:** Open
- **Owner:** tech lead (decision) + main agent (execution)
- **Target phase:** MEDIUM-batch (depends on S-027..S-032 closing first)
- **Closure evidence:** (empty)

### S-041: CORE-CAPABILITY-DAG.md "Status: DRAFT" banner is stale (file is committed to Architecture/Core/)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** `CORE-CAPABILITY-DAG.md` line 5: "**Status:** DRAFT — saved to `/home/z/my-project/download/` for tech-lead review before commit to `Architecture/Core/`." But the file IS at `Architecture/Core/CORE-CAPABILITY-DAG.md` (committed in PR #287). The DRAFT banner is stale — the document has been committed and is authoritative per ADR-021 line 54 (which lists it as a companion doc).
- **Evidence:**
  - `Architecture/Core/CORE-CAPABILITY-DAG.md` line 5: "Status: DRAFT — saved to /home/z/my-project/download/"
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 54: lists `Core/CORE-CAPABILITY-DAG.md` as a companion doc
- **Affected artifact:** `Architecture/Core/CORE-CAPABILITY-DAG.md` line 5
- **Contract violated:** ADR-021 line 54 (companion doc status is authoritative, not draft).
- **Root cause:** The Status banner was set to DRAFT during the tech-lead review phase; when the file was committed to `Architecture/Core/` in PR #287, the banner was not updated.
- **Remediation:** Change line 5 to: "**Status:** Authoritative (ratified as a companion to ADR-021 per INDEX.md §1 line 54)."
- **Verification test:** CORE-CAPABILITY-DAG.md line 5 reads "Authoritative"; `rg "DRAFT" Architecture/Core/CORE-CAPABILITY-DAG.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-042: HUB-DECLARED-DAG.md lists Hub nodes including RUNTIME-03 / RUNTIME-04 references (cross-tier edge placement)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** HUB-DECLARED-DAG.md §4 (lines 344-369) records 11 "relocated edges" with `New Target (Runtime-tier)` = `RUNTIME-03` or `RUNTIME-04`. But the DAG's node set (§1, line 28) only includes "29 active Hub blueprints" — RUNTIME-03 and RUNTIME-04 are NOT Hub nodes (they're Runtime-tier). The table mixes Hub-tier source nodes with Runtime-tier target nodes — which is technically a cross-tier integration inventory, not a Hub-tier DAG edge set.
- **Evidence:**
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §1 line 28: "Node set (29 active Hub blueprints)"
  - `Architecture/Hub/HUB-DECLARED-DAG.md` §4 lines 355-369: 11 relocated edges with `New Target (Runtime-tier)` = `RUNTIME-03` / `RUNTIME-04`
- **Affected artifact:** `Architecture/Hub/HUB-DECLARED-DAG.md`
- **Contract violated:** ADR-021 §11 (the Hub DAG only contains Hub-internal + Hub→Core edges — cross-tier edges belong in cross-tier integration inventory or the Runtime-tier DAG).
- **Root cause:** When HUB-10/HUB-25 were relocated to Runtime tier per ADR-021 §12, the 11 inbound Hub→HUB-10/HUB-25 edges were preserved as a holding table inside the Hub DAG. No Runtime-tier DAG was authored to absorb them.
- **Remediation:** Author `Architecture/Runtime/RUNTIME-DECLARED-DAG.md` and `RUNTIME-VERIFIED-DAG.md` to absorb these 11 edges as incoming Hub→Runtime INTEGRATION edges. Remove the 11 relocated edges from HUB-DECLARED-DAG.md (or replace with a one-paragraph pointer to the Runtime DAG).
- **Verification test:** `Architecture/Runtime/RUNTIME-DECLARED-DAG.md` exists; HUB-DECLARED-DAG.md §1 node set remains Hub-only (no Runtime-tier nodes); the 11 cross-tier edges are recorded in the Runtime DAG as incoming INTEGRATION edges.
- **Disposition:** Open
- **Owner:** tech lead (decision) + main agent (execution)
- **Target phase:** MEDIUM-batch (depends on S-032 closing first)
- **Closure evidence:** (empty)

### S-043: Lint script's PREFIXES list excludes RUNTIME (added in ADR-021 but never integrated)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** `Architecture/Verification/lint/run.php` line 30: `private const PREFIXES = ['CORE', 'HUB', 'ISPOKE', 'ESPOKE', 'BRIDGE', 'DEPLOY'];` — RUNTIME is NOT in the list. ADR-021 §12 introduced the Runtime tier (RUNTIME-01..04). Multiple files reference RUNTIME-NN (HUB-10.md, HUB-25.md, ADR-021, INDEX.md, HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md). The lint's regex does NOT match `RUNTIME-NN` references — so the lint silently ignores them. This means the lint would not catch a typo like "RUNTIME-99" or a reference to a nonexistent "RUNTIME-05".
- **Evidence:**
  - `Architecture/Verification/lint/run.php` line 30: PREFIXES list (no RUNTIME)
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 43: introduces RUNTIME-01..04
  - 6 files reference RUNTIME-NN (per audit Cat 1a analysis)
- **Affected artifact:** `Architecture/Verification/lint/run.php` line 30 (PREFIXES list) + line 58 (`buildValidIds()` map) + line 169 (`checkStructure()` expected file list)
- **Contract violated:** ADR-021 §12 (Runtime tier + RUNTIME-01..04 ratification) is not reflected in the lint's PREFIXES + validIds + structural expected list.
- **Root cause:** When ADR-021 §12 ratified the Runtime tier, the lint script was not extended to recognize RUNTIME as a prefix.
- **Remediation:** Add `'RUNTIME'` to the PREFIXES array (line 30) and add a `range(1, 4)` entry for RUNTIME in `buildValidIds()` (line 58). Optionally extend `checkStructure()` (line 169) to expect `Runtime/RUNTIME-0X.md` files (which don't exist yet — until Runtime-tier blueprints are authored).
- **Verification test:** `Architecture/Verification/lint/run.php` line 30 includes `'RUNTIME'` in PREFIXES; `buildValidIds()` includes RUNTIME-01..RUNTIME-04; a `rg "RUNTIME-99" Architecture/` followed by a lint run produces an "undefined reference" error (proves the lint catches typos).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-044: ADR-021 line 344 references "renamed from CORE-DEPENDENCY-DAG.md" (historical, but echoes old filename)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** ADR-021 line 344: "| `Architecture/Core/CORE-VERIFIED-DAG.md` | **NEW** (renamed from `CORE-DEPENDENCY-DAG.md`) — 13-edge verified implementation DAG. |" — this is a historical mention, technically OK. But it echoes the old filename, which can confuse readers/searches.
- **Evidence:**
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 344
- **Affected artifact:** `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 344
- **Contract violated:** None (historical mention is technically permissible).
- **Root cause:** The rename note was added at the time of the rename (PR #287); the phrasing echoes the old filename.
- **Remediation:** Leave as-is (historical mention) OR rephrase to "(originally authored as CORE-DEPENDENCY-DAG.md, renamed 2026-10-01 per two-DAG model)".
- **Verification test:** Either the line is unchanged (historical mention preserved) OR the line uses the rephrased form that does not appear to reference a live file by the old name.
- **Disposition:** Open
- **Owner:** main agent (opportunistic cleanup)
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

### S-045: INDEX.md §1 line 50 says "ADR-001..010 | 10 Accepted" — accurate, but ambiguous about totals
- **Severity:** LOW
- **Category:** Coherence
- **Description:** INDEX.md §1 line 50: "| `Architecture/ADRs/ADR-001..010` | 10 Accepted Architecture Decision Records |" — this is accurate for ADR-001..010 (all 10 are Accepted). But it doesn't acknowledge that the total Accepted count is 18 (per S-016 analysis). A casual reader sees "10 Accepted" and thinks the project has 10 Accepted ADRs total — when actually 8 more (ADR-012, 013, 014, 017, 018, 019, 020, 021) are also Accepted and listed individually below.
- **Evidence:**
  - `Architecture/INDEX.md` line 50
- **Affected artifact:** `Architecture/INDEX.md` line 50
- **Contract violated:** None (technically accurate; ambiguity only).
- **Root cause:** The line was authored when ADR-001..010 was the full set; later ADRs were appended below without refreshing the summary line.
- **Remediation:** Change line 50 to: "| `Architecture/ADRs/ADR-001..010` | 10 of 18 Accepted ADRs (the original decathlon; 8 more Accepted ADRs listed individually below) |".
- **Verification test:** INDEX.md line 50 reads "10 of 18 Accepted ADRs"; the total Accepted count matches the directory inventory.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

### S-046: INDEX.md §1 line 56 says "1 Proposed ADR (HUB-31)" — but there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** INDEX.md §1 line 56: "| `Architecture/ADRs/ADR-011` | 1 **Proposed** ADR (HUB-31) — not accepted, not counted |" — this only counts ADR-011 as Proposed. But ADR-015 (hospitality vertical promotion) is also Proposed (per line 55), and ADR-016 (library-app boundary split) is also Proposed (per the ADR file's own status). So there are 3 Proposed ADRs total, not 1.
- **Evidence:**
  - `Architecture/INDEX.md` line 56: "1 Proposed ADR (HUB-31)"
  - `Architecture/INDEX.md` line 55: ADR-015 Proposed
  - `Architecture/ADRs/ADR-016-library-app-boundary-split.md` line 3: "Status: Proposed"
- **Affected artifact:** `Architecture/INDEX.md` line 56
- **Contract violated:** None (technically the line is about ADR-011 specifically; the "1 Proposed" framing is misleading).
- **Root cause:** The line was authored when ADR-011 was the only Proposed ADR; later Proposed ADRs (ADR-015, ADR-016) were added without refreshing the count.
- **Remediation:** Either update line 56 to mention all 3 Proposed ADRs explicitly, OR remove the count and rely on the individual row entries.
- **Verification test:** INDEX.md line 56 either mentions 3 Proposed ADRs or doesn't include a count; the Proposed count matches the directory inventory.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

### S-047: INCONSISTENCIES.md #8 still flagged "critical" but CORE-02 is implemented (stale)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** `Architecture/Verification/INCONSISTENCIES.md` lines 107-112: "#8 — `CORE-02` (DI Container) is an empty stub — Flagged as the top build-blocking dependency." But CORE-02 is now implemented (per INDEX.md §2.1 line 95: "Implemented + tested, v1.0.0, 97.2% coverage, PSR-11 conformance"). The INCONSISTENCIES.md entry is stale — it still says "Flagged critical" with status "Flagged critical" instead of "Resolved".
- **Evidence:**
  - `Architecture/Verification/INCONSISTENCIES.md` lines 107-112: #8 "Flagged as the top build-blocking dependency"
  - `Architecture/INDEX.md` line 95: CORE-02 "Implemented + tested, v1.0.0, 97.2% coverage"
- **Affected artifact:** `Architecture/Verification/INCONSISTENCIES.md` #8
- **Contract violated:** None (the file is a tracking ledger; it should be reconciled with INDEX.md).
- **Root cause:** INCONSISTENCIES.md #8 was authored when CORE-02 was a stub; CORE-02 was implemented in PR #127 but #8 was never flipped to "Resolved".
- **Remediation:** Update INCONSISTENCIES.md #8 status to "Resolved (PR #127, 2026-09-18) — CORE-02 implemented + tested + PSR-11 conformance".
- **Verification test:** INCONSISTENCIES.md #8 status reads "Resolved"; `rg "Flagged as the top build-blocking dependency" Architecture/Verification/INCONSISTENCIES.md` returns zero matches at the CORE-02 entry.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

---

## Cross-References (dependencies between findings)

| Finding | Depends on (must close first) | Blocks (downstream findings) |
|---|---|---|
| S-001 | S-033 (HUB-32 blueprint must exist) | S-014 (Hub count update post-canonical-publication) |
| S-002 | S-033 (ESPOKE-19 is part of the same canonical-publication batch) | — |
| S-003 | A0 spec must land first (S-003 is itself the spec+fix) | S-004 (same root cause; same fix) |
| S-004 | S-003 (same root cause; same fix) | — |
| S-014 | S-001, S-033 (post-canonical-publication count update) | — |
| S-019 | S-017 (auto-resolves when §5.2 is collapsed) | — |
| S-033 | S-001 (lint extension bundled with blueprint publication) | S-001, S-014 |
| S-040 | S-027, S-028, S-029, S-030, S-031, S-032 (governance decisions must close before HUB-BUILD-ORDER can be derived) | — |
| S-042 | S-032 (Runtime-tier DAG absorbs the 11 relocated edges) | — |

## Re-Audit Trigger (Phase A3)

At the end of each phase (A0, A1, A2, HIGH-batch, MEDIUM-batch, LOW-backlog), a re-audit subagent re-runs the verification tests for every finding in that phase. A finding's disposition moves to `Closed` only when the re-audit confirms the verification test passes against HEAD at that time.

The full register is re-audited at Phase A3 against HEAD to confirm every architectural claim is now true. The roadmap does not advance past the current lap until A3 re-audit returns zero Open findings above the LOW severity threshold (LOW findings may be Accepted or Deferred at tech-lead discretion).

---

*End of DGLab Architecture Integrity Register. Saved to `/home/z/my-project/download/SHORTCOMINGS-REGISTER.md`. Worklog entry appended separately.*
