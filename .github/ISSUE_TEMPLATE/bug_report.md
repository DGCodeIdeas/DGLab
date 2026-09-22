---
name: Bug Report
about: Report a defect in a DGLab package or infrastructure
title: 'fix(<component>): <brief description>'
labels: bug
---

## AGRD classification

<!-- Required. See CONTRIBUTING.md for definitions. -->

- **Type:** bug <!-- bug, regression, security, performance -->
- **Component:** <!-- e.g., core/http-message, anvil/install.sh, orchestrator -->
- **Build order Step:** <!-- which Step was the component built in? 1, 2, 3, 4, or N/A -->
- **Depth at time of bug:** <!-- depth 2 (happy path), 3 (error paths), etc. — bugs at lower depths are expected -->
- **Frozen interface affected?** <!-- Yes (requires ADR) / No -->

## Environment

- **PHP version:** `php -v`
- **OS:** `uname -a`
- **DGLab version:** <!-- e.g., v0.1.3.0+abc1234 — from git tag or composer.lock -->
- **Package version:** <!-- from composer.json `version` field -->

## Steps to reproduce

Minimal code or commands that reproduce the issue:

```bash
# steps here
```

```php
// code here
```

## Expected behavior

What should happen.

## Actual behavior

What actually happens. Include error messages, stack traces, and log output where relevant.

## Related

- PR #
- ADR #
- OD #
- Blueprint ID:
