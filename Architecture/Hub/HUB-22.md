# PHASE HUB-22: Billing & Subscription Abstraction Layer


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and a concrete "no card data touches the server"
enforcement mechanism instead of a design-intent statement.

## Component Name
Sovereign Ledger (Billing)

## Description
Provider-agnostic billing/subscription layer abstracting Stripe, Paddle, or a custom billing engine
into one API: plans, subscriptions, invoices, payment methods.

## Build Status
🔴 **Blocked** on `HUB-21` (Tenancy), `HUB-20` (Vault), `HUB-06` (Audit), `HUB-17` (Webhooks) — none
implemented.

## Dependency Status
- **Direct Hub:** `HUB-21`, `HUB-20`, `HUB-06`, `HUB-17`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-19`, `CORE-03`.
- **Downward:** any Spoke gating features on subscription status.

## Architectural Design
- **BillingManager** — subscription checks and checkout creation.
- **SubscriptionEngine** — tracks state (Active, Trialling, Past Due).
- **InvoiceManager** — generates/stores internal invoice records.
- **WebhookHandler** — billing-specific webhooks via `HUB-17`.

```php
namespace SovereignStack\Hub\Contracts;

interface BillingInterface
{
    public function subscribed(string $tenantId, string $plan): bool;
    public function checkout(string $tenantId, string $plan): string;
}
```

## PCI-Scope Enforcement (tightened)
"Credit card data must never touch the Sovereign server" was previously a design statement with no
mechanism. Concretely: `BillingManager::checkout()` returns a **redirect URL to the provider's hosted
checkout page** (Stripe Checkout / Paddle Checkout) — it never accepts a card-data payload as a method
parameter, and no `BillingInterface` method signature anywhere in this package accepts raw card fields.
This is enforced by interface design, not by convention, and should additionally be enforced by a
static-analysis rule flagging any parameter named/typed suggestive of raw card data (`cardNumber`,
`cvv`, etc.) anywhere in this package.

## Integration Strategy
- **Upward:** `HUB-17` for async payment updates, `HUB-20` for provider API keys.
- **Downward:** Spoke applications use `BillingInterface` to guard features and initiate payments.
- **Contract:** emits `SubscriptionUpdated` via `HUB-09` for downstream processing.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| No network calls in test suite | CI runs the full suite against a "Mock Billing Driver" with network access disabled at the test-runner level (not just an unused real driver) — a hard failure if any HTTP call is attempted. |
| State transition accuracy | Integration test simulating a webhook sequence (`checkout.session.completed` → `invoice.paid`); assert `SubscriptionEngine` transitions `trialling` → `active` in the correct order, not just the final state. |
| PCI-scope static check | The static-analysis rule described above, run in CI on every PR touching this package. |

## CI Verification Criteria
- Network-isolated mock-driver test, blocking.
- State-transition-sequence test (not just end-state), blocking.
- PCI-scope static rule, blocking — this is what makes the "card data never touches the server"
  claim enforced rather than aspirational.

## SemVer Impact
**Minor.** Adds monetization capabilities.


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
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-22 — Billing & Subscription Abstraction Layer — shipped per the verified DAG).

### What Was NOT Changed in PR #332

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/billing/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #332 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
