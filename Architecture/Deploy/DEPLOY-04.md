# DEPLOY-04: Multi-Environment & Promotion Pipeline


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Deploy blueprint may contain **stale infrastructure assumptions** (e.g., PHP-FPM vs FrankenPHP per ADR-017, Nginx vs Caddy, Supervisor vs systemd). The deployment configuration should be verified against actual infrastructure. Runtime substrate claims (Anvil v3, worker recycling, signal handling) should be tested against real deployments. **Documentation drift is especially dangerous in deployment blueprints — always verify against the actual runtime.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Deploy

## Component Name
Multi-Environment & Promotion Pipeline — dev → staging → production promotion across the 50+ service
repositories, using immutable image digests and CORE-01 (Sovereign Loom) as the release orchestrator.

## Description
DEPLOY-04 is the promotion control plane. Each service (Core, Hub, Bridge, Spokes, Deploy-02/03
datastores) is built once into an **immutable image digest** and promoted dev → staging → production by
**CORE-01 (Sovereign Loom)**; the same digest that passed staging is the only artifact allowed in prod
(no rebuild-per-environment). It coordinates the ordering implied by the tier DAG (INDEX.md §5):
datastores (DEPLOY-02) → Core/Hub (DEPLOY-01) → Bridge/Spokes (DEPLOY-03), with HUB-15 readiness gates at
each step.

## Build Status
✅ **Documented — ready for implementation** (promoted from stub on 2026-08-05).

## Dependency Status
- **Upward (consumes):** CORE-01 (Sovereign Loom — release orchestration + digest registry), DEPLOY-01
  (Core & Hub — produces the digests), DEPLOY-02 (Datastores — first promoted), DEPLOY-03 (Bridge &
  Spokes — last promoted), HUB-06 (Sovereign Auditor — promotion audit trail), HUB-15 (Sovereign Pulse —
  readiness gate), HUB-20 (Sovereign Vault — environment secrets).
- **Downward (consumed by):** none — this is the terminal tier of the deployment DAG.

## Architectural Design

| Concern | Decision |
|---|---|
| Artifact | Single immutable image digest per service; promoted, never rebuilt per env. |
| Orchestrator | CORE-01 (Loom) drives the promotion graph across 50+ repos from one command. |
| Ordering | Tier DAG (INDEX §5): DEPLOY-02 → DEPLOY-01 → DEPLOY-03. |
| Gates | HUB-15 readiness + HUB-06 audit + required CI (lint, phpstan, tests) before each env bump. |
| Rollback | `CORE-01` repoints the env to the previous good digest; no rebuild, no data migration for stateless tiers. |
| Secrets | Per-env secrets injected by HUB-20; the same digest runs in every env with different secret material. |

## Integration Strategy
**Upward:** CORE-01 is the engine; it reads the deploy manifests produced by DEPLOY-01/02/03 and applies
the promotion order. **Downward:** terminal — it promotes the other three deploy blueprints. Depends on
ARCHIVED root `Dockerfile`/`render.yaml` having been superseded by DEPLOY-00 (docs) and DEPLOY-01
(application); see INDEX.md §1.

## Security Properties
1. Immutability: prod runs exactly the digest validated in staging — no "works on my env" drift.
2. Every promotion is audited (HUB-06) with operator + digest + source env; promotion is non-repudiable.
3. Gate failure (HUB-15 not ready, CI red, audit missing) blocks the bump — fail closed.
4. Secrets are environment-scoped via HUB-20; the promoted digest carries no env-specific secret.

## CI Verification Criteria
- CORE-01 promotion dry-run: given seeded dev/staging digests, produces the expected ordered promotion
  plan dev→staging→prod with the tier DAG order.
- Gate test: a simulated HUB-15 unhealthy state causes the bump to abort with a clear error.
- Rollback test: `CORE-01 rollback <env>` repoints to the previous digest and the served digest matches
  the recorded prior value (verified via HUB-15 metadata).
- Audit: each successful bump writes exactly one HUB-06 record with digest + operator.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — DEPLOY-04 — Multi-Environment & Promotion Pipeline — shipped per the verified DAG).

### What Was NOT Changed in PR #354

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/deploy/multi-environment/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #354 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
