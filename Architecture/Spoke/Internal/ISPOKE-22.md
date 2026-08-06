# PHASE ISPOKE-22: Sovereign Registrar (Compliance)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Registrar — `SovereignStack\Internal\Registrar`. Automated generation of compliance reports
for regulatory frameworks (SOC 2, GDPR, HIPAA, PCI-DSS): evidence collection, control mapping, audit-trail
export.

## Description
ISPOKE-22 assembles framework-specific compliance packages. It maps the controls of each framework to
the underlying evidence sources (HUB-06 audit log, ISPOKE-10 compliance foundation, ISPOKE-20 signed
reports, ISPOKE-17 retention records) and produces a control-mapped dossier. It is an **aggregation and
mapping** layer — it collects evidence others produce; it does not itself enforce controls.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** ISPOKE-10 (Sovereign Compliance — control foundation), ISPOKE-20 (Sovereign Scribe — signed
  report packages), ISPOKE-17 (Sovereign Vault Keeper — retention evidence), HUB-06 (Sovereign Auditor —
  evidence source), HUB-20 (Sovereign Vault — evidence signing), HUB-31 (Real-Time Analytics — *proposed,
  pending* — compliance-metric emission), CORE-19 (Database — control-mapping store), HUB-21 (Sovereign
  Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Framework` | `final readonly class` | `id` (`soc2`\|`gdpr`\|`hipaa`\|`pci_dss`), `controls` (list of `ControlRef`). |
| `RegistrarInterface` | interface | `assemble(string $framework, string $tenantId): ComplianceDossier`, `export(string $dossierId): SignedPackage`. |
| `ControlMapper` | class | Joins framework controls → evidence sources. |
| `EvidenceCollector` | class | Pulls + signatures evidence via HUB-06/HUB-20/ISPOKE-20. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Registrar;

interface RegistrarInterface
{
    public function assemble(string $framework, string $tenantId): ComplianceDossier;
    public function export(string $dossierId): SignedPackage;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE compliance_control_maps (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id    ULID NOT NULL REFERENCES tenants(id),
    framework    text NOT NULL CHECK (framework IN ('soc2','gdpr','hipaa','pci_dss')),
    control_ref  text NOT NULL,
    evidence_src text NOT NULL,
    UNIQUE (tenant_id, framework, control_ref)
);
CREATE TABLE compliance_dossiers (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id    ULID NOT NULL REFERENCES tenants(id),
    framework    text NOT NULL,
    generated_at timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves ISPOKE-10/ISPOKE-20/ISPOKE-17/HUB-06/HUB-20/HUB-31/CORE-19/HUB-21 through the
container (CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. Evidence is collected read-only; ISPOKE-22 cannot alter the systems it reports on.
2. Dossiers are signed (HUB-20) and the signature recorded for verifier-side integrity.
3. Control maps are tenancy-scoped (HUB-21); a dossier never spans tenants.
4. Every assembly is audited (HUB-06) with the operator id.

## CI Verification Criteria
- Unit: `ControlMapper` maps a seeded SOC 2 control set to the expected evidence sources; missing
  evidence is flagged, not silently dropped.
- Integration (MySQL 16): `assemble()` for a seeded tenant writes a `compliance_dossiers` row;
  `export()` returns a signed package whose signature verifies via HUB-20.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `ControlMapper`.
