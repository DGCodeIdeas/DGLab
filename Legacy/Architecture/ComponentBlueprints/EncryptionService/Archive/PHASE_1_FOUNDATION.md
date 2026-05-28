# Phase 1: Foundation & Core Infrastructure

## Overview
Establish the architectural backbone: the Driver-based Manager and the Database-backed Key Registry.

## Detailed Tasks
1. **Define Interfaces**:
   - `EncryptionDriverInterface`: Methods for `encrypt`, `decrypt`, `getDriverId`.
   - `KeyProviderInterface`: Methods for `getKey(id)`, `getActiveKey()`.
2. **Implement EncryptionManager**:
   - Handles driver registration and selection.
   - Implements "Fail-Closed" logic.
3. **Key Registry Implementation**:
   - Create migration for `encryption_keys` table.
   - Implement `DatabaseKeyProvider` with caching (Redis) for performance.
4. **Binary Header Processor**:
   - Logic to pack/unpack Magic Bytes, Version, Driver ID, and Key ID.
5. **Legacy Support**:
   - Detect non-prefixed payloads and route to legacy AES-256-GCM logic.

## Security Verification
- Unit test: verify decryption fails if Magic Number is incorrect.
- Unit test: verify decryption fails if Tag is tampered with.
- Audit: Ensure `ENCRYPTION_MASTER_WRAPPING_KEY` is never logged.
