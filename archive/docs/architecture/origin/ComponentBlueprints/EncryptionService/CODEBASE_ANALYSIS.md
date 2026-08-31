# Codebase Analysis: Current Encryption State

This document analyzes the existing cryptographic implementations across the DGLab codebase and identifies the integration points for the new EncryptionService.

## Current Implementations

### 1. `EncryptionService` (Legacy)
- **Location**: `app/Services/Encryption/EncryptionService.php`
- **Algorithm**: Hardcoded `aes-256-gcm`.
- **Keying**: Uses a static 32-character key from config/env.
- **Flaws**: No versioning headers, making key rotation impossible without data loss. No support for modern primitives like ChaCha20. No streaming support.

### 2. `KeyManagementService`
- **Location**: `app/Services/Auth/KeyManagementService.php`
- **Focus**: Purely RSA key pair generation for JWT/signing.
- **Integration**: Operates independently of the `EncryptionService`.
- **Opportunities**: Should be unified under the `KeyProviderInterface` to manage both symmetric and asymmetric keys.

### 3. `JWTService`
- **Location**: `app/Services/Auth/JWTService.php`
- **Usage**: Hardcoded `openssl_sign` and `openssl_verify`.
- **Target**: Should eventually use the `EncryptionService` abstraction for signing to support different algorithms (e.g., Ed25519 via Sodium).

## Integration Points (Consumers)

| Consumer | Location | Impact of New Service |
|---|---|---|
| **State Persistence** | `app/Services/Superpowers/Runtime/StateContainer.php` | Will benefit from versioned headers to allow seamless key rotation for stored SPA state. |
| **Download Signatures** | `app/Services/Download/DownloadManager.php` | Can move to more compact Sodium-based signatures. |
| **Auth Tokens** | `app/Services/Auth/Guards/JwtGuard.php` | Centralized key management for token verification. |
| **Audit Logs** | `app/Core/EventAuditService.php` | Potential for encrypting sensitive audit data at rest. |

## Refactoring Strategy

1. **Phase 1 Implementation**: Introduce the new `EncryptionManager` alongside the legacy service.
2. **Transparent Decryption**: The new service will detect legacy payloads (lack of magic bytes) and route them to the legacy OpenSSL logic.
3. **Incremental Migration**: Use the `Re-encryption Job` (Phase 5) to migrate existing stored state to the new versioned format.
