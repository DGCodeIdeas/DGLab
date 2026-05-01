# Phase 1: Core Interface Architecture

## Overview
Establish the foundational contracts and interfaces for the driver-based encryption ecosystem.

## Detailed Tasks
- Define `EncryptionDriverInterface` with `encrypt`, `decrypt`, and `getDriverId` methods.
- Define `KeyProviderInterface` for retrieving raw key material.
- Define `CipherInterface` to abstract specific algorithm parameters (IV length, tag length).
- Create `CryptographicException` hierarchy for granular error handling.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
