# DGLab Old-to-New Architecture Migration Map
## Deliverable C — Component-by-Component Migration Map

**Baseline:** `origin/main @ 4ba7e7a` · One row per component · Split/merge detail at class level.
**Legend:** Action = Move / Rename / Split / Merge / Retain / Remove / Defer. Conf = Verified (V) / Partially verified (P) / Unresolved (U).
**Global namespace rule:** every `SovereignStack\` prefix becomes `DGLab\` with the tier-exact mapping below; all callers, tests, allowlists, and lint scripts change in the same stage (Stage 9 cutover, see Deliverable G).

---

## C-1. Core tier (packages/core/* → Core/*)

| Field | C-10 Config | C-02 Container | C-16 Crypto | C-19 DBAL |
|---|---|---|---|---|
| Existing ID / path | CORE-10 · `packages/core/config` | CORE-02 · `packages/core/container` | CORE-16 · `packages/core/crypto` | CORE-19 · `packages/core/dbal` |
| Namespace now → target | `SovereignStack\Core\Config` → **`DGLab\Core\Config`** | `SovereignStack\Core\Container` → **`DGLab\Core\Container`** | `SovereignStack\Core\Crypto` → **`DGLab\Core\Crypto`** | `SovereignStack\Core\Database` → **`DGLab\Core\Database`** (namespace kept; dir renamed dbal→Database — or keep dir `Dbal` w/ same NS; **D-H4-adjacent**: recommend dir `Database` to match NS) |
| Composer identity | sovereign-stack/core-config → **deleted** (root only) | core-container → deleted | core-crypto → deleted | core-dbal → deleted |
| Actual responsibility | Immutable config repository (merge files+env+overrides; redaction; freeze) | PSR-11 DI + Fiber pulse() Shape C + compiler passes | AES-GCM/HKDF/Argon2id/key registry | PDO wrapper + query builder + drivers + savepoints |
| Target path | `Core/Config/` | `Core/Container/` | `Core/Crypto/` | `Core/Database/` |
| Action | Move+Rename | Move+Rename | Move+Rename | Move+Rename (dir≠NS fix) |
| Dependency evidence | leaf; consumed by kernel/view/hub-config/app | psr/container; consumed by kernel/app | leaf; consumed by hub-identity only | leaf; consumed by hub-identity, spokes lms/showcase |
| Test ownership | co-located `tests/` → stays co-located (D-H3) | same | same | same |
| Other affected assets | per-pkg composer.json+ci/run.php+phpstan.neon+README (absorbed root-level); FROZEN-CONTRACTS entry | same + PSR-11 conformance runner path in packages-ci | same (no ci/ no README today — add runner, see G) | same |
| Contract impact | ConfigInterface etc. frozen — **names only** | ContainerInterface+pulse semantics frozen | Encrypter/KeyRegistry frozen | Connection/QueryBuilder/Driver frozen |
| Migration order | Stage 2 (wave 0) | Stage 2 (wave 0) | Stage 2 (wave 0) | Stage 2 (wave 0) |
| Risk / mitigation | low; grep gates | medium (pulse fixtures); keep test bodies identical | low; **add Encrypter/Envelope tests post-migration (defect D-3)** | low; note TenantContext API |
| Verification | composer dump-autoload -o; Unit suite green; no stale `SovereignStack\Core\Config` refs | + PSR-11 conformance | + Unit×4 green | + Unit×4 green |
| Conf / Evidence | V (src read) | V | V | V |

| Field | C-08 Error Handler | C-03 Event Dispatcher | C-14 Filesystem | C-04 HTTP Message |
|---|---|---|---|---|
| Existing ID / path | CORE-08 · `packages/core/error-handler` | CORE-03 · `packages/core/event-dispatcher` | CORE-14 · `packages/core/filesystem` | CORE-04 · `packages/core/http-message` |
| Namespace now → target | `SovereignStack\Core\ErrorHandler` → **`DGLab\Core\ErrorHandler`** | `SovereignStack\Core\EventDispatcher` → **`DGLab\Core\EventDispatcher`** | `SovereignStack\Core\Filesystem` → **`DGLab\Core\Filesystem`** | `SovereignStack\Core\Http` → **`DGLab\Core\Http`** |
| Composer identity | core-error-handler → deleted | core-event-dispatcher → deleted | core-filesystem → deleted | core-http-message → deleted |
| Actual responsibility | Global handler + renderers | PSR-14 dispatcher + provider | Root-scoped blob storage + atomic writes + quarantine | PSR-7/17 message VOs + factories |
| Target path | `Core/ErrorHandler/` | `Core/EventDispatcher/` | `Core/Filesystem/` | `Core/Http/` |
| Action | Move+Rename | Move+Rename | Move+Rename (**flag: zero code dependents — orphaned-but-complete; keep, re-evaluate consumers post-migration**) | **Split partner A** of the shared `Core\Http` namespace (OD-01): dir becomes `Core/Http/`; middleware leaves the namespace |
| Dependency evidence | psr/log (src); consumed by kernel/app | psr-14; consumed by kernel (Event base!) + app | leaf; consumed by nobody (lint only) | psr/http-*; consumed by middleware, kernel, router(tests), app, vanguard, canvas |
| Test ownership | co-located | co-located (note: tests at tests/ root, not Unit/) | co-located | co-located |
| Other affected assets | composer/ci/README absorbed; **drop declared-but-unused dep on core-logger (tests keep it via root autoload)** | same | add ci/ runner + README (gap) | **boundary-lint allowlist? no; naming-drift map yes; `.github/architecture-export-allowlist.yaml` no** |
| Contract impact | ErrorHandlerInterface/RendererInterface frozen | EventDispatcher/ListenerProvider interfaces frozen | FilesystemInterface frozen | MessageFactoryInterface frozen |
| Migration order | Stage 2 (wave 2 w/ logger chain) | Stage 2 (wave 0) | Stage 2 (wave 0) | **Stage 2 FIRST** (middleware+callers in same stage) |
| Risk / mitigation | low | low (Event base class extended by kernel events — same-stage rename) | low | **HIGH: dual PSR-4 root today; every `SovereignStack\Core\Http\X` import must be re-resolved to Http vs Middleware owner class-by-class** (only 3 classes actually belong to middleware semantics: pipeline/resolver/final-handler; all PSR-7 VOs stay Http) |
| Verification | Unit green; grep | Unit green; kernel events still extend Event | Unit green | autoload -o; middleware suite green; grep `SovereignStack\Core\Http` = 0 |
| Conf | V | V | V | V |

| Field | C-18 Kernel | C-09 Logger | C-05 Middleware | C-07 NeoSPX | C-06 Router | (view) Template |
|---|---|---|---|---|---|---|
| Existing ID / path | CORE-18 · `packages/core/kernel` | CORE-09 · `packages/core/logger` | CORE-05 · `packages/core/middleware` | CORE-07 · `packages/core/neospx` | CORE-06 · `packages/core/router` | `packages/core/view` |
| Namespace → target | `SovereignStack\Core\Kernel` → **`DGLab\Core\Kernel`** | `SovereignStack\Core\Logger` → **`DGLab\Core\Logger`** | `SovereignStack\Core\Http` → **`DGLab\Core\Middleware`** (OD-01 confirmed) | `SovereignStack\Core\NeoSPX` → **`DGLab\Core\NeoSpx`** (canonical casing decision D-H5) | `SovereignStack\Core\Router` → **`DGLab\Core\Router`** | `SovereignStack\Core\View` → **`DGLab\Core\View`** |
| Composer identity | core-kernel → deleted | core-logger → deleted | core-middleware → deleted | core-neospx (already absent) → deleted | core-router → deleted | core-view → deleted |
| Actual responsibility | Lifecycle state machine + bootstrappers + RequestContext + lifecycle audit + CORE-17 Stub | PSR-3 logger + formatters/handlers | Pipeline + resolver + FinalRequestHandler | **Lexer only** (parser/compiler/runtime absent) | Route registry + compiler + attribute loader | Driver-based templates: Twig complete; NeoSPX stub |
| Target path | `Core/Kernel/` | `Core/Logger/` | `Core/Middleware/` | `Core/NeoSpx/` (D-H5) | `Core/Router/` | `Core/View/` |
| Action | Move+Rename (+ keep `Stub/` in place, CORE-17 pending) | Move+Rename | **Split partner B** (namespace change; dir move) | Move+Rename (**carry as-is; add to root composer (currently orphaned); fix tests/bootstrap foreign path; add CI matrix entry**) | Move+Rename | Move+Rename |
| Dependency evidence | imports all 8 shipped core comps (config/container/event/err/http/logger/middleware/router) | psr/log | imports http-message(Response), router(RouterInterface) | leaf (mbstring/pcre) | psr-7 only in src | twig+config |
| Test ownership | co-located (Unit+Integration+fixture) | co-located | co-located | co-located (fix bootstrap) | co-located | co-located |
| Other affected assets | FROZEN-CONTRACTS CORE-18 doctrine block; hub-identity imports RequestContext | drop unused dep on core-config | naming-drift map; middleware tests import http classes | EBNF spec doc (root) → Docs; **add to packages-ci matrix (drift fix)** | vanguard declares dep (unused) — drop | app inline view config; ViewIntegrationTest |
| Contract impact | KernelInterface/KernelState/PanicException/constants frozen | LoggerInterface/LogRecord frozen | Pipeline/Resolver/FinalRequestHandler interfaces frozen | none frozen (no contracts) | RouterInterface frozen | TemplateDriverInterface frozen-ish (v0.1) |
| Migration order | Stage 2 LAST (wave 3 — after its deps) | Stage 2 (wave 1) | Stage 2 (with Http split, wave 1) | Stage 2 (wave 0) | Stage 2 (wave 2) | Stage 2 (wave 0) |
| Risk / mitigation | MEDIUM (most-imported core comp: app, hub-identity) | low | HIGH (namespace split; see C-04 row) | low (nobody depends on it) | low-med (FinalRequestHandler + kernel import it) | low (app + integration test import) |
| Verification | kernel+app+identity suites green | Unit green | Unit×4+Exception+Immutability+Order green | lexer tests green (after bootstrap fix) | Unit+Security green | Unit + ViewIntegrationTest green |
| Conf | V | V | V | V | V | V |

**Core: class-level split detail for the shared `SovereignStack\Core\Http` namespace (the OD-01 split):**
- Stay in `DGLab\Core\Http` (PSR-7/17 semantics, from http-message): Request, Response, ServerRequest, Stream, UploadedFile, Uri, RequestFactory, ResponseFactory, ServerRequestFactory, StreamFactory, UriFactory, UploadedFileFactory, MessageFactory(+Interface).
- Move to `DGLab\Core\Middleware` (from middleware pkg): MiddlewarePipeline(+Interface), MiddlewareResolver(+Interface), FinalRequestHandler(+Interface), CallableMiddlewareAdapter, PerRequestHandler (@internal).
- Caller updates required in: kernel (Kernel.php, HttpBootstrapper.php import `Http\MiddlewarePipelineInterface`, `Http\MiddlewareResolverInterface`), app/ApplicationFactory, vanguard docblocks, tests (middleware suite imports `Http\Response` for 404 — stays Http), FinalRequestHandler keeps importing `DGLab\Core\Http\Response` + `DGLab\Core\Router\RouterInterface`.

## C-2. Hub tier (packages/hub/* → Hub/*)

| Field | HUB-01 Hub Config | HUB-04 Hub Identity |
|---|---|---|
| Existing path / namespace | `packages/hub/config` · `SovereignStack\Hub\Config` | `packages/hub/identity` · `SovereignStack\Hub\Identity` |
| Composer identity | sovereign-stack/hub-config → deleted | hub-identity → deleted |
| Actual responsibility | Tenant config layering + feature flags w/ deterministic rollouts (in-memory repos; DB deferred; **no SQL migrations exist**) | Auth service + User/JWT(ES256)/AuthMiddleware + MySQL repo (3 SQL migrations) |
| Target path / namespace | **`Hub/Config/`** · `DGLab\Hub\Config` | **`Hub/Identity/`** · `DGLab\Hub\Identity` |
| Action | Move+Rename | Move+Rename |
| Dependency evidence | core-config(ConfigInterface) | core-crypto(PasswordHasher), core-dbal(ConnectionInterface), core-kernel(RequestContext) |
| Dependents | none in code (docs+lint) | spoke-lms ×11 files, spoke-showcase ×3 files (namespace imports!) |
| Test ownership | co-located | co-located |
| Other affected assets | `.github/architecture-export-allowlist.yaml` (6 FQCNs → rename), naming-drift map, FROZEN-CONTRACTS HUB-01 | allowlist (6 FQCNs), FROZEN-CONTRACTS, **migrations 001-003 → Database/ (see C-7)** |
| Contract impact | GlobalConfigInterface/FeatureManagerInterface frozen | IdentityInterface + VOs (no registered freeze? — interfaces are de-facto frozen via dependents) |
| Migration order | Stage 3 (after Core) | Stage 3 (after Core; before spokes) |
| Risk / mitigation | low | **MEDIUM: 14 spoke files import its namespace; same-stage caller updates; Request­Context import binds it to Kernel stage** |
| Verification | Unit×5 green; allowlist lint green | Unit×6 green; lms+showcase suites green |
| Conf | V | V |

**Hub components with blueprints but NO code (no migration action; governance-only):** HUB-02..HUB-32 minus implemented (30 blueprints, incl. superseded HUB-10/25 → RUNTIME-03/04, deferred HUB-32 AI Inference at depth 1 interface-only, HUB-31 blocked). These are *Architecture/ documents*, not runtime components — they stay in `Architecture/Hub/` untouched. The inventory (Deliverable D) records them with `status: blueprint-only`.

## C-3. Bridge tier (packages/bridge/vanguard → Bridge/Vanguard)

| Field | BRIDGE-01 Vanguard |
|---|---|
| Existing path / namespace | `packages/bridge/vanguard` · `SovereignStack\Bridge\` (flat — no Vanguard sub-NS) |
| Composer identity | sovereign-stack/bridge-vanguard → deleted |
| Actual responsibility | PSR-15 boundary security middleware (7-step chain; JWT/rate-limit/audit stubs; WAF + default-deny contracts + DTO transform real). **In-process security chokepoint — NOT an external-system integrator** |
| Target path / namespace | **`Bridge/Vanguard/`** · **`DGLab\Bridge\Vanguard\`** (sub-namespace added — flat `DGLab\Bridge\` would collide with future bridge components; justified by target NS list showing `DGLab\Bridge\...` hierarchical) |
| Action | Move+Rename (namespace gains Vanguard segment) |
| Dependency evidence | core-http-message (Stream) only in src; declared-but-unused: core-middleware, core-router (drop) |
| Dependents | **app/ApplicationFactory (the only runtime wiring)** + PublicIndexSmokeTest + contracts `/`,`/hello`,`/health`×4 |
| Test ownership | co-located |
| Other affected assets | FROZEN-CONTRACTS (BoundaryContractInterface, DtoTransformerInterface) |
| Contract impact | both interfaces frozen — namespace-only |
| Migration order | Stage 4 (after Hub; before spokes per ADR-021 tier order) |
| Risk / mitigation | low-med (it's outermost middleware in the skeleton; smoke test guards it) |
| Verification | Vanguard suite + PublicIndexSmokeTest green |
| Conf | V |

**Bridge tier justification (D-layer analysis):** exactly one component; it is a genuine cross-tier security mechanism sitting between external traffic and the kernel — tier justified. No reclassification needed. Canvas does NOT belong here (it's a content-delivery capability, correctly an external spoke by intent even though its current depth-2 code is in-process).

## C-4. Internal spokes (packages/spoke/internal/* → Spokes/Internal/*)

| Field | ISPOKE-09 Codex | LMS Spoke | Showcase Spoke |
|---|---|---|---|
| Existing path / namespace | `packages/spoke/internal/codex` · `SovereignStack\Internal\Codex` | `packages/spoke/internal/lms` · `SovereignStack\Spoke\Lms` | `packages/spoke/internal/showcase` · `SovereignStack\Spoke\Showcase` |
| Composer identity | spoke-codex → deleted | spoke-lms → deleted | spoke-showcase → deleted |
| Actual responsibility | Versioned knowledge-base capability (in-memory; isPublic boundary) | **Reusable LMS domain capability**: Course/Module/Enrollment/Progress + app services + MySQL repos + 5 SQL migrations | Product-catalog capability (create/publish only; 1 SQL migration) |
| Target path / namespace | **`Spokes/Internal/Codex/`** · **`DGLab\Spokes\Internal\Codex`** | **`Spokes/Internal/Lms/`** · **`DGLab\Spokes\Internal\Lms`** | **`Spokes/Internal/Showcase/`** · **`DGLab\Spokes\Internal\Showcase`** |
| Action | Move+Rename | Move+Rename (+ **5 migrations → Database/, see C-7**) | Move+Rename (+ **1 migration → Database/**) |
| Dependency evidence | none (standalone) | core-dbal + hub-identity (11 files) | core-dbal + hub-identity (3 files) |
| Dependents | none in code | none in code (awaiting Products/LMS composition) | none in code |
| Test ownership | co-located | co-located | co-located |
| Other affected assets | FROZEN-CONTRACTS (KnowledgeBaseInterface) | allowlist (10 FQCNs), FROZEN-CONTRACTS; **defects carried: progress unpersisted; re-enroll vs UNIQUE conflict (D-H12 — not fixed by migration)** | allowlist (11 FQCNs); dead exceptions noted |
| Migration order | Stage 5 | Stage 5 (after Hub identity) | Stage 5 |
| Risk / mitigation | low | MEDIUM (14 namespace-import sites; MySQL repo tests mock DBAL iface — rename mocks too) | low-med |
| Verification | Unit green | Unit×7 green + grep | Unit×2 green + grep |
| Conf | V | V | V |

**Layer analysis (D4):** all three are genuinely *reusable domain capabilities* (library semantics, no HTTP/controllers) — correct as spokes. The LMS **Product** (deployable app) is a different thing (C-6): it will compose `Spokes/Internal/Lms`. No reclassification. Codex-Product deferred (D-H6).

## C-5. External spokes (packages/spoke/external/canvas → Spokes/External/Canvas)

| Field | ESPOKE-01 Canvas |
|---|---|
| Existing path / namespace | `packages/spoke/external/canvas` · `SovereignStack\External\Canvas` |
| Composer identity | spoke-canvas → deleted |
| Actual responsibility | Content-delivery capability w/ stale-while-revalidate + SEO validation. **No external system integration exists at depth 2** (Bridge fetch/CDN simulated in-process; no HTTP client; no credentials) |
| Target path / namespace | **`Spokes/External/Canvas/`** · **`DGLab\Spokes\External\Canvas`** |
| Action | Move+Rename |
| Dependency evidence | core-http-message (Stream, Response) |
| Dependents | none in code |
| Test ownership | co-located |
| Other affected assets | FROZEN-CONTRACTS (ContentDeliveryInterface, SeoValidationInterface) |
| Migration order | Stage 5 |
| Risk / mitigation | low |
| Verification | Unit green; grep |
| Conf | V |

**Layer analysis (D5):** the *architectural intent* (external-delivery tier) is confirmed by blueprints; the *current implementation* is in-process simulation. It stays an external spoke by intent; the migration does not reclassify it. Note for the owner: 18 other ESPOKE blueprints have no code.

## C-6. Products & application composition (app/ + public/ → Products/LMS/)

| Field | Walking skeleton (app/+public/) | XFF debug fixture | Placeholder scaffolding |
|---|---|---|---|
| Existing path / namespace | `app/ApplicationFactory.php` + `app/Controller/*` + `app/Resources/views/hello.twig` + `public/index.php` · `App`, `App\Controller` | `public/debug/xff.php` | `config/`, `cli/`, `app/{Bridge,Spokes}/**.gitkeep`, `docker-compose.yml`, `.env.example` |
| Actual responsibility | Generic composition root + front controller serving `/`, `/health`, `/hello`; FrankenPHP worker loop | APP_DEBUG-gated XFF diagnostic (anvil smoke dependency) | Empty placeholders ("Nothing here yet" / "deferred") |
| Target path / namespace | **`Products/LMS/src/Bootstrap.php`** (=ApplicationFactory, class `DGLab\Products\LMS\Bootstrap`; canonical PSR-4 root `Products/LMS/src/` per F-1, consistent with every other component), **`Products/LMS/src/Controller/{Hello,Health,Template}Controller.php`** (namespace `DGLab\Products\LMS\Controller`), **`Products/LMS/public/index.php`** (front controller — non-namespaced, `require_once __DIR__.'/../src/Bootstrap.php'`), **`Products/LMS/Routes/web.php`** (route table — non-namespaced), **`Products/LMS/Config/view.php`** (inline view config becomes file — non-namespaced), **`Products/LMS/resources/views/hello.twig`**, **`Products/LMS/tests/Unit/*`** (namespace `DGLab\Products\LMS\Tests\Unit`) | Stays a dev fixture: `Products/LMS/public/debug/xff.php` (anvil smoke probes `/debug/xff(.php)`) | **Remove** (5 .gitkeep trees + 2 placeholder files); root `Dockerfile` → re-point/retire (docs server), `Public/` root retires (D-H8) |
| Action | **Move + Split** (app/tests → Products/LMS tests; integration tests → Testsuite/; demo controllers → Products/LMS/Controller/) | Move | Remove |
| Dependency evidence | imports 11 SovereignStack core comps + Vanguard; wired contracts/routes | none (self-gated) | — |
| Dependents | anvil (document root `public/index.php` at fixed relative depth); tests/Integration boots it | anvil deploy-smoke | — |
| Contract impact | ApplicationFactory public API (create/run/getKernel) is SPEC-001 §44-restricted — preserve | — | — |
| Migration order | Stage 6 (after spokes exist at new paths) | Stage 6 | Stage 10 |
| Risk / mitigation | **HIGH: anvil Caddyfile + frankenphp.sh + deploy.sh all assume `<root>/public/index.php`; moving it changes the dev/release path geometry — must update anvil refs in same stage + re-run deploy-smoke in dev** | low (probe path unchanged relative to docroot) | low |
| Verification | PublicIndexSmokeTest + ViewIntegrationTest green from new path; anvil dev boot + smoke passes; `/health/{live,ready,dependencies}` routing fixed (D-4 defect: registered-but-unrouted — **fix in Stage 6 as a called-out behavior change** or keep defect; owner call) | smoke green | grep for placeholder refs = 0 |
| Conf | V (paths) / **U (D-H1 policy)** | V | V |

**D6/D7 layer analysis:** No `App/` tier is justified — the skeleton is the LMS product seed (only Product with code today). `Products/Codex/` is reserved as a future Product boundary (blueprint-only, no application code today — see C-6b below and D-H6 updated); the migration does NOT invent Codex application code. Template engines stay in `Core/View` (driver infra) — NOT an external spoke (adapter-for-library ≠ external-service spoke). Product resources: `Products/LMS/resources/` with explicit shared-fallback config only (deterministic resolution: product resources win; shared root configured, no autodetection). Twig-first confirmed; NeoSPX driver stub stays until runtime ships (never default — enforced already by ViewFactory rejecting 'neospx').

## C-6b. Reserved Product boundary: Products/Codex (proposed, no code today)

| Field | Codex Product (proposed) |
|---|---|
| Existing path / namespace | (none — no application code exists today) |
| Actual responsibility | Future deployable application composing the Codex spoke (ISPOKE-09). Not implemented in this migration. |
| Target path / namespace | **`Products/Codex/`** · `DGLab\Products\Codex` (reserved per agreed target structure §3) |
| Composer identity | n/a — no source code today; when implemented, root autoload prefix `DGLab\Products\Codex\` → `Products/Codex/src/` (consistent with LMS Product convention) |
| Action | **Defer** (blueprint-only; no source to migrate — see D-H6 updated) |
| Dependency evidence | (none today) — expected to compose `Spokes/Internal/Codex/` when implemented (declared intent in brief, not yet wired) |
| Dependents | none (no code) |
| Test ownership | n/a |
| Other affected assets | none |
| Contract impact | none (no contracts to freeze) |
| Migration order | Post-migration workstream when the Codex application is built |
| Risk / mitigation | none (no code = no risk); owner directive: do NOT invent implementation merely to fill the directory |
| Verification | inventory records PRODUCT-CODEX at `status: blueprint-only`; no source code on disk to verify |
| Conf | U (no source evidence) |

The reusable capability stays at `Spokes/Internal/Codex/` (C-4 row "ISPOKE-09 Codex"). Decision D-H6 (updated) confirms: `Products/Codex/` is the intended application boundary; `Spokes/Internal/Codex/` is the capability. The migration does not blur this distinction by inventing code.

## C-7. Database assets (SQL migrations + runner)

| Field | Identity migrations (3) | LMS migrations (5) | Showcase migration (1) | Migration runner (PR #370, unmerged) |
|---|---|---|---|---|
| Existing path | `packages/hub/identity/migrations/001..003.sql` | `packages/spoke/internal/lms/migrations/001..005.sql` | `packages/spoke/internal/showcase/migrations/001_create_products_table.sql` | branch `migration-runner-impl` (packages/core/migration-runner) |
| Content | users/roles/user_roles (unprefixed) | lms_courses/modules/enrollments/progress + content ALTER | showcase_products | 8 metadata tables + gseq + gate + CLI + tests (Rev 5.3 design) |
| Target path | **`Database/Migrations/Identity/001..003.sql`** (D-H4) | **`Database/Migrations/Lms/001..005.sql`** | **`Database/Migrations/Showcase/001.sql`** | **`Database/Runner/`** (proposed; D-H4) — CLI entry re-homed to `Cli/migrate` per target tree; NOT implemented in this task |
| Action | Move (with ownership preserved via directory + inventory `owner` field) | Move | Move | Defer (separate workstream; rebase onto new layout when merged) |
| Risk | table-name collision space (unprefixed `users` vs future) — record in inventory, don't rename now | **UNIQUE(course_id,learner_id) vs re-enroll semantics (D-H12 pre-existing defect — carried, not fixed)** | low | PR #370 base ff439a4 is 1 commit behind main; will need rebase onto migrated tree |
| Conf | V | V | V | P (code on branch, reviewed at design level only) |

## C-8. CLI / Testsuite / Docs / Tools

| Component | Existing | Target | Action | Notes |
|---|---|---|---|---|
| Loom CLI | `orchestrator/bin/loom` | **stays in place** (protected) | Retain | reference updates only: path globs `packages/*/*/composer.json` must learn the tier layout **when D-H2 release model is decided**; until then release.yml per-package jobs disabled at cutover (Stage 9) |
| anvilctl | `anvil/anvilctl` | stays in place | Retain | fix latent `anvil/bin/anvilctl` web-UI path bug (pre-existing; optional) |
| Migration runner CLI | (PR #370 `cli/migrate`) | `Cli/migrate` (future) | Defer | D-H4 |
| `cli/` scaffolding | `cli/.bin/.gitkeep` | — | Remove | real CLI elsewhere |
| Component tests (177 files) | co-located in each package | co-located in each component (D-H3 recommended) | Move-with-component | count 177 before/after as metric |
| Cross-component tests | `tests/Integration/*` (2) | **`Testsuite/Integration/`** | Move | root phpunit.xml.dist updated (Stage 8) |
| App unit tests | `app/tests/Unit/*` | `Products/LMS/tests/Unit/` + **added to a runner (currently orphaned — no CI)** | Move + Fix | runner gap closed in Stage 8 |
| Architecture tests | orchestrator/tests, lint self-tests | stay co-located | Retain | Loom contract test pins `packages/core/logger` — must be re-pinned to `Core/Logger` in Stage 9 |
| Root-doc corpus (23 .md) | repo root | **`Docs/`** sub-layout (D-H7): Docs/Research/, Docs/Architecture-Reports/, Docs/Operations/ | Move | README.md + AGENTS.md/CLAUDE* stay at root (orientation); `document.md` → Docs/Research/sources/; `worklog.md` → untrack or Docs/ (D-H11) |
| Boundary/fitness scripts | `scripts/` (12) | **`Tools/`** (recommended: Tools/lint, Tools/fitness, Tools/ci) or stay `scripts/` | Move (D-H7 scope) | **workflow paths + naming-drift map + allowlist update in lockstep (Stage 9)** |
| Obsolete generators | `scripts/generate-architecture-baseline{.py,.php}` + doc-append helpers | — | **Remove** (S-026; one-shot) | |

## C-9. Protected directories & archives (D9)

| Component | Action | Detail |
|---|---|---|
| `Architecture/` | **Retain physically** | only the new `component-inventory.yaml` is added (Stage 1, governance task); INDEX/DAG repair scope = D-H9; Dockerfile CMD references it (docs server) — update only if docroot policy changes |
| `orchestrator/` | **Retain physically** | reference updates in Stage 9 (path globs); its own composer.json/lock stay (self-contained vendored tool, excluded from "no per-component manifests" rule by its protected status — flag for D-H2) |
| `anvil/` | **Retain physically** | Caddyfile/frankenphp/deploy refs update in Stage 6 (document-root change) |
| `archive/` sub-trees (6) | Classify: **Keep archived** (all 6 by default) | Extraction candidates recorded for future workstreams, NOT this migration: (1) Legacy auth/MFA/password-reset/verification suite → HUB-04 backlog; (2) Legacy job/event queue + worker CLI → RUNTIME-03; (3) Legacy tenancy/RBAC → HUB-19/28 backlog; (4) 90-file Legacy test corpus incl. accessibility/visual assertions → Testsuite scenarios porting; (5) Legacy download-manager (chunked upload + tokened downloads + audit) → future spoke; (6) SuperPHP engine (Lexer/Parser/Compiler/Interpreter) → **reference only** for NeoSPX work (already conceptually succeeded by packages/core/neospx); vendored Bootstrap/FontAwesome UI kit → **low value, do not extract** |

---

## C-10. Action summary counts (reconciled with A §8 and B summary)

**Counting conventions:** see A §8 for the canonical definitions (components / physical moves / namespace changes / splits / merges / removals). Splits are 1→many mappings (counted once, here); moves+renames are 1:1 mappings (counted once, here); the skeleton's primary action is split, so it is NOT counted under moves.

- **Move+Rename (directory+namespace, 1:1 source→target): 21 components** (14 Core + 2 Hub + 1 Bridge + 4 Spokes). Skeleton is NOT counted here — its 1→many mapping makes it a split, not a move.
- **Split: 2** (shared `Core\Http` NS → Http + Middleware per OD-01; app-skeleton → Products/LMS + Testsuite/ + Products/LMS/tests). Skeleton counted ONLY here.
- **Merge: 1 group** (9 SQL migrations → Database/Migrations/<owner>).
- **Remove: 11 items** (4 .gitkeep placeholder trees, `config/`, `cli/`, `docker-compose.yml`, `.env.example`, 2 obsolete generator scripts, 3 doc-append helpers — counted with those; plus untrack `.phpunit.cache/`).
- **Retain in place:** Architecture/, orchestrator/, anvil/, archive/ (6 subtrees), README/AGENTS/CLAUDE root docs. Internal namespaces inside protected dirs (e.g., `SovereignStack\Orchestrator\`) are grandfathered as sanctioned exceptions per I-9 — the migration does NOT rewrite protected code.
- **Defer:** migration-runner home (D-H4), Public/ retirement (D-H8), NeoSPX casing (D-H5). `Products/Codex/` is NOT in the defer list — it is a reserved proposed Product boundary (blueprint-only; no migration action because there is no source to move — see C-6b and D-H6 updated).
- **Unresolved classifications: 5 explicit** (D-H1, D-H4, D-H5, D-H7 depth, D-H8) — see Deliverable H.

**Cross-document total reconciliation:** A §8 = B summary = C §C-10. All three documents agree: 42 components / 21 moves / 2 splits / 1 merge / 11 removals.

*Deliverable C complete.*
