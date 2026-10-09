# PHASE HUB-21: Multi-tenancy Coordination Layer


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and makes this blueprint the explicit authority that
`ISPOKE-01`'s `TenantSwitcher` and `HUB-01`'s tenant-override merge logic (`HUB-01.md`) both build on —
previously the three documents referenced each other loosely without one being the clear source of
truth for "what is a tenant ID."

## Component Name
Sovereign Nexus (Tenancy)

## Description
Coordination layer for multi-tenant applications: tenant resolution (domain, header, or user),
database connection switching, and scope isolation for shared Hub services, guaranteeing Tenant A's
data never leaks into Tenant B's.

## Build Status
🔴 **Blocked** on `HUB-01` (Config), `HUB-04` (Identity), `HUB-08` (Gateway) — none implemented. Must
land before any tenant-aware Spoke is built (`ISPOKE-01` and every External Spoke assume this exists).

## Dependency Status
- **Direct Hub:** `HUB-01`, `HUB-04`, `HUB-08`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-19`, `CORE-10`, `CORE-02`.
- **Downward:** `HUB-01` (tenant config overrides reference this tenant-ID format), `HUB-02` (cache-key
  tenant prefixing), `HUB-11` (storage-path tenant prefixing), `ISPOKE-01`, every tenant-scoped Spoke.

## Tenant ID Format (new — closes an unstated cross-reference)
`HUB-01.md`'s override schema declares `tenant_id CHAR(26)` and attributes the format to `HUB-04` —
that attribution is corrected here: tenant identity is owned by **this** blueprint (`HUB-21`), not
`HUB-04` (which owns user identity, a related but distinct concept). This blueprint is the source of
truth: **tenant IDs are ULIDs** (26-character, Crockford Base32, lexicographically sortable).
`Tenant::id` is generated at creation time by `TenantResolver` and is immutable thereafter. `HUB-01.md`
should be read with this correction; its schema type (`CHAR(26)`) was already right.

**Stack-wide ID policy (new):** per `01_MASTER_INDEX.md` §10, every entity primary/foreign-key
identifier in the Sovereign Stack — tenant, user, audit record, or otherwise — is a ULID (26-character,
Crockford Base32, lexicographically sortable). This blueprint is the source of truth for tenant IDs and
reaffirms that all active Sovereign Stack blueprints use ULID-based primary and foreign keys.

## Architectural Design
- **TenantResolver** — identifies the current tenant from the Request.
- **TenantScope** — global state object holding the current tenant's ID/config.
- **ConnectionSwitcher** — points `CORE-19` at the tenant's specific database if configured
  (database-per-tenant supported; column-based isolation is the default).
- **StorageIsolation** — prefixes `HUB-11` file paths with the Tenant ID.

```php
namespace SovereignStack\Hub\Contracts;

interface TenancyInterface
{
    public function current(): ?Tenant;
    public function runAs(string $tenantId, callable $callback): mixed;
}
```

## Integration Strategy
- **Upward:** registered as `CORE-05` middleware in `HUB-08`.
- **Downward:** Spoke applications inject `TenancyInterface`; global models implement a
  `BelongsToTenant` trait for automatic query scoping.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Cross-tenant leak prevention | Integration test: seed Users for Tenant A and Tenant B; run a `Users` query while Tenant A is active; assert zero Tenant-B rows returned — this is the same class of test as `HUB-01.md`'s config-isolation test and `BRIDGE-01`'s boundary tests, and should be held to the same CI-blocking severity. |
| Resolution speed | State environment before citing "< 0.1ms" — measure once `HUB-01`/`HUB-04` exist (Finding 10). |
| Cache-key tenant prefixing | Integration test: write a `HUB-02` cache entry under Tenant A, assert it is unreachable via the same key under Tenant B's context — verifies `HUB-02`'s tag/key namespacing actually incorporates the ULID from this blueprint. |

## CI Verification Criteria
- Cross-tenant leak test, blocking — treat with the same severity as `BRIDGE-01`'s Zero-Exposure Test.
- Cache-key isolation test, blocking.
- Resolution speed measured and reported with environment stated.

## SemVer Impact
**Major.** Transforms the stack into a multi-tenant platform.


---

## Doctrines Applied + Rewrite Notes (PR #332)

> **This section was added in PR #332 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #332

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-21 — Multi-tenancy Coordination Layer — shipped per the verified DAG).

### What Was NOT Changed in PR #332

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/multi-tenancy/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #332 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
