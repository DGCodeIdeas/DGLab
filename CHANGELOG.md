# Changelog

All notable changes to DGLab are documented here. Per ADR-018, releases follow a centralized per-tier model. This changelog tracks monorepo releases (`release-<SemVer>`).

## [Unreleased]

### Added
- ADR-018: Centralized per-tier release model
- ADR-017: Fiber-based cooperative runtime (FrankenPHP worker mode)
- OD-09: DGLab SCSS framework design space (deferred, Tailwind interim)
- OD-10: Kernel-level driver architecture for multi-runtime spoke support
- Release workflow: monorepo-release job for `release-<SemVer>` tags
- Release workflow: per-tier matrix (was per-package)
- Release workflow: composer cache, token hygiene, debug output
- PR title lint workflow: Conventional-Commit enforcement (P1 gap 5)
- Branch protection on `main`: required status checks + PR review
- `LOOM_RELEASE_PAT` secret and `LOOM_RELEASE_ENABLED=1` variable configured

### Changed
- Tag naming convention: `<tier>-<short>-v<X.Y.Z>` → `<tier>-v<X.Y.Z>` (ADR-018)
- Release matrix: 5 per-package entries → 1 per-tier entry
- `anvil/install.sh`: 8 bug fixes (arg parser, root check, TTY, --env=, --yes)
- `anvil/lib/install-dev.sh`: 7 bug fixes (config path, grep portability, --env handling)
- `anvil/lib/install-trio.sh`: 8 bug fixes (Caddy URL, /opt/anvil/lib, --env, sysctl, auto-build Tengine)
- `anvil/lib/tengine-build.sh`: GCC 15 fix, GitHub 3.2.0-rc5 URL, zlib check fix, pv progress bars
- `anvil/migrate.sh`: 7 bug fixes (dry-run, missing fi, echo→printf, --env, --phase, read -r)
- `anvil/uninstall.sh`: 5 bug fixes (arg parser, root check, TTY, --phase=, read -rp)
- `anvil/lib/deploy.sh`: shift 2 guard on --strategy/--timeout/--release
- `anvil/lib/core.sh`: `anvil_download()` helper with pv-based progress bars
- 37 total anvil script bugs fixed across 13 PRs

## [release-1.0.0] — 2026-09-09

### Core tier (core-v1.0.0)

#### Added
- **CORE-02: PSR-11 DI Container** — autowiring, compiler passes, circular dependency detection, Pulse-scoped bindings (WeakMap<Fiber, array> per ADR-017). 51 tests, 56 assertions, 97.2% line coverage. Tagged `v1.0.0` (PR #107).
- **CORE-03: PSR-14 Event Dispatcher** — prioritized, haltable listener pipeline with lazy resolution. Tagged `core-event-dispatcher-v1.0.0` (PR #108).
- **CORE-04: PSR-7 HTTP Message & PSR-17 Factory** — immutable value objects (Request, Response, ServerRequest, Stream, Uri, UploadedFile) + 6 PSR-17 factories + MessageFactoryInterface aggregate. Header injection prevention (CWE-113/93), resource leak prevention (ADR-017). 268 tests, 378 assertions. Tagged `core-http-message-v1.0.0` (PR #127).
- **CORE-05: PSR-15 Middleware & Request Handler** — cursor-based O(1) pipeline, frozen-after-first-handle immutability, heterogeneous middleware resolution (MiddlewareInterface, callable, class-string). 29 tests, 36 assertions. Tagged `core-middleware-v1.0.0` (PR #140).
- **CORE-06: Attribute-Based Router** — PCRE-compiled route patterns, method-indexed matching, `#[Route]` attribute loading, URL generation. Path traversal prevention, single URL-decode. Tagged `core-router-v1.0.0` (PR #141).
- **CORE-01: Loom (Polyrepo Orchestrator)** — SemVer automation: `version:bump`, `version:release`, `tag:create`, `tag:push`, `status:clean`, `repos:generate`, `ci:monitor`. 11 gaps from the SemVer audit plan closed across PRs #109–#114.
- **Anvil v3 trio** — Caddy + Tengine + FrankenPHP deployment stack with unified install.sh, anvilctl, uninstall.sh, and 22 lib/ scripts.

#### Infrastructure
- CI: `packages-ci.yml` (6-package matrix), `architecture-lint.yml`, `pr-title-lint.yml`, `release.yml` (per-tier + monorepo)
- Branch protection on `main`: architecture-lint, packages-ci, pr-title-lint required
- `LOOM_RELEASE_PAT` secret, `LOOM_RELEASE_ENABLED=1` variable

#### Documentation
- 18 Architecture Decision Records (ADR-001 through ADR-018)
- 105 component blueprints (Core, Hub, Spoke, Bridge, Deploy)
- SDLC-AGRD v3.4(3): Spiral Deepening methodology
- MEMORY.md, MEMORY_INSTRUCTIONS.md, WORKLOG.md (18 tasks logged)
- 2 Open Decisions (OD-09 SCSS framework, OD-10 kernel drivers)
