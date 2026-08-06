# PHASE ISPOKE-17: Sovereign Vault Keeper (Retention)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Vault Keeper — `SovereignStack\Internal\VaultKeeper`. Policy-based data lifecycle:
retention schedules, automated purging, legal-hold management, and archival-workflow orchestration.

## Description
ISPOKE-17 enforces data-retention policy across tenants. Policies are declarative rules
(`entity → retain_for → action`) evaluated by a sweeper that walks eligible tables in MySQL 8 (InnoDB) and
either purges, anonymizes, or archives rows past their retention horizon. Legal holds pin records so the
sweeper skips them. Archival ships pinned/aged rows to cold object storage (HUB-11) as encrypted
blobs (CORE-16) before optional purge.

It is a **policy engine**, not a backup system (that is ISPOKE-24). It operates on live data under
retention rules; it never restores.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** CORE-19 (Database), CORE-16 (Encryption Envelope), HUB-20 (Sovereign Vault — key
  custody for archive blobs), HUB-11 (Sovereign Cloud Storage — cold archive sink), HUB-06 (Sovereign
  Auditor — every purge is audited), HUB-15 (Sovereign Pulse — sweeper health), HUB-21 (Sovereign Nexus
  — tenancy scoping), ISPOKE-10 (Sovereign Compliance — policy source), HUB-31 (Real-Time Analytics —
  *proposed, pending* — retention-metric emission).
- **Downward:** ISPOKE-01 (UI shell), ISPOKE-22 (Sovereign Registrar — reads retention evidence).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `RetentionPolicy` | `final readonly class` | `entity`, `retain_for` (interval), `action` (`purge`\|`anonymize`\|`archive`), `legal_hold_tag`. |
| `RetentionEngineInterface` | interface | `evaluate(ULID $tenantId): SweepReport`, `placeHold(ULID $recordId, string $reason): void`, `releaseHold(ULID $recordId): void`. |
| `Sweeper` | class | Paginated walker over eligible rows; applies action inside a tenant transaction. |
| `ArchiveSink` | class | Encrypts + streams aged rows to HUB-11. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\VaultKeeper;

interface RetentionEngineInterface
{
    public function evaluate(string $tenantId): SweepReport;
    public function placeHold(string $recordId, string $reason): void;
    public function releaseHold(string $recordId): void;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE retention_policies (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id   ULID NOT NULL REFERENCES tenants(id),
    entity      text NOT NULL,
    retain_for  interval NOT NULL,
    action      text NOT NULL CHECK (action IN ('purge','anonymize','archive')),
    created_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, entity)
);
CREATE TABLE legal_holds (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    record_id   ULID NOT NULL,
    reason      text NOT NULL,
    created_by  ULID NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves CORE-19/CORE-16/HUB-20/HUB-11/HUB-06/HUB-15/HUB-21 through the container (CORE-02).
**Downward:** UI in ISPOKE-01; evidence consumed by ISPOKE-22.

## Security Properties
1. Purge is non-destructive until the tenant transaction commits; a swept batch is audited (HUB-06)
   with before/after counts before deletion.
2. Legal holds are immutable until explicitly released by a holder with `retention:release` (HUB-05).
3. Archive blobs are envelope-encrypted (CORE-16) with keys custodied by HUB-20; plaintext never leaves
   the pod.
4. Every sweeper run is tenancy-scoped (HUB-21) — cross-tenant data is never visible.

## CI Verification Criteria
- Unit: `Sweeper` purges exactly the rows past `retain_for` and skips any with an active `legal_holds`
  row; a held row is never touched.
- Integration (MySQL 16): `evaluate()` over a seeded tenant deletes N rows and writes one
  `SweepReport` row to HUB-06.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `Sweeper`.
