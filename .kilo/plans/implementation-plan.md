# NeoSPX Implementation Plan and Milestone Dependencies

**Status:** Active — Local only, not committed  
**Last Updated:** 2026-10-24  
**Specification:** `NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md` (v1 Draft, Revised 3, SHA-256: `6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a`)  
**Baseline Commit:** `8858e165a76db7b14650298ca17b2265da1e4115` (origin/main)

---

## Milestone Overview

| Milestone | ID | Name | Status | Dependencies | Exit Criteria |
|-----------|----|------|--------|--------------|---------------|
| M0 | Handoff Infrastructure | ✅ Complete | — | Canonical docs exist; checkpoint exists |
| M1 | M1: Grammar Contract & Conformance Plan | ✅ Complete | Spec Revised 3 approved | Test matrix defined; ambiguities documented |
| M2 | Lexer (CORE-07) | ⏳ Planned | M1 complete; ADR-023 approved | Lexer produces correct tokens for all terminals; error reporting; ≥100K tokens/sec |
| M3 | Parser (CORE-11) | Planned | M2 complete; ADR-023 approved | Recursive descent parser; Pratt parsing for expressions; AST with source locations; ≥90% unit test coverage |
| M4 | Compiler (CORE-12) | Planned | M3 complete | AST→native PHP; flat arrays + s-data; checksum caching; OPcache-friendly; ≥90% coverage |
| M5 | Integration & Composition | Planned | M4 complete | End-to-end template→PHP→render; Reactive Bridge verified; performance benchmarks met |
| M6 | LMS HTTP/API Layer | Planned | ADR-022 approved; M5 complete | Auth middleware; real-MySQL tests; E2E learner/admin flow |

---

## Milestone Dependencies

```
M0 (Handoff) → M1 (Grammar Contract) → M2 (Lexer) → M3 (Parser) → M4 (Compiler) → M5 (Integration)
                                                      ↘
                           ADR-023 (NeoSPX Identity) ──→ Publication
                           ADR-022 (Auth) ──────────────→ LMS HTTP Layer
```

**Critical Path:** M0 → M1 → M2 → M3 → M4 → M5  
**Parallel Track:** LMS HTTP Layer requires ADR-022 (Auth) + M5 complete

---

## Decision Register (Stable IDs)

| Decision ID | Title | Status | Owner | Affected Milestones | Resolution |
|-------------|-------|--------|-------|---------------------|------------|
| OD-01 | PHP expressions verbatim | APPROVED | Tech Lead | M1-M5 | Latte strategy |
| OD-02 | Verbose keywords, single canonical form | APPROVED | Tech Lead | M1-M5 | Anka strategy |
| OD-03 | XML-style component tags | APPROVED | Tech Lead | M1-M5 | Explicit XML tags |
| OD-04 | HTML-superset | APPROVED | Tech Lead | M1-M5 | HTML-superset |
| OD-05 | Full 12+ directive set | APPROVED | Tech Lead | M1-M5 | Full directive set |
| OD-EXPR-BOUNDARY | Attribute-expression boundary | APPROVED (2026-10-23) | Tech Lead | M2 | Parentheses for complex expressions |
| F-EASE-03 | Context-aware escaping | Pending | Tech Lead | M4 | |
| F-EASE-06 | Dual surface syntax | Pending | Tech Lead | M3 | |
| F-EASE-11 | Debug info in output | Pending | Tech Lead | M4 | |
| F-NAM-06 | Component doc format | Pending | Tech Lead | M5 | |
| F-VUE-02 | Dual authoring modes | Pending | Tech Lead | M3 | |
| F-VUE-03 | DSDS audience tagging | Pending | Tech Lead | M5 | |
| F-VUE-04 | Build-free vs compiled | Pending | Tech Lead | M4 | |
| F-VUE-05 | Scoped styles vs SCSS | Pending | Tech Lead | M5 | |
| F-COMPILER-04 | Fault-tolerant parsing | Pending | Tech Lead | M3 | |
| F-COMPILER-12 | Early cutoff | Pending | Tech Lead | M4 | |
| F-FW-01 | Feature-sliced convention | Pending | Tech Lead | LMS |
| F-FW-05 | Onboarding SUS ≥68 | Pending | Founder | LMS handoff |
| F-REC-01 | Connecting theme | Pending | Owner | Research | |
| F-REC-02 | SuperPHP placement | Pending | Owner | Research |
| F-REC-03 | Half Exceptions | Pending | Owner | Research |

