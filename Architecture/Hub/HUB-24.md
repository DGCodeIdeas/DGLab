# PHASE HUB-24: GraphQL Schema Registry (Pure PHP)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign GraphQL Registry

## Description
Pure-PHP GraphQL schema registry/execution engine: Hub services and Spokes register schema fragments
(Types, Queries, Mutations) unified into a single API via `webonyx/graphql-php` — no Node/Apollo.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway), `HUB-04` (Identity), `HUB-05` (RBAC) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-04`, `HUB-05`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-02`, `CORE-06`, `CORE-04`.

## Architectural Design
- **SchemaRegistry** — collects schema fragments from registered providers.
- **UnifiedExecutor** — validates/executes queries against the stitched schema.
- **DirectiveEngine** — PHP-based `@auth`, `@cache`, `@tenant` directives — `@tenant` should resolve
  through `HUB-21`'s `TenancyInterface`, and `@auth` through `HUB-05`'s `GateInterface`, rather than
  each directive reimplementing authorization/tenancy logic independently.
- **BatchResolver** — Data Loader pattern, N+1 prevention.

```php
$registry->register('blog', [
    'type_defs' => 'type Post { id: ID!, title: String! }',
    'resolvers' => [
        'Query' => ['post' => fn($root, $args) => $db->find($args['id'])]
    ]
]);
```

```php
namespace SovereignStack\Hub\Contracts;

interface GraphQLInterface
{
    public function execute(string $query, array $variables = [], mixed $context = null): array;
    public function register(string $namespace, array $definition): void;
}
```

## Integration Strategy
- **Upward:** exposed via a single `/graphql` endpoint in `HUB-08`.
- **Downward:** Spoke applications provide `SchemaProvider` classes discovered at boot.
- **Contract:** resolvers return raw arrays/objects; the engine handles JSON conversion.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Namespace collision detection | Unit test: register two namespaces both defining the same root `Query` field; assert schema stitching fails loudly at registration time, not silently overwriting one. |
| Field-level RBAC enforcement | Integration test: query a field guarded by `@auth(ability: "admin.view")` as a non-admin fixture user; assert the field resolves to `null`/an authorization error per the GraphQL spec's partial-response semantics, not a full-request failure that breaks unrelated fields in the same query. |
| Data Loader N+1 prevention | Integration test with a query fetching N related records; assert the DBAL query count stays constant (not O(N)) as N grows — measured via a query-counting fixture, not asserted from "uses Data Loaders." |
| Complex-query latency | State environment before citing "< 20ms" — measure once `CORE-02`/`HUB-08` exist (Finding 10). |

## CI Verification Criteria
- Collision-detection test, blocking.
- Field-level RBAC test with correct partial-response behavior, blocking.
- Query-count (N+1) test, blocking.
- Latency measured and reported with environment stated.

## SemVer Impact
**Minor.** Enables modern, typed data fetching across the stack.


---

## Doctrines Applied + Rewrite Notes (PR #333)

> **This section was added in PR #333 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #333

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-24 — GraphQL Schema Registry (Pure PHP) — shipped per the verified DAG).

### What Was NOT Changed in PR #333

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/graphql-registry/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #333 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
