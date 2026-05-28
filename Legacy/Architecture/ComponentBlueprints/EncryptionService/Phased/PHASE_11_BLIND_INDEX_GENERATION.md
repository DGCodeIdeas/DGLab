# Phase 11: Blind Index Generation (HMAC-SHA256)

## Objective
Implement blind indexing to allow secure searching of encrypted data without decrypting the entire database table or leaking frequency information across different columns.

## Prerequisites
- Phase 7: KDF & HKDF
- Phase 8: #[Encrypted] Attribute

## Technical Specification

### Logic
For a given plaintext and column:
1. Derive a column-specific key using HKDF: `column_key = HKDF(master_key, salt=tenant_id, info=column_name)`.
2. Generate the blind index: `BIDX = HMAC-SHA256(plaintext, column_key)`.
3. Truncate if necessary (security trade-off for performance).

### BlindIndexService
```php
class BlindIndexService
{
    public function generate(string $plaintext, string $columnName, ?string $tenantId = null): string;
}
```

## Implementation Steps
1. Create `BlindIndexService`.
2. Implement column-key derivation.
3. Update `HasEncryption` trait to automatically populate `{column}_bidx` fields.
4. Update migration generator to suggest `_bidx` columns for searchable encrypted fields.

## Integration Points
- **DocStudio**: Searchable tags on encrypted documents.
- **CMS Studio**: Searchable user emails and phone numbers.

## Testing Criteria
- Verify that identical plaintexts in different columns produce different blind indexes.
- Verify that the same plaintext for the same column produces the same index.

## Completion Gate
- `BlindIndexService` functional and integrated into the Model lifecycle.
