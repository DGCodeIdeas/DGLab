# PHASE HUB-20: Cryptography & Secrets Management Service

## Tier
Hub (Shared Services)

## Component Name
Sovereign Vault

## Description
A secure service for managing sensitive data, API keys, and cryptographic operations. It extends `CORE-16` to provide high-level "Secrets Management" including key rotation, encrypted field storage, and secure handshaking.

## Sequencing Rationale
Depends on `CORE-16` (Binary Envelope) and `CORE-19` (DBAL). Critical for `HUB-22` (Billing) and any Spoke handling PII.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-06: Audit Log`, `HUB-02: Shared Cache`.
- **Transitive Core Dependencies**: `CORE-16: Binary Encryption Envelope`, `CORE-19: DBAL`, `CORE-08: Error Handler`.
- **Standards**: AES-256-GCM, Argon2id for hashing.

## Architectural Design
- **SecretManager**: Stores and retrieves encrypted environment secrets.
- **KeyRotator**: Automates the rotation of encryption keys without downtime (re-encrypting data in background).
- **CryptoProvider**: Provides high-level methods for signing, verifying, and encrypting payloads.
- **BlindIndexGenerator**: Creates searchable hashes for encrypted fields (referencing encryption patterns in CORE-16).

## Interface Contracts

### VaultInterface
```php
namespace Sovereign\Hub\Contracts;

interface VaultInterface
{
    /**
     * Retrieve a secret from the vault.
     */
    public function getSecret(string $key): ?string;

    /**
     * Encrypt a value for persistent storage.
     */
    public function encrypt(string $value, ?string $context = null): string;

    /**
     * Decrypt a value.
     */
    public function decrypt(string $payload, ?string $context = null): string;
}
```

## Integration Strategy
- **Upward**: Uses `CORE-16` for low-level cryptographic primitives.
- **Downward**: Spoke applications use the Vault to store third-party API keys (e.g., Stripe keys) rather than hardcoding them in `.env`.
- **Security**: All access to the Vault is logged via `HUB-06`.

## CI Verification Criteria
- **Encryption Integrity**: Data encrypted with Key A must be unreadable with Key B.
- **Rotation Safety**: Must be able to rotate keys and still decrypt "Legacy" data using the previous key version.
- **Audit Coverage**: 100% of Vault access must generate an Audit Log entry.

## SemVer Impact
**Major**. Establishes the secure storage and crypto standard for the stack.
