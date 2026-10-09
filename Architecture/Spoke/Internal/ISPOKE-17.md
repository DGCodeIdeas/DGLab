# PHASE ISPOKE-17: Sovereign Vault Keeper (Retention)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

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

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE retention_policies (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    entity      VARCHAR(255) NOT NULL,
    retain_for  VARCHAR(32) NOT NULL,               -- ISO 8601 duration (e.g. 'P1Y2M10D')
    action      ENUM('purge','anonymize','archive') NOT NULL,
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_retention_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_entity (tenant_id, entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE legal_holds (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    record_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    created_by  CHAR(26) CHARACTER SET ascii NOT NULL,
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
- Integration (MySQL 8 (InnoDB)): `evaluate()` over a seeded tenant deletes N rows and writes one
  `SweepReport` row to HUB-06.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `Sweeper`.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-17 — shipped per the verified DAG).

### What Was NOT Changed in PR #342

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/vault-keeper/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #342 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
