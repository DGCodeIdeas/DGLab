# Frozen Contracts Registry

> Canonical list of all interfaces and enums that are **frozen per SDLC-AGRD §2.1**.
>
> An interface is frozen the first time it is implemented at any depth. After
> freezing, the interface signature (method names, parameter types, return types,
> constant values) **must not change** — additions are allowed, but removals,
> renames, and type changes are breaking changes that require a new interface
> (e.g. `RouterInterface2`) and a deprecation migration.
>
> This registry is the diff-target for future audits. The Architecture Lint CI
> should be extended to verify interface signatures against this file.
>
> **Last audited**: 2026-09-17 (Task 41 — mini-cooldown interface-freeze audit)

## CORE-02 — DI Container (`SovereignStack\Core\Container`)

| FQCN | File | Status |
|---|---|---|
| `ContainerInterface` | `src/ContainerInterface.php` | Frozen (extends PSR-11) |
| `ContainerBuilderInterface` | `src/ContainerBuilderInterface.php` | Frozen |
| `CompilerPassInterface` | `src/CompilerPassInterface.php` | Frozen |

## CORE-03 — Event Dispatcher (`SovereignStack\Core\EventDispatcher`)

| FQCN | File | Status |
|---|---|---|
| `EventDispatcherInterface` | `src/EventDispatcherInterface.php` | Frozen (extends PSR-14) |
| `ListenerProviderInterface` | `src/ListenerProviderInterface.php` | Frozen (extends PSR-14) |

## CORE-04 — HTTP Message (`SovereignStack\Core\Http`)

| FQCN | File | Status |
|---|---|---|
| `MessageFactoryInterface` | `src/MessageFactoryInterface.php` | Frozen (extends all 6 PSR-17 factories) |

## CORE-05 — Middleware (`SovereignStack\Core\Http`)

| FQCN | File | Status |
|---|---|---|
| `MiddlewarePipelineInterface` | `src/MiddlewarePipelineInterface.php` | Frozen (extends PSR-15) |
| `FinalRequestHandlerInterface` | `src/FinalRequestHandlerInterface.php` | Frozen (extends PSR-15) |
| `MiddlewareResolverInterface` | `src/MiddlewareResolverInterface.php` | Frozen |

## CORE-06 — Router (`SovereignStack\Core\Router`)

| FQCN | File | Status |
|---|---|---|
| `RouterInterface` | `src/RouterInterface.php` | Frozen |

## CORE-08 — Error Handler (`SovereignStack\Core\ErrorHandler`)

| FQCN | File | Status |
|---|---|---|
| `ErrorHandlerInterface` | `src/ErrorHandlerInterface.php` | Frozen (docblock marker) |
| `RendererInterface` | `src/RendererInterface.php` | Frozen (docblock marker) |

## CORE-09 — Logger (`SovereignStack\Core\Logger`)

| FQCN | File | Status |
|---|---|---|
| `LoggerInterface` | `src/LoggerInterface.php` | Frozen (extends PSR-3; docblock marker) |
| `FormatterInterface` | `src/FormatterInterface.php` | Frozen |
| `HandlerInterface` | `src/HandlerInterface.php` | Frozen |

## CORE-10 — Config (`SovereignStack\Core\Config`)

| FQCN | File | Status |
|---|---|---|
| `ConfigInterface` | `src/ConfigInterface.php` | Frozen (has `SECRET_PATTERN` constant) |
| `EnvLoaderInterface` | `src/EnvLoaderInterface.php` | Frozen |
| `ConfigBuilderInterface` | `src/ConfigBuilderInterface.php` | Frozen |

## CORE-18 — Kernel (`SovereignStack\Core\Kernel`)

| FQCN | File | Status |
|---|---|---|
| `KernelInterface` | `src/KernelInterface.php` | Frozen (docblock marker) |
| `BootstrapperInterface` | `src/BootstrapperInterface.php` | Frozen (docblock marker) |
| `enum KernelState: string` | `src/KernelState.php` | Frozen (6 cases: Unbooted, Booting, Booted, Handling, Terminating, Terminated) |
| `ProviderRegistryInterface` | `src/Stub/ProviderRegistryInterface.php` | **NOT frozen** — temporary stub for CORE-17. Deleted when CORE-17 ships. |

## HUB-01 — Hub Config (`SovereignStack\Hub\Config`)

| FQCN | File | Status |
|---|---|---|
| `GlobalConfigInterface` | `src/GlobalConfigInterface.php` | Frozen (docblock marker) |
| `FeatureManagerInterface` | `src/FeatureManagerInterface.php` | Frozen (docblock marker) |
| `ConfigOverrideRepositoryInterface` | `src/ConfigOverrideRepositoryInterface.php` | Frozen (depth-2 stub; replaced when CORE-19 DBAL lands) |
| `FeatureFlagRepositoryInterface` | `src/FeatureFlagRepositoryInterface.php` | Frozen (depth-2 stub; same) |
| `enum Environment: string` | `src/Environment.php` | Frozen (4 cases: Development, Staging, Production, Testing). Placeholder — when CORE-10 ships its own `Environment` enum, swap via use-clause. |