---

## Conformance Test Matrix (M1 Deliverable)

### Test Categories & IDs

| Test ID | Category | Grammar Section | Description | Expected Outcome | Repo Location |
|---------|----------|-----------------|-------------|------------------|---------------|
| T-LEX-001 | Lexer | 3.1 | Tokenizes simple component `<s:ui:button />` | Tokens: T_COMPONENT_OPEN, T_ATTR_NAME, T_ATTR_EQUALS, T_STRING, T_SELF_CLOSE | tests/Unit/Lexer/ComponentTokenizationTest.php |
| T-LEX-002 | Lexer | 3.1 | Tokenizes component with attributes | Correct T_ATTR_NAME, T_ATTR_EQUALS, T_STRING | tests/Unit/Lexer/ComponentTokenizationTest.php |
| T-LEX-003 | Lexer | 3.2 | Tokenizes `@if` directive with PhpExpression | T_DIRECTIVE_START, T_PHP_EXPR, T_DIRECTIVE_END | tests/Unit/Lexer/DirectiveTokenizationTest.php |
| T-LEX-004 | Lexer | 3.2 | Tokenizes `~setup` block | T_SETUP_START, T_PHP_CODE, T_SETUP_END | tests/Unit/Lexer/SetupBlockTest.php |
| T-LEX-005 | Lexer | 3.2 | Tokenizes `@php` block | T_DIRECTIVE_START(@php), T_PHP_CODE, T_END_PHP | tests/Unit/Lexer/PhpDirectiveTest.php |
| T-LEX-006 | Lexer | 3.2 | Handles `~setup` in string | `~setup` inside string not recognized as closer | tests/Unit/Lexer/StatePhpClosingTest.php |
| T-LEX-007 | Lexer | 3.2 | Handles `@endphp` in comment | `@endphp` in comment not recognized | tests/Unit/Lexer/StatePhpClosingTest.php |
| T-LEX-008 | Lexer | 5.8 | Expression boundary: arrow operator `->` | `label=$user->name >` → expression `$user->name`, terminator `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-009 | Lexer | 5.9 | Attribute expression with `>=` | `cmp=$a >= $b >` parses `$a >= $b` | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-010 | Lexer | 5.9 | Attribute expression with `=>` in array | `arr=['key' => $value] >` parses `['key' => $value]` | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-010 | Lexer | 5.9 | Attribute with ternary | `value=$a ? $b : $c >` parses ternary | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-011 | Lexer | 5.9 | Expression with nested delimiters | `fn=$obj->method($a, $b) >` | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-012 | Lexer | 5.9 | String containing `>` | `str="foo > bar" >` does not terminate | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-013 | Lexer | 5.9 | Comment containing `>` | `x=$a /* > */ + $b >` not terminated at `>` in comment | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-014 | Lexer | 3.2 | Multiple adjacent attributes | `label=$user->name age=$user->age >` | tests/Unit/Lexer/ExpressionBoundaryTest.php |
| T-LEX-015 | Lexer | 5.10 | `~setup` closing in string | `~setup` in string not closing | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-016 | Lexer | 3.2 | Unterminated `~setup` | Lexical error reported | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-017 | Lexer | 5.10 | `@endphp` in comment | Not recognized as closing | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-018 | Lexer | 5.10 | `@endphp` in string | Not recognized as closing | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-019 | Lexer | 5.10 | Unterminated `~setup` | Lexical error reported | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-020 | Lexer | 5.10 | Heredoc containing closing marker | `~setup` in heredoc ignored | tests/Unit/Lexer/ClosingMarkerTest.php |
| T-LEX-021 | Lexer | 5.10 | `@endphp` in comment | Not recognized as closing | tests/Unit/Lexer/ClosingMarkerTest.php |

### Parser Tests (CORE-11)

| Test ID | Category | Section | Description | Expected Outcome |
|---------|----------|---------|-------------|------------------|
| T-PAR-001 | Parser | 4.1 | Parses valid component with attributes | ComponentNode with correct attributes |
| T-PAR-002 | Parser | 4.4.1 | Parses `@if`/`@elseif`/`@else`/`@endif` | Correct nesting in AST |
| T-PAR-003 | Parser | 4.4.2 | Parses `@foreach` with key/value | ForeachDirective with key/value |
| T-PAR-004 | Parser | 4.4.2 | Parses `@for` with 3-section header | PhpForHeader with 3 expressions |
| T-PAR-005 | Parser | 4.4.3 | Parses `@switch` with fall-through | Correct AST with fall-through |
| T-PAR-006 | Parser | 4.4.5 | Parses `@persist` directive | PersistDirective with variable |
| T-PAR-007 | Parser | 4.4.6 | Parses `@global` directive | GlobalDirective with variable |
| T-PAR-008 | Parser | 4.4.7 | Parses `@include` directive | IncludeDirective with path |
| T-PAR-009 | Parser | 4.4.8 | Parses `@php` block | PhpDirective with PhpBlock |
| T-PAR-010 | Parser | 4.5 | Parses `~setup` block | SetupNode with PhpBlock |
| T-PAR-011 | Parser | 5.2 | Error recovery on missing terminator | Recovers at synchronizing token |

### Compiler Tests (CORE-12)

| Test ID | Description | Expected Outcome |
|---------|-------------|------------------|
| T-CMP-001 | Compiles simple component to PHP | Valid PHP output |
| T-CMP-002 | Compiles component with directives | Directives compiled to PHP control structures |
| T-CMP-003 | Compiler | `@persist` serializes to s-data | s-data JSON attribute in output |
| T-CMP-004 | Compiler | `@global` declares template scope variable | Variable available in scope |
| T-CMP-005 | Compiler | `@include` resolves and inlines | Included template inlined |
| T-CMP-006 | Compiler | `@persist` serializes to s-data JSON | s-data attribute in output |
| T-CMP-007 | Compiler | `@global` declares variable | Variable available in scope |
| T-CMP-008 | Compiler | Caching by source checksum | Cache hit on unchanged source |

---

## Integration Tests

| Test ID | Description | Expected |
|---------|-------------|----------|
| T-INT-001 | Template → compiled PHP → rendered output | End-to-end render matches expected HTML |
| T-INT-002 | Reactive Bridge: s-data injection | s-data attributes in output |
| T-INT-003 | Learner enrollment flow | Enroll → progress → complete |
| T-INT-004 | Admin create/publish course | Course discoverable by learner |
| T-INT-005 | Reactive Bridge hydration | s-data hydrated on client |

---

## Performance Benchmarks (Milestone Acceptance)

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Lexer throughput | ≥100K tokens/sec | Benchmark on standard template |
| Parser throughput | ≥50K nodes/sec | Benchmark on standard template |
| Compiler throughput | ≥20K templates/sec (cached) | Benchmark on standard template |
| Memory usage | < 50MB for 10K tokens | Memory profiler |
| Error recovery | ≥1 error per malformed template | Test suite |

---

## Milestone Dependencies & Blocking Decisions

| Milestone | Blocking Decisions | Status |
|---------|-------------------|--------|
| M2 (Lexer) | ADR-023 approved; OD-EXPR-BOUNDARY approved | ⏳ ADR-023 pending |
| M3 (Parser) | M2 complete; ADR-023 approved | Blocked on M2 |
| M4 (Compiler) | M3 complete; ADR-023 approved | Blocked on M3 |
| M5 (Integration) | M4 complete; ADR-022 (auth) | Blocked on M4 + ADR-022 |
| M6 (LMS HTTP) | ADR-022 (auth); M5 complete | Blocked on ADR-022 |

---

## Blocking Decisions Required

| Decision ID | Description | Options | Recommended | Blocker |
|--------------|-------------|---------|------------|---------|
| ADR-023 | NeoSPX canonical identity | Approve ADR-023 / Amend ADR-005 | Approve ADR-023 | Tech Lead |
| ADR-022 | Auth transport (LMS) | Session cookies / JWT / Both | Session cookies (same-origin) | Founder |
| ADR-022 | MySQL integration test | Configure CI MySQL / Mock | CI MySQL service | Founder |
| F-EASE-03 | Context-aware escaping | Automatic / Explicit opt-in | Automatic (Latte-style) | Tech Lead |
| F-EASE-06 | Dual surface (single vs dual syntax) | Single syntax v1 | Tech Lead |
| F-EASE-11 | Debug info in output | Defer to v1.1 / Include in MVP | Defer to v1.1 |
| F-NAM-06 | Component doc format | JSON-first / JSON+YAML | JSON-first | Tech Lead |
| F-VUE-02 | Dual authoring modes | Defer / Implement | Defer to v2 | Tech Lead |
| F-VUE-04 | Build-free vs compiled | Defer / Compile | Compile (MVP) | Tech Lead |
| F-VUE-05 | Scoped styles vs SCSS | Scoped styles / SCSS | Scoped styles | Tech Lead |
| F-COMPILER-04 | Fault-tolerant parsing | Implement / Defer | Implement (Milestone 3) | Tech Lead |
| F-COMPILER-12 | Early cutoff | Implement / Defer | Implement | Tech Lead |
| F-FW-01 | Feature-sliced convention | Implement / Defer | Implement (hand-wired root) | Tech Lead |
| F-FW-05 | Onboarding SUS ≥68 | Implement / Defer | Implement (doc + samples) | Founder |
| F-COMPILER-04 | Fault-tolerant parsing | Implement / Defer | Implement (panic mode + placeholders) | Tech Lead |
| F-COMPILER-12 | Early cutoff | Implement / Defer | Implement (hash-based) | Tech Lead |
| F-FW-01 | Feature-sliced convention | Defer / Implement | Implement (hand-wired) | Tech Lead |
| F-FW-05 | Onboarding SUS ≥68 | Defer / Implement | Defer (founder owns UI) | Founder |
| F-REC-01 | Connecting theme | Owner selects variant | Documented | Owner |
| F-REC-02 | SuperPHP placement | Separate section / numbered | Separate section | Owner |
| F-REC-03 | Half Exceptions | Confirm formulations | Documented | Owner |

---

## Conformance Test Matrix Status

| Category | Total Tests | Implemented | Passing | Location |
|----------|-------------|-------------|--------|----------|
| Lexer (M2) | 19 | 19 (prototype) | 0 (repo) | `/tmp/neospx-lexer/` |
| Parser (CORE-11) | 11 | 0 | 0 | N/A |
| Compiler (CORE-12) | 8 | 0 | N/A |
| Integration | 5 | 0 | N/A |
| Performance | 5 | 0 | N/A |

**Note:** Prototype tests exist in `/tmp/neospx-lexer/` (27 tests, 49 assertions, all passing). These are **prototype only** — not in repository test suite.

---

## Handoff Checkpoint: M0 Complete / M1 Complete

| Checkpoint | Status | Evidence |
|------------|--------|----------|
| M0: Handoff Infrastructure | ✅ Complete | `.kilo/plans/implementation-plan.md` (this file) |
| M1: Grammar Contract & Conformance Plan | ✅ Complete | Spec Revised 3; test matrix defined |
| M2: Lexer Implementation | ⏳ Pending | Blocked on ADR-023 |

---

## Next Action Required

**Decision Required from Tech Lead:**
1. **Approve ADR-023** (NeoSPX canonical identity) — unblocks publication and Milestone 2
2. **Decide on sandbox defaults** — Minimal pure-function allowlist + YAML config + compile-time + runtime
3. **Approve §5.10 @php/@endphp doc fix** — Low risk, high clarity

**No publication, commits, or merges without explicit authorization.**

---

## Current Git State

```
Branch: main (up to date with origin/main)
HEAD: 8858e165a76db7b14650298ca17b2265da1e4115
Working tree: Modified tracked files (.kilo/plans/..., RESEARCH-INDEX.md)
Untracked: NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md, RESEARCH-TO-IMPLEMENTATION-REGISTER.md, etc.
Stash: stash@{0}: On main: SPX Stash (modifies 3 research files)
```

---

*Last Updated: 2026-10-24 | Author: Kilo | Specification: NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (SHA-256: 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a)*
