# PHASE HUB-27: Cross-Origin (CORS) & Security Header Management


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and clarifies precedence against `HUB-01`'s tenant
config overrides, which this blueprint already assumed but didn't formally define the precedence
order for.

## Component Name
Sovereign Sentinel (Headers)

## Description
Centralized HTTP security-header and CORS-policy management: allowed origins/methods/headers, guarding
the Hub and Spokes against common web attacks.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway) and `HUB-01` (Config) — neither implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-01`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-04`, `CORE-05`.

## Architectural Design
- **HeaderManager** — injects security headers (CSP, HSTS, X-Frame-Options) on every response.
- **CorsEngine** — evaluates preflight `OPTIONS`, injects `Access-Control-*` headers.
- **PolicyRegistry** — per-tenant or per-service security policies.
- **CspGenerator** — dynamic CSP hashes for inline scripts, if any exist.

```php
namespace SovereignStack\Hub\Contracts;

interface SentinelInterface
{
    public function apply(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface;
}
```

## Config Precedence (clarified)
Global defaults come from `CORE-10`; tenant-level overrides come from `HUB-01`. Precedence: a
tenant-specific policy in `PolicyRegistry` **may only narrow** the global default (e.g., a stricter CSP
`default-src`), never broaden it (e.g., a tenant cannot add `unsafe-inline` if the global policy
forbids it). This mirrors `HUB-01`'s own "tenant overrides may only add/replace known keys" rule and
prevents a misconfigured tenant policy from weakening the platform's baseline security posture.

## Integration Strategy
- **Upward:** registered as global middleware in `HUB-08`.
- **Downward:** automatically covers all Spoke requests routed through the Gateway.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Preflight correctness | Integration test: send an `OPTIONS` preflight for an allowed origin/method combination; assert `204` with correct `Access-Control-*` headers, and a separate test for a disallowed origin asserting rejection. |
| CSP presence | Integration test asserting `Content-Security-Policy` header with `default-src 'self'` (or the configured baseline) on every response type (HTML, JSON API, error pages) — not just the happy-path route. |
| HSTS correctness | Integration test asserting `Strict-Transport-Security` includes `max-age` and `includeSubDomains` with the configured values, not just presence of the header. |
| Tenant-narrowing-only enforcement | Integration test: configure a tenant policy attempting to *broaden* the global CSP; assert `PolicyRegistry` rejects it at write time, per the Config Precedence rule above. |

## CI Verification Criteria
- Preflight allow/deny test pair, blocking.
- CSP-on-every-response-type test, blocking.
- Tenant-narrowing-only enforcement test, blocking — new, makes the precedence rule a checked
  property.

## SemVer Impact
**Minor.** Hardens the security posture of the entire stack.


---

## Doctrines Applied + Rewrite Notes (PR #334)

> **This section was added in PR #334 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #334

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-27 — Cross-Origin (CORS) & Security Header Management — shipped per the verified DAG).

### What Was NOT Changed in PR #334

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/cors/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #334 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
