# PHASE ISPOKE-25: Sovereign Responder (Incident Response)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Responder — `SovereignStack\Internal\Responder`. End-to-end incident-response management:
detection alerting, triage workflow, containment actions, forensic data collection, post-mortem
documentation, metrics tracking. The operational layer above ISPOKE-15 (SOC) and ISPOKE-21 (Scan).

## Description
ISPOKE-25 is the incident console. It ingests alerts (from ISPOKE-15 SOC, ISPOKE-21 Scan critical
findings, HUB-12 Notify escalations), drives a triage→containment→eradication→recovery workflow, collects
forensic snapshots (read-only copies of relevant state via CORE-19/HUB-11), and produces a post-mortem.
Containment actions that mutate live systems are gated behind explicit operator confirmation and audited
(HUB-06). It is the **coordination** layer — it calls other components; it does not itself detect or
remediate autonomously.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** ISPOKE-15 (Sovereign SOC — detection source), ISPOKE-07 (Sovereign Webhook Nexus — alert
  ingestion), ISPOKE-21 (Sovereign Scan — critical findings), HUB-12 (Sovereign Notify — paging), HUB-06
  (Sovereign Auditor — every action audited), HUB-04 (Sovereign Identity — responder authn), CORE-19
  (Database — incident store), HUB-11 (Sovereign Cloud Storage — forensic snapshot sink), HUB-15
  (Sovereign Pulse — system health during incident), HUB-21 (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Incident` | `final readonly class` | `tenant_id`, `severity`, `status` (`triaged`\|`contained`\|`eradicated`\|`recovered`\|`closed`), `timeline`. |
| `ResponderInterface` | interface | `open(array $alert): string`, `contain(string $incidentId, Containment $c): void`, `postMortem(string $incidentId): Document`. |
| `TriageWorkflow` | class | State machine over `Incident.status`; gates mutating actions. |
| `ForensicCollector` | class | Read-only snapshots to HUB-11 for later analysis. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Responder;

interface ResponderInterface
{
    public function open(array $alert): string;
    public function contain(string $incidentId, Containment $action): void;
    public function postMortem(string $incidentId): Document;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE incidents (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id    ULID NOT NULL REFERENCES tenants(id),
    severity     text NOT NULL CHECK (severity IN ('sev1','sev2','sev3','sev4')),
    status       text NOT NULL DEFAULT 'triaged'
                      CHECK (status IN ('triaged','contained','eradicated','recovered','closed')),
    opened_by    ULID NOT NULL,
    opened_at    timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE incident_timeline (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    incident_id  ULID NOT NULL REFERENCES incidents(id),
    event        jsonb NOT NULL,
    created_at   timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves ISPOKE-15/ISPOKE-07/ISPOKE-21/HUB-12/HUB-06/HUB-04/CORE-19/HUB-11/HUB-15/HUB-21
through the container (CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. Every containment action is audited (HUB-06) with `opened_by`/`operator` and a before/after record —
   incident response is itself observable.
2. Forensic snapshots are written to HUB-11 read-only; they never alter the live system.
3. Mutating containment requires explicit operator confirmation, never automatic execution.
4. Incidents are tenancy-scoped (HUB-21); a responder cannot act across tenants.

## CI Verification Criteria
- Unit: `TriageWorkflow` rejects an illegal status transition (e.g. `triaged → closed` skipping
  `contained`); `ForensicCollector` writes a snapshot without modifying source rows.
- Integration (MySQL 16): `open()` writes `incidents`; `contain()` appends an `incident_timeline`
  event and advances status.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `TriageWorkflow`.
