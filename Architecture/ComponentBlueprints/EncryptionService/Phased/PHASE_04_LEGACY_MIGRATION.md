# Phase 4: Legacy Migration Bridge

## Objective
Ensure backward compatibility with the existing AES-256-GCM implementation while providing a transparent path for data migration to the new envelope format.

## Prerequisites
- Phase 1, 2, 3

## Technical Specification

### Detection Logic
The `EncryptionManager` must check for the "DG" magic bytes. If absent, it routes the payload to the `LegacyOpenSSLDriver`.

### LegacyOpenSSLDriver
```php
class LegacyOpenSSLDriver implements EncryptionDriverInterface
{
    // Uses the old logic: IV (12b) + Tag (16b) + Ciphertext
    // No header.
}
```

## Implementation Steps
1. Implement `LegacyOpenSSLDriver` using existing `EncryptionService.php` logic.
2. Update `EncryptionManager::decrypt()` to perform "Magic Byte" detection.
3. Develop `MigrateEncryptionJob`:
   - Queries tables with encrypted columns.
   - Identifies legacy payloads.
   - Decrypts with Legacy driver -> Encrypts with new Active driver.
   - Saves back to database.

## Integration Points
- **CMS Studio**: Migration job will be critical for existing content drafts.

## Testing Criteria
- Verify that payloads created with the old `EncryptionService` are still decrypable.
- Verify that new encryptions use the "DG" header.
- Test the migration job on a sample dataset with 100% success rate.

## Risks & Mitigations
- **Risk**: Performance impact of magic byte check on every decryption.
- **Mitigation**: Check is a simple string comparison on the first 2 bytes; overhead is negligible.

## Completion Gate
- Legacy data decrypable via the new `EncryptionManager`.
- `MigrateEncryptionJob` verified in an integration test.
