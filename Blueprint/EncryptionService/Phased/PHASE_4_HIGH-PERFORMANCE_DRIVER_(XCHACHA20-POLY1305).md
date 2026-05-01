# Phase 4: High-Performance Driver (XChaCha20-Poly1305)

## Overview
Implement a high-performance alternative driver using the Libsodium extension.

## Detailed Tasks
- Create `SodiumDriver` supporting `xchacha20poly1305`.
- Leverage `sodium_crypto_aead_xchacha20poly1305_ietf_*` primitives.
- Benchmark performance against OpenSslDriver.
- Ensure graceful fallback if Sodium extension is missing.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
