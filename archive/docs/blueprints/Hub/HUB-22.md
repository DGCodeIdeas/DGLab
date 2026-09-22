# PHASE HUB-22: Billing & Subscription Abstraction Layer

## Tier
Hub (Shared Services)

## Component Name
Sovereign Ledger (Billing)

## Description
A provider-agnostic billing and subscription layer. It abstracts the complexities of Stripe, Paddle, or custom billing engines into a unified API. It manages plans, subscriptions, invoices, and payment methods.

## Sequencing Rationale
Depends on `HUB-21` (Tenancy) for billing isolation and `HUB-20` (Vault) for secure API keys.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-21: Tenancy`, `HUB-20: Vault`, `HUB-06: Audit Log`, `HUB-17: Webhooks`.
- **Transitive Core Dependencies**: `CORE-19: DBAL`, `CORE-03: Event Dispatcher`.
- **Abstraction**: Implements the "Payment Gateway" pattern.

## Architectural Design
- **BillingManager**: The entry point for subscription checks and checkout creation.
- **SubscriptionEngine**: Tracks the state of a tenant's subscription (Active, Trialling, Past Due).
- **InvoiceManager**: Generates and stores internal invoice records.
- **WebhookHandler**: Specifically handles billing-related webhooks via `HUB-17`.

## Interface Contracts

### BillingInterface
```php
namespace Sovereign\Hub\Contracts;

interface BillingInterface
{
    /**
     * Check if a tenant has an active subscription to a specific plan.
     */
    public function subscribed(string $tenantId, string $plan): bool;

    /**
     * Create a checkout session for a specific plan.
     */
    public function checkout(string $tenantId, string $plan): string;
}
```

## Integration Strategy
- **Upward**: Consumes `HUB-17` for async payment updates.
- **Downward**: Spoke applications use `BillingInterface` to guard features and initiate payments.
- **Contract**: Emits `SubscriptionUpdated` events on `HUB-09` for downstream processing.

## CI Verification Criteria
- **Mocking**: Must pass a suite using a "Mock Billing Driver" without external network calls.
- **State Accuracy**: Subscription status must correctly transition from `trialling` to `active` upon a simulated successful payment.
- **Security**: Credit card data must never touch the Sovereign server (enforced via driver design).

## SemVer Impact
**Minor**. Adds monetization capabilities.
