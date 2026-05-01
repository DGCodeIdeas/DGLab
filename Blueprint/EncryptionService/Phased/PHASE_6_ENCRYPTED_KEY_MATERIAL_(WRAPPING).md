# Phase 6: Encrypted Key Material (Wrapping)

## Overview
Secure the Data Encryption Keys (DEKs) at rest using a Master Wrapping Key.

## Detailed Tasks
- Implement Key Wrapping logic in `DatabaseKeyProvider`.
- Use `ENCRYPTION_MASTER_WRAPPING_KEY` from environment to protect key material.
- Add integrity checks for the wrapping key to prevent accidental data loss.
- Implement `KmsKeyProvider` interface for future cloud integration.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
