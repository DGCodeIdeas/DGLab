# Encryption Service Master Blueprint

## 1. Executive Summary
The Encryption Service is a centralized cryptographic authority for the DGLab framework. It provides high-level abstractions for data protection, ensuring confidentiality and integrity across storage and transmission. This blueprint specifies a multi-algorithm, driver-based architecture supporting symmetric and asymmetric operations, key rotation, searchable encryption via blind indexes, and transparent model integration using PHP 8+ Attributes.

## 2. Threat Model

### 2.1 Protected Against
- **At-Rest Theft**: Physical or logical theft of database backups, disk snapshots, or CSV exports.
- **Database Dumps**: Unauthorized access to the database via SQL injection or compromised administrative credentials.
- **Compromised Read-Only Application Servers**: Attackers with file-read access (e.g., LFI) cannot decrypt data without the `ENCRYPTION_MASTER_WRAPPING_KEY` stored in the environment.
- **Padding Oracle Attacks**: Usage of Authenticated Encryption (AEAD) like AES-GCM and XChaCha20-Poly1305 ensures ciphertext integrity.

### 2.2 NOT Protected Against
- **Active Memory Inspection**: Attackers with root access or ability to dump memory of the running PHP process can see keys in RAM.
- **Compromised PHP Runtime**: Attackers with full execution capability on the application server can intercept plaintext before encryption.
- **Frequency Analysis (Partial)**: Blind indexes leak frequency information of identical plaintexts. This is a known trade-off for searchability.

## 3. Core Principles
- **Fail-Closed**: Any cryptographic failure (tag mismatch, missing key, malformed header) results in a `CryptographicException`.
- **Envelope Encryption**: Data Encryption Keys (DEK) are generated locally, used for the payload, then wrapped by a master key/KMS and stored alongside the ciphertext.
- **Cryptographic Agility**: Support for multiple algorithms and versioned payloads allows for seamless transition as crypto standards evolve.
- **Observability**: Structured logging of all operations (latency, key_id, algorithm, outcome) without ever leaking sensitive material.

## 4. Detailed Architecture

### 4.1 Payload Protocol (The "Envelope")
Every piece of encrypted data is prefixed with a binary header.

| Byte Offset | Field | Description |
|---|---|---|
| 0-1 | Magic Number | `0x44 0x47` (DG) |
| 2 | Version | Header version (e.g., `0x01`) |
| 3 | Driver ID | Identifier for the driver used (e.g., `0x01` for OpenSSL, `0x03` for AWS KMS) |
| 4-19 | Key ID | 128-bit UUID of the key in the registry |
| 20-N | Nonce/IV | Initialization vector (length varies by driver) |
| N-M | Tag | Authentication tag (for AEAD) |
| M-End | Ciphertext | The actual encrypted data |

### 4.2 Key Registry (`encryption_keys` Table)
| Field | Type | Description |
|---|---|---|
| id | UUID | Primary Key (matches Key ID in header) |
| key_id | String | Human-readable alias (e.g., `master-2024-Q1`) |
| algorithm | String | e.g., `aes-256-gcm`, `xchacha20poly1305` |
| key_material | Blob | DEK encrypted by `ENCRYPTION_MASTER_WRAPPING_KEY` (local) |
| kms_reference | String | ARN for AWS KMS or Path for HashiCorp Vault |
| status | Enum | `active`, `decrypt-only`, `retired` |
| tenant_id | UUID | Null for global, non-null for tenant-specific master keys |
| created_at | DateTime | |
| rotated_at | DateTime | |
| destroyed_at | DateTime | |

### 4.3 Data Flow Diagrams (Textual)

#### A. Symmetric Encryption (Local Driver)
1. **App**: Call `EncryptionManager->encrypt(data)`.
2. **Manager**: Fetch `ActiveKey` from `KeyRegistry`.
3. **Registry**: Retrieve wrapped DEK from DB.
4. **Manager**: Unwrap DEK using `ENCRYPTION_MASTER_WRAPPING_KEY`.
5. **Driver**: Generate random IV.
6. **Driver**: Encrypt `data` with DEK + IV -> `ciphertext` + `tag`.
7. **Manager**: Pack `Magic + Version + DriverID + KeyID + IV + Tag + Ciphertext`.
8. **App**: Receive binary string.

#### B. Envelope Encryption (Cloud KMS)
1. **App**: Call `EncryptionManager->encrypt(data)`.
2. **Manager**: Delegate to `KmsDriver`.
3. **KmsDriver**: Call KMS API `GenerateDataKey(KeyARN)`.
4. **KMS API**: Return `PlaintextDEK` and `CiphertextDEK`.
5. **KmsDriver**: Encrypt `data` using `PlaintextDEK`.
6. **KmsDriver**: Zero out `PlaintextDEK` from memory.
7. **Manager**: Pack `Header + CiphertextDEK + IV + Tag + Ciphertext`.
8. **App**: Receive binary string.

