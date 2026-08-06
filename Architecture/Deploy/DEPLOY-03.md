# DEPLOY-03: Bridge & External Spoke Deployment

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
