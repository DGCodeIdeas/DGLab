# PHASE ISPOKE-24: Sovereign Restore (Backup)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Restore — `SovereignStack\Internal\Restore`. Centralised backup management: schedule
configuration, retention-policy management, restore-workflow initiation, backup-integrity verification.

## Description
ISPOKE-24 manages the backup lifecycle. It schedules snapshots of datastores (delegated to **DEPLOY-02**
datastore provisioning) to cold object storage (**HUB-11** Cloud Storage) with keys custodied by
**HUB-20** (Sovereign Vault), verifies integrity (checksum + envelope-decrypt probe via CORE-16), and
initiates restore by driving the import pipeline (**ISPOKE-16** Sovereign Transporter). It is the
operator console + orchestration; the actual byte movement is DEPLOY-02/HUB-11.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-11 (Sovereign Cloud Storage — backup sink), HUB-20 (Sovereign Vault — key custody),
  CORE-16 (Encryption Envelope — integrity probe), CORE-19 (Database — backup-catalog store), HUB-03
  (Sovereign Asset Engine — asset/binary snapshots), HUB-15 (Sovereign Pulse — job health), HUB-06
  (Sovereign Auditor — every restore audited), HUB-21 (Sovereign Nexus — tenancy scoping), ISPOKE-14
  (Sovereign Nexus console — hosts shared backup config), ISPOKE-16 (import pipeline for restore).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `BackupJob` | `final readonly class` | `tenant_id`, `target`, `schedule`, `retention`. |
| `RestoreConsoleInterface` | interface | `schedule(BackupJob $j): void`, `verify(string $backupId): VerifyReport`, `restore(string $backupId, string $target): JobId`. |
| `IntegrityVerifier` | class | Checksum + CORE-16 decrypt-probe on a stored backup. |
| `RestoreDriver` | class | Calls ISPOKE-16 `import()` with the backup as source. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Restore;

interface RestoreConsoleInterface
{
    public function schedule(BackupJob $job): void;
    public function verify(string $backupId): VerifyReport;
    public function restore(string $backupId, string $target): string;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE backup_catalog (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    target       VARCHAR(255) NOT NULL,
    object_ref   VARCHAR(512) NOT NULL,             -- HUB-11 key
    checksum     VARBINARY(255) NOT NULL,
    verified_at  TIMESTAMP(6) NULL,
    created_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_backup_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_backup_tenant_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-11/HUB-20/CORE-16/CORE-19/HUB-03/HUB-15/HUB-06/HUB-21/ISPOKE-14/ISPOKE-16 through
the container (CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. A restore is the highest-risk action — it is always audited (HUB-06) with `created_by` and a
   signed backup reference (CORE-16).
2. Backups are envelope-encrypted (CORE-16); `IntegrityVerifier` proves decryptability before any
   restore proceeds.
3. Restore targets are tenancy-scoped (HUB-21); a backup cannot be restored into another tenant.
4. `verify()` is read-only — it never mutates the live system.

## CI Verification Criteria
- Unit: `IntegrityVerifier` passes a good checksum + decrypt-probe and fails a flipped checksum.
- Integration (MySQL 8 (InnoDB) + HUB-11 stub): `schedule()` writes `backup_catalog`; `restore()` calls
  ISPOKE-16 `import()` and returns a job id.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `IntegrityVerifier`.
