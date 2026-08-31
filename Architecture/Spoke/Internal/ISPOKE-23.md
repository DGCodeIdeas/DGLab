# PHASE ISPOKE-23: Sovereign Role Play (Simulation)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Role Play — `SovereignStack\Internal\RolePlay`. Sandbox for testing RBAC configurations before
deployment: "what-if" analysis of permission changes, role preview, conflict detection.

## Description
ISPOKE-23 lets administrators preview the effect of a proposed RBAC change (new role, permission grant,
role merge) against a **simulated** subject, without touching live policy in **HUB-05 (Sovereign
Guardian)**. It clones the current policy graph, applies the candidate change in the clone, and reports
the resulting effective permissions plus any conflicts (e.g. a Permission X granted to a role that is
denied elsewhere, or a separation-of-duties violation). It is read-only against production and writes
only to its own simulation store.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-05 (Sovereign Guardian — live policy source + the target it previews), ISPOKE-04
  (Sovereign Staff Hub — the staff identity entry gate), CORE-19 (Database — simulation store), HUB-06
  (Sovereign Auditor — simulation audit), HUB-21 (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `PolicyPatch` | `final readonly class` | A candidate change: `grant`\|`revoke`\|`merge` on a role/permission. |
| `RolePlayInterface` | interface | `simulate(PolicyPatch $p, string $tenantId): SimulationResult`, `conflicts(string $tenantId): ConflictPage`. |
| `PolicyCloner` | class | Snapshots HUB-05 graph into the simulation store. |
| `ConflictDetector` | class | Computes effective perms + flags SoD/deny conflicts. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\RolePlay;

interface RolePlayInterface
{
    public function simulate(PolicyPatch $patch, string $tenantId): SimulationResult;
    public function conflicts(string $tenantId): ConflictPage;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE sim_policies (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    snapshot    JSON NOT NULL,                       -- cloned HUB-05 graph
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_sim_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sim_results (
    id               CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    sim_id           CHAR(26) CHARACTER SET ascii NOT NULL,
    patch            JSON NOT NULL,
    effective_perms  JSON NOT NULL,
    conflicts        JSON NOT NULL,                   -- default applied by the DBAL (JSON_ARRAY())
    created_at       TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_results_sim FOREIGN KEY (sim_id) REFERENCES sim_policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-05/ISPOKE-04/CORE-19/HUB-06/HUB-21 through the container (CORE-02). **Downward:**
UI in ISPOKE-01.

## Security Properties
1. Simulation is strictly read-only against HUB-05; the clone is isolated and never promoted without an
   explicit admin action outside ISPOKE-23.
2. Conflict detection surfaces separation-of-duties violations before a real grant — the whole point of
   the sandbox.
3. Simulations are tenancy-scoped (HUB-21); a clone cannot read another tenant's policy.
4. Every simulation is audited (HUB-06) with the proposing operator id.

## CI Verification Criteria
- Unit: `ConflictDetector` flags a deny/grant collision and an SoD violation on seeded graphs; a clean
  patch yields zero conflicts.
- Integration (MySQL 8 (InnoDB)): `simulate()` writes `sim_results` with the expected `effective_perms`
  and `conflicts`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `ConflictDetector`.
