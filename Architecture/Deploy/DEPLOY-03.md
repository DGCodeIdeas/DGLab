# DEPLOY-03: Bridge & External Spoke Deployment


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Deploy blueprint may contain **stale infrastructure assumptions** (e.g., PHP-FPM vs FrankenPHP per ADR-017, Nginx vs Caddy, Supervisor vs systemd). The deployment configuration should be verified against actual infrastructure. Runtime substrate claims (Anvil v3, worker recycling, signal handling) should be tested against real deployments. **Documentation drift is especially dangerous in deployment blueprints — always verify against the actual runtime.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Deploy

## Component Name
Bridge & External Spoke Deployment — the public-serving tier: deployment, edge/CDN caching, and
network-policy enforcement of the Zero-Exposure Test for BRIDGE-01 (The Vanguard) and ESPOKE-01..15.

## Description
DEPLOY-03 deploys the only internet-facing surface of the platform. It places **BRIDGE-01 (The
Vanguard)** as the mandatory entry point (default-deny, DTO transform, zero-exposure), fronts the
External Spokes (ESPOKE-01..15) behind it, and applies edge caching via **HUB-02 (Sovereign Cache)** and
header hardening via **HUB-27 (Sovereign Sentinel)**. The defining property is the **Zero-Exposure
Test**: no External Spoke process may bind a public socket or resolve a Hub-internal dependency; the
Vanguard is the sole egress/ingress.

## Build Status
✅ **Documented — ready for implementation** (promoted from stub on 2026-08-05).

## Dependency Status
- **Upward (consumes):** BRIDGE-01 (The Vanguard — entry point), HUB-08 (Sovereign Gateway — routing
  behind the Vanguard), HUB-02 (Sovereign Cache — edge/CDN cache), HUB-27 (Sovereign Sentinel — security
  headers), HUB-04 (Sovereign Identity — external authn), HUB-06 (Sovereign Auditor — request audit),
  HUB-15 (Sovereign Pulse — health/readiness), ESPOKE-01..15 (External Spokes — the served apps),
  CORE-01 (Sovereign Loom — image promotion), DEPLOY-01 (Core & Hub — the upstream it fronts).
- **Downward (consumed by):** DEPLOY-04 (Promotion) — this tier is promoted dev→staging→prod.

## Architectural Design

| Concern | Decision |
|---|---|
| Entry point | BRIDGE-01 Vanguard, 3 replicas, default-deny; DTO transform at the boundary. |
| Routing | HUB-08 Gateway inside the Vanguard; maps external routes → Hub services / ESPOKE. |
| Caching | HUB-02 edge cache + CDN; cache keys are tenant-scoped (HUB-21). |
| Headers | HUB-27 sets CSP/HSTS/permissions-policy; no internal header leaks outward. |
| Exposure | Network policy: ESPOKE pods have no public IP; only the Vanguard Service is public. |
| Health | HUB-15 readiness gates rollout; unhealthy Vanguard → no traffic. |

## Integration Strategy
**Upward:** BRIDGE-01 + HUB-08 + ESPOKE deploy as a unit; CORE-01 promotes the immutable image digests
produced by DEPLOY-01. **Downward:** DEPLOY-04 promotes this tier across environments. The Zero-Exposure
Test is enforced by network policy + a red-team CI job (chaos/eBPF packet inspection) that fails the
deploy if any ESPOKE binds a public socket.

## Security Properties
1. **Zero-Exposure is structural, not config.** Network policy + the Vanguard make direct External-Spoke
   exposure impossible; a misconfiguration fails closed.
2. No Hub-internal dependency is resolvable from the public tier — only via the Vanguard's allow-list.
3. Headers (HUB-27) are uniform; internal topology never leaks in responses.
4. All inbound requests are audited (HUB-06) at the Vanguard before reaching a spoke.

## CI Verification Criteria
- Zero-Exposure Test: a probing job from outside the cluster can reach only the Vanguard Service; direct
  ESPOKE pod IPs are unreachable (network-policy enforced).
- Rollout: HUB-15 readiness gates; a canary with 1 unhealthy replica halts promotion.
- CDN: HUB-02 cache keys verified tenant-scoped; a cross-tenant cache hit is impossible by construction.
- Promotion dry-run via CORE-01 succeeds for the bridge + spoke repos.


---

## Doctrines Applied + Rewrite Notes (PR #354)

> **This section was added in PR #354 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #354

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — DEPLOY-03 — Bridge & External Spoke Deployment — shipped per the verified DAG).

### What Was NOT Changed in PR #354

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/deploy/bridge-spoke-deployment/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #354 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
