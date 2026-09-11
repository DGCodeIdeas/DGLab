# Contributing to DGLab

## Overview

DGLab is a personal scaffold built by a solo tech lead. Contributions are welcome but should follow the established methodology and architecture.

## Development methodology — SDLC-AGRD

DGLab uses **SDLC-AGRD v3.4(3): Spiral Deepening** (ratified as [ADR-014](Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md)). Read [`Architecture/CrossCutting/SDLC-AGRD.md`](Architecture/CrossCutting/SDLC-AGRD.md) before contributing — it defines the lap structure, depth scale, interface freeze rules, and cooldown process.

### Core rules

- **Interface freeze (§2.1):** a blueprint's public contract freezes the first time it's implemented at any depth. Changing a frozen interface is an ADR-gated event.
- **Depth scale (§4.1):** depth 1 = stub, depth 2 = happy path, depth 3 = error paths, depth 4 = observability, depth 5 = production hardening, depth 6 = at-scale verified.
- **WORKLOG discipline:** every task gets a WORKLOG entry at implementation time, not retroactively. Append to `Architecture/CrossCutting/WORKLOG.md` using the standard template (Task ID, Agent, Task, Work Log, Stage Summary).
- **Build order (INDEX.md §5):** components are built in dependency order. Don't skip ahead — if a prerequisite isn't shipped, you can't build the dependent.

### Build order Steps

The build order is divided into Steps, each containing components that can be built in parallel within the Step:

| Step | Components | Status |
|------|------------|--------|
| 1 | CORE-02 (DI), CORE-03 (Events), CORE-04 (HTTP), CORE-05 (Middleware), CORE-06 (Router) | ✅ Complete |
| 2 | CORE-10 (Config), CORE-09 (Logger), CORE-08 (Error Handler) | ✅ Complete |
| 3 | CORE-18 (Kernel) | ⬜ In progress |
| 4 | HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01 | ⬜ Pending |

### Laps and cooldowns

- **Laps:** a lap is one pass through the build order. Each lap deepens existing components (toward depth 6) and adds new ones. The current lap number is tracked in the version number (third segment — see [ADR-019](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md)).
- **Between-lap cooldowns (§7):** 2-week cooldowns for worklog reconciliation, OD triage, refactor backlog, and recovery. **Currently deferred until next year** per the solo-tech-lead directive — the project is in sustained-Milestone-0 mode and cannot afford 2-week pauses.
- **Mini cooldowns (OD-11):** interim replacement for the between-lap cooldown. ~1 working day (≤4 hours) between Steps within a lap. Scoped to: (a) worklog reconciliation, (b) interface-freeze audit, (c) just-shipped refactor triage, (d) trivial lint-scope expansion. Do NOT consume OD-triage time; do NOT count toward §7 cooldown total.

### When to take a mini cooldown

Take a mini cooldown:
- Between Steps (e.g., after Step 2 triplet ships, before Step 3 begins)
- Between depth bumps within a Step (e.g., after CORE-18 depth 2, before CORE-18 depth 3)
- After any PR that required 4+ CI iterations (the iterations surface friction worth reconciling)

Do NOT take a mini cooldown:
- Mid-Step (between two parallelisable components within the same Step — keep momentum)
- For trivial fixes (typos, doc updates, single-line bugs)

## Before you start

1. Read [`Architecture/CrossCutting/MEMORY.md`](Architecture/CrossCutting/MEMORY.md) — the entry-point file for every agent/session.
2. Read [`Architecture/CrossCutting/MEMORY_INSTRUCTIONS.md`](Architecture/CrossCutting/MEMORY_INSTRUCTIONS.md) — the operational process companion.
3. Read the relevant blueprint in `Architecture/Core/`, `Architecture/Hub/`, etc.
4. Check [`Architecture/OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) for any open decisions that affect your work.
5. Check [`Architecture/CrossCutting/WORKLOG.md`](Architecture/CrossCutting/WORKLOG.md) for what's been done.

## Code standards

- **PHP 8.3+** with `declare(strict_types=1)`.
- **PHPStan level max** (bleedingEdge) — zero errors. Run `vendor/bin/phpstan analyse` before pushing.
- **PHPUnit 10.5** — all tests must pass. Run `vendor/bin/phpunit --testdox --no-coverage`.
- **Conventional Commits** — PR titles must match: `^(feat|fix|chore|docs|refactor|test|perf|build|ci|style|revert)(\(.+\))?!?: .+`
- **PSR-12** coding standard (enforced via php-cs-fixer in `ci/run.php`).

## PR process

1. Create a branch: `feat/<component>-<description>` or `fix/<description>`.
2. Write code + tests. Ensure `ci/run.php` passes locally (if PHP is available).
3. Push and open a PR against `main`.
4. Fill in the PR template — including the AGRD classification (Step #, mini cooldown Y/N, interfaces frozen Y/N).
5. CI runs automatically:
   - `Architecture Lint` — validates blueprint references
   - `Packages CI` — PHPUnit + PHPStan per package
   - `PR Title Lint` — validates Conventional-Commit format
6. Branch protection requires all three to pass before merge.
7. Squash-merge to `main`.
8. Append a WORKLOG entry at implementation time (not retroactively).

## Package structure

Each package follows the same layout:

```
packages/<tier>/<name>/
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon
├── ci/run.php
├── README.md
├── src/
│   └── Exception/        # if needed
└── tests/
    ├── Unit/
    ├── Integration/
    └── Fixtures/
```

## Release process

Releases are automated via the Loom and `release.yml`. See [ADR-018](Architecture/ADRs/ADR-018-centralized-per-tier-releases.md) for the centralized per-tier release model and [ADR-019](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md) for the pre-MUWV version scheme.

- **Per-tier tags:** `core-v0.1.3.0+abc1234` — all packages in a tier share one version
- **Monorepo releases:** `v0.1.3.0+abc1234` — deployment snapshots
- **Version scheme:** `v<MUWV>.<Milestone>.<Lap>.<Patch>+<git-sha>` (see ADR-019)
- The Loom handles version bumping, composer.json sync, tag creation, and GitHub releases automatically

### Deprecated tag formats

The following tag formats are deprecated (historical only, not retagged — see [`Architecture/DEPRECATED_TAGS.md`](Architecture/DEPRECATED_TAGS.md) for the full register, migration guide, and CI enforcement):
- `v1.0.0`, `release-1.0.0`/`1.1.0`/`1.2.0`/`1.3.0` → replaced by `v0.1.X.0+<sha>`
- `core-v1.0.0` → replaced by `core-v0.1.X.0+<sha>`
- Per-package `core-*-v1.0.0` → already deprecated by ADR-018 §4

**Enforcement:** deprecation is enforced via `composer.json` migration (all packages use `0.1.0.0`), `release.yml` (creates new-format tags only), and PR review. See [`Architecture/DEPRECATED_TAGS.md`](Architecture/DEPRECATED_TAGS.md) for details.

## Architecture decisions

All significant decisions are documented as ADRs in [`Architecture/ADRs/`](Architecture/ADRs/). If your change affects an architectural decision, either:
- Update the existing ADR with a revision note, or
- Create a new ADR that supersedes the old one (cite the old ADR explicitly).

## Questions?

- Check [`Architecture/INDEX.md`](Architecture/INDEX.md) for the master index
- Check [`Architecture/OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) for unresolved questions
- Check [`Architecture/CrossCutting/GLOSSARY.md`](Architecture/CrossCutting/GLOSSARY.md) for terminology
