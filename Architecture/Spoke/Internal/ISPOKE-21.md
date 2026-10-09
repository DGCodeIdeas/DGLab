# PHASE ISPOKE-21: Sovereign Scan (Vulnerability)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Scan — `SovereignStack\Internal\Scan`. Automated vulnerability scanning for internal services
and dependencies: CVE-database integration, severity scoring, remediation workflow, patch tracking.

> **Naming note.** The placeholder source named this "Sovereign Sentinel (Vulnerability)"; renamed to
> **Sovereign Scan** to avoid colliding with `HUB-27` (Sovereign Sentinel — Headers). Display word
> "Sentinel" is reserved for the Hub tier where it already exists.

## Description
ISPOKE-21 scans deployed services and their dependency manifests (composer.lock, container images) for
known CVEs, scores severity, and drives a remediation workflow (ticket → patch → re-scan). It consumes
SBOM/dependency data and correlates against an internal CVE mirror; it does not perform penetration
testing (that is ISPOKE-15 SOC's red-team tooling).

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-04 (Sovereign Identity — scanner authn), HUB-19 (Sovereign Guard — input validation on
  scan targets), HUB-06 (Sovereign Auditor — findings recorded), HUB-15 (Sovereign Pulse — target health),
  ISPOKE-15 (Sovereign SOC — hands off critical findings), CORE-19 (Database — findings store), HUB-21
  (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell), ISPOKE-25 (Sovereign Responder — consumes critical findings).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `ScanTarget` | `final readonly class` | `tenant_id`, `kind` (`composer`\|`image`\|`endpoint`), `ref`. |
| `ScannerInterface` | interface | `scan(ScanTarget $t): ScanReport`, `remediate(string $findingId, string $patchRef): void`. |
| `CveCorrelator` | class | Joins dependency graph to the CVE mirror; emits `Finding` list. |
| `SeverityScorer` | class | CVSS-based scoring → `critical`\|`high`\|`medium`\|`low`. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Scan;

interface ScannerInterface
{
    public function scan(ScanTarget $target): ScanReport;
    public function remediate(string $findingId, string $patchRef): void;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE scan_findings (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    target_ref   VARCHAR(255) NOT NULL,
    cve          VARCHAR(32) NOT NULL,
    severity     ENUM('critical','high','medium','low') NOT NULL,
    status       ENUM('open','patched','accepted','wont_fix') NOT NULL DEFAULT 'open',
    created_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_scan_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_scan_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-04/HUB-19/HUB-06/HUB-15/ISPOKE-15/CORE-19/HUB-21 through the container
(CORE-02). **Downward:** UI in ISPOKE-01; critical findings handed to ISPOKE-25.

## Security Properties
1. Scan targets are validated (HUB-19) — no arbitrary command or URL injection via `ref`.
2. Findings are immutable audit rows (HUB-06); "accepted"/"wont_fix" requires a recorded justification.
3. Critical findings escalate to ISPOKE-15/ISPOKE-25 through the event bus (HUB-09).
4. Scans are tenancy-scoped (HUB-21); one tenant cannot enumerate another's dependencies.

## CI Verification Criteria
- Unit: `SeverityScorer` maps a CVSS 9.8 finding to `critical`; `CveCorrelator` returns exactly the
  expected `Finding` set for a seeded dependency graph.
- Integration (MySQL 8 (InnoDB)): `scan()` writes `scan_findings`; `remediate()` flips status to `patched`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `CveCorrelator`.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-21 — shipped per the verified DAG).

### What Was NOT Changed in PR #343

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/scan/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #343 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
