# Phase 6: Sodium XChaCha20-Poly1305 Driver

## Objective
Implement the modern, high-security XChaCha20-Poly1305 driver using libsodium, offering superior security against nonce-misuse and side-channel attacks.

## Prerequisites
- Phase 1: Interface Contracts

## Technical Specification

### Algorithm Rationale
- **XChaCha20**: 192-bit nonce allows for safe random nonce generation without collision risk.
- **Poly1305**: High-speed MAC for authentication.
- **Why Sodium?**: Modern cryptography standard; generally considered more secure against timing attacks than OpenSSL AES on non-specialized hardware.

### Driver ID
- `0x02`

## Implementation Steps
1. Verify `sodium` extension availability.
2. Implement `SodiumXChaChaDriver`.
3. Use `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt`.
4. Implement secure key zeroing if supported by the PHP environment.

## Integration Points
- **EncryptionManager**: Alternative driver for high-sensitivity data.

## Testing Criteria
- RFC 8439 test vectors.
- Verify 192-bit nonce usage.
- Performance comparison with AES-GCM.

## Completion Gate
- Sodium driver implemented and registered in the registry.
