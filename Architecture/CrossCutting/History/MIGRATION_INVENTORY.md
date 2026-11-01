# DGLab Migration Inventory

**Generated:** 2026-10-31  
**Baseline:** `origin/main` at commit `ae410ea09fcd7a4c0c2c77cc980ff7d3e4984ac3` (historical) — current worktree on `main` with staged migration changes  
**Scope:** All components in `packages/` directory and new top-level tier directories (`Core/`, `Hub/`, `Bridge/`, `Spokes/Internal/`, `Spokes/External/`)

---

## Executive Summary

| Tier | Documented (INDEX.md) | Legacy Packages (`packages/`) | New Tier Directories | Migrated | In Progress | Not Started |
|------|----------------------|-------------------------------|---------------------|----------|-------------|-------------|
| **Core** | 20 | 10 (9 with composer.json) | 6 (`Core/`) | 6 | 3 | 11 |
| **Hub** | 31 (29 active) | 2 | 0 | 0 | 0 | 29 |
| **Bridge** | 1 | 1 | 0 | 0 | 0 | 1 |
| **Internal Spokes** | 27 | 3 | 0 | 0 | 0 | 24 |
| **External Spokes** | 18 | 1 | 0 | 0 | 0 | 17 |
| **Total** | 102 | 17 | 6 | 6 | 3 | 82 |

> **Note:** The migration from `packages/` to tier-isolated top-level directories (`Core/`, `Hub/`, `Bridge/`, `Spokes/Internal/`, `Spokes/External/`) is **in progress**. Only Core tier components have been partially migrated. The `packages/` directory remains the primary source for Hub, Bridge, and Spoke components.

---

## 1. Core Tier Components

| # | Component ID | Component Name | Legacy Path | Target Path | Current Namespace | Target Namespace | Tests | Composer.json | Dependencies (require) | Complexity | Status |
|---|--------------|----------------|-------------|-------------|-------------------|------------------|-------|---------------|------------------------|------------|--------|
| 1 | CORE-01 | Polyrepo Orchestrator ("Loom") | `orchestrator/` | `Core/Orchestrator/` | `SovereignStack\Orchestrator` | `SovereignStack\Orchestrator` | Yes (`orchestrator/tests/`) | Yes (`orchestrator/composer.json`) | php ^8.4, psr/container ^2.0, psr/event-dispatcher ^1.0, psr/log ^3.0, symfony/console ^6.0, symfony/filesystem ^6.0 | **High** (CLI tooling, multi-repo orchestration, release automation) | ✅ **Merged** (implemented + tested, v1.0.0) |
| 2 | CORE-02 | Dependency Injection Container | `packages/core/container/` | `Core/Container/` | `SovereignStack\Core\Container` | `SovereignStack\Core\Container` | Yes (`tests/`, 97.2% coverage) | Yes | php ^8.4, psr/container ^2.0 | **Medium** (PSR-11, autowiring, compiler passes, circular detection) | ✅ **Merged** (implemented + tested, v1.0.0) |
| 3 | CORE-03 | PSR-14 Event Dispatcher | `packages/core/event-dispatcher/` → **staged to `Core/EventDispatcher/`** | `Core/EventDispatcher/` | `SovereignStack\Core\EventDispatcher` | `DGLab\Core\EventDispatcher` | Yes (`tests/`) | Yes (staged for deletion) | php ^8.4, psr/event-dispatcher ^1.0, psr/container ^2.0 | **Low-Medium** (PSR-14 compliant, listener provider) | 🔄 **In Progress** (staged rename to `Core/EventDispatcher/`, namespace change to `DGLab\Core\EventDispatcher`) |
| 4 | CORE-04 | PSR-7 HTTP Message & Factory | `packages/core/http-message/` | `Core/HttpMessage/` | `SovereignStack\Core\Http` | `SovereignStack\Core\Http` | Yes (`tests/`, PSR-7 integration tests) | Yes | php ^8.4, psr/http-message ^2.0, psr/http-factory ^1.0, ext-mbstring, ext-fileinfo | **Medium** (PSR-7/17, streaming, upload handling) | ✅ **Merged** (tagged core-http-message-v1.0.0) |
| 5 | CORE-05 | PSR-15 Middleware & Request Handler | `packages/core/middleware/` | `Core/Middleware/` | `SovereignStack\Core\Http` | `SovereignStack\Core\Http` | Yes (`tests/`) | Yes | php ^8.4, psr/http-message ^2.0, psr/http-server-handler ^1.0, psr/http-server-middleware ^1.0, psr/container ^2.0, sovereign-stack/core-http-message, sovereign-stack/core-router | **Medium** (PSR-15 pipeline, cursor-based, router integration) | ✅ **Merged** (tagged core-middleware-v1.0.0) |
| 6 | CORE-06 | Attribute-Based Router | `packages/core/router/` | `Core/Router/` | `SovereignStack\Core\Router` | `SovereignStack\Core\Router` | Yes (`tests/`) | Yes | php ^8.4, psr/http-message ^2.0, sovereign-stack/core-http-message, ext-mbstring, ext-pcre | **Medium** (attribute routing, PCRE compilation, method-indexed matching) | ✅ **Merged** (tagged core-router-v1.0.0) |
| 7 | CORE-07 | NeoSPX Lexer | — (new) | `Core/NeoSpx/Lexer/` | — | `DGLab\Core\NeoSpx\Lexer` | Yes (`Core/NeoSpx/tests/Unit/`) | No (part of Core/NeoSpx) | php ^8.4, ext-mbstring, ext-pcre | **Medium** (lexer state machine, tokenization, source location tracking) | 🔄 **In Progress** (implemented in `Core/NeoSpx/`, not in `packages/`) |
| 8 | CORE-08 | Global Error & Exception Handler | `packages/core/error-handler/` | `Core/ErrorHandler/` | `SovereignStack\Core\ErrorHandler` | `SovereignStack\Core\ErrorHandler` | Yes (`tests/`) | Yes | php ^8.4, psr/log ^3.0, sovereign-stack/core-logger, sovereign-stack/core-config | **Medium** (PSR-3 logging, multi-format rendering, stack-trace sanitization) | ✅ **Merged** (implemented) |
| 9 | CORE-09 | PSR-3 Logging Service | `packages/core/logger/` | `Core/Logger/` | `SovereignStack\Core\Logger` | `SovereignStack\Core\Logger` | Yes (`tests/`) | Yes | php ^8.4, psr/log ^3.0, sovereign-stack/core-config | **Medium** (PSR-3, HandlerStack, JSON/Line formatters, flock file handler) | ✅ **Merged** (implemented) |
| 10 | CORE-10 | Configuration & Environment Loader | `packages/core/config/` (tests only) → **migrated to `Core/Config/`** | `Core/Config/` | `SovereignStack\Core\Config` | `DGLab\Core\Config` | Yes (`Core/Config/tests/`) | No (was in packages, now in Core/) | php ^8.4, ext-json, ext-hash, psr/container ^2.0, sovereign-stack/core-container | **Medium** (tenant-aware, .env + JSON, feature flags, deterministic rollouts) | 🔄 **In Progress** (migrated to `Core/Config/`, namespace changed to `DGLab\Core\Config`) |
| 11 | CORE-11 | NeoSPX Parser | — | `Core/NeoSpx/Parser/` | — | `DGLab\Core\NeoSpx\Parser` | No | No | — | **Medium** | 📝 **Not Started** |
| 12 | CORE-12 | NeoSPX Compiler | — | `Core/NeoSpx/Compiler/` | — | `DGLab\Core\NeoSpx\Compiler` | No | No | — | **Medium** | 📝 **Not Started** |
| 13 | CORE-13 | CLI Engine (Console) | — | `Core/Console/` | — | `SovereignStack\Core\Console` | No | No | — | **Medium** | 📝 **Not Started** |
| 14 | CORE-14 | Filesystem Abstraction | — (new) | `Core/Filesystem/` | — | `DGLab\Core\Filesystem` | Yes (`Core/Filesystem/tests/`) | No (part of Core/Filesystem) | php ^8.4, psr/log ^3.0 | **Medium** (local + S3, integrity checks, path traversal protection, stream limits) | 🔄 **In Progress** (implemented in `Core/Filesystem/`, not in `packages/`) |
| 15 | CORE-15 | Cache Abstraction (PSR-6/16) | — | `Core/Cache/` | — | `SovereignStack\Core\Cache` | No | No | — | **High** (PSR-6/16, Redis 7, nuclear-grade doctrine) | 📝 **Not Started** (PR #310 parked) |
| 16 | CORE-16 | Binary Encryption Envelope | — (new) | `Core/Crypto/` | — | `DGLab\Core\Crypto` | Yes (`Core/Crypto/tests/`) | No (part of Core/Crypto) | php ^8.4, ext-openssl, ext-sodium, psr/log ^3.0 | **High** (AES-256-GCM, Argon2id, key registry, envelope format, nuclear-grade) | 🔄 **In Progress** (implemented in `Core/Crypto/`, not in `packages/`) |
| 17 | CORE-17 | Service Provider System | — | `Core/Providers/` | — | `SovereignStack\Core\Providers` | No | No | — | **Medium** | 📝 **Not Started** |
| 18 | CORE-18 | Core Kernel & Lifecycle | `packages/core/kernel/` | `Core/Kernel/` | `SovereignStack\Core\Kernel` | `SovereignStack\Core\Kernel` | Yes (`tests/`, chaos tests per doctrine) | Yes | php ^8.4, psr/http-message ^2.0, psr/event-dispatcher ^1.0, psr/container ^2.0, psr/log ^3.0, sovereign-stack/core-container, core-event-dispatcher, core-http-message, core-middleware, core-router, core-config, core-logger, core-error-handler | **High** (orchestrates full request lifecycle, boot/handle/terminate, doctrine §4.5 pilot) | ✅ **Merged** (implemented, doctrine pilot complete) |
| 19 | CORE-19 | Database Abstraction Layer | `packages/core/dbal/` | `Core/Database/` | `SovereignStack\Core\Database` | `SovereignStack\Core\Database` | Yes (`tests/`) | Yes | php ^8.4, ext-pdo, ext-pdo_sqlite, psr/log ^3.0 | **High** (PDO wrapper, QueryBuilder, nested transactions, tenant scoping, MySQL 8/InnoDB per ADR-013) | ✅ **Merged** (implemented, but INDEX says "Not started" — **discrepancy**) |
| 20 | CORE-20 | Sovereign Forge (Developer CLI Toolchain) | — | `Core/Forge/` | — | `SovereignStack\Forge` | No | No | — | **High** (scaffolding, code gen, multi-repo) | 📝 **Not Started** |

---

## 2. Hub Tier Components

| # | Component ID | Component Name | Legacy Path | Target Path | Current Namespace | Target Namespace | Tests | Composer.json | Dependencies (require) | Complexity | Status |
|---|--------------|----------------|-------------|-------------|-------------------|------------------|-------|---------------|------------------------|------------|--------|
| 1 | HUB-01 | Sovereign Hub Config & Flags | `packages/hub/config/` | `Hub/Config/` | `SovereignStack\Hub\Config` | `SovereignStack\Hub\Config` | Yes (`tests/`) | Yes | php ^8.4, ext-json, ext-hash, psr/container ^2.0, sovereign-stack/core-container, sovereign-stack/core-config | **Medium** (tenant-aware config, feature flags, deterministic percentage rollouts) | ✅ **Merged** (implemented) |
| 2 | HUB-04 | Sovereign Identity & Authentication | `packages/hub/identity/` | `Hub/Identity/` | `SovereignStack\Hub\Identity` | `SovereignStack\Hub\Identity` | Yes (`tests/`) | Yes | php ^8.4, ext-json, ext-openssl, psr/container ^2.0, psr/http-message ^2.0, psr/http-server-middleware ^1.0, psr/log ^3.0, sovereign-stack/core-container, core-config, core-crypto, core-dbal, core-http-message, core-kernel, core-event-dispatcher, core-logger | **High** (auth, RBAC integration, JWT, sessions, migrations, crypto, DBAL) | ✅ **Merged** (implemented, has migrations) |
| 3–31 | HUB-02, 03, 05–31 (except 10, 25 superseded) | Various Hub capabilities | — | `Hub/<Name>/` | — | `SovereignStack\Hub\<Name>` | No | No | — | **Varies** (Critical: 10, High: 15, Medium: 6) | 📝 **Not Started** (29 active blueprints documented in INDEX.md) |

> **Note:** HUB-10 (Queue) and HUB-25 (Chronos) are **superseded** per ADR-021, relocated to Runtime tier as RUNTIME-03/RUNTIME-04. HUB-32 (AI Inference Hub) ratified pending canonical publication.

---

## 3. Bridge Tier Components

| # | Component ID | Component Name | Legacy Path | Target Path | Current Namespace | Target Namespace | Tests | Composer.json | Dependencies (require) | Complexity | Status |
|---|--------------|----------------|-------------|-------------|-------------------|------------------|-------|---------------|------------------------|------------|--------|
| 1 | BRIDGE-01 | The Vanguard (Boundary Middleware) | `packages/bridge/vanguard/` | `Bridge/Vanguard/` | `SovereignStack\Bridge` | `SovereignStack\Bridge` | Yes (`tests/`) | Yes | php ^8.4, ext-pcre, ext-json, psr/http-message ^2.0, psr/http-server-middleware ^1.0, psr/http-server-handler ^1.0, psr/http-factory ^1.0, psr/log ^3.0, sovereign-stack/core-http-message, core-middleware, core-router | **High** (default-deny contract routing, WAF inspection, DTO transformation, zero-exposure test) | ✅ **Merged** (implemented) |

---

## 4. Internal Spoke Components

| # | Component ID | Component Name | Legacy Path | Target Path | Current Namespace | Target Namespace | Tests | Composer.json | Dependencies (require) | Complexity | Status |
|---|--------------|----------------|-------------|-------------|-------------------|------------------|-------|---------------|------------------------|------------|--------|
| 1 | ISPOKE-09 | Sovereign Codex (Knowledge Base) | `packages/spoke/internal/codex/` | `Spokes/Internal/Codex/` | `SovereignStack\Internal\Codex` | `SovereignStack\Internal\Codex` | Yes (`tests/`) | Yes | php ^8.4, ext-json, psr/container ^2.0 | **Low-Medium** (Markdown editing, version history, boundary enforcement) | ✅ **Merged** (implemented) |
| 2 | ISPOKE-?? | Showcase Spoke (Product Catalog + Asset Mgmt) | `packages/spoke/internal/showcase/` | `Spokes/Internal/Showcase/` | `SovereignStack\Spoke\Showcase` | `SovereignStack\Internal\Showcase` | Yes (`tests/`) | Yes | php ^8.4, ext-json, psr/http-message ^2.0, sovereign-stack/core-container, core-dbal, core-event-dispatcher, core-http-message, core-kernel, hub-identity | **Medium** (catalog, assets, hub-identity integration, migrations) | ✅ **Merged** (implemented, has migrations) |
| 3 | ISPOKE-?? | LMS Spoke (Course Catalog + Enrollment + Progress) | `packages/spoke/internal/lms/` | `Spokes/Internal/Lms/` | `SovereignStack\Spoke\Lms` | `SovereignStack\Internal\Lms` | Yes (`tests/`, integration tests with vendor) | Yes | php ^8.4, ext-json, psr/http-message ^2.0, sovereign-stack/core-container, core-dbal, core-event-dispatcher, core-http-message, core-kernel, hub-identity | **High** (courses, modules, enrollment, progress, versioned API, MySQL integration tests) | ✅ **Merged** (implemented, has migrations, composer.lock, vendor) |
| 4–27 | ISPOKE-01–08, 10–27 | Remaining Internal Spokes | — | `Spokes/Internal/<Name>/` | — | `SovereignStack\Internal\<Name>` | No | No | — | **Varies** | 📝 **Not Started** (24 blueprints documented in INDEX.md) |

> **Note:** The INDEX.md lists ISPOKE-01 as "Command Center" with dependencies on HUB-01, HUB-04, HUB-06, HUB-15. The Showcase and LMS spokes are implemented but their exact ISPOKE IDs need verification against the INDEX mapping.

---

## 5. External Spoke Components

| # | Component ID | Component Name | Legacy Path | Target Path | Current Namespace | Target Namespace | Tests | Composer.json | Dependencies (require) | Complexity | Status |
|---|--------------|----------------|-------------|-------------|-------------------|------------------|-------|---------------|------------------------|------------|--------|
| 1 | ESPOKE-01 | Sovereign Canvas (Public CMS + Delivery) | `packages/spoke/external/canvas/` | `Spokes/External/Canvas/` | `SovereignStack\External\Canvas` | `SovereignStack\External\Canvas` | Yes (`tests/`) | Yes | php ^8.4, ext-json, psr/http-message ^2.0, sovereign-stack/core-http-message | **Medium** (CMS rendering, SEO validation, stale-while-revalidate caching, BRIDGE-01 integration) | ✅ **Merged** (implemented) |
| 2–18 | ESPOKE-02–18 | Remaining External Spokes | — | `Spokes/External/<Name>/` | — | `SovereignStack\External\<Name>` | No | No | — | **Varies** | 📝 **Not Started** (17 blueprints documented in INDEX.md, including hospitality vertical ESPOKE-16/17/18) |

---

## 6. New Core Components (Implemented in `Core/` but not in `packages/` or INDEX.md)

| Component | Path | Namespace | Tests | Composer.json | Dependencies | Complexity | Status |
|-----------|------|-----------|-------|---------------|--------------|------------|--------|
| **Core View / Template Engine** | `Core/View/` | `DGLab\Core\View` | Yes (`Core/View/tests/`) | No (referenced in root composer.json as `sovereign-stack/core-view`) | php ^8.4, psr/http-message ^2.0, sovereign-stack/core-http-message, twig/twig ^3.0 | **Medium** (driver-based: NeoSPX, Twig; ViewFactory, TemplateDriverInterface) | 🔄 **In Progress** (implemented in `Core/View/`, namespace `DGLab\Core\View`) |
| **Core NeoSpx Lexer** | `Core/NeoSpx/Lexer/` | `DGLab\Core\NeoSpx\Lexer` | Yes (`Core/NeoSpx/tests/Unit/`) | No | php ^8.4, ext-mbstring, ext-pcre | **Medium** | 🔄 **In Progress** |
| **Core Filesystem** | `Core/Filesystem/` | `DGLab\Core\Filesystem` | Yes (`Core/Filesystem/tests/`) | No | php ^8.4, psr/log ^3.0 | **Medium** | 🔄 **In Progress** |
| **Core Crypto** | `Core/Crypto/` | `DGLab\Core\Crypto` | Yes (`Core/Crypto/tests/`) | No | php ^8.4, ext-openssl, ext-sodium, psr/log ^3.0 | **High** | 🔄 **In Progress** |
| **Core Config** | `Core/Config/` | `DGLab\Core\Config` | Yes (`Core/Config/tests/`) | No | php ^8.4, ext-json, ext-hash, psr/container ^2.0, sovereign-stack/core-container | **Medium** | 🔄 **In Progress** |

---

## 7. Migration Discrepancies & Observations

| Issue | Details | Severity |
|-------|---------|----------|
| **CORE-03 Namespace Change** | EventDispatcher moved from `packages/core/event-dispatcher/` (namespace `SovereignStack\Core\EventDispatcher`) to `Core/EventDispatcher/` (namespace `DGLab\Core\EventDispatcher`). Root `composer.json` autoload maps both. | **High** — Breaking namespace change; consumers must update imports. |
| **CORE-10 Namespace Change** | Config moved from `packages/core/config/` (namespace `SovereignStack\Core\Config`) to `Core/Config/` (namespace `DGLab\Core\Config`). Root `composer.json` maps both. | **High** — Breaking namespace change. |
| **CORE-19 Status Mismatch** | INDEX.md §2.1 says CORE-19 "Not started" but `packages/core/dbal/` has full implementation with tests, composer.json, and is required by root composer.json. | **Medium** — INDEX.md stale; DBAL is implemented. |
| **CORE-07/14/16 Implemented Outside INDEX** | NeoSPX Lexer (CORE-07), Filesystem (CORE-14), Crypto (CORE-16) are implemented in `Core/` but INDEX.md lists them as "Not started". | **Medium** — INDEX.md outdated; these are new implementations not in `packages/`. |
| **Core/View Not in INDEX** | `Core/View/` (template engine with NeoSPX/Twig drivers) exists but has no CORE-ID in INDEX.md. Root composer.json references `sovereign-stack/core-view`. | **Medium** — Undocumented component; needs ID assignment. |
| **Missing Core Packages** | `core-event-dispatcher`, `core-crypto`, `core-view` referenced in root composer.json and hub-identity but not all have `packages/` equivalents. | **High** — Dependency resolution may fail for fresh installs. |
| **Hub/Identity Repositories** | hub-identity composer.json uses wildcard repositories (`packages/*/*`, `packages/*/*/*`) which may not resolve new `Core/` paths. | **Medium** — Migration may break Hub tier dependency resolution. |
| **No Hub/Bridge/Spoke Migration Yet** | Only Core tier has new top-level directories. Hub, Bridge, Spoke tiers still only exist in `packages/`. | **Low** — Expected per phased migration. |

---

## 8. Complexity Assessment Methodology

| Level | Criteria |
|-------|----------|
| **Low** | Pure PHP, no external deps, no DB, no runtime state, <5 classes, unit tests only |
| **Medium** | PSR interfaces, 1-2 internal deps, file I/O or config, unit + integration tests, <20 classes |
| **High** | Multiple internal deps, DB migrations, external services (Redis, MySQL), crypto, runtime lifecycle, middleware pipeline, nuclear-grade doctrine compliance, chaos tests, >20 classes |

---

## 9. Recommended Next Steps

1. **Complete Core Tier Migration**
   - Finalize `Core/EventDispatcher/`, `Core/Config/` namespace migration
   - Add composer.json to new `Core/` components (Config, Crypto, Filesystem, NeoSpx, View)
   - Update root `composer.json` to use new paths exclusively
   - Resolve CORE-19 status in INDEX.md

2. **Assign CORE-ID to Core/View** — Template engine needs canonical ID (CORE-21?)

3. **Begin Hub Tier Migration** — Create `Hub/` top-level directory, migrate `packages/hub/config/` and `packages/hub/identity/`

4. **Update Dependency Resolution** — Fix wildcard repository paths in hub-identity and spoke composer.json files to include new `Core/` paths

5. **Run Full Test Suite** — Verify all components pass after namespace changes (`phpunit`, `phpstan`)

6. **Update INDEX.md** — Reflect actual implementation status (CORE-07, 14, 16, 19 implemented; CORE-03/10 namespace changes)

---

## 10. Verification Commands

```bash
# Check current git status (staged migration)
git status

# Run tests for migrated Core components
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/EventDispatcher/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/Config/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/NeoSpx/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/Filesystem/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/Crypto/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit Core/View/tests/

# Run tests for legacy packages
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/core/container/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/core/dbal/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/core/kernel/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/hub/config/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/hub/identity/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/bridge/vanguard/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/spoke/internal/codex/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/spoke/internal/showcase/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/spoke/internal/lms/tests/
cd /home/dgci/www/DGLab && php vendor/bin/phpunit packages/spoke/external/canvas/tests/

# Static analysis
cd /home/dgci/www/DGLab && php vendor/bin/phpstan analyse
```