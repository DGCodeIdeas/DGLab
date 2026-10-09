# PHASE ISPOKE-19: Sovereign SLA Monitor


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign SLA Monitor — `SovereignStack\Internal\SlaMonitor`. Real-time + historical SLA-compliance
monitoring for internal and external services: uptime tracking, incident-timeline visualization, SLA
breach alerting.

## Description
ISPOKE-19 consumes health signals from **HUB-15 (Sovereign Pulse — Health Check & Service Discovery)**
and the health dashboard (ISPOKE-03) to compute SLA attainment per service and per tenant. It maintains
rolling windows (e.g. 30/90/365-day availability), detects breaches against configured SLA targets, and
raises alerts via **HUB-12 (Sovereign Notify)**. It is a **reporting/alerting** layer over HUB-15 — it
does not perform health checks itself.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-15 (Sovereign Pulse — health signal source), ISPOKE-03 (Sovereign Health &
  Observability Dashboard — timeline source), HUB-12 (Sovereign Notify — breach alerts), HUB-02
  (Sovereign Cache — hot SLA windows), CORE-19 (Database — SLA target + breach store), HUB-21 (Sovereign
  Nexus — tenancy scoping), HUB-06 (Sovereign Auditor — breach record).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `SlaTarget` | `final readonly class` | `service`, `tenant_id`, `window`, `target_pct` (e.g. 99.95). |
| `SlaMonitorInterface` | interface | `attainment(string $service, string $tenantId, string $window): float`, `breaches(string $tenantId): BreachPage`. |
| `AvailabilityWindow` | class | Rolling-window aggregator backed by HUB-02; recomputed from HUB-15 samples. |
| `BreachDetector` | class | Compares attainment vs `SlaTarget`; emits HUB-12 alerts. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\SlaMonitor;

interface SlaMonitorInterface
{
    public function attainment(string $service, string $tenantId, string $window): float;
    public function breaches(string $tenantId): BreachPage;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE sla_targets (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    service     VARCHAR(255) NOT NULL,
    window      ENUM('30d','90d','365d') NOT NULL,
    target_pct  DECIMAL(5,2) NOT NULL CHECK (target_pct > 0 AND target_pct <= 100),
    CONSTRAINT fk_sla_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_service_window (tenant_id, service, window)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sla_breaches (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    service     VARCHAR(255) NOT NULL,
    window      VARCHAR(16) NOT NULL,
    detected_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_breaches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_breaches_tenant_detected (tenant_id, detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-15/ISPOKE-03/HUB-12/HUB-02/CORE-19/HUB-21/HUB-06 through the container
(CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. SLA computation is read-only over HUB-15 signals; it cannot influence health state.
2. Breach alerts carry the service + window + measured attainment (HUB-12); no secret data is emitted.
3. Windows are tenancy-scoped (HUB-21); cross-tenant SLA is not computable from one tenant's view.
4. Breach records are immutable audit rows (HUB-06).

## CI Verification Criteria
- Unit: `AvailabilityWindow` computes 99.9% over a seeded sample set with one downtime blip;
  `BreachDetector` fires exactly when `attainment < target_pct`.
- Integration (MySQL 8 (InnoDB)): seeding `sla_targets` + HUB-15 samples yields the expected
  `sla_breaches` rows.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `BreachDetector`.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-19 — shipped per the verified DAG).

### What Was NOT Changed in PR #343

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/sla-monitor/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #343 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
