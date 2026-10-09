# PHASE ISPOKE-16: Sovereign Transporter (Import/Export)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only application — VPN/bastion, never via the public Bridge)

## Component Name
Sovereign Transporter — `SovereignStack\Internal\Transporter`. Bulk import/export of system entities
(users, content, configurations) with mapping, transformation, and validation pipelines.

## Description
ISPOKE-16 is the bulk data-movement console. It reads source files (CSV, JSON, NDJSON, Parquet
metadata) or streams from a queue, maps source columns to the target entity schema, runs a
transformation pipeline (typing, normalization, reference resolution), validates against the target
entity's invariants, and writes via the canonical persistence path (CORE-19 Database over MySQL
8 (InnoDB) / JSON, per ADR-013). Exports are the inverse: a configured projection over entities is
serialized to the requested format and streamed to object storage (HUB-11 Cloud Storage) or returned inline.

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
- **Runtime:** PHP 8.4, `league/csv` (dev only, for CSV mapping) or an equivalent streaming parser;
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
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type is materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() is emitted by the app/DBAL, not the engine.
CREATE TABLE transporter_jobs (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    kind        ENUM('import','export') NOT NULL,
    spec        JSON NOT NULL,                       -- serialized ImportJob/ExportJob
    status      ENUM('queued','running','succeeded','failed','rolled_back') NOT NULL DEFAULT 'queued',
    rows_total  INT NOT NULL DEFAULT 0,
    rows_done   INT NOT NULL DEFAULT 0,
    errors      JSON NOT NULL,                       -- default applied by the DBAL (JSON_ARRAY())
    created_by  CHAR(26) CHARACTER SET ascii NOT NULL,  -- operator (HUB-04)
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    -- Partial-index equivalent per ADR-013: generated boolean + regular index (MySQL has no
    -- SQL-standard partial indexes; DriverInterface::supports('partial_index') is false).
    is_pending  TINYINT(1) GENERATED ALWAYS AS (status <> 'succeeded') STORED NOT NULL,
    CONSTRAINT fk_transporter_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_transporter_tenant_pending (tenant_id, is_pending)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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


---

## Doctrines Applied + Rewrite Notes (PR #342)

> **This section was added in PR #342 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #342

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-16 — shipped per the verified DAG).

### What Was NOT Changed in PR #342

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/transporter/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #342 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
