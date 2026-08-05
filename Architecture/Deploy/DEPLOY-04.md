# DEPLOY-04: Multi-Environment & Promotion Pipeline

> **Stub blueprint.** Scoped in `INDEX.md` §6; not yet authored to the `AUTHORING_GUIDE.md` fidelity
> bar. Phase-2 work. Nothing may depend on an interface from this file.

## Tier
Deploy

## Resolves
Finding 9 (`Critiques/00_CRITIQUE.md`) — nothing described how a change reaches production. Also the
practical consequence of ADR-001 (polyrepo): with ~50+ independently deployable repositories, promotion
cannot be a per-repo manual step.

## Component Name
Sovereign Promotion Pipeline — no PHP namespace (infrastructure-as-code + `CORE-01` Loom automation)

## Description
How a change moves `dev → staging → production` across the ~50+ repositories of the polyrepo
(ADR-001). Images are promoted by **immutable digest**, never rebuilt per environment. Ties into
`CORE-01` (Loom) for cross-repo version bumps, release gating, and the merge gate that consults
`HUB-15`'s aggregate health before tagging.

**Explicit non-goals:** the image build itself (`DEPLOY-01`), datastore migration mechanics
(`DEPLOY-02`), edge configuration (`DEPLOY-03`).

## Build Status
📝 **Not started.** Scoped only.

## Dependency Status
- **Upward:** `CORE-01` (Polyrepo Orchestrator / Loom — supplies the cross-repo version-bump and
  release-gate automation), `DEPLOY-01`, `DEPLOY-02`, `DEPLOY-03` (supply the artefacts being
  promoted), `HUB-15` (Health — the merge gate's health signal).
- **Downward:** none — this is the terminal tier.
- **Runtime:** CI/CD engine, container registry supporting digest pinning.

## Normative constraints already fixed
| Constraint | Source |
|---|---|
| Repository topology is **polyrepo** (~50+ repos), not a monorepo. | ADR-001 |
| Promotion is by immutable image **digest**, not by re-tagging or rebuilding. | `Deploy/DEPLOY-01.md` |
| A Hub service reporting `unhealthy` blocks the release tag. | `Hub/HUB-15.md`, `INDEX.md` §5 |
| Opcache preload changes require a container **replacement**, not `systemctl reload php8.3-fpm`. | ADR-010, INCONSISTENCIES #7 |

## Architectural Design
Not specified.

## Integration Strategy
Not specified.

## Benchmark & Verification Methodology
Not specified — **provisional, unverified**.

## CI Verification Criteria
Not specified.

## Security Properties
Not specified. At minimum: production promotion requires a signed approval; the registry credential
used for production promotion must not be available to `dev`/`staging` pipelines.

## Migration Notes
None — nothing depends on this component yet.

## SemVer Impact
**Not applicable** — no released contract.
