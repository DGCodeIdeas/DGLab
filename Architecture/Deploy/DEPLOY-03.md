# DEPLOY-03: Bridge & External Spoke Deployment

> **Stub blueprint.** Scoped in `INDEX.md` §6; not yet authored to the `AUTHORING_GUIDE.md` fidelity
> bar. Phase-2 work. Nothing may depend on an interface from this file.

## Tier
Deploy

## Resolves
Finding 9 (`Critiques/00_CRITIQUE.md`) — no blueprint deployed the public-facing tier. Also closes the
gap noted in `Spoke/Bridge/BRIDGE-01.md`: the "Zero-Exposure Test" is only enforceable as a
namespace-import static check today, with no network-level counterpart.

## Component Name
Sovereign Edge Deployment — no PHP namespace (infrastructure-as-code)

## Description
Deployment of the only two publicly reachable tiers: `BRIDGE-01` (the Vanguard, ≥3 replicas) and
`ESPOKE-01..15`. Covers CDN/edge cache integration with `HUB-02`, TLS termination, WAF placement,
and — critically — the **network policy** that makes the Bridge's zero-exposure guarantee real:
Internal Spokes and Hub services must be unroutable from the public network, enforced at the network
layer rather than by convention.

**Explicit non-goals:** documentation hosting (`DEPLOY-00`), Core/Hub images (`DEPLOY-01`), datastores
(`DEPLOY-02`), promotion (`DEPLOY-04`).

## Build Status
📝 **Not started.** Scoped only. Blocked behind `BRIDGE-01` and `DEPLOY-01`.

## Dependency Status
- **Upward:** `DEPLOY-01` (reuses the base image recipe and the `/health` contract), `BRIDGE-01`
  (defines the enforcement surface being deployed), `HUB-02` (Cache — CDN/edge cache coordination),
  `HUB-15` (Health — the CDN's origin health target is `GET /health/bridge`), `HUB-08` (Gateway —
  shares the `RequestForwarderInterface` forward path).
- **Downward:** `DEPLOY-04` (promotes these images across environments).
- **Runtime:** CDN, network-policy engine, TLS certificate issuance.

## Normative constraints already fixed
| Constraint | Source |
|---|---|
| The Vanguard runs ≥3 replicas; failover is coordinated through `HUB-15`. | `Spoke/Bridge/BRIDGE-01.md` |
| The Vanguard never holds the ES256 **private** key; it verifies via `HUB-04`. | ADR-003 |
| JWTs are ES256. `Ed25519`/`EdDSA` is rejected for JWT signing. | ADR-003 |
| No Internal Spoke or Hub service may be reachable from the public network. | `CrossCutting/THREAT_MODEL.md` |

## Architectural Design
Not specified.

## Integration Strategy
Not specified.

## Benchmark & Verification Methodology
Not specified — **provisional, unverified**.

## CI Verification Criteria
Not specified. Must eventually include an automated zero-exposure network probe, not only the
static namespace-import scan.

## Security Properties
Not specified. Inherits `CrossCutting/THREAT_MODEL.md` §7 (edge threats): header manipulation,
`X-Forwarded-For` spoofing, and CDN-to-origin authentication.

## Migration Notes
None — nothing depends on this component yet.

## SemVer Impact
**Not applicable** — no released contract.
