# Phase 5: Key Registry Schema & Multi-Tenancy

## Overview
Design and implement the database layer for managing encryption keys.

## Detailed Tasks
- Create migration for `encryption_keys` table with UUID IDs.
- Support `tenant_id` column for isolated key management in SaaS environments.
- Add status tracking (`active`, `decrypt-only`, `retired`).
- Implement `DatabaseKeyProvider` with Eloquent integration.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
