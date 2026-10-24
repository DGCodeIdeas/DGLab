# Research-to-Implementation Register

**Created:** 2026-10-22  
**Status:** Research deliverable — maps research findings to implementation decisions. Does NOT authorize implementation. All decisions require owner approval.  
**Parent:** `RESEARCH-INDEX.md` (root)  
**Rule:** No finding is implemented twice. Conflicting recommendations are flagged, not merged.

---

## Decision Key

| Code | Meaning |
|------|---------|
| A | Accepted (owner-approved) |
| R | Rejected |
| S | Superseded by newer finding |
| D | Duplicate of another finding (cross-ref) |
| W | Awaiting owner decision |

---

## 1. SPX / SuperPHP Naming (`SPX-NAMING-COLLISION-REPORT.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-NAM-01 | SPX-NAMING §5 | Forge collides internally with CORE-20 "Sovereign Forge" | A (rejected as name) | N/A (naming only) | Exclude "Forge" from candidate list | Candidate list no longer includes Forge | N/A (doc) | Verified: `Architecture/INDEX.md` §1 line 122 |
| F-NAM-02 | SPX-NAMING §5 | Nexus collides with HUB-17 and HUB-21 | A (rejected as name) | N/A | Exclude "Nexus" from candidate list | Candidate list no longer includes Nexus | N/A | Verified: `Architecture/INDEX.md` §2.3 lines 146, 149 |
| F-NAM-03 | SPX-NAMING §5 | Sovereign is Hub-tier prefix (26+ components) | A (rejected as name) | N/A | Exclude "Sovereign" as standalone name | Candidate list excludes Sovereign | N/A | Verified: grep `Architecture/` |
| F-NAM-04 | SPX-NAMING §5 | Foundry clean internally; external `coderstm/foundry` medium risk | W | `packages/` (future) | Decide Foundry vs SPX vs function-based name | Owner selects name; ADR-005 amendment authorized | N/A | Blocker: owner decision; R4 in index |
| F-NAM-05 | SPX-NAMING §5 | SPX clean internally; external `noisebynorthwest/php-spx` medium-high risk | W | `packages/` (future) | Same as F-NAM-04 | Same | N/A | Blocker: owner decision |
| F-NAM-06 | SPX-NAMING §4 | Component document JSON-first vs JSON+YAML | W | CORE-07/11/12 blueprints | Decide JSON-first or JSON+YAML-equal | ADR or frozen contract updated | Schema validation tests | Blocker: owner decision (R3) |
| F-NAM-07 | SPX-NAMING §6 | Rename cost estimate not produced | W | `Architecture/`, `packages/` | Grep rename cost across all references | Count of "SuperPHP" references reported | N/A | Not yet produced |

---

## 2. SPX Ease-of-Use (`SPX-EASE-OF-USE-RESEARCH.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-EASE-01 | SPX-EASE §5.1.1 | Keep PHP expressions verbatim (Latte strategy) | W (proposed) | CORE-07 lexer, CORE-11 parser | Adopt PHP expressions in template surface | Expressions parse as PHP; no new expression language | Parser unit tests | Blocker: owner decision |
| F-EASE-02 | SPX-EASE §5.1.2 | Verbose keyword-based single-canonical directives (Anka) | W (proposed) | CORE-11 parser | Define `@if`/`@foreach`/etc. one written form | EBNF lists one form per construct | Grammar tests | Blocker: owner decision |
| F-EASE-03 | SPX-EASE §5.1.3 | Safe-by-default escaping | W (proposed) | CORE-12 codegen | Default escape `{{ }}`; raw marker explicit | Output escaped unless raw marker | Codegen snapshot tests | Blocker: owner decision (marker TBD) |
| F-EASE-04 | SPX-EASE §5.1.4 | Explicit tagged component structure (POML/LLMON) | W (proposed) | CORE-07/11 | Use `<s:...>` tags for control flow | Tags self-describing in AST | Parser tests | Blocker: owner decision |
| F-EASE-05 | SPX-EASE §5.1.5 | Small core + progressive disclosure | W (proposed) | CORE-12 | Minimal directive set v1 | v1 feature list documented | Feature-gate tests | Blocker: owner decision |
| F-EASE-06 | SPX-EASE §5.1.6 | Dual surface human ↔ machine canonical form | W (proposed, conflicts with F-EASE-02 single form) | CORE-07/11/12 | Decide single vs dual surface | Decision recorded | N/A | Blocker: owner decision; tension flagged |
| F-EASE-07 | SPX-EASE §5.1.7 | Ship formal EBNF grammar (SynCode) | W (proposed) | `docs/` / `Architecture/` | Publish EBNF grammar | Grammar file exists and validates | Grammar conformance tests | Blocker: owner decision |
| F-EASE-08 | SPX-EASE §5.1.11 | Evaluate with SUS + comprehension benchmarks | W (proposed) | Test suite (future) | Define benchmark suite across skill/model tiers | Benchmark harness + baseline numbers | Benchmark tests | Blocker: owner decision |
| F-EASE-09 | SPX-EASE §5.3 | Anti-patterns: no new expression language, no whitespace-significant control flow, no terse operators | A (as constraints) | CORE-07/11/12 | Document as design constraints | Constraints referenced in blueprints | Review checklist | Evidence: SPX-EASE §5.3 |

