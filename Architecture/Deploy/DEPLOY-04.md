# DEPLOY-04: Multi-Environment & Promotion Pipeline

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
