# Phase 2: Searchable Models & Blind Indexing

## Overview
Implement transparent integration into the ORM using PHP 8+ Attributes and enable searching via Blind Indexes.

## Detailed Tasks
1. **Attribute Definition**:
   - Create `#[Encrypted(searchable, algorithm, context)]`.
   - Create `#[EncryptionContext]` for model-level overrides.
2. **HasEncryption Trait**:
   - Hook into `Model::getAttribute` and `Model::setAttribute`.
   - Support for complex types (arrays/objects) via automatic JSON serialization before encryption.
3. **BlindIndexService**:
   - Implement HMAC-SHA256 based indexing.
   - Logic for "Key Derivation per Column" to prevent frequency correlation across different fields.
4. **QueryBuilder Interception**:
   - Modify `DGLab\Database\QueryBuilder` to detect queries on searchable encrypted columns.
   - Swap values for their HMAC hashes during query preparation.

## Security Verification
- Verify that identical plaintexts in different columns result in different blind index hashes.
- Verify that searching for an encrypted field in a non-searchable column throws an exception.
