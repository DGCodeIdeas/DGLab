# Phased Implementation Roadmap: EncryptionService

A 5-phase approach to transitioning from the legacy static implementation to a fully featured, enterprise-grade cryptographic service.

## Phase 1: Foundation & Abstraction (Current Focus)
- **Interface Definition**: Create `EncryptionServiceInterface` and `EncryptionDriverInterface`.
- **EncryptionManager**: Implement the manager to handle driver registration and resolution.
- **Legacy Wrapper**: Create an `OpenSSLDriver` that is backward compatible with the current AES-256-GCM implementation.
- **Unit Testing**: 100% coverage on basic encrypt/decrypt cycles.

## Phase 2: Binary Headers & Versioning
- **Envelope Protocol**: Implement the binary header (Magic bytes, Version, Driver ID, Key ID).
- **Graceful Upgrade**: Update `decrypt()` to handle both legacy (no header) and versioned payloads.
- **Metadata Extraction**: Logic to determine which key and driver to use based on the header.

## Phase 3: Advanced Driver Ecosystem (Sodium)
- **SodiumDriver**: Implement `XChaCha20-Poly1305` support via `libsodium`.
- **KDF Integration**: Implement `HKDF` for deriving session-specific keys from the master key.
- **Argon2id Support**: For password-based key derivation.

## Phase 4: Streaming & Large File Support
- **StreamableInterface**: Methods for `encryptStream()` and `decryptStream()`.
- **Chunked AEAD**: Implementation of `crypto_secretstream` (Sodium) or segmented AES-GCM for large files.
- **Integration**: Update `DownloadService` to use streaming encryption for secure assets.

## Phase 5: Enterprise Hardening & Rotation
- **Key Rotation CLI**: Commands to rotate the primary key and track historical keys.
- **Re-encryption Jobs**: Background tasks to migrate old data to the latest version.
- **Audit Logging**: Integration with `AuditService` to track key usage (without logging the keys).
- **FIPS Compliance Check**: Optional mode to restrict algorithms to FIPS-approved ciphers.
