# PHASE CORE-16: Binary Encryption Envelope

## Tier
Core

## Component Name
Cryptographic Foundation

## Description
A low-level service for secure data handling. It implements AEAD (Authenticated Encryption with Associated Data) using OpenSSL or Libsodium, providing the "Binary Envelope" pattern for encrypting sensitive strings and files.

## Context7 Research
- **Ciphers**: Prioritizes `AES-256-GCM` (OpenSSL) or `XChaCha20-Poly1305` (Sodium).
- **KDF**: Implements `Argon2id` for password hashing and `HKDF` for key derivation.

## Architectural Design
- **Encrypter**: Simple `encrypt(data)` and `decrypt(payload)` methods.
- **Payload**: A base64-encoded JSON object containing the IV, ciphertext, and HMAC/Tag.
- **KeyRegistry**: Securely loads keys from environment or specialized key files (referencing CORE-10).

## Integration Strategy
Foundational for any security-related Hub component (e.g., Auth, Database Encryption).

## CI Verification Criteria
- **Security**: Must pass a "known plaintext" attack test.
- **Performance**: Encrypting a 1KB string must take < 0.5ms.

## SemVer Impact
**Major**. Establishes the post-quantum-ready security baseline of the stack.