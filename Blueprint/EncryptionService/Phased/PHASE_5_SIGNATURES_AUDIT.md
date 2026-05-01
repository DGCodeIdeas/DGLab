# Phase 5: Digital Signatures & Audit Provenance

## Overview
Implement cryptographic signatures to ensure data integrity and non-repudiation.

## Detailed Tasks
1. **SigningService**:
   - Implement Ed25519 for high-performance signatures.
   - API for `sign(data)` and `verify(data, signature)`.
2. **Audit Log Hardening**:
   - Integrate with `EventAuditService` to sign every audit entry.
   - Create a background verification job to detect tampering in audit tables.
3. **Data Provenance**:
   - Attribute-based signing for critical model fields (e.g., `status`, `role`).

## Verification
- Tamper detection test: Modify a signed audit entry and verify that the verification utility flags it.
