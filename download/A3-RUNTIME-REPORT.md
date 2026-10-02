# A3-RUNTIME-76 — Runtime / Concurrency Re-Audit Report

**Task ID:** A3-RUNTIME-76
**Lens:** Runtime + Concurrency
**Audience:** Tech lead / main agent
**Date:** 2026-10-02
**HEAD audited:** `200479e` (immediately after A2 merge `2ecfb03` — "fix(core/container): A2 — Fiber isolation for pulse() (Shape C) (#301)")
**Scope:** Independent verification of the `Container::pulse()` Fiber-isolation fix; new shortcoming hunt.

---

## 0. Methodology

This audit does NOT accept "all 12 edge cases handled" as evidence. Each edge case was independently traced through the live source (`packages/core/container/src/Container.php` v2.1, 635 lines) against the CORE-02 contract (lines 147–258 of `Architecture/Core/CORE-02.md`). Where the implementation comment made a claim (e.g., "class-strings fall through to normal resolution below… step 8b pulseScoped cache will store it"), the claim itself was treated as a contract and verified.

Sources read in full:
- `packages/core/container/src/Container.php` (635 lines)
- `packages/core/container/src/ContainerInterface.php` (125 lines)
- `packages/core/container/src/ContainerException.php` (18 lines — NEW in A2)
- `packages/core/container/src/ServiceDefinition.php`
- `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` (706 lines)
- `Architecture/Core/CORE-02.md` (pulse() contract §147–258)
- `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` (A0 spec)
- `.github/workflows/architecture-boundary-lint.yml`, `scripts/architecture-boundary-lint.py`, `scripts/test_architecture_boundary_lint.py`
- `Architecture/Verification/lint/run.php` (architecture-lint — singular — for cross-check)
- `download/SHORTCOMINGS-REGISTER.md` (S-003/S-004 verification conditions)

PHP is not installed in the sandbox, so PHPUnit was not executed. Verification is by manual code-path tracing, supplemented by running the architecture-boundary-lint (Python) and its 19-test regression suite locally.

---

## 1. FATAL verification — S-003 / S-004

