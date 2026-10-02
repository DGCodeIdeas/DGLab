# ESPOKE-19: Eloq — Private AI Writing Assistant



> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**
<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->

> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Status:** 📝 Canonical (depth 1 — identity declared, implementation deferred)
**Date:** 2026-10-01 (canonical publication per ADR-021 §14; ratified 2026-09-30)
**Tier:** Spoke / External
**Namespace:** `SovereignStack\Spoke\External\Eloq`
**Package path:** `packages/spoke/external/eloq/` (to be created during implementation phase)

---

## Application Identity

**Eloq** is a private AI writing assistant combining four product capabilities:

1. **Notion-style block editor** with slash-menu blocks, contentEditable, document.execCommand
2. **Linguix-style grammar review** with 4-color inline highlights
3. **Document-aware AI paraphrasing** — 22 document types × 10 paraphrase styles across SFW/NSFW/academic/legal registers
4. **BYOK zero-knowledge privacy** — user's own Gemini API key, browser-only persistence with AES-GCM-256 at-rest encryption, plus EPUB/backup envelope export

The singular external identity of the Eloq application. Owns the public surface (routes, HTTP methods, response shapes, content negotiation, public auth), composition policy (which ISPOKEs this app composes), application configuration, cross-cutting policy, and entry-point orchestration.

Per ADR-021 §15 (ISPOKE Contract Lint Rule): the ESPOKE is a pure composition layer. Domain logic, business rules, direct Hub calls, state, and long-running work are FORBIDDEN in the ESPOKE source. Lint rule: ESPOKE source may only import from `SovereignStack\Spoke\*` and `SovereignStack\Application\*` namespaces; imports from `SovereignStack\Hub\*` or `SovereignStack\Core\*` are lint errors.

## Composition Policy (Application Manifest Draft)

```yaml
application: Eloq
espoke: ESPOKE-19
content_policy: neutral_parity  # per ADR-021 §7

# Hubs consumed (ISPOKEs compose Hubs; ESPOKE orchestrates ISPOKEs)
hubs:
  - HUB-04 Identity       # user auth (already shipped at depth 2)
  - HUB-32 AI Inference    # hierarchical LLM failover (canonical depth 1, implementation deferred)

# ISPOKEs composed (consumer-side, per ADR-021 §15)
workers:
  - ISPOKE-E1  Document Vault              # feature, reusable: false (Eloq-private)
  - ISPOKE-E2  Editor Block Engine         # abstraction, reusable: true
  - ISPOKE-E4  Paraphrase Engine           # abstraction, reusable: true (consumes HUB-32)
                                            #   NOTE: deferred per ADR-021 §9 (0 existing consumers)
  - ISPOKE-E5  Grammar Reviewer            # abstraction, reusable: true (consumes HUB-32)
  - ISPOKE-E6  Readability Auditor         # abstraction, reusable: true (pure library)
  - ISPOKE-E7  RAG Retriever               # abstraction, reusable: true (consumes HUB-14 + HUB-32)
  - ISPOKE-E8  BYOK Vault                  # abstraction, reusable: true (consumes HUB-20 + HUB-04)
  - ISPOKE-E9  Remote Backup Orchestrator  # abstraction, reusable: true (consumes RUNTIME-03 + RUNTIME-04 + HUB-11 + HUB-06)
  - ISPOKE-E10 EPUB Importer               # abstraction, reusable: true (consumes CORE-14)
  - ISPOKE-E11 Manuscript Exporter         # abstraction, reusable: true (consumes HUB-32; redaction optional per ADR-021 §15)
  - ISPOKE-E12 Censorship & Redaction      # feature, reusable: false (Eloq-private; consumed optionally by E11)
  - ISPOKE-E13 Privacy & Audit Ledger      # abstraction, reusable: true (slim; mechanism in HUB-06)
  - ISPOKE-E14 Writing Analytics           # feature, reusable: false (Eloq-private)
  - ISPOKE-E15 Theme Manager               # abstraction, reusable: true (HUB-26 absorption target per ADR-021 §18)
```

## Content Policy

Per tech-lead decision 2026-09-30 and ADR-021 §7: **neutral parity.**

- The 22 document-type × 10 paraphrase-style taxonomy ships intact, **including NSFW document types**.
- The per-doc `content_filter_setting` toggle (filtered vs. unfiltered) is a user choice, not platform-wide enforcement.
- The `BLOCK_NONE` Gemini safety setting is available as a user option.
- DGLab applies no platform-wide content filter for the Eloq ESPOKE. Other ESPOKEs may apply their own content policies via their own Application Manifests.

## Upward Dependencies

| Dependency | Type | Requiredness | Gates | Notes |
|---|---|---|---|---|
| HUB-04 Identity | CAPABILITY | REQUIRED | [INTEGRATION, PRODUCTION] | User auth + tenant resolution |
| HUB-32 AI Inference | CAPABILITY | REQUIRED | [INTEGRATION, PRODUCTION] | LLM invocation (4 ISPOKEs consume it) |
| CORE-18 Kernel | RUNTIME | REQUIRED | [RUNTIME, PRODUCTION] | Request lifecycle (depth-2 conditional per ADR-021 §21 — C17 stub) |
| RUNTIME-01 Anvil | RUNTIME | REQUIRED | [RUNTIME, PRODUCTION] | FrankenPHP worker for HTTP serving |

## Downstream Consumers

None — ESPOKE is the application identity; nothing consumes an ESPOKE.

## Implementation Status

- **implementation_depth:** 0 (no code exists; package directory not yet created)
- **integration_completeness:** NOT IMPLEMENTED
- **production_gate:** All Upward Dependencies must be at depth ≥2 + implementation must land

## Open Questions

1. **ISPOKE-E1 through E15 actual IDs:** the E1-E15 IDs are conceptual (from ELQ analysis). Actual ISPOKE-XX IDs will be assigned from the existing 27-slot ISPOKE catalog (ISPOKE-01..27) during admission. Some may reuse existing slots; others may extend the catalog.
2. **UI/UX patterns to preserve vs. redesign:** per ELQ-ANALYSIS-6 §7.5, decide which of (floating-pill paraphrase trigger, Linguix inline highlights, slash-menu block editor, 6 censorship visualizations, disaster-recovery HTML reader, three-pane writing environment) must be preserved verbatim vs. fitted to HUB-26 UI Elements (when shipped).

## Provenance

New External Spoke declared 2026-09-30 per tech-lead decision ("It's a new one" — not a rename of any existing planned ESPOKE). Cherry-pick source: `https://github.com/DGCodeIdeas/ELQ` (tech-lead-owned). Full ELQ analysis at `Architecture/Verification/HUB-EDGE-INVENTORY.md` (no — that's the Hub edge inventory; ELQ analysis is at `download/ELQ-ANALYSIS.md`). Canonical blueprint published 2026-10-01 per ADR-021 §14 (ratified pending canonical publication → now canonical at depth 1).
