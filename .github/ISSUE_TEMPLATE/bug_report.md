# DGLab Issue Templates

## Bug Report

When filing a bug, include:

1. **Component:** which package (e.g., `core/http-message`, `anvil/install.sh`)
2. **PHP version:** `php -v`
3. **OS:** `uname -a`
4. **Steps to reproduce:** minimal code or commands
5. **Expected behavior:** what should happen
6. **Actual behavior:** what actually happens (include error messages, stack traces)
7. **Related:** PR number, ADR number, or blueprint ID if applicable

## Feature Request

When proposing a feature:

1. **Component:** which package or tier
2. **Use case:** what problem does this solve?
3. **Proposed solution:** high-level description
4. **Alternatives considered:** what else did you look at?
5. **Breaking changes:** does this affect any frozen interface?

Feature requests that affect architecture should be filed as an Open Decision in `Architecture/OPEN-DECISIONS.md` before implementation.
