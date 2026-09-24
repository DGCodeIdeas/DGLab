# ARCHITECTURE BASELINE — M0 (Protected Baseline)

**Purpose:** Machine-generated, reproducible baseline of the DGLab repository per SPEC-001 §39.

**Generated:** 2026-09-24T11:36:37.367010+00:00

---

## Repository State

| Field | Value |
|---|---|
| Commit (full) | `a4a34027cbc8ef7ec98f3aef4810d9e81433c0e3` |
| Commit (short) | `a4a3402` |
| Branch | `main` |
| Git describe | `v1.2.4.0+45757ac-21-ga4a3402` |
| Generated at | 2026-09-24T11:36:37.367010+00:00 (1790249797) |

## PHP Runtime & Tooling

| Field | Value |
|---|---|
| PHP constraint (composer.json) | `^8.4` |
| PHP runtime (generator) | `Python baseline generator (PHP runtime version not queried)` |
| PHPUnit (root) | `^11.0` |
| PHPStan (root) | `^2.2` |

## Package Distribution (per tier)

| Tier | Path | Package count |
|---|---|---|
| core | `packages/core` | 11 |
| hub | `packages/hub` | 1 |
| spoke_internal | `packages/spoke/internal` | 1 |
| spoke_external | `packages/spoke/external` | 1 |
| bridge | `packages/bridge` | 1 |
| **TOTAL** | | **15** |

Packages with `tests/` directory: **15 / 15**

<details><summary>Package-by-package detail (click to expand)</summary>

### core

| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |
|---|---|---|---|---|---|
| `packages/core/config` | ✅ | ✅ | ✅ | 10 | 8 |
| `packages/core/container` | ✅ | ✅ | ✅ | 7 | 17 |
| `packages/core/crypto` | ✅ | ✅ | ✅ | 8 | 4 |
| `packages/core/dbal` | ✅ | ✅ | ✅ | 11 | 4 |
| `packages/core/error-handler` | ✅ | ✅ | ✅ | 5 | 2 |
| `packages/core/event-dispatcher` | ✅ | ✅ | ✅ | 7 | 7 |
| `packages/core/http-message` | ✅ | ✅ | ✅ | 14 | 11 |
| `packages/core/kernel` | ✅ | ✅ | ✅ | 12 | 4 |
| `packages/core/logger` | ✅ | ✅ | ✅ | 9 | 8 |
| `packages/core/middleware` | ✅ | ✅ | ✅ | 11 | 7 |
| `packages/core/router` | ✅ | ✅ | ✅ | 13 | 7 |

### hub

| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |
|---|---|---|---|---|---|
| `packages/hub/config` | ✅ | ✅ | ✅ | 13 | 5 |

### spoke_internal

| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |
|---|---|---|---|---|---|
| `packages/spoke/internal/codex` | ✅ | ✅ | ✅ | 3 | 1 |

### spoke_external

| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |
|---|---|---|---|---|---|
| `packages/spoke/external/canvas` | ✅ | ✅ | ✅ | 5 | 1 |

### bridge

| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |
|---|---|---|---|---|---|
| `packages/bridge/vanguard` | ✅ | ✅ | ✅ | 6 | 4 |

</details>

## Blueprint Counts (per tier)

| Tier | Path | Count |
|---|---|---|
| Core | `archive/Arc/Blueprints/Core` | 20 |
| Hub | `archive/Arc/Blueprints/Hub` | 30 |
| Spoke_Internal | `archive/Arc/Blueprints/Spoke/Internal` | 15 |
| Spoke_External | `archive/Arc/Blueprints/Spoke/External` | 15 |
| Spoke_Bridge | `archive/Arc/Blueprints/Spoke/Bridge` | 1 |
| Deploy | `archive/Arc/Blueprints/Deploy` | 1 |
| **TOTAL** | | **82** |

## Architecture Decision Records

Count: **20** at `Architecture/ADRs`

Files:

<details><summary>ADR file list (click to expand)</summary>

```
ADR-001-polyrepo-vs-monorepo.md
ADR-002-psr11-container-scope.md
ADR-003-es256-jwt-signing.md
ADR-004-tier-enforcement-dag.md
ADR-005-superphp-vs-blade-twig.md
ADR-006-redis-over-memcached.md
ADR-007-postgresql-over-mysql.md
ADR-008-argon2id-password-hashing.md
ADR-009-ulid-over-uuid.md
ADR-010-opcache-preload-strategy.md
ADR-011-hub-31-real-time-analytics.md
ADR-012-post-quantum-jwt-agility.md
ADR-013-mysql-primary-datastore.md
ADR-014-ratify-agrd-canonical-sdlc.md
ADR-015-hospitality-vertical-promotion.md
ADR-016-library-app-boundary-split.md
ADR-017-fiber-based-cooperative-runtime.md
ADR-018-centralized-per-tier-releases.md
ADR-019-pre-muwv-version-scheme.md
ADR-020-stable-and-bleeding-edge-release-channels.md
```

</details>

## Test Suites

Packages with `tests/` directory: **15**

<details><summary>Test suite paths (click to expand)</summary>

```
packages/core/config
packages/core/container
packages/core/crypto
packages/core/dbal
packages/core/error-handler
packages/core/event-dispatcher
packages/core/http-message
packages/core/kernel
packages/core/logger
packages/core/middleware
packages/core/router
packages/hub/config
packages/spoke/internal/codex
packages/spoke/external/canvas
packages/bridge/vanguard
```

</details>

## Frozen Contracts

Source: `Architecture/FROZEN-CONTRACTS.md`

| Tier | Count |
|---|---|
| CORE | 9 |
| HUB | 1 |
| ISPOKE | 1 |
| ESPOKE | 1 |
| BRIDGE | 1 |
| DEPLOY | 0 |
| **TOTAL** | **13** |

## Worker Recycling Configuration (Production)

Source: `anvil/app/Caddyfile.blue` + `anvil/systemd/anvil-frankenphp@.service`

| Parameter | Value |
|---|---|
| `max_requests` | `500` |
| `memory_limit` | `256M` |
| `Restart` | `on-failure` |
| `RestartSec` | `2s` |
| `TimeoutStopSec` | `30s` |
| `KillSignal` | `SIGTERM` |

## Reproducibility

This baseline is reproducible from a clean checkout by running:
```
python3 /home/z/my-project/scripts/generate-architecture-baseline.py
# OR (when PHP 8.4 is installed in the contractor env):
php /home/z/my-project/scripts/generate-architecture-baseline.php
```

The generator walks repository state directly — no manual data entry. Per SPEC-001 §37 governance principle: *"Executable repository state is authoritative wherever implementation status can be determined automatically."*

---

*Generated by `scripts/generate-architecture-baseline.py` (Python equivalent of `generate-architecture-baseline.php`) per SPEC-001 §39 Phase 0.*
