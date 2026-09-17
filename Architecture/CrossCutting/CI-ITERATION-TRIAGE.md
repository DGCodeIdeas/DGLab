# CI Iteration Triage — PRs #193–#209

> Mini-cooldown retrospective: 17 PRs to get the FrankenPHP worker-mode
> Pulse trace working end-to-end. This document groups them into patterns,
> extracts lessons, and proposes preventive measures.

## Pattern A — Composer metadata gaps (3 PRs)

| PR | What | Root cause |
|---|---|---|
| #191 | PSR-4 autoload mappings missing for 12 SovereignStack packages | Root `composer.json` only had `App\` mapping |
| #192 | PSR package dependencies missing from root `require` | Per-package `require` not visible at root level |
| #193 | Path repo glob `packages/*/*` missed 3-level deep spokes | ADR-016 Internal/External split puts spokes at `packages/spoke/<internal\|external>/<name>/` |

**Lesson**: Composer metadata in a monorepo must be tested with an actual `composer install` from a clean state — not just `composer dump-autoload`. The path repository glob depth must match the actual package directory structure.

**Prevention**: Add a CI job that runs `composer install --dry-run` on a clean checkout and verifies all `require` entries resolve.

## Pattern B — Runtime code never exercised by tests (2 PRs)

| PR | What | Root cause |
|---|---|---|
| #195 | Vanguard contract regex `{3,128}` rejected `/` (1 char) | All tests used 3+ char contract IDs; `public/index.php` uses `/` |
| #197 | `public/index.php` imported `SovereignStack\Core\Providers\ProviderRegistry` (nonexistent) | PHPUnit tests use `TestKernelFactory` which correctly uses `EmptyProviderRegistry`; the production entry point was never executed end-to-end |

**Lesson**: Integration tests that pass don't prove the production entry point works. The test factory and `public/index.php` diverge silently. This is exactly what AGRD §4 catches — *"the actual synchronous-radial Pulse trace, not a diagram of it."*

**Prevention**: Add a smoke test that boots `public/index.php` directly (not via `TestKernelFactory`) and asserts `200 Hello World`. This would have caught both bugs at PR #156.

## Pattern C — FrankenPHP worker-mode behavioral differences (4 PRs)

| PR | What | Root cause |
|---|---|---|
| #199 | `set_exception_handler` never fires in worker mode | FrankenPHP intercepts exceptions before PHP's global handler |
| #202 | `frankenphp_handle_request()` needs a `while`/`for` loop | Without a loop, the worker handles one request then exits |
| #204 | Handler takes NO arguments; request from globals; emit via echo | Our handler had `function ($request)` — FrankenPHP passes nothing |
| #201 | Diagnostic instrumentation needed to trace where execution stops | `error_log()` goes to journald as JSON — good for tracing |

**Lesson**: FrankenPHP worker mode is NOT PHP-FPM. The handler signature, exception handling, and response emission are all different. The official docs are the canonical reference — guessing from FPM experience produces wrong code.

**Prevention**: Add a "FrankenPHP worker mode contract test" to CI that verifies: (1) handler takes 0 args, (2) `frankenphp_handle_request` is in a loop, (3) response is emitted via `echo` not `return`.

## Pattern D — Caddyfile directive confusion (3 PRs)

| PR | What | Root cause |
|---|---|---|
| #203 | `php_server` vs `php` — `php_server` bypasses the worker | FPM-like per-request execution instead of worker routing |
| #205 | Missing `try_files` — `/` resolved to directory, not `/index.php` | Removed `try_files` when changing `php_server` → `php` |
| #208 | Multi-line sed substitution broke `s///` command | Shell-expanded newlines split one sed command across lines |

**Lesson**: Caddy/FrankenPHP directives have subtle behavioral differences. `php_server` always does FPM-like; `php` uses the worker if configured. `try_files` is needed to resolve directory requests to files. Multi-line values in sed require the `r` (read-file) idiom, not inline `s|||`.

**Prevention**: Add a Caddyfile validation step to CI that runs `caddy validate --config <rendered Caddyfile>` after template rendering.

## Pattern E — Sed/shell scripting edge cases (2 PRs)

| PR | What | Root cause |
|---|---|---|
| #208 | Inlined multi-line variable in `s|||g` → "unterminated `s` command" | Shell expands newlines before sed sees them |
| #209 | `/r` + `/d` pattern matched comment line too → double insertion | Comment mentioned the token name: `# Token: __DEV_LOCALHOST_BLOCK__` |

**Lesson**: Shell scripting with sed has two traps: (1) multi-line values can't go in `s///` expressions, (2) pattern matching is substring-based, so comments mentioning token names also match. Use the `r` (read-file) idiom for multi-line values, and anchor patterns with `^...$` for exact-line matching.

**Prevention**: Add shellcheck to CI for all `.sh` files.

## Pattern F — PHP 8.4+ deprecations (1 PR)

| PR | What | Root cause |
|---|---|---|
| #206 | `array $server = null` deprecated in PHP 8.4+ | FrankenPHP uses PHP 8.5.10; implicit-nullable was deprecated in 8.4 |

**Lesson**: PHP 8.4+ deprecates implicit-nullable parameters (`array $x = null` → must be `?array $x = null`). The ErrorHandler converts deprecations to `ErrorException`, which surfaces as a 500 on first request (before the class is cached).

**Prevention**: Add `php -l` (lint) with `error_reporting=E_ALL` to CI on PHP 8.4+ to catch deprecations before merge.

## Pattern G — Infrastructure wiring (2 PRs)

| PR | What | Root cause |
|---|---|---|
| #207 | Tengine health check hit `/health` (403 default-deny) + Caddy on-demand TLS failed | Vanguard only has contract for `/`; `/_anvil/tls-allowed` not implemented |
| #209 | Sed pattern matched comment → double `localhost {}` block → Caddy ambiguous site error | `/r` + `/d` matched both the comment and the placeholder |

**Lesson**: Health checks must hit a registered contract path. On-demand TLS needs an ask-endpoint that returns 200. Dev mode needs self-signed certs (`tls internal`) not on-demand TLS.

**Prevention**: Add an integration test that boots all three services (Caddy → Tengine → FrankenPHP) and curls through the full stack.

## Summary — 7 patterns from 17 PRs

| Pattern | PRs | Root cause category |
|---|---|---|
| A | #191, #192, #193 | Composer metadata gaps |
| B | #195, #197 | Runtime code never tested |
| C | #199, #201, #202, #204 | FrankenPHP worker-mode differences |
| D | #203, #205, #208 | Caddyfile directive confusion |
| E | #208, #209 | Sed/shell scripting edge cases |
| F | #206 | PHP 8.4+ deprecations |
| G | #207, #209 | Infrastructure wiring |

## Proposed preventive CI measures

1. **Smoke test `public/index.php`** — boot it directly (not via TestKernelFactory), curl `/`, assert `200 Hello World`
2. **Composer install dry-run** — verify all `require` entries resolve from a clean checkout
3. **Caddyfile validation** — run `caddy validate` after template rendering
4. **Shellcheck** — lint all `.sh` files
5. **PHP lint on 8.4+** — `php -l` with `E_ALL` to catch deprecations
6. **Full-stack integration test** — Caddy → Tengine → FrankenPHP → curl → assert 200

These 6 measures would have caught 14 of the 17 bugs before they reached the user's server.
