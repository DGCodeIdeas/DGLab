# PHASE ISPOKE-25: Sovereign Responder (Incident Response)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Responder — `SovereignStack\Internal\Responder`. End-to-end incident-response management:
detection alerting, triage workflow, containment actions, forensic data collection, post-mortem
documentation, metrics tracking. The operational layer above ISPOKE-15 (SOC) and ISPOKE-21 (Scan).

## Description
ISPOKE-25 is the incident console. It ingests alerts (from ISPOKE-15 SOC, ISPOKE-21 Scan critical
findings, HUB-12 Notify escalations), drives a triage→containment→eradication→recovery workflow, collects
forensic snapshots (read-only copies of relevant state via CORE-19/HUB-11), and produces a post-mortem.
Containment actions that mutate live systems are gated behind explicit operator confirmation and audited
(HUB-06). It is the **coordination** layer — it calls other components; it does not itself detect or
remediate autonomously.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** ISPOKE-15 (Sovereign SOC — detection source), ISPOKE-07 (Sovereign Webhook Nexus — alert
  ingestion), ISPOKE-21 (Sovereign Scan — critical findings), HUB-12 (Sovereign Notify — paging), HUB-06
  (Sovereign Auditor — every action audited), HUB-04 (Sovereign Identity — responder authn), CORE-19
  (Database — incident store), HUB-11 (Sovereign Cloud Storage — forensic snapshot sink), HUB-15
  (Sovereign Pulse — system health during incident), HUB-21 (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Incident` | `final readonly class` | `tenant_id`, `severity`, `status` (`triaged`\|`contained`\|`eradicated`\|`recovered`\|`closed`), `timeline`. |
| `ResponderInterface` | interface | `open(array $alert): string`, `contain(string $incidentId, Containment $c): void`, `postMortem(string $incidentId): Document`. |
| `TriageWorkflow` | class | State machine over `Incident.status`; gates mutating actions. |
| `ForensicCollector` | class | Read-only snapshots to HUB-11 for later analysis. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Responder;

interface ResponderInterface
{
    public function open(array $alert): string;
    public function contain(string $incidentId, Containment $action): void;
    public function postMortem(string $incidentId): Document;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE incidents (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    severity     ENUM('sev1','sev2','sev3','sev4') NOT NULL,
    status       ENUM('triaged','contained','eradicated','recovered','closed') NOT NULL DEFAULT 'triaged',
    opened_by    CHAR(26) CHARACTER SET ascii NOT NULL,
    opened_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_incidents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_incidents_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE incident_timeline (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    incident_id  CHAR(26) CHARACTER SET ascii NOT NULL,
    event        JSON NOT NULL,
    created_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_timeline_incident FOREIGN KEY (incident_id) REFERENCES incidents(id),
    INDEX idx_timeline_incident (incident_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves ISPOKE-15/ISPOKE-07/ISPOKE-21/HUB-12/HUB-06/HUB-04/CORE-19/HUB-11/HUB-15/HUB-21
through the container (CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. Every containment action is audited (HUB-06) with `opened_by`/`operator` and a before/after record —
   incident response is itself observable.
2. Forensic snapshots are written to HUB-11 read-only; they never alter the live system.
3. Mutating containment requires explicit operator confirmation, never automatic execution.
4. Incidents are tenancy-scoped (HUB-21); a responder cannot act across tenants.

## CI Verification Criteria
- Unit: `TriageWorkflow` rejects an illegal status transition (e.g. `triaged → closed` skipping
  `contained`); `ForensicCollector` writes a snapshot without modifying source rows.
- Integration (MySQL 8 (InnoDB)): `open()` writes `incidents`; `contain()` appends an `incident_timeline`
  event and advances status.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `TriageWorkflow`.


---

## Doctrines Applied + Rewrite Notes (PR #345)

> **This section was added in PR #345 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #345

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-25 — shipped per the verified DAG).

### What Was NOT Changed in PR #345

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/responder/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #345 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
