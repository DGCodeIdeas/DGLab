# PHASE HUB-06: Audit Log & Activity Tracker

## Tier
Hub (Shared Services)

## Resolves
This is the audit component `BRIDGE-01` depends on for its "Tier-Crossing" metadata flag and its
fail-closed audit requirement (`02_EXEMPLARS/BRIDGE-01.md` §5) — this rewrite makes that dependency
explicit and adds the availability contract `BRIDGE-01` needs from it.

## Component Name
Sovereign Auditor

## Description
Centralized logging for system-wide activity: a tamper-evident record of "who did what, and when"
across the polyrepo stack, with searchable audit trails for compliance and forensics.

## Build Status
🔴 **Blocked** on `CORE-19` (DBAL), `HUB-04` (Identity), `CORE-03` (Event Dispatcher — the one Core
component already implemented, see `packages/core/event-dispatcher/`).

## Dependency Status
- **Upward:** `CORE-19`, `HUB-04`, `CORE-03`. *(Matches taxonomy.)*
- **Downward:** `BRIDGE-01` (tier-crossing audit — critical), `HUB-16` (release gating), `ISPOKE-01`
  (Audit Viewer UI), `HUB-20` (Vault access logging).

## Architectural Design
- **AuditManager** — listens for system events, decides which require auditing.
- **LogWriter** — writes audit records asynchronously to a dedicated store.
- **ActivityTracker** — trait for Spoke models to auto-track CRUD operations.
- **AuditViewer** — Hub-level query/filter API by user, tenant, or action type.

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

```php
namespace SovereignStack\Hub\Contracts;

interface AuditorInterface
{
    public function record(string $action, ?string $resourceType = null, ?string $resourceId = null, array $metadata = []): void;
    public function search(array $criteria): array;
}
```

## Availability Contract (new — required by BRIDGE-01)
`BRIDGE-01`'s fail-closed policy means a Bridge instance that can't reach this service must reject the
request rather than skip logging. That makes `record()`'s availability, not just its correctness, a
security-relevant property. This blueprint therefore commits to:
- `record()` for tier-crossing events must have a documented, bounded timeout (not "best effort").
- A synchronous write path for tier-crossing events specifically (not the general `HUB-10`-queued path
  used for lower-stakes audit entries) — logging a Bridge crossing after the fact defeats the purpose
  if the process crashes between the crossing and an async flush.

## Integration Strategy
- **Upward:** `CORE-03` for `HubEvent` listening, `CORE-19` for persistence.
- **Downward:** Spoke applications use the `Auditable` trait. High-volume, non-critical audits may be
  queued via `HUB-10`; tier-crossing audits (Bridge) use the synchronous path above.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Tamper detection | Utility test: mutate one record in a chained-hash fixture set; assert the chain-verification utility flags exactly that record and everything downstream of it. |
| Zero-drop under load | Load test at a stated, reproducible rate (e.g., `k6` generating N logs/sec against a seeded fixture) — report the actual sustained rate the implementation handles rather than asserting "1000 logs/sec" unmeasured (Finding 10). |
| PII stripping | Unit test asserting a `changes` payload containing a `password` or `ssn` key is redacted before persistence, across at least one nested-object case, not just top-level keys. |
| Bridge audit synchronous-path latency | Measured directly as part of `BRIDGE-01`'s own DTO-transformation-latency benchmark (see `BRIDGE-01.md`) — not a separate unmeasured claim. |

## CI Verification Criteria
- Tamper-chain verification test, blocking.
- PII redaction test including nested payloads, blocking.
- Load test with a stated, reproducible target rate and actual measured throughput reported in the
  test output (not just pass/fail).
- Synchronous tier-crossing write path has explicit test coverage separate from the general
  queued-audit path.

## SemVer Impact
**Minor** for general audit features; **treat as Major** if the synchronous tier-crossing path's
timeout or failure behavior changes, since `BRIDGE-01`'s security posture depends on it.
