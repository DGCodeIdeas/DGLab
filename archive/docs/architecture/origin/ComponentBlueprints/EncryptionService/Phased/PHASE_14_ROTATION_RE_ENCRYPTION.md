# Phase 14: Rotation CLI, Grace Periods & Re-encryption

## Objective
Automate the key rotation lifecycle, including grace periods for multi-version decryption and background re-encryption of legacy data.

## Prerequisites
- Phase 13

## Implementation Steps
1. Create `encryption:key:rotate` command.
2. Implement "Grace Period" logic: old keys remain `decrypt-only` for 30 days.
3. Develop `ReEncryptJob`:
   - Batches records.
   - Decrypts with old Key ID -> Encrypts with new Active Key ID.
   - Implements progress tracking and idempotency.

## Completion Gate
- Full rotation workflow (rotate -> re-encrypt -> retire) verified in integration tests.
