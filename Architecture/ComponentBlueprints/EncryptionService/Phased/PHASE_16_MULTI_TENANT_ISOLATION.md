# Phase 16: Multi-Tenant Key Isolation & CMK Overrides

## Objective
Provide cryptographic isolation between tenants, allowing each tenant to have their own master keys or even their own Cloud KMS keys (BYOK).

## Prerequisites
- Phase 15

## Implementation Steps
1. Implement `TenantKeyProvider`.
2. Add `tenant_id` to the `EncryptionContext`.
3. Support CMK (Customer Managed Key) overrides in the `tenants` table config.
4. Ensure HKDF derivation includes `tenant_id` as a salt.

## Completion Gate
- Data encrypted for Tenant A cannot be decrypted by Tenant B even with the same master wrapping key.
