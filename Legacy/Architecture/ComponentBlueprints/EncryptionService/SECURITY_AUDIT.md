# Security Audit & Cryptographic Verification

This document defines the requirements for auditing and verifying the integrity of the EncryptionService.

## Threat Model

| Threat | Mitigation |
|---|---|
| Key Compromise | Multi-tier key hierarchy, hardware-backed storage (Phase 5). |
| Padding Oracle | Usage of AEAD (GCM/Poly1305) which provides authentication. |
| Replay Attacks | Nonce/IV management ensures unique ciphertext for identical plaintext. |
| Key Reuse | HKDF-based key derivation for specific contexts. |
| Side-Channel | Use of constant-time comparisons in drivers. |

## Verification Requirements

### 1. Known Answer Tests (KAT)
Every driver must be tested against standard test vectors (NIST for AES, RFC for Sodium) to ensure the implementation is correct.

### 2. Randomness Testing
Verify that `openssl_random_pseudo_bytes` and `random_bytes` are used exclusively for IVs and nonces.

### 3. Authentication Integrity
Deliberately modify a single bit in the ciphertext or tag to verify that `decrypt()` correctly rejects the payload with a `DecryptionException`.

### 4. Header Validation
Ensure the system rejects payloads with invalid magic numbers or unsupported versions before attempting expensive decryption.

## Audit Trail

The `AuditService` should log the following (WITHOUT sensitive data):
- Key rotation events (Time, Admin ID, Old Key ID, New Key ID).
- Decryption failures (Payload Version, Driver ID, Reason).
- Re-encryption job progress.
