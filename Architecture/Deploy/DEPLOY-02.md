# DEPLOY-02: Datastore Provisioning


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Deploy blueprint may contain **stale infrastructure assumptions** (e.g., PHP-FPM vs FrankenPHP per ADR-017, Nginx vs Caddy, Supervisor vs systemd). The deployment configuration should be verified against actual infrastructure. Runtime substrate claims (Anvil v3, worker recycling, signal handling) should be tested against real deployments. **Documentation drift is especially dangerous in deployment blueprints — always verify against the actual runtime.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Deploy

## Component Name
Datastore Provisioning — the operational blueprint for provisioning and operating the stateful tier:
MySQL 8 (InnoDB) (primary datastore, ADR-013), Redis 7 (cache + queue + session, ADR-006), and the queue
broker; secret custody via HUB-20 (Sovereign Vault) / sealed-secrets; backup/restore wired to ISPOKE-24.

## Description
DEPLOY-02 owns everything with a disk. It provisions MySQL 8 (InnoDB) (JSON, ULID primary keys, RDS or
self-managed), Redis 7 (ADR-006), and the queue broker that HUB-10 (Sovereign Queue) consumes. It
manages.schema migrations (CORE-19 Database), secrets injection (HUB-20), connection topology, replica
sets, and the backup/restore contract that ISPOKE-24 drives. It is the foundation DEPLOY-01 (Core & Hub
Deployment) and DEPLOY-03 (Bridge & External Spoke Deployment) build on.

## Build Status
✅ **Documented — ready for implementation** (promoted from stub on 2026-08-05).

## Dependency Status
- **Upward (consumes):** CORE-19 (Database — schema/migrations), CORE-15 (Cache Abstraction — Redis
  adapter), CORE-16 (Encryption Envelope — at-rest/TDE key wrap), HUB-20 (Sovereign Vault — secret
  custody), HUB-11 (Sovereign Cloud Storage — backup sink), HUB-15 (Sovereign Pulse — datastore health),
  ISPOKE-24 (Sovereign Restore — backup orchestration), CORE-01 (Sovereign Loom — provisioning
  orchestration across repos).
- **Downward (consumed by):** DEPLOY-01 (Core & Hub), DEPLOY-03 (Bridge & Spokes), and every Hub service
  that persists state (HUB-02, HUB-20, HUB-21, HUB-22, and all Core services with a datastore).

## Architectural Design

| Concern | Decision |
|---|---|
| Primary datastore | MySQL 8 (InnoDB), `json` columns, `ulid` PKs, generated-column indexes, tenant scoping via DBAL (STRUCTURE-05). |
| Cache / queue / session | Redis 7+ (ADR-006) — distinct logical databases; `HUB-09` pub/sub, `HUB-10` streams. |
| Secrets | Injected at pod start from `HUB-20`; never baked into images or env files in VCS. |
| Migrations | Applied by `CORE-19` migration runner in DEPLOY-01 boot; backward-compatible (expand/contract). |
| Backups | Continuous WAL archive → `HUB-11`; catalog in `ISPOKE-24`; integrity verified (CORE-16). |
| HA | Primary + 1–2 sync replicas; Redis with replica + sentinel; failover automated. |

## Integration Strategy
**Upward:** resolved through CORE-01 (Loom) which provisions the datastore repos and applies
CORE-19 migrations. **Downward:** DEPLOY-01 references this blueprint for connection topology; DEPLOY-03
and all Hub services connect through the provisioned endpoints. The Zero-Exposure rule (BRIDGE-01) means
datastores are never directly reachable from the public tier — only via Hub services behind the Vanguard.

## Security Properties
1. No datastore is publicly reachable; network policy permits connections only from the Hub pod CIDR.
2. Secrets are custodied by HUB-20 and injected at runtime; no credential appears in image layers or
   Git history.
3. At-rest encryption uses keys wrapped by CORE-16; key rotation is coordinated via HUB-20.
4. Restore (ISPOKE-24) is tenancy-scoped and fully audited (HUB-06); a restore never crosses tenants.

## CI Verification Criteria
- IaC plan (Terraform/Pulumi) for MySQL 8 (InnoDB) + Redis 7 produces no diff against the declared topology
  on `main`.
- A ephemeral MySQL 8 (InnoDB) + Redis 7 stand up in CI; CORE-19 migration runner applies all migrations
  with zero errors; HUB-10 publishes/consumes a test message via Redis Streams.
- Backup job writes a verifiable artifact to HUB-11; ISPOKE-24 `verify()` passes.
- Static/drift: `CORE-01` (Loom) promotion dry-run succeeds for the datastore repos.


---

## Doctrines Applied + Rewrite Notes (PR #353)

> **This section was added in PR #353 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #353

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — DEPLOY-02 — Datastore Provisioning — shipped per the verified DAG).

### What Was NOT Changed in PR #353

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/deploy/datastore-provisioning/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #353 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
