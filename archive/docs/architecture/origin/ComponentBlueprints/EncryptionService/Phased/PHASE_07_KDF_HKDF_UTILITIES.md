# Phase 7: KDF & HKDF Utilities

## Objective
Implement robust Key Derivation Functions (KDF) to securely derive keys from passwords (Argon2id) and perform sub-key derivation (HKDF).

## Prerequisites
- Phase 1

## Technical Specification

### Algorithms
- **Argon2id**: Winner of the Password Hashing Competition. Use for deriving master keys from environment secrets.
- **HKDF (RFC 5869)**: HMAC-based Extract-and-Expand KDF. Use for deriving column-specific keys and blind index keys.

### Interface
```php
interface KeyDerivationInterface
{
    public function derive(string $ikm, string $salt, string $info, int $length): string;
    public function hashPassword(string $password): string;
}
```

## Implementation Steps
1. Implement `Argon2Kdf` with configurable cost factors (memory, time, threads).
2. Implement `HkdfService` using `hash_hkdf`.
3. Integrate HKDF into the `EncryptionManager` for internal key management.

## Testing Criteria
- Verify Argon2id against OWASP recommended parameters.
- HKDF test vectors from RFC 5869.

## Completion Gate
- KDF and HKDF services available in the `Encryption` namespace.
