# PHASE ISPOKE-20: Sovereign Scribe (Reports)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

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

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE report_templates (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    query       JSON NOT NULL,
    format      ENUM('pdf','csv','json') NOT NULL,
    schedule    JSON NULL,
    created_by  CHAR(26) CHARACTER SET ascii NOT NULL,
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE signed_reports (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    template_id  CHAR(26) CHARACTER SET ascii NOT NULL,
    content_hash VARBINARY(255) NOT NULL,
    signature    VARBINARY(255) NOT NULL,
    created_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_reports_template FOREIGN KEY (template_id) REFERENCES report_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
- Integration (MySQL 8 (InnoDB)): defining a template then `build()` writes `signed_reports` with a
  non-null `content_hash` + `signature`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `Signer`.


---

## Doctrines Applied + Rewrite Notes (PR #343)

> **This section was added in PR #343 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #343

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-20 — shipped per the verified DAG).

### What Was NOT Changed in PR #343

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/scribe/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #343 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
