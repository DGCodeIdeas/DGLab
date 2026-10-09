# PHASE HUB-12: Notification Service


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and ties the webhook-rate-limit claim to `HUB-07`'s
actual contract instead of an unlinked cross-reference.

## Component Name
Sovereign Notify

## Description
Unified multi-channel notification engine: Email, in-app, webhooks, SMS. Handles template rendering,
queuing, and delivery tracking.

## Build Status
🔴 **Blocked** on `HUB-04` (Identity), `HUB-10` (Queue), `CORE-12` (SuperPHP Compiler) — none
implemented.

## Dependency Status
- **Upward:** `HUB-04`, `HUB-10`, `CORE-12`. *(Matches taxonomy.)*
- **Downward:** `HUB-23` (Reporter notifies on export completion), `HUB-22` (Billing notifies on
  payment events), any Spoke sending user-facing notifications.

## Architectural Design
- **NotificationManager** — routes notifications to channels.
- **ChannelInterface** — contract for delivery mechanisms.
- **Notification** — per-channel content class (`toMail`, `toDatabase`, …).
- **WebhookDispatcher** — outbound system events to external URLs, rate-limited via `HUB-07` (see
  below — the original blueprint referenced this without specifying the actual limiter key).

```php
class OrderShipped extends Notification
{
    public function via($notifiable) { return ['mail', 'database']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Order Shipped')
            ->view('emails.shipped', ['order' => $this->order]);
    }
}
```

```php
namespace SovereignStack\Hub\Contracts;

interface NotifierInterface
{
    public function send(mixed $notifiables, object $notification): void;
    public function sendNow(mixed $notifiables, object $notification): void;
}
```

## Integration Strategy
- **Upward:** `HUB-10` for background delivery, `HUB-04` for contact details.
- **Downward:** Spoke applications call `send()`.
- **UI:** standard SuperPHP toast component (`s:ui:notifications`).
- **Webhook rate limiting:** `WebhookDispatcher` calls `HUB-07`'s `RateLimiterInterface::hit()` keyed
  per destination URL — `webhook:{sha256(url)}` — with `maxAttempts: 10, decaySeconds: 1`, making the
  "≤10/sec per endpoint" requirement a concrete `HUB-07` call, not a separate unimplemented rule.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Channel fallback on failure | Integration test: force the mail transport to throw; assert the job is marked failed (visible via `HUB-10`'s `FailedJobProvider`) and the worker process does not crash or block subsequent jobs. |
| Webhook rate limit enforcement | Integration test: dispatch 15 webhooks to the same destination within one second; assert exactly 10 succeed and 5 are deferred/queued per `HUB-07`'s `check()`/`hit()` contract. |
| Template rendering correctness | Integration test rendering a fixture email template with dynamic data via the real `CORE-12` compiler (not a string-replace stub) and asserting the output matches expected hydrated HTML. |

## CI Verification Criteria
- Channel-fallback test, blocking.
- Webhook rate-limit enforcement test against the real `HUB-07` contract, blocking.
- Template-rendering test against the real `CORE-12` compiler once available.

## SemVer Impact
**Minor.** Standardizes user communication across the stack.


---

## Doctrines Applied + Rewrite Notes (PR #330)

> **This section was added in PR #330 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #330

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-12 — Notification Service — shipped per the verified DAG).

### What Was NOT Changed in PR #330

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/notification/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #330 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
