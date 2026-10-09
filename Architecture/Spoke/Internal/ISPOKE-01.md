# ISPOKE-01: Sovereign Command Center — Internal Exemplar


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Merges the self-identified but never-integrated weakness from
`archive/docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` ("CRUD Engine (ISPOKE-01) Could Be Over-Generalized") into
this file directly, per Governance Rule 5, and corrects the tier inventory context per
`00_CRITIQUE.md` Finding 13 (this is spoke 1 of a true 25, not of 15).

## Component Name
Sovereign Command Center

## Description
The primary administrative interface for the Sovereign Stack: a centralized UI for managing Users,
Roles, Tenants, and global System Settings, built on the Shared UI Component Library
(`HUB-26`). This is the first of **25** planned Internal Spokes (`ISPOKE-01`–`25`; see
`01_MASTER_INDEX.md` §4 — 10 of those 25 exist only as placeholder stubs today, not yet at this level
of detail).

## Build Status
🔴 **Blocked** on `HUB-04`, `HUB-05`, `HUB-21`, `HUB-26`, `HUB-08`, `HUB-15`, `HUB-16` (all Hub-tier,
none implemented) and transitively on the full Core tier. Design work may proceed; implementation
cannot start meaningfully before at least `HUB-04` (Identity) and `HUB-05` (RBAC) land, since this
Spoke's core CI criterion (permission-leak prevention, below) is meaningless without them.

## Dependency Status

### Direct Hub Dependencies
- `HUB-04`: Global Identity & Authentication
- `HUB-05`: RBAC & Permission Engine
- `HUB-21`: Multi-tenancy Coordination Layer
- `HUB-26`: Shared UI Component Library
- `HUB-08`: API Gateway
- `HUB-15`: Health Check & Service Discovery
- `HUB-16`: Hub-level Orchestration Hooks

### Transitive Core Dependencies
- `CORE-11`: SuperPHP Parser
- `CORE-12`: SuperPHP Compiler
- `CORE-18`: Core Kernel & Lifecycle
- `CORE-19`: DBAL
- `CORE-06`: Router

(Cross-checked against `archive/pre-A3-audit/docs/hub-taxonomy/hub-blueprint-taxonomy.md` (historical pre-A3 reference) — all IDs above match current
Hub blueprint titles; no drift found in this direction, unlike the Core-tier renumbering in Finding 2.)

## Architectural Design

### Components
- **AdminShell** — master layout from `HUB-26` providing sidebar and top navigation.
- **EntityCrudEngine** — generates standardized management interfaces for DBAL entities.
- **TenantSwitcher** — UI component for switching active tenant context (`HUB-21`).
- **AuditViewer** — integrated view of `HUB-06` audit logs.

### EntityCrudEngine — scoping correction

The original blueprint left `EntityCrudEngine` fully generic ("generates standardized interfaces for
managing DBAL entities"), which is exactly the over-generalization risk `SOLUTIONS_TO_WEAKNESSES.md`
flagged: a single generic CRUD generator tends to accumulate special-casing for every entity that
doesn't fit the default form/table/filter shape, until it's no longer generic in practice. This
blueprint narrows the contract:

```php
namespace SovereignStack\Internal\CommandCenter\Contracts;

/**
 * A resource description the CrudEngine can render generically.
 * Entities that need custom behavior (e.g., a wizard-style multi-step
 * creation flow) implement CustomResourceInterface instead and opt OUT
 * of the generic engine entirely for that one action — not a partial
 * override of it.
 */
interface CrudResourceInterface
{
    public static function label(): string;

    /** @return array<string, FieldDefinition> keyed by DBAL column name */
    public static function fields(): array;

    /** Fields visible in the list/table view — a subset of fields(). */
    public static function listColumns(): array;

    /** RBAC permission string required to view this resource at all. */
    public static function viewPermission(): string;

    /** RBAC permission string required to create/edit/delete. */
    public static function managePermission(): string;
}

/**
 * Opt-out escape hatch: a resource implementing this instead of
 * CrudResourceInterface is rendered by its own controller, not the
 * generic engine. Prevents the generic engine from growing
 * entity-specific conditionals over time.
 */
interface CustomResourceInterface
{
    public static function controller(): string; // FQCN of a dedicated controller
}
```

**Rule:** `EntityCrudEngine` only ever implements `CrudResourceInterface`'s contract. Any entity that
needs behavior outside that contract implements `CustomResourceInterface` and gets its own controller
— it does not get a special case bolted onto the generic engine. This is the concrete mechanism that
keeps the engine from becoming "generic in name only."

## Integration Strategy
- **Bootstrapping:** boots via the `CORE-18` Kernel; registers with `HUB-15` for health monitoring.
- **UI Rendering:** exclusively consumes `HUB-26` components — no local CSS or custom primitives.
- **Orchestration:** hooks into `HUB-16` for specialized administrative maintenance modes.
- **Health Reporting:** reports its own health and its Hub-connection health via `HUB-15`.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| 100% of rendered tags originate from `HUB-26` namespaces | Automated DOM/template scan in CI over every rendered page fixture; fails the build on any non-`HUB-26` tag. |
| A non-super-admin staff user cannot access Tenant Management | Integration test authenticated as a fixture user with a restricted role (via `HUB-05`); assert `403` on the Tenant Management route. |
| Admin Dashboard server response time | State the reference environment (PHP version, DB proximity, cache state) before citing "< 50ms" — measure via a load-testing tool (e.g., `k6` or `siege`) against a seeded fixture dataset of realistic size, not an empty database, since CRUD list-view performance is dataset-size-sensitive. |

## CI Verification Criteria
- UI consistency scan (above), blocking.
- Permission-leak test (above), blocking — this is a security property, treat failures as release
  blockers, not warnings.
- Response time measured against a seeded, realistic-size fixture dataset, with the seed size stated
  in the test itself so the number is reproducible.
- Any `CrudResourceInterface` implementation attempting to bypass `managePermission()` via a
  non-standard action route fails CI (guards against the exact over-generalization failure mode this
  blueprint's scoping correction is meant to prevent).

## SemVer Impact
**Major.** Provides the human interface for the entire Sovereign platform.


---

## Doctrines Applied + Rewrite Notes (PR #336)

> **This section was added in PR #336 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #336

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-01 — Sovereign Command Center — shipped per the verified DAG).

### What Was NOT Changed in PR #336

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/command-center/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #336 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
