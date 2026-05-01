# Phase 1: Interface Contracts & Driver Registry

## Objective
Establish the primary abstraction layer for all cryptographic operations through strict PHP 8.2+ interface contracts and a central registry for encryption drivers.

## Prerequisites
- None (Foundational Phase)

## Technical Specification

### Configuration Example (`config/encryption.php`)
```php
return [
    'default' => env('ENCRYPTION_DRIVER', 'aes-256-gcm'),
    'key' => env('ENCRYPTION_MASTER_WRAPPING_KEY'),
    'drivers' => [
        'aes-256-gcm' => [
            'driver' => 'openssl',
            'key_id' => 'master-v1',
        ],
        'xchacha20poly1305' => [
            'driver' => 'sodium',
            'key_id' => 'master-v2',
        ],
    ],
];
```

### Environment Variables (`.env`)
```bash
ENCRYPTION_DRIVER=aes-256-gcm
ENCRYPTION_MASTER_WRAPPING_KEY=base64:...
EOF_SECRET_KDF_SALT=...
```

### Interfaces

#### `EncryptionDriverInterface`
Defines the contract for symmetric encryption implementations.
```php
namespace DGLab\Services\Encryption\Contracts;

interface EncryptionDriverInterface
{
    public function getDriverId(): int;
    public function encrypt(string $plaintext, string $key, string $associatedData = ''): array;
    public function decrypt(string $ciphertext, string $key, string $iv, string $tag, string $associatedData = ''): string;
    public function generateIv(): string;
}
```

## Implementation Steps
1. Create namespaces and interfaces.
2. Implement `EncryptionManager`.
3. Create default configuration file.

## Completion Gate
- Interfaces defined and configuration structure finalized.
