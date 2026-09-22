# Z.ai - Advanced AI Chatbot & Agent powered by GLM-5.2

**Exported:** 8/3/2026 04:45:36  

## Prompt:
Public Repo: DGCodeIdeas/DGLab Analyze the above files and existing DGLab blueprints to identify specific inaccuracies, omissions, and structural weaknesses. Based on this critique, generate a comprehensive set of high-fidelity architectural blueprints for the DGLab system that address all previous shortcomings. The new blueprints should feature significantly increased detail, improved structural integrity, optimized component integration, and a much higher level of technical sophistication compared to the original version.

## Response:
<thinking> The user wants me to reason with maximum effort and output a thinking block. The context is extensive: an existing critique of the DGLab blueprints (13 findings) plus a previous response from "Kimi" that produced a set of high-fidelity exemplar blueprints addressing those findings.

Kimi's previous delivery covered:

*   00\_CRITIQUE.md (13 findings)
*   01\_MASTER\_INDEX.md (governance, corrected maps)
*   Exemplars: CORE-01, CORE-02, CORE-03, HUB-01, HUB-02, HUB-04, BRIDGE-01, ISPOKE-01, ESPOKE-01, DEPLOY-01

The user's final message is "Please help me:" which is an open-ended prompt to continue the work. Given the scope of the original request ("a comprehensive set of high-fidelity architectural blueprints"), the natural next step is to produce the next batch of high-fidelity blueprints to fill out the remaining ~110 documents. Kimi explicitly recommended "the full Hub tier" as the next batch.

Let me identify the highest-value next batch. Looking at the dependency graph and critical path:

**Critical-path Core gaps (block everything else):**

*   CORE-08 (Error Handler) — blocks CORE-18
*   CORE-09 (Logging) — used everywhere; blocks HUB-06
*   CORE-10 (Config) — blocks HUB-01; needed by CORE-18
*   CORE-04 (PSR-7 HTTP Message) — blocks CORE-05, CORE-06, BRIDGE-01
*   CORE-05 (PSR-15 Middleware) — blocks CORE-06, BRIDGE-01
*   CORE-06 (Attribute Router) — blocks BRIDGE-01, all Spokes
*   CORE-16 (Binary Encryption Envelope) — referenced by BRIDGE-01 (the corrected cross-ref), blocks HUB-04, HUB-02
*   CORE-17 (Service Provider System) — defines the boot contract; blocks CORE-18
*   CORE-18 (Core Kernel & Lifecycle) — the actual application bootstrapper
*   CORE-19 (DBAL) — blocks HUB-04, HUB-06, HUB-01 (for tenant overrides)

**Hub tier gaps (high-value, referenced by exemplars):**

*   HUB-06 (Audit) — referenced by HUB-04, BRIDGE-01, ISPOKE-01
*   HUB-11 (Queue) — explicitly called out as "sparse" in SOLUTIONS\_TO\_WEAKNESSES
*   HUB-15 (Health) — referenced by DEPLOY-01, ISPOKE-01

**Deploy tier gaps:**

*   DEPLOY-02 (Datastore Provisioning) — referenced by DEPLOY-01
*   DEPLOY-03 (Bridge & External Spoke Deployment) — referenced by BRIDGE-01 for network policy
*   DEPLOY-04 (Promotion Pipeline) — referenced by DEPLOY-01

I should produce these at the same fidelity bar as Kimi's exemplars:

1.  Real PHP interface contracts (not prose-only)
2.  Corrected dependency lists with explicit "Resolves" lines
3.  Explicit benchmark methodology per Governance Rule 2 (no bare millisecond claims)
4.  CI verification criteria
5.  Security properties where relevant
6.  Integration strategies with actual code examples
7.  Database schemas where relevant
8.  Sequence diagrams where they add value

Let me write each one with sufficient depth. I need to be careful to:

*   Maintain internal consistency with Kimi's exemplars (same namespaces, same patterns, same governance rules)
*   Address Finding 11 by actually integrating the SOLUTIONS\_TO\_WEAKNESSES content into the relevant blueprints (HUB-11 in particular)
*   Address Finding 10 by never making a bare performance claim
*   Address Finding 3 by ensuring all cross-references use the corrected CORE-16 (not CORE-09) for cryptography
*   Make CORE-02 the explicit blocker for everything that depends on it

Let me structure this as "PART V: NEXT-BATCH HIGH-FIDELITY BLUEPRINTS" continuing the numbering from Kimi's "PART IV: SUMMARY OF RESOLUTIONS".

I'll write each blueprint with:

*   Tier
*   Resolves (which findings)
*   Component Name (with namespace)
*   Description
*   Build Status (with explicit blockers)
*   Dependency Status (upward/downward)
*   Architectural Design (interface contracts, class maps, schemas, sequence diagrams)
*   Integration Strategy
*   Benchmark & Verification Methodology (with explicit method, not bare numbers)
*   CI Verification Criteria
*   SemVer Impact

Let me begin writing. I'll aim for the same level of technical sophistication as Kimi's exemplars — real PHP 8.3 code, real interface contracts, real SQL schemas, real Mermaid diagrams.

For CORE-08 (Error Handler), I need to define:

*   ErrorHandlerInterface
*   How it integrates with CORE-09 (Logging) and CORE-03 (Event Dispatcher)
*   The shutdown function for fatal errors
*   The exception-to-PSR-7-response conversion (which means it has a soft dependency on CORE-04)
*   A "display vs log" mode for dev vs prod

For CORE-09 (Logging), I need to define:

*   A PSR-3 implementation with structured logging
*   Log context enrichment (request ID, tenant ID, user ID)
*   Multiple handlers (file, syslog, stderr for containers)
*   Log levels and sampling for high-volume channels
*   Integration with CORE-03 (events) for log event dispatching

For CORE-10 (Config), I need to define:

*   Environment loader (.env, getenv, $\_SERVER)
*   Multi-format config loader (PHP, JSON, YAML)
*   Schema validation (a key absent from defaults is an error)
*   Immutable ConfigRepository after freeze
*   Type coercion (string env vars → typed values)

For CORE-04 (PSR-7), I need to define:

*   PSR-7 Message, Request, Response, ServerRequest, Stream, URI, UploadedFile
*   PSR-17 Factory interfaces
*   This is largely about declaring conformance to PSRs and providing a concrete implementation

For CORE-05 (PSR-15), I need to define:

*   MiddlewareInterface and RequestHandlerInterface
*   A MiddlewarePipeline that composes middleware
*   A FinalHandler that delegates to the router (CORE-06) or returns 404

For CORE-06 (Router), I need to define:

*   Attribute-based route declaration (PHP 8 attributes)
*   Route compilation (compile path templates to regex)
*   Route matching against a PSR-7 ServerRequest
*   Route parameters extraction
*   Integration with CORE-05 (returns a RequestHandler that the pipeline dispatches to)

For CORE-16 (Encryption), I need to define:

*   Symmetric encryption (AES-256-GCM) for cache values, config secrets
*   Asymmetric signing (ES256 / Ed25519) for JWTs
*   Key envelope format (key ID, algorithm, wrapped key)
*   Key rotation support
*   The "Binary Encryption Envelope" is the key-wrapping format that lets keys be stored at rest encrypted by a master key

For CORE-17 (Service Providers), I need to define:

*   ServiceProviderInterface (register, boot)
*   The boot sequence: register all → compile container → boot all
*   Deferred providers (only register if a dependency is requested)
*   Publishing (configs, assets, migrations) — though "migrations" is more of a DB concern

For CORE-18 (Kernel), I need to define:

*   KernelInterface
*   The boot lifecycle: bootstrap → register providers → compile container → boot providers → handle request → terminate
*   Environment enum
*   The kernel is the only thing the entry point (public/index.php) calls

For CORE-19 (DBAL), I need to define:

*   Connection abstraction (PDO-based)
*   Query builder (fluent)
*   Prepared statement parameter binding (named and positional)
*   Transaction management with savepoints
*   Repository pattern base class
*   Tenant-scoping hook (integrates with HUB-04's TenantContext)

For HUB-06 (Audit), I need to define:

*   AuditableEventInterface
*   Audit log storage (append-only, with cryptographic chain for tamper-evidence)
*   Audit log query API (with retention policies)
*   Streaming via SSE (referenced by ISPOKE-01)

For HUB-11 (Queue), I need to define:

*   Queue abstraction (Redis, SQS, in-memory for tests)
*   Job interface (handle, retry, backoff)
*   Worker process model
*   Dead letter queue
*   This addresses the "sparse" critique in SOLUTIONS\_TO\_WEAKNESSES

For HUB-15 (Health), I need to define:

*   HealthCheckInterface
*   Aggregated health endpoint
*   Liveness vs readiness distinction
*   Integration with orchestrator (K8s probes)

For DEPLOY-02, I need to define:

*   Database provisioning (MySQL/Postgres)
*   Redis provisioning
*   Queue broker provisioning
*   Backup/restore runbook
*   Connection secret management

For DEPLOY-03, I need to define:

*   Bridge and External Spoke deployment
*   Network isolation rules (the actual Terraform/K8s NetworkPolicy)
*   CDN integration
*   The "Zero-Exposure Test" enforcement mechanism

For DEPLOY-04, I need to define:

*   Multi-environment promotion
*   dev → staging → production
*   Integration with CORE-01 (Loom) for version-bump automation
*   Blue/green or canary deployment strategy
*   Rollback procedure

OK, this is a substantial amount of content. Let me write it all out at the same fidelity as Kimi's exemplars. I'll be thorough but not redundant — I won't repeat the governance rules or critique; I'll just produce the blueprints.

I'll structure the response as:

*   Brief intro explaining what this batch covers and why
*   The blueprints in dependency order (so a reader can follow the critical path)
*   A summary table mapping each blueprint to the findings it resolves

Let me write this now. I need to make sure every blueprint:

1.  Has a "Resolves" line
2.  Has real PHP interface contracts (not just prose)
3.  Has explicit benchmark methodology (no bare ms claims)
4.  Has CI verification criteria
5.  Has corrected cross-references (CORE-16 for crypto, not CORE-09)
6.  Marks CORE-02 as the explicit blocker where applicable

Let me write each one carefully. This will be long but that's appropriate for "comprehensive high-fidelity blueprints."

Actually, looking at this more carefully — the user just said "Please help me:" which is quite open-ended. But given the context (a comprehensive blueprint generation task where the previous response explicitly recommended "the full Hub tier" as the next batch), the most helpful thing I can do is produce the next batch of high-fidelity blueprints.

I'll produce the critical Core tier completions first (since they unblock everything), then the high-value Hub tier, then the remaining Deploy tier. This gives a comprehensive critical-path coverage.

Let me write it now.</think>\# DGLab Blueprint Upgrade — Next-Batch High-Fidelity Delivery

The previous delivery (`PART I–IV`) established the critique, governance framework, and 10 exemplar blueprints. The explicit recommendation was to continue with the next batch — prioritizing the critical-path Core tier (since `CORE-02` is the universal blocker) and the high-value Hub tier that the existing exemplars reference but cannot fully specify without their upstream Core dependencies.

Below is **PART V: Next-Batch High-Fidelity Blueprints**, covering 16 components that close the critical path from "empty stubs and prose" to "implementable contracts for the entire boot-time and request-time stack." Every blueprint is written to the same enforced fidelity bar: real PHP 8.3 interface contracts, corrected cross-references (cryptography is always `CORE-16`, never `CORE-09`), explicit benchmark methodology per Governance Rule 2, and a `Resolves` line.

* * *

## CORE-08: Global Error & Exception Handler

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described `CORE-09` as "Error Handling"; this is the authoritative error-handler spec); Finding 10 (benchmark methodology); Finding 11 (error-to-response conversion path, previously underspecified, now concrete).

### Component Name

Sovereign Error Handler — `SovereignStack\Core\ErrorHandler`

### Description

A unified error, exception, and shutdown handler that converts PHP's procedural error model into a structured, PSR-3-loggable, PSR-7-renderable pipeline. It is the single registration point for `set_error_handler`, `set_exception_handler`, and `register_shutdown_function` during boot. It must not depend on `CORE-18` (Kernel) — the Kernel depends on *it* — but it has a soft dependency on `CORE-04` (PSR-7) for response rendering, falling back to a plain-text emitter when PSR-7 is unavailable (e.g., during CLI boot before the HTTP stack is ready).

### Build Status

🔴 **Blocked** on `CORE-02` (DI Container — for handler resolution), `CORE-09` (Logging — for structured error logging). Soft dependency on `CORE-04` (PSR-7) for HTTP response rendering.

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-09`. Soft: `CORE-04`.
*   **Downward:** `CORE-18` (Kernel) registers this as the first boot step; every Hub/Spoke inherits its error-to-response contract.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler;

interface ErrorHandlerInterface
{
    /**
     * Convert a PHP error (E_ERROR, E_WARNING, etc.) into a log entry or exception.
     * Returns true to suppress PHP's internal error handler, false to defer.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool;

    /**
     * Top-level exception handler. Must never throw — if it does, PHP aborts.
     * Logs the exception, dispatches an ErrorEvent (CORE-03), and renders a response.
     */
    public function handleException(\Throwable $e): void;

    /**
     * Shutdown handler. Detects fatal errors (E_ERROR | E_PARSE | E_CORE_*)
     * missed by handleError because they bypass the error handler, logs them,
     * and renders a 500 response if the HTTP stack is still intact.
     */
    public function handleShutdown(): void;

    /** Register all three handlers with PHP in one call. Called once by CORE-18. */
    public function register(): void;

    /** Restore PHP's default handlers (used in tests). */
    public function unregister(): void;
}

interface ErrorRendererInterface
{
```

#### Error-to-Exception Conversion Map

| PHP Severity | Converted To | Rationale |
| --- | --- | --- |
| `E_ERROR`, `E_PARSE`, `E_CORE_*`, `E_COMPILE_*` | Not convertible (fatal) — caught by `handleShutdown()` | These bypass `handleError` entirely |
| `E_WARNING`, `E_NOTICE` | Logged at PSR-3 `warning`/`notice`; not converted | Noisy but non-fatal; conversion would mask origin |
| `E_USER_ERROR` | `\ErrorException` thrown | Explicit developer signal |
| `E_USER_WARNING`, `E_USER_NOTICE` | Logged; not converted | Same as engine equivalents |
| `E_DEPRECATED`, `E_USER_DEPRECATED` | Logged at `info`; not converted | Forward-compat signal, not an error |
| `E_STRICT` | Suppressed (PHP 8+ removes most cases) | Legacy |

#### Shutdown Fatal-Error Detection

php

```php
public function handleShutdown(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
           | E_COMPILE_ERROR | E_COMPILE_WARNING;

    if (($error['type'] & $fatal) === 0) {
        return;
    }

    // Wrap as Throwable for uniform logging path
    $this->logError(new \ErrorException(
        $error['message'], 0, $error['type'], $error['file'], $error['line']
    ));

    // If headers not yet sent, attempt a 500 response via the renderer.
    // If headers are sent (partial output already flushed), we can only abort.
    if (!headers_sent()) {
        $this->emitFatalResponse();
    }
}
```

#### Environment-Aware Rendering

| Environment | Stack Trace | Request Context | Source Code Snippet |
| --- | --- | --- | --- |
| `development` | Full | Headers, query, body | 5 lines around error |
| `staging` | Full (internal access only) | Headers only | None |
| `production` | None (opaque `500 Internal Server Error`) | None | None |

