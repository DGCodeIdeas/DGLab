# HUB-32: AI Inference Hub


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
