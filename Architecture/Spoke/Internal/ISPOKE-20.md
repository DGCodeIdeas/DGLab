# PHASE ISPOKE-20: Sovereign Scribe (Reports)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Scribe — `SovereignStack\Internal\Scribe`. Configurable audit-report generation: custom
report templates, scheduled delivery, signed export of audit packages.

## Description
ISPOKE-20 lets compliance officers define report templates over the audit log (HUB-06) and entity
stores (CORE-19), schedule their delivery, and export the result as a tamper-evident package. Each
export is hashed and signed with a key custodied by **HUB-20 (Sovereign Vault)**; the signature is
recorded so a recipient can verify integrity later. It is the human-facing reporting layer over HUB-06 —
it does not itself store audit events.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-06 (Sovereign Auditor — audit data source), CORE-19 (Database — entity projections),
  HUB-20 (Sovereign Vault — signing keys), HUB-12 (Sovereign Notify — scheduled delivery), HUB-02
  (Sovereign Cache — rendered report cache), ISPOKE-10 (Sovereign Compliance — template governance),
  HUB-31 (Real-Time Analytics — *proposed, pending* — report-metric emission), HUB-21 (Sovereign Nexus
  — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell), ISPOKE-22 (Sovereign Registrar — consumes report packages).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `ReportTemplate` | `final readonly class` | `tenant_id`, `query`, `format` (`pdf`\|`csv`\|`json`), `schedule`. |
| `ReportBuilderInterface` | interface | `build(ReportTemplate $t): SignedReport`, `list(string $tenantId): TemplatePage`. |
| `Signer` | class | Hashes + signs the rendered package via HUB-20; records the signature. |
| `DeliveryScheduler` | class | Enqueues HUB-12 delivery on the template's `schedule`. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Scribe;

interface ReportBuilderInterface
{
    public function build(ReportTemplate $template): SignedReport;
    public function list(string $tenantId): TemplatePage;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE report_templates (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id   ULID NOT NULL REFERENCES tenants(id),
    query       jsonb NOT NULL,
    format      text NOT NULL CHECK (format IN ('pdf','csv','json')),
    schedule    jsonb,
    created_by  ULID NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE signed_reports (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    template_id ULID NOT NULL REFERENCES report_templates(id),
    content_hash bytea NOT NULL,
    signature    bytea NOT NULL,
    created_at   timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves HUB-06/CORE-19/HUB-20/HUB-12/HUB-02/ISPOKE-10/HUB-31/HUB-21 through the container
(CORE-02). **Downward:** UI in ISPOKE-01; packages consumed by ISPOKE-22.

## Security Properties
1. Reports are built read-only over HUB-06/CORE-19; they cannot mutate source data.
2. Every package is signed (HUB-20) and the signature recorded — recipient verification is possible
   without trusting the builder.
3. Templates are tenancy-scoped (HUB-21); a tenant's report cannot query another tenant's rows.
4. Delivery goes through HUB-12 with the operator's `created_by` for non-repudiation.

## CI Verification Criteria
- Unit: `Signer` produces a signature that `HUB-20.verify()` accepts and that fails verification after
  a single byte of the package is flipped.
- Integration (MySQL 16): defining a template then `build()` writes `signed_reports` with a
  non-null `content_hash` + `signature`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `Signer`.
