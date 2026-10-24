# NeoSPX Implementation Status

**Last Updated:** 2026-10-24  
**Specification:** NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (Revised 3)  
**SHA-256:** 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a  
**Branch:** main (up to date with origin/main at 8858e165a76db7b14650298ca17b2265da1e4115)  
**Working Tree:** Clean (tracked files unmodified; untracked files present)

---

## Implementation Status by Milestone

| Milestone | Status | Blocking | Evidence |
|-----------|--------|----------|----------|
| **M0: Handoff Infrastructure** | ✅ Complete | — | Canonical docs exist; checkpoint exists |
| M1: Grammar Contract & Conformance Plan | ✅ Complete | Spec Revised 3 approved | Spec Revised 3; test matrix defined |
| M2: Lexer (CORE-07) | ⏳ Pending | ADR-023 approval | Spec Revised 3; test matrix defined |
| M3: Parser (CORE-11) | ⏳ Planned | M2 complete; ADR-023 | Spec sections 4.1-4.5 |
| M4: Compiler (CORE-12) | Planned | M3 complete; ADR-023 | Spec sections 4.5-4.5 |
| M5: Integration | Planned | M4 complete; ADR-022 | Spec sections 5-7 |
| M6: LMS HTTP/API | Planned | ADR-022; M5 | ADR-022 (Founder) |

---

## Approved Decisions (Locked In)

| ID | Decision | Approved By | Date | Affects |
|------|----------|-------------|------|---------|
| OD-01 | PHP expressions verbatim | Tech Lead | 2026-10-20 | M1-M5 |
| OD-02 | Verbose keywords, single canonical form | Tech Lead | 2026-10-20 | M1-M5 |
| OD-03 | XML-style component tags | Tech Lead | M1-M5 | |
| OD-04 | HTML-superset | Tech Lead | M1-M5 | |
| OD-05 | Full 12+ directive set | Tech Lead | M1-M5 | |
| OD-EXPR-BOUNDARY | APPROVED (2026-10-23) | Tech Lead | M2+ | Complex exprs must be parenthesized |

---

## Pending Decisions (Blocking)

| Decision ID | Description | Owner | Blocks | Options | Recommended |
|--------------|-------------|--------|--------|---------|-------------|
| ADR-023 | NeoSPX canonical identity | Tech Lead | Publication | Approve ADR-023 | **Approve** |
| ADR-022 | Auth transport (LMS) | Founder | LMS HTTP | Session cookies (same-origin) | **Awaiting Founder** |
| F-EASE-03 | Context-aware escaping | Tech Lead | M4 | Automatic (Latte-style) | **Auto** |
| F-EASE-06 | Dual surface syntax | Tech Lead | M3 | Single syntax v1 | **Single** |
| F-EASE-11 | Debug info in output | Tech Lead | M4 | Defer to v1.1 | **Defer** |
| F-NAM-06 | Component doc format | Tech Lead | M5 | JSON-first | **JSON-first** |
| F-VUE-02 | Dual authoring modes | Tech Lead | M3 | Defer to v2 | **Defer** |
| F-VUE-04 | Build-free vs compiled | Tech Lead | M4 | Compile (MVP) | **Compile** |
| F-VUE-05 | Scoped styles vs SCSS | Tech Lead | M5 | Scoped styles | **Scoped** |
| F-COMPILER-04 | Fault-tolerant parsing | Tech Lead | M3 | Implement (panic mode) | **Implement** |
| F-COMPILER-12 | Early cutoff | Tech Lead | M4 | Implement (hash-based) | **Implement** |
| F-FW-01 | Feature-sliced convention | Tech Lead | LMS | Implement (hand-wired) | **Implement** |
| F-FW-05 | Onboarding SUS ≥68 | Founder | LMS | Defer | **Defer** |
| F-REC-01 | Connecting theme | Owner | Research | Owner selects | **Owner decides** |
| F-REC-02 | SuperPHP placement | Owner | Research | Separate section | **Separate** |
| F-REC-03 | Half Exceptions | Owner | Research | Owner confirms | **Owner decides** |
| F-COMPILER-04 | Fault-tolerant parsing | Tech Lead | M3 | Implement (panic mode) | **Implement** |
| F-COMPILER-12 | Early cutoff | Tech Lead | M4 | Implement (hash-based) | **Implement** |
| F-FW-01 | Feature-sliced convention | Tech Lead | LMS | Implement (hand-wired) | **Implement** |
| F-FW-05 | Onboarding SUS ≥68 | Founder | LMS | Defer | **Defer** |
| F-REC-01 | Connecting theme | Owner | Research | Owner selects | **Owner decides** |
| F-REC-02 | SuperPHP placement | Owner | Research | Separate section | **Separate** |
| F-REC-03 | Half Exceptions | Owner | Research | Owner confirms | **Owner confirms** |

