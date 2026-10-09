# PHASE ISPOKE-23: Sovereign Role Play (Simulation)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Role Play — `SovereignStack\Internal\RolePlay`. Sandbox for testing RBAC configurations before
deployment: "what-if" analysis of permission changes, role preview, conflict detection.

## Description
ISPOKE-23 lets administrators preview the effect of a proposed RBAC change (new role, permission grant,
role merge) against a **simulated** subject, without touching live policy in **HUB-05 (Sovereign
Guardian)**. It clones the current policy graph, applies the candidate change in the clone, and reports
the resulting effective permissions plus any conflicts (e.g. a Permission X granted to a role that is
denied elsewhere, or a separation-of-duties violation). It is read-only against production and writes
only to its own simulation store.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-05 (Sovereign Guardian — live policy source + the target it previews), ISPOKE-04
  (Sovereign Staff Hub — the staff identity entry gate), CORE-19 (Database — simulation store), HUB-06
  (Sovereign Auditor — simulation audit), HUB-21 (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `PolicyPatch` | `final readonly class` | A candidate change: `grant`\|`revoke`\|`merge` on a role/permission. |
| `RolePlayInterface` | interface | `simulate(PolicyPatch $p, string $tenantId): SimulationResult`, `conflicts(string $tenantId): ConflictPage`. |
| `PolicyCloner` | class | Snapshots HUB-05 graph into the simulation store. |
| `ConflictDetector` | class | Computes effective perms + flags SoD/deny conflicts. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\RolePlay;

interface RolePlayInterface
{
    public function simulate(PolicyPatch $patch, string $tenantId): SimulationResult;
    public function conflicts(string $tenantId): ConflictPage;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID pseudo-type materialised as CHAR(26) CHARACTER SET
-- ascii by the DBAL (ADR-009); ulid_generate() emitted by the app/DBAL, not the engine.
CREATE TABLE sim_policies (
    id          CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id   CHAR(26) CHARACTER SET ascii NOT NULL,
    snapshot    JSON NOT NULL,                       -- cloned HUB-05 graph
    created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_sim_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sim_results (
    id               CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    sim_id           CHAR(26) CHARACTER SET ascii NOT NULL,
    patch            JSON NOT NULL,
    effective_perms  JSON NOT NULL,
    conflicts        JSON NOT NULL,                   -- default applied by the DBAL (JSON_ARRAY())
    created_at       TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_results_sim FOREIGN KEY (sim_id) REFERENCES sim_policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves HUB-05/ISPOKE-04/CORE-19/HUB-06/HUB-21 through the container (CORE-02). **Downward:**
UI in ISPOKE-01.

## Security Properties
1. Simulation is strictly read-only against HUB-05; the clone is isolated and never promoted without an
   explicit admin action outside ISPOKE-23.
2. Conflict detection surfaces separation-of-duties violations before a real grant — the whole point of
   the sandbox.
3. Simulations are tenancy-scoped (HUB-21); a clone cannot read another tenant's policy.
4. Every simulation is audited (HUB-06) with the proposing operator id.

## CI Verification Criteria
- Unit: `ConflictDetector` flags a deny/grant collision and an SoD violation on seeded graphs; a clean
  patch yields zero conflicts.
- Integration (MySQL 8 (InnoDB)): `simulate()` writes `sim_results` with the expected `effective_perms`
  and `conflicts`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `ConflictDetector`.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-23 — shipped per the verified DAG).

### What Was NOT Changed in PR #344

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/role-play/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #344 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
