---
name: Pull Request Template
about: Template for all PRs to DGLab
---

## What

Brief description of what this PR changes.

## Why

The motivation — what problem does this solve, what gap does it close?

## AGRD classification

<!-- Required for all PRs. See CONTRIBUTING.md and Architecture/CrossCutting/SDLC-AGRD.md. -->

- **Build order Step:** <!-- 1, 2, 3, 4, or "N/A (governance/docs/CI)" -->
- **Component ID:** <!-- e.g., CORE-10, HUB-01, or "N/A" -->
- **Depth:** <!-- 1 (stub), 2 (happy path), 3 (error paths), 4 (observability), 5 (hardening), 6 (at-scale), or "N/A" -->
- **Lap:** <!-- which lap within the current milestone, or "N/A" -->
- **Mini cooldown taken since last PR?** <!-- Yes / No / N/A — see OD-11 -->
- **Interfaces frozen per §2.1?** <!-- Yes (list which), No (not applicable), or N/A -->

## How

Key implementation details. What files changed and why.

## Verification

- [ ] `bash -n` passes (if shell scripts changed)
- [ ] `vendor/bin/phpunit --testdox --no-coverage` passes (if PHP changed)
- [ ] `vendor/bin/phpstan analyse` passes (if PHP changed)
- [ ] Architecture Lint passes (if Architecture/ changed)
- [ ] PR title matches Conventional-Commit format
- [ ] WORKLOG entry appended at implementation time (not retroactively)

## Related

- Closes #
- References ADR-
- References OD-
- References blueprint CORE-/HUB-/ISPOKE-/ESPOKE-/BRIDGE-/DEPLOY-