The production response body is a static JSON payload `{"error":"internal_server_error","request_id":"..."}` — the `request_id` (from `CORE-09`'s context enrichment) is the only correlation key, and it is also logged server-side so support can trace.

### Integration Strategy

*   `CORE-18` (Kernel) calls `ErrorHandlerInterface::register()` as the **first** boot step, before the container is compiled — unhandled exceptions during provider registration must still be caught.
*   `CORE-03` (Event Dispatcher) receives an `ErrorEvent` for every handled error/exception, allowing `HUB-06` (Audit) and `HUB-15` (Health) to observe error rates without coupling.
*   `CORE-04` (PSR-7) is the rendering target; if unavailable, the handler falls back to `fwrite(STDERR, ...)` in CLI and a minimal `header('HTTP/1.1 500')` + `echo` in HTTP.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Error-to-log path does not allocate unbounded memory on recursive error storms | Unit test: trigger 10,000 `E_USER_WARNING` in a loop; assert memory usage stays bounded (measured via `memory_get_usage(true)` deltas; assert delta < 1MB per 1,000 errors). No absolute claim until measured on reference runner. |
| `handleException` never re-throws | Mutation test: inject a `LoggerInterface` mock that throws on `error()`; assert `handleException` catches the logger exception internally and still emits a response. |
| Shutdown handler detects all fatal severities | Parameterized test: for each bit in `$fatal`, simulate via `error_get_last()` mock; assert logging path executes. |

### CI Verification Criteria

*   `error_reporting()` is set to `E_ALL` in the handler's `register()` — silent suppression of any severity is a bug, not a feature.
*   `display_errors` is forced to `Off` in production environment (the handler owns output, not PHP).
*   100% branch coverage on the severity-conversion table above.
*   The handler is reentrant: a `handleException` call that itself triggers an error must not infinite-loop (guarded by a `$handling` flag).

### SemVer Impact

**Major** — establishes the error contract every downstream tier inherits.

* * *

## CORE-09: PSR-3 Logging Service

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Error Handling"; this is the authoritative PSR-3 logging spec); Finding 10 (no bare "5ms per log line" claim — methodology stated).

### Component Name

Sovereign Logger — `SovereignStack\Core\Logging`

### Description

A PSR-3-compliant logging service with structured context enrichment, multi-handler fan-out, per-channel level filtering, and high-volume sampling. Every log line carries a stable `request_id`, `tenant_id` (when available), and `correlation_id` (for distributed traces across Hub → Spoke calls). This is the upstream dependency for `CORE-08` (Error Handler), `HUB-06` (Audit), and effectively every component that emits diagnostic output.

### Build Status

🔴 **Blocked** on `CORE-02` (DI Container — for handler resolution and singleton binding).

### Dependency Status

*   **Upward:** `psr/log: ^3.0` (already declared in `composer.json`).
*   **Downward:** `CORE-08` (Error Handler), `CORE-18` (Kernel), `HUB-06` (Audit), every Hub/Spoke that logs.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logging;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    /** Channel name (e.g., 'app', 'cache', 'auth', 'queue'). */
    public function channel(string $name): static;

    /** Attach a handler that receives all log records at or above its threshold. */
    public function addHandler(HandlerInterface $handler): void;

    /** Push a context processor that mutates the record before it reaches handlers. */
    public function pushProcessor(callable $processor): void;

    /** Pop the most recently pushed processor. */
    public function popProcessor(): callable;
}

interface HandlerInterface
{
    /** True if this handler should process a record at the given level and channel. */
    public function isHandling(string $level, string $channel): bool;

    /** Write the record. Must not throw — failures are silently swallowed to prevent log-recursion. */
    public function handle(LogRecord $record): void;
}

final class LogRecord
{
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
```

#### Built-In Handlers

| Handler | Target | Use Case |
| --- | --- | --- |
| `StreamHandler` | `php://stderr` (default for containers), file path | Container stdout/stderr; k8s log aggregation |
| `SyslogHandler` | `openlog()` / `syslog()` | systemd integration on bare-metal deploys |
| `NullHandler` | /dev/null | Test isolation; explicitly silence a channel |
| `FingersCrossedHandler` | Wraps another handler; buffers records until a threshold is hit, then flushes the buffer and forwards all subsequent records | Capture debug context around an error without paying the cost in the happy path |
| `SamplingHandler` | Wraps another handler; forwards only 1-in-N records at a given level | High-volume `debug` channels (e.g., cache hits) |

#### Context Enrichment Processors

php

```php
// RequestIdProcessor — injected once per request by CORE-18.
// Generates a ULID if none is present in the request headers (X-Request-Id).
final class RequestIdProcessor
{
    public function __construct(private string $requestId) {}
    public function __invoke(LogRecord $r): LogRecord
    {
        return $r->with(requestId: $this->requestId);
    }
}

// TenantContextProcessor — injected by HUB-04 when a TenantContext is resolved.
final class TenantContextProcessor
{
    public function __construct(private ?string $tenantId) {}
    public function __invoke(LogRecord $r): LogRecord
    {
        return $r->with(tenantId: $this->tenantId);
    }
}
```

#### Sampling Contract

For channels registered as "high-volume" (e.g., `cache`, `http.access`), the logger applies a `SamplingHandler` with a configurable rate (default: 1-in-100 for `debug`, 1-in-10 for `info`). The sampling decision is made **per-record**, not per-process, so a sudden burst of errors is never sampled away — only `debug`/`info` traffic is. `warning` and above are never sampled.

### Integration Strategy

*   `CORE-18` (Kernel) constructs the root `Logger`, attaches the `RequestIdProcessor`, and binds it as a singleton in `CORE-02` (Container) under both `LoggerInterface` and `Psr\Log\LoggerInterface`.
*   `CORE-08` (Error Handler) calls `$logger->channel('error')->error(...)` for handled errors.
*   `HUB-06` (Audit) does **not** use this logger for audit records — audit is a separate append-only store (see `HUB-06.md`). The logger is for operational diagnostics; audit is for compliance.
*   Containerized deployment (`DEPLOY-01`) configures the `StreamHandler` to write to `php://stderr` so k8s/Docker log drivers capture it natively.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Log line serialization does not block the request path under sustained load | Performance test: emit 1,000 `info` records in a tight loop to a `NullHandler`; assert wall-clock scales linearly and per-record overhead is measured (not asserted) on a reference runner. |
| Sampling reduces handler invocations by the configured factor | Unit test: wrap a counting handler in `SamplingHandler` with rate 1-in-10; emit 1,000 `debug` records; assert handler was invoked ~100 times (±5%). |
| `FingersCrossedHandler` flushes buffer on `error` and continues forwarding | Unit test: emit 5 `debug` records (buffered), then 1 `error`; assert handler received all 6, then emit 2 more `debug` and assert both forwarded immediately. |
| Handler failure does not propagate to caller | Mutation test: handler that throws `RuntimeException`; assert logger catches, writes a fallback to `stderr`, and returns normally. |

### CI Verification Criteria

*   PSR-3 interface compliance verified by `psr/log` test suite (the package's own tests).
*   100% coverage on `FingersCrossedHandler` and `SamplingHandler` — these are the two non-obvious handlers.
*   No log record ever contains a raw password, token, or private key — a `SensitiveDataProcessor` scrubs known-sensitive context keys (`password`, `token`, `authorization`, `secret`, `*_key`) by replacing values with `[REDACTED]`. Verified by a test that logs a record with sensitive keys and asserts redaction.

### SemVer Impact

**Major** — defines the logging contract every component depends on.

* * *

## CORE-10: Configuration & Environment Loader

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described `CORE-01` as "Bootstrapper & Kernel"; this is the actual config loader); Finding 8 (this blocks `HUB-01`, which blocks the Hub tier — explicitly marked blocked); Finding 10 (no bare "< 0.005ms flag evaluation" claim).

### Component Name

Sovereign Config — `SovereignStack\Core\Config`

### Description

A typed, schema-validated configuration loader that merges environment variables, `.env` files, and PHP/JSON config files into a single immutable `ConfigRepository`. After `freeze()`, the repository rejects mutations — this is the contract that makes `HUB-01`'s tenant-override merge logic safe (a tenant override cannot mutate the frozen default schema). All values are type-coerced from string env vars to their declared types (int, float, bool, enum, JSON).

### Build Status

🔴 **Blocked** on `CORE-02` (DI Container — for singleton binding and `CompilerPass` integration).

### Dependency Status

*   **Upward:** `CORE-02` (Container).
*   **Downward:** `CORE-18` (Kernel — reads `app.environment`, `app.debug`), `HUB-01` (Config & Flags — extends this with tenant overrides), every Hub/Spoke that reads settings.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

interface ConfigRepositoryInterface
{
    /** Get a value by dot-path key, e.g. 'database.mysql.host'. */
    public function get(string $key, mixed $default = null): mixed;

    /** True if the key exists in the schema (not just whether it has a value). */
    public function has(string $key): bool;

    /**
     * Define a schema entry: key, expected type, default, and whether it's required.
     * Must be called before freeze().
     *
     * @throws \LogicException if called after freeze().
     */
    public function define(string $key, string $type, mixed $default = null, bool $required = false): void;

    /** Freeze the repository: validate all required keys are present, reject further mutations. */
    public function freeze(): void;

    public function isFrozen(): bool;
}

interface EnvironmentInterface
{
    /** Get a raw environment variable. */
    public function get(string $name, ?string $default = null): ?string;

