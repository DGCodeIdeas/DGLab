# Contributing to DGLab

## Overview

DGLab is a personal scaffold built by a solo tech lead. Contributions are welcome but should follow the established methodology and architecture.

## Development methodology

DGLab uses **Spiral Deepening** (SDLC-AGRD v3.4). Read [`Architecture/CrossCutting/SDLC-AGRD.md`](Architecture/CrossCutting/SDLC-AGRD.md) before contributing — it defines the lap structure, depth scale, interface freeze rules, and cooldown process.

Key rules:
- **Interface freeze (§2.1):** a blueprint's public contract freezes the first time it's implemented at any depth. Changing a frozen interface is an ADR-gated event.
- **Depth scale (§4.1):** depth 1 = stub, depth 2 = happy path, depth 3 = error paths, depth 4 = observability, depth 5 = production hardening, depth 6 = at-scale verified.
- **WORKLOG discipline:** every task gets a WORKLOG entry at implementation time, not retroactively.

## Before you start

1. Read [`Architecture/CrossCutting/MEMORY.md`](Architecture/CrossCutting/MEMORY.md) — the entry-point file for every agent/session.
2. Read [`Architecture/CrossCutting/MEMORY_INSTRUCTIONS.md`](Architecture/CrossCutting/MEMORY_INSTRUCTIONS.md) — the operational process companion.
3. Read the relevant blueprint in `Architecture/Core/`, `Architecture/Hub/`, etc.
4. Check [`Architecture/OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) for any open decisions that affect your work.
5. Check [`Architecture/CrossCutting/WORKLOG.md`](Architecture/CrossCutting/WORKLOG.md) for what's been done.

## Code standards

- **PHP 8.3+** with `declare(strict_types=1)`.
- **PHPStan level 8** — zero errors. Run `vendor/bin/phpstan analyse` before pushing.
- **PHPUnit 10.5** — all tests must pass. Run `vendor/bin/phpunit --testdox --no-coverage`.
- **Conventional Commits** — PR titles must match: `^(feat|fix|chore|docs|refactor|test|perf|build|ci|style|revert)(\(.+\))?!?: .+`
- **PSR-12** coding standard (enforced via php-cs-fixer in `ci/run.php`).

## PR process

1. Create a branch: `feat/<component>-<description>` or `fix/<description>`.
2. Write code + tests. Ensure `ci/run.php` passes locally (if PHP is available).
3. Push and open a PR against `main`.
4. CI runs automatically:
   - `Architecture Lint` — validates blueprint references
   - `Packages CI` — PHPUnit + PHPStan per package
   - `PR Title Lint` — validates Conventional-Commit format
5. Branch protection requires all three to pass before merge.
6. Squash-merge to `main`.

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

Releases are automated via the Loom and `release.yml`. See [ADR-018](Architecture/ADRs/ADR-018-centralized-per-tier-releases.md) for the centralized per-tier release model.

- **Per-tier tags:** `core-v1.0.0`, `hub-v0.1.0` — all packages in a tier share one version
- **Monorepo releases:** `release-1.0.0` — deployment snapshots
- The Loom handles version bumping, composer.json sync, tag creation, and GitHub releases automatically

## Architecture decisions

All significant decisions are documented as ADRs in [`Architecture/ADRs/`](Architecture/ADRs/). If your change affects an architectural decision, either:
- Update the existing ADR with a revision note, or
- Create a new ADR that supersedes the old one (cite the old ADR explicitly).

## Questions?

- Check [`Architecture/INDEX.md`](Architecture/INDEX.md) for the master index
- Check [`Architecture/OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) for unresolved questions
- Check [`Architecture/CrossCutting/GLOSSARY.md`](Architecture/CrossCutting/GLOSSARY.md) for terminology
