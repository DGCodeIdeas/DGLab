# NeoSPX Conformance Test Matrix

**Specification:** NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (Revised 3)  
**SHA-256:** 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a  
**Status:** Proposal — awaiting Tech Lead approval for ADR-023  

---

## Conformance Test Matrix

### Legend
- **Test ID**: Stable identifier (immutable)
- **Category**: Test category
- **Grammar Section**: Specification section
- **Description**: Test scenario
- **Expected Outcome**: Tokens, parse result, or error
- **Repository Test Location**: Path in repo test suite
- **Status**: Implemented / Proposed / Missing
- **Prototype Coverage**: Covered by prototype tests?

---

## Lexer Tests (CORE-07 / Milestone 2)

| Test ID | Category | Grammar Section | Description | Expected Outcome | Repo Location | Status | Proto Coverage |
|---------|----------|-----------------|-------------|------------------|---------------|--------|----------------|
| T-LEX-001 | Lexer | 3.1 | Tokenizes `<s:ui:button />` | T_COMPONENT_OPEN, T_ATTR_NAME, T_ATTR_EQUALS, T_STRING, T_SELF_CLOSE | tests/Unit/Lexer/ComponentTokenizationTest.php | Proposed | Prototype ✅ |
| T-LEX-002 | Lexer | 3.1 | Component with attributes | Correct T_ATTR_NAME, T_ATTR_EQUALS, T_STRING | tests/Unit/Lexer/ComponentTokenizationTest.php | Proposed | Prototype ✅ |
| T-LEX-003 | Lexer | 3.2 | `@if` with PhpExpression | T_DIRECTIVE_START, T_PHP_EXPR, T_DIRECTIVE_END | tests/Unit/Lexer/DirectiveTokenizationTest.php | Proposed | Prototype ✅ |
| T-LEX-004 | Lexer | 3.2 | `~setup` block tokenization | T_SETUP_START, T_PHP_CODE, T_SETUP_END | tests/Unit/Lexer/SetupBlockTest.php | Proposed | Prototype ✅ |
| T-LEX-005 | Lexer | 3.2 | `@php` block | T_DIRECTIVE_START(@php), T_PHP_CODE, T_END_PHP | tests/Unit/Lexer/PhpDirectiveTest.php | Proposed | Prototype ✅ |
| T-LEX-006 | Lexer | 3.2 | `~setup` in string | Not recognized as closer | tests/Unit/Lexer/StatePhpClosingTest.php | Proposed | Prototype ✅ |
| T-LEX-007 | Lexer | 3.2 | `@endphp` in comment | Not recognized | tests/Unit/Lexer/StatePhpClosingTest.php | Proposed | Prototype ✅ |
| T-LEX-008 | Lexer | 5.8 | `label=$user->name >` | `$user->name`, terminator `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-009 | Lexer | 5.9 | `>=` operator | `cmp=$a >= $b >` → `$a >= $b`, terminator `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-010 | Lexer | 5.9 | `=>` in array literal | `arr=['key' => $value] >` → `['key' => $value]`, `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-011 | Lexer | 5.9 | Ternary operator | `value=$a ? $b : $c >` → `$a ? $b : $c`, `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-012 | Lexer | 5.9 | Nested delimiters | `fn=$obj->method($a, $b) >` → `$obj->method($a, $b)`, `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-013 | Lexer | 5.9 | String containing `>` | `str="foo > bar" >` → `"foo > bar"`, `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-014 | Lexer | 5.9 | Comment containing `>` | `x=$a /* > */ + $b >` → `$a /* > */ + $b`, `>` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-015 | Lexer | 5.9 | Multiple adjacent attrs | `label=$user->name age=$user->age >` → `$user->name`, `next-attribute` | tests/Unit/Lexer/ExpressionBoundaryTest.php | Proposed | Prototype ✅ |
| T-LEX-016 | Lexer | 5.10 | `~setup` closing in string | `~setup` in string not closing | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-017 | Lexer | 3.2 | Unterminated `~setup` | Lexical error | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-018 | Lexer | 5.10 | `@endphp` in comment | Not recognized | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-019 | Lexer | 5.10 | `@endphp` in string | Not recognized | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-020 | Lexer | 5.10 | Unterminated `~setup` | Lexical error | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-021 | Lexer | 5.10 | Heredoc with closing marker | `~setup` in heredoc ignored | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-022 | Lexer | 5.10 | `@endphp` in comment | Not recognized | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-023 | Lexer | 5.10 | `@endphp` in string | Not recognized | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-024 | Lexer | 5.10 | Unterminated `~setup` | Lexical error | tests/Unit/Lexer/ClosingMarkerTest.php | Proposed | Prototype ✅ |
| T-LEX-025 | Lexer | 5.8 | `{{ }}` output expression | T_OUTPUT_OPEN, T_PHP_EXPR, T_OUTPUT_CLOSE | tests/Unit/Lexer/OutputExpressionTest.php | Proposed | Prototype ❌ |
| T-LEX-026 | Lexer | 5.8 | `{!! !!}` raw output | T_RAW_OPEN, T_PHP_EXPR, T_RAW_CLOSE | tests/Unit/Lexer/OutputExpressionTest.php | Proposed | Prototype ❌ |
| T-LEX-027 | Lexer | 3.1 | HTML passthrough (`<div>`, comments, text) | T_HTML token | tests/Unit/Lexer/HtmlPassthroughTest.php | Proposed | Prototype ❌ |
| T-LEX-028 | Lexer | 3.1 | `T_HTML` accumulation | Single T_HTML for `<div class="foo">` | tests/Unit/Lexer/HtmlPassthroughTest.php | Proposed | Prototype ❌ |

