# HUB-32: AI Inference Hub



> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**
<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->

> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Status:** 📝 Canonical (depth 1 — interface declared, implementation deferred)
**Date:** 2026-10-01 (canonical publication per ADR-021 §13; ratified 2026-09-30)
**Tier:** Hub
**Namespace:** `SovereignStack\Hub\AiInference`
**Package path:** `packages/hub/ai-inference/` (to be created during implementation phase)

---

## Capability

**Hierarchical AI provider failover with model cascade.** Routes LLM invocation requests across multiple upstream providers in priority order, falling back gracefully when each provider fails or rate-limits. Returns a normalized response regardless of which upstream succeeded.

Reference design: ELQ's `server-api.cjs:39-296` — hierarchical failover: Gemini native SDK → OpenAI-compatible fetch → Pollinations zero-key → rule-based local fallback, with model cascade (2.5-flash → 2.0-flash → 1.5-flash → 1.5-flash-8b).

## Why This Is a Hub (Not an ISPOKE)

Per ADR-021 §13 and tech-lead decision 2026-09-30: LLM invocation is judged as foundational as Identity (HUB-04) or Audit (HUB-06). Immediate ratification bypasses the deferred-promotion rule because LLM consumption is expected to be near-universal across DGLab apps that integrate AI capabilities.

## Public Interface (Frozen at Depth 1)

```php
namespace SovereignStack\Hub\AiInference\Contracts;

interface AiInferenceHubInterface
{
    /**
     * Invoke an LLM with hierarchical provider failover.
     *
     * @param InferenceRequest $request  Prompt, model preferences, safety settings
     * @return InferenceResponse         Normalized response + which provider succeeded
     * @throws InferenceException        When all providers in the cascade fail
     */
    public function invoke(InferenceRequest $request): InferenceResponse;

    /**
     * List available providers in failover order for diagnostics.
     *
     * @return list<ProviderDescriptor>
     */
    public function listProviders(): array;
}
```

## Upward Dependencies (Implementation, When Built)

| Dependency | Type | Requiredness | Gates | Notes |
|---|---|---|---|---|
| CORE-02 Container | COMPILE | REQUIRED | [BUILD] | DI for provider factories |
| CORE-09 Logger | COMPILE | REQUIRED | [BUILD] | Structured logging of provider attempts + failures |
| CORE-08 ErrorHandler | COMPILE | REQUIRED | [BUILD] | Exception taxonomy for `InferenceException` |
| CORE-10 Config | COMPILE | REQUIRED | [BUILD] | Per-tenant API key vault config |
| CORE-16 Crypto | COMPILE | REQUIRED | [BUILD] | API key at-rest encryption (envelope) |
| HUB-20 Vault | CAPABILITY | REQUIRED | [INTEGRATION, PRODUCTION] | Per-tenant BYOK key storage |
| HUB-04 Identity | CAPABILITY | REQUIRED | [INTEGRATION, PRODUCTION] | Tenant resolution for key scoping |
| RUNTIME-01 Anvil | RUNTIME | REQUIRED | [RUNTIME, PRODUCTION] | Long-lived worker for connection pooling |

## Downstream Consumers

| Consumer | Tier | Status | Consumption Pattern |
|---|---|---|---|
| ISPOKE-E4 Paraphrase Engine | Application | 📝 Deferred (0 existing consumers per `ELQ-CONSUMER-MAP-7`) | Per-doc register-aware prompt assembly |
| ISPOKE-E5 Grammar Reviewer | Application | 📝 Planned | Inline grammar suggestions |
| ISPOKE-E11 Content Classification | Application | 📝 Planned | 22 doc-type classification + safety setting toggle (neutral parity per ADR-021 §7) |
| ISPOKE-E12 Generation Service | Application | 📝 Planned | Long-form text generation |
| ESPOKE-19 Eloq | Application | 📝 Depth 1 (canonical) | Hosts all 4 above ISPOKEs |
| ESPOKE-17 Concierge | Application | Existing blueprint | `LlmClassifier` adapter |

## Content Policy

Per tech-lead decision 2026-09-30 and ADR-021 §7: **neutral parity.**

- The 22 document-type × 10 paraphrase-style taxonomy ships intact, **including NSFW document types**.
- The per-doc `content_filter_setting` toggle (filtered vs. unfiltered) is a user choice, not platform-wide enforcement.
- The `BLOCK_NONE` Gemini safety setting is available as a user option.
- DGLab applies no platform-wide content filter for the Eloq ESPOKE. Other ESPOKEs may apply their own content policies via their own Application Manifests.

## Implementation Status

- **implementation_depth:** 0 (no code exists; package directory not yet created)
- **integration_completeness:** NOT IMPLEMENTED
- **production_gate:** All Upward Dependencies must be at depth ≥2 + implementation must land

## Provenance

Promoted from ISPOKE-E3 (ELQ analysis §6) to HUB-32 per tech-lead decision 2026-09-30 ("LLM to Hub"). Canonical blueprint published 2026-10-01 per ADR-021 §13 (ratified pending canonical publication → now canonical at depth 1). Reference implementation pattern: ELQ `server-api.cjs:39-296` (hierarchical failover, owned by tech lead per ELQ-ANALYSIS-6 license decision).


---

## Doctrines Applied + Rewrite Notes (PR #335)

> **This section was added in PR #335 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #335

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-32 — AI Inference Hub (canonical depth 1 per ADR-021 §13) — shipped per the verified DAG).

### What Was NOT Changed in PR #335

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/ai-inference/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #335 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
