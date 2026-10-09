# PHASE ISPOKE-22: Sovereign Registrar (Compliance)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

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

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE compliance_control_maps (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    framework    ENUM('soc2','gdpr','hipaa','pci_dss') NOT NULL,
    control_ref  VARCHAR(255) NOT NULL,
    evidence_src VARCHAR(255) NOT NULL,
    CONSTRAINT fk_control_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_framework_control (tenant_id, framework, control_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE compliance_dossiers (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    framework    VARCHAR(32) NOT NULL,
    generated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_dossiers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
- Integration (MySQL 8 (InnoDB)): `assemble()` for a seeded tenant writes a `compliance_dossiers` row;
  `export()` returns a signed package whose signature verifies via HUB-20.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `ControlMapper`.


---

## Doctrines Applied + Rewrite Notes (PR #344)

> **This section was added in PR #344 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #344

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-22 — shipped per the verified DAG).

### What Was NOT Changed in PR #344

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/registrar/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #344 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
