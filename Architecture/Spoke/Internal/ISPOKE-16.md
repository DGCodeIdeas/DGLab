# PHASE ISPOKE-16: Sovereign Transporter (Import/Export)

## Tier
Internal Spoke (Staff-only application — VPN/bastion, never via the public Bridge)

## Component Name
Sovereign Transporter — `SovereignStack\Internal\Transporter`. Bulk import/export of system entities
(users, content, configurations) with mapping, transformation, and validation pipelines.

## Description
ISPOKE-16 is the bulk data-movement console. It reads source files (CSV, JSON, NDJSON, Parquet
metadata) or streams from a queue, maps source columns to the target entity schema, runs a
transformation pipeline (typing, normalization, reference resolution), validates against the target
entity's invariants, and writes via the canonical persistence path (CORE-19 Database over MySQL 8
(InnoDB) / JSON). Exports are the inverse: a configured projection over entities is serialized to the
requested format and streamed to object storage (HUB-11 Cloud Storage) or returned inline.

The component is **not** an ETL platform. It deliberately scopes itself to operator-initiated, audited,
entity-level migrations — not continuous replication. Long-running jobs are delegated to HUB-10
(Sovereign Queue); the Transporter only orchestrates and reports.

## Build Status
✅ **Documented — ready for implementation.** Promoted from placeholder blueprint (Finding 13) on
2026-08-05.

## Dependency Status
- **Upward (consumes):** CORE-19 (Database), CORE-16 (Encryption Envelope — for credential/secret
  columns in transit), HUB-10 (Sovereign Queue — async job execution), HUB-11 (Sovereign Cloud
  Storage — artifact sink), HUB-04 (Sovereign Identity — operator authn), HUB-05 (Sovereign Guardian —
  RBAC for export scopes), HUB-06 (Sovereign Auditor — every job is an audited action), HUB-15
  (Sovereign Pulse — health of the job worker), ISPOKE-01 (Sovereign Command Center — hosts the UI).
- **Downward (consumed by):** ISPOKE-01 (UI shell), ISPOKE-24 (Sovereign Restore — backup restore uses
  the import pipeline).
- **Runtime:** PHP 8.3, `league/csv` (dev only, for CSV mapping) or an equivalent streaming parser;
  MySQL 8 (InnoDB) (ADR-013); Redis 7 (ADR-006) for job state.

## Architectural Design

### Class Map

| Class | Kind | Responsibility |
|---|---|---|
| `TransporterInterface` | interface | `import(JobSpec $spec): JobId`, `export(JobSpec $spec): JobId`, `status(JobId $id): JobStatus`. |
| `ImportJob` / `ExportJob` | `final readonly class` | Value objects describing source, mapping, validation rules, target. |
| `MappingEngine` | class | Applies column→field mappings and transformations. |
| `ValidationPipeline` | class | Runs entity invariants; collects `ValidationError` list (never throws mid-batch). |
| `JobWorker` | class | Consumes HUB-10 messages; drives the pipeline; reports progress to HUB-15. |

### Key interface contract

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Internal\Transporter;

use SovereignStack\Core\Database\DatabaseInterface;

interface TransporterInterface
{
    /** Enqueue an import. Returns the queue job id (HUB-10). */
    public function import(ImportJob $job): string;

    /** Enqueue an export. Returns the queue job id (HUB-10). */
    public function export(ExportJob $job): string;

    /** Poll job status; pulls progress from HUB-15 when the worker is live. */
    public function status(string $jobId): JobStatus;
}
```

## Data Model (MySQL 8 (InnoDB) / JSON / ULID)

```sql
CREATE TABLE transporter_jobs (
    id          ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id   ULID NOT NULL REFERENCES tenants(id),
    kind        text NOT NULL CHECK (kind IN ('import','export')),
    spec        jsonb NOT NULL,            -- serialized ImportJob/ExportJob
    status      text NOT NULL DEFAULT 'queued'
                    CHECK (status IN ('queued','running','succeeded','failed','rolled_back')),
    rows_total  integer NOT NULL DEFAULT 0,
    rows_done   integer NOT NULL DEFAULT 0,
    errors      jsonb NOT NULL DEFAULT '[]'::jsonb,
    created_by  ULID NOT NULL,             -- operator (HUB-04)
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON transporter_jobs (tenant_id, status) WHERE status <> 'succeeded';
```

## Integration Strategy
**Upward:** resolves CORE-19/CORE-16/HUB-10/HUB-11/HUB-04/HUB-05/HUB-06/HUB-15 through the container
(CORE-02). **Downward:** renders its UI inside ISPOKE-01; ISPOKE-24 calls `import()` for restore.

## Security Properties
1. Every job is an audited action (HUB-06) with `created_by` and a signed spec hash (CORE-16).
2. Export scopes are RBAC-gated (HUB-05); PII columns are masked unless the operator holds the
   `export:pii` permission.
3. Secret/credential columns are envelope-encrypted (CORE-16) before write; plaintext never touches
   object storage (HUB-11).
4. Rollback: a failed import runs compensating deletes within the same tenant transaction; partial
   imports are never left dangling.

## CI Verification Criteria
- Unit: `MappingEngine` round-trips a 5-column CSV→entity with 3 transforms; `ValidationPipeline`
  collects exactly the expected `ValidationError` set for an invalid row.
- Integration (docker-compose MySQL 8 (InnoDB)): `import()` of 1,000 rows via HUB-10 worker leaves
  `transporter_jobs.status = 'succeeded'` and `rows_done = rows_total`.
- Static: phpstan `level: max` clean over `src/`.
- Coverage: ≥95% branch coverage on `MappingEngine` and `ValidationPipeline`.