---

## Prototype Test Evidence (Local Only)

| Artifact | Path | SHA-256 | Tests | Assertions | Status |
|----------|-------|---------|-------|------------|--------|
| AttributeExpressionScanner.php | /tmp/neospx-lexer/src/ | c0162107... | 15 | 31 | ✅ Pass |
| LexerBoundaryTest.php | 15 tests, 31 assertions | ✅ PASS |
| FooTraceTest.php | 12 tests, 18 assertions | ✅ Pass |
| **Total** | **27 tests, 49 assertions** | **ALL PASS** |

*Note: Prototype tests in `/tmp/neospx-lexer/` — NOT repository tests. Prototype only.*

---

## Current Blockers (Ranked)

| Priority | Blocker | Type | Required For | Owner | Status |
|----------|---------|------|------------|-------|--------|
| 1 | ADR-023 (NeoSPX identity) | Governance | Publication | Tech Lead | **Pending** |
| 2 | ADR-022 (Auth transport) | Founder | LMS HTTP | **Pending** |
| 3 | MySQL integration-test contract | Founder | CI | Pending | Founder |
| 4 | Mechanical grammar validation | Milestone 1 | No tool available | **Blocked** |
| 5 | ADR-022 (Auth transport) | LMS HTTP | Founder | Pending | Founder |
| 6 | MySQL integration-test contract | CI | Pending | Founder |
| 7 | Sandbox enforcement (F-COMPILER-05) | Milestone 3+ | Unresolved OD | **Unresolved** |
| 8 | @php/@endphp §5.10 doc gap | Documentation | Proposed, not applied | |
| 9 | Mechanical grammar validation | Milestone 1 | No tool available | **Blocked** |

---

## Prototype Test Evidence (Local Only)

| Artifact | Path | SHA-256 | Tests | Assertions | Status |
|----------|------|---------|-------|------------|--------|
| AttributeExpressionScanner.php | /tmp/neospx-lexer/src/ | c0162107... | — | — | Source |
| LexerBoundaryTest.php | /tmp/neospx-lexer/tests/ | 56cc4897... | 15 tests, 31 assertions | ✅ PASS |
| FooTraceTest.php | 12 tests, 18 assertions | ✅ PASS |
| **Total** | **27 tests, 49 assertions** | **ALL PASS** |

*Note: Prototype tests in `/tmp/neospx-lexer/` — NOT repository tests. Do not claim as repo conformance.*

---

## Outstanding Decisions Required

| # | Decision | Owner | Options | Recommended | Blocks |
|-----|----------|-------|---------|-------------|--------|
| 1 | ADR-023 approval | Tech Lead | Approve / Revise | **Approve** | Publication |
| 2 | Sandbox allowlist | Tech Lead | Minimal / Extended / Config | Minimal pure fn | Sandbox impl |
| 3 | Sandbox config format | Tech Lead | Hardcoded / YAML / PHP | YAML | Sandbox impl |
| 3 | Enforcement layer | Tech Lead | Compile-time / Runtime / Both | Compile-time + runtime | Sandbox impl |
| 4 | ADR-023 approval | Tech Lead | Approve / Revise | **Approve** | Publication |
| 5 | ADR-022 (Auth) | Founder | Session / JWT / Both | Session (same-origin) | LMS HTTP |
| 6 | §5.10 @php/@endphp doc fix | Tech Lead | Apply / Skip | Apply (low risk) | Doc clarity |

---

## Next Required Actions (Priority Order)

1. **Tech Lead: Approve ADR-023** → Unblocks publication & Milestone 2
2. **Tech Lead: Decide sandbox defaults** → Unblocks sandbox implementation
3. **Tech Lead: Approve §5.10 @php/@endphp doc fix** → Low risk, high clarity
4. **Tech Lead: Approve ADR-023** → Unblocks publication
5. **Founder: Decide ADR-022 (Auth transport)** → Unblocks LMS HTTP layer
6. **Founder: Decide MySQL test contract** → Unblocks CI
4. **Team: Select grammar validation tool** → ANTLR / Bison / custom
5. **Tech Lead: Approve ADR-023** → Unblocks publication
6. **Team: Implement lexer (Milestone 2)** → After ADR-023 approval

---

*Last Updated: 2026-10-24 | Specification: NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (Revised 3) | SHA-256: 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a*
