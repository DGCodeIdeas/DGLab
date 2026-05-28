# Phase 2: Binary Envelope Specification (The "DG" Header)

## Objective
Define and implement the binary protocol for encrypted payloads to support cryptographic agility, versioning, and driver-specific metadata.

## Prerequisites
- Phase 1: Interface Contracts

## Technical Specification

### Header Format (The "Envelope")
Every encrypted payload must start with this immutable header.

| Byte Offset | Field | Size | Description |
|---|---|---|---|
| 0-1 | Magic Number | 2 bytes | `0x44 0x47` ("DG") |
| 2 | Version | 1 byte | Header version (e.g., `0x01`) |
| 3 | Driver ID | 1 byte | ID of the driver used for this payload. |
| 4-19 | Key ID | 16 bytes | UUID of the key used (binary). |
| 20-N | Metadata | Variable | IV, Tag, etc. (determined by Driver ID). |

### Binary Header Processor
```php
namespace DGLab\Services\Encryption;

class HeaderProcessor
{
    public const MAGIC = "\x44\x47";
    public const VERSION = 0x01;

    public function pack(int $driverId, string $keyUuid, string $iv, string $tag, string $ciphertext): string;
    public function unpack(string $payload): PayloadContainer;
}
```

## Implementation Steps
1. Define the binary structure constants.
2. Implement `HeaderProcessor` to handle packing and unpacking.
3. Create `PayloadContainer` DTO to hold extracted header data and ciphertext.
4. Integrate `HeaderProcessor` into the `EncryptionManager`.

## Integration Points
- **EncryptionManager**: Uses `HeaderProcessor` to wrap driver output and unwrap inputs.

## Testing Criteria
- Round-trip test: `unpack(pack(...)) === original_values`.
- Tamper test: Changing a single byte in the header results in an `InvalidHeaderException`.
- Versioning test: Ensure the processor rejects unsupported header versions.

## Risks & Mitigations
- **Risk**: Header overhead for very small plaintexts.
- **Mitigation**: Standardize on binary storage (BLOB/BINARY) in the database to minimize Base64 bloat.

## Completion Gate
- `HeaderProcessor` implemented and passing 100% unit tests.
- Specification documented and finalized in the master blueprint.