---

## Parser Tests (CORE-11 / Milestone 3)

| Test ID | Category | Grammar Section | Description | Expected Outcome | Repo Location | Status |
|---------|----------|-----------------|-------------|------------------|---------------|--------|
| T-PAR-001 | Parser | 4.1 | Parses valid component with attributes | ComponentNode with correct attrs | tests/Unit/Parser/ComponentParserTest.php | Proposed |
| T-PAR-002 | Parser | 4.4.1 | `@if`/`@elseif`/`@else`/`@endif` | Correct nesting in AST | tests/Unit/Parser/IfDirectiveTest.php | Proposed |
| T-PAR-003 | Parser | 4.4.2 | `@foreach` with key/value | ForeachDirective with key/value | tests/Unit/Parser/ForeachDirectiveTest.php | Proposed |
| T-PAR-004 | Parser | 4.4.2 | `@for` with 3-section header | PhpForHeader with 3 expressions | tests/Unit/Parser/ForDirectiveTest.php | Proposed |
| T-PAR-005 | Parser | 4.4.3 | `@switch` with fall-through | Correct AST with fall-through | tests/Unit/Parser/SwitchDirectiveTest.php | Proposed |
| T-PAR-006 | Parser | 4.4.5 | `@persist` directive | PersistDirective with variable | tests/Unit/Parser/PersistDirectiveTest.php | Proposed |
| T-PAR-007 | Parser | 4.4.6 | `@global` directive | GlobalDirective with variable | tests/Unit/Parser/GlobalDirectiveTest.php | Proposed |
| T-PAR-008 | Parser | 4.4.7 | `@include` directive | IncludeDirective with path | tests/Unit/Parser/IncludeDirectiveTest.php | Proposed |
| T-PAR-009 | Parser | 4.4.8 | `@php` block | PhpDirective with PhpBlock | tests/Unit/Parser/PhpDirectiveTest.php | Proposed |
| T-PAR-010 | Parser | 4.5 | `~setup` block | SetupNode with PhpBlock | tests/Unit/Parser/SetupBlockTest.php | Proposed |
| T-PAR-011 | Parser | 5.2 | Error recovery on missing terminator | Recovers at synchronizing token | tests/Unit/Parser/ErrorRecoveryTest.php | Proposed |

---

## Compiler Tests (CORE-12 / Milestone 4)

| Test ID | Description | Expected Outcome | Repo Location | Status |
|---------|-------------|------------------|---------------|--------|
| T-CMP-001 | Compiles simple component to PHP | Valid PHP output | tests/Integration/Compiler/SimpleComponentTest.php | Proposed |
| T-CMP-002 | Compiles component with directives | Directives → PHP control | tests/Integration/Compiler/DirectiveCompilationTest.php | Proposed |
| T-CMP-003 | `@persist` serializes to s-data | s-data JSON attribute in output | tests/Integration/Compiler/PersistSerializationTest.php | Proposed |
| T-CMP-004 | `@global` declares template scope | Variable available in scope | tests/Integration/Compiler/GlobalVariableTest.php | Proposed |
| T-CMP-005 | `@include` resolves and inlines | Included template inlined | tests/Integration/Compiler/IncludeResolutionTest.php | Proposed |
| T-CMP-006 | `@php` executes and inserts output | PHP code output at directive position | tests/Integration/Compiler/PhpDirectiveTest.php | Proposed |
| T-CMP-007 | `~setup` executes before render | Variables available in template | tests/Integration/Compiler/SetupBlockTest.php | Proposed |
| T-CMP-008 | Caching by source checksum | Cache hit on unchanged source | tests/Integration/Compiler/CacheInvalidationTest.php | Proposed |

