# Phase 13: Key Registry, Shamir's & MPC

## Objective
Implement a robust, database-backed key registry with support for enterprise-grade security features like Shamir's Secret Sharing and Multi-Party Computation (MPC).

## Prerequisites
- Phase 1

## Technical Specification

### Database Schema (`encryption_keys`)
```sql
CREATE TABLE encryption_keys (
    id UUID PRIMARY KEY,
    key_id VARCHAR(255) UNIQUE,
    key_material BLOB, -- Wrapped by Master Key
    algorithm VARCHAR(50),
    status ENUM('active', 'decrypt-only', 'retired'),
    tenant_id UUID NULL,
    created_at TIMESTAMP,
    rotated_at TIMESTAMP NULL
);
```

### Shamir's Secret Sharing
Implement a 3-of-5 scheme for master key recovery.
- Tooling to split the `ENCRYPTION_MASTER_WRAPPING_KEY` into shares.
- Tooling to reconstruct from shares.

### MPC (Enterprise Tier)
- Placeholder for distributed key generation (DKG) integration.

## Implementation Steps
1. Create the `encryption_keys` migration.
2. Implement `DatabaseKeyProvider`.
3. Develop `cli/nexus.php encryption:master:split` and `encryption:master:reconstruct` commands.
4. Implement DEK/KEK separation (Data Encryption Key / Key Encryption Key).

## Completion Gate
- Migration running.
- Master key splitting/reconstruction verified.
