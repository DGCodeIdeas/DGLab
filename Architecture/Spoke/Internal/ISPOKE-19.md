# PHASE ISPOKE-19: Sovereign SLA Monitor

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign SLA Monitor — `SovereignStack\Internal\SlaMonitor`. Real-time + historical SLA-compliance
monitoring for internal and external services: uptime tracking, incident-timeline visualization, SLA
breach alerting.

## Description
ISPOKE-19 consumes health signals from **HUB-15 (Sovereign Pulse — Health Check & Service Discovery)**
and the health dashboard (ISPOKE-03) to compute SLA attainment per service and per tenant. It maintains
rolling windows (e.g. 30/90/365-day availability), detects breaches against configured SLA targets, and
raises alerts via **HUB-12 (Sovereign Notify)**. It is a **reporting/alerting** layer over HUB-15 — it
does not perform health checks itself.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-15 (Sovereign Pulse — health signal source), ISPOKE-03 (Sovereign Health &
  Observability Dashboard — timeline source), HUB-12 (Sovereign Notify — breach alerts), HUB-02
  (Sovereign Cache — hot SLA windows), CORE-19 (Database — SLA target + breach store), HUB-21 (Sovereign
  Nexus — tenancy scoping), HUB-06 (Sovereign Auditor — breach record).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `SlaTarget` | `final readonly class` | `service`, `tenant_id`, `window`, `target_pct` (e.g. 99.95). |
| `SlaMonitorInterface` | interface | `attainment(string $service, string $tenantId, string $window): float`, `breaches(string $tenantId): BreachPage`. |
| `AvailabilityWindow` | class | Rolling-window aggregator backed by HUB-02; recomputed from HUB-15 samples. |
| `BreachDetector` | class | Compares attainment vs `SlaTarget`; emits HUB-12 alerts. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\SlaMonitor;

interface SlaMonitorInterface
{
    public function attainment(string $service, string $tenantId, string $window): float;
    public function breaches(string $tenantId): BreachPage;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE sla_targets (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    service     VARCHAR(255) NOT NULL,
    window      ENUM('30d','90d','365d') NOT NULL,
    target_pct  DECIMAL(5,2) NOT NULL CHECK (target_pct > 0 AND target_pct <= 100),
    CONSTRAINT fk_sla_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_service_window (tenant_id, service, window)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sla_breaches (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    service     VARCHAR(255) NOT NULL,
    window      VARCHAR(16) NOT NULL,
    detected_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_breaches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_breaches_tenant_detected (tenant_id, detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-15/ISPOKE-03/HUB-12/HUB-02/CORE-19/HUB-21/HUB-06 through the container
(CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. SLA computation is read-only over HUB-15 signals; it cannot influence health state.
2. Breach alerts carry the service + window + measured attainment (HUB-12); no secret data is emitted.
3. Windows are tenancy-scoped (HUB-21); cross-tenant SLA is not computable from one tenant's view.
4. Breach records are immutable audit rows (HUB-06).

## CI Verification Criteria
- Unit: `AvailabilityWindow` computes 99.9% over a seeded sample set with one downtime blip;
  `BreachDetector` fires exactly when `attainment < target_pct`.
- Integration (MySQL 8 (InnoDB)): seeding `sla_targets` + HUB-15 samples yields the expected
  `sla_breaches` rows.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `BreachDetector`.