---

## Integration Tests (Milestone 5)

| Test ID | Description | Expected | Status |
|---------|-------------|----------|--------|
| T-INT-001 | Template → compiled PHP → rendered output | End-to-end render matches expected HTML | Proposed |
| T-INT-002 | Reactive Bridge: s-data injection | s-data attributes in output | Proposed |
| T-INT-003 | Learner enrollment flow | Enroll → progress → complete | Proposed |
| T-INT-004 | Admin create/publish course | Course discoverable by learner | Proposed |
| T-INT-005 | Reactive Bridge hydration | s-data hydrated on client | Proposed |

---

## Performance Benchmarks (Acceptance Criteria)

| Metric | Target | Measurement Method | Status |
|--------|--------|-------------------|--------|
| Lexer throughput | ≥100K tokens/sec | Benchmark on standard template | Planned |
| Parser throughput | ≥50K nodes/sec | Benchmark on standard template | Planned |
| Compiler throughput | ≥20K templates/sec (cached) | Benchmark on standard template | Planned |
| Test coverage | ≥90% line coverage | PHPUnit coverage report | Planned |
| Error recovery | ≥1 error per malformed template | Test suite | Planned |
| SUS (usability) | ≥68/100 across skill tiers | User study | Planned |
| Comprehension (AI) | ≥80% parse success across tiers | Benchmark suite | Planned |

---

## Prototype Test Coverage (Existing in `/tmp/neospx-lexer/`)

| Test Suite | Tests | Assertions | Status | Notes |
|-----------|-------|------------|--------|-------|
| LexerBoundaryTest.php | 15 | 31 | ✅ PASS | Prototype only |
| FooTraceTest.php | 12 | 18 | ✅ PASS | Prototype only |
| SandboxEnforcementTest.php | 7 | 3 (4 skipped) | ⚠️ 4 Skipped | Prototype only |
| **Total** | **34** | **52** | **ALL PASS** | Prototype only |

**Note:** These tests exist in `/tmp/neospx-lexer/` and are **prototype only** — NOT integrated into repository tests. They validate the algorithm design but do NOT prove repository correctness.

---

## 4. Missing Test Coverage (Gaps)

| Category | Missing | Priority |
|----------|---------|----------|
| Parser unit tests (CORE-11) | All 11 unit tests | High |
| Compiler unit tests (CORE-12) | All 8 compiler tests | High |
| Integration tests | 5 integration scenarios | High |
| Performance benchmarks | 7 benchmarks | Medium |
| Security tests (sandbox) | 7 tests (sandbox bypass, XSS, etc.) | Critical |
| Mechanical grammar validation | None | Blocker (no tool) |
| Parser/AST implementation | None | Blocker (Milestone 3) |
| Compiler implementation | None | Blocker (Milestone 4) |

---

## 4. Test Location Conventions

| Category | Repository Path | Naming Convention |
|----------|-----------------|-------------------|
| Lexer Unit | `packages/core/lexer/tests/Unit/Lexer/` | `*Test.php` |
| Lexer Integration | `packages/core/lexer/tests/Integration/` | `*Test.php` |
| Parser Unit | `packages/core/parser/tests/Unit/Parser/` | `*Test.php` |
| Parser Integration | `packages/core/parser/tests/Integration/` | `*Test.php` |
| Compiler Unit | `packages/core/compiler/tests/Unit/Compiler/` | `*Test.php` |
| Integration | `tests/Integration/` | `*Test.php` |
| Performance | `tests/Performance/` | `*Bench.php` |

---

## 5. Validation Taxonomy (Per §12.6)

| Tier | Description | Performed? | Evidence |
|------|-------------|------------|----------|
| Tier 1: Manual Structural Review | Verify grammar structure, nonterminal/terminal defs | ✅ Manual | §12.1 |
| Tier 2: Example-by-Example Inspection | Trace 26 examples token-by-token | ✅ 26/26 | §12.2 |
| Tier 3: Mechanical Grammar Validation | Automated grammar checks | ❌ No tool | §12.5 |
| Tier 4: Executable Parser/Conformance Tests | Execute parser against test suite | ❌ Not implemented | §12.6 (proposed) |

---

## 6. Validation Commands (Reproducible)

```bash
# 1. Structural completeness
grep -oE '\[[A-Za-z]+\]' NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md | sort -u

# 2. Extract all terminals from §3.2
grep -oE 'T_[A-Z_]+' NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md | sort -u

# 3. Verify every nonterminal in productions is defined in §4.0
# (Manual: compare §4.0 table against §4.1-§4.7 usage)

# 4. Verify every example against productions
# (Manual: trace each example's tokens to production rules)

# 5. Check for undefined references
# (Manual: search for nonterminal names not in §4.0 table)
```