| Finding | Verification condition (from SHORTCOMINGS-REGISTER.md) | Met? | Evidence |
|---|---|---|---|
| **S-003** (a) | `WorkerContaminationTest::testConcurrentFibersObserveIndependentPulseState` passes | ✅ Verified by reasoning | `Container.php` lines 192–207 write `pulse()` to `pulseDefinitions[$fiber][$id]` (WeakMap<Fiber,…>), NOT to global `$definitions`. Fiber A's bucket is untouched by Fiber B's `pulse()`. Test 1 (lines 60–142) exercises this and would pass. |
| **S-003** (b) | `WorkerContaminationTest::testCompletedFiberStateIsNotVisibleToNewFiber` passes (S-004's check) | ✅ Verified by reasoning | After Fiber A completes, its `pulseDefinitions` entry is weakly held; new Fiber B's bucket is empty by construction. `make()` falls through to autowire → `NotFoundException` for `RequestContext`'s required ctor params. Test 4 (lines 281–314) expects `assertNull($contextFromRequestB)`. |
| **S-003** (c) | A new unit test `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` asserting two Fibers calling `pulse()` for the same id with different instances each get their own instance back, with explicit `Fiber::suspend()` interleavings | ❌ **NOT MET** | `packages/core/container/tests/Unit/` contains only `AutowiringTest.php`, `CircularDependencyTest.php`, `CompileTest.php`, `ContainerTest.php`. **No `PulseFiberIsolationTest.php` exists.** A2 added 6 new regression tests to the *kernel-level* `WorkerContaminationTest`, but the *container-level* unit test the register explicitly required was never created. See new finding **S-054**. |
| **S-003** (d) | `PHPUnit + PHPStan (core/kernel)` CI check passes on `main` | ✅ Per A2 commit message ("All CI green: container + kernel + lint + gate") + worklog line 1620. |

### Verdict

**S-003's verification condition (c) is NOT met.** Per the register's closure rule (line 943: "A finding's disposition moves to `Closed` only when the re-audit confirms the verification test passes against HEAD at that time"), **S-003 cannot move to Closed.** It may move to `Fixed` (code change landed) but NOT `Closed` (all verification conditions met). The same applies to S-004 (whose verification condition is "testCompletedFiberStateIsNotVisibleToNewFiber passes" — met — but S-004 depends on S-003 per the cross-reference table at register line 934, so S-004 cannot close until S-003 closes).

The two-line headline:

> **S-003 is Fixed (code change landed) but NOT Closed (verification condition (c) unmet).**
> **S-004 is Fixed but NOT Closed (blocked on S-003 closure).**

This is itself a new shortcoming — **S-054** (HIGH) — see §3 below.

---

## 2. Independent verification of the 12 edge cases

The implementation passes 9 of 12 edge cases cleanly. Three edge cases (#5, #10, #12) are partially correct: they hold for object-value and Closure pulse bindings (the typical Shape C use case) but **fail for class-string pulse bindings**, an input pattern the implementation's own step-0 comment explicitly claims to support.

| # | Edge case (per CORE-02 §183–241) | Implementation (Container.php) | Test coverage | Independent verdict |
|---|---|---|---|---|
| 1 | `pulse()` outside a Fiber → throws `ContainerException` | Lines 175–182: `\Fiber::getCurrent()` null-check throws `ContainerException`. ✓ Implemented correctly. | ✗ **NO TEST** exercises this. (See S-052.) The 11 WorkerContamination tests all call `pulse()` from inside a Fiber; no container-level test exists. | **Implemented, UNVERIFIED.** |
| 2 | `make()` before `pulse()` → falls through | Step 0 (lines 232–265): `isset($pulseDefinitions[$pulseFiber][$id])` false → falls through to step 1+. ✓ | ✓ Tests 4 & 6. | **CORRECT + verified.** |
| 3 | Repeated `pulse()` → overwrite + invalidate | Lines 192–207: `$pulseDefinitions[$fiber][$id] = new ServiceDefinition(...)` overwrites; `invalidateCurrentFiberPulseInstance($fiber, $id)` clears cached instance in the same Fiber only. ✓ | ✓ Test 5 (5 sequential requests). | **CORRECT + verified.** |
| 4 | `pulse()` after resolution → invalidates cached (current Fiber only) | Same as #3. `invalidateCurrentFiberPulseInstance` (lines 521–532) mirrors the `invalidatePulseInstances` pattern but scoped to the current Fiber only. ✓ | ✓ Test 2. | **CORRECT + verified.** |
| 5 | `singleton()` interaction → pulse shadows singleton | For object-value & Closure pulse bindings: ✓ step 0 returns before step 1 (`$instances` cache). **For class-string pulse bindings: ✗ VIOLATED** — step 0 falls through (see lines 261–264 comment); step 1 then returns the cached singleton, completely ignoring the pulse binding. (S-048.) | ✓ Object case covered (Test 11). ✗ Class-string case untested. | **PARTIALLY CORRECT** — fails for class-strings. |
| 6 | Nested Fibers → child has own pulse scope | WeakMap keys on Fiber object identity. Child Fiber ≠ parent Fiber → child's `pulseDefinitions[$childFiber]` is a separate (empty until written) bucket. ✓ | ✓ Tests 8 & 9. | **CORRECT + verified.** |
| 7 | Fiber termination/GC → WeakMap auto-evicts | WeakMap language guarantee. ✓ | ✓ Test 4 (with explicit `unset($fiberA)`). | **CORRECT + verified.** |
| 8 | Fiber reuse → fresh pulse scope | PHP Fibers are one-shot; "reuse" means creating a new Fiber → new WeakMap key → fresh bucket. ✓ | ✓ Test 7. | **CORRECT + verified.** |
| 9 | Child Fiber isolation → inherits global, NOT parent's pulse | Child's `pulseDefinitions[$childFiber]` is empty until child calls `pulse()`; child's `make()` step 0 misses pulse, falls through to global definitions + autowire. ✓ | ✓ Tests 8 (singleton inherited) & 9 (pulse NOT inherited bidirectionally). | **CORRECT + verified.** |
| 10 | `make()` precedence: pulse def → pulse cache → singleton → definition → `NotFoundException` | Object/Closure pulse: ✓ (step 0 handles all of pulse-def + cache + build + cache-write inline). Class-string pulse: ✗ — step 0 falls through; step 1 returns cached singleton if present (violating precedence); step 2 falls back to `$id` as `$concrete` (not the pulse-bound class); step 3 throws `NotFoundException` if `$id` is an interface. (S-048.) Additionally, step 1b (lines 275–282) and step 8b (lines 371–384) are now **dead code** (S-049) — the `pulseScoped` flag is never set in the global `$definitions` array after the A2 redirect. | ✓ Object case. ✗ Class-string case untested. | **PARTIALLY CORRECT** — fails for class-strings; dead code at 1b/8b. |
| 11 | New pulse invalidates cached → YES (current Fiber only) | Line 207. `invalidateCurrentFiberPulseInstance($fiber, $id)` only touches the current Fiber's bucket. ✓ | ✓ Test 2. | **CORRECT + verified.** |
| 12 | Pulse object resolves singleton → OK | Object pulse: ✓ (object IS the instance). Closure pulse: ✓ (Closure invoked; can call `make(DBAL::class)` which hits singleton cache). Class-string pulse: ✗ — class-string fall-through is broken (S-048), so a pulse-scoped object whose constructor takes a singleton cannot be constructed via class-string pulse at all. | ✓ Object & Closure cases. ✗ Class-string untested. | **PARTIALLY CORRECT** — fails for class-strings. |

### Summary of edge-case verification

- **9 / 12** edge cases: correct implementation, verified by tests.
- **3 / 12** edge cases (#5, #10, #12): correct for object-value & Closure pulse bindings; **broken for class-string pulse bindings** (S-048).
- **1 / 12** edge case (#1): correct implementation, **no test** (S-052).

The implementation's own step-0 comment at lines 261–264 makes the false claim: *"Class-string — fall through to normal resolution below. (step 1b / step 2+ will resolve it via autowire and the step-8b pulseScoped cache will store it in `$pulseInstances[$pulseFiber]`.)"* — but step 1b and step 8b are dead code (S-049), and step 2 falls back to `$id` (not the pulse-bound `$pulseConcrete`). The implementation comment is **internally inconsistent** with the implementation.

---

## 3. New findings (S-048+)

### S-048 — `pulse()` with class-string concrete silently misroutes or fails

- **Severity:** HIGH (borderline FATAL — load-bearing isolation invariant #5 "pulse shadows singleton" is violated for class-string pulse values; not bumped to FATAL because the typical Shape C use case is object-value bindings, which work, and no test exercises the failing path)
- **Category:** Latent-Defect (runtime / concurrency)
- **Description:** The implementation's step-0 comment (Container.php lines 261–264) claims that pulse bindings whose `$concrete` is a class-string "fall through to normal resolution (steps 2+)" and that "step 8b pulseScoped cache will store it in `$pulseInstances[$pulseFiber]`". Both claims are FALSE. After the A2 redirect, `pulse()` writes only to `pulseDefinitions[$fiber][$id]` (a WeakMap), never to the global `$definitions` array. Step 2 therefore sees `$definition = null` and computes `$concrete = $id` (line 289), **dropping the pulse-bound class-string entirely**. Three concrete failure modes follow:

  1. **Interface id → `NotFoundException`.** `pulse(RepositoryInterface::class, ConcreteRepo::class)` followed by `make(RepositoryInterface::class)`: step 3 (line 296) sees `$definition === null && !(is_string($concrete='RepositoryInterface') && class_exists('RepositoryInterface'))` → interface_exists is NOT consulted → throws `NotFoundException("No service registered for id [RepositoryInterface] and [RepositoryInterface] is not an instantiable class.")`. The pulse binding is silently lost.

  2. **Non-self class-string → wrong class autowired.** `pulse(SomeClass::class, OtherClass::class)` followed by `make(SomeClass::class)`: step 2 sets `$concrete = $id = 'SomeClass'`. Step 6 autowires `SomeClass`, **not `OtherClass`**. The pulse binding's concrete is silently dropped.

  3. **Competing singleton → singleton wins, ignoring pulse.** `singleton(A, $factory)` already cached in `$instances[A]`, then `pulse(A, ConcreteClass::class)` in a Fiber, then `make(A)` in that Fiber: step 0 falls through (class-string), step 1 (line 268) returns the cached singleton. **The pulse binding is silently ignored, and the singleton takes precedence — directly contradicting CORE-02 edge case #5 ("`pulse(A, value)` shadows `singleton(A, factory)` for the current Fiber").**

- **Evidence:**
  - `packages/core/container/src/Container.php` lines 251–265 (step 0 class-string fall-through comment + closing brace).
  - `packages/core/container/src/Container.php` lines 267–270 (step 1 — `$instances` cache check, returns singleton if present).
  - `packages/core/container/src/Container.php` line 275 (step 1b — `$definition = $this->definitions[$id] ?? null` — `null` for pulse bindings).
  - `packages/core/container/src/Container.php` line 289 (step 2 — `$concrete = $definition !== null ? $definition->concrete : $id` — falls back to `$id`, not the pulse-bound `$pulseConcrete`).
  - `packages/core/container/src/Container.php` lines 296–300 (step 3 — throws `NotFoundException` for non-class-string `$id`, including interfaces).
  - `packages/core/container/src/Container.php` lines 195–201 (pulse() writes to `pulseDefinitions`, never to global `$definitions` — confirming `$definition` at step 1b/2 is always `null` for pulse bindings).
  - `packages/core/container/tests/Integration/WorkerContaminationTest.php` lines 81, 106, 175, 185, 231, 244, 294, 340, 402, 452, 561, 573, 656, 662 — every `pulse()` call in the test suite passes an OBJECT value; **zero tests pass a class-string**.
- **Affected artifact:** `packages/core/container/src/Container.php` step 0 (lines 226–265).
- **Contract violated:** CORE-02 edge case #5 ("pulse-scoped binding shadows a singleton for the current Fiber") — violated for class-string pulse values when a singleton is already cached. CORE-02 edge case #10 ("make() precedence: pulse def → pulse cache → singleton → definition → NotFoundException") — violated: cached singleton takes precedence over pulse class-string binding. CORE-02 edge case #12 ("Pulse object resolves singleton → OK") — violated: pulse-bound class-string whose constructor needs a singleton cannot be constructed at all.
- **Root cause:** A2 redirected `pulse()` to write to `pulseDefinitions` (WeakMap), but step 0's class-string fall-through was not updated to propagate `$pulseConcrete` into step 2's `$concrete`. The fall-through was a no-op refactor-wise: it still routes through step 2 which computes `$concrete` from the global `$definitions` array (now always `null` for pulse ids). Step 8b, which would have cached the result in `pulseInstances[$pulseFiber]`, is dead code (S-049).
- **Remediation (preferred):** Handle the class-string case directly in step 0, mirroring the Closure path:
  ```php
  // Class-string — autowire the bound concrete and cache in pulse scope.
  if (is_string($pulseConcrete) && class_exists($pulseConcrete)) {
      $object = $this->build($pulseConcrete, $parameters);
      if (!isset($this->pulseInstances[$pulseFiber])) {
          $this->pulseInstances[$pulseFiber] = [];
      }
      $this->pulseInstances[$pulseFiber][$id] = $object;
      return $object;
  }
  ```
  (Cycle detection must also wrap this — see step 4. The cleanest fix is to route the class-string pulse through the existing cycle-detection + autowire path by setting `$definition = $this->pulseDefinitions[$pulseFiber][$id]` for the pulse case, so step 2 picks up the correct `$concrete` and step 8b caches the result. This simultaneously fixes S-049.)
- **Verification test:**
  - `pulse(RepositoryInterface::class, ConcreteRepo::class)` in a Fiber, then `make(RepositoryInterface::class)` returns a `ConcreteRepo` instance (not `NotFoundException`).
  - `singleton(A, $factory)` already resolved, then `pulse(A, B::class)` in a Fiber, then `make(A)` in that Fiber returns a `B` instance (NOT the cached singleton).
  - Pulse-bound class-string result is cached: two `make(A)` calls in the same Fiber return the same object identity.

---

### S-049 — Steps 1b and 8b are dead code; the `pulseScoped` flag is now vestigial on global definitions

- **Severity:** LOW (no behavioral defect — just dead code + misleading comment)
- **Category:** Coherence
- **Description:** After the A2 redirect, `pulse()` writes only to `pulseDefinitions[$fiber][$id]` with `pulseScoped: true` (line 199). The global `$definitions` array NEVER has a `pulseScoped = true` entry (no other code path sets it — `bind()` at lines 142–158 always sets `pulseScoped: false` implicitly via the default). Therefore:
  - Step 1b (lines 275–282): `if ($definition !== null && $definition->pulseScoped)` — `$definition` is from global `$definitions`, so `pulseScoped` is always `false`. **Dead branch.**
  - Step 8b (lines 371–384): same condition, same conclusion. **Dead branch.**
  - The `pulseScoped` field on `ServiceDefinition` (lines 35 of `ServiceDefinition.php`) is reachable only via direct construction or reflection — no public API exposes it.
- **Evidence:**
  - `packages/core/container/src/Container.php` line 199 (`pulseScoped: true` set only in `pulse()`).
  - `packages/core/container/src/Container.php` line 195 (`pulse()` writes to `pulseDefinitions[$fiber]`, NOT to global `$definitions`).
  - `packages/core/container/src/Container.php` line 276 (`if ($definition !== null && $definition->pulseScoped)` — always false).
  - `packages/core/container/src/Container.php` line 372 (same — always false).
  - `packages/core/container/src/Container.php` lines 261–264 (step 0 comment that claims "step 8b pulseScoped cache will store it in `$pulseInstances[$pulseFiber]`" — **this claim is FALSE** for pulse bindings; step 8b is unreachable).
- **Affected artifact:** `packages/core/container/src/Container.php` steps 1b (lines 275–282), 8b (lines 371–384); `ServiceDefinition.php` field `pulseScoped` (line 35).
- **Contract violated:** No direct contract violation — but the implementation comment at line 263 is internally inconsistent with the implementation, which is a self-contradiction that misleads maintainers.
- **Root cause:** A2 redirected `pulse()` away from the global definitions array, but did not update the global-resolution branches (1b, 8b) that previously handled the pulseScoped cache. They became unreachable.
- **Remediation:** Two options:
  - **(a) Remove dead code.** Delete step 1b and step 8b entirely; remove `pulseScoped` from `ServiceDefinition` (it's vestigial). Update the step 0 comment to reflect that class-string pulse bindings are NOT supported (then resolve S-048 by either rejecting them in `pulse()` or properly handling them in step 0).
  - **(b) Make dead code live.** Refactor step 0's class-string fall-through to set `$definition = $this->pulseDefinitions[$pulseFiber][$id]` for the pulse case, so step 2 picks up the correct `$concrete` and step 8b caches the result. This simultaneously fixes S-048.
- **Verification test:** Code-coverage report shows step 1b and step 8b are hit when (a) `bind()` with `pulseScoped=true` is invoked directly, OR (b) class-string `pulse()` bindings are resolved through `make()`. If neither path covers them, the branches are confirmed dead.

---

### S-050 — `ContainerInterface::pulse()` docblock drifts from Shape C contract

- **Severity:** MEDIUM (interface contract drift — does not affect runtime behavior, but misleads API consumers)
- **Category:** Doc-Drift
- **Description:** The A0 worklog (line 1621) states "Shape C is LOCKED (pulse() = request-time, Fiber-local value binding)". The CORE-02 contract (lines 147–258) describes Shape C semantics. The live `Container.php` docblock at lines 78–91 reflects Shape C. **But the live `ContainerInterface.php` docblock at lines 48–63 still describes Shape A semantics.** It reads:
  - "Register a Pulse-scoped binding — one instance per Fiber (per Pulse)." — Shape A language ("register a binding").
  - "When a Pulse resolves this service, it receives a fresh instance that is cached for the duration of that Pulse only." — Shape A language (pulse = a factory that produces fresh instances).
  - "This is the correct scope for tenant-scoped services (repositories, unit-of-work, request context) under the Fiber-based cooperative runtime (OD-07)." — Shape A framing.
  - "@param mixed $concrete The concrete resolver (same types as {@see bind()})." — describes `$concrete` as a "resolver" (factory), not a VALUE.
  - **Missing:** `@throws \SovereignStack\Core\Container\ContainerException If called outside a Fiber context.` (CORE-02 contract line 256 explicitly requires this.)

- **Evidence:**
  - `packages/core/container/src/ContainerInterface.php` lines 48–63 (live interface docblock — Shape A language).
  - `packages/core/container/src/Container.php` lines 78–91 (live impl docblock — Shape C language).
  - `Architecture/Core/CORE-02.md` lines 147–258 (ratified Shape C contract).
  - `worklog.md` line 1621 ("Shape C is LOCKED").
- **Affected artifact:** `packages/core/container/src/ContainerInterface.php` (the `pulse()` docblock).
- **Contract violated:** CORE-02 (Shape C semantics for `pulse()`); ADR-017 (Fiber-based cooperative runtime); the live impl's own docblock.
- **Root cause:** When A2 was implemented (commit `2ecfb03`), only the implementation file (`Container.php`) and the new exception class (`ContainerException.php`) were updated. The interface file (`ContainerInterface.php`) was not touched — its docblock still carries the pre-A2 Shape A language.
- **Remediation:** Rewrite the `ContainerInterface::pulse()` docblock to:
  - Describe Shape C: "request-time, Fiber-local value-binding operation. The `$value` IS the instance — `make($id)` returns this value directly for the current Fiber."
  - Add `@throws \SovereignStack\Core\Container\ContainerException If called outside a Fiber context.`
  - Remove Shape A language ("Register a Pulse-scoped binding", "fresh instance that is cached for the duration of that Pulse only", "tenant-scoped services").
- **Verification test:** `rg "Register a Pulse-scoped binding" packages/core/container/src/` returns zero matches; `rg "@throws.*ContainerException" packages/core/container/src/ContainerInterface.php` returns one match.

---

### S-051 — Duplicate stacked docblock for `$pulseDefinitions` property

- **Severity:** LOW (cosmetic; PHP allows stacked docblocks but only the LAST one is attached to the property)
- **Category:** Coherence
- **Description:** The `$pulseDefinitions` property at `Container.php` line 95 has TWO stacked docblocks: one at lines 73–91 (the rich explanation, including S-003/S-004 remediation context) and a second at lines 92–94 (just the `@var` annotation). PHP attaches only the docblock IMMEDIATELY preceding the property declaration, so the rich explanation in the first docblock is **orphaned** — IDEs and PHPStan may not associate it with the property. This pattern was likely a leftover from the A2 merge.
- **Evidence:**
  - `packages/core/container/src/Container.php` lines 73–95 (two stacked docblocks).
  - Compare to `$pulseInstances` at lines 56–71 (single docblock with `@var` inside — correct pattern).
- **Affected artifact:** `packages/core/container/src/Container.php` (property `$pulseDefinitions`).
- **Contract violated:** None — but inconsistent with the surrounding docblock style (`$pulseInstances`, `$fiberResolving`).
- **Root cause:** A2 merge artifact — likely an artifact of an intermediate edit where the `@var` was added without merging into the existing docblock.
- **Remediation:** Merge the two docblocks into one, placing the `@var` annotation inside the main docblock (matching the `$pulseInstances` pattern at lines 56–71).
- **Verification test:** `grep -c '^    /\*\*$' packages/core/container/src/Container.php` returns the same count as the number of properties + methods + class (no orphaned docblocks).

---

### S-052 — No test for edge case #1 (`pulse()` outside Fiber throws `ContainerException`)

- **Severity:** LOW (verification gap — implementation is correct, but no test asserts it)
- **Category:** Latent-Defect (test coverage)
- **Description:** CORE-02 edge case #1 (line 185): "pulse() outside a Fiber: Throws `ContainerException`." The implementation is correct (lines 175–182 throw `ContainerException` when `\Fiber::getCurrent()` is null). **But NO test exercises this.** The 11 WorkerContamination tests all call `pulse()` from inside a Fiber. The container-level tests (`ContainerTest`, `AutowiringTest`, `CompileTest`, `CircularDependencyTest`, `Psr11ConformanceTest`) do not reference `pulse()` or `Fiber` at all.
- **Evidence:**
  - `packages/core/container/src/Container.php` lines 175–182 (the throw — implementation correct).
  - `grep -rn "ContainerException" packages/core/kernel/tests/ packages/core/container/tests/` — matches only the Psr11ConformanceTest for `CircularDependencyException`; no test for the `pulse()`-outside-Fiber throw.
  - `packages/core/kernel/tests/Integration/WorkerContaminationTest.php` line 692 (helper docblock mentions the behavior in a comment) — but no `test*` method asserts it.
- **Affected artifact:** Container test suite.
- **Contract violated:** CORE-02 edge case #1 (no test enforces it).
- **Root cause:** A2 added tests for the Fiber-isolation use case (Tests 1–11) but did not add a test for the boundary case (pulse outside Fiber).
- **Remediation:** Add a test (either at the kernel level or, preferably, in the new `PulseFiberIsolationTest.php` recommended by S-054):
  ```php
  public function testPulseOutsideFiberThrowsContainerException(): void
  {
      $container = new Container();
      $this->expectException(\SovereignStack\Core\Container\ContainerException::class);
      $this->expectExceptionMessageMatches('/requires a current Fiber context/');
      $container->pulse(stdClass::class, new stdClass());
  }
  ```
- **Verification test:** The test exists and passes.

---

### S-053 — No test for class-string `pulse()` bindings (S-048's failure mode)

- **Severity:** LOW (test-coverage gap — this is why S-048 went undetected)
- **Category:** Latent-Defect (test coverage)
- **Description:** All 11 `pulse($id, $value)` calls in `WorkerContaminationTest.php` pass an OBJECT as `$value` (`RequestContext` instances at lines 81, 106, 175, 185, 231, 244, 294, 340, 402, 452, 561, 573; `stdClass` at lines 656, 662). **Zero tests pass a class-string or a Closure.** This is why S-048's three failure modes (interface→`NotFoundException`, non-self class→wrong class autowired, competing singleton→singleton wins) were not caught by CI.
- **Evidence:**
  - `grep -n "pulse(" packages/core/kernel/tests/Integration/WorkerContaminationTest.php` — 14 hits, all object values.
- **Affected artifact:** Test suite.
- **Contract violated:** CORE-02 edge cases #5, #10, #12 (only the object-value sub-case is verified).
- **Root cause:** The A2 implementer wrote tests for the use case they were fixing (per-Fiber object-value isolation) but did not write tests for the other concrete-type paths the implementation's step 0 claims to support (Closure, class-string).
- **Remediation:** Add tests covering all three concrete-type paths in `pulse()`:
  - **Object value** (already covered).
  - **Closure**: `pulse(A, fn($c) => new A($c->make(B::class)))` — assert the Closure is invoked exactly once per Fiber and the result is cached in `pulseInstances[$fiber]`.
  - **Class-string**: `pulse(A::class, B::class)` — assert `make(A::class)` returns a `B` instance, that it's cached in `pulseInstances[$fiber]`, and that it shadows any pre-cached singleton. (This test would have caught S-048.)
- **Verification test:** The new tests exist and pass.

---

### S-054 — S-003 verification condition (c) UNMET: container-level `PulseFiberIsolationTest.php` does not exist

- **Severity:** HIGH (FATAL-adjacent — blocks closure of S-003 / S-004 per the register's closure rule)
- **Category:** Governance (process / audit)
- **Description:** The S-003 register entry (SHORTCOMINGS-REGISTER.md line 115) explicitly lists four verification conditions, of which (c) is:

  > "a new unit test `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` asserting that two Fibers calling `pulse()` for the same id with different instances each get their own instance back, with explicit `Fiber::suspend()` interleavings"

  **This file does not exist.** `packages/core/container/tests/Unit/` contains only `AutowiringTest.php`, `CircularDependencyTest.php`, `CompileTest.php`, `ContainerTest.php`. The A2 fix added 6 new regression tests to the *kernel-level* `WorkerContaminationTest.php` (Test 6 through Test 11), but did NOT add the container-level unit test that S-003's verification condition explicitly required.

  Per the register's closure rule (line 943): "A finding's disposition moves to `Closed` only when the re-audit confirms the verification test passes against HEAD at that time." Since condition (c) is unmet, **S-003 cannot move to `Closed`** at this A3 re-audit. The same applies to S-004 (cross-reference table line 934: "S-004 depends on S-003 (same root cause; same fix)").

- **Evidence:**
  - `download/SHORTCOMINGS-REGISTER.md` line 115 (S-003 verification condition (c) — names the file).
  - `ls packages/core/container/tests/Unit/` — `AutowiringTest.php`, `CircularDependencyTest.php`, `CompileTest.php`, `ContainerTest.php`. **No `PulseFiberIsolationTest.php`.**
  - `grep -l 'pulse\|Fiber' packages/core/container/tests/Unit/*.php` — zero matches (no container-level test references `pulse()` or `Fiber`).
  - `git show 2ecfb03 --stat` — A2 commit touched only 3 files: `Container.php`, `ContainerException.php`, `WorkerContaminationTest.php`. No new container-level test file was added.
- **Affected artifact:** `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` (missing).
- **Contract violated:** SHORTCOMINGS-REGISTER.md S-003 verification condition (c); the closure rule (line 943); the paradigm-shift directive ("making every architectural claim true before allowing the roadmap to move").
- **Root cause:** The A2 implementer treated the kernel-level `WorkerContaminationTest` expansion as satisfying S-003's verification intent (Test 1 + Test 4 cover the (a) and (b) sub-conditions), but did not notice that condition (c) explicitly required a NEW FILE at a DIFFERENT PATH (`packages/core/container/tests/Unit/PulseFiberIsolationTest.php`). The expansion-of-existing-tests satisfied the spirit of (c) but not the letter.
- **Remediation:** Create `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` with at minimum:
  - `testTwoFibersCallingPulseForSameIdWithDifferentInstancesEachGetTheirOwnBack` — the test condition (c) explicitly names.
  - `testPulseOutsideFiberThrowsContainerException` (closes S-052).
  - `testPulseClassStringBindingResolvesToBoundConcrete` (closes S-053 + catches S-048).
  - `testPulseShadowsSingleton` (closes part of S-048).
- **Verification test:** `ls packages/core/container/tests/Unit/PulseFiberIsolationTest.php` exits 0; the test class has the four methods above; `phpunit packages/core/container` is green.

---

## 4. Blind-spot report — what we discovered during A3 that we did not know before A3 began

### 4.1 The premise "`architecture-boundary-lint` is failing on `main`" appears INCORRECT.

The A3 task brief stated: "`architecture-boundary-lint` is failing on `main`." Running the linter locally at HEAD `200479e`:

```
Files scanned:      198
Imports scanned:    317
Violations:         0
Legitimate callers seen: 0 / 6 expected
✅ No violations. Architecture boundary rules pass.
```

The 19-test regression suite (`scripts/test_architecture_boundary_lint.py`) returns `19 passed, 0 failed`. The A2 commit message explicitly claims "All CI green: container + kernel + lint + gate" (worklog line 1620 confirms for the predecessor; commit `2ecfb03`'s message extends this to the A2 fix). The new `ContainerException.php` imports only `\RuntimeException` and `\Psr\Container\ContainerExceptionInterface` — both are in the `ALLOWED_EXTERNAL_PREFIXES` list (`Psr\`, plus PHP built-ins). `Container.php` imports only same-namespace classes (`SovereignStack\Core\Container\…`) and PHP built-ins. Neither triggers an `ARCH-BOUNDARY-001` or `ARCH-LOCATOR-001` violation.

**Conclusion:** `architecture-boundary-lint` is NOT failing on `main` as of HEAD `200479e`. The task brief's premise may have been derived from a stale CI status snapshot. If the tech lead's dashboard shows otherwise, the next investigation step is to download the live workflow log from GitHub Actions (the local Python run is authoritative for the code at HEAD; the only way the CI run could differ is if the CI runner is using a different version of the linter script than what's on disk — which would itself be a finding worth recording).

**One cosmetic anomaly:** the lint's `legitimate_callers_seen: 0 / 6 expected` counter is a soft informational field — the 6 expected files (`MiddlewareResolver.php`, etc.) appear to no longer contain `->resolve(` calls matching the lint's pattern, so the counter is `0/6`. The test suite does not assert this counter is non-zero, so the lint still passes. But this means the "expected legitimate callers" list in the script (`LEGITIMATE_RESOLVE_CALLERS` at lines 123–130) may be stale relative to the live source. Worth a follow-up.

### 4.2 The A2 fix is narrower than its own step-0 comment claims.

Before A3, the working assumption (per the A2 commit message and the passing WorkerContaminationTest) was that "12 edge cases are handled." The re-audit reveals:

- The implementation's step-0 comment explicitly claims support for **class-string pulse bindings** via fall-through to "normal resolution."
- The fall-through is **broken in three distinct ways** (S-048): interface id → `NotFoundException`; non-self class → wrong class autowired; competing singleton → singleton silently wins.
- **None** of the 11 WorkerContamination tests exercise a class-string pulse value. The test suite exclusively uses object-value bindings, which work correctly because they are handled inline in step 0.

This is the classic "test suite verifies the use case the implementer had in mind, but the implementation comment claims a broader contract that the tests don't cover" pattern. The step-0 comment is the implementation's own contract — and it is internally inconsistent with the implementation.

### 4.3 The A2 fix did not satisfy S-003's verification condition (c).

Before A3, the assumption was that A2 closed S-003/S-004 (the worklog line 1620 says "2 of 4 FATALs resolved" at A1 time, and the A2 commit message says "Resolves S-003/S-004 (FATAL)"). The re-audit reveals: S-003's verification condition (c) explicitly required a NEW FILE at `packages/core/container/tests/Unit/PulseFiberIsolationTest.php`. That file does not exist. The A2 implementer expanded the kernel-level `WorkerContaminationTest` (which satisfies the spirit of (c) — the isolation behavior is tested) but missed the letter of (c) — the new file at the container-package level was never created.

Per the register's closure rule (line 943: "A finding's disposition moves to `Closed` only when the re-audit confirms the verification test passes against HEAD at that time"), **S-003 cannot move to `Closed` at this A3 re-audit.** It is `Fixed` (code change landed, behavior correct) but NOT `Closed` (one verification condition unmet). S-004 is blocked on S-003 closure per the cross-reference table.

This is the most consequential A3 discovery: **the FATAL finding set is NOT actually closed** despite the A2 commit message's claim. The closure rule was not enforced mechanically; the A2 PR's CI green does not equal closure.

### 4.4 The `ContainerInterface` docblock did not get the Shape C update.

Before A3, the working assumption was that A2 implemented Shape C in full. The re-audit reveals: only the implementation file (`Container.php`) and the new exception class (`ContainerException.php`) carry Shape C language. The interface file (`ContainerInterface.php`) still carries Shape A language ("Register a Pulse-scoped binding", "fresh instance that is cached for the duration of that Pulse only", "tenant-scoped services"). The interface's `@throws` tag is also missing `ContainerException`. A caller reading the interface would not know that `pulse()` throws `ContainerException` outside a Fiber, nor that the `$concrete` parameter is supposed to be the instance itself (not a factory). This is a contract drift between interface and implementation — both written by the same agent, in the same commit window.

### 4.5 The `pulseScoped` flag on `ServiceDefinition` is now vestigial.

Before A3, the assumption was that the `pulseScoped` flag (per `ServiceDefinition::__construct` parameter at line 35) is consumed somewhere. The re-audit reveals: the only writer is `pulse()` at line 199, which writes to `pulseDefinitions` (not global `$definitions`). The only readers are step 1b and step 8b, both of which check `$definition->pulseScoped` where `$definition` comes from global `$definitions`. After A2, global `$definitions` never contains a `pulseScoped = true` entry, so steps 1b and 8b are unreachable. The flag is vestigial — kept alive only by the dead branches. Either the flag should be removed (and the dead branches deleted), or the branches should be made live by routing class-string pulse bindings through them (which simultaneously fixes S-048).

### 4.6 The "12 edge cases handled" claim was not independently verified by tests.

Before A3, the assumption (per A2 commit message: "All CI green") was that all 12 edge cases are tested. The re-audit reveals:
- Edge case #1 (pulse outside Fiber): **no test**.
- Edge case #5 (pulse shadows singleton): tested for object values only (Test 11); class-string sub-case untested.
- Edge case #10 (make() precedence): tested for object values only; class-string sub-case untested.
- Edge case #12 (pulse object resolves singleton): tested for object values only; class-string sub-case untested.

**Three of twelve edge cases have direct test coverage for all sub-paths; the other nine have partial coverage.** The "all 12 handled" headline is true at the implementation level for the typical use case, but false at the test-coverage level for the broader contract (especially class-string pulse values).

### 4.7 Stale `LEGITIMATE_RESOLVE_CALLERS` list (potential follow-up, not a finding).

The architecture-boundary-lint script declares 6 expected legitimate callers (lines 123–130) but reports 0 seen. This may indicate that the listed files have been refactored to no longer contain matching `resolve()` calls (the lint's regex is narrow: `\$(container|c|di|containerInterface)\s*->\s*(get|make|resolve|has)\s*\(`). The test suite passes because it only checks that legitimate callers are NOT flagged, not that they ARE seen. This is a cosmetic stale-list issue, not a defect. Suggest follow-up to either update the list or strengthen the test to assert the seen-counter is ≥ 1.

---

## 5. Summary table

| ID | Severity | Category | One-liner |
|---|---|---|---|
| S-048 | HIGH | Latent-Defect | `pulse()` with class-string concrete silently misroutes or throws `NotFoundException`; step-0 fall-through drops `$pulseConcrete`. Three failure modes (interface id, non-self class, competing singleton). |
| S-049 | LOW | Coherence | Steps 1b and 8b are dead code after A2; `pulseScoped` flag on `ServiceDefinition` is now vestigial. The step-0 comment claiming step 8b caches is FALSE. |
| S-050 | MEDIUM | Doc-Drift | `ContainerInterface::pulse()` docblock still describes Shape A semantics; missing `@throws ContainerException` tag. Drift between interface and impl. |
| S-051 | LOW | Coherence | Duplicate stacked docblock for `$pulseDefinitions` (lines 73–95); rich explanation is orphaned, only the bare `@var` is attached. |
| S-052 | LOW | Test coverage | No test for edge case #1 (`pulse()` outside Fiber throws `ContainerException`). Implementation correct, verification gap. |
| S-053 | LOW | Test coverage | No test for class-string `pulse()` bindings — the input pattern whose mishandling is S-048. Tests exclusively use object values. |
| S-054 | HIGH | Governance | S-003 verification condition (c) UNMET: `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` does not exist. S-003/S-004 cannot move to `Closed` per the register's closure rule. |

**Net new findings:** 7 (S-048 through S-054).
- 2 HIGH (S-048, S-054).
- 1 MEDIUM (S-050).
- 4 LOW (S-049, S-051, S-052, S-053).

**No new FATAL findings.** The two existing FATALs (S-003, S-004) are confirmed Fixed (code change correct) but NOT Closed (S-003 condition (c) unmet — S-054).

---

## 6. Recommended remediation order

1. **S-054 + S-052 + S-053 (combined fix):** Create `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` covering (a) two-Fiber isolation with `Fiber::suspend()` interleavings [closes S-003 cond. (c) and S-054], (b) pulse outside Fiber throws `ContainerException` [closes S-052], (c) class-string pulse binding resolves to the bound concrete [closes S-053 and verifies the S-048 fix], (d) pulse shadows singleton for object AND class-string values [verifies S-048 fix]. Single PR.

2. **S-048 + S-049 (combined fix):** Route class-string pulse through the existing cycle-detection + autowire path by setting `$definition = $this->pulseDefinitions[$pulseFiber][$id]` for the pulse case in step 0 (or equivalent). This makes step 2 pick up the correct `$concrete` and step 8b live (caches the result in `pulseInstances[$pulseFiber]`). Single PR; simultaneously fixes S-048 and makes step 8b reachable (resolving S-049's dead-code finding for step 8b). Step 1b remains dead — delete it.

3. **S-050:** Update `ContainerInterface::pulse()` docblock to Shape C language and add the `@throws ContainerException` tag. Single PR (doc-only).

4. **S-051:** Merge the duplicate docblock for `$pulseDefinitions`. Single PR (doc-only, can be batched with S-050).

---

## 7. Files I would change (not changed — A3 is audit-only)

No files were modified during this audit (per A3 task constraint: audit only, no fixes). The recommended remediations above are sketched for the next PR window.

---

*End of A3-RUNTIME-76 report. Saved to `/home/z/my-project/download/A3-RUNTIME-REPORT.md`.*