## BRIDGE-01 — Vanguard (`SovereignStack\Bridge`)

| FQCN | File | Status |
|---|---|---|
| `BoundaryContractInterface` | `src/BoundaryContractInterface.php` | Frozen (extends PSR-15; docblock marker) |
| `DtoTransformerInterface` | `src/DtoTransformerInterface.php` | Frozen (docblock marker) |

## ISPOKE-09 — Codex (`SovereignStack\Internal\Codex`)

| FQCN | File | Status |
|---|---|---|
| `KnowledgeBaseInterface` | `src/KnowledgeBaseInterface.php` | Frozen (docblock marker) |

## ESPOKE-01 — Canvas (`SovereignStack\External\Canvas`)

| FQCN | File | Status |
|---|---|---|
| `ContentDeliveryInterface` | `src/ContentDeliveryInterface.php` | Frozen (docblock marker) |
| `SeoValidationInterface` | `src/SeoValidationInterface.php` | Frozen (docblock marker) |

---

## Summary

| Package | Interfaces | Enums | Total | Frozen | Not frozen |
|---|---|---|---|---|---|
| CORE-02 (Container) | 3 | 0 | 3 | 3 | 0 |
| CORE-03 (EventDispatcher) | 2 | 0 | 2 | 2 | 0 |
| CORE-04 (HttpMessage) | 1 | 0 | 1 | 1 | 0 |
| CORE-05 (Middleware) | 3 | 0 | 3 | 3 | 0 |
| CORE-06 (Router) | 1 | 0 | 1 | 1 | 0 |
| CORE-08 (ErrorHandler) | 2 | 0 | 2 | 2 | 0 |
| CORE-09 (Logger) | 3 | 0 | 3 | 3 | 0 |
| CORE-10 (Config) | 3 | 0 | 3 | 3 | 0 |
| CORE-18 (Kernel) | 3 | 1 | 4 | 3 | 1 (Stub) |
| HUB-01 (HubConfig) | 4 | 1 | 5 | 5 | 0 |
| BRIDGE-01 (Vanguard) | 2 | 0 | 2 | 2 | 0 |
| ISPOKE-09 (Codex) | 1 | 0 | 1 | 1 | 0 |
| ESPOKE-01 (Canvas) | 2 | 0 | 2 | 2 | 0 |
| **Total** | **30** | **2** | **32** | **31** | **1** |

## Known deferred contracts

1. **`ProviderRegistryInterface`** (`SovereignStack\Core\Kernel\Stub`) — explicitly NOT frozen. Temporary placeholder for CORE-17 (Service Providers). Deleted when CORE-17 ships.

2. **`Environment` enum** (`SovereignStack\Hub\Config`) — frozen, but marked as a placeholder. When CORE-10 ships its own `Environment` enum, HUB-01 swaps via use-clause. The string values (`Development`, `Staging`, `Production`, `Testing`) are stable across both.

3. **`ConfigOverrideRepositoryInterface` + `FeatureFlagRepositoryInterface`** (HUB-01) — frozen at depth 2 with in-memory stubs. When CORE-19 (DBAL) ships, the in-memory stubs are replaced with database-backed implementations. The interface signatures are stable.

---

## Core tier contracts under Nuclear-Grade Engineering Doctrine

