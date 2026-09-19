# Key Management and Rotation

Effective security relies more on key management than algorithm choice. The EncryptionService implements a robust key lifecycle.

## Key Types

1. **Master Key**: The root of trust, usually stored in environment variables or a hardware module.
2. **Data Encryption Keys (DEK)**: Short-lived keys derived from the Master Key or generated randomly and wrapped.
3. **Purpose-Specific Keys**: Keys derived for specific use cases (e.g., "auth.tokens", "state.persistence") to prevent key reuse across domains.

## Key Derivation (KDF)

Instead of using raw strings, the service uses **Argon2id** or **HKDF** (HMAC-based Key Derivation Function) to derive keys.

- **Context-Aware**: Every key derivation includes a context string (e.g., `dglab.encryption.v1.auth`).
- **Salt Management**: Random salts are stored alongside the Master Key or in the header of the encrypted payload if needed.

## Rotation Strategy

To limit the blast radius of a compromised key, keys must be rotated regularly.

### 1. Active Key
The newest key used for all **new** encryption operations.

### 2. Historical Keys
Older keys maintained in a read-only state to allow decryption of legacy data.

### 3. Re-encryption (Churn)
A background process (controlled via `cli/nexus.php` or a dedicated cron) that reads legacy payloads and re-encrypts them with the Active Key.

## Storage Options

- **Filesystem**: Encrypted local storage (default).
- **Environment**: Single master key (standard).
- **Database**: Encrypted storage for DEKs (wrapped by the master key).

## Security Measures

- **No Key Logging**: Keys are never printed in stack traces or logs.
- **Memory Zeroing**: Sensitive variables are overwritten with null/random data before being released (where supported by PHP's garbage collector).
- **Permissions**: Local key files (`.key`) are set to `0600` permissions.
