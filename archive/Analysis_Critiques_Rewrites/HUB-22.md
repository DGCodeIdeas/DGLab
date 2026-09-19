# PHASE HUB-22: Billing & Subscription Abstraction Layer

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
