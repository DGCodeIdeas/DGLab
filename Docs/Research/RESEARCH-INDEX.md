# DGLab Research Index

**Created:** 2026-10-20  
**Status:** Research deliverable — links all research reports and separates evidence from recommendations  
**Parent:** `RESEARCH-AGENDA.md` (root)

---

## 1. Research Report Inventory

| # | Report | Location | Status | Evidence Level |
|---|--------|----------|--------|----------------|
| 1 | **Research Agenda (canonical draft)** | `RESEARCH-AGENDA.md` (root) | Draft — 5 tracks + NeoSPX (historical: SuperPHP) | Hypotheses + deliverables + related doctrines. Status: Research — Not an Implementation Requirement |
| 2 | **V1–V7 Reconciliation Matrix** | `RESEARCH-RECONCILIATION-MATRIX.md` (root) | Draft — complete | Reconstructed from handoff + SAAI briefing + agenda. Includes source/uncertainty register. |
| 3 | **SPX / NeoSPX Standalone Report** | `SPX-NAMING-COLLISION-REPORT.md` (root) | Draft — complete | Responsibilities, architecture comparison, format analysis, naming collision audit (internal + external) |
| 4 | **Track 1: Platform integrity/copyright/fair disputes** | Embedded in `RESEARCH-AGENDA.md` Track 1 | Draft | Hypotheses + deliverables defined. Web research: MC Law, WIPO ADR, EU Art. 17, DMCA policies. |
| 5 | **Track 2: Avoiding YouTube model** | Embedded in `RESEARCH-AGENDA.md` Track 2 | Draft | Hypotheses + deliverables defined. Web research: complementor governance, creator power asymmetry, advertising as governance. |
| 6 | **Track 3: Half Exceptions** | Embedded in `RESEARCH-AGENDA.md` Track 3 | Draft | 4 formulations enumerated. Web research confirms no SE term exists (only hardware/SIMD). |
| 7 | **Track 4: Cancel-Anytime paradox** | Embedded in `RESEARCH-AGENDA.md` Track 4 | Draft | Empirical evidence found: Bryant study, FTC Uber complaint, UX dark patterns, consumer surveys. |
| 8 | **Track 5: Connecting principles** | Embedded in `RESEARCH-AGENDA.md` Track 5 | Draft | 3 variants now presented side-by-side. Reconciliation recommendation provided. |
| 9 | **NeoSPX naming investigation** | Embedded in `RESEARCH-AGENDA.md` + standalone `SPX-NAMING-COLLISION-REPORT.md` | Draft | Internal collision audit (5 candidates). External audit: Foundry (`coderstm/foundry`), SPX (`noisebynorthwest/php-spx`). |
| 10 | **SPX Ease-of-Use & Human/AI Comprehension** | `SPX-EASE-OF-USE-RESEARCH.md` (root) | Draft — complete | 44 sources. Key finding: dual-audience design requires verbose keywords (Anka) + PHP expressions (Latte) + dual surface (LLMON) + formal grammar (SynCode) + measured benchmarks (TOON/SUS). |
| 11 | **Vue SFC & Component-Format Research** | `VUE-SFC-COMPONENT-FORMAT-RESEARCH.md` (root) | Draft — complete | 21 sources. Key finding: sectioned SFC is proven pattern; Marko's dual modes validate syntax drivers; DSDS audience tagging is emerging standard; HTML-first viable but hits reusability limits. |
| 12 | **Framework Structure, DI, DX & Accessibility** | `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` (root) | Draft — complete | 70 sources. Key finding: feature-sliced beats layer-based for change locality; PSR-11 standardizes fetching only; frameworks don't make apps accessible; power-vs-simplicity via Kay/Norman/Maeda. |
| 13 | **SPX / NeoSPX Compiler Construction Principles** | `SPX-COMPILER-CONSTRUCTION-RESEARCH.md` (root) | Draft — complete | Source: CIT 316 course guide (NOUN). Maps Chomsky hierarchy, regex→NFA→DFA, recursive descent, CFG formalism, codegen phases, error handling, symbol tables to CORE-07/11/12. |
| 14 | **Component-Based Template Formats (Vue SFC & peers)** | `COMPONENT-TEMPLATE-FORMATS-RESEARCH.md` (root) | Draft — complete | 10+ formats (Vue, Svelte, Astro, Solid, Qwik, Marko, Lit, Web Components, HTMX/Alpine, meta-frameworks). Key finding: HTML-superset surface + PHP expressions + verbose keyword directives + tagged structure + dual surface. |
| 15 | **This Index** | `RESEARCH-INDEX.md` (root) | Draft — complete | Links all 14 reports + separates evidence from recommendations. |

---

## 2. Evidence vs. Recommendation Separation

### Evidence (Verified Facts)

