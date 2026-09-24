# DGLab — Sovereign Stack

A from-scratch PHP 8.3 application framework and monorepo, built for full control over every layer of the stack — from the DI container to the HTTP pipeline to the template engine to the deployment infrastructure.

## What is this?

DGLab is a personal scaffold: a complete, opinionated application stack that eliminates "starting from scratch" for new projects. It is **not** a product seeking adoption — it is a developer's toolkit, built by one developer, for that developer's use.

The repository contains:

- **8 Core-tier packages** (PSR-7, PSR-15, PSR-11, PSR-14, attribute router, PSR-3 logging, config, error handler) — the foundational infrastructure
- **19 Architecture Decision Records** — every major decision documented with alternatives and trade-offs
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
│       ├── container/         # CORE-02: PSR-11 DI Container
│       ├── event-dispatcher/  # CORE-03: PSR-14 Event Dispatcher
│       ├── http-message/      # CORE-04: PSR-7 HTTP Message + PSR-17 Factory
│       ├── middleware/        # CORE-05: PSR-15 Middleware Pipeline
│       ├── router/            # CORE-06: Attribute-Based Router
│       ├── config/            # CORE-10: Configuration & Environment Loader
│       ├── logger/            # CORE-09: PSR-3 Structured Logging Service
│       └── error-handler/     # CORE-08: Global Error & Exception Handler
├── orchestrator/          # CORE-01: Loom — SemVer automation tool
├── anvil/                 # Deployment stack (Caddy + Tengine + FrankenPHP)
├── Architecture/          # Blueprints, ADRs, governance docs
│   ├── ADRs/                  # 19 Architecture Decision Records
│   ├── Core/                  # 20 Core-tier blueprints
│   ├── Hub/                   # 31 Hub-tier blueprints
│   ├── Spoke/                 # 45 Spoke-tier blueprints (internal + external)
│   ├── Deploy/                # 5 Deploy-tier blueprints
│   └── CrossCutting/          # SDLC, MEMORY, WORKLOG, PROMPTS, etc.
├── .github/workflows/     # CI: packages-ci.yml, release.yml, pr-title-lint.yml
└── app/                   # Application tier (consumer of the framework)
```

## Development methodology — SDLC-AGRD

DGLab uses **SDLC-AGRD v3.4(3): Spiral Deepening** (ratified as [ADR-014](Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md)) — a solo-tech-lead methodology designed for sustained, calibrated development without burnout.

### Core concepts

- **Spiral Deepening:** components are built at increasing depth across laps, not all at once. A component starts as a stub (depth 1), gains a happy path (depth 2), error paths (depth 3), observability (depth 4), production hardening (depth 5), and at-scale verification (depth 6).
- **Interface freeze (§2.1):** a blueprint's public contract freezes the first time it's implemented at any depth. Changing a frozen interface is an ADR-gated event.
- **Laps:** a lap is one pass through the build order (Core → Hub → Bridge → Spoke → Deploy). Each lap deepens existing components and adds new ones. Lap count is tracked in the version number.
- **Milestones:** Milestone 0 = walking skeleton (CORE-18 Kernel wiring the full request pipeline). Milestone 1+ = feature expansion.
- **Cooldowns (§7):** 2-week between-lap cooldowns for worklog reconciliation, OD triage, refactor backlog, and recovery. Interim: **mini cooldowns** (OD-11) — ~1-day checkpoints between Steps within a lap.

### Build order (INDEX.md §5)

The build order defines which components depend on which:

| Step | Components | Status |
|------|------------|--------|
| 1 | CORE-02 (DI), CORE-03 (Events), CORE-04 (HTTP), CORE-05 (Middleware), CORE-06 (Router) | ✅ Complete |
| 2 | CORE-10 (Config), CORE-09 (Logger), CORE-08 (Error Handler) | ✅ Complete |
| 3 | CORE-18 (Kernel) | ✅ Complete — **5 of 8 Milestone 0 blueprints shipped** |
| 4 | HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01 | ✅ Complete — **all 8 Milestone 0 blueprints shipped** |

See [`Architecture/CrossCutting/SDLC-AGRD.md`](Architecture/CrossCutting/SDLC-AGRD.md) for the full methodology and [`Architecture/INDEX.md`](Architecture/INDEX.md) §5 for the complete build order.

> **Nuclear-grade doctrine (binding on Core tier).** Every Core-tier package is built under [`Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md): 12 principles, 5-class error taxonomy (Transient / Permanent-External / Permanent-Local / Corrupt / Panic), hard resource ceilings, circuit breakers, hash-chained audit, chaos tests, and a §9 merge gate. Step 5 packages (CORE-19 DBAL, CORE-15 Cache, CORE-14 Filesystem, CORE-16 Encryption) have per-package application in doctrine §4.1–§4.4 (binding since 2026-09-20). CORE-18 Kernel has per-package application in doctrine §4.5 (binding 2026-09-23 — pilot for the broader Core-tier scope); ALL 6 items implemented: §4.5.2 re-entrancy tests (PR #246), §4.5.3 bootstrapper circuit breaker + `BootstrapperTimeoutExceeded` (PR #249), §4.5.4 `PanicException` class + 4 invariant-violation throw-points (PR #251), §4.5.5 resource ceilings — 30s boot/30s handle/5s terminate/32 bootstrapper cap (PR #253), §4.5.6 `KernelLifecycleRecord` audit feed — 7 lifecycle points + `getLifecycleRecords()` (PR #256), §4.5.7 8 chaos tests — all scenarios covered (PR #257). The §4.5 CORE-18 Kernel pilot is **fully implemented**. Remaining Core packages (CORE-01/02/03/04/05/06/07/08/09/10/17/20) are bound in principle under §1–§3 and §5–§11 immediately; per-package application sections §4.6 onward land incrementally. Where the doctrine and a per-package blueprint disagree, the doctrine wins.

## Versioning

DGLab uses a **four-segment version scheme** ([ADR-019](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md)):

```
v<MUWV>.<Milestone>.<Lap>.<Patch>+<git-sha>
```

| Segment | Meaning |
|---------|---------|
| `MUWV` | `1` = post-MUWV (walking skeleton complete — flipped 2026-09-18) |
| `Milestone` | Milestone number + 1 (Milestone 0 = `1`, Milestone 1 = `2`) |
| `Lap` | Lap within the milestone (resets at each milestone) |
| `Patch` | Patch within the lap (0 = first release of the lap) |
| `+sha` | 7-char git short SHA (build metadata, ignored for precedence) |

**Current version:** `v1.2.0.0+<sha>` — **post-MUWV**, Milestone 1 (segment 2), lap 0, patch 0. The MUWV flip was authorized on 2026-09-18 after all 8 Milestone 0 blueprints shipped and the full Pulse trace was verified end-to-end. See [ADR-019 §8 Flip Log](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md#8-muwv-flip-criterion-corrected) for details.

**Pre-MUWV history:** `v0.1.0.0` → `v0.1.1.0` → ... → `v0.1.35.0` (last pre-MUWV release). All `v0.*` releases remain marked as prerelease on GitHub.

**Tag formats:**
- Monorepo releases: `v1.2.0.0+abc1234` (regular release)
- Per-tier releases: `core-v1.2.0.0+abc1234` (per [ADR-018](Architecture/ADRs/ADR-018-centralized-per-tier-releases.md))

**Deprecated tags** (historical, not retagged — see [`Architecture/DEPRECATED_TAGS.md`](Architecture/DEPRECATED_TAGS.md) for the full register, migration guide, and CI enforcement details): `v1.0.0`, `release-1.0.0`/`1.1.0`/`1.2.0`/`1.3.0`, `core-v1.0.0`, per-package `core-*-v1.0.0`. A CI lint check in `architecture-lint.yml` flags any new reference to these deprecated patterns.

## Key design decisions

| ADR | Decision | Why |
|-----|----------|-----|
| ADR-001 | Monorepo (not polyrepo) | Single source of truth, atomic cross-package changes |
| ADR-005 | Build SuperPHP (not Blade/Twig) | Full control over the template engine |
| ADR-014 | SDLC-AGRD: Spiral Deepening | Solo-tech-lead methodology with calibration |
| ADR-017 | Fiber-based cooperative runtime | FrankenPHP workers with per-Fiber scoping |
| ADR-018 | Centralized per-tier releases | All packages in a tier share one version |
| ADR-019 | Pre-MUWV version scheme (v0.X.Y.Z) | Honest versioning before walking skeleton is complete |

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
composer require sovereign-stack/core-http-message:^0.1
```

### Run the test suite

```bash
cd packages/core/http-message
composer install
vendor/bin/phpunit --testdox
vendor/bin/phpstan analyse
```

## Releasing

DGLab uses a **centralized per-tier release model** (ADR-018) with the four-segment version scheme (ADR-019):

- All packages within a tier share a single version (`core-v0.1.3.0+abc1234`)
- The Loom (`orchestrator/bin/loom`) analyzes path-scoped commits and computes the bump
- Tags are created automatically by `release.yml` when `LOOM_RELEASE_ENABLED=1`
- Monorepo releases (`v0.1.3.0+abc1234`) track deployment state

## Built with

| Tool | Purpose |
|------|---------|
| PHP 8.3 | Runtime + all packages |
| FrankenPHP 1.12 | App server (Fiber-based worker mode per ADR-017) |
| Caddy 2.11 | Edge server (TLS, HTTP/3, reverse proxy) |
| Tengine 3.2 | Internal LB (dynamic upstream, health checks) |
| Composer 2.x | Dependency management |
| PHPUnit 10.5 | Testing |
| PHPStan 2.x | Static analysis (level max) |
| Dart Sass | Asset compilation |

## License

MIT — see [LICENSE](LICENSE).

## Project status

**Active development. Post-MUWV (stable).** All 8 Milestone 0 blueprints shipped + the full Pulse trace verified end-to-end (real HTTP request through Caddy → Tengine → FrankenPHP → Kernel → Router → Controller → 200). MUWV flipped to `1` on 2026-09-18 per ADR-019 §8. Current work: doctrine §4.5 follow-up items 4-6 on CORE-18 Kernel (§4.5.5 resource ceilings, §4.5.6 `KernelLifecycleRecord` audit feed, §4.5.7 8 chaos tests), then Step 5 Core persistence packages (CORE-19 DBAL, CORE-15 Cache, CORE-14 Filesystem, CORE-16 Encryption) at nuclear-grade depth 2. The release workflow is fully functional end-to-end — first real `core-v*` tag (`core-v0.2.0.0+684eaed`) created 2026-09-23 after a 4-PR silent no-op fix series.

### Milestone 0 components (8 blueprints required for MUWV)

| Component | Status | PR |
|-----------|--------|-----|
| CORE-02 (DI Container) | ✅ Depth 2 | — |
| CORE-04 (HTTP Message) | ✅ Depth 2 | #127 |
| CORE-05 (Middleware) | ✅ Depth 2 | #140 |
| CORE-06 (Router) | ✅ Depth 2 | #141 |
| CORE-18 (Kernel) | ✅ Depth 2 | #156 |
| HUB-01 (Hub Config & Flags) | ⬜ Not started | — |
| BRIDGE-01 (Vanguard) | ⬜ Not started | — |
| ISPOKE-09 (Codex) | ⬜ Not started | — |
| ESPOKE-01 (Canvas) | ⬜ Not started | — |

Also shipped but not in the Milestone 0 scope: CORE-03 (Event Dispatcher), CORE-10 (Config), CORE-09 (Logger), CORE-08 (Error Handler), CORE-17 (Service Providers, stub).

**MUWV criterion (per AGRD §4 + ADR-019 §8):** all 8 blueprints above at depth 1–2 AND a real HTTP request through the full Pulse trace (Outer Rim → Inner Rim → Inner Spoke → return). The integration test `KernelHelloWorldIntegrationTest::testHelloWorldRoundTrip` proves the architectural round-trip works, but `public/index.php` is still a 503 placeholder — the flip is NOT yet authorized.

### Premature flip (2026-09-12) — reverted

The MUWV segment was flipped to `1` prematurely on 2026-09-12 based on the integration test passing. The AGRD §4 criterion requires all 8 blueprints + a real HTTP request through the full Rim. The premature `v1.2.0.0+b4ed694` tag and GitHub release have been deleted; ADR-019 §8 documents the corrected criterion. See [`Architecture/CrossCutting/WORKLOG.md`](Architecture/CrossCutting/WORKLOG.md) Task 27 for the full reversion log.
