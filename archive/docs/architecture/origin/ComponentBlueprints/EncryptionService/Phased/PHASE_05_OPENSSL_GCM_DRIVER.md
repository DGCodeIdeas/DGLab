# Phase 5: OpenSSL AES-256-GCM Driver

## Objective
Implement the industry-standard AES-256-GCM driver using the OpenSSL extension, providing high-performance authenticated encryption.

## Prerequisites
- Phase 1: Interface Contracts

## Technical Specification

### Algorithm Rationale
- **AES-256**: Standard for high-security applications, resistant to brute force.
- **GCM (Galois/Counter Mode)**: Provides Authenticated Encryption (AEAD), preventing ciphertext manipulation.
- **Why OpenSSL?**: Native performance and broad support across hosting environments.

### Driver ID
- `0x01`

### Implementation Detail
```php
class OpenSslGcmDriver implements EncryptionDriverInterface
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12; // Standard for GCM
    private const TAG_LEN = 16;

    public function encrypt(string $plaintext, string $key, string $associatedData = ''): array
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LEN);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $associatedData, self::TAG_LEN);
        return ['ciphertext' => $ciphertext, 'iv' => $iv, 'tag' => $tag];
    }
}
```

## Implementation Steps
1. Create `DGLab\Services\Encryption\Drivers\OpenSslGcmDriver`.
2. Implement `encrypt` and `decrypt` with full error handling.
3. Add support for Additional Authenticated Data (AAD).
4. Register driver in `EncryptionManager` with alias 'aes-256-gcm'.

## Integration Points
- **EncryptionManager**: Default driver for standard operations.

## Testing Criteria
- NIST Known Answer Tests (KAT) vectors for AES-256-GCM.
- Verify AAD integrity: Changing AAD must cause decryption failure.
- Performance benchmark: Target < 2ms for 10KB payloads.

## Risks & Mitigations
- **Risk**: Nonce reuse.
- **Mitigation**: `generateIv()` must use `openssl_random_pseudo_bytes`.

## Completion Gate
- OpenSSL driver functional and verified against NIST vectors.
