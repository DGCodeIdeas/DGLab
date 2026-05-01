# Phase 14: Asymmetric Encryption (RSA-OAEP)

## Overview
Implement public-key primitives for secure data exchange and bootstrapping.

## Detailed Tasks
- Create `AsymmetricDriver` using OpenSSL RSA-OAEP.
- Implement PKCS#8 key loading and management.
- Support PEM format for external key import/export.
- Develop CLI for generating RSA key pairs.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
