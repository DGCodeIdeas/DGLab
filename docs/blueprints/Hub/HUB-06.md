# PHASE HUB-06: Audit Log & Activity Tracker

## Tier
Hub

## Component Name
Sovereign Auditor

## Description
A centralized logging service for tracking system-wide activity and user actions. It provides a tamper-evident record of "who did what, and when" across the entire polyrepo stack. It focuses on high-integrity data storage and searchable audit trails for compliance and security forensics.

## Context7 Research
- **Depends on**: `CORE-19: DBAL`, `HUB-04: Identity`, `CORE-03: Event Dispatcher`.
- **Patterns**: Observer Pattern (via events), Event Sourcing (lite version).
- **Integrity**: Implements row-level hashing or "chained logs" to detect record tampering.

## Architectural Design
- **AuditManager**: Listens for system events and determines which ones require auditing.
- **LogWriter**: Asynchronously writes audit records to a dedicated high-performance database or append-only file (referencing CORE-14).
- **ActivityTracker**: A trait-based utility that Spoke applications can add to their models to automatically track CRUD operations.
- **AuditViewer**: Hub-level API for querying and filtering logs by user, tenant, or action type.

### Record Schema
```json
{
  "id": "uuid",
  "user_id": "int",
  "tenant_id": "int",
  "action": "document.update",
  "resource_type": "Document",
  "resource_id": "123",
  "changes": {"title": ["Old", "New"]},
  "ip_address": "string",
  "user_agent": "string",
  "timestamp": "iso8601",
  "signature": "sha256"
}
```

## Interface Contracts

### AuditorInterface
```php
namespace Sovereign\Hub\Contracts;

interface AuditorInterface
{
    /**
     * Manually record an audit entry.
     */
    public function record(string $action, ?string $resourceType = null, ?string $resourceId = null, array $metadata = []): void;

    /**
     * Search the audit trail.
     */
    public function search(array $criteria): array;
}
```

## Integration Strategy
- **Upward**: Consumes `CORE-03` to listen for `HubEvent` types and `CORE-19` for persistence.
- **Downward**: Spoke applications use the `Auditable` trait on their domain models.
- **Performance**: High-volume audits are queued (referencing HUB-10) to avoid blocking the user request.

## CI Verification Criteria
- **Tamper Detection**: Must provide a utility that verifies the integrity of the audit chain (hashes must match).
- **Zero-Drop Policy**: Under high load (1000 logs/sec), the system must successfully buffer and write all logs without data loss.
- **PII Stripping**: Must verify that sensitive fields (passwords, SSNs) are automatically filtered from the `changes` payload.

## SemVer Impact
**Minor**. Essential for enterprise-grade compliance and security.
