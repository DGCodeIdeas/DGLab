# DGLab — Sovereign Stack

A from-scratch PHP 8.3 application framework and monorepo, built for full control over every layer of the stack — from the DI container to the HTTP pipeline to the template engine to the deployment infrastructure.

## What is this?

DGLab is a personal scaffold: a complete, opinionated application stack that eliminates "starting from scratch" for new projects. It is **not** a product seeking adoption — it is a developer's toolkit, built by one developer, for that developer's use.

The repository contains:

- **5 Core-tier packages** (PSR-7, PSR-15, PSR-11, PSR-14, attribute router) — the foundational infrastructure
- **18 Architecture Decision Records** — every major decision documented with alternatives and trade-offs
- **105 component blueprints** — full implementation specs for Core, Hub, Bridge, Spoke, and Deploy tiers
- **Anvil** — a three-tier deployment stack (Caddy + Tengine + FrankenPHP) with automated provisioning
- **Loom** — a custom SemVer automation tool that drives the monorepo release flow end-to-end

## Architecture

```
                    ┌─────────────────────────────────┐
                    │          Outer Rim (UI)          │
                    └──────────────┬──────────────────┘
                                   │
                    ┌──────────────┴──────────────────┐
                    │       Outer Spokes (Adapters)    │
                    └──────────────┬──────────────────┘
                                   │
                    ┌──────────────┴──────────────────┐
                    │         Inner Rim (API)          │
                    └──────────────┬──────────────────┘
                                   │
                    ┌──────────────┴──────────────────┐
                    │      Inner Spokes (Services)     │
                    └──────────────┬──────────────────┘
                                   │
                    ┌──────────────┴──────────────────┐
                    │          Hub (Aggregates)         │
                    └──────────────┬──────────────────┘
                                   │
                    ┌──────────────┴──────────────────┐
                    │          Core (Domain)            │
                    └─────────────────────────────────┘
```

DGLab follows a **Wheel architecture** with 6 concentric rings. A request enters at the Outer Rim and traverses inward to the Core, then returns outward with a response. Each ring is a dependency layer — nothing in an outer ring is imported by an inner ring.

## Repository structure

```
DGLab/
├── packages/              # Composer packages (the framework)
│   └── core/
│       ├── container/         # CORE-02: PSR-11 DI Container (v1.0.0)
│       ├── event-dispatcher/  # CORE-03: PSR-14 Event Dispatcher (v1.0.0)
│       ├── http-message/      # CORE-04: PSR-7 HTTP Message + PSR-17 Factory (v1.0.0)
│       ├── middleware/        # CORE-05: PSR-15 Middleware Pipeline (v1.0.0)
│       └── router/            # CORE-06: Attribute-Based Router (v1.0.0)
├── orchestrator/          # CORE-01: Loom — SemVer automation tool
├── anvil/                 # Deployment stack (Caddy + Tengine + FrankenPHP)
├── Architecture/          # Blueprints, ADRs, governance docs
│   ├── ADRs/                  # 18 Architecture Decision Records
│   ├── Core/                  # 20 Core-tier blueprints
│   ├── Hub/                   # 31 Hub-tier blueprints
│   ├── Spoke/                 # 45 Spoke-tier blueprints (internal + external)
│   ├── Deploy/                # 5 Deploy-tier blueprints
│   └── CrossCutting/          # SDLC, MEMORY, WORKLOG, PROMPTS, etc.
├── .github/workflows/     # CI: packages-ci.yml, release.yml, pr-title-lint.yml
└── app/                   # Application tier (consumer of the framework)
```

## Key design decisions

| ADR | Decision | Why |
|-----|----------|-----|
| ADR-001 | Monorepo (not polyrepo) | Single source of truth, atomic cross-package changes |
| ADR-005 | Build SuperPHP (not Blade/Twig) | Full control over the template engine |
| ADR-014 | SDLC-AGRD: Spiral Deepening | Solo-tech-lead methodology with calibration |
| ADR-017 | Fiber-based cooperative runtime | FrankenPHP workers with per-Fiber scoping |
| ADR-018 | Centralized per-tier releases | All packages in a tier share one version |

See [`Architecture/ADRs/`](Architecture/ADRs/) for the full list.

## Getting started

### Prerequisites

- PHP 8.3+
- Composer 2.x
- Docker Engine + Compose plugin (for dev stack)
- `ext-mbstring`, `ext-fileinfo`, `ext-pcre`

### Install the dev stack

```bash
cd anvil
sudo ./install.sh              # interactive menu
# or
sudo ./install.sh --bootstrap  # dev stack only (Docker, dnsmasq, mkcert, sass)
sudo ./install.sh --trio       # production trio (Caddy + Tengine + FrankenPHP)
```

### Use a package

```bash
composer require sovereign-stack/core-http-message:^1.0
```

### Run the test suite

```bash
cd packages/core/http-message
composer install
vendor/bin/phpunit --testdox
vendor/bin/phpstan analyse
```

## Development methodology

DGLab uses **Spiral Deepening** (SDLC-AGRD v3.4): a solo-tech-lead methodology where each component is built at increasing depth across laps, with 2-week cooldowns for reconciliation. Components are frozen at their first implementation (depth 1–2) and deepened in later laps without breaking the frozen interface.

See [`Architecture/CrossCutting/SDLC-AGRD.md`](Architecture/CrossCutting/SDLC-AGRD.md) for the full methodology.

## Releasing

DGLab uses a **centralized per-tier release model** (ADR-018):

- All packages within a tier share a single SemVer version (`core-v1.0.0`, `hub-v0.1.0`)
- The Loom (`orchestrator/bin/loom`) analyzes path-scoped commits and computes the bump
- Tags are created automatically by `release.yml` when `LOOM_RELEASE_ENABLED=1`
- Monorepo releases (`release-<SemVer>`) track deployment state

## Built with

| Tool | Purpose |
|------|---------|
| PHP 8.3 | Runtime + all packages |
| FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017) |
| Caddy 2.11 | Edge server (TLS, HTTP/3, reverse proxy) |
| Tengine 3.2 | Internal LB (dynamic upstream, health checks) |
| Composer 2.x | Dependency management |
| PHPUnit 10.5 | Testing |
| PHPStan 2.x | Static analysis (level 8) |
| Dart Sass | Asset compilation |

## License

MIT — see [LICENSE](LICENSE).

## Project status

**Active development.** Milestone 0 (the walking skeleton) is in progress:

- ✅ CORE-02 (DI Container)
- ✅ CORE-03 (Event Dispatcher)
- ✅ CORE-04 (HTTP Message)
- ✅ CORE-05 (Middleware)
- ✅ CORE-06 (Router)
- ⬜ CORE-08 (Error Handler)
- ⬜ CORE-09 (Logging)
- ⬜ CORE-10 (Config)
- ⬜ CORE-18 (Kernel)
- ⬜ HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01

See [`Architecture/CrossCutting/WORKLOG.md`](Architecture/CrossCutting/WORKLOG.md) for the full execution log.
