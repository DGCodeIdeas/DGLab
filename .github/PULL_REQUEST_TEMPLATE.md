---
name: Pull Request Template
about: Template for all PRs to DGLab
---

## What

Brief description of what this PR changes.

## Why

The motivation — what problem does this solve, what gap does it close?

## How

Key implementation details. What files changed and why.

## Verification

- [ ] `bash -n` passes (if shell scripts changed)
- [ ] `vendor/bin/phpunit --testdox --no-coverage` passes (if PHP changed)
- [ ] `vendor/bin/phpstan analyse` passes (if PHP changed)
- [ ] Architecture Lint passes (if Architecture/ changed)
- [ ] PR title matches Conventional-Commit format

## Related

- Closes #
- References ADR-
- References OD-
- References blueprint CORE-/HUB-/ISPOKE-/ESPOKE-/BRIDGE-/DEPLOY-
