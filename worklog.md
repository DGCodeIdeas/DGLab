# DGLab Worklog

## Task 42 — P2 Batch: Security & Correctness Fixes Across Core Packages

**Date:** 2026-09-17
**Branch:** `fix/p2-batch-security-correctness`
**Commit SHA:** `c1163e0f43d4eac6651e3b31116c6a18df0fed20`
**Base:** `main` @ `3fcc063` (the GUID-tagged scratch commit immediately after PR #214)

### Packages touched
- `packages/core/http-message` — Uri CRLF validation + Request::withRequestTarget CRLF validation
- `packages/core/logger` — HandlerInterface propagation docstring + StreamHandler 0-byte write
- `packages/core/error-handler` — shutdownRegistered flag + handleFatal stale-callback guard
- `packages/core/kernel` — new KernelException named constructors for accurate state-machine messages

### Source changes

#### `packages/core/http-message/src/Uri.php`
- Added private `assertNoCrlf(string $component, string $value)` helper that throws `InvalidArgumentException` on `\r` or `\n`.
- Called it in the constructor (after `parse_url`) for `scheme`, `userInfo`, and `host`.
- Called it in `withScheme`, `withUserInfo`, `withHost` (validate on `$this` before clone — same pattern as `Request::withHeader`).
- Path/query/fragment are NOT validated — they go through the percent-encoding normalizers which encode `\r` and `\n` to `%0D`/`%0A`, so they cannot carry raw CRLF.
- Updated the class docblock to document the new security invariant.

#### `packages/core/http-message/src/Request.php`
- `withRequestTarget()` now calls `$this->assertNoCrlf('request-target', [$target])` before assigning to the new instance. Matches the existing `withHeader`/`withAddedHeader` pattern.

#### `packages/core/logger/src/HandlerInterface.php`
- Rewrote the `handle()` docstring to clearly document the propagation contract: `true` continues propagation to downstream handlers; `false` stops propagation (the record is "swallowed").
- The code in `Logger::log()` already breaks on `false`. The old docstring ambiguously implied `true` stopped propagation. Aligning the docstring to the code is the less-invasive fix; flipping the code would silently break the multi-handler broadcast test and require updating every existing handler.
- Mentioned the Monolog `bubble=true` convention explicitly so future contributors know the lineage.

#### `packages/core/logger/src/Handler/StreamHandler.php`
- Changed the fwrite failure check from `if ($written === false || $written === 0)` to `if ($written === false)`. A 0-byte fwrite of an empty string is a legitimate success case (PHP returns 0, not false). The old condition triggered a spurious `error_log()` warning on zero-byte writes.

#### `packages/core/error-handler/src/ErrorHandler.php`
- Added `private bool $shutdownRegistered = false;` field with a docblock explaining that PHP's `register_shutdown_function()` registry is process-global and cannot be undone by `unregister()`.
- `register()` now also sets `$this->shutdownRegistered = true;` after calling `register_shutdown_function()`.
- `handleFatal()` now early-returns if `!$this->shutdownRegistered || !$this->registered` — so a stale shutdown callback (after `unregister()`) is a no-op rather than calling `logThrowable()` + `emitOutput()` against a torn-down logger/renderer.
- Added a new public `isShutdownRegistered(): bool` accessor for diagnostics. Not added to the frozen `ErrorHandlerInterface` — concrete-class method only.

#### `packages/core/kernel/src/KernelException.php`
- Added `handleDuringHandling(): self` — message: "Cannot handle() while already handling a request. Recursive handle() calls are not allowed — the kernel is not re-entrant within a single request. Wait for the outer handle() to return."
- Added `accessBeforeBoot(): self` — message: "Cannot access kernel services before boot(). Call boot() first to initialize the container, register service providers, and run bootstrappers."

#### `packages/core/kernel/src/Kernel.php`
- `handle()` match arm for `KernelState::Handling` now throws `KernelException::handleDuringHandling()` instead of `KernelException::handleDuringBoot()`. The old message ("Cannot handle() during boot()") was misleading because in the Handling state the actual scenario is a recursive `handle()` call, not a `handle()`-during-boot.
- `assertBooted()` for `KernelState::Unbooted` now throws `KernelException::accessBeforeBoot()` instead of `KernelException::handleBeforeBoot()`. The old message ("Cannot handle() before boot()") was misleading because `assertBooted()` is called from `getContainer`/`getRouter`/`getLogger`/etc., not from `handle()`.

### Test changes

#### `packages/core/http-message/tests/Security/HeaderInjectionTest.php`
- Restructured `testUriHostWithCrlfThrowsInConstructor` and `testUriHostWithCrlfThrowsInWithUri`: `expectException` now sits BEFORE the line that throws, because `Uri::withHost()` throws before `Request::__construct`/`withUri` even sees the malicious value.
- Added `crlfComponentProvider()` data provider returning CR / LF / CRLF / LFCR payloads × {withHost, withScheme, withUserInfo}.
- Added `testUriRejectsCrlfInHostSchemeAndUserInfo` data-driven test covering all 18 combinations.
- Added `testRequestWithRequestTargetRejectsCrlf` covering `Request::withRequestTarget`.

#### `packages/core/logger/tests/Unit/LoggerTest.php`
- Added `testHandlerReturningFalseStopsPropagation`: verifies that when the first handler returns `false`, the downstream handler is NOT called (propagation stops).
- Added `testHandlerReturningTrueContinuesPropagation`: verifies that when the first handler returns `true`, the downstream handler IS called (propagation continues — the default StreamHandler path).

#### `packages/core/kernel/tests/Unit/KernelStateMachineTest.php`
- Updated `testGetContainerBeforeBootThrows` to assert the new `Cannot access kernel services before boot()` message.
- Added `testHandleDuringHandlingThrows`: forces the kernel into Handling state via reflection (the property is private, not readonly) and verifies that `handle()` throws `handleDuringHandling()` with the new message.

#### `packages/core/error-handler/tests/Unit/ErrorHandlerTest.php`
- Added `testHandleFatalIsNoOpAfterUnregister`: registers, unregisters, then calls `handleFatal()`. Verifies that no log output is produced (the stale shutdown callback is a no-op). Also asserts `isRegistered()` is false and `isShutdownRegistered()` is true (the flag stays set after unregister).
- Added `testHandleFatalIsNoOpBeforeRegister`: verifies `handleFatal()` is a no-op when neither register nor unregister has been called.
- Added `testFlagsAfterRegister`: asserts both flags are true after `register()`.

### Verification
- Pre-flight `git diff --cached | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — clean.
- Post-commit `git show HEAD | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — clean.
- Post-push `git ls-remote` confirms the remote branch is at `c1163e0`.
- PHP runtime was not available in the sandbox (no `php`, `apt-get install` blocked by perms) — PHPUnit tests were not executed locally. All changes were verified by careful code review against the existing test patterns.

### Tests that may break (in CI)
1. **None expected to break.** All existing test values use safe (non-CRLF) inputs. The two existing URI CRLF tests in `HeaderInjectionTest` were restructured to expect the exception at the new (earlier) throw point — the assertion type (`\InvalidArgumentException`) is unchanged.
2. **The `testGetContainerBeforeBootThrows` test was updated** to assert the new `Cannot access kernel services before boot()` message instead of the old `Cannot handle() before boot()`. This is intentional — the new message is more accurate.

### Branch / push
- Local branch `fix/p2-batch-security-correctness` is at `c1163e0`.
- `main` was temporarily moved forward by one commit during the initial commit (off-target branch); restored to `3fcc063` via `git reset --hard`.
- Remote `fix/p2-batch-security-correctness` is at `c1163e0` (pushed via the one-shot PAT URL; never persisted to git config).

### Items NOT touched (left for follow-up)
- `Kernel::boot()`'s match arm `KernelState::Handling => throw KernelException::handleDuringBoot()` — this scenario is "boot() called during handling", which is different from the recursive `handle()` case the task addressed. Left alone; would warrant a separate `bootDuringHandling()` exception if pursued.
- The `dglab_clone` submodule's modified content was pre-existing and not touched.