---

## 3. Component Template Formats (`COMPONENT-TEMPLATE-FORMATS-RESEARCH.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-COMP-01 | COMP §5.1 | HTML-superset surface (Astro/Svelte/Vue/Marko consensus) | W (proposed, extends F-EASE-04) | CORE-07 lexer | Templates valid-ish HTML with PHP expressions | HTML parses; expressions embedded | Lexer tests | Blocker: owner decision |
| F-COMP-02 | COMP §5.6 | Dual surface (Marko two-syntax, LLMON two-form) | D (dup of F-EASE-06) | — | See F-EASE-06 | — | — | Duplicate; do not implement separately |
| F-COMP-03 | COMP §5.8 | Safe-by-default escaping | D (dup of F-EASE-03) | — | See F-EASE-03 | — | — | Duplicate |
| F-COMP-04 | COMP §5.10 | Built-in tooling (highlighting, autocomplete, live preview) | W (proposed) | `packages/` (future tooling) | Plan tooling as part of accessibility | Tooling roadmap item | N/A | Blocker: owner decision |
| F-COMP-05 | COMP §5.11 | Sandboxed subset (Liquid) for untrusted authors | W (proposed) | CORE-12 codegen (future) | Decide if sandbox ships in v1 | Decision recorded | Sandbox tests | Blocker: owner decision (F-EASE lists same) |
| F-COMP-06 | COMP §1 | Single-file components default with multi-file fallback | W (proposed) | CORE-07/11 | Colocate template + `~setup`/PHP + styles; support split | SFC parses; `src` import works | Parser tests | Blocker: owner decision |

---

## 4. Framework Structure, DI, DX, a11y (`FRAMEWORK-STRUCTURE-DI-DX-ACCESSIBILITY-RESEARCH.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-FW-01 | FW §11.1.1 | Publish within-ring file convention (feature-sliced) | W (proposed) | `Architecture/`, `packages/Core`, `packages/Hub`, `packages/Spoke` | Document convention before LMS increments | Convention doc merged | Architecture tests | Blocker: owner decision |
| F-FW-02 | FW §11.1.2 | Hand-wired composition root per ring boundary | W (proposed, aligns with LMS gap) | `packages/*/src/CompositionRoot.php` (future) | Implement hand-wired composition root | No heavy DI container; ports wired | Integration tests | Blocker: LMS build order step 7 |
| F-FW-03 | FW §11.1.3 | Ports for persistence (complete missing Progress, Enrollment) | A (aligns with LMS plan) | `packages/Hub` ports, `packages/Spoke` repos | Complete `ProgressRepositoryInterface`, `MySQLEnrollmentRepository` | Interfaces + MySQL impls exist | Unit + MySQL integration | In LMS build order steps 4-5 |
| F-FW-04 | FW §11.1.4 | Strong package boundaries with `exports` | W (proposed) | `packages/*/composer.json` | Add `exports`/autoload boundaries | Package boundaries enforced | Boundary tests | Blocker: owner decision |
| F-FW-05 | FW §11.1.5 | Onboarding path with SUS ≥68 | W (proposed) | `README.md`, docs | Add quickstart + samples + SUS measure | SUS ≥68 in founder review | Usability eval | Blocker: founder owns UI |
| F-FW-06 | FW §11.1.6 | Accessibility enforcement (lint + CI + primitives) | W (proposed, frontend founder-owned) | CI workflows (future) | Add a11y lint when frontend work begins | Lint in CI | CI check | Blocker: frontend not in backend handoff |
| F-FW-07 | FW §11.2.1 | Component-scoped folders with co-located tests | W (proposed, SuperPHP) | `packages/SuperPHP` (future) | Adopt component-scoped layout | Layout documented | N/A | Blocker: owner decision |
| F-FW-08 | FW §11.2.4 | Formal grammar EBNF for tooling | D (dup of F-EASE-07) | — | See F-EASE-07 | — | — | Duplicate |

---