| # | Fact | Source | Verification |
|---|------|--------|--------------|
| E1 | ADR-005 ratified SuperPHP (now NeoSPX) over Blade/Twig | `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md` | ✅ Verified |
| E2 | CORE-07, CORE-11, CORE-12 are all `📝 Not started` | `Architecture/INDEX.md` §1 lines 109, 113, 114 | ✅ Verified |
| E3 | `CORE-20` = "Sovereign Forge" (CLI Toolchain) | `Architecture/INDEX.md` §1 line 122 | ✅ Verified |
| E4 | `HUB-17` = "Sovereign Webhook Nexus", `HUB-21` = "Sovereign Nexus (Tenancy)" | `Architecture/INDEX.md` §2.3 lines 146, 149 | ✅ Verified |
| E5 | No matches for `\bFoundry\b` or `\bSPX\b` in `Architecture/` or `docs/` | grep (2026-10-13) | ✅ Verified |
| E6 | `coderstm/foundry` exists on Packagist (SaaS framework) | Packagist (2026-09-02) | ✅ Verified (web) |
| E7 | `noisebynorthwest/php-spx` exists on Packagist (profiling extension) | Packagist (2026-08-01) | ✅ Verified (web) |
| E8 | JSON Schema 2020-12 redesigned array/tuple keywords, dynamic references, embedded schemas | JSON Schema spec | ✅ Verified (web) |
| E9 | YAML RFC 9512 documents JSON interoperability issues | RFC 9512 | ✅ Verified (web) |
| E10 | "Half Exceptions" has no established SE meaning (only hardware/SIMD) | Web search (Intel patent US 6,691,223) | ✅ Verified (web) |
| E11 | Bryant University study: cancel options increase purchase intent for long-term commitments | Academic paper (2023) | ✅ Verified (web) |
| E12 | FTC Uber complaint: "cancel anytime" with 12+ actions, 7 screens, 48-hour restriction | FTC legal document (2025) | ✅ Verified (web) |
| E13 | 82% of consumers more likely to subscribe when cancellation is easy | Business Wire survey (2025) | ✅ Verified (web) |
| E14 | EU Copyright Directive Art. 17 requires fair balance, exception handling, complaints/redress | Academic analysis (2026-09-24) | ✅ Verified (web) |
| E15 | WIPO provides ADR for digital copyright disputes (mediation, expert determination, DCTI) | WIPO AMC | ✅ Verified (web) |

### Recommendations (Agent-Authored, Not Decisions)

| # | Recommendation | Track | Status |
|---|---------------|-------|--------|
| R1 | Use Variant B as primary connecting-theme question, A as thesis, C as method | Track 5 | **Proposed — owner decision required** |
| R2 | Keep NeoSPX as separate section (not numbered track) | NeoSPX (historical: SuperPHP) | **Proposed — owner decision required** |
| R3 | JSON-first with YAML as supported authoring format | SPX | **Proposed — owner decision required** |
| R4 | Disqualify Forge, Nexus, Sovereign by internal collision; Foundry and SPX remain candidates | SuperPHP | **Proposed — owner decision required** |
| R5 | Confirm Half Exceptions formulations 3 & 4 with owner | Track 3 | **Proposed — owner decision required** |
| R6 | Authorize `Architecture/CrossCutting/RESEARCH-AGENDA.md` publication | All | **Proposed — owner decision required** |

---

## 3. Research Gate Status

| Criterion | Status |
|-----------|--------|
| All 5 track reports + NeoSPX report delivered | ✅ In `RESEARCH-AGENDA.md` |
| V1–V7 reconciliation matrix | ✅ `RESEARCH-RECONCILIATION-MATRIX.md` |
| Three connecting-theme variants side-by-side | ✅ Updated in `RESEARCH-AGENDA.md` Track 5 |
| Standalone SPX naming/collision report | ✅ `SPX-NAMING-COLLISION-REPORT.md` |
| Research index linking all reports | ✅ This document |
| SPX ease-of-use & Human/AI research | ✅ `SPX-EASE-OF-USE-RESEARCH.md` |
| Vue SFC & component-format research | ✅ `VUE-SFC-COMPONENT-FORMAT-RESEARCH.md` |
| Framework structure, DI, DX & accessibility research | ✅ `FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md` |
| Owner approves canonical agenda wording | ❌ **Pending** |
| Owner authorizes publication | ❌ **Pending** |
| No research deliverables in LMS PRs | ✅ Compliant |

---

## 4. Next Steps

1. **Owner reviews** the research package (this index + reconciliation matrix + SPX report + updated agenda)
2. **Owner decides** connecting-theme variant, Half Exceptions formulations, NeoSPX placement, naming candidates
3. **Owner authorizes** publication of `Architecture/CrossCutting/RESEARCH-AGENDA.md`
4. **Research gate closes** — LMS backend implementation can begin (Gate 0)
5. **Research findings inform** future architecture but do not authorize changes to code, ADRs, or governance

---

**End of research index.** This document is a research deliverable. It does not authorize any repository change, ADR amendment, or implementation. All recommendations require owner approval.