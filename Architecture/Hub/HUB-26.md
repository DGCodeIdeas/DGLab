# PHASE HUB-26: Shared UI Component Library (PHP-rendered)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Grounds this blueprint against `ISPOKE-01.md`'s `AdminShell` and `ESPOKE-01.md`'s "Public Theme"
consumption pattern, both of which already depend on this component — makes the two variants
(Admin vs. Public theme) an explicit, named contract instead of an implicit assumption.

## Component Name
Sovereign UI (Elements)

## Description
Reusable UI component library (Buttons, Modals, Tables, Forms) rendered entirely in PHP via SuperPHP
(`CORE-11`/`CORE-12`), ensuring visual/functional consistency across Spoke applications without
Node/NPM.

## Build Status
🔴 **Blocked** on `HUB-03` (Asset Pipeline) and `HUB-13` (I18n) — neither implemented. Also
transitively blocked on `CORE-11`/`CORE-12` (SuperPHP Parser/Compiler), which are later in the revised
Core sequence (`01_MASTER_INDEX.md` §5) — this is one of the later-buildable Hub components as a
result, despite being architecturally foundational for UI.

## Dependency Status
- **Direct Hub:** `HUB-03`, `HUB-13`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-11`, `CORE-12`.
- **Downward:** `ISPOKE-01` (Admin theme variant), `ESPOKE-01` (Public theme variant) — every Spoke.

## Theme Variant Contract (new — makes an implicit assumption explicit)
`ISPOKE-01.md` and `ESPOKE-01.md` both assume a themed variant of this library exists ("Admin theme,"
"Public theme") without this blueprint previously defining what that means concretely:

```php
namespace SovereignStack\Hub\Contracts;

interface ThemeInterface
{
    /** Design tokens (colors, spacing, typography) as CSS custom properties. */
    public function tokens(): array;

    /** Component variant overrides for this theme (e.g., denser table rows in Admin). */
    public function componentOverrides(): array;
}
```
`ComponentRegistry` resolves the active `ThemeInterface` from `HUB-01` config (per-Spoke, not
per-request) and applies token/override resolution at render time. Internal Spokes register the Admin
theme; External Spokes register the Public theme. This is the mechanism, not just the naming
convention, behind "Admin Theme" / "Public Theme" as used elsewhere in this document set.

## Architectural Design
- **ComponentRegistry** — maps tag names (`<s:ui:button />`) to SuperPHP view files.
- **ThemeEngine** — CSS variables/design tokens for the stack, resolves `ThemeInterface` per above.
- **IconLibrary** — pure-PHP SVG injector.
- **LayoutRegistry** — master shell layouts (Admin, Dashboard, Landing).

```php
namespace SovereignStack\Hub\Contracts;

interface UIComponentInterface
{
    public function render(array $attributes = []): string;
}
```

## Integration Strategy
- **Upward:** assets bundled/served via `HUB-03`.
- **Downward:** all Spokes MUST use these components for consistent branding — enforced by
  `ISPOKE-01.md`'s and `ESPOKE-01.md`'s "100% of rendered tags originate from HUB-26" CI checks.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Void-tag compliance | Static scan of every component template for void elements (`input`, `img`) lacking explicit self-closing syntax — required by SuperPHP's parser contract (`CORE-11`). |
| Bundle size | Measure actual gzipped size of the compiled core CSS/JS via the real `HUB-03` build pipeline once it exists; report the number, don't restate "< 150KB" unmeasured (Finding 10). |
| Accessibility baseline | Automated ARIA-role/label presence check across every component's rendered output — a floor, not a substitute for manual accessibility review. |
| Theme resolution correctness | Integration test: render the same component under both Admin and Public theme configuration; assert `componentOverrides()` correctly changes rendered output where a theme-specific override is defined. |

## CI Verification Criteria
- Void-tag static scan, blocking.
- Accessibility baseline scan, blocking.
- Theme resolution test (above), blocking — new, verifies the Theme Variant Contract actually works.
- Bundle size measured and reported with the real pipeline once available.

## SemVer Impact
**Minor.** Establishes the visual language of the Sovereign Stack.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-26 — Shared UI Component Library — shipped per the verified DAG).

### What Was NOT Changed in PR #333

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/ui-library/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #333 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