#### C. Searchable Encryption (Blind Index)
1. **App**: `User->email = 'test@example.com'`.
2. **Model**: Detect `#[Encrypted(searchable: true)]`.
3. **Model**: Call `BlindIndexService->generate('test@example.com', 'email', tenant_id)`.
4. **BidxService**: Derive `column_key` from `MasterKey` via HKDF using `tenant_id + column_name`.
5. **BidxService**: Return `HMAC-SHA256('test@example.com', column_key)`.
6. **Model**: Store ciphertext in `email` and hash in `email_bidx`.

## 5. Model Integration (#[Encrypted] Attribute)
### API Contract
```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted {
    public function __construct(
        public bool $searchable = false,
        public string $algorithm = 'aes-256-gcm',
        public ?string $keyId = null,
        public array $context = []
    ) {}
}
```


## 6. Roadmap (18-Phase Execution)

| Phase | Title | Focus |
|---|---|---|
| 1 | **Core Interface Architecture** | Foundation, Contracts, Exception Hierarchy |
| 2 | **Binary Header Protocol** | Magic Bytes, Versioning, Envelope Agility |
| 3 | **Symmetric Suite (OpenSSL)** | AES-256-GCM, AAD Support |
| 4 | **High-Performance (Sodium)** | XChaCha20-Poly1305 integration |
| 5 | **Key Registry & Multi-Tenancy** | DB Schema, Tenant Isolation |
| 6 | **Encrypted Key Material** | DEK Wrapping (Master Key) |
| 7 | **Manager Orchestration** | Driver Lifecycle, Redis Caching |
| 8 | **#[Encrypted] Attribute** | Transparent Model Integration |
| 9 | **Blind Index Service** | Searchable Cryptography (HMAC) |
| 10 | **Query Interception** | Transparent Search logic |
| 11 | **Cloud KMS (AWS)** | Hardware-backed Security |
| 12 | **Cloud Resilience** | Circuit Breakers, Retry Logic |
| 13 | **Streaming Service** | Large Blob chunked AEAD |
| 14 | **Asymmetric (RSA)** | Public Key Primitives |
| 15 | **Sealed Boxes (ECC)** | Anonymous Recipient Encryption |
| 16 | **Digital Signatures** | Ed25519 Non-Repudiation |
| 17 | **Tamper-Evident Logs** | Signed Audit Trails |
| 18 | **Lifecycle & Hardening** | Rotation CLI, Shredding, Audit |

## 7. Performance Budget
| Operation | Target Latency | Payload Size |
|---|---|---|
| Local Encrypt | < 5ms | 1 KB |
| Local Decrypt | < 5ms | 1 KB |
| KMS Unwrap | < 50ms | N/A (API call) |
| Streaming | > 50 MB/s | Large Files |

## 8. Compliance Mapping
- **GDPR Article 32**: Technical measures for security of processing.
- **SOC 2 CC6.1/6.6**: Access control and protection against unauthorized disclosure.
- **HIPAA**: Technical safeguards for PHI at rest.

## 9. Operational Runbooks
- **Key Rotation**:
  1. `php cli/nexus.php encryption:key:generate --alias=2024-Q2`
  2. The new key is added to `encryption_keys` as `active`.
  3. Old key status changes to `decrypt-only`.
- **Emergency Shred**:
  1. Set `status = 'retired'` for the compromised Key ID in the database.
  2. All subsequent decryption attempts for that key will fail immediately.

## 10. Testing Strategy

### 10.1 Known Answer Tests (KAT)
Every driver (OpenSSL, Sodium, AWS KMS) must pass validation against official test vectors (NIST, RFC) to ensure correct implementation of the underlying algorithm.

### 10.2 Round-trip Integrity
- Assert `decrypt(encrypt(data)) === data` for strings, large blobs, nested arrays, and objects.
- Verify preservation of data types after JSON serialization/deserialization.

### 10.3 Tamper Verification (AEAD Testing)
- **Header Tampering**: Modify Magic Bytes or Version and verify `CryptographicException`.
- **Ciphertext Tampering**: Flip a single bit in the ciphertext and verify authentication failure.
- **Tag Tampering**: Modify the authentication tag and verify `CryptographicException`.
- **IV Tampering**: Modify the IV and verify `CryptographicException`.

### 10.4 Key Resolution Testing
- Verify that a payload encrypted with an old Key ID still decrypts correctly if the key exists in the registry with `status = 'decrypt-only'`.
- Verify that a payload fails to decrypt if the Key ID is missing or `status = 'retired'`.

### 10.5 Blind Index Collision & Frequency
- Verify that different plaintexts do not produce blind index collisions within a reasonable statistical bound.
- Verify that the same plaintext in two different columns produces different hashes.