    /** True if the variable is set (even if empty string). */
    public function has(string $name): bool;
```

#### Type Coercion Map

| Declared Type | Env Var (string) | Coerced Value |
| --- | --- | --- |
| `int` | `"3306"` | `3306` |
| `float` | `"0.15"` | `0.15` |
| `bool` | `"true"`, `"1"`, `"yes"`, `"on"` | `true` (case-insensitive) |
| `bool` | `"false"`, `"0"`, `"no"`, `"off"`, `""` | `false` |
| `string` | any | string as-is |
| `enum:X,Y,Z` | `"Y"` | `"Y"` (validated against the enum set) |
| `json` | `'{"host":"db","port":3306}'` | decoded array |
| `list` | `"redis,apcu"` | `["redis", "apcu"]` (comma-separated) |

#### Schema Validation at Freeze

php

```php
public function freeze(): void
{
    if ($this->frozen) {
        return;
    }

    foreach ($this->schema as $key => $def) {
        $value = $this->values[$key] ?? null;

        if ($value === null) {
            if ($def['required']) {
                throw new \RuntimeException("Missing required config key: {$key}");
            }
            $this->values[$key] = $def['default'];
            continue;
        }

        // Type already coerced at set() time; freeze() just confirms presence.
    }

    $this->frozen = true;
}
```

#### Loader Pipeline

```
flowchart TD
    A[Process start] --> B[EnvironmentInterface<br/>reads $_SERVER + getenv]
    B --> C[DotEnvLoader<br/>reads .env file if present]
    C --> D[FileConfigLoader<br/>reads config/*.php in sorted order]
    D --> E[Merge: env overrides file overrides default]
    E --> F[Type coercion per schema]
    F --> G[freeze — validate required keys]
    G --> H[Immutable ConfigRepository<br/>bound in CORE-02 container]
```

### Integration Strategy

*   `CORE-18` (Kernel) constructs the `Environment`, runs the loader pipeline, calls `freeze()`, and binds the frozen repository as a singleton in `CORE-02`.
*   `HUB-01` (Config & Flags) wraps this repository: it reads static defaults from here, applies tenant overrides on top, and exposes the merged view via `GlobalConfigInterface`. The frozen schema is the validation target for tenant overrides — a tenant override for a key not in the schema is rejected (closes the "silent schema drift" risk in `HUB-01.md`).
*   `CORE-17` (Service Providers) may call `define()` during their `register()` phase to declare their own config keys, before the Kernel calls `freeze()`.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Config load + freeze completes in bounded time for a 200-key schema | Performance test: generate a 200-key fixture schema, load from a temp `.env` + PHP config file, freeze; assert wall-clock measured (not asserted) on reference runner. |
| `get()` on a frozen repository is O(1) array access | Unit test: call `get()` 100,000 times on a frozen repo; assert per-call overhead is sub-microsecond (measured via `microtime(true)` delta / iterations). |
| Type coercion rejects invalid enum values at load, not at read | Unit test: define key as `enum:redis,memcached`; set env var to `mysql`; assert `set()` (or load) throws `InvalidValueException`, not `get()`. |

### CI Verification Criteria

*   The `.env.example` file in each repo must declare every key the schema defines — a CI check diffs the schema keys against `.env.example` and fails on mismatch.
*   `freeze()` is called exactly once per process; a second call is a no-op (idempotent), not an error — but a `define()` after freeze is a `\LogicException`.
*   No secret value (passwords, keys, tokens) is ever logged by the loader — the loader redacts values for keys matching `/password|secret|token|key/i` in any diagnostic output.

### SemVer Impact

**Major** — defines the config contract; any change to key names or types is a breaking change for every consumer.

* * *

## CORE-04: PSR-7 HTTP Message & Factory

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Encryption Primitives"; this is the authoritative PSR-7 spec); Finding 10 (no bare "message construction < Xms" claim).

### Component Name

Sovereign HTTP Message — `SovereignStack\Core\Http\Message`

### Description

A concrete implementation of the PSR-7 HTTP message interfaces (`RequestInterface`, `ResponseInterface`, `ServerRequestInterface`, `StreamInterface`, `UriInterface`, `UploadedFileInterface`) and the PSR-17 HTTP factory interfaces. This is the message layer that `CORE-05` (Middleware), `CORE-06` (Router), and `BRIDGE-01` (Vanguard) all consume. Messages are **immutable** per PSR-7; every `with*()` method returns a new instance.

### Build Status

🔴 **Blocked** on `CORE-02` (DI Container — for factory resolution via PSR-17 autodiscovery). No other Core dependency.

### Dependency Status

*   **Upward:** `psr/http-message: ^2.0`, `psr/http-factory: ^1.0` (both already declared).
*   **Downward:** `CORE-05` (Middleware), `CORE-06` (Router), `CORE-08` (Error Handler — for response rendering), `BRIDGE-01` (Vanguard), every Spoke.

### Architectural Design

#### Class Map

| Class | PSR Interface | Notes |
| --- | --- | --- |
| `Request` | `RequestInterface` | Immutable; method, URI, headers, body, target |
| `Response` | `ResponseInterface` | Immutable; status code, reason phrase, headers, body |
| `ServerRequest` | `ServerRequestInterface` | Extends `Request` with server params, cookies, query, parsed body, attributes |
| `Stream` | `StreamInterface` | Wraps a PHP resource; lazy-opened from filename; `php://temp` for in-memory |
| `Uri` | `UriInterface` | Immutable; RFC 3986 compliant; host lowercased on construction |
| `UploadedFile` | `UploadedFileInterface` | Wraps `$_FILES` entry; `moveTo()` uses `move_uploaded_file` only when SAPI is `cli-server` or `cgi-fcgi` |
| `ServerRequestFactory` | `ServerRequestFactoryInterface` | PSR-17 |
| `RequestFactory` | `RequestFactoryInterface` | PSR-17 |
| `ResponseFactory` | `ResponseFactoryInterface` | PSR-17 |
| `StreamFactory` | `StreamFactoryInterface` | PSR-17 |
| `UriFactory` | `UriFactoryInterface` | PSR-17 |
| `UploadedFileFactory` | `UploadedFileFactoryInterface` | PSR-17 |

#### Stream Implementation Contract

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Http\Message;

use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    public function __construct(
        private mixed $stream,        // resource | string filename | 'php://memory'
        private string $mode = 'r',
    ) {
        if (\is_string($this->stream)) {
            $this->resource = @\fopen($this->stream, $this->mode);
            if ($this->resource === false) {
                throw new \RuntimeException("Unable to open stream: {$this->stream}");
            }
        } elseif (\is_resource($this->stream)) {
            $this->resource = $this->stream;
        } else {
            throw new \InvalidArgumentException('Stream must be a resource or filename.');
        }
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
```

#### ServerRequest Construction from Globals

php

```php
<?php

final class ServerRequestFactory implements ServerRequestFactoryInterface
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequest
    {
        return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
    }

    /** Convenience: build a ServerRequest from $_SERVER, $_GET, $_POST, $_FILES, $_COOKIE. */
    public static function fromGlobals(): ServerRequest
    {
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = self::marshalUriFromServer($server);
        $request = (new self())->createServerRequest($method, $uri, $server);

        $request = $request
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withCookieParams($_COOKIE)
            ->withUploadedFiles(self::normalizeFiles($_FILES));

        // Content-Type negotiation for parsed body
        $contentType = $server['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            if ($body !== '') {
                $request = $request->withParsedBody(json_decode($body, true, 512, JSON_THROW_ON_ERROR));
            }
        }

        return $request;
    }
}
```

### Integration Strategy

*   `CORE-05` (Middleware) types all middleware as `MiddlewareInterface` consuming `ServerRequestInterface` and returning `ResponseInterface`.
*   `CORE-08` (Error Handler) uses `ResponseFactory` to construct error responses.
*   `BRIDGE-01` (Vanguard) uses `ServerRequestFactory::fromGlobals()` as the entry point, then mutates the request to add `TenantContext` attributes after JWT verification.
*   PSR-17 factories are registered in `CORE-02` (Container) under their interface names so any PSR-17 consumer can autowire them.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| `withHeader()` returns a new instance without copying the body stream | Unit test: create a response with a 1MB body stream, call `withHeader()`; assert the original and new instance share the same stream resource (no copy); measure memory delta. |
| `Stream::getContents()` on a 10MB file does not exceed 11MB peak memory | Unit test: create stream from a 10MB temp file, read fully; assert `memory_get_peak_usage(true)` delta < 11MB (streaming, not slurping). |
| `fromGlobals()` correctly parses JSON body when Content-Type is `application/json` | Integration test: populate `$_SERVER`, `$_POST`, etc. with a JSON Content-Type and a JSON body string; assert `getParsedBody()` returns the decoded array, not the raw string. |

### CI Verification Criteria

*   Full `psr/http-message` and `psr/http-factory` test suites pass against this implementation (the packages' own compliance tests).
*   Immutability verified: every `with*()` method returns `new static(...)`, never `$this`.
*   `UploadedFile::moveTo()` uses `is_uploaded_file()` + `move_uploaded_file()` only under SAPI; under CLI it falls back to `rename()` with a security warning logged.

### SemVer Impact

**Major** — establishes the HTTP message contract.

* * *

## CORE-05: PSR-15 Middleware & Request Handler

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Router & Dispatch"; this is the authoritative middleware spec); Finding 10.

### Component Name

Sovereign Middleware Pipeline — `SovereignStack\Core\Http\Middleware`

### Description

A PSR-15-compliant middleware pipeline implementing `MiddlewareInterface` and `RequestHandlerInterface`. The pipeline composes middleware in declaration order; each middleware receives the request and a delegate handler that invokes the next middleware. The terminal handler is provided by `CORE-06` (Router) — if no route matches, the terminal handler returns `404`.

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-04` (PSR-7).

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-04`. PSR interfaces: `psr/http-server-middleware`, `psr/http-server-handler`.
*   **Downward:** `CORE-06` (Router — terminal handler), `BRIDGE-01` (Vanguard pipeline), every Spoke's HTTP entry.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface PipelineInterface extends RequestHandlerInterface
{
    /** Add middleware to the end of the pipeline. */
    public function pipe(MiddlewareInterface $middleware): void;

    /** Add middleware at a specific position (0 = first). */
    public function insertAt(int $index, MiddlewareInterface $middleware): void;

    /** Set the terminal handler invoked when all middleware delegate. */
    public function setTerminalHandler(RequestHandlerInterface $handler): void;
}

final class Pipeline implements PipelineInterface
{
    /** @var array<int, MiddlewareInterface> */
    private array $middleware = [];

    private ?RequestHandlerInterface $terminal = null;

    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
```

**Note on the `array_shift` pattern above:** the simplified implementation mutates `$this->middleware`, which makes the `Pipeline` single-use. The production implementation uses an internal recursive handler stack (`PipelineHandler`) that clones the middleware array per-request, so the same `Pipeline` instance can serve multiple requests (it's bound as a singleton in the container). The contract above defines the public API; the internal implementation detail is in `packages/core/middleware/src/PipelineHandler.php`.

#### Built-In Middleware (registered by `CORE-18` in default order)

| Order | Middleware | Responsibility |
| --- | --- | --- |
| 1 | `RequestIdMiddleware` | Reads `X-Request-Id` header or generates ULID; sets it as request attribute and response header |
| 2 | `ErrorCatchingMiddleware` | Wraps the entire downstream pipeline in a try/catch; delegates to `CORE-08`'s renderer on `\Throwable` |
| 3 | `ContentLengthMiddleware` | Sets `Content-Length` header on the response (runs last, post-process) |
| 4 | `RouteDispatchMiddleware` | Delegates to `CORE-06` (Router) as the terminal handler |

`BRIDGE-01` (Vanguard) prepends its own middleware before this stack: `RateLimitMiddleware`, `JwtAuthMiddleware`, `TenantContextMiddleware`.

### Integration Strategy

*   `CORE-18` (Kernel) constructs the `Pipeline`, registers default middleware, sets `CORE-06` (Router) as terminal, and binds the pipeline in `CORE-02`.
*   `BRIDGE-01` (Vanguard) constructs its own pipeline that wraps the default pipeline — Vanguard's middleware runs first (rate limit, auth), then delegates to the default pipeline for routing.
*   Spokes do not construct their own pipelines; they declare routes via `CORE-06` attributes and let the Kernel-provided pipeline dispatch to them.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Pipeline with 10 middleware processes a request in bounded time | Performance test: stack 10 no-op middleware; measure `handle()` wall-clock; assert scales linearly with middleware count (measured, not asserted). |
| Middleware order is preserved end-to-end | Unit test: 3 middleware that append 'A', 'B', 'C' to a response header; assert final header value is `A, B, C` (execution order = declaration order). |
| Terminal handler invoked when all middleware delegate | Unit test: pipeline with 2 passthrough middleware + terminal returning `200`; assert terminal is reached and returns its response. |
| Terminal handler not invoked when middleware short-circuits | Unit test: first middleware returns `403` without delegating; assert terminal never called (mock with `expects($this->never())`). |

### CI Verification Criteria

*   PSR-15 interface compliance verified by `psr/http-server-middleware` and `psr/http-server-handler` test suites.
*   `ErrorCatchingMiddleware` never lets a `\Throwable` escape to PHP's default handler — verified by a test that injects a middleware throwing `RuntimeException` and asserting a `500` response is returned.
*   The pipeline is reusable across requests (no shared mutable state between `handle()` calls) — verified by a test that calls `handle()` twice on the same instance and asserts identical behavior.

### SemVer Impact

**Major** — establishes the request-processing contract.

* * *

## CORE-06: Attribute-Based Router

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Middleware Pipeline"; this is the authoritative router spec); Finding 10.

### Component Name

Sovereign Router — `SovereignStack\Core\Http\Routing`

### Description

An attribute-based router that discovers route declarations via PHP 8 attributes on controller classes and methods, compiles path templates to regex at boot, and matches incoming `ServerRequestInterface` instances to produce a `RouteMatch`. The router implements `RequestHandlerInterface` (PSR-15), making it the terminal handler for `CORE-05`'s pipeline.

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-04`, `CORE-05`.

### Dependency Status

*   **Upward:** `CORE-02` (Container — resolves controller instances), `CORE-04` (PSR-7), `CORE-05` (PSR-15 — router is the terminal handler).
*   **Downward:** `BRIDGE-01` (Vanguard), every Spoke that declares routes.

### Architectural Design

#### Attribute Definitions

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Http\Routing\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
        public readonly ?string $name = null,
        /** @var array<string, string> Param name => regex constraint, e.g. ['id' => '[0-9]+'] */
        public readonly array $constraints = [],
        /** @var array<string, string> Middleware class names to run for this route only. */
        public readonly array $middleware = [],
    ) {}
}

// Convenience attributes
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Get extends Route
{
    public function __construct(string $path, ?string $name = null, array $middleware = [])
    { parent::__construct($path, 'GET', $name, [], $middleware); }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Post extends Route { /* method = 'POST' */ }

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Put extends Route { /* method = 'PUT' */ }

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Delete extends Route { /* method = 'DELETE' */ }
```

#### Route Compilation

Path templates like `/users/{id}/posts/{slug}` are compiled to a regex at boot:

text

```thought
/^\/users\/(?P<id>[^\/]+)\/posts\/(?P<slug>[^\/]+)$/
```

If a constraint is provided (`['id' => '[0-9]+']`), the `[^\/]+` default is replaced:

text

```
/^\/users\/(?P<id>[0-9]+)\/posts\/(?P<slug>[^\/]+)$/
```

Compilation happens once at boot and the compiled regexes are cached in memory. In production with OPcache preloading, the compiled route table is preloaded.

#### Router Implementation

php

```php
<?php

final class Router implements RequestHandlerInterface
{
    /** @var array<string, CompiledRoute[]> Method => routes */
    private array $routes = [];

    public function __construct(
        private \Psr\Container\ContainerInterface $container,
        private \SovereignStack\Core\Http\Middleware\PipelineInterface $pipeline,
    ) {}

    public function register(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);

        $classAttr = $reflection->getAttributes(Controller::class)[0] ?? null;
        $prefix = $classAttr?->newInstance()->prefix ?? '';
        $classMiddleware = $classAttr?->newInstance()->middleware ?? [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                $route = $attr->newInstance();
                $path = rtrim($prefix, '/') . '/' . ltrim($route->path, '/');
                $this->routes[$route->method][] = new CompiledRoute(
                    path: $path,
                    regex: $this->compile($path, $route->constraints),
                    controller: $controllerClass,
                    method: $method->getName(),
                    name: $route->name,
                    middleware: [...$classMiddleware, ...$route->middleware],
                );
            }
        }
    }
```

### Integration Strategy

*   `CORE-18` (Kernel) scans configured controller directories at boot, calls `register()` for each, then sets the `Router` as the terminal handler on the default `Pipeline`.
*   `BRIDGE-01` (Vanguard) constructs its own `Router` instance scoped to External Spoke routes only; Internal Spokes are never registered in the Vanguard router.
*   Spokes declare their controllers in their `ServiceProvider` (see `CORE-17`), which calls a `Router::register()` helper exposed by the Kernel.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Route matching for 1,000 registered routes completes in bounded time | Performance test: register 1,000 synthetic routes; measure `handle()` for a route at the end of the list; assert linear scan time is measured (or optimize to a trie if measured time is unacceptable — don't assert a number without measuring). |
| Path parameter extraction handles URL-encoded values | Unit test: route `/users/{name}`; request `/users/John%20Doe`; assert extracted `name` param is `John Doe` (urldecoded). |
| Optional trailing slash matches both `/users` and `/users/` | Unit test: register `/users`; request both; assert both match. (Configurable behavior: `strict` mode rejects the mismatched form.) |
| 405 Method Not Allowed returned when path matches but method doesn't | Unit test: register `GET /users`; request `POST /users`; assert `405` with `Allow: GET` header. |

### CI Verification Criteria

*   All PHP 8 attributes tested with both `#[Get]` shorthand and `#[Route(method: 'GET')]` long form.
*   Route name uniqueness enforced — two routes with the same name is a boot-time `\RuntimeException`.
*   No route path may contain a literal `?` (query string is not part of the path) — validated at `register()` time.

### SemVer Impact

**Major** — establishes the routing contract.

* * *

## CORE-16: Binary Encryption Envelope

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 3 (this is the correct cryptography component — previously misreferenced as `CORE-09` in `BRIDGE-01.md`); Finding 10; Finding 11 (sparse encryption details now fully specified with key rotation and envelope format).

### Component Name

Sovereign Crypto Envelope — `SovereignStack\Core\Crypto`

### Description

A binary envelope format for encrypting and signing arbitrary payloads. Provides symmetric encryption (AES-256-GCM) for data at rest (cache values, config secrets, session tokens), asymmetric signing (Ed25519) for JWTs and inter-service message authentication, and a key-envelope wrapping format that allows key rotation without re-encrypting all data. This is the **single** cryptography primitive in the system — `BRIDGE-01` uses it for payload verification, `HUB-04` uses it for password hashing and JWT signing, `HUB-02` uses it for encrypted cache values.

### Build Status

🔴 **Blocked** on `CORE-02` (DI Container — for key manager singleton binding). Soft: `ext-sodium` (bundled with PHP 7.2+, always available).

### Dependency Status

*   **Upward:** `ext-sodium` (PHP core extension, no installation needed). `CORE-02` for binding.
*   **Downward:** `CORE-09` (Logging — does **not** use this; that was the stale reference in the old `BRIDGE-01.md`), `HUB-02` (Cache — encrypted values at rest), `HUB-04` (Identity — password hashing, JWT signing), `BRIDGE-01` (Vanguard — payload verification), `HUB-06` (Audit — audit log chain hashing).

### Architectural Design

#### Envelope Binary Format

text

```
Offset  Length  Field
0       1       Version (0x01)
1       1       Algorithm ID (0x01 = AES-256-GCM)
2       1       Key ID length (N)
3       N       Key ID (UTF-8 string, e.g. "key-2026-08")
3+N     12      Nonce (96-bit, random per encryption)
15+N    16      Auth tag (GCM)
31+N    *       Ciphertext (remainder)
```

This is a self-describing format: a decryptor reads the key ID, looks up the corresponding key in the `KeyManager`, then decrypts. Key rotation works because old envelopes retain their key ID; the `KeyManager` retains retired keys for decryption until their data is re-encrypted.

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

interface EncryptionInterface
{
    /**
     * Encrypt plaintext using the active key, return a binary envelope.
     * AAD (additional authenticated data) is bound to the ciphertext but not encrypted.
     *
     * @param string $plaintext
     * @param string $aad Additional authenticated data (e.g., tenant ID for cache keys).
     * @return string Binary envelope (see format above).
     */
    public function encrypt(string $plaintext, string $aad = ''): string;

    /**
     * Decrypt a binary envelope.
     *
     * @throws DecryptionException If the key ID is unknown, the auth tag is invalid,
     *         or the envelope is malformed.
     */
    public function decrypt(string $envelope, string $aad = ''): string;
}

interface SigningInterface
{
    /** Sign a message with the active Ed25519 private key. Returns raw 64-byte signature. */
    public function sign(string $message): string;

    /** Verify a signature against a message using the named public key. */
    public function verify(string $message, string $signature, string $keyId): bool;
}
```

#### Key Storage Backends

| Backend | Use Case | Notes |
| --- | --- | --- |
| `FileKeyStore` | Development | Keys in `storage/keys/`; permissions `0600`; never committed to git |
| `VaultKeyStore` | Production | HashiCorp Vault Transit engine; keys never leave Vault — encrypt/decrypt via API |
| `AwsKmsKeyStore` | AWS production | AWS KMS; envelope encryption (data key encrypted by KMS master key) |
| `EnvKeyStore` | Containerized staging | Key material injected via environment variable (base64-encoded); rotated by redeploy |

#### Password Hashing (separate from envelope)

php

```php
interface PasswordHasherInterface
{
    /** Hash a password using Argon2id. Returns the full hash string (algorithm + params + salt + hash). */
    public function hash(string $password): string;

    /** Verify a password against a stored hash. Constant-time. */
    public function verify(string $password, string $hash): bool;

    /** True if the hash uses the current recommended algorithm/params and should not be re-hashed. */
    public function needsRehash(string $hash): bool;
}
```

Implementation uses `sodium_crypto_pwhash_str()` with `SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE` and `SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE` (tunable via `CORE-10` config). **Never** MD5, SHA-1, or unsalted SHA-256.

### Integration Strategy

*   `CORE-18` (Kernel) constructs the `KeyManager` with the configured backend, loads keys, and binds `EncryptionInterface`, `SigningInterface`, and `PasswordHasherInterface` as singletons in `CORE-02`.
*   `HUB-04` (Identity) uses `SigningInterface` for JWT signing (Ed25519) and `PasswordHasherInterface` for password storage.
*   `HUB-02` (Cache) uses `EncryptionInterface` for encrypted-at-rest cache values (AES-256-GCM with tenant ID as AAD).
*   `BRIDGE-01` (Vanguard) uses `SigningInterface::verify()` to verify JWT signatures — note that the Vanguard needs only the **public** key, not the private key, which is why asymmetric signing (Ed25519) is mandatory rather than symmetric (HMAC).

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| AES-256-GCM encrypt/decrypt round-trip for 1KB payload | Performance test: encrypt then decrypt a 1KB payload 1,000 times; measure per-operation cost. Compare against `sodium_crypto_secretbox` baseline to verify no unnecessary overhead. State measured number on reference runner. |
| Ed25519 sign + verify round-trip | Performance test: sign and verify a 256-byte message 1,000 times; measure. |
| Key rotation: old envelopes remain decryptable after rotation | Integration test: encrypt with key A; retire key A; activate key B; decrypt the envelope — must succeed (key A still available for decrypt). New encryptions use key B. |
| AAD mismatch causes decryption failure | Unit test: encrypt with AAD `"tenant-A"`; attempt decrypt with AAD `"tenant-B"`; assert `DecryptionException` (GCM auth tag verification fails). |
| Password hashing is constant-time | Statistical test: hash and verify 100 correct and 100 incorrect passwords; measure timing distribution; assert no statistically significant timing difference (p > 0.05). |

### CI Verification Criteria

*   `ext-sodium` is loaded (CI fails if missing — it's a PHP core extension since 7.2, so this is a sanity check).
*   No plaintext key material is ever logged — the `KeyManager`'s `__debugInfo()` returns `['keys' => '[REDACTED]']`.
*   Envelope version byte is checked on decrypt; unknown versions throw `DecryptionException` (forward-compat: a v2 envelope format can coexist with v1).
*   All encryption uses authenticated encryption (AES-GCM or ChaCha20-Poly1305) — never unauthenticated AES-CBC or ECB.

### SemVer Impact

**Major** — establishes the cryptography contract. Any change to the envelope format, key ID scheme, or algorithm is a breaking change requiring a migration path for all encrypted data.

* * *

## CORE-17: Service Provider System

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Testing Framework"; this is the authoritative service-provider spec); Finding 10.

### Component Name

Sovereign Service Providers — `SovereignStack\Core\Providers`

### Description

A service-provider system that defines the boot contract for every component in the system. Each Hub service and Spoke ships a `ServiceProvider` that registers its bindings in the `CORE-02` container during `register()`, then performs any post-container-compile initialization during `boot()`. The Kernel (`CORE-18`) orchestrates: `register()` all providers → `compile()` the container → `boot()` all providers. This is the extension point — new capabilities are added by writing a provider, not by editing the Kernel.

### Build Status

🔴 **Blocked** on `CORE-02` (Container — providers register bindings into it).

### Dependency Status

*   **Upward:** `CORE-02` (Container).
*   **Downward:** `CORE-18` (Kernel — orchestrates providers), every Hub service provider, every Spoke provider.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Providers;

use SovereignStack\Core\Container\ContainerInterface;

interface ServiceProviderInterface
{
    /**
     * Register bindings in the container. Must not resolve services here —
     * the container is not yet compiled, and resolving a service that depends
     * on another not-yet-registered service will fail.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Post-compile initialization. The container is frozen; services may be
     * resolved here. This is where event listeners are attached, routes
     * registered, scheduled jobs declared, etc.
     */
    public function boot(ContainerInterface $container): void;

    /** Providers are sorted by this value in boot() order. Lower = earlier. */
    public function priority(): int;

    /**
     * Deferred providers are not booted until one of their `provides()` services
     * is requested from the container. Returns empty array for eager providers.
     *
     * @return array<int, string> Service IDs this provider provides.
     */
    public function provides(): array;
}
```

#### Boot Sequence Contract

```
sequenceDiagram
    participant Kernel as CORE-18 Kernel
    participant Registry as ProviderRegistry
    participant Container as CORE-02 Container
    participant Provider as ServiceProvider

    Kernel->>Registry: discover providers (config scan)
    Registry-->>Kernel: [ConfigProvider, LoggingProvider, ErrorProvider, ...]
    loop Each provider in priority order
        Kernel->>Provider: register(container)
        Provider->>Container: bind(), singleton()
    end
    Kernel->>Container: compile() — run compiler passes, freeze
    loop Each provider in priority order
        Kernel->>Provider: boot(container)
        Provider->>Container: make() — resolve services
        Provider->>Provider: attach event listeners, register routes
    end
    Kernel-->>Kernel: Boot complete — ready to handle requests
```

#### Default Provider Priority Order

| Priority | Provider | Reason |
| --- | --- | --- |
| \-100 | `CoreConfigProvider` | Config must be registered before anything reads it |
| \-90 | `CoreLoggingProvider` | Logging must be available before error handler boots |
| \-80 | `CoreErrorProvider` | Error handler must be registered before any service that might throw |
| \-70 | `CoreCryptoProvider` | Encryption keys must be loaded before Identity boots |
| \-60 | `CoreHttpProvider` | PSR-7/17 factories registered before router |
| \-50 | `CoreRoutingProvider` | Router registered before any Spoke declares routes |
| 0 | Hub providers (`HUB-01` through `HUB-30`) | Default priority |
| 100 | Spoke providers | Boot after Hub so Hub services are available |

### Integration Strategy

*   `CORE-18` (Kernel) discovers providers by scanning configured directories for classes implementing `ServiceProviderInterface`. In production, this scan is cached (a compiled `providers.php` array) to avoid reflection overhead on every boot.
*   Hub services ship a provider per service (e.g., `SovereignStack\Hub\Config\ConfigServiceProvider` for `HUB-01`).
*   Spokes ship a provider that registers their controllers with `CORE-06` (Router) and declares any Hub dependencies.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Boot time scales linearly with provider count | Performance test: register 10, 50, 100 no-op providers; measure `boot()` wall-clock; assert linear scaling (measured, not asserted). |
| Deferred provider not booted until its service is requested | Unit test: a deferred provider with `provides() = ['my.service']` that sets a flag in `boot()`; assert flag is false after container compile; assert flag is true after `$container->get('my.service')`. |
| Provider priority order respected | Unit test: three providers with priorities -10, 0, 10 that append to a shared array; assert array order is `[-10, 0, 10]`. |
| `register()` resolving a service throws clear error | Unit test: a provider whose `register()` calls `$container->make('unregistered.service')`; assert `\SovereignStack\Core\Container\NotFoundException` with a message mentioning "register phase — services cannot be resolved in register()". |

### CI Verification Criteria

*   No provider's `register()` method calls `make()` or `get()` on the container — statically checked by a PHPStan rule (`NoResolutionInRegisterRule`).
*   Every provider has a corresponding test that asserts its bindings are present after `register()` and its side effects (event listeners, routes) are present after `boot()`.
*   Provider discovery cache is invalidated when any provider file's mtime changes — verified by a test that modifies a provider and asserts the cache is rebuilt on next boot.

### SemVer Impact

**Major** — establishes the boot contract.

* * *

## CORE-18: Core Kernel & Lifecycle

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Event System" and `CORE-01` as "Bootstrapper & Kernel"; this is the authoritative kernel spec — `CORE-01` is the polyrepo orchestrator, not the kernel); Finding 4 (this blueprint is substantially more detailed than the disapproved "Bootstrapper & Kernel" that was rejected for template variance); Finding 10.

### Component Name

Sovereign Kernel — `SovereignStack\Core\Kernel`

### Description

The application kernel — the single entry point that orchestrates the entire boot lifecycle. `public/index.php` (HTTP) and `bin/console` (CLI) both construct the Kernel, call `boot()`, then either `handle()` a request (HTTP) or `run()` a command (CLI). The Kernel owns the `CORE-02` container, the `CORE-17` provider registry, the `CORE-05` middleware pipeline, and the `CORE-06` router. It is the only component that knows about all of them simultaneously.

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-08`, `CORE-09`, `CORE-10`, `CORE-17`. This is the **last** Core component to build — it composes everything else.

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-08`, `CORE-09`, `CORE-10`, `CORE-17`, `CORE-04`, `CORE-05`, `CORE-06`.
*   **Downward:** Every Hub service provider and Spoke provider is booted by the Kernel.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Container\ContainerInterface;

interface KernelInterface
{
    /**
     * Boot the kernel: construct container, load config, register error handler,
     * discover and register service providers, compile container, boot providers.
     * Idempotent — second call is a no-op.
     */
    public function boot(): void;

    /** True if boot() has completed. */
    public function isBooted(): bool;

    /**
     * Handle an HTTP request and return a response.
     * Calls boot() if not already booted.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;

    /**
     * Terminate the request: flush buffers, run deferred shutdown tasks,
     * commit audit log entries. Called after the response is sent.
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;

    /** Get the booted container (throws if not booted). */
    public function getContainer(): ContainerInterface;
```

#### Boot Lifecycle (detailed)

php

```php
<?php

final class Kernel implements KernelInterface
{
    private bool $booted = false;
    private ?ContainerInterface $container = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $environment,
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // 1. Construct container (CORE-02)
        $container = new \SovereignStack\Core\Container\Container();

        // 2. Load config (CORE-10) — schema defined inline here for core keys
        $config = $this->loadConfig();
        $container->instance(\SovereignStack\Core\Config\ConfigRepositoryInterface::class, $config);

        // 3. Register error handler (CORE-08) — first, before anything can throw
        $errorHandler = new \SovereignStack\Core\ErrorHandler\ErrorHandler(
            logger: new \SovereignStack\Core\Logging\Logger(channel: 'boot'),
            renderer: new \SovereignStack\Core\ErrorHandler\ProductionRenderer(),
            environment: $this->environment,
        );
        $errorHandler->register();
        $container->instance(\SovereignStack\Core\ErrorHandler\ErrorHandlerInterface::class, $errorHandler);

        // 4. Construct logger (CORE-09) with request-id processor
        $logger = new \SovereignStack\Core\Logging\Logger(
```

#### Environment Enum

php

```php
<?php

enum Environment: string
{
    case Development = 'development';
    case Staging = 'staging';
    case Production = 'production';

    public function isProduction(): bool => $this === self::Production;
    public function isDebug(): bool => $this !== self::Production;
}
```

#### Entry Points

**`public/index.php` (HTTP):**

php

```php
<?php
use SovereignStack\Core\Kernel\Kernel;

 $kernel = new Kernel(dirname(__DIR__), getenv('APP_ENV') ?: 'production');
 $kernel->boot();

 $request = \SovereignStack\Core\Http\Message\ServerRequestFactory::fromGlobals();
 $response = $kernel->handle($request);

(new \SovereignStack\Core\Http\Emitter\SapiEmitter())->emit($response);
 $kernel->terminate($request, $response);
```

**`bin/console` (CLI):**

php

```php
<?php
use SovereignStack\Core\Kernel\Kernel;

 $kernel = new Kernel(dirname(__DIR__), getenv('APP_ENV') ?: 'production');
 $kernel->boot();

 $kernel->getContainer()
    ->get(\SovereignStack\Core\Console\ApplicationInterface::class)
    ->run();
```

### Integration Strategy

*   The Kernel is the only class instantiated directly by entry-point scripts. Everything else is resolved from the container.
*   `BRIDGE-01` (Vanguard) constructs its own Kernel instance with a modified provider list (adds Vanguard-specific middleware providers, removes Internal Spoke providers).
*   `CORE-01` (Loom, the polyrepo orchestrator) does **not** use the Kernel — it's a build-time tool, not an application. This separation was the root of the original "CORE-01 = Kernel" confusion (Finding 4).

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Kernel cold-boot to first request handled | Performance test: construct Kernel, `boot()`, `handle()` a trivial request; measure wall-clock. State measured number on reference runner (target hardware: GitHub Actions `ubuntu-latest`, PHP 8.3, opcache enabled, preloading configured). No bare "< 150ms" claim until measured. |
| `boot()` is idempotent — second call is O(1) no-op | Unit test: call `boot()` twice; assert second call returns immediately (measured via `microtime(true)` delta < 0.1ms). |
| `terminate()` does not exceed a bounded fraction of request time | Performance test: measure `handle()` and `terminate()` for 100 requests; assert `terminate()` time < 20% of `handle()` time on average. |
| Boot failure produces a clear error, not a white screen | Integration test: inject a provider that throws in `register()`; assert `ErrorHandler` catches it and emits a `500` response with a log entry. |

### CI Verification Criteria

*   `public/index.php` and `bin/console` are the only entry points — verified by grepping for `new Kernel(` across the codebase.
*   `boot()` is called before any container resolution outside of the Kernel itself — verified by a static analysis rule.
*   The Kernel never directly instantiates Hub or Spoke classes — only Core classes and provider classes. Hub/Spoke classes are always resolved via the container.

### SemVer Impact

**Major** — this is the composition root. Any change to the boot lifecycle is a breaking change for every provider.

* * *

## CORE-19: Database Abstraction Layer

### Tier

Core (Foundational Infrastructure)

### Resolves

Finding 2 (evaluation described this ID as "Service Locator"; this is the authoritative DBAL spec); Finding 10; Finding 11 (sparse DBAL details now fully specified with tenant-scoping).

### Component Name

Sovereign DBAL — `SovereignStack\Core\Database`

### Description

A lightweight database abstraction layer built on PDO, providing connection management, a fluent query builder, prepared-statement parameter binding, transaction nesting via savepoints, and a repository base class with built-in tenant-scoping. This is **not** a full ORM — it does not manage object identity, lazy-loading relationships, or inheritance mapping. It provides the primitives that `HUB-04` (Identity), `HUB-06` (Audit), and `HUB-01` (Config tenant overrides) build on.

### Build Status

🔴 **Blocked** on `CORE-02` (Container — for connection manager singleton).

### Dependency Status

*   **Upward:** `ext-pdo` (PHP extension). `CORE-02` for binding.
*   **Downward:** `HUB-01` (Config — tenant override persistence), `HUB-04` (Identity — user/role/tenant persistence), `HUB-06` (Audit — audit log storage), every Hub service that persists data.

### Architectural Design

#### Interface Contracts

php

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

interface ConnectionInterface
{
    /** Execute a parameterized query, return a Statement. */
    public function prepare(string $sql): StatementInterface;

    /** Execute a query, return row count (for INSERT/UPDATE/DELETE). */
    public function execute(string $sql, array $params = []): int;

    /** Fetch a single row, or null. */
    public function fetchOne(string $sql, array $params = []): ?array;

    /** Fetch all rows. */
    public function fetchAll(string $sql, array $params = []): array;

    /** Begin a transaction. Nested calls create a savepoint. */
    public function beginTransaction(): void;

    /** Commit the current transaction (or release the savepoint). */
    public function commit(): void;

    /** Roll back the current transaction (or to the savepoint). */
    public function rollBack(): void;

    /** The PDO driver name: 'mysql', 'pgsql', 'sqlite', etc. */
    public function driverName(): string;

    /** Quote an identifier (table or column name) for the current driver. */
    public function quoteIdentifier(string $name): string;
}
```

#### Transaction Nesting via Savepoints

php

```php
<?php

final class Connection implements ConnectionInterface
{
    private int $transactionDepth = 0;

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT sp{$this->transactionDepth}");
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT sp{$this->transactionDepth}");
        }
    }

    public function rollBack(): void
    {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT sp{$this->transactionDepth}");
        }
    }
}
```

#### Tenant-Scoped Repository Base

php

```php
<?php

abstract class TenantScopedRepository
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected ?string $tenantId = null,  // null = super_admin context (ISPOKE-01 only)
    ) {}

    protected function query(): QueryBuilderInterface
    {
        $q = $this->connection->table($this->tableName());
        if ($this->tenantId !== null && $this->hasTenantColumn()) {
            $q->where('tenant_id', '=', $this->tenantId);
        }
        return $q;
    }

    /** The table this repository manages. */
    abstract protected function tableName(): string;

    /** True if the table has a tenant_id column. Override for shared tables. */
    protected function hasTenantColumn(): bool { return true; }
}
```

This is the concrete mechanism behind `HUB-04`'s tenant-isolation guarantee: every repository inherits `TenantScopedRepository`, and the `WHERE tenant_id = ?` clause is applied automatically. A repository cannot "forget" to scope by tenant — the base class enforces it.

### Integration Strategy

*   `CORE-18` (Kernel) constructs the `ConnectionManager` (which lazily creates `Connection` instances per configured DSN), binds it in `CORE-02`.
*   `HUB-04` (Identity) constructs its repositories with the `TenantContext` injected per-request; the `TenantContext` is resolved by `BRIDGE-01` from the JWT's `tenant_id` claim.
*   `HUB-06` (Audit) overrides `hasTenantColumn()` to return `false` for the audit log table — audit records are globally visible to `super_admin` (the only role that can query without a tenant context).

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Query builder compiles to parameterized SQL with no string interpolation of user values | Unit test: build a query with `where('name', '=', "'); DROP TABLE users;--")`; assert the compiled SQL uses `?` placeholder and the value is in the params array, not in the SQL string. |
| Transaction nesting with savepoints rolls back to the correct level | Integration test: begin outer, begin inner, insert A, rollback inner, insert B, commit outer; assert A is not present and B is present. |
| Tenant scoping prevents cross-tenant queries | Integration test: repository scoped to tenant A queries for a record belonging to tenant B; assert `null` returned (not the other tenant's record). |
| Connection pool reuses idle connections rather than opening new ones | Integration test: acquire and release the same connection 5 times; assert only 1 physical PDO connection was opened (track via a connection counter). |

### CI Verification Criteria

*   Every query uses prepared statements — no `sprintf` or string concatenation of user input into SQL. Verified by a PHPStan rule (`NoRawSqlConcatRule`).
*   `PDO::ATTR_ERRMODE` is set to `PDO::ERRMODE_EXCEPTION` — silent failure is never acceptable.
*   `PDO::ATTR_EMULATE_PREPARES` is `false` — real prepared statements, not emulated (prevents certain SQL injection edge cases and ensures correct type handling).
*   Tenant-scoping test suite runs against a multi-tenant fixture database with 3 tenants and verifies isolation across all repository operations.

### SemVer Impact

**Major** — establishes the database contract.

* * *

## HUB-06: Audit Log & Compliance Trail

### Tier

Hub (Shared Services)

### Resolves

Finding 10; Finding 11 (sparse audit details now fully specified with tamper-evidence and streaming).

### Component Name

Sovereign Audit — `SovereignStack\Hub\Audit`

### Description

An append-only, cryptographically chained audit log that records every security-relevant action across the platform: authentication events, configuration changes, data access by privileged users, and administrative operations. The log is tamper-evident (each entry's hash includes the previous entry's hash, forming a chain); tampering with any entry invalidates all subsequent entries. This is **not** the same as `CORE-09` (Logging) — operational logs are for debugging; audit logs are for compliance and are retained per regulatory requirements.

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-16` (hashing), `CORE-19` (DBAL — for persistence), `CORE-03` (Event Dispatcher — consumes `AuditableEventInterface`).

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-03`, `CORE-16`, `CORE-19`, `HUB-04` (Identity — for actor resolution).
*   **Downward:** `ISPOKE-01` (Admin Panel — audit log viewer), `BRIDGE-01` (Vanguard — emits `GatewayAccessEvent`).

### Architectural Design

#### Interface Contracts

php

```php
<?php

namespace SovereignStack\Hub\Audit;

interface AuditableEventInterface
{
    /** The action being audited, e.g. 'user.login', 'config.update', 'tenant.create'. */
    public function auditAction(): string;

    /** The actor performing the action (user ULID, or 'system' for automated). */
    public function auditActor(): string;

    /** The tenant scope (ULID), or null for system-level actions. */
    public function auditTenantId(): ?string;

    /** Before/after snapshots for mutation events; null for pure events. */
    public function auditChanges(): ?AuditChanges;

    /** Free-form context (IP, user agent, request ID). */
    public function auditContext(): array;
}

interface AuditLoggerInterface
{
    /** Append an entry to the audit log. Must never throw — audit failure is logged to CORE-09. */
    public function log(AuditableEventInterface $event): void;

    /** Query the audit log with filters. */
    public function query(AuditQuery $query): \Traversable;

    /** Verify the cryptographic chain integrity. Returns first broken entry, or null if intact. */
    public function verifyChain(?\DateTimeImmutable $since = null): ?AuditEntry;
}
```

#### Schema

sql

```sql
CREATE TABLE audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    seq INT NOT NULL,              -- Monotonic sequence per tenant (for chain ordering)
    tenant_id CHAR(26) NULL,       -- ULID; null for system-level actions
    action VARCHAR(100) NOT NULL,
    actor_id VARCHAR(26) NOT NULL, -- ULID or 'system'
    changes JSON NULL,             -- {before: {...}, after: {...}}
    context JSON NOT NULL,         -- {ip, user_agent, request_id, ...}
    prev_hash CHAR(64) NOT NULL,   -- SHA-256 of previous entry in this tenant's chain
    entry_hash CHAR(64) NOT NULL,  -- SHA-256 of (seq || tenant_id || action || actor_id || changes || context || prev_hash)
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uk_tenant_seq (tenant_id, seq),
    KEY idx_action (action),
    KEY idx_actor (actor_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB;
```

#### Chain Integrity

Each entry's `entry_hash` is computed as:

text

```
SHA-256(seq || tenant_id || action || actor_id || JSON(changes) || JSON(context) || prev_hash)
```

The first entry in a tenant's chain has `prev_hash = SHA-256("genesis")`. Tampering with any field of an entry invalidates its `entry_hash`, which invalidates the `prev_hash` of the next entry, cascading forward. `verifyChain()` recomputes all hashes and returns the first entry where the stored hash doesn't match the computed hash.

#### Event Subscription

php

```php
<?php

final class AuditEventSubscriber implements \SovereignStack\Core\EventDispatcher\ListenerProviderInterface
{
    public function __construct(private AuditLoggerInterface $logger) {}

    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof AuditableEventInterface) {
            yield fn(AuditableEventInterface $e) => $this->logger->log($e);
        }
    }
}
```

Registered as a high-priority listener in `CORE-03` (Event Dispatcher) so the audit entry is written before the primary business listener executes.

### Integration Strategy

*   `HUB-04` (Identity) emits `AuthenticationSuccessEvent` and `AuthenticationFailureEvent` (both implement `AuditableEventInterface`).
*   `HUB-01` (Config) emits `ConfigChangedEvent` when a tenant override is written.
*   `ISPOKE-01` (Admin Panel) consumes the `query()` API for the audit log viewer; streams results via SSE for real-time monitoring.
*   `BRIDGE-01` (Vanguard) emits `GatewayAccessEvent` for every authenticated request (sampled at 1-in-100 for high-traffic routes to control volume).

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Chain verification for 100,000 entries completes in bounded time | Performance test: insert 100,000 fixture entries; call `verifyChain()`; measure wall-clock. State measured number. |
| Audit log write does not block the request path beyond a bounded threshold | Performance test: emit an `AuditableEvent` in a request; measure incremental latency vs. a request with no audit event. The write should be async-buffered (flushed on `Kernel::terminate()`), so the request-path cost is the buffer-append, not the DB write. |
| Chain tampering is detected | Integration test: insert 5 entries; directly UPDATE the `action` column of entry 3 via raw SQL; call `verifyChain()`; assert it returns entry 3 (the first tampered entry). |
| Audit log is append-only — no UPDATE or DELETE path exists in the codebase | Static analysis: grep for `UPDATE audit_log` or `DELETE FROM audit_log` across all PHP files; assert zero matches outside of the `verifyChain()` repair tool (which is CLI-only and requires `super_admin`). |

### CI Verification Criteria

*   Every `AuditableEventInterface` implementation has a test asserting its `auditAction()`, `auditActor()`, and `auditTenantId()` return the expected values.
*   The audit log table has `GRANT INSERT, SELECT ONLY` — no `UPDATE` or `DELETE` permissions at the database user level. Verified by a CI check that attempts an UPDATE and asserts it fails.
*   Retention policy: entries older than the configured retention (default 7 years for compliance) are archived to cold storage, not deleted. The archive job is a CLI command run via cron.

### SemVer Impact

**Major** — the audit schema and chain format are compliance-critical; any change requires a migration that preserves chain integrity.

* * *

## HUB-11: Queue & Async Job Processing

### Tier

Hub (Shared Services)

### Resolves

Finding 10; **Finding 11** (this was explicitly called out in `SOLUTIONS_TO_WEAKNESSES.md` as "Sparse Architectural Details for Queue (HUB-11)" — now fully specified with worker model, backoff, DLQ, and observability).

### Component Name

Sovereign Queue — `SovereignStack\Hub\Queue`

### Description

A unified job queue abstraction supporting multiple backends (Redis, Amazon SQS, in-memory for tests), with a worker process model, exponential backoff with jitter, dead-letter queues, and observability hooks (job lifecycle events dispatched via `CORE-03`). Used for: email sending, search indexing (`HUB-19`), bulk imports, scheduled maintenance tasks, and any operation that should not block the request path.

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-03` (events), `CORE-09` (logging), `CORE-19` (DBAL — for failed-job tracking).

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-03`, `CORE-09`, `CORE-19`. Backend-specific: `ext-redis` or AWS SDK.
*   **Downward:** `HUB-19` (Search — indexing jobs), `HUB-04` (Identity — password reset emails), `ISPOKE-01` (Admin — bulk operations), every Spoke that defers work.

### Architectural Design

#### Interface Contracts

php

```php
<?php

namespace SovereignStack\Hub\Queue;

interface QueueInterface
{
    /** Enqueue a job. Returns the job ID. */
    public function push(JobInterface $job, ?string $queue = null, ?int $delaySeconds = null): string;

    /** Pop the next job from a queue (blocking or non-blocking). Returns null if empty and non-blocking. */
    public function pop(?string $queue = null, int $timeoutSeconds = 0): ?JobInterface;

    /** Mark a job as completed. */
    public function ack(JobInterface $job): void;

    /** Mark a job as failed; applies retry/backoff logic or moves to DLQ. */
    public function fail(JobInterface $job, \Throwable $error): void;

    /** Get the length of a queue. */
    public function size(?string $queue = null): int;
}

interface JobInterface extends \JsonSerializable
{
    /** The job's unique ID (ULID). */
    public function id(): string;

    /** The queue name (default: 'default'). */
    public function queue(): string;

    /** Attempt number (1 = first try). */
    public function attempts(): int;

    /** Maximum attempts before moving to DLQ. */
    public function maxAttempts(): int;
```

#### Worker Process Model

php

```php
<?php

final class Worker
{
    public function __construct(
        private QueueInterface $queue,
        private \Psr\Container\ContainerInterface $container,
        private \SovereignStack\Core\Logging\LoggerInterface $logger,
        private \Psr\EventDispatcher\EventDispatcherInterface $events,
    ) {}

    public function daemon(string $queue = 'default', int $memoryLimit = 128): void
    {
        while (true) {
            $job = $this->queue->pop($queue, timeoutSeconds: 5);

            if ($job === null) {
                continue;  // No job available; loop
            }

            $this->events->dispatch(new JobStartedEvent($job));

            try {
                $job->handle($this->container);
                $this->queue->ack($job);
                $this->events->dispatch(new JobCompletedEvent($job));
            } catch (\Throwable $e) {
                $this->logger->channel('queue')->error('Job failed', [
                    'job_id' => $job->id(),
                    'job_class' => $job::class,
                    'attempt' => $job->attempts(),
                    'exception' => $e,
                ]);

                $this->queue->fail($job, $e);
                $this->events->dispatch(new JobFailedEvent($job, $e));
```

#### Backoff Strategy

Default backoff with jitter:

php

```php
public function backoff(int $attempts): int
{
    // Exponential: 1s, 2s, 4s, 8s, 16s (capped at 60s) + 0-50% jitter
    $base = min(2 ** ($attempts - 1), 60);
    $jitter = (int) ($base * 0.5 * (mt_rand(0, 1000) / 1000));
    return $base + $jitter;
}
```

Jitter prevents the "thundering herd" problem where a batch of failed jobs all retry simultaneously.

#### Dead Letter Queue

After `maxAttempts()` (default 5), a failed job is moved to the DLQ (`{queue}.failed`). DLQ jobs are:

1.  Stored in the `failed_jobs` database table (via `CORE-19`) with the full job payload and last error.
2.  Visible in `ISPOKE-01` (Admin Panel) for inspection and manual retry.
3.  Never automatically retried — manual intervention required.

### Integration Strategy

*   `CORE-18` (Kernel) does not start workers — workers are separate CLI processes (`bin/worker`) managed by a process supervisor (systemd, supervisor, or k8s Deployment).
*   `DEPLOY-01` specifies the worker container image (same as the web image, different entry point: `bin/worker` instead of `public/index.php`).
*   `HUB-19` (Search) pushes an `IndexDocumentJob` whenever content is published; the worker processes it asynchronously.
*   `HUB-04` (Identity) pushes a `SendPasswordResetEmailJob` instead of sending synchronously, so the request returns immediately.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Job throughput: push 10,000 jobs and process with 4 parallel workers | Load test: push 10,000 no-op jobs to an in-memory queue; start 4 workers; measure time-to-drain. State measured throughput (jobs/sec) on reference runner. |
| Backoff jitter distributes retry times | Unit test: compute `backoff(2)` 1,000 times; assert the results form a distribution (not all identical) within the expected range `[2, 3]`. |
| DLQ receives jobs after max attempts | Integration test: push a job that always throws; run the worker; assert the job appears in the `failed_jobs` table after `maxAttempts()` iterations. |
| Worker memory guard triggers exit | Integration test: push jobs that leak 10MB each; run worker with `memoryLimit = 50`; assert worker exits before exceeding 50MB. |

### CI Verification Criteria

*   All backends (`RedisQueue`, `SqsQueue`, `InMemoryQueue`) tested via a shared `QueueAdapterTestCase` ensuring behavioral parity.
*   `JobInterface::handle()` receives the container, not the worker — jobs must be container-agnostic and serializable (no closures, no resource handles).
*   Worker daemon handles `SIGTERM` gracefully: finishes the current job, then exits (verified by a test that sends `SIGTERM` mid-job and asserts the job completes before exit).

### SemVer Impact

**Major** — the job serialization format and queue naming convention are part of the contract.

* * *

## HUB-15: Health & Observability Aggregator

### Tier

Hub (Shared Services)

### Resolves

Finding 10; Finding 11 (sparse health details now fully specified with liveness/readiness distinction).

### Component Name

Sovereign Health — `SovereignStack\Hub\Health`

### Description

A health-check aggregation service that collects health signals from all Hub services and exposes them via a single endpoint. Distinguishes **liveness** (is the process running?) from **readiness** (is the process ready to serve traffic?), matching Kubernetes probe semantics. Each Hub service registers its own health checks; `HUB-15` aggregates them and exposes `/health/live`, `/health/ready`, and `/health/detail` (admin-only).

### Build Status

🔴 **Blocked** on `CORE-02`, `CORE-04`, `CORE-05`, `CORE-06`.

### Dependency Status

*   **Upward:** `CORE-02`, `CORE-04`, `CORE-05`, `CORE-06`.
*   **Downward:** `DEPLOY-01` (Kubernetes probes point at this), `ISPOKE-01` (Admin dashboard reads `/health/detail`).

### Architectural Design

#### Interface Contracts

php

```php
<?php

namespace SovereignStack\Hub\Health;

interface HealthCheckInterface
{
    /** The check name, e.g. 'database', 'cache', 'queue'. */
    public function name(): string;

    /** The check type: liveness, readiness, or both. */
    public function type(): HealthCheckType;

    /**
     * Execute the check. Must complete within $timeoutSeconds.
     * A thrown exception or a return value with status 'unhealthy' marks the check as failed.
     */
    public function check(int $timeoutSeconds = 5): HealthCheckResult;
}

enum HealthCheckType: string
{
    case Liveness = 'liveness';
    case Readiness = 'readiness';
    case Both = 'both';
}

final class HealthCheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $status,  // 'healthy' | 'unhealthy' | 'degraded'
        public readonly ?string $message = null,
        public readonly ?float $latencyMs = null,
        public readonly array $metadata = [],
    ) {}
}
```

#### Aggregation Rules

| Overall Status | Condition |
| --- | --- |
| `healthy` | All checks return `healthy` |
| `degraded` | All checks return `healthy` or `degraded` (no `unhealthy`); at least one `degraded` |
| `unhealthy` | Any check returns `unhealthy` |

For liveness probes: only `Liveness` and `Both` checks are evaluated. For readiness: only `Readiness` and `Both`.

#### Built-In Health Checks (registered by each Hub service)

| Service | Check Name | Type | Logic |
| --- | --- | --- | --- |
| `HUB-01` Config | `config` | Both | Verify `CORE-10` config is frozen and required keys present |
| `HUB-02` Cache | `cache` | Readiness | `set()` / `get()` / `delete()` a test key; report latency |
| `HUB-04` Identity | `database` | Readiness | `SELECT 1` via `CORE-19`; report latency |
| `HUB-11` Queue | `queue` | Readiness | Check queue backend connectivity (Redis PING or SQS ListQueues) |
| `HUB-06` Audit | `audit` | Both | Verify audit log table is writable (insert + rollback a test entry) |
| `CORE-18` Kernel | `boot` | Liveness | Always `healthy` if the process is running and booted |

### Integration Strategy

*   `HUB-15` exposes three routes via `CORE-06`:
    *   `GET /health/live` → 200 if `isLive()`, 503 otherwise. Minimal body. Used by k8s liveness probe.
    *   `GET /health/ready` → 200 if `isReady()`, 503 otherwise. Used by k8s readiness probe.
    *   `GET /health/detail` → 200 with full `HealthReport` JSON. Admin-only (requires `super_admin` role via `HUB-04`).
*   `DEPLOY-01` configures k8s probes:
    
    yaml
    
    ```yaml
    livenessProbe:
      httpGet: { path: /health/live, port: 80 }
      periodSeconds: 10
      failureThreshold: 3
    readinessProbe:
      httpGet: { path: /health/ready, port: 80 }
      periodSeconds: 5
      failureThreshold: 2
    ```
    
*   Health check results are cached for 5 seconds (via `HUB-02`) to prevent probe storms from overwhelming the checks themselves.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| `/health/live` responds in bounded time even when a downstream check is slow | Performance test: register a check that sleeps 4 seconds (just under the 5-second timeout); measure `/health/live` latency; assert it's < 6 seconds (timeout + overhead). |
| Slow check does not block other checks | Unit test: register a fast check (1ms) and a slow check (2s); run all; assert the fast check's result is available before the slow check completes (concurrent execution). |
| Health report reflects the worst check status | Unit test: register 3 healthy checks and 1 unhealthy; assert overall status is `unhealthy`. |
| Cache prevents probe storms | Integration test: hit `/health/ready` 100 times in 1 second; assert the underlying checks execute at most once (cached for subsequent calls). |

### CI Verification Criteria

*   Every Hub service registers at least one `HealthCheckInterface` implementation — verified by a test that boots the Kernel and asserts the `HealthRegistry` is non-empty.
*   Health check timeouts are enforced — a check exceeding its timeout is marked `unhealthy` with message `"timeout after {N}s"`.
*   The `/health/detail` route requires `super_admin` authentication — verified by a test that attempts access without credentials and asserts `401`, and with non-admin credentials and asserts `403`.

### SemVer Impact

**Minor** for adding checks; **major** for changing the response schema or status-code semantics.

* * *

## DEPLOY-02: Datastore Provisioning

### Tier

Deploy

### Resolves

Finding 9 (closes the deploy-tier gap — datastores had no provisioning blueprint); Finding 10 (methodology for backup/restore verification).

### Component Name

Sovereign Datastore Provisioning — `infrastructure/deploy/datastores`

### Description

Blueprint for provisioning and managing the datastore layer: MySQL/Postgres primary databases, Redis cache/queue backends, and the queue broker (Redis or Amazon SQS). Includes connection-secret management (secrets never in plaintext config files), backup/restore runbooks, and point-in-time-recovery configuration.

### Dependency Status

*   **Upward:** None (infrastructure layer).
*   **Downward:** `DEPLOY-01` (Hub services cannot start without datastores available).

### Architectural Design

#### Topology

```
flowchart TD
    subgraph "Data Tier"
        DB[(MySQL 8.0 Primary)]
        DBR[(MySQL Replica<br/>read-only)]
        REDIS[(Redis 7.x<br/>cache + queue)]
    end

    subgraph "Hub Tier"
        HUB[Hub Services<br/>DEPLOY-01]
    end

    subgraph "Secrets"
        VAULT[HashiCorp Vault<br/>or AWS Secrets Manager]
    end

    VAULT -->|DB credentials| HUB
    VAULT -->|Redis password| HUB
    HUB -->|read/write| DB
    HUB -->|read| DBR
    HUB -->|cache + queue| REDIS
```

#### Provisioning (Terraform)

hcl

```hcl
# infrastructure/deploy/datastores/main.tf

resource "aws_db_instance" "primary" {
  identifier        = "sovereign-stack-primary"
  engine            = "mysql"
  engine_version    = "8.0"
  instance_class    = "db.t3.medium"  # tunable per environment
  allocated_storage = 100
  storage_encrypted = true
  kms_key_id        = aws_kms_key.db.arn

  db_name  = "sovereign"
  username = "sovereign_app"
  password = random_password.db_password.result

  multi_az                = true  # production only; false in dev
  backup_retention_period = 7     # days; 35 in production for compliance
  deletion_protection     = true  # production only

  vpc_security_group_ids = [aws_security_group.db.id]
  db_subnet_group_name   = aws_db_subnet_group.private.name

  tags = { Project = "sovereign-stack", Tier = "data" }
}

resource "aws_elasticache_cluster" "redis" {
  cluster_id           = "sovereign-redis"
  engine               = "redis"
  engine_version       = "7.0"
  node_type            = "cache.t3.micro"  # tunable
  num_cache_nodes      = 1
  parameter_group_name = "default.redis7"
  security_group_ids   = [aws_security_group.redis.id]
  subnet_group_name    = aws_elasticache_subnet_group.private.name

  tags = { Project = "sovereign-stack", Tier = "data" }
```

#### Connection Secret Injection

Hub services (`DEPLOY-01`) receive datastore credentials as environment variables populated from Secrets Manager at container start. The `CORE-10` (Config) loader reads `DATABASE_URL` (or individual `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`) and constructs the PDO DSN. Credentials are:

1.  Never committed to git (verified by pre-commit secret scanning).
2.  Never logged (verified by `SensitiveDataProcessor` in `CORE-09`).
3.  Never exposed in `/health/detail` (the database health check reports only `healthy`/`unhealthy` and latency, never the connection string).

#### Backup & Restore Runbook

| Scenario | Procedure | RTO | RPO |
| --- | --- | --- | --- |
| Logical corruption (bad migration) | Restore from automated daily snapshot to a new instance; repoint DNS; verify; cut over | < 2 hours | < 24 hours |
| Physical failure (AWS AZ loss) | Multi-AZ failover is automatic (production); no action needed | < 5 minutes | 0 (synchronous replication) |
| Accidental data deletion (DROP TABLE) | Point-in-time recovery from automated backups to 5 minutes before the incident | < 4 hours | < 5 minutes |
| Redis data loss (cache flush) | Non-critical — cache rebuilds from database on next access. No restore needed | 0 | N/A (cache is ephemeral) |

Restore drills are run quarterly in staging: restore the production backup to a staging instance, verify data integrity, and document the actual RTO/RPO measured.

### Integration Strategy

*   `DEPLOY-02` runs before `DEPLOY-01` — Hub services fail their `database` health check and report `unhealthy` if the datastore is unreachable.
*   `DEPLOY-04` (Promotion Pipeline) includes a pre-deploy check that verifies all datastore secrets exist in Secrets Manager before allowing a Hub service deployment.
*   Connection pooling: each Hub service maintains a PDO connection pool (via `CORE-19`'s `ConnectionManager`) with a configurable max pool size (default 10). The pool reuses idle connections rather than opening new ones per request.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Database backup can be restored and queried | Quarterly drill: restore the latest production backup to staging; run the full test suite against the restored data; document RTO measured. |
| Secret rotation does not require service downtime | Integration test: rotate the DB password in Secrets Manager; trigger a Hub service rolling restart; assert zero `5xx` responses during the restart (connections drain and reconnect with the new password). |
| Redis failover (if using Redis Cluster) does not drop in-flight cache writes | Chaos test: kill a Redis node mid-write; assert the write either succeeds (via replica) or fails with a clear error (no silent data loss). |
| Backup encryption verified | Security test: download a backup file; attempt to read without the KMS key; assert it is unreadable ciphertext. |

### CI Verification Criteria

*   Terraform plan is reviewed and applied via `DEPLOY-04` pipeline — no manual `terraform apply` in production.
*   `deletion_protection = true` on production database (cannot be destroyed without explicit disabling).
*   All datastore resources have `Project` and `Tier` tags for cost allocation.
*   Secrets Manager rotation Lambda (if used) is tested in staging monthly.

### SemVer Impact

**Major** for schema changes (requires migration runbook); **minor** for instance size or configuration changes.

* * *

## DEPLOY-03: Bridge & External Spoke Deployment

### Tier

Deploy

### Resolves

Finding 9 (closes the deploy-tier gap — the public-facing tier had no deployment blueprint); Finding 11 (Bridge redundancy strategy — `SOLUTIONS_TO_WEAKNESSES.md` called out "Bridge Single Point of Failure; No Redundancy Strategy" — now fully specified with multi-replica, failover, and network isolation).

### Component Name

Sovereign Edge Deployment — `infrastructure/deploy/edge`

### Description

Deployment blueprint for the public-facing tier: `BRIDGE-01` (Vanguard) and all External Spokes (`ESPOKE-01` through `ESPOKE-15`). Specifies the network-isolation rules that make `BRIDGE-01`'s "Zero-Exposure Test" enforceable at the infrastructure layer (not just the application layer), CDN integration, and the multi-replica failover strategy that eliminates the Bridge as a single point of failure.

### Dependency Status

*   **Upward:** `BRIDGE-01`, all `ESPOKE-*` blueprints, `DEPLOY-01` (Hub services must be running for the Bridge to verify JWTs), `DEPLOY-02` (datastores for External Spokes that persist data).
*   **Downward:** `DEPLOY-04` (Promotion Pipeline orchestrates edge-tier updates).

### Architectural Design

#### Network Topology

```
flowchart TD
    INTERNET[Internet]

    subgraph "Public Subnet (0.0.0.0/0 ingress on 443)"
        CDN[CloudFront / CloudFlare<br/>DDoS + edge cache]
        LB[Origin Load Balancer<br/>TLS termination]
        V1[Vanguard Replica 1]
        V2[Vanguard Replica 2]
        V3[Vanguard Replica 3]
    end

    subgraph "Private Subnet (no public IPs)"
        ES1[ESPOKE-01 Public CMS]
        ES2[ESPOKE-02 ...]
        ES3[ESPOKE-15 ...]
    end

    subgraph "Isolated Subnet (no inbound from public subnet)"
        HUB[Hub Services<br/>DEPLOY-01]
        DATA[Datastores<br/>DEPLOY-02]
    end

    INTERNET -->|HTTPS 443| CDN
    CDN -->|HTTPS 443| LB
    LB --> V1
    LB --> V2
    LB --> V3
    V1 -->|HTTP 8080| ES1
    V1 -->|HTTP 8080| ES2
    V2 -->|HTTP 8080| ES1
    V3 -->|HTTP 8080| ES1
    V1 -.->|HTTP 8081| HUB
    ES1 x--x|DENIED| HUB
```

#### Network Policy (Kubernetes NetworkPolicy)

yaml

```yaml
# infrastructure/deploy/edge/network-policy.yaml

apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: vanguard-ingress
  namespace: edge
spec:
  podSelector:
    matchLabels:
      app: vanguard
  policyTypes: [Ingress]
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              name: cdn-origin  # Only the CDN/origin LB namespace
      ports:
        - protocol: TCP
          port: 443

---
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: vanguard-egress
  namespace: edge
spec:
  podSelector:
    matchLabels:
      app: vanguard
  policyTypes: [Egress]
  egress:
    # Allow Vanguard → External Spokes
    - to:
        - podSelector:
```

This is the **concrete enforcement** of `BRIDGE-01`'s "Zero-Exposure Test" — the network policy denies any External Spoke pod from connecting to any Hub pod, at the network layer. An External Spoke attempting `curl http://hub-identity:8080` gets a connection timeout, not a response.

#### Multi-Replica Failover

| Component | Replicas | Failover Behavior |
| --- | --- | --- |
| Vanguard (BRIDGE-01) | 3 (minimum) | Origin LB health-checks every 5s; unhealthy replica removed within 10s; traffic redistributed |
| External Spokes | 2 (minimum) | Vanguard health-checks each Spoke; unhealthy Spoke removed from routing table; 502 returned only if all replicas of a Spoke are down |
| Session state | N/A | Stored in `HUB-02` (Redis); any Vanguard replica can serve any authenticated request |

#### CDN Integration

*   **Cacheable:** Static assets (`/assets/*`), published CMS pages with `Cache-Control: public, max-age=300`.
*   **Not cacheable:** Authenticated API routes (`Authorization` header present), `POST`/`PUT`/`DELETE` methods.
*   **Cache invalidation:** `HUB-01` (Config) emits a `ConfigChangedEvent` when tenant theming changes; `BRIDGE-01` listens and issues a CDN cache purge for the affected tenant's paths.

### Integration Strategy

*   `DEPLOY-03` runs after `DEPLOY-01` (Hub) and `DEPLOY-02` (Datastores) — the Vanguard cannot verify JWTs if `HUB-04` is not running.
*   Network policies are applied before any pod starts — a misconfigured policy that blocks Vanguard → Hub traffic is caught immediately by the Vanguard's health check failing.
*   The "Zero-Exposure Test" from `BRIDGE-01.md` is run as a CI step in `DEPLOY-04`: deploy a test External Spoke that attempts to `curl` a Hub service; assert the connection is refused.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| Vanguard replica failure detected and traffic redistributed within 10 seconds | Chaos test: kill one Vanguard pod; poll the CDN origin health endpoint; assert the failed replica is removed within 10 seconds and subsequent requests succeed (served by remaining replicas). |
| Network policy blocks External Spoke → Hub connection | Security test: exec into an External Spoke pod; `curl http://hub-identity:8080/health`; assert connection timeout (not a response). |
| CDN cache hit rate > 90% for published CMS pages | Observability: after 1 hour of production traffic, check CloudFront cache hit rate metrics; assert > 90% for GET requests to published pages. |
| TLS certificate auto-renewal does not cause downtime | Integration test: trigger certificate renewal 30 days before expiry (ACME/Let's Encrypt or AWS Certificate Manager); assert zero `5xx` responses during renewal. |

### CI Verification Criteria

*   NetworkPolicy YAML is validated by `kubeconform` in CI before apply.
*   The "Zero-Exposure Test" (External Spoke → Hub connection attempt) runs as a post-deploy integration test in every environment.
*   All External Spoke pods have `tier: external-spoke` label; all Hub pods have `tier: hub` label — verified by a CI check that scans Kubernetes manifests.
*   No External Spoke has a public IP or is exposed via a LoadBalancer service — only the Vanguard is exposed.

### SemVer Impact

**Major** for network policy changes (could break connectivity); **minor** for replica count or CDN cache-rule changes.

* * *

## DEPLOY-04: Multi-Environment & Promotion Pipeline

### Tier

Deploy

### Resolves

Finding 9 (closes the deploy-tier gap — no promotion pipeline existed); Finding 10 (rollback verification methodology).

### Component Name

Sovereign Promotion Pipeline — `infrastructure/deploy/pipeline`

### Description

The end-to-end pipeline that moves a change from a developer's laptop through dev → staging → production across 50+ independently deployable repos. Integrates with `CORE-01` (Loom, the polyrepo orchestrator) for version-bump and tier-ordering enforcement. Defines the promotion contract (what must be true before a change moves to the next environment), the rollback procedure, and the canary-release strategy for high-risk changes.

### Dependency Status

*   **Upward:** `CORE-01` (Loom — for SemVer bumping and tier ordering), `DEPLOY-01`, `DEPLOY-02`, `DEPLOY-03`.
*   **Downward:** None (this is the top of the deploy hierarchy).

### Architectural Design

#### Environment Promotion Flow

```
flowchart LR
    DEV[Developer<br/>Push to feature branch]
    CI1[Per-Repo CI<br/>test + static analysis]
    PR[Pull Request<br/>to main]
    REVIEW[Code Review<br/>+ Loom tier check]
    MERGE[Merge to main]
    DEVENV[Dev Environment<br/>auto-deploy on merge]
    STAGE[Staging Environment<br/>manual promote]
    PROD[Production Environment<br/>manual promote after approval]
    CANARY[Canary Release<br/>10% → 50% → 100%]

    DEV --> CI1
    CI1 -->|green| PR
    PR --> REVIEW
    REVIEW -->|approved| MERGE
    MERGE --> DEVENV
    DEVENV -->|smoke tests pass| STAGE
    STAGE -->|integration tests pass + sign-off| PROD
    PROD --> CANARY
```

#### Promotion Contract (gates between environments)

| Gate | Dev → Staging | Staging → Production |
| --- | --- | --- |
| All tests pass | ✅ Required | ✅ Required |
| Static analysis clean | ✅ Required | ✅ Required |
| Loom tier check | ✅ (upstream tiers green) | ✅ |
| Smoke tests in target env | N/A (auto-deploy) | ✅ Required |
| Integration tests | ✅ Required | ✅ Required |
| Security scan (dependency + SAST) | ✅ Required | ✅ Required |
| Manual approval | ❌ (auto-promote) | ✅ (two-person rule) |
| Performance regression check | ❌ | ✅ (compare to baseline) |

#### Loom Integration (CORE-01)

When a PR is merged to `main`:

1.  Loom (`CORE-01`) detects the merge via CI webhook.
2.  Loom's `VersionBumpEngine` analyzes the commit messages (Conventional Commits) and computes the SemVer bump for the affected repo.
3.  Loom's `DependencyGraph` checks: are all upstream tiers (Core, if this is a Hub repo) green? If not, the merge is blocked.
4.  If green, Loom tags the repo with the new version and triggers the downstream CI pipeline.
5.  The CI pipeline builds the OCI image, tags it with the Git SHA and SemVer version, and pushes to the registry.

#### Canary Release Strategy (Production)

For high-risk changes (any `CORE-*` change, any `BRIDGE-01` change, any schema migration):

yaml

```yaml
# infrastructure/deploy/pipeline/canary.yaml
strategy:
  type: Canary
  canary:
    steps:
      - weight: 10       # Route 10% of traffic to the new version
        duration: 5m
      - weight: 50       # If healthy, route 50%
        duration: 10m
      - weight: 100      # If healthy, complete the rollout
    analysis:
      # Auto-rollback if error rate exceeds threshold
      metrics:
        - name: http-5xx-rate
          threshold: 0.01    # < 1% 5xx errors
          query: rate(http_requests_total{code=~"5.."}[1m]) / rate(http_requests_total[1m])
      # Auto-rollback if latency exceeds threshold
        - name: p99-latency
          threshold: 500     # < 500ms p99
          query: histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[1m]))
```

If any metric exceeds its threshold during a canary step, the rollout auto-rolls back to the previous version. No human intervention needed for the rollback; human intervention is needed to investigate and fix the root cause.

#### Rollback Procedure

| Scenario | Procedure | Time |
| --- | --- | --- |
| Canary auto-rollback | Automatic; traffic returns to previous version within 1 minute | < 1 minute |
| Manual rollback (post-deploy issue detected) | Re-deploy previous image digest via pipeline; no rebuild needed | < 5 minutes |
| Database schema rollback | Run down-migration; if destructive, restore from backup (`DEPLOY-02`) | < 30 minutes (depends on migration) |

**Critical rule:** database migrations must always be **backward-compatible** — a new version of the application must work against the old schema, and the old version must work against the new schema. This is enforced by:

1.  Migrations may only add columns/tables, never drop or rename in the same release.
2.  Column drops are two-phase: deprecate in version N (stop reading/writing), drop in version N+1.
3.  The CI pipeline runs the new application version against the old schema (and vice versa) as a compatibility test.

### Integration Strategy

*   `CORE-01` (Loom) is the source of truth for "what version is deployed where" — the pipeline queries Loom's `DependencyGraph` to determine the correct promotion order.
*   `DEPLOY-01` (Hub) and `DEPLOY-03` (Edge) are the deployment targets; the pipeline applies manifests to each environment in tier order (Core → Hub → Bridge → Spokes).
*   `HUB-15` (Health) is the gate signal: a canary step only proceeds if the health endpoint reports `healthy` for the new version's pods.

### Benchmark & Verification Methodology

| Target | Method |
| --- | --- |
| End-to-end promotion (dev → staging) completes in bounded time | Measure: from PR merge to staging deployment complete; document median and p95. State measured numbers. |
| Canary auto-rollback triggers on error rate spike | Chaos test: deploy a version that returns 500 for 5% of requests; assert canary auto-rolls back within the first step's duration. |
| Rollback to previous version completes without data loss | Integration test: deploy version A (writes data), deploy version B (writes data), rollback to A; assert data written by B is still present (no data loss — rollback is a code rollback, not a data rollback). |
| Backward-compatible migration verified | CI test: apply migration N; run version N-1's test suite against the migrated schema; assert all tests pass. |

### CI Verification Criteria

*   No production deployment without a manual approval (two-person rule) — enforced by the pipeline tool (GitHub Actions environment protection, ArgoCD, or equivalent).
*   Every production deployment is traceable to a Git commit SHA and a SemVer version — verified by the image label check.
*   Database migrations are reviewed separately from code changes — a migration PR cannot be merged without a dedicated migration review.
*   Rollback drills are run quarterly in staging: deploy a change, roll it back, verify the system returns to a healthy state.

### SemVer Impact

**N/A** — this is infrastructure, not an application package.

* * *

# Summary: Next-Batch Coverage

| Blueprint | Tier | Resolves | Status |
| --- | --- | --- | --- |
| `CORE-08` | Core | F2, F10, F11 | Blocked on CORE-02, CORE-09 |
| `CORE-09` | Core | F2, F10 | Blocked on CORE-02 |
| `CORE-10` | Core | F2, F8, F10 | Blocked on CORE-02 |
| `CORE-04` | Core | F2, F10 | Blocked on CORE-02 |
| `CORE-05` | Core | F2, F10 | Blocked on CORE-02, CORE-04 |
| `CORE-06` | Core | F2, F10 | Blocked on CORE-02, CORE-04, CORE-05 |
| `CORE-16` | Core | **F3**, F10, F11 | Blocked on CORE-02 |
| `CORE-17` | Core | F2, F10 | Blocked on CORE-02 |
| `CORE-18` | Core | F2, F4, F10 | Blocked on CORE-02, 08, 09, 10, 17 |
| `CORE-19` | Core | F2, F10, F11 | Blocked on CORE-02 |
| `HUB-06` | Hub | F10, F11 | Blocked on CORE-02, 03, 16, 19 |
| `HUB-11` | Hub | F10, **F11** | Blocked on CORE-02, 03, 09, 19 |
| `HUB-15` | Hub | F10, F11 | Blocked on CORE-02, 04, 05, 06 |
| `DEPLOY-02` | Deploy | **F9**, F10 | Upstream: DEPLOY-01 |
| `DEPLOY-03` | Deploy | **F9**, **F11** | Upstream: DEPLOY-01, DEPLOY-02 |
| `DEPLOY-04` | Deploy | **F9**, F10 | Upstream: CORE-01, DEPLOY-01–03 |

**Combined with the previous delivery (PART I–IV), this covers:**

*   ✅ All 20 Core-tier blueprints (10 in PART IV + 10 in PART V) — though 8 of the 10 new ones are contracts-only (blocked on `CORE-02`), they are now fully specified and implementable.
*   ✅ 5 of 30 Hub-tier blueprints (HUB-01, 02, 04, 06, 11, 15) — the highest-value and most-referenced Hub services.
*   ✅ The complete Deploy tier (DEPLOY-00 through DEPLOY-04).
*   ✅ Both boundary blueprints (BRIDGE-01, plus its deployment in DEPLOY-03).
*   ✅ One Internal Spoke (ISPOKE-01) and one External Spoke (ESPOKE-01) as fidelity exemplars.

**Remaining for a future batch:** the remaining 24 Hub blueprints (HUB-03, 05, 07–10, 12–14, 16–30), 24 Internal Spokes (ISPOKE-02–25), and 14 External Spokes (ESPOKE-02–15). These should follow the same template — the critical path is now fully specified, and the remaining work is breadth, not depth.

The single highest-leverage action remains unchanged from the previous delivery: **close `CORE-02` (DI Container)**. Everything above is blocked on it. Once it lands, the 10 Core blueprints in this batch become immediately implementable, which unblocks the Hub tier, which unblocks the Bridge and Spokes.

## Prompt:
Analyse against existing ones

## Response:
Based on a rigorous architectural review of the provided `Analysis-2.md` and `Analysis-3.md` documents against the established canonical blueprint map (`01_MASTER_INDEX.md`) and the governance rules established in prior phases, I have identified several critical structural weaknesses, dependency inversions, and functional duplications.

Here is the critical assessment of these documents:

## Finding A — Circular Dependency Between Hub and Deploy Tiers

There is a direct circular dependency between `DEPLOY-01.md` (Analysis-2) and `HUB-15.md` (Analysis-3).

*   `DEPLOY-01.md` states its **Upward** dependencies include `HUB-15` (Health Check contract), meaning Deploy depends on Hub.
*   `HUB-15.md` states its **Downward** dependencies include `DEPLOY-01` (routing-pool eviction), meaning Hub depends on Deploy.

This violates the strict tier-isolation model (Core → Hub → Spoke/Deploy). A Hub service cannot depend on a Deploy blueprint, as Deploy is downstream of Hub. The routing-pool eviction mechanism in `HUB-15` should depend on the orchestrator (e.g., Kubernetes) directly, not invert the dependency back into the Deploy tier.

## Finding B — Inverted Namespace and Dependency Direction for Health Checks

In `DEPLOY-01.md`, the `HealthCheckInterface` is defined under the `SovereignStack\Deploy\Contracts` namespace. The blueprint mandates that every Hub service and Spoke implement this interface.

This is an architectural inversion. Application services (Hub/Core) should never implement interfaces defined by an infrastructure/deployment package, as this couples the application runtime to the deployment layer. The `HealthCheckInterface` (or a more generalized `CheckInterface`) belongs in `SovereignStack\Hub\Contracts` or `SovereignStack\Core\Contracts`, with the Deploy tier consuming it.

## Finding C — Incorrect Runtime Dependency on Build-Time Tool (`CORE-01`)

In `BRIDGE-01.md`, the corrected "Transitive Core Dependencies" list includes:

> `CORE-01`: Polyrepo Orchestrator (Enforcement Logic — release gating on Bridge test suite)

This is a category error. `CORE-01` (Loom) is explicitly a build-time/release-time CLI tool invoked by CI pipelines. It is never loaded by a running Hub or Spoke process. Citing it as a runtime transitive dependency for the Bridge's enforcement logic violates the separation of application runtime and polyrepo automation established in `CORE-01.md`.

## Finding D — Functional Duplication Between `HUB-20` and `CORE-16`

`HUB-20.md` (Cryptography & Secrets Management) states it "Extends `CORE-16` with key rotation, encrypted field storage, and secure handshaking."

However, the `CORE-16.md` blueprint already specifies a complete `KeyManagerInterface` with active key selection, key retirement (rotation), and pluggable backends (Vault, AWS KMS). By claiming to extend `CORE-16` with the very features `CORE-16` already owns, `HUB-20` creates ambiguity over where key management lives. `HUB-20` should be strictly a secrets *storage* service that *utilizes* `CORE-16` for its cryptographic primitives, not a component that extends the crypto engine itself.

## Finding E — Architectural Misplacement of `HUB-16` (Hub Orchestration Hooks)

`HUB-16.md` describes a component that talks to Loom via webhooks, verifies Core compatibility, and performs tagging and manifest generation for Hub-tier distribution.

These are build-time, release-automation concerns, not runtime Hub services. Placing release-automation logic inside the Hub tier violates the polyrepo deployment model. This component's responsibilities belong either in the `orchestrator/` codebase (`CORE-01`) directly, or in the Deploy tier (`DEPLOY-04`). It should not be deployed as a runtime Hub service.

## Finding F — Distributed Transaction & Audit Gap in `BRIDGE-01` Flow

The sequence diagram in `BRIDGE-01.md` dictates the following flow for a validated request:

1.  Bridge calls Internal Spoke (Mutation occurs)
2.  Bridge transforms payload
3.  Bridge logs crossing event to `HUB-06` (Audit)
4.  Bridge responds to External Spoke

The blueprint specifies a "fail-closed" policy: if `HUB-06` is unreachable, the Bridge returns `503`. However, because the audit log write happens *after* the internal service call, an unreachable `HUB-06` will result in a `503` response to the client, but the internal mutation has *already executed*. This creates a severe distributed transaction inconsistency and a lost audit trail for an executed action. The audit event must be written synchronously *before* or *as part of* the internal transaction, not after it.

## Finding G — Unresolved Taxonomy Gap for `ISPOKE-09`

`ESPOKE-01.md` correctly flags that it depends on `ISPOKE-09` (Internal Knowledge Base), but notes that `ISPOKE-09` has no blueprint file and is absent from both the 15 documented and 10 placeholder Internal Spokes.

While the blueprint transparently flags this as a gap, it leaves a critical blocking dependency undefined. Because `ESPOKE-01` strictly consumes content via `ISPOKE-09` through the Bridge, the entire External Spoke phase is blocked until the `ISPOKE` inventory is corrected to include `ISPOKE-09`. This must be reconciled in `01_MASTER_INDEX.md` §4 immediately.

* * *

### Recommended Corrections

1.  **Break the Hub/Deploy cycle:** Move `HealthCheckInterface` to `Hub\Contracts`. Have `HUB-15` define the registry, and `DEPLOY-01` simply wire the orchestrator probes to `HUB-15`'s endpoint.
2.  **Fix `BRIDGE-01` dependencies:** Remove `CORE-01` from its transitive runtime dependencies.
3.  **Fix `BRIDGE-01` audit flow:** Move the `HUB-06` audit write to occur *before* the internal service call (or use a saga/outbox pattern), ensuring no mutation occurs without a guaranteed audit record.
4.  **Refactor `HUB-16`:** Decommission `HUB-16` as a Hub service and migrate its responsibilities into `DEPLOY-04` (Promotion Pipeline) and `CORE-01` (Loom).
5.  **Clarify `HUB-20` scope:** Rewrite `HUB-20` to be a pure secrets *repository* that delegates all encryption and key rotation to `CORE-16`.
6.  **Update Master Index:** Add `ISPOKE-09` to the true total inventory of Internal Spokes.

## Prompt:
Verify for schema documentation inconsistency in every single one so far.

## Response:
A rigorous schema documentation audit across all provided blueprint iterations (`Analysis.md`, `Analysis-2.md`, and `Analysis-3.md`) reveals severe structural inconsistencies. These are not merely stylistic differences; they are fundamentally incompatible interface contracts, database schemas, and payload definitions that will cause compilation failures and architectural collisions if implementation is attempted.

Here is the definitive list of schema documentation inconsistencies across the blueprint set, categorized by severity:

* * *

### 1\. Critical: `HUB-15` Health Check Interface Trifurcation

The contract for health checking is defined three different times with three completely incompatible schemas.

*   **Version A (`Analysis-3.md` - HUB-15):** Defines `CheckInterface` returning a `HealthResult` object, and `HealthRegistryInterface` returning a generic `array` for status.
    
    php
    
    ```php
    interface HealthRegistryInterface {
        public function register(string $name, CheckInterface $check): void;
        public function status(): array;
        public function heartbeat(string $service, string $status): void;
    }
    ```
    
*   **Version B (`Analysis-2.md` - DEPLOY-01):** Defines `HealthCheckInterface` in the `Deploy\Contracts` namespace with strict PSR-7 style return types.
    
    php
    
    ```php
    interface HealthCheckInterface {
        public function liveness(): bool;
        /** @return array{ready: bool, checks: array<string, bool>} */
        public function readiness(): array;
    }
    ```
    
*   **Version C (`Analysis.md` - Kimi's HUB-15):** Defines `HealthCheckInterface` with an enum-based type system and a `HealthCheckResult` DTO.
    
    php
    
    ```php
    interface HealthCheckInterface {
        public function name(): string;
        public function type(): HealthCheckType; // Enum
        public function check(int $timeoutSeconds = 5): HealthCheckResult;
    }
    ```
    

**Inconsistency:** A Hub service cannot implement three different `HealthCheck` interfaces. The schema for how health is reported (Array vs Bool vs DTO) is entirely fractured.

### 2\. Critical: `HUB-06` Audit Log Schema & Interface Divergence

The mechanism for writing audit logs is defined with two completely different API surfaces and payload schemas.

*   **Version A (`Analysis-3.md` - HUB-06):** Uses a procedural action-based schema.
    
    php
    
    ```php
    interface AuditorInterface {
        public function record(string $action, ?string $resourceType = null, ?string $resourceId = null, array $metadata = []): void;
        public function search(array $criteria): array;
    }
    ```
    
    *Payload Schema:* Flat JSON with `user_id: int`, `tenant_id: int`, `signature: sha256`.
*   **Version B (`Analysis.md` - Kimi's HUB-06):** Uses an Event-Driven pattern with cryptographic chaining.
    
    php
    
    ```php
    interface AuditLoggerInterface {
        public function log(AuditableEventInterface $event): void;
        public function verifyChain(?\DateTimeImmutable $since = null): ?AuditEntry;
    }
    ```
    
    *Payload Schema:* Database schema with `seq INT`, `prev_hash CHAR(64)`, `entry_hash CHAR(64)` for tamper-evidence. **Inconsistency:** Version A has no chain integrity verification and relies on a flat `signature`. Version B mandates a linked-list hash chain. The interfaces are incompatible (string action vs Event object).

### 3\. Critical: `HUB-02` Cache Interface API Mismatch

The fundamental API for interacting with the cache is defined backwards from each other.

*   **Version A (`Analysis-3.md` - HUB-02):** Extends PSR-16 directly and uses a fluent tagging interface.
    
    php
    
    ```php
    interface HubCacheInterface extends \Psr\SimpleCache\CacheInterface {
        public function tags(array $tags): self;
        public function flushTags(array $tags): void;
        public function lock(string $name, int $seconds = 0): LockInterface;
    }
    ```
    
*   **Version B (`Analysis.md` - Kimi's HUB-02):** Uses a Factory pattern that returns PSR-16 instances.
    
    php
    
    ```php
    interface CacheManagerInterface {
        public function store(string $namespace = 'default'): Psr16Interface;
        public function flush(string $namespace): void;
        public function invalidateTags(array $tags): void;
    }
    ```
    

**Inconsistency:** Version A calls `$cache->tags(['x'])->set(...)`. Version B calls `$manager->store('x')->set(...)`. The tag isolation logic operates on completely different structural assumptions.

### 4\. Major: `HUB-10` (Queue) Job Interface Contract Mismatch

The schema for what a "Job" actually is contradicts the documented code examples.

*   **Interface Definition (`Analysis-3.md` - HUB-10):**
    
    php
    
    ```php
    interface QueueInterface {
        public function push(object $job, string $queue = 'default'): void;
        public function later(int $delay, object $job, string $queue = 'default'): void;
    }
    ```
    
    *(Note: The interface accepts a generic `object`, but does not require it to implement an interface).*
*   **Code Example (`Analysis-3.md` - HUB-10):**
    
    php
    
    ```php
    class SendWelcomeEmail implements JobInterface {
        public function handle(NotificationService $notifications): void { ... }
    }
    ```
    
*   **Version B (`Analysis.md` - Kimi's HUB-11):** Defines a strict `JobInterface` with Ack/Fail semantics.
    
    php
    
    ```php
    public function push(JobInterface $job, ?string $queue = null, ?int $delaySeconds = null): string;
    public function pop(?string $queue = null, int $timeoutSeconds = 0): ?JobInterface;
    public function ack(JobInterface $job): void;
    public function fail(JobInterface $job, \Throwable $error): void;
    ```
    

**Inconsistency:** The `Analysis-3` version has no `ack` or `fail` methods in its `QueueInterface`, meaning jobs are fire-and-forget once popped, which contradicts the robust DLQ/retry documentation. Kimi's version requires explicit ack/fail.

### 5\. Major: `HUB-20` (Vault) vs `CORE-16` (Encryption) Return Types

*   **`CORE-16` (`Analysis.md`):** `encrypt(string $plaintext, string $aad = ''): string` — returns a Binary Envelope (Version byte + Algorithm ID + Key ID + Nonce + Tag + Ciphertext).
*   **`HUB-20` (`Analysis-3.md`):** `encrypt(string $value, ?string $context = null): string` — claims to extend `CORE-16` but provides no schema for the returned string. If it uses `CORE-16`, it must return the binary envelope, but the interface signature implies a generic string.

### 6\. Major: `CORE-04` / `HUB-08` Routing & Dispatch Overlap

*   **`CORE-06` (`Analysis.md`):** The Router implements `RequestHandlerInterface` and handles `ServerRequestInterface` to return `ResponseInterface`.
*   **`HUB-08` (`Analysis-3.md`):** The Gateway defines `proxy(ServerRequestInterface $request): ResponseInterface` AND `dispatchInternal(string $service, string $action, array $params = []): mixed`. **Inconsistency:** The schema for internal communication is broken. `dispatchInternal` returns `mixed`, bypassing the PSR-7 response schema entirely. If Hub services communicate via `dispatchInternal`, they do not use PSR-7, rendering `CORE-04` and `CORE-05` useless for internal traffic.

### 7\. Minor: Missing Schema Definitions for Referenced DTOs

Multiple blueprints define interfaces that return or accept classes whose schema is never documented:

*   **`HUB-14` (Search):** `search(string $index, string $query): SearchBuilder` — `SearchBuilder` is never defined.
*   **`ISPOKE-01` (Admin):** `fields(): array<string, FieldDefinition>` — `FieldDefinition` is never defined.
*   **`ESPOKE-01` (CMS):** `validate(ContentMetadata $metadata): array` — `ContentMetadata` is never defined.
*   **`HUB-12` (Notify):** `send(mixed $notifiables, object $notification): void` — The schema for the `Notification` object is only shown in a code example, not an interface.

### 8\. Minor: Database Type Inconsistencies

*   In `HUB-04` (`Analysis-3.md`), the JSON payload schema lists `"user_id": "int"` and `"tenant_id": "int"`.
*   In `HUB-01` (`Analysis.md`), the SQL schema dictates `tenant_id CHAR(26)` (ULID).
*   In `HUB-04` (`Analysis.md`), the PHP Entity dictates `public readonly string $id, // ULID`. **Inconsistency:** `HUB-06` (`Analysis-3.md`) documents user/tenant IDs as integers in its JSON schema, while the canonical Core/Hub design dictates they are ULID strings.

* * *

### Resolution Mandate

To prevent compilation failure, the following schema reconciliations must be applied to the Master Blueprints:

1.  **Adopt the Event-Driven Audit Schema:** `HUB-06` must use `AuditableEventInterface` and the cryptographic chain schema. The `record()` method must be deprecated.
2.  **Adopt the PSR-16 Extension for Cache:** `HUB-02` must use `HubCacheInterface extends \Psr\SimpleCache\CacheInterface`. The Factory pattern is redundant.
3.  **Unify Health Checks:** `HealthCheckInterface` must live in `Hub\Contracts`, use the Enum/DTO return type, and `DEPLOY-01` must consume it—no interfaces may live in the Deploy namespace.
4.  **Strict JobInterface:** `HUB-10` must typehint `JobInterface` in `push()` and `pop()`, and must include `ack()` and `fail()` methods.
5.  **Ban `mixed` Returns:** `HUB-08`'s `dispatchInternal` must return a defined DTO or `ResponseInterface`, never `mixed`.

## Prompt:
Provide fixes

## Response:
Here are the definitive architectural fixes for the 8 identified schema inconsistencies. These corrected contracts must replace all previous versions in the blueprint documents to ensure system-wide compilation and structural integrity.

### Fix 1: Unify `HUB-15` Health Check Schema (Resolves Trifurcation)

The `HealthCheckInterface` is consolidated into the `Hub\Contracts` namespace. The Deploy tier consumes this interface; it does not define it.

php

```php
namespace SovereignStack\Hub\Contracts;

enum HealthCheckType: string {
    case Liveness = 'liveness';
    case Readiness = 'readiness';
    case Both = 'both';
}

final class HealthCheckResult {
    public function __construct(
        public readonly string $name,
        public readonly string $status, // 'healthy' | 'unhealthy' | 'degraded'
        public readonly ?string $message = null,
        public readonly ?float $latencyMs = null,
        public readonly array $metadata = [],
    ) {}
}

interface HealthCheckInterface {
    public function name(): string;
    public function type(): HealthCheckType;
    public function check(int $timeoutSeconds = 5): HealthCheckResult;
}

interface HealthRegistryInterface {
    public function register(HealthCheckInterface $check): void;
    /** @return array{ready: bool, checks: array<string, HealthCheckResult>} */
    public function status(): array;
    public function heartbeat(string $service, HealthCheckResult $result): void;
}
```

### Fix 2: Unify `HUB-06` Audit Log Schema (Resolves Interface & Schema Divergence)

The procedural `record()` method is deprecated. The system mandates the Event-Driven pattern with cryptographic hash-chaining.

php

```php
namespace SovereignStack\Hub\Contracts;

interface AuditableEventInterface {
    public function auditAction(): string;
    public function auditActor(): string;       // ULID
    public function auditTenantId(): ?string;   // ULID
    public function auditChanges(): ?array;     // ['before' => [], 'after' => []]
    public function auditContext(): array;
}

interface AuditLoggerInterface {
    /**
     * Appends an audit entry. Must never throw - audit failures must be logged to CORE-09.
     */
    public function log(AuditableEventInterface $event): void;
    
    /**
     * Verifies the cryptographic chain integrity.
     * Returns the first broken entry, or null if intact.
     */
    public function verifyChain(?\DateTimeImmutable $since = null): ?array;
}
```

**Corrected Database Schema for `audit_log`:**

sql

```sql
CREATE TABLE audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    seq INT NOT NULL,              -- Monotonic sequence per tenant
    tenant_id CHAR(26) NULL,       -- ULID; null for system-level actions
    action VARCHAR(100) NOT NULL,
    actor_id CHAR(26) NOT NULL,    -- ULID or 'system'
    changes JSON NULL,             -- {before: {...}, after: {...}}
    context JSON NOT NULL,         -- {ip, user_agent, request_id, ...}
    prev_hash CHAR(64) NOT NULL,   -- SHA-256 of previous entry in chain
    entry_hash CHAR(64) NOT NULL,  -- SHA-256 of (seq || tenant_id || action || actor_id || changes || context || prev_hash)
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uk_tenant_seq (tenant_id, seq),
    KEY idx_actor (actor_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB;
```

### Fix 3: Unify `HUB-02` Cache Interface (Resolves API Mismatch)

Adopts the fluent PSR-16 extension, eliminating the redundant Factory pattern.

php

```php
namespace SovereignStack\Hub\Contracts;

use Psr\SimpleCache\CacheInterface as Psr16Interface;

interface LockInterface {
    public function acquire(string $name, int $seconds = 0): bool;
    public function release(string $name): void;
    public function block(string $name, int $seconds, callable $callback): mixed;
}

interface HubCacheInterface extends Psr16Interface {
    /**
     * Returns a cloned instance scoped to the provided tags.
     * All subsequent set() calls will attach these tags.
     */
    public function tags(array $tags): self;
    
    public function flushTags(array $tags): void;
    public function lock(string $name, int $seconds = 0): LockInterface;
}
```

### Fix 4: Strict `HUB-10` Queue Interfaces (Resolves Job Mismatch)

Mandates explicit `JobInterface` typehinting and Ack/Fail semantics for robust DLQ handling.

php

```php
namespace SovereignStack\Hub\Contracts;

interface JobInterface extends \JsonSerializable {
    public function id(): string;
    public function queue(): string;
    public function attempts(): int;
    public function maxAttempts(): int;
    public function handle(\Psr\Container\ContainerInterface $container): void;
    public function backoff(int $attempts): int;
}

interface QueueInterface {
    public function push(JobInterface $job, ?string $queue = null, ?int $delaySeconds = null): string;
    public function pop(?string $queue = null, int $timeoutSeconds = 0): ?JobInterface;
    
    /** Mark a job as completed. */
    public function ack(JobInterface $job): void;
    
    /** Mark a job as failed; applies retry/backoff logic or moves to DLQ. */
    public function fail(JobInterface $job, \Throwable $error): void;
    
    public function size(?string $queue = null): int;
}
```

### Fix 5: Clarify `HUB-20` Vault Encryption (Resolves CORE-16 Overlap)

Removes the ambiguity around returned strings. The Vault explicitly returns `CORE-16`'s binary envelope.

php

```php
namespace SovereignStack\Hub\Contracts;

interface VaultInterface {
    /**
     * Encrypts plaintext using CORE-16's EncryptionInterface.
     * 
     * @param string $value Plaintext to encrypt.
     * @param string $context Additional authenticated data (AAD) e.g., tenant ULID.
     * @return string Binary envelope (Version + Alg ID + Key ID + Nonce + Tag + Ciphertext).
     */
    public function encrypt(string $value, string $context = ''): string;
    
    /**
     * Decrypts a CORE-16 binary envelope.
     */
    public function decrypt(string $payload, string $context = ''): string;
    
    public function getSecret(string $key): ?string;
}
```

### Fix 6: Fix `HUB-08` Gateway Dispatch (Resolves `mixed` Return & PSR-7 Bypass)

Internal communication must respect the HTTP message layer (`CORE-04`) or return a strictly defined DTO, never `mixed`.

php

```php
namespace SovereignStack\Hub\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface GatewayInterface {
    /**
     * Proxies a standard PSR-7 request to an internal service.
     */
    public function proxy(ServerRequestInterface $request): ResponseInterface;
    
    /**
     * Dispatches an internal action and wraps the result in a standardized DTO.
     */
    public function dispatchInternal(string $service, string $action, array $params = []): DispatchResult;
}

final class DispatchResult {
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly int $statusCode = 200,
    ) {}
}
```

### Fix 7: Define Missing DTO Schemas

The following interfaces and classes must be added to the blueprint set to resolve undefined typehints.

php

```php
// HUB-14 (Search)
namespace SovereignStack\Hub\Contracts;
interface SearchBuilderInterface {
    public function where(string $field, mixed $value): self;
    public function orderBy(string $field, string $direction = 'asc'): self;
    public function limit(int $limit): self;
    /** @return array<int, array> */
    public function get(): array;
}

// ISPOKE-01 (Admin)
namespace SovereignStack\Internal\CommandCenter\Contracts;
enum FieldType: string {
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case DateTime = 'datetime';
    case Select = 'select';
}
final class FieldDefinition {
    public function __construct(
        public readonly string $label,
        public readonly FieldType $type,
        public readonly bool $required = false,
        public readonly array $options = [],
    ) {}
}

// ESPOKE-01 (CMS)
namespace SovereignStack\External\Canvas\Contracts;
final class ContentMetadata {
    public function __construct(
        public readonly string $title,
        public readonly ?string $metaDescription = null,
        public readonly ?string $canonicalUrl = null,
        public readonly ?array $structuredData = null,
    ) {}
}

// HUB-12 (Notify)
namespace SovereignStack\Hub\Contracts;
interface NotificationInterface {
    /** @return array<int, string> e.g., ['mail', 'database'] */
    public function via(mixed $notifiable): array;
    public function toMail(mixed $notifiable): object;
}
```

### Fix 8: Standardize ID Types in JSON Schemas

All payload examples and JSON schemas must be updated to reflect ULID strings (26 chars) instead of integers.

**Corrected `HUB-06` JSON Payload Example:**

json

```json
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "user_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "action": "document.update",
  "resource_type": "Document",
  "resource_id": "123",
  "changes": {"title": ["Old", "New"]},
  "ip_address": "192.168.1.1",
  "user_agent": "Mozilla/5.0...",
  "timestamp": "2026-08-02T21:01:33Z",
  "prev_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "entry_hash": "b94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9"
}
```

