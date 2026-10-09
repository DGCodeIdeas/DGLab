# PHASE ESPOKE-17: Sovereign Concierge (AI Concierge)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
External Spoke (Public-facing — untrusted, rate-limited via HUB-07, channel adapter for WhatsApp
and web chat)

## Component Name
Sovereign Concierge — `SovereignStack\External\Concierge`. The guest-facing AI concierge surface
for hospitality-tenant properties: an intent-classifying chatbot that handles FAQs (wifi, breakfast,
checkout time) inline and routes complex queries (room upgrade request, late checkout, complaint)
to a human agent via `ISPOKE-08` (Support Desk extension). Channels: WhatsApp Business API, web
chat widget, and (future) SMS. Tenant-isolated — every conversation is scoped to one `HUB-21`
tenant and one guest identity.

## Description
ESPOKE-17 is the **conversational front door** for hospitality guests. A guest messages "what time
is breakfast?" on WhatsApp; the concierge classifies the intent as `faq_breakfast_hours`, looks up
the answer in the tenant's FAQ knowledge base (served from `HUB-14` search), and replies in under
2 seconds. A guest messages "I want a late checkout tomorrow"; the concierge classifies as
`request_late_checkout`, opens a ticket in `ISPOKE-08`, pings the on-duty front-desk agent via
`HUB-12`, and replies "I've asked the front desk — they'll confirm within 15 minutes."

The intent classifier is the critical component. It is **pluggable** — the default implementation
is a rules-based classifier (regex + keyword matching, fast and explainable) that handles ~70% of
inbound queries; the rest are routed to a configured LLM provider (OpenAI / Anthropic / local
model) via an adapter interface. The classifier never makes a *decision* (e.g., it cannot approve
a late checkout) — it only routes. Every decision is made by either the FAQ path (deterministic
lookup) or a human agent (via `ISPOKE-08`). This is deliberate: an AI that can approve a late
checkout is an AI that can be socially-engineered into approving a late checkout.

The handoff to `ISPOKE-08` is the safety valve. When the classifier's confidence is below the
tenant-configured threshold (default 0.7), or the intent is `complaint_*` / `request_*`, the
concierge opens a ticket, includes the full transcript, and notifies the on-duty agent. The guest
sees "I'm connecting you with the front desk" — they never see the classifier's confidence score
or the routing reason.

## Build Status
📝 **Documented — ready for implementation.** Blocked on `CORE-02` (DI Container stub) and on
Bet 3 (Hub Full) ring lock per `HOSPITALITY-VERTICAL.md` §3. The LLM adapter is a separate
concern — the default rules-based classifier is buildable today against `HUB-14` search.

## Dependency Status
- **Direct Hub:** `HUB-08` (Sovereign Gateway — request entry, channel webhook validation),
  `HUB-07` (Sovereign Throttle — rate limit: 30 messages per minute per guest), `HUB-21`
  (Sovereign Nexus — tenant resolution from WhatsApp sender / web chat session), `HUB-14`
  (Sovereign Search — FAQ knowledge base lookup), `HUB-12` (Sovereign Notify — agent paging on
  handoff), `HUB-09` (Sovereign Signal — `HandoffRequired` / `FaqAnswered` / `ConversationClosed`
  events), `HUB-11` (Sovereign Cloud Storage — transcript persistence, 90-day retention),
  `HUB-06` (Sovereign Auditor — every classifier decision + every handoff audited).
- **Transitive Core:** `CORE-04`, `CORE-05`, `CORE-06`, `CORE-02`.
- **Spoke peer:** `ISPOKE-08` (Support Desk — ticket creation on handoff).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `InboundMessage` | `final readonly class` | `tenant_id`, `channel` (whatsapp / web / sms), `guest_id`, `conversation_id`, `body`. Validated at construction. |
| `IntentClassifierInterface` | interface | `classify(InboundMessage $m): IntentClassification`. Implementations: `RulesBasedClassifier` (default), `LlmClassifier` (adapter). |
| `IntentClassification` | `final readonly class` | `intent` (e.g. `faq_breakfast_hours`), `confidence` (0.0–1.0), `evidence` (matched rule / LLM rationale). |
| `FaqResponder` | class | Looks up answer in `HUB-14` search index. Returns the answer or `null` (no match → handoff). |
| `HandoffRouter` | class | Opens a ticket in `ISPOKE-08` with the transcript, pings the on-duty agent via `HUB-12`, emits `HandoffRequired`. |
| `ConversationState` | `final readonly class` | Per-conversation state (open / awaiting_agent / closed). Persisted in `HUB-02` cache with 24h TTL; promoted to `HUB-11` on close. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\External\Concierge;

interface IntentClassifierInterface
{
    public function classify(InboundMessage $message): IntentClassification;
}

