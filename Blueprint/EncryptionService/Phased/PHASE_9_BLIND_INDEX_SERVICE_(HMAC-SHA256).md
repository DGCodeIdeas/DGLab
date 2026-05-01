# Phase 9: Blind Index Service (HMAC-SHA256)

## Overview
Enable secure search functionality without exposing plaintext to the database.

## Detailed Tasks
- Create `BlindIndexService` for generating deterministic hashes.
- Implement HMAC-SHA256 with per-column salts.
- Add support for 'fast-lookup' columns in the `HasEncryption` trait.
- Verify collision resistance and statistical distribution.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
