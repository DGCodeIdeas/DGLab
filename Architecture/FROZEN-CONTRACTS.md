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
