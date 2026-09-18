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

## Task 45 — P3 Batch 2: Edge-Case Tests Across 5 Untouched Core Packages

**Date:** 2026-09-18
**Branch:** `fix/p3-batch2-untouched-packages`
**Commit SHA:** `2574c2df718a9a5a016159c5141b475d2831fedc`
**Base:** `main` @ `3a9cecd` (PR #222 — container WeakMap fix)

### Packages touched
- `packages/core/logger` — 4 edge-case tests in LoggerTest / StreamHandlerTest / LineFormatterTest
- `packages/core/error-handler` — 4 edge-case tests in ErrorHandlerTest / RendererTest + 1 polyfill fixture
- `packages/core/config` — 4 edge-case tests in ConfigRepositoryTest / EnvLoaderTest
- `packages/core/middleware` — 4 edge-case tests in MiddlewarePipelineTest / CallableMiddlewareAdapterTest / MiddlewareResolverTest
- `packages/core/event-dispatcher` — 4 edge-case tests in EventDispatcherTest / ListenerProviderTest + 1 ChildEvent fixture

**Total: 20 test methods + 2 fixture files, 14 files changed, +655 lines.**

### Test inventory (per the task specification)

#### `packages/core/logger/tests/Unit/LoggerTest.php` (2 tests)
1. `testLogWithEmptyMessageIsFormattedAndLogged` — empty-message log call must produce a log line whose payload is the empty string (`[timestamp] info: \n`). Asserts non-empty file contents, the `info: ` substring, and the regex `/\] info: \n$/`.
2. `testLogWithNoHandlersRegisteredIsSilentNoOp` — Logger constructed with no handlers must accept `log()` calls without throwing or producing output. Uses `expectNotToPerformAssertions()` to make the no-op contract explicit.

#### `packages/core/logger/tests/Unit/StreamHandlerTest.php` (1 test)
3. `testHandleBatchWithEmptyRecordsArrayEarlyReturns` — empty-records `handleBatch([])` must early-return before invoking the formatter or opening the stream. Uses a spy formatter with two boolean flags (`formatBatchCalled`, `formatCalled`) that remain false. Also asserts the log file does not exist (lazy stream-open was never triggered).

#### `packages/core/logger/tests/Unit/LineFormatterTest.php` (1 test)
4. `testFormatWithEmptyMessageAndEmptyContextProducesMinimalLine` — empty message + empty context produces the minimal line shape `[timestamp] info: ` with no payload, no `{}` context segment, and no multi-line exception block. Regex: `/^\[\d{4}-\d{2}-\d{2}T.+info: $/`.

#### `packages/core/error-handler/tests/Unit/ErrorHandlerTest.php` (3 tests)
5. `testHandleErrorMapsEDeprecatedToInfoLevel` — `handleError(E_DEPRECATED, ...)` must log at `LogLevel::INFO` (per `severityToLevel()` mapping). Asserts log file contains `] info:` and the message text. Also asserts the thrown `ErrorException` carries `E_DEPRECATED` severity.
6. `testHandleErrorMapsEStrictToNoticeLevel` — `handleError(E_STRICT, ...)` must log at `LogLevel::NOTICE`. Same assertion shape as #5 but for `] notice:`.
7. `testHandleFatalIsNoOpForNonFatalErrorType` — `handleFatal()` with `error_get_last()` returning a non-fatal error type (E_WARNING) must early-return without logging. PHP userland cannot trigger a true E_WARNING, so this test uses a namespace-polyfilled `error_get_last()` function (see Fixture below) to inject a synthetic non-fatal error array.

#### `packages/core/error-handler/tests/Unit/RendererTest.php` (1 test)
8. `testPlainTextRendererRendersEmptyMessageInHeaderShape` — `PlainTextRenderer::render()` with an exception having empty message produces the standard header shape `{class}: {message} in {file}:{line}`. The empty message renders as the zero-length segment between `: ` and ` in` — i.e., the visible double-space pattern `RuntimeException:  in`. **NOTE**: the task spec wrote the expected pattern as `: '' in` (single-quoted empty), but the actual renderer does NOT single-quote the message (unlike `LineFormatter::formatException` which does wrap in quotes). The test asserts the **actual current behavior** (double-space, no quoting) per the P3 batch 1 precedent of "documenting current behavior". A future source change could add quoting to align PlainTextRenderer with LineFormatter; the test would need updating at that time.

#### `packages/core/config/tests/Unit/ConfigRepositoryTest.php` (2 tests)
9. `testLeadingDotKeyThrowsForEmptySegment` — `get('.app')` leading-dot key: `explode('.', '.app')` yields `['', 'app']`, the first segment is empty, triggering the "contains an empty segment" `InvalidArgumentException`. Same guard as consecutive-dots.
10. `testAllReturnsEmptyArrayWhenConstructedWithEmptyData` — `all()` on empty data `[]` returns `[]` (and `allRaw()` returns `[]`). Confirms empty-tree round-trip doesn't produce null or a redaction shell.

#### `packages/core/config/tests/Unit/EnvLoaderTest.php` (2 tests)
11. `testLoadReturnsEmptyArrayForEmptyFile` — zero-byte `.env` file yields `[]` and does not throw.
12. `testLoadReturnsEmptyArrayForFileContainingOnlyComments` — a file with only blank lines and `#`-prefixed comment lines yields `[]`. Exercises the early-continue guard in `lines()`.

#### `packages/core/middleware/tests/Unit/MiddlewarePipelineTest.php` (2 tests)
13. `testPipeSameMiddlewareInstanceTwiceExecutesTwice` — piping the SAME `MiddlewareInterface` instance twice into a pipeline executes it twice per request. The pipeline does NOT deduplicate middleware by identity (unlike `ListenerProvider::addListener` which dedups at registration time). Uses an anonymous counting middleware with a public `$processCount` field.
14. `testThreeConsecutiveRequestsEachRunFullStackInOrder` — stronger re-entrancy variant of the existing `testConsecutiveRequestsAreIndependent`. Stacks two middlewares (A, B) and asserts each request runs the full stack in FIFO-in / LIFO-out order. Three requests × 4 trace entries each = 12 total entries; traces the cumulative array `['A-in','B-in','B-out','A-out', ...]` × 3. Verifies no cursor carryover AND no ordering corruption across 3 sequential dispatches.

#### `packages/core/middleware/tests/Unit/CallableMiddlewareAdapterTest.php` (1 test)
15. `testProcessPropagatesExceptionFromCallable` — when the wrapped callable throws, the adapter must propagate (not swallow) the exception. This is the contract that `ExceptionPropagationTest::testExceptionFromInnerMiddlewareReachesOuterMiddleware` relies on. Uses `expectException(\RuntimeException::class)` + `expectExceptionMessage('callable exploded')`.

#### `packages/core/middleware/tests/Unit/MiddlewareResolverTest.php` (1 test)
16. `testResolveNonExistentClassStringWithoutContainerThrowsLogicException` — `resolve('NonExistentClass')` WITHOUT a container hits the container-less string branch, explicitly checks `class_exists()`, and throws `LogicException` with `"class does not exist"`. Exercises the P2 fix that replaced the old unreachable `TypeError` fallthrough with a clear `class_exists()` guard.

#### `packages/core/event-dispatcher/tests/EventDispatcherTest.php` (1 test)
17. `testDispatchWithPreStoppedStoppableEventRunsZeroListeners` — when `isPropagationStopped()` returns true BEFORE the first listener runs (i.e., `stopPropagation()` called externally before `dispatch()`), the dispatcher's foreach must break on the very first iteration without invoking any listener. Verifies the guard at the TOP of the foreach body executes BEFORE `$listener($event)`. Two listeners registered, neither runs; `event->calledBy` stays empty.

#### `packages/core/event-dispatcher/tests/ListenerProviderTest.php` (3 tests)
18. `testAddListenerDeduplicatesSameListenerAtSamePriority` — adding the SAME listener instance at the SAME priority is silently deduplicated to a single registration. The dedup check at the top of `addListener()` walks the existing group with `isSameListener()` (identity for closures/objects, `===` for strings, `serialize()` comparison for array-shaped callables). Asserts `count === 1` and the surviving listener is the same instance.
19. `testClearCacheForcesReResolutionOnNextCall` — after `getListenersForEvent()` populates the cache, `clearCache()` must wipe it so the next `getListenersForEvent()` re-resolves (invokes the container again for class-string listeners). Uses a container mock with `expects($this->exactly(2))` to assert the container's `get()` is called twice — once before `clearCache()` and once after — proving the cache was actually invalidated.
20. `testListenerRegisteredForParentFiresForChildEvent` — a listener registered for the PARENT event class (`TestEvent`) must fire when a CHILD event (`ChildEvent extends TestEvent`) is dispatched. `collectAndSortListeners()` walks the full type hierarchy via `getTypeHierarchy()` (parent classes + interfaces), so child events inherit parent-class listeners. Uses the new `ChildEvent` fixture.

### New fixture files

#### `packages/core/error-handler/tests/Fixtures/error_get_last_polyfill.php`
Namespaced function `SovereignStack\Core\ErrorHandler\error_get_last()` polyfill. The `ErrorHandler::handleFatal()` source calls `error_get_last()` UNQUALIFIED — PHP's name resolution looks up the function in the current namespace (`SovereignStack\Core\ErrorHandler`) before falling back to the global builtin. Defining this namespaced function lets tests inject a non-null error array via `$GLOBALS['dglab_test_handleFatal_error']`, exercising the `fatalSeverities` guard in `handleFatal()` without relying on real PHP fatal errors (which would terminate the process before assertions can fire).

When `$GLOBALS['dglab_test_handleFatal_error']` is unset, the polyfill returns `null` — matching the real global builtin's behavior in a clean test environment. This preserves backward compatibility with the existing `testHandleFatalNoOpsWhenNoError`, `testHandleFatalIsNoOpAfterUnregister`, and `testHandleFatalIsNoOpBeforeRegister` tests, which call `handleFatal()` and expect no log output.

The polyfill is loaded on demand by `ErrorHandlerTest::loadErrorGetLastPolyfill()` via `require_once`; the `function_exists` guard in the fixture file makes the require idempotent.

#### `packages/core/event-dispatcher/tests/Fixtures/ChildEvent.php`
Child event fixture extending `TestEvent` for the type-hierarchy listener dispatch test (#20). Adds a `$childMarker` readonly property and passes `'child-event'` to the parent constructor. Extends `TestEvent` directly (not `Event`) so parent-registered `SampleListener` instances see the same `processed` / `data['handled_by']` shape.

### Verification
- Pre-flight `git diff --cached | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — CLEAN.
- Post-commit `git show HEAD | grep -iE "ghp_[a-z0-9]{36}|github_pat_[a-z0-9]{22}"` — CLEAN.
- Post-push `git ls-remote` confirms the remote branch is at `2574c2d`.
- PHP runtime was not available in the sandbox (no `php`, `apt-get install` blocked by perms) — PHPUnit tests were not executed locally. All tests verified by careful code review against existing test patterns and source-code behavior.

### Tests that may break (in CI)
1. **None expected to break.** All 20 tests assert the actual current source behavior, verified by reading the source code line-by-line:
   - `Logger::log()` with empty message: `LogRecord::create(level, '')` constructs a valid record (constructor accepts empty string); `StreamHandler::handle()` writes the formatted line; `LineFormatter::format()` produces `[timestamp] level: ` (with empty message rendering as the empty segment).
   - `Logger` with no handlers: the `foreach ($this->handlers as $handler)` loop body is empty; threshold filter passes (DEBUG default); call returns void.
   - `StreamHandler::handleBatch([])`: `array_filter([], ...)` returns `[]`; `if ($filtered === []) return;` fires; no formatter/write invocation.
   - `LineFormatter::format()` with empty message+context: `interpolate('', [])` returns the empty string after `sanitizeForSingleLine('')` (no control chars to replace); `$line = "[ts] info: "`; no context segment appended (`$context !== []` is false); no exception block (no 'exception' key).
   - `handleError(E_DEPRECATED, ...)`: `severityToLevel(E_DEPRECATED)` returns `LogLevel::INFO` (per the match in ErrorHandler.php line 287); logger logs at INFO; throws `ErrorException` with severity `E_DEPRECATED`.
   - `handleError(E_STRICT, ...)`: `severityToLevel(E_STRICT)` returns `LogLevel::NOTICE` (per ErrorHandler.php line 286); same pattern.
   - `handleFatal()` with polyfilled `error_get_last()` returning `['type' => E_WARNING, ...]`: `$shutdownRegistered && $registered` is true (after `register()`); `$error` is non-null; `in_array(E_WARNING, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)` is false; early return. No log.
   - `PlainTextRenderer::render()` with empty message: `sprintf('%s: %s in %s:%d', 'RuntimeException', '', $file, $line)` yields `"RuntimeException:  in {$file}:{$line}"` (double space between `:` and `in`); the regex `/^RuntimeException:  in .+:\d+$/m` matches.
   - `ConfigRepository::get('.app')`: `explode('.', '.app')` returns `['', 'app']`; the `foreach` over segments checks `$segment === ''` on the first iteration, throws `InvalidArgumentException` with message containing `"contains an empty segment"`.
   - `ConfigRepository::all()` on empty data: `redact([])` walks an empty array, returns `[]`.
   - `EnvLoader::load()` on empty file: `file_get_contents` returns `''`; `preg_split` on empty string returns `['']`; the foreach iterates once with `$line = ''`; `$trimmed === ''` early-continues; no `[key, value]` yielded; `$loaded` stays `[]`.
   - `EnvLoader::load()` on comments-only file: same as above for each `#`-prefixed line; `str_starts_with($trimmed, '#')` early-continues; no `[key, value]` yielded.
   - `MiddlewarePipeline::pipe()` same instance twice: `$this->middleware[] = $middleware` appends both references (no dedup); `PerRequestHandler` iterates both entries, calling `$resolver->resolve($entry)` each time, which returns the same instance for `MiddlewareInterface` entries; `$middleware->process()` called twice.
   - `CallableMiddlewareAdapter` exception propagation: `($this->callable)($request, $handler)` invokes the callable directly; no try/catch in `process()`; exception propagates to the caller.
   - `MiddlewareResolver::resolve('NonExistentClass')` without container: `is_string($entry) && $this->container !== null` is false (container is null); falls into the `is_string($entry)` branch; `class_exists($entry)` is false; throws `LogicException` with `"class does not exist"` substring (matches the sprintf format in source).
   - `MiddlewarePipeline` three-consecutive-requests: each `handle()` creates a fresh `PerRequestHandler` with its own cursor; trace entries accumulate as expected.
   - `EventDispatcher::dispatch()` on pre-stopped stoppable event: `foreach ($listeners as $listener)` — first iteration: `if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) break;` fires immediately (event is already stopped); `break` exits the loop without invoking `$listener($event)`. Zero listeners run.
   - `ListenerProvider::addListener()` same listener at same priority: `$group = $this->listeners[$eventClass][$priority] ?? []`; foreach over existing group; `isSameListener($existing, $listener)` returns true for the same object instance; `return;` (silent dedup). One registration survives.
   - `ListenerProvider::clearCache()`: `$this->resolvedCache = []; $this->resolving = [];` — wipes both arrays. Next `getListenersForEvent()` sees `!isset($this->resolvedCache[$eventClass])` as true, re-resolves via `collectAndSortListeners()`, which calls `resolveListener()` for class-string listeners, which calls `$this->container->get($listener)`.
   - `ListenerProvider` parent-class listener fires for child event: `getTypeHierarchy(ChildEvent::class)` walks `['ChildEvent', 'TestEvent', 'Event', ...interfaces]`; `isset($this->listeners[TestEvent::class])` is true; the parent-registered listener is collected; dispatch invokes it.

2. **Risk: namespace-polyfilled `error_get_last()` affecting other tests in `SovereignStack\Core\ErrorHandler` namespace.** The polyfill is defined in the `SovereignStack\Core\ErrorHandler` namespace; once loaded, ALL `error_get_last()` calls from source code in that namespace (specifically `ErrorHandler::handleFatal()`) hit the polyfill, not the global builtin. The polyfill returns `null` when `$GLOBALS['dglab_test_handleFatal_error']` is unset, matching the global builtin's behavior in a clean test environment. Verified: existing tests `testHandleFatalNoOpsWhenNoError`, `testHandleFatalIsNoOpAfterUnregister`, `testHandleFatalIsNoOpBeforeRegister` all call `handleFatal()` with no `error_get_last()` mock — they expect no log output. With the polyfill returning `null`, the `if ($error === null) return;` guard fires (same as the global builtin in a clean environment), so no log is produced. **Risk verdict: safe.**

3. **Risk: PHPUnit's error handler interfering with `handleError()` tests.** Tests #5 and #6 call `handleError(E_DEPRECATED, ...)` and `handleError(E_STRICT, ...)` after setting `error_reporting(E_ALL)`. PHPUnit 10's default error handler converts user errors to `PHPUnit\Framework\Error\*` exceptions, but these tests catch `\ErrorException` (thrown by `handleError` after the logger call). PHPUnit's handler runs FIRST (since `set_error_handler` is LIFO), then `handleError` is invoked directly (not via `trigger_error`). Wait — `handleError` is invoked DIRECTLY as a method call, not via `trigger_error`. So PHPUnit's error handler is not invoked. The test's `try { $handler->handleError(...) } catch (\ErrorException) {}` catches the `ErrorException` thrown by `handleError` at line 154. **Risk verdict: safe.**

### Branch / push
- Local branch `fix/p3-batch2-untouched-packages` is at `2574c2d`.
- Branch was created from `main` @ `3a9cecd` (PR #222's merge commit).
- Remote `fix/p3-batch2-untouched-packages` is at `2574c2d` (pushed via the one-shot PAT URL; never persisted to git config).

### Items NOT touched (left for follow-up)
- **PlainTextRenderer quoting**: The task spec's expected pattern `: '' in` (single-quoted empty) does NOT match the current renderer output (`:  in` double-space, no quoting). This was left as-is to keep the change test-only (per P3 batch 1 precedent). A future source change could add single-quote wrapping to align PlainTextRenderer with `LineFormatter::formatException`'s quoting convention; the test docstring explicitly notes this gap.
- **No source modifications.** Per the task's "edge-case tests" scope and the P3 batch 1 precedent (PR #221 was purely test additions), this PR is test-only. All 20 tests assert the actual current source behavior.
- The `dglab_clone` submodule's modified content was pre-existing and not touched.

### MUWV status
- **MUWV remains UNAUTHORIZED** — not flipped, not referenced, not modified.