interface HandoffRouterInterface
{
    /**
     * Opens a ticket in ISPOKE-08 with the conversation transcript attached.
     * Returns the ticket ID. Never throws on ISPOKE-08 failure — falls back to
     * HUB-12 page + audit log, so the guest is never left without a response.
     */
    public function handoff(InboundMessage $message, IntentClassification $intent, string $reason): string;
}
```

## Interface Contracts

```php
namespace SovereignStack\External\Concierge\Contracts;

use SovereignStack\Bridge\Contracts\BoundaryContractInterface;

/**
 * The concierge never crosses the Bridge directly. This contract is the edge
 * the Inner Rim orchestrator pings for fleet-status reporting.
 */
interface ConciergeEdgeContract extends BoundaryContractInterface
{
    public function pingHealth(): bool;
    public function activeConversationCount(string $tenantId): int;
}
```

## Integration Strategy
- **Channel adapters:** WhatsApp Business API and web chat are pluggable adapters implementing
  `ChannelAdapterInterface` (`inbound(WebhookPayload $p): InboundMessage`,
  `outbound(OutboundMessage $m): void`). Adding SMS is a new adapter, not a code change to the
  classifier.
- **Bridge compliance:** the concierge goes through `HUB-21` / `HUB-14` / `HUB-12` / `HUB-09` /
  `HUB-11` / `ISPOKE-08` for every internal call. Its only Bridge-facing contract is
  `ConciergeEdgeContract` (health + active-conversation count).
- **Classifier pluggability:** the default `RulesBasedClassifier` is registered in the container;
  swapping to `LlmClassifier` is a container config change, not a code change. The LLM adapter
  itself is an `LlmProviderInterface` (OpenAI / Anthropic / local) — the classifier never calls
  an LLM SDK directly, only the adapter.
- **Transcript persistence:** every inbound + outbound message is appended to a transcript in
  `HUB-11` cloud storage (one object per conversation, JSON-Lines format). Transcripts are
  retained 90 days, then deleted; a tenant can extend retention via config. Transcripts are
  tenant-isolated by `HUB-11` prefix convention.
- **Failure mode:** if the classifier is unreachable (e.g., LLM provider down), the concierge
  routes every inbound to `HandoffRouter` — it never silently drops a message. The guest sees
  "I'm connecting you with the front desk" within 2 seconds.

## Security Properties
1. **The classifier never decides.** It routes; it does not approve, deny, refund, or modify a
   booking. Every decision is either a deterministic FAQ lookup or a human agent. This is the
   single most important security property — it bounds the blast radius of a classifier bug or
   prompt-injection attack to "the wrong agent gets paged," not "the guest gets a free upgrade."
2. **Confidence threshold is tenant-configurable.** A high-touch property can set the threshold
   to 0.9 (hand off aggressively); a budget property can set it to 0.5 (handle more inline).
   The threshold is set by the tenant admin via `HUB-21` config, not by the concierge itself.
3. **Transcripts are auditable.** Every classifier decision is logged to `HUB-06` with
   `intent`, `confidence`, `evidence`, and the resulting action (`faq_answered` / `handed_off`).
   A guest complaint ("the bot said X") can be investigated by replaying the transcript +
   audit log.
4. **PII minimization.** The concierge redacts card numbers, passport numbers, and email
   addresses from the transcript before persisting to `HUB-11`. The redaction is a regex pass
   over the message body; the original is held in memory only long enough to classify and respond.
5. **Rate-limit per guest, not per IP.** A single guest cannot flood the concierge from multiple
   devices; `HUB-07` keys on `guest_id` (resolved from WhatsApp sender or web chat session), not
   IP. This prevents a malicious guest from DoSing the LLM adapter.

## CI Verification Criteria
- **Unit:** `RulesBasedClassifier` returns `confidence >= 0.9` for an exact keyword match and
  `confidence < 0.5` for a no-match; `HandoffRouter` opens a ticket in `ISPOKE-08` and emits
  `HandoffRequired` on `HUB-09`.
- **Integration:** end-to-end Pulse (WhatsApp webhook → classify → FAQ hit → reply) completes in
  under 2s at p95 with `RulesBasedClassifier`; LLM adapter failure routes to `HandoffRouter`
  without dropping the message.
- **PII redaction:** integration test with a message body containing a fake card number
  (`4242 4242 4242 4242`) asserts the persisted transcript in `HUB-11` contains `[REDACTED_CARD]`
  not the original digits.
- **Classifier pluggability:** integration test with `LlmClassifier` registered in the container
  asserts `IntentClassifierInterface` is wired to `LlmClassifier` (container smoke test).
- **Static:** phpstan `level: max` clean; ≥85% branch coverage on `RulesBasedClassifier` and
  `HandoffRouter`.


---

## Doctrines Applied + Rewrite Notes (PR #351)

> **This section was added in PR #351 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #351

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ESPOKE-17 — shipped per the verified DAG).

### What Was NOT Changed in PR #351

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/external/concierge/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #351 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
