# Phase 12: Deterministic Encryption (AES-SIV) & Searchable DSL

## Objective
Implement AES-SIV for deterministic encryption use-cases (where blind indexes aren't sufficient) and provide a fluent Query DSL for searching encrypted data.

## Prerequisites
- Phase 11

## Technical Specification

### AES-SIV
- **SIV (Synthetic Initialization Vector)**: RFC 5297. Ensures that the same plaintext and AAD always produce the same ciphertext.
- Use only when high-entropy data needs deterministic mapping.

### Searchable DSL
```php
User::query()->whereEncrypted('email', 'test@example.com')->get();
```
The `whereEncrypted` method automatically resolves to a `WHERE email_bidx = ...` query.

## Implementation Steps
1. Implement `AesSivDriver` (Driver ID `0x03`).
2. Implement the `whereEncrypted` macro on the Query Builder.
3. Support `whereInEncrypted` and basic equality checks.

## Completion Gate
- Fluent search API available on models.
- AES-SIV driver registered.
