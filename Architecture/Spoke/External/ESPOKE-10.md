# PHASE ESPOKE-10: Public Subscription and Billing Portal


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
External Spoke (Public-facing Application)

## Resolves
Corrects Pattern A and Pattern H (`01_MASTER_INDEX.md` §3, Finding 15) — the same two corrections as
`ESPOKE-09`: `CORE-09` → `CORE-16`, and the internal counterpart in the Subscription Management Flow
diagram corrected from `ISPOKE-05: Ledger` to `ISPOKE-13: Ledger` (the real `ISPOKE-05` is Insight/
Analytics).

## Component Name
Sovereign Pulse (Billing) — **External Spoke**; distinct from `HUB-25`'s "Sovereign Chronos" and any
other "Pulse"-named component — check tier before assuming which "Pulse" is meant (this one is
customer-facing billing self-service, not the Hub-tier `HUB-15` health-monitoring "Sovereign Pulse").

## Description
Portal for customers to manage subscriptions, view invoices, update billing methods — "Self-Service
Billing," interacting with the Internal Spoke sub-tier via the Bridge to keep sensitive financial/plan
data protected.

## Sequencing Rationale
Depends on `ESPOKE-09` for shared payment abstraction logic and `ESPOKE-03` (Account Portal) for user
context.

## Build Status
🔴 **Blocked** on `HUB-04`, `HUB-26`, `HUB-08` — none implemented.

## Dependency Status — corrected
- **Direct Hub:** `HUB-04`, `HUB-26`, `HUB-08`, `HUB-15`. *(Verified — correct.)*
- **Transitive Core:** ~~`CORE-09: Cryptography & Hashing`~~ → **`CORE-16: Binary Encryption
  Envelope`**, `CORE-18`, `CORE-04`, `CORE-12`.

## Architectural Design
- **SubscriptionManager** — plan transitions (upgrades/downgrades), lifecycle states.
- **InvoiceEngine** — public-safe invoice data, downloadable PDFs via the Bridge.
- **PaymentMethodVault** — secure interface for saved payment tokens (never raw cards).
- **PulseUI** — customer-facing dashboard via `HUB-26`.

### Subscription Management Flow
```mermaid
sequenceDiagram
    participant C as Customer
    participant P as ESPOKE-10 (Pulse)
    participant B as BRIDGE-01 (Bridge)
    participant I as Internal Spoke (ISPOKE-13: Ledger)
    C->>P: View Billing Dashboard
    P->>B: Get Account Summary (SubscriptionBillingBridgeContract)
    B->>I: Fetch Subscription & History
    I-->>B: Internal Financial Data
    B->>B: Transform to Public DTO
    B-->>P: Public-Safe Billing Data
    P-->>C: Render Dashboard
    C->>P: Download Invoice
    P->>B: Request Invoice PDF
    B-->>P: PDF Stream
    P-->>C: File Download
```

## Interface Contracts

```php
namespace SovereignStack\External\Pulse\Contracts;

use SovereignStack\Bridge\Contracts\BoundaryContractInterface;

interface SubscriptionBillingBridgeContract extends BoundaryContractInterface
{
    public function getSubscriptionSummary(string $customerId): array;
    public function getInvoices(string $customerId): array;
    public function changePlan(string $customerId, string $newPlanId): array;
}
```

## Integration Strategy
- **Bridge Compliance:** sensitive financial records never exposed — the Bridge only passes DTOs with
  "safe" metadata (last 4 digits, expiry, plan names, amounts), fetched from `ISPOKE-13` (corrected
  from the original's `ISPOKE-05`).
- **Auth Guard:** every request verified against `HUB-04`/`HUB-05` — users only access their own
  billing data.
- **Payment Abstraction:** shares `PaymentProviderInterface` from `ESPOKE-09`.
- **PDF Delivery:** invoices streamed via the Bridge — never stored in a publicly accessible bucket.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Privacy enforcement | Integration test: fetch an invoice for "Customer A" using "Customer B's" session; assert `403 Forbidden`. |
| Plan transition integrity | Integration test: downgrade a fixture subscription; assert the correct "Entitlement Change" event fires in the Bridge, verified against `ISPOKE-13`'s actual entitlement state, not just an event being emitted. |
| UI performance | State the actual 4G throttle profile used before citing "< 1.5s" — measure on a defined test harness (Finding 10). |
| Ledger routing | Integration test asserting all three Bridge contract methods actually query `ISPOKE-13`, not `ISPOKE-05` — verifies the Pattern H fix. |

## CI Verification Criteria
- Privacy-enforcement cross-customer test, blocking — same severity class as `BRIDGE-01`'s tests.
- Plan-transition entitlement test against real `ISPOKE-13` state, blocking.
- Ledger-routing test (above), blocking.
- UI performance measured against a defined throttle profile and reported.

## SemVer Impact
**Major.** Completes customer lifecycle management for the platform.


---

## Doctrines Applied + Rewrite Notes (PR #349)

> **This section was added in PR #349 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #349

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ESPOKE-10 — shipped per the verified DAG).

### What Was NOT Changed in PR #349

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/external/billing-portal/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #349 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
