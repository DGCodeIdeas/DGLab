# EncryptionService: Secure Cryptographic Backbone

The EncryptionService is the centralized cryptographic authority for the DGLab ecosystem. It provides high-level abstractions for data protection, ensuring that sensitive information remains confidential and tamper-proof across storage and transmission.

## Core Purpose
- **Data at Rest Protection**: Encrypting sensitive database columns, user settings, and state persistence payloads.
- **Data in Transit Security**: Providing primitives for secure signaling, signed signatures, and encrypted tokens.
- **Key Lifecycle Management**: Standardizing how keys are generated, stored, rotated, and retired.
- **Cryptographic Agility**: Allowing the system to transition between algorithms (e.g., OpenSSL to Libsodium) without breaking legacy data.

## Design Principles
- **Algorithm Agility**: Support for multiple drivers and versioned payloads to allow for future-proofing.
- **Fail-Safe Defaults**: Utilizing industry-standard, authenticated encryption (AEAD) by default.
- **Zero-Knowledge Architecture**: Minimizing the exposure of raw keys to application logic.
- **Developer Friendly**: Abstracting complex cryptographic primitives into simple `encrypt()` and `decrypt()` interfaces.

## Tech Stack
- **OpenSSL**: Primary driver for high-performance AES-GCM.
- **Libsodium**: Advanced driver for modern primitives (ChaCha20-Poly1305, Argon2).
- **SQLite/Redis**: Local caching of derived keys or rotation metadata.
- **KeyManagementService (KMS)**: Interface for local filesystem or external hardware security modules.

## Architectural Hierarchy
1. **EncryptionManager**: The entry point that resolves specific drivers based on configuration or payload versions.
2. **Drivers**: Concrete implementations (OpenSSL, Sodium, etc.) following a strict interface.
3. **KeyResolver**: Responsible for fetching the correct key (primary, secondary, or historical) for a given operation.
4. **HeaderProcessor**: Manages the binary metadata header (Version, Algorithm ID, Key ID) attached to every ciphertext.
