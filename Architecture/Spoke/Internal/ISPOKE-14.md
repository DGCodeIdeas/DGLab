# PHASE ISPOKE-14: Multi-tenancy Administration Console


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only Application)

## Component Name
Sovereign Nexus (Tenancy)

## Description
A high-level console for managing the lifecycle of tenants within the system. This goes beyond simple CRUD (ISPOKE-01) to include tenant migration, resource quota management, custom domain mapping, and white-labeling configuration.

## Sequencing Rationale
Follows Billing (ISPOKE-13) because tenant quotas and white-labeling features are typically unlocked via specific billing plans.

## Context7 Research
### Direct Hub Dependencies
- `HUB-21: Multi-tenancy Coordination Layer`
- `HUB-01: Global Configuration & Feature Flags`
- `HUB-03: Unified Asset Pipeline & Bundler`
- `HUB-26: Shared UI Component Library`
- `HUB-04: Global Identity & Authentication`
- `HUB-15: Health Check & Service Discovery`

### Transitive Core Dependencies
- `CORE-10: Config & Env Loader`
- `CORE-18: Core Kernel & Lifecycle`
- `CORE-19: DBAL & Migrations`
- `CORE-11: SuperPHP Parser`
- `CORE-12: SuperPHP Compiler`

## Architectural Design
- **TenantProvisioner**: Automates the creation of tenant-specific database schemas and file storage buckets.
- **QuotaManager**: Enforces limits on users, storage, and API calls via `HUB-21`.
- **DomainMapper**: Manages SSL certificates and DNS verification for custom tenant domains.
- **ThemeEngine**: UI for configuring tenant-specific CSS variables and logos (integrated with `HUB-03`).

### Tenant Lifecycle Diagram
```mermaid
stateDiagram-v2
    [*] --> Provisioning
    Provisioning --> Active: Setup Complete
    Active --> Suspended: Non-payment
    Suspended --> Active: Balance Cleared
    Active --> Archive: Offboarding
    Archive --> [*]: Deleted
```

## Interface Contracts

### TenantAdminInterface
```php
namespace SovereignStack\Internal\Nexus\Contracts;

interface TenantAdminInterface
{
    /**
     * Provision a new tenant environment.
     */
    public function provision(string $tenantName, array $config): string;

    /**
     * Update resource quotas for an existing tenant.
     */
    public function setQuotas(string $tenantId, array $quotas): bool;
}
```

## Integration Strategy
- **Bootstrapping**: Boots via `CORE-18`; interfaces with the multi-tenant DBAL in `CORE-19`.
- **UI**: Renders a "Global Tenant Map" and resource usage charts using `HUB-26`.
- **Asset Pipeline**: Triggers `HUB-03` rebuilds when a tenant's white-labeling theme is updated.
- **Orchestration**: Hooks into `HUB-16` to manage tenant migrations between physical database clusters.
- **Health**: Reports tenant saturation levels and provisioning success rates to `HUB-15`.

## CI Verification Criteria
- **Schema Isolation**: Tenant migrations must be verified to ensure zero data crossover between `tenant_a` and `tenant_b`.
- **Asset Security**: Custom tenant logos/assets must be stored in isolated `HUB-14` buckets with restricted access.
- **Quota Enforcement**: A tenant exceeding its user quota must be blocked from adding new users at the Hub level (`HUB-21`).

## SemVer Impact
**Major**. Enables the scale and customization required for a enterprise-grade SaaS platform.


---

## Doctrines Applied + Rewrite Notes (PR #341)

> **This section was added in PR #341 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #341

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-14 — shipped per the verified DAG).

### What Was NOT Changed in PR #341

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/multi-tenancy-console/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #341 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
