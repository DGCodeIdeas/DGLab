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

---

## Task 44 — P3 Batch 1: Security & Error-Path Edge-Case Tests Across 4 Core Packages

**Date:** 2026-09-17
**Branch:** `fix/p3-batch1-security-and-error-paths`
**Commit SHA:** `2d5bd1b0363d2a66f7f992bb0d216b7e636c2285`
**Base:** `main` @ `1e3f683` (the PHPStan 2.x upgrade, PR #218)

### Packages touched
- `packages/core/http-message` — 7 edge-case tests (Uri port, Request method, Response status, Stream write/read)
- `packages/core/router` — 6 edge-case tests (HEAD fallback, root match, slash injection, query string, anonymous routes, empty path compile)
- `packages/core/kernel` — 7 edge-case tests in a NEW file `KernelEdgeCasesTest.php` (null pipeline, setPipeline Booted, handleDuringHandling state preservation, getRouter before boot, terminate after failed boot, boot bootstrapper throw, handle pipeline throw)
- `packages/core/container` — 5 edge-case tests (empty id, positional params, instance replaces singleton, re-bind invalidates cache, addCompilerPass after compile)

### Test changes

#### `packages/core/http-message/tests/Unit/UriTest.php` (+2 tests)
- `testWithPortNullClearsPort` — `withPort(null)` clears the port; the resulting Uri reports `null` from `getPort()` and the authority omits the port. Original is unchanged (immutability).
- `testWithPortRejectsNegativePort` — `withPort(-1)` throws `InvalidArgumentException` (filterPort enforces 1..65535).

#### `packages/core/http-message/tests/Unit/RequestTest.php` (+1 test, SECURITY GAP)
- `testWithMethodAcceptsSpaceInValueDocumentsSecurityGap` — documents that `withMethod('GET DELETE')` (a method with an embedded space) is silently accepted and stored uppercased. RFC 9110 §4.2 defines `method = token` and the `tchar` production EXCLUDES SP — a conformant implementation should reject this. Flagged as a security gap with a `@todo` annotation; when validation is added, the assertion should flip to `expectException`.

#### `packages/core/http-message/tests/Unit/ResponseTest.php` (+2 tests, 1 SECURITY GAP)
- `testRejectsNegativeStatusCode` — `new Response(-1)` throws `InvalidArgumentException` (the 100..599 guard catches negative values). Complements the existing `testRejectsStatusCodeBelow100` (which uses 99, just below the boundary).
- `testWithStatusAcceptsCrlfInReasonPhraseDocumentsSecurityGap` — documents that `withStatus(200, "OK\r\nInjected: yes")` is silently accepted; the CRLF is stored verbatim in the reason phrase. This is a SECURITY GAP (CWE-113 response splitting): the reason phrase is emitted on the status line ("HTTP/1.1 200 <reason>"), and an injected `\r\n` would let an attacker forge a second status line. Flagged with `@todo` for the CRLF-validation fix.

#### `packages/core/http-message/tests/Unit/StreamTest.php` (+2 tests)
- `testReadAtEofReturnsEmptyStringWithoutThrowing` — at EOF, `fread()` returns `''` (empty string, NOT `false`). The `Stream::read()` guard checks `=== false` only, so reads at EOF are safe no-ops returning `''`. Callers that loop `while (!$stream->eof()) { read(); }` rely on this contract.
- `testWriteEmptyStringReturnsZeroBytesWithoutError` — `fwrite()` of an empty string returns `0` (a legitimate success, not `false`). The P2 fix already changed the guard from `=== false || === 0` to `=== false` only, so a 0-byte write returns `0` without throwing.

#### `packages/core/router/tests/Unit/RouterTest.php` (+5 tests, 1 documents a gap)
- `testMatchHeadRequestWhenOnlyGetRegisteredReturnsNullDocumentsGap` — when only GET is registered, a HEAD request returns `null` (no HEAD→GET fallback per RFC 9110 §9.2.2). Callers must register HEAD explicitly alongside GET. Flagged as a gap with a comment.
- `testMatchRootPathMatchesRootRoute` — a route declared with path `'/'` matches a request to `'/'`. The trailing-slash normalizer skips the rtrim for the root path (`if ($path !== '/' && ...)`).
- `testGenerateUrlPercentEncodesSlashInParameterValue` — a parameter value containing `'/'` is percent-encoded (`'/'` → `'%2F'`), preventing path-segment injection. E.g. `id='42/secret'` produces `/users/42%2Fsecret`, NOT `/users/42/secret` (which would let the value escape its segment).
- `testGenerateUrlAppendsExtraParametersAsQueryString` — parameters NOT matching a `{placeholder}` in the route path are appended as an RFC-3986 query string via `http_build_query(..., PHP_QUERY_RFC3986)`.
- `testGenerateUrlMergesLeftoverParametersAndQueryArray` — leftover `$parameters` (not consumed by placeholder substitution) AND the explicit `$query` array are BOTH appended; leftover comes first, then query.

#### `packages/core/router/tests/Unit/RouteCollectionTest.php` (+1 test, KNOWN-FAILING)
- `testAllReturnsAllRoutesIncludingAnonymous` — asserts the DESIRED behavior: `all()` returns all 3 routes (named + anonymous) in registration order. **FAILS on main**: the current implementation returns `array_values($this->byName)` which excludes anonymous routes (empty-name routes are indexed only in `$byMethod`, not `$byName`). The P2 fix on the unmerged branch `fix/p2-batch-remaining-final` (commit `b57acf5`) adds a `$byPosition` tracking array to address this; merging that branch will make this test pass.

#### `packages/core/router/tests/Unit/RouteCompilerTest.php` (+1 test)
- `testEmptyPathCompilesToEmptyRegexAnchored` — `compile()` of a route with path `''` produces `'#^$#u'` (anchored empty regex). This regex matches ONLY the empty string `''`, NOT `'/'` or any non-empty path. Sanity-checks the regex behavior with two `preg_match` assertions.

#### `packages/core/kernel/tests/Unit/KernelEdgeCasesTest.php` (NEW FILE, +7 tests)
- `testHandleThrowsLogicExceptionWhenPipelineIsNotConfigured` — kernel booted WITHOUT an HttpBootstrapper (no-op bootstrapper); `handle()` throws `\LogicException` with message "Middleware pipeline is not configured" (the null-check sits AFTER the state guard but BEFORE `$this->state = Handling`, so state stays Booted).
- `testSetPipelineDuringBootedThrowsKernelException` — `setPipeline()` only callable during Booting; calling from Booted throws `KernelException` with a message naming the offending state (`...Current state: booted`). Uses `expectExceptionMessageMatches` with a regex.
- `testHandleDuringHandlingThrowsAndPreservesHandlingState` — complementary to `KernelStateMachineTest::testHandleDuringHandlingThrows`. Uses try/catch to verify BOTH the exception (with the "Cannot handle() while already handling" message) AND that state is PRESERVED as Handling (the match arm throws BEFORE the try/finally could reset state to Booted).
- `testGetRouterBeforeBootThrowsAccessBeforeBoot` — `getRouter()` on an Unbooted kernel throws `KernelException::accessBeforeBoot()` with the service-access-specific message "Cannot access kernel services before boot()".
- `testTerminateAfterFailedBootThrowsDoubleTerminate` — a throwing bootstrapper causes `boot()` to catch, set state=Terminated, and re-throw. Subsequent `terminate()` hits the `KernelState::Terminated => throw doubleTerminate()` arm.
- `testBootFailureTransitionsStateToTerminatedAndReleasesReferences` — verifies the state transition to Terminated after a failed boot, AND that subsequent `handle()` throws `handleAfterTerminate` (proving the Terminated state is enforced).
- `testHandleRecoversToBootedWhenPipelineThrows` — replaces the pipeline via reflection with a throwing stub (anonymous class implementing `MiddlewarePipelineInterface`). Calls `handle()` in try/catch; verifies the RuntimeException propagates AND the finally block resets state to Booted (one bad request cannot wedge the worker).

#### `packages/core/container/tests/Unit/ContainerTest.php` (+4 tests)
- `testBindWithEmptyStringIdAcceptedDocumentsGap` — `bind('', 'UTC')` is accepted (no validation); `has('')`, `hasDefinition('')`, and `make('')` all work. Flagged as a potential gap (two binds to `''` silently collide; `has('')` returning true can surprise callers).
- `testMakeAcceptsPositionalParameterOverrides` — `make(WithParams::class, [0 => 'positionalValue'])` exercises the `array_key_exists($position, $parameters)` arm in `autowire()` (the second lookup after the by-name lookup).
- `testInstanceSupersedesAlreadyResolvedSingletonCache` — singleton resolved (cached in `$instances`); `instance()` with a new object overwrites the cache; subsequent `make()` returns the NEW instance.
- `testRebindingSameIdInvalidatesSingletonCache` — singleton resolved (cached); `singleton()` re-bind for the same id `unset`s `$instances[$id]` before storing; subsequent `make()` resolves a FRESH instance (different identity from the first).

#### `packages/core/container/tests/Unit/CompileTest.php` (+1 test)
- `testAddCompilerPassAfterCompileThrowsLogicException` — after `compile()`, `addCompilerPass()` is guarded by `assertNotCompiled()` and throws `\LogicException` with the same message as bind-after-compile ("Cannot modify the container after it has been compiled.").

### Verification
- Pre-flight `git diff --cached | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — clean.
- Post-commit `git show HEAD | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — clean.
- Post-push `git ls-remote` confirms the remote branch is at `2d5bd1b`; remote `main` is unchanged at `1e3f683`.
- PHP runtime was not available in the sandbox (no `php`, `apt-get install` blocked by perms) — PHPUnit tests were not executed locally. All 25 tests verified by careful code-trace review against the existing source implementations.

### Tests that may fail (in CI)
1. **`RouteCollectionTest::testAllReturnsAllRoutesIncludingAnonymous`** — asserts the DESIRED behavior (anonymous routes included in `all()`); fails on main because the current implementation returns only named routes. Will pass once the P2 fix on `fix/p2-batch-remaining-final` (commit `b57acf5`, adds `$byPosition` tracking) is merged into main.
2. **All other 24 tests should pass** — they were written by tracing the actual source code line-by-line, and assert either current behavior (with comments flagging gaps) or exception paths that the source explicitly throws.

### Branch / push
- Local branch `fix/p3-batch1-security-and-error-paths` is at `2d5bd1b`.
- Initial commit accidentally landed on `main` (the `git checkout -b` from earlier did not persist due to a shell session state issue); recovered via `git branch -f fix/p3-batch1-security-and-error-paths 2d5bd1b && git checkout fix/p3-batch1-security-and-error-paths && git branch -f main 1e3f683`, then force-pushed (with `--force-with-lease` against the previously-pushed `1e3f683`) to update the remote from the base commit to `2d5bd1b`. `main` was never force-pushed and remains at `1e3f683`.
- Remote `fix/p3-batch1-security-and-error-paths` is at `2d5bd1b` (pushed via the one-shot PAT URL; never persisted to git config).

### Items NOT touched (left for follow-up)
- The two SECURITY-GAP tests (#3 `Request::withMethod` space; #4 `Response::withStatus` CRLF in reason phrase) document current behavior with `@todo` annotations. They PASS today; flipping them to `expectException` requires source-side validation fixes (a future P4 batch).
- The `Kernel::boot()` `handleDuringBoot()` arm (boot called during handling) was not exercised — same scope as Task 42's follow-up item.
- The `dglab_clone` submodule's modified content was pre-existing and not touched.
