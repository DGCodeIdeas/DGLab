# Phase 10: Query Builder Interception

## Overview
Make searching encrypted data transparent to the application logic.

## Detailed Tasks
- Intercept `QueryBuilder` where/find calls on encrypted columns.
- Automatically redirect queries to `_bidx` companion columns.
- Transform lookup values into blind index hashes before execution.
- Add logging for search operations on sensitive fields.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
