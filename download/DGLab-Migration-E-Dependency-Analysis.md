# DGLab Old-to-New Architecture Migration Map
## Deliverable E — Dependency and Ownership Analysis

**Baseline:** `origin/main @ 4ba7e7a` · All edges below are **verified from `use` statements in source** unless marked otherwise.

---

## E-1. Verified component dependency graph (runtime code)

```text
                    ┌──────────────────────── LAYER GRAPH (consumes →) ───────────────────────┐
                    │                                                                          │
  CORE (leaves)     │  config(C10)   container(C02)   crypto(C16)   dbal(C19)   filesystem(C14)│
                    │  neospx(C07)*  event-disp(C03)   view(C-view)→config                       │
                    │                                                                          │
  CORE (mid)        │  logger(C09)→(psr/log only)     error-handler(C08)→(psr/log only)         │
                    │  router(C06)→(psr-7 only)       middleware(C05)→router + http(C04)       │
                    │                                                                          │
  CORE (sink)       │  kernel(C18)→ config,container,event-disp,http,middleware,router,        │
                    │                logger,error-handler  [8 verified edges; sink of the tier]│
                    │                                                                          │
  HUB               │  hub-config(H01)→config(C10)                                             │
                    │  hub-identity(H04)→crypto(C16), dbal(C19), kernel.RequestContext(C18)   │
                    │                                                                          │
  BRIDGE            │  vanguard(B01)→http.Stream(C04)                                          │
                    │                                                                          │
  SPOKES            │  lms→dbal(C19), hub-identity(H04)                                        │
                    │  showcase→dbal(C19), hub-identity(H04)                                   │
                    │  codex→(none)        canvas→http(C04)                                    │
                    │                                                                          │
  PRODUCT           │  app-skeleton→C02,C03,C04,C08,C09,C10,C18,router,view + BRIDGE-01       │
                    │  (ApplicationFactory is the composition root; imports 11 components)    │
                    └──────────────────────────────────────────────────────────────────────────┘
  * neospx(C07) and filesystem(C14) have ZERO dependents — orphaned-but-present.
```

**Edge list (16 verified inter-component edges):**

| # | Edge (consumer → provider) | Evidence |
|---|---|---|
| 1 | kernel → config | `Kernel.php` imports ConfigInterface |
| 2 | kernel → container | Kernel imports ContainerInterface; Stub registry |
| 3 | kernel → event-dispatcher | 4 lifecycle Events extend `Event` |
| 4 | kernel → http-message | HttpBootstrapper imports pipeline types (→middleware via same NS today) |
| 5 | kernel → middleware | HttpBootstrapper/Kernel import MiddlewarePipelineInterface |
| 6 | kernel → router | Kernel/HttpBootstrapper import RouterInterface |
| 7 | kernel → logger | `LoggerInterface as DgLoggerInterface` |
| 8 | kernel → error-handler | ErrorHandlerInterface |
| 9 | middleware → router | FinalRequestHandler imports RouterInterface (**the only cross-package src import in middleware**) |
| 10 | middleware → http-message | FinalRequestHandler 404 Response |
| 11 | view → config | ViewFactory::fromConfig |
| 12 | hub-config → config | HubConfigRegistry |
| 13 | hub-identity → crypto, dbal, kernel | PasswordHasher / ConnectionInterface / RequestContext |
| 14 | vanguard → http-message | Stream (DTO transform) |
| 15 | lms, showcase → dbal, hub-identity | repos / VOs+IdentityInterface (11 + 3 files) |
| 16 | canvas → http-message | Stream + Response |

**Composer-declared but code-unused edges (cleanup list):** logger→config; error-handler→logger (tests only); router→http-message (tests only); vanguard→middleware+router; hub-config→container; lms/showcase→container+event-dispatcher+http-message+kernel+filesystem. These six groups are removed at Stage 9 (they exist only to simulate package boundaries).

## E-2. Reconciliation with the declared/verified DAGs (Phase F revalidation)

| Prior finding (A3-ARCH-DAG-REPORT / audits) | Revalidated at 4ba7e7a | Status |
|---|---|---|
| Router→Kernel "reversal" (legacy §5.2 showed C18→C06) | Correct direction IS C06→C18 (kernel imports router) — confirmed in source; both DAG docs' own Mermaid still render the old wrong edge (S-058) | **Still open (doc defect)** — code is correct |
| C18→C06 misclassified DECLARED_ONLY (S-057, HIGH) | Confirmed: kernel composer.json requires core-router + src imports it → should be VERIFIED; four-status math in CORE-DECLARED-DAG doesn't reconcile | **Still open** |
| C04→C18 undeclared-but-used | Confirmed | Still open |
| C04/C05 shared namespace | Confirmed (root autoload maps only http-message; middleware resolves via path-repo vendor) | **Resolved by migration** (OD-01 split, Stage 2) |
| HUB-31/HUB-32 INDEX omission; ESPOKE count 18 vs 19 | Confirmed (32 Hub files, 19 ESPOKE files on disk; INDEX says 31/29/18) | Still open → inventory generation fixes institutional counts (D-H9) |
| CI matrix missing neospx+view | Confirmed (20-entry matrix; both packages eligible; exclusions file empty) | Still open → Stage 0 fix |
| `branches: ain]` corruption ×6 workflows | Confirmed at baseline | Still open → Stage 0 fix |
| Dangling `scripts/architecture-boundary-lint.php` refs | Confirmed (workflow paths filter + comment; file absent) | Still open → Stage 0/9 fix |
| `.phpunit.cache/` tracked; `orchestrator/repos.json` untracked assumption | Confirmed | Still open → Stage 10 |
| Hub DAG "29 active" excludes HUB-32 (file now exists) | Confirmed | Still open (doc) |

**No prohibited dependency cycles exist in code** (verified: the graph is a clean DAG — Core sink = Kernel; Hub → Core only; Bridge → Core only; Spokes → Core+Hub; app → everything below). The ONLY cycle risk is *governance-declared* edges (HUB-DECLARED-DAG's 150 edges include Hub↔Core bidirectional pairs flagged in S-027..S-032 remediations) — not code.

## E-3. Ownership boundaries that are unclear (with evidence)

| Boundary | Ambiguity | Evidence | Recommendation |
|---|---|---|---|
| RequestContext | Lives in kernel (C18) but is consumed by hub-identity (HTTP auth context) and fitness script FF-01 | `packages/hub/identity/src/Http/AuthMiddleware.php` imports it | Keep in Core/Kernel (identity→kernel edge already declared); do NOT move during migration |
| View vs Products | ViewFactory defaults `view.path` to `getcwd()/app/Resources/views` — app-coupled default inside a Core component | `packages/core/view/src/ViewFactory.php` | Remove the cwd default at Stage 2 (behavior-preserving for configured use; the app always passes explicit path) — flagged as approved-scope micro-change, else keep |
| Filesystem (C14) | Orphan — zero code dependents; only lint allowlist references it | allowlist 7 FQCNs | Keep (blueprint-backed); hunt consumers post-migration (LMS asset mgmt intended) |
| Identity SQL table naming | `users/roles/user_roles` unprefixed vs spokes' `lms_*`/`showcase_*` | migrations 001-003 | Record in inventory; do not rename tables in the migration (schema change = separate approval) |
| Middleware's FinalRequestHandler | Dispatches controllers via container — application-composition concern living in Core | FinalRequestHandler imports router | Retain (frozen contract); note for future ADR |
| Skeleton health routes | `/health/{live,ready,dependencies}` registered in Vanguard contracts but NOT routed — unreachable | ApplicationFactory contracts vs route table | Stage 6: route them or drop contracts (owner call, D-4) |

## E-4. Migration ordering implications (derived from the verified graph)

1. **Core must move first** (everything depends on it); within Core, the dependency waves are: leaves (config, container, crypto, dbal, filesystem, neospx, event-dispatcher, view) → logger/error-handler → router/middleware (with the Http split) → kernel LAST.
2. **Hub before spokes** (lms/showcase import hub-identity namespaces).
3. **Bridge before Products** (ApplicationFactory imports Vanguard).
4. **Spokes before Products** (future LMS composition; today only autoload references, but stage order keeps it safe).
5. **Products/LMS (app+public) after everything it imports exists at final paths** — and in the same stage as anvil reference updates (document-root contract).
6. **Stage 9 cutover last-but-one**: autoload+CI+Loom+lint re-pointing must be atomic across all tiers because half-migrated PSR-4 roots are the highest-risk state.
7. Orchestrator/anvil/Architecture reference updates ride with the stage that moves the thing they reference (Stage 6 anvil; Stage 9 Loom/CI/lint).

*Deliverable E complete.*
