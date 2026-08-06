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

## Data Model (MySQL 16)

```sql
CREATE TABLE sim_policies (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id   ULID NOT NULL REFERENCES tenants(id),
    snapshot    jsonb NOT NULL,          -- cloned HUB-05 graph
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE sim_results (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    sim_id       ULID NOT NULL REFERENCES sim_policies(id),
    patch        jsonb NOT NULL,
    effective_perms jsonb NOT NULL,
    conflicts    jsonb NOT NULL DEFAULT '[]'::jsonb,
    created_at   timestamptz NOT NULL DEFAULT now()
);
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
- Integration (MySQL 16): `simulate()` writes `sim_results` with the expected `effective_perms`
  and `conflicts`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `ConflictDetector`.