---

## 7. Security Gate

### F-COMPILER-05: Cache Sanity Checks

| Decision | Options | Recommended | Status |
|----------|---------|-------------|--------|
| Allowlist scope | Minimal pure / Extended / Configurable | Minimal pure functions | Pending Tech Lead |
| Config format | Hardcoded / YAML / PHP array | YAML config | Pending Tech Lead |
| Enforcement layer | Compile-time / Runtime / Both | Compile-time AST walk + runtime `disable_functions`/`open_basedir` | Tech Lead | Sandbox impl |

**Required before implementation:**
1. Tech Lead decision on allowlist scope
2. Config format approval
3. Enforcement layer approval
4. ADR-023 approval (for publication)

---

## 4. Blocking Decisions Required

| # | Decision | Owner | Options | Recommended | Blocks |
|---|----------|-------|-------------|-----------|--------|
| 1 | ADR-023 (NeoSPX identity) | Tech Lead | Approve / Revise | **Approve** | Publication |
| 2 | ADR-022 (Auth transport) | Founder | Session cookies / JWT / Both | Session cookies | LMS HTTP |
| 3 | ADR-022 | MySQL test contract | Founder | MySQL service in CI | Configure MySQL service |
| 4 | Sandbox allowlist scope | Tech Lead | Minimal pure functions | **Minimal pure functions** | Sandbox impl |
| 5 | Sandbox config format | Tech Lead | Hardcoded / YAML / PHP | **YAML config** | Sandbox impl |
| 6 | Enforcement layer | Tech Lead | Compile-time / Runtime / Both | **Compile-time AST + runtime `disable_functions`/`open_basedir`** | Sandbox impl |
| 7 | §5.10 @php/@endphp doc fix | Doc clarification | Tech Lead | Apply | Doc clarity |
| 8 | Mechanical grammar validator | Tech Lead | ANTLR / Bison / Custom | Tech Lead | Milestone 1 |
| 9 | ADR-023 approval | Governance | Tech Lead | **Approve** | Publication |
| 10 | ADR-022 (Auth transport) | Founder | Session / JWT / Both | Session cookies | LMS HTTP |

---

## 6. Remaining Blockers (Prioritized)

| Priority | Blocker | Type | Required For | Status |
|---|---|---|---|---|
| 1 | **ADR-023** (NeoSPX identity) | Governance | Publication | **Tech Lead** | **Pending** |
| 2 | ADR-022 (Auth transport) | Governance | LMS HTTP | Founder | Pending |
| 3 | MySQL integration-test contract | Governance | CI | Founder | Pending |
| 4 | Mechanical grammar validator | Technical | Milestone 1 | No tool available |
| 5 | Sandbox enforcement (F-COMPILER-05) | Technical | Milestone 3+ | Unresolved OD |
| 6 | §5.10 @php/@endphp doc fix | Documentation | Low priority | Proposed, not applied |
| 7 | Mechanical grammar validator | Technical | Milestone 1 | No tool available |
| 8 | ADR-023 approval | Governance | Publication | **Tech Lead approval needed** |
| 9 | ADR-022 (Auth transport) | Governance | LMS HTTP | Founder decision |
| 10 | MySQL test contract | Governance | CI | Founder decision |
| 11 | OD-EXPR-BOUNDARY algorithm | Technical | Milestone 2 | Prototype only |

---

## 6. Next Actions (Priority Order)

1. **Tech Lead: Approve ADR-023** → Unblocks publication
2. **Tech Lead: Approve §5.10 @php/@endphp doc fix** → Low risk, high clarity
3. **Tech Lead: Decide sandbox defaults** → Minimal pure functions + YAML config + compile-time + runtime
4. **Tech Lead: Approve ADR-023** → Unblocks publication
5. **Founder: Decide ADR-022 (Auth transport)** → Unblocks LMS HTTP
6. **Founder: Decide MySQL test contract** → Unblocks CI
7. **Team: Select grammar validation tool** → ANTLR / Bison / custom
8. **Tech Lead: Approve ADR-023** → Unblocks publication
9. **Implement lexer (M2)** → After ADR-023 approval
10. **Implement parser (M3)** → After lexer
11. **Implement compiler (M4)** → After parser
12. **Integrate sandbox (F-COMPILER-05)** → After sandbox decisions
11. **Tech Lead: Approve ADR-023** → Unblocks publication

---

**No publication, commits, pushes, merges, rebases, resets, ADR modifications, or governance changes performed.** All work remains local and uncommitted. Prototype test artifacts preserved in `/tmp/neospx-lexer/`. Draftmark live page unchanged (not authorized to update).