> Every interface landed under the Core tier is **frozen** the moment it is implemented at any depth, per SDLC-AGRD §2.1, AND is **operationally bound** by [`CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](./CrossCutting/NUCLEAR-GRADE-DOCTRINE.md). Freezing governs the **signature** (method names, parameter types, return types, constant values). The doctrine governs the **operational envelope** (failure shape, resource ceilings, breaker thresholds, audit, panic, chaos tests, merge gate). A signature-frozen interface can still fail doctrine §9 merge gate — in which case the implementation is **not eligible for promotion to `stable`** even though the interface is technically frozen.

### Doctrine-imposed contract constraints (binding on every Core-tier package)

The following constraints are binding on every frozen Core-tier contract and MUST be respected by any future amendment:

- **Exception classes** thrown across the public surface MUST name their error taxonomy class (Transient / Permanent-External / Permanent-Local / Corrupt / Panic) in the docblock `@throws` tag.
- **Resource-limit exceptions** (`ResourceLimitExceeded`, `QueryTimeoutExceeded`, `ConnectionLeakDetected`, `CacheTtlTooShort`, `StreamByteLimitExceeded`, `NonceCounterUnavailable`, `WeakHashParametersRefused`, `PathTraversalRefused`, `QuarantineNotReleased`, `BootstrapperTimeoutExceeded`, `BootstrapperCountExceeded`, `RequestTimeoutExceeded`, `TerminateTimeoutExceeded`) are part of the frozen contract surface — they cannot be renamed or have their constructor signature changed without a major SemVer bump.
- **Token interfaces** that gate elevated privileges (`SystemContext` for tenant-scope bypass, `PermanentCacheAllowed` for TTL=0 cache writes, `QuarantineRelease` for untrusted-file reads) are part of the frozen contract surface; their acquisition paths are enforced by static analysis.
- **Panic class** (`PanicException`, extends `\RuntimeException`, class Panic per doctrine §2) is part of the frozen contract surface for every Core-tier package — additions are SemVer-minor; removing a PanicException throw-point or downgrading it to a different class is SemVer-major.
- **Audit record schema** (`AuditRecord` with `seq`, `tenant_id`, `request_id`, `fiber_id`, `actor_id`, `operation`, `target`, `before_hash`, `after_hash`, `prev_hash`, `entry_hash`, `created_at`) is part of the frozen contract surface — downstream consumers (HUB-06, ISPOKE-17) depend on it.
- **Circuit breaker** public methods (`isOpen()`, `trip()`, `cooldown()`, `probe()`) are part of the frozen contract surface; their state machine (CLOSED → OPEN → HALF_OPEN → CLOSED) cannot change.

### Per-package registration pending

The actual interface and enum FQCN/file tables for CORE-19, CORE-15, CORE-14, CORE-16, and CORE-18 will be appended to this registry at the same PR that lands each package's depth-2 implementation under the doctrine. Until then, the per-package blueprints (`Architecture/Core/CORE-{14,15,16,18,19}.md`) are the source of truth for the planned interface surface; the doctrine file (`CrossCutting/NUCLEAR-GRADE-DOCTRINE.md` §4.1–§4.5) is the source of truth for the operational envelope.

#### Frozen contracts landed for CORE-18 (per doctrine §4.5 implementation):

| FQCN | File | Frozen via | Status |
|---|---|---|---|
| `PanicException` (extends `\RuntimeException`) | `packages/core/kernel/src/PanicException.php` | PR #251 (2026-09-23) | ✅ Frozen — 5 named constructors: `forInvariantViolation`, `forNullFactoryResult`, `forUnexpectedNullProperty`, `forStateRecoveryGap`, `forNullPipelineInHandlingState` |
| `KernelException::bootstrapperTimeoutExceeded()` | `packages/core/kernel/src/KernelException.php` | PR #249 (2026-09-23) | ✅ Frozen — named constructor for the per-bootstrapper wall-clock budget throw-point |
| `Kernel::BOOTSTRAPPER_TIMEOUT_SECONDS` (constant = 5.0) | `packages/core/kernel/src/Kernel.php` | PR #249 (2026-09-23) | ✅ Frozen — hard ceiling for per-bootstrapper wall-clock budget |
| `Kernel::$bootstrapperTimeoutSeconds` (protected property) | `packages/core/kernel/src/Kernel.php` | PR #249 (2026-09-23) | ✅ Frozen — instance-level override for tests; production code MUST NOT modify |

### CORE-18-specific doctrine constraints (added 2026-09-23)

Per doctrine §4.5, CORE-18 (Kernel) carries these additional doctrine-imposed constraints on top of the Core-tier-wide constraints above:

- The 6-case `KernelState` enum string values (`Unbooted`, `Booting`, `Booted`, `Handling`, `Terminating`, `Terminated`) are part of the frozen audit-log schema — they cannot be renamed.
- The 9 `KernelException` named constructors (`bootAfterTerminate`, `handleBeforeBoot`, `handleAfterTerminate`, `terminateBeforeBoot`, `doubleTerminate`, `handleDuringHandling`, `bootDuringBoot`, `handleDuringBoot`, `terminateDuringBoot`, `terminateDuringHandling`) are part of the frozen contract surface — they cannot be renamed or removed; additions are SemVer-minor.
- The `PanicException` class (`SovereignStack\Core\Kernel\PanicException`, extends `\RuntimeException`, class Panic per doctrine §2) is **frozen per PR #251 (2026-09-23)**. The four invariant-violation throw-points it covers (`releaseReferences()` failure, null factory result, `assertBooted()` passing but `$pipeline` null, `handle()` finally cannot restore state) MUST remain PanicException throws — downgrading any of them to KernelException is SemVer-major.
- The `BootstrapperTimeoutExceeded` named constructor on `KernelException` (added per doctrine §4.5.3) is **frozen per PR #249 (2026-09-23)**. The `Kernel::BOOTSTRAPPER_TIMEOUT_SECONDS = 5.0` constant is part of the frozen contract — worker supervisors + deployment scripts depend on the documented 5s ceiling for their own process-level watchdogs (e.g., systemd `TimeoutStartSec=30s` for the aggregate boot budget). Changing the constant is SemVer-major.
- The `Kernel::$bootstrapperTimeoutSeconds` protected instance property is **frozen per PR #249**. Tests override via reflection; production code MUST NOT modify this property.
- `boot()` on an already-Booted Kernel is idempotent (returns immediately without re-running bootstrappers) — this is part of the frozen contract and cannot change.
- `KernelLifecycleRecord` audit fields (`bootStarted`, `bootCompleted`, `bootFailed`, `handleStarted`, `handleCompleted`, `handleFailed`, `terminateStarted`, `terminateCompleted`) are part of the frozen contract surface — downstream consumers (HUB-06, ISPOKE-17) depend on them.