## 5. SPX Compiler Construction (`SPX-COMPILER-CONSTRUCTION-RESEARCH.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-COMPILER-01 | SPX-COMPILER §3-4 | Recursive descent parser with CFG formalism | W (proposed) | CORE-11 | Implement recursive descent parser | Parser handles documented grammar | Parser unit tests | Blocker: ADR-005 pipeline; owner decision |
| F-COMPILER-02 | SPX-COMPILER §3.4 | Regex → NFA → DFA lexical conversion | W (proposed) | CORE-07 | Implement lexer with deterministic automaton | Lexer produces token stream | Lexer tests | Blocker: owner decision |
| F-COMPILER-03 | SPX-COMPILER §6 | Front-end/back-end separation in codegen | W (proposed) | CORE-12 | Separate AST→IR and IR→PHP | Two-pass codegen | Codegen tests | Blocker: owner decision |
| F-COMPILER-04 | SPX-COMPILER §7 | Error handling with recovery (panic mode / sync tokens) | W (proposed) | CORE-11 | Implement error recovery in parser | Parser reports multiple errors | Error tests | Blocker: owner decision |
| F-COMPILER-05 | SPX-COMPILER §8 | Symbol table design | W (proposed) | CORE-11/12 | Implement symbol table for templates | Symbols resolve correctly | Symbol tests | Blocker: owner decision |
| F-COMPILER-06 | SPX-COMPILER §13.9 | Study Twig/Blade/Smarty/Latte compiler internals | W (proposed) | Research input only | No code change; inform design | Design doc references internals | N/A | Research input |

---

## 6. Reconciliation Matrix / Agenda (`RESEARCH-RECONCILIATION-MATRIX.md`, `RESEARCH-AGENDA.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-REC-01 | REC §2 | Connecting-theme wording unresolved (3 variants) | W (proposed reconciliation) | `RESEARCH-AGENDA.md` Track 5 | Owner selects variant A/B/C | Single wording in agenda | N/A | Blocker: owner decision (R1) |
| F-REC-02 | REC §3 | SuperPHP placement divergence (separate section vs numbered track) | W | `RESEARCH-AGENDA.md` | Owner decides placement | Agenda updated | N/A | Blocker: owner decision (R2) |
| F-REC-03 | REC §4 | Half Exceptions 4 formulations; confirm 3 & 4 with owner | W | `RESEARCH-AGENDA.md` Track 3 | Owner confirms formulations | Agenda updated | N/A | Blocker: owner decision (R5) |
| F-REC-04 | REC §8 | Agenda rendition reconciliation table | A (done) | `RESEARCH-RECONCILIATION-MATRIX.md` | Reconciliation table complete | Table present | N/A | Verified: matrix §1, §8 |
| F-REC-05 | AGENDA §Track1-4 | Product research tracks (platform integrity, YouTube model, Half Exceptions, Cancel-Anytime) | W (research only) | N/A (no code) | No implementation; research deliverables only | Research reports complete | N/A | Research gate: owner approval pending |

---

## 7. Vue SFC Research (`VUE-SFC-COMPONENT-FORMAT-RESEARCH.md`)

| Finding ID | Source Doc + ID | Problem / Capability | Decision | Target Package / Files | Concrete Task | Acceptance Criteria | Tests | Status / Blockers / Evidence |
|------------|------------------|---------------------|----------|------------------------|---------------|---------------------|-------|------------------------------|
| F-VUE-01 | VUE §3.1 | Sectioned SFC proven pattern (template/script/style) | W (proposed) | CORE-07/11 | Adopt sectioned SFC blocks | Parser handles blocks | Parser tests | Blocker: owner decision (dup of F-COMP-06) |
| F-VUE-02 | VUE §9 | DSDS audience tagging emerging standard | W (proposed) | Component document schema (future) | Consider audience tags in doc | Schema includes audience field | Schema tests | Blocker: owner decision |

---

## Traceability Rules

1. Every finding maps to exactly one source doc + section. Duplicates are marked D and cross-reference the canonical finding; do not implement both.
2. Conflicting recommendations (e.g., F-EASE-02 single canonical form vs F-EASE-06 dual surface) are both listed; implementation blocked until owner resolves the tension.
3. No finding with decision W may be implemented without owner authorization. Findings with A that are constraints only (F-EASE-09) must be referenced in blueprints but do not require code.
4. LMS-backend findings (F-FW-03) are the only findings with an accepted link to an in-progress build order; they must not be duplicated by PR #364's published-course discovery work.

## Summary Counts

- Total findings: 46
- Accepted (A): 6 (F-NAM-01/02/03, F-EASE-09, F-FW-03, F-REC-04)
- Rejected (R): 0
- Superseded (S): 0
- Duplicate (D): 4 (F-COMP-02/03, F-FW-08, F-VUE-01)
- Awaiting (W): 36

**End of register.** This document is a research deliverable. It does not authorize implementation, ADR amendment, or governance change. All W decisions require explicit owner approval.