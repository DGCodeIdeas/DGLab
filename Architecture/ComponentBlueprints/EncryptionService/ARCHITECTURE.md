# Technical Architecture: EncryptionService

The EncryptionService follows a modular, driver-based architecture to provide maximum flexibility and security.

## Payload Structure (The "Envelope")

To support cryptographic agility, every piece of encrypted data is prefixed with a binary header. This allows the service to identify how to decrypt data even if the default algorithm or key has changed.

| Byte Offset | Field | Description |
|---|---|---|
| 0-1 | Magic Number | `0x44 0x47` (DG) identifying the format. |
| 2 | Version | Format version (e.g., `0x01`). |
| 3 | Driver ID | Identifier for the driver used (e.g., `0x01` for OpenSSL, `0x02` for Sodium). |
| 4-7 | Key ID | CRC32 or hash of the key alias used for rotation tracking. |
| 8-N | IV / Nonce | Initialization vector (length varies by driver). |
| N-M | Tag | Authentication tag (for AEAD). |
| M-End | Ciphertext | The actual encrypted data. |

## Core Components

### 1. EncryptionManager
The central hub that implements `EncryptionServiceInterface`. It determines which driver to use for encryption (based on config) and which driver to use for decryption (based on the payload header).

### 2. EncryptionDriverInterface
All drivers must implement these methods:
- `encrypt(string $data, Key $key): string`
- `decrypt(string $payload, Key $key): string`
- `getDriverId(): int`
- `getIvLength(): int`

### 3. Driver Registry
- **OpenSSLDriver**: Uses `aes-256-gcm`. High compatibility and performance.
- **SodiumDriver**: Uses `xchacha20poly1305ietf`. Modern, highly secure, and resistant to nonce-misuse.
- **NullDriver**: Used for testing or local development (no encryption).

### 4. KeyProviderInterface
Decouples key storage from encryption logic.
- `getCurrentKey(string $purpose): Key`
- `getKeyById(string $keyId): Key`

## Class Diagram (Conceptual)

```
[ EncryptionManager ] 1 --- * [ EncryptionDriverInterface ]
          |
          | 1
          V
[ KeyProviderInterface ] <--- [ FileKeyProvider ]
                         <--- [ EnvironmentKeyProvider ]
```

## Error Handling
- **DecryptionException**: Thrown when authentication tags fail or headers are malformed.
- **MissingKeyException**: Thrown when a payload refers to an unknown or retired Key ID.
- **UnsupportedDriverException**: Thrown when the system encounters a Driver ID it doesn't recognize.
