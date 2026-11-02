# SPX / NeoSPX — Compiler Construction Principles Research

**Created:** 2026-10-22  
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR  
**Source:** `document.md` (CIT 316 Compiler Construction I course guide, NOUN) — compiler theory input for NeoSPX pipeline design  
**Related:** `SPX-NAMING-COLLISION-REPORT.md`, `SPX-EASE-OF-USE-RESEARCH.md`, `Architecture/ADRs/ADR-005-neospx-vs-blade-twig.md` (CORE-07/11/12)

---

## 1. Executive Summary

The CIT 316 guide (17 study units, 4 modules) covers formal grammars, lexical analysis, syntax analysis, and code generation. Its principles map directly onto NeoSPX's three-stage pipeline (Lexer → Parser → Compiler) ratified by ADR-005. Key takeaways:

1. **Grammar class selection**: NeoSPX's template syntax is best modeled as a **context-free grammar (Type-2 / CFG)** for structure, with **regular grammars (Type-3)** for tokenization. This matches the course's front-end/back-end split and ADR-005's regex-and-DFA-based lexer.
2. **Lexical analysis**: regex → NFA → DFA pipeline (McNaughton-Yamada-Thompson + subset construction) is the standard; ADR-005's "regex-and-DFA-based lexer" is the recommended, automatable path (not hand-implemented transition diagrams).
3. **Parsing technique**: recursive descent (top-down LL(1)-style) is the ADR-005 ratified choice; the course's left-recursion elimination, ambiguity resolution (precedence/associativity), and parse-tree construction are prerequisites for CORE-11.
4. **Semantic analysis is embedded**, not a separate pass — consistent with the course's "analysis part" framing and ADR-005's "semantic analysis embedded in parser/compiler."
5. **Code generation**: intermediate representation → optimization → target code; ADR-005's AST (ComponentNode/SetupNode/DirectiveNode/RawHtmlNode) is the IR, with the Reactive Bridge (`s-data` injection) as a code-generation concern, not a parse concern.
6. **Error handling**: must span all phases (lexical, syntax, semantic, codegen) to maximize diagnostics per compilation — aligns with ADR-005's "100% coverage on complex syntax samples" testing commitment.

No implementation authorization is granted by this report.

---

## 2. Grammar Class Mapping to NeoSPX

| Chomsky Class | Automaton | Use in NeoSPX | ADR-005 Alignment |
|---------------|-----------|-----------------|-------------------|
| Type-3 Regular | Finite automaton (DFA/NFA) | Tokenization: identifiers, keywords, operators, literals, whitespace stripping, comment removal | CORE-07 "regex-and-DFA-based lexer" |
| Type-2 Context-Free | Pushdown automaton | Structural parsing: directives, component tags, expressions, control flow | CORE-11 "recursive-descent parser" |
| Type-1 Context-Sensitive | Linear-bounded automaton | Not needed for MVP; possible future use for context-dependent directives (e.g., `@persist` scoping) | Out of scope per ADR-005 |
| Type-0 Unrestricted | Turing machine | Not applicable | Out of scope |

**Principle from course**: "Context-free grammars and regular grammars provide a good compromise between expressivity and ease of parsing, and are widely used in practical applications." NeoSPX's small, bounded directive set (ADR-005) fits this compromise exactly.

**Analytic grammars (PEGs)**: the course mentions PEGs as "a more recent generalisation of TDPL designed around the practical expressiveness needs of programming language and compiler writers." This is relevant to NeoSPX's syntax-driver architecture (multiple authoring syntaxes → public component document), but PEGs are a research candidate only — not an approved decision.

---

## 3. Lexical Analysis Principles for CORE-07

### 3.1 Token Model
The course defines a token as a pair (token name, optional attribute). NeoSPX's TokenStream (per ADR-005) should follow this model:
- Simple tokens (punctuation, operators): integer code only
- Complex tokens (identifiers, literals, component tags): integer code + pointer to symbol table / value

### 3.2 Scanner Responsibilities (from course §3.2 "The Need for Lexical Analyser")
1. Group characters into tokens (longest match disambiguation)
2. Strip whitespace and comments
3. Track line numbers for error reporting
4. Recognize keywords vs identifiers (keyword-as-prefix disambiguation)
5. Process directives (`@global`, `@persist`, `~setup`)

### 3.3 Implementation Path: Automatic Generation vs Hand
The course presents two construction methods:
- **Hand implementation**: input buffering + transition diagrams (error-prone, hard to maintain)
- **Automatic generation**: regex → NFA (McNaughton-Yamada-Thompson) → DFA (subset construction) → optimized DFA → code

**Recommendation for NeoSPX**: Use automatic generation (regex + DFA), consistent with ADR-005's "regex-and-DFA-based lexer." Hand-implemented transition diagrams are a research/maintenance liability. The course's longest-match and rule-priority disambiguation rules (Lex semantics) should be adopted verbatim.

### 3.4 Regex → NFA → DFA Conversion
The course's conversion pipeline is the standard:
1. RE → NFA (McNaughton-Yamada-Thompson)
2. NFA → DFA (subset construction, with ε-closure)
3. DFA minimization
4. Code generation (transition table or hardwired goto-based scanner)

This is the implementation blueprint for CORE-07.

---

## 4. Syntax Analysis Principles for CORE-11

### 4.1 Parser Functions (from course §3.1.1)
1. Read token stream from lexer
2. Verify token sequence conforms to grammar
3. Impose tree structure (AST) for subsequent phases
4. Enter info into symbol table
5. Report syntax errors

### 4.2 CFG Formalism
A CFG = (N, T, P, S). NeoSPX's grammar should be specified in BNF/EBNF form before implementation, with:
- Terminals = token names from CORE-07
- Nonterminals = ComponentNode, SetupNode, DirectiveNode, RawHtmlNode (per ADR-005)
- Start symbol = document/template root

### 4.3 Derivations and Parse Trees
- **Leftmost derivations** → top-down parsers (recursive descent, LL)
- **Rightmost derivations** → bottom-up parsers (LR, shift-reduce)
- **Parse trees** → AST construction (the course's parse tree is the AST)

ADR-005 ratifies recursive descent (top-down), which corresponds to leftmost derivations.

### 4.4 Ambiguity Resolution
The course demonstrates that ambiguity arises from unspecified precedence/associativity. NeoSPX must define:
- Operator precedence in `~setup` expressions
- Associativity rules
- Disambiguation for component tag vs HTML tag parsing

The course's solution: rewrite grammar to be unambiguous (e.g., separate expression levels: E → E + T | T; T → T * F | F; F → (E) | id).

### 4.5 Left Recursion Elimination
Recursive descent parsers cannot handle left recursion directly. The course provides algorithms for:
- Immediate left recursion elimination: A → Aα | β becomes A → βA', A' → αA' | ε
- Indirect left recursion elimination (topological ordering)

This is a prerequisite for CORE-11's recursive descent implementation.

### 4.6 Top-Down vs Bottom-Up
| Approach | Pros | Cons | NeoSPX Fit |
|----------|------|------|--------------|
| Top-down (LL/recursive descent) | Simple, intuitive, easy error recovery, matches ADR-005 | Cannot handle left recursion without transformation; limited lookahead | **Ratified by ADR-005** |
| Bottom-up (LR/LALR) | Handles more grammars, powerful | Complex table generation, harder error messages | Not needed for NeoSPX's small grammar |
| Operator precedence | Simple for expressions | Limited to expression grammars | Could be used for `~setup` expressions |

---

## 5. Semantic Analysis (Embedded in Parser/Compiler)

Per ADR-005: "semantic analysis embedded in parser/compiler." The course's compiler architecture (§3.5) places semantic analysis in the front-end, performing:
- Type checking (variables, functions used consistently)
- Translation of ASTs into IRs

For NeoSPX, semantic analysis should cover:
- Variable scope validation (`@global`)
- Function/filter validation (allowed functions in `~setup`)
- Type checking for `@persist` serialization
- Security/sandbox (no arbitrary PHP in templates)
- Namespace resolution for component tags (`<s:ui:button />`)

---

## 6. Code Generation Principles for CORE-12

### 6.1 Compiler Phases (from course §3.2)
1. **Scanning** → CORE-07 (lexer)
2. **Parsing** → CORE-11 (parser → AST)
3. **Semantic analysis** → embedded
4. **Intermediate code generation** → AST → IR (flat PHP arrays)
5. **Code optimization** → optional pass
6. **Code generation** → IR → native PHP (OPcache-friendly)

### 6.2 Intermediate Representation
The course emphasizes IR as the bridge between front-end and back-end. For NeoSPX:
- **IR = AST** (ComponentNode/SetupNode/DirectiveNode/RawHtmlNode) per ADR-005
- Compiled output = flat PHP arrays + `s-data` JSON injection (Reactive Bridge)
- No `eval()`, no string-built closures (ADR-005, ADR-010)

### 6.3 Front-End / Back-End Separation
The course's n+m compiler architecture (n front-ends + m back-ends via universal IR) maps to:
- Front-end: NeoSPX lexer + parser (one per authoring syntax via syntax drivers)
- Back-end: PHP code generator (one target: native PHP)
- IR: public component document (language-neutral, per SPX research)

### 6.4 Passes and Back-Patching
The course discusses reducing passes via buffering and back-patching. NeoSPX's single-pass compilation (lexer → parser → compiler in one pass) is feasible because:
- Component tags are self-contained
- No forward references (declarations after use) in template syntax
- Back-patching only needed if future features require multi-pass

### 6.5 Code Optimization
The course treats optimization as optional. For NeoSPX:
- OPcache handles runtime optimization
- Compiler optimization pass is deferred (out of MVP scope per ADR-005)
- Focus on correctness and preload-friendliness (ADR-010)

---

## 7. Error Handling (Cross-Cutting)

The course (§3.2.7) states: "It is desirable that compilation be completed on flawed programmes, at least through the syntax-analysis phase, so that as many errors as possible can be detected in one compilation."

NeoSPX error handling should:
1. **Lexical errors**: report invalid characters, unterminated strings/comments
2. **Syntax errors**: report unexpected tokens with position (line/col)
3. **Semantic errors**: report undefined variables, invalid function calls, type mismatches
4. **Codegen errors**: report generation failures (rare)
5. **Recovery**: continue compilation to collect multiple errors (panic-mode or phrase-level recovery)

This aligns with ADR-005's "100% coverage on complex syntax samples" testing commitment.

---

## 8. Symbol Table Design

The course (§3.2.6): "keeps track of the names used by the programme and records essential information about each, such as its type."

For NeoSPX, the symbol table should track:
- Variables declared via `@global`
- Variables persisted via `@persist` (with serialization metadata)
- Functions/filters available in `~setup`
- Component definitions (tag → class mapping)
- Scope information (template-level vs component-level)

Implementation: hash table (course mentions hash tables as the standard data structure).

---

## 9. Compiler Quality Criteria (from course §3.4)

The course lists compiler qualities in order of importance. Mapped to NeoSPX:

| # | Quality | NeoSPX Application |
|---|---------|----------------------|
| 1 | Bug-free compiler | CORE-07/11/12 test coverage |
| 2 | Correct generated code | AST → PHP correctness tests |
| 3 | Fast generated code | OPcache-friendly output (ADR-010) |
| 4 | Fast compilation | Single-pass, checksum-cached (ADR-005) |
| 5 | Portability | PHP ^8.4 only, no Node.js (ADR-005) |
| 6 | Good diagnostics | Multi-phase error reporting |
| 7 | Debugger integration | Deferred (not MVP) |
| 8 | Consistent optimization | Deferred (OPcache handles) |

---

## 10. Research Questions (Open, Not Decisions)

1. Should NeoSPX adopt PEGs for its syntax drivers, or stick with CFG + recursive descent? (PEGs are a research candidate per course; ADR-005 already ratifies recursive descent.)
2. Should NeoSPX use a separate semantic analysis pass, or keep it embedded? (ADR-005 says embedded; course supports both.)
3. Should the component document (interchange contract) be JSON Schema-validated? (Per SPX research — JSON Schema 2020-12 is a candidate, not approved.)
4. Should NeoSPX support operator precedence parsing for `~setup` expressions, or full recursive descent? (Both are course-covered; recursive descent is simpler for a small grammar.)
5. What is the error recovery strategy for NeoSPX? (Panic-mode vs phrase-level — course covers both; decision needed for CORE-11.)

---

## 11. Decisions Explicitly NOT Made

- No change to ADR-005 (ratified; this report is research only)
- No implementation of CORE-07/11/12 (all `📝 Not started`)
- No selection of PEGs vs CFG (research candidate only)
- No authorization of JSON Schema 2020-12 for component document
- No change to semantic analysis embedding decision
- No error recovery strategy selection

---

## 12. Evidence Level

| Claim | Source | Verification |
|-------|--------|--------------|
| Chomsky hierarchy (4 types) | CIT 316 Unit 1 | ✅ Course guide |
| Regex → NFA → DFA pipeline | CIT 316 Module 2 Units 3-4 | ✅ Course guide |
| Recursive descent / left recursion elimination | CIT 316 Module 3 Units 1-4 | ✅ Course guide |
| CFG formalism (N, T, P, S) | CIT 316 Module 3 Unit 1 | ✅ Course guide |
| Compiler phases (scan/parse/semantic/IR/opt/codegen) | CIT 316 Module 1 Unit 3 | ✅ Course guide |
| Error handling across phases | CIT 316 Module 4 Unit 1 | ✅ Course guide |
| Symbol table (hash table) | CIT 316 Module 4 Unit 2 | ✅ Course guide |
| ADR-005 pipeline (Lexer→Parser→Compiler) | `Architecture/ADRs/ADR-005-*.md` | ✅ Verified |
| CORE-07/11/12 not started | `Architecture/INDEX.md` | ✅ Verified |

---

---

## 13. Online Research Findings (2026-10-22)

This section supplements the CIT 316 course-guide analysis (Sections 1–12) with findings from primary sources, documentation, and open-source compiler internals retrieved online. Each subsection maps to one of the 12 research topics requested. Sources are dated where available.

### 13.1 Recursive Descent Parser Design and Implementation Best Practices

**Sources:**
- jayconrod.com "How to build a parser by hand" (2014-02-13)
- matklad.github.io "Modern parser generator" (2018-06-06)
- chibivue Book "Parser Optimization" (Vue 3.4 tokenizer)
- TACO Lab, HKUST "A Simple Recipe for Writing Decent Recursive Descent Parsers" (2026-01-01)
- Rice University "Design Patterns for Parsing" (LL(1) visitor/chain-of-responsibility/factory patterns)

**Key findings:**
1. **Pratt parsing for expressions**: Recursive descent requires grammar rewriting for operator precedence; Pratt parsing (top-down operator precedence) fuses precedence into a single loop and is the recommended modern approach for expression parsing within a recursive descent parser. matklad (rust-analyzer author) advocates this as the standard technique.
2. **Loops replace left-recursion elimination**: In practice, left recursion is handled with `while`/`loop` constructs rather than formal grammar transformation. This is simpler and matches how production parsers (Kotlin, Rust) actually work.
3. **Error recovery in recursive descent**: Two tricks — (a) in homogeneous sequences, skip tokens that cannot begin a new element; (b) for a specific construct `T` expecting token `foo` but seeing `bar`: if `bar` ∉ FOLLOW(T), skip it; if `bar` ∈ FOLLOW(T), emit error but don't skip. This yields precise partial parses.
4. **Vue 3.4 state-machine tokenizer**: Vue replaced recursive descent + regex with a character-by-character state machine tokenizer, achieving 2x parse speedup. For NeoSPX, this suggests: if the lexer becomes a bottleneck, consider a state-machine approach over regex-and-DFA. However, ADR-005 ratifies regex-and-DFA; the state-machine is a future optimization candidate, not a replacement.
5. **Design patterns for LL(1) parsers**: Visitor pattern (tokens as hosts, parsing algorithms as visitors), chain-of-responsibility (select correct rule), abstract factory (manufacture parsing visitors). These reduce conditionals and improve extensibility. Relevant for NeoSPX's directive extensibility.

**Relevance to NeoSPX:** Pratt parsing should be the technique for `~setup` expressions; error recovery tricks should be adopted for CORE-11; state-machine tokenizer is a future optimization option.

### 13.2 Lexer/Tokenizer Design Patterns for Template Engines

**Sources:**
- Twig Lexer source (twigphp/Twig, STATE_DATA/STATE_BLOCK/STATE_VAR/STATE_STRING/STATE_INTERPOLATION)
- php-dissect docs/lexing.md (stateful lexer with mode stack)
- Sharp view engine docs/architecture.md (char-by-char mode stack, 7 modes)
- Latte docs (dual lexer: HTML template + PHP expression language, parallel execution)
- ircmaxell's blog "Tries and Lexers" (trie-based lexer for PHP)
- phplrt.org (regex-driven lexer, nested lexers for template languages)

**Key findings:**
1. **Stateful/mode-stacked lexer is the standard for template engines**: Twig uses 5 states (STATE_DATA, STATE_BLOCK, STATE_VAR, STATE_STRING, STATE_INTERPOLATION); php-dissect provides StatefulLexer with push/pop state stack; Sharp uses 7 modes. NeoSPX should adopt a stateful lexer with explicit mode stack.
2. **Latte's dual-lexer architecture**: Latte runs two lexers in parallel — one for HTML template structure, one for PHP-like expression language inside tags. The lexer and parser run in parallel (not sequentially) using coroutines/fibers, because the parser must influence tokenization (e.g., `n:syntax=off`). This is critical for NeoSPX's component tags (`<s:ui:button />`) where HTML and directive contexts interleave.
3. **Twig's pre-scan optimization**: Twig uses `preg_match_all` to find all token starts in one pass, then iterates positions. This avoids repeated regex scanning. Recommended for NeoSPX lexer performance.
4. **Trie-based lexer**: ircmaxell's blog demonstrates trie-based tokenization as an alternative to regex for PHP, achieving efficiency. Relevant if regex performance becomes an issue.
5. **Nested lexers**: phplrt supports handing fragments to sub-lexers (for string literals, PHP inside HTML). NeoSPX's `~setup` blocks should use a nested lexer for PHP expressions.
6. **Anchoring regexes**: Always anchor regexes with `^` at the beginning (php-dissect docs). Without anchoring, PCRE scans the entire remaining input, causing O(n²) behavior.

**Relevance to NeoSPX:** Adopt stateful mode-stacked lexer; consider parallel lexer/parser for component tags; anchor all regexes; pre-scan token positions for performance.

### 13.3 AST Design for Template Languages

**Sources:**
- Twig Node system (Twig\Node\Node, compile() method, NodeTraverser, NodeVisitor)
- Latte AST (Node base class, print(), getIterator(), NodeHelpers)
- Sharp view engine (RootNode, Node::compile(ctx), OptimizePass)
- Semantic UI AST spec (html, expression, if, each, slot, template, snippet, svg nodes)
- ruitl_compiler (Rust enum-based AST: Element, Text, Expression, If, For, Component, Fragment)
- CoffeeHaml AST (SourceLocation on every node, renderer-agnostic IR)
- templast spec (unist-compliant cross-template-language AST)
- mau (Elixir tuple-format AST with opts metadata)

**Key findings:**
1. **Node base class with compile() method**: Both Twig and Sharp use a Node base class where each node implements `compile(Compiler)` or `compile(Context)`. This is the visitor/codegen pattern. NeoSPX's AST nodes (ComponentNode, SetupNode, DirectiveNode, RawHtmlNode) should follow this.
2. **Source location on every node**: CoffeeHaml and Sharp carry SourceLocation (line, column, offset, endLine, endColumn, endOffset) on every node. Essential for error reporting and source maps. NeoSPX should include this.
3. **getIterator() for traversability**: Latte requires every node to implement getIterator() yielding references to children, enabling compiler passes to traverse and modify. This is a powerful pattern for NeoSPX's semantic analysis and optimization passes.
4. **Node visitor pattern**: Twig's NodeTraverser applies registered NodeVisitors before compilation, enabling AST transformation (optimization, security sandboxing). NeoSPX should support this for the Reactive Bridge injection and sandbox checks.
5. **Rust enum-based AST**: ruitl uses a tagged enum (Element, Text, Expression, If, For, Component, Fragment, Raw) with Box for recursive nesting. In PHP, this maps to an abstract base class with concrete subclasses. NeoSPX's 4 node types (ComponentNode, SetupNode, DirectiveNode, RawHtmlNode) are sufficient for MVP; additional types (IfNode, ForNode, etc.) can be added as directives expand.
6. **Flat vs. nested AST**: Sharp's OptimizePass merges adjacent TextNodes and strips CommentNodes. NeoSPX should include a similar optimization pass between parse and codegen.

**Relevance to NeoSPX:** Adopt Node::compile() pattern, source locations on all nodes, getIterator() for traversability, NodeVisitor pattern for passes, and an optimization pass.

### 13.4 Code Generation Strategies for Template Compilers

**Sources:**
- Twig Compiler (subcompile, write, raw, repr, indent/outdent, addDebugInfo)
- Latte (print(PrintContext), Blueprint::printClass(), PHP code generation)
- Sharp (Node::compile(ctx), CompilationContext with registries)
- Tangible template-system PR #159 (compile-to-PHP with hoisting, closures, cache keys)
- PHPX (JSX-like → flat PHP arrays)
- Scaffold/CodefyPHP (cached PHP classes, recompilation modes)
- Mironsoft blog "Building Your Own Templating Engine in PHP" (regex → PHP, caching, mtime checks)

**Key findings:**
1. **Compile-once, cache-forever**: All major PHP template engines (Blade, Twig, Smarty, Latte) compile to PHP and cache on disk, keyed by source mtime/checksum. NeoSPX's checksum-based cache (ADR-005) is the standard approach. mtime comparison is the simplest invalidation; checksum is more robust.
2. **Flat PHP arrays as IR/output**: PHPX compiles to flat arrays `['$', tag, props, children]`. NeoSPX's compiled output (flat PHP arrays + s-data JSON) aligns with this. Flat arrays are OPcache-friendly and avoid deep nesting at runtime.
3. **Hoisting deterministic content**: Tangible's compiler hoists provably deterministic content to compile-time string literals, deferring only dynamic content. This is an optimization NeoSPX could adopt for static HTML blocks.
4. **Compiler context object**: Sharp passes CompilationContext carrying DirectiveRegistry, ComponentRegistry, Config. NeoSPX should use a similar context object during codegen.
5. **Indentation tracking**: Twig's Compiler tracks indentation level and emits formatted PHP. NeoSPX should emit readable PHP for debugging, with debug info (line mapping) like Twig's addDebugInfo().
6. **OPcache integration**: Docket Cache demonstrates that flat array returns compile to a single opcode. NeoSPX should ensure compiled templates return flat arrays for maximum OPcache efficiency.
7. **Never-fatal compilation**: Tangible tests 20 malformed templates that must not throw. NeoSPX should handle malformed input gracefully during codegen.

**Relevance to NeoSPX:** Adopt compile-once/cache-forever, flat array output, compiler context, indentation tracking, debug info, and graceful error handling.

### 13.5 Semantic Analysis in Template Engines

**Sources:**
- Kida (template type checking, {% template %} declarations, variable tracking)
- LiquidScript (static analysis: variables, globals, locals, paths, locations)
- LiquidJS (analysis.ts: StaticAnalysis, VariableMap, scope tracking)
- Latte (Sandbox, compiler passes, type-aware validation)
- Citry (template linting, unknown variable detection)
- Jinja2 (SandboxedEnvironment, AST-based validation)
- Scope graphs paper (Portland State, constraint-based semantic analysis)

**Key findings:**
1. **Embedded semantic analysis**: ADR-005's "semantic analysis embedded in parser/compiler" is confirmed by all major engines. Twig validates during parsing; Latte uses compiler passes; Jinja2 parses AST without rendering.
2. **Variable scope tracking**: LiquidJS tracks rootScope, partialScope, blockScope, and alias resolution. NeoSPX should track variable scopes for @global and ~setup variables.
3. **Sandbox/security passes**: Latte's Sandbox is implemented as a compiler pass that traverses the AST and validates allowed functions/tags. NeoSPX's security model (no arbitrary PHP in templates) should use the same approach.
4. **Static analysis for variables**: Kida's {% template %} declarations and LiquidScript's variable extraction provide models for NeoSPX to validate that all referenced variables are declared via @global or ~setup.
5. **Type checking**: Kida validates literal argument types against function signatures. NeoSPX's ~setup expressions could benefit from similar validation for @persist serialization.
6. **Scope graph formalism**: The Portland State paper on scope graphs provides a formal framework for name resolution. For NeoSPX's small scope model (template-level vs component-level), a simple hash-table-based symbol table suffices; scope graphs are overkill for MVP.

**Relevance to NeoSPX:** Keep semantic analysis embedded; implement scope tracking for variables; use compiler passes for sandbox security; validate variable declarations.

### 13.6 Error Recovery in Parsers

**Sources:**
- NYU Compilers Lecture #5 (panic mode, phrase-level, error productions, global correction)
- CPCT+ paper (arxiv.org 1804.07133, panic mode vs advanced recovery, 98.37% repair rate)
- CUP Chapter 6 (error symbol, sync size, stack cutting)
- HLP thesis (phrase-level recovery with local correction for LR parsers)
- Hylo compiler (phrase-level + combinator wrappers + token sync + AST placeholders)
- Leinius original phrase-level recovery paper (1983)

**Key findings:**
1. **Panic mode is the baseline**: Discard input until synchronizing token (e.g., `;`, `}`, newline). Simple, grammar-neutral, applicable to any parser. NeoSPX should implement this as the minimum.
2. **Phrase-level recovery is more precise**: Replace error phrase with a reduction goal. Local correction (insert/delete/replace single tokens) handles common typos. Recommended for better diagnostics.
3. **Synchronizing tokens for template languages**: For NeoSPX, good synchronizing tokens are: `}`, `/>`, `</`, `@`, newline, EOF. These are natural statement boundaries.
4. **AST placeholders for error recovery**: Hylo compiler uses AST placeholders (error nodes) to keep the AST valid even with syntax errors. This enables continued analysis (type checking, etc.) after errors. NeoSPX should emit placeholder nodes for unparseable sections.
5. **Error productions**: Extend grammar to accept common erroneous constructs (e.g., missing closing tag). Useful for specific known error patterns.
6. **Diagnostic quality**: CPCT+ demonstrates that reporting minimum-cost repair sequences reduces cascading errors from 981K to 436K locations. For NeoSPX, reporting the first error precisely is more valuable than reporting many.

**Relevance to NeoSPX:** Implement panic-mode recovery as baseline with template-specific synchronizing tokens; use AST placeholders for partial parse trees; prioritize precise first-error reporting.

### 13.7 Symbol Table Design for Template Languages

**Sources:**
- Nand2Tetris Ch. 11 (list of hash tables, scope nesting, enter/exit scope)
- Wellesley CS301 scope-type.pdf (scoped symbol table design)
- UMN CS5641 L15 (list of hashtables, operations, running times)
- WPI CS544 PLT7 (scope attribute, hash tables, O(1) average)
- Engineering a Compiler (Cooper & Torczon) §5.5.3 sheaf-of-tables
- nand2tetris symbol table (list of hash tables, inner-to-outer search)

**Key findings:**
1. **List of hash tables (sheaf-of-tables)**: The standard approach — a list of hash tables, one per scope, searched from innermost to outermost. Scope entry adds a new table; scope exit removes it. O(1) expected for declaration/lookup; O(depth) worst case for lookup.
2. **Hash table is the standard**: All production compilers use hash tables. The course's recommendation (hash table) is confirmed.
3. **Scope nesting in templates**: NeoSPX scopes are: template-level (@global), component-level, and ~setup block. Three levels maximum for MVP. This is shallow; performance is not a concern.
4. **Symbol entries**: Each symbol should store: name, type, scope level, declaration location (line/col), and semantic metadata (e.g., @persist serialization type, @global visibility).
5. **Name hiding**: Inner scopes can shadow outer names. The sheaf-of-tables approach handles this naturally by searching innermost first.
6. **Separate tables per name kind**: Some compilers use separate tables for different name kinds (variables vs functions vs types). For NeoSPX, a single table with a "kind" field is simpler and sufficient for MVP.

**Relevance to NeoSPX:** Implement sheaf-of-tables with hash tables; track name, type, scope, location, and semantic metadata; single table with kind field.

### 13.8 Compiler Optimization for PHP Output

**Sources:**
- PHP OPcache preloading manual (php.net)
- nikic's blog "The opcache optimizer" (2022-05-22)
- Docket Cache OPcache optimisations docs
- PHP 7 packed arrays (Blackfire blog)
- Ready, Preload, Go (thephp.cc)
- Symfony performance docs (OPcache + preload)

**Key findings:**
1. **OPcache handles runtime optimization**: The compiler should focus on correctness and OPcache-friendliness, not optimization passes. This confirms ADR-005's "optimization deferred" decision.
2. **Flat array returns compile to single opcode**: Docket Cache demonstrates that `return [key => value, ...]` with all literals compiles to a single opcode. NeoSPX's flat PHP array output should leverage this.
3. **Packed arrays**: PHP 7+ optimizes arrays with sequential integer keys (packed arrays). NeoSPX should emit sequentially-keyed arrays where possible for memory efficiency.
4. **Preloading**: OPcache preloading (`opcache.preload`) loads compiled PHP into persistent memory at startup. NeoSPX's compiled templates should be preloadable (no global side effects, only class/function definitions or return statements).
5. **Constant folding**: PHP compiler folds constant expressions (`4 * 25` → `100`) at compile time. NeoSPX's codegen should emit constant expressions where possible to let PHP's compiler optimize.
6. **No NOP removal needed**: OPcache's NOP removal pass handles dead code. NeoSPX should not implement its own dead code elimination.
7. **Preload-friendly output**: Flat files with no autoload dependencies, no global state mutation, and `return` statements (not `echo`) are ideal for preloading.

**Relevance to NeoSPX:** Focus on OPcache-friendly output (flat arrays, packed arrays, constant folding, no global state); defer optimization passes to OPcache.

### 13.9 PHP Template Engine Compiler Internals (Twig, Blade, Smarty, Latte)

**Sources:**
- Twig internals docs (twig.symfony.com/doc/3.x/internals.html)
- Blade compiler internals (Laravel 10.x src/Illuminate/View/Compilers/BladeCompiler.php)
- Smarty core architecture (deepwiki.com/smarty-php/smarty)
- Latte docs (latte.nette.org, blog.nette.org latte-3-impressive-leap)
- liamhammett.com "Laravel Blade Internals" (2021-09-15)
- stillat.com Blade Parser compiler phases

**Key findings:**

**Twig:**
- 4-step pipeline: Load → Tokenize → Parse → Compile (same as NeoSPX)
- Lexer: 5 states (STATE_DATA, STATE_BLOCK, STATE_VAR, STATE_STRING, STATE_INTERPOLATION); pre-scans token positions with preg_match_all
- Parser: recursive descent via TokenParser handlers; NodeTraverser applies NodeVisitors before compilation
- Compiler: Node::compile(Compiler) pattern; tracks indentation, debug info, var name salt
- Key pattern: NodeVisitor for AST transformation before codegen (optimization, sandbox)

**Blade:**
- Not a true parser — uses PHP's `token_get_all()` to tokenize, then regex-based directive replacement
- Preprocessor-style: directives are string transformations, not AST-level
- Compile-once, cache-forever model
- Key insight: Blade's simplicity comes from NOT building an AST; but this limits semantic directives (like @persist). NeoSPX's AST approach (ADR-005) is the correct choice for semantic directives.

**Smarty:**
- True compiler: Lexer + Parser (TemplateParser.y grammar file) + Code Generation
- Uses yacc/bison-style grammar file (TemplateParser.y) — a parser generator, not hand-written
- Plugin/tag system with attribute validation (required/optional attributes, option flags)
- Nocache handling: tracks nocache sections and injects code conditionally
- Key pattern: grammar file + parser generator for complex grammars; but NeoSPX's small grammar favors hand-written recursive descent

**Latte:**
- Most sophisticated PHP template engine: dual lexer (HTML + PHP expression), parallel lexer/parser execution
- AST with Node base class, print(), getIterator(), NodeHelpers
- Compiler passes: traverse AST, modify nodes, security sandbox
- Context-aware escaping: tracks content type (HTML, JS, CSS, URL, XML) and applies appropriate escaping
- PHP linter integration: validates generated PHP code during compilation
- Key patterns: compiler passes, context-aware escaping, PHP linter validation, dual lexer with parallel execution

**Relevance to NeoSPX:** Twig's NodeVisitor pattern and Compiler architecture are the closest model; Latte's compiler passes and context-aware escaping are valuable; Blade's preprocessor approach validates ADR-005's AST decision; Smarty's parser-generator approach is not recommended for NeoSPX's small grammar.

### 13.10 Formal Grammar Specification for Template Languages

**Sources:**
- ISO 14977 EBNF standard summary (cl.cam.ac.uk)
- BNF Converter / LBNF (bnfc.digitalgrammars.com)
- Grammarware "BNF was here" paper (diversity of notation)
- Protobuf language spec (EBNF-based formal spec)
- XEBNF / grammar-meta-standard (versioned grammars)
- Thomas van Binsbergen slides (formal/executable/reusable syntax specs)
- tomassetti.me EBNF explained

**Key findings:**
1. **EBNF is the standard notation**: ISO 14977 defines the standard EBNF. NeoSPX's grammar should be specified in EBNF (or a documented variant) before implementation. ADR-005 requires BNF/EBNF form (Section 4.2 of existing report).
2. **LBNF (Labelled BNF)**: BNFC uses LBNF where each rule has a label (constructor name). This is useful for AST generation — the label becomes the node class name. NeoSPX could adopt this convention (e.g., `ComponentNode ::= "<s:" IDENT ... ">"`).
3. **Grammar should be versioned**: XEBNF supports version annotations (`@[since: "11.0"]`). NeoSPX's grammar should include version info for SemVer tracking (ADR-005 notes compiler output format is a SemVer major).
4. **Grammar diversity is a real problem**: The Grammarware paper shows 42 different notations across 77 grammars. NeoSPX should pick one notation and document it clearly.
5. **EBNF operators**: Standard EBNF uses `=` for definition, `|` for alternation, `[]` for option, `{}` for repetition, `()` for grouping. These should be used consistently.
6. **Grammar recovery from sources**: The Grammarware approach (formal notation definition + automated extraction) could help if NeoSPX's grammar needs to be extracted from code later.

**Relevance to NeoSPX:** Specify grammar in EBNF/LBNF before CORE-11 implementation; use LBNF labels for AST node naming; version the grammar; document notation choice.

### 13.11 Compiler Construction Textbooks and Resources

**Sources:**
- Dragon Book (Aho, Lam, Sethi, Ullman) 2nd ed. 2006 — 1009 pages, 8,173 citations
- compilerprogramming.github.io compiler-books.html
- CIT 316 course guide (NOUN) — 17 units, 4 modules
- Appel "Modern Compiler Implementation in Java/C/ML" (2002)
- Cooper & Torczon "Engineering a Compiler" (2012)
- Wirth "Compiler Construction" (Oberon examples)
- Fischer & LeBlanc "Crafting a Compiler" (ADA pseudo-code)
- Allen & Kennedy "Optimising Compilers for Modern Architectures" (2001)

**Key findings:**
1. **Dragon Book (2nd ed.)**: The classic reference. Covers lexical analysis (regex, DFA, NFA), syntax analysis (LL, LR, LALR), syntax-directed translation, intermediate code, symbol tables, code generation, optimization. However, criticized for excessive focus on lexing/parsing and lack of modern techniques (SSA, graph coloring register allocation).
2. **Engineering a Compiler (Cooper & Torczon, 2012)**: More modern than Dragon Book; covers later phases in more detail; less focus on lexing/parsing. Better for NeoSPX's codegen phase.
3. **Modern Compiler Implementation (Appel, 2002)**: Cleanly written; covers ML/Java/C perspectives. Good for understanding compiler front-end design.
4. **CIT 316 (course guide)**: Already analyzed in Sections 1–12. Provides the foundational theory (Chomsky hierarchy, regex→NFA→DFA, CFG, recursive descent, codegen phases, error handling, symbol tables).
5. **Wirth "Compiler Construction"**: Oberon-based; good for understanding small-language compiler design. Relevant for NeoSPX's small grammar.
6. **Compiler Programming blog (compilerprogramming.github.io)**: Curated list of compiler books with reviews. Recommends 1986 Dragon Book edition over 2nd ed. for pseudo-code style.

**Relevance to NeoSPX:** Dragon Book for theory (already covered by CIT 316); Engineering a Compiler for modern codegen; Wirth for small-language design; all confirm the course guide's recommendations.

### 13.12 Modern Compiler Construction Patterns (Incremental, Demand-Driven, Rust-Based)

**Sources:**
- Rust compiler dev guide (query system, incremental compilation, red-green algorithm)
- Rust RFC 1298 (incremental compilation design)
- jfecher/modern-compiler-architecture (Rust, incremental, concurrent, fault-tolerant)
- Salsa framework (demand-driven incremental computation)
- OriLang compiler design (Salsa patterns, early cutoff)
- LWN "Rust's incremental compiler architecture" (2024-12-03)
- PLISS 2019 "Responsive compilers" (Nicholas Matsakis)

**Key findings:**
1. **Query-based/demand-driven architecture**: Rust compiler organizes computation as queries (functions with memoization and dependency tracking). Instead of fixed passes, the compiler demands information from the end (codegen) backward. NeoSPX's single-pass compilation is simpler but could adopt query-based caching for multi-file scenarios.
2. **Incremental compilation via red-green algorithm**: After each compilation, save query results and dependency DAG. On next run, color queries red (changed) or green (unchanged). Green queries skip re-execution. NeoSPX's checksum-based cache is file-level; query-based caching would be finer-grained (per-component, per-directive).
3. **Early cutoff**: If a re-executed query produces the same result, dependents skip re-execution. This is powerful for template engines — a whitespace-only edit should not trigger recompilation of the entire template. NeoSPX could implement this at the AST level.
4. **Fault-tolerant parsers**: Modern compilers (rust-analyzer, Roslyn) always produce a valid AST even with syntax errors, using error nodes/placeholders. This is essential for IDE support (Language Server Protocol). NeoSPX should adopt this for future tooling.
5. **Concurrent compilation**: jfecher's modern compiler compiles files in parallel. NeoSPX's component-based architecture could compile components in parallel.
6. **Salsa framework**: General-purpose incremental computation framework (inputs, tracked functions, automatic dependency tracking, early cutoff). Could be adapted for PHP but is likely overkill for MVP.

**Relevance to NeoSPX:** Query-based caching and early cutoff are valuable for future iterations; fault-tolerant parsing should be adopted now; concurrent compilation is a future optimization. For MVP, checksum-based file cache (ADR-005) is sufficient.

---

## 14. Reconciliation: Course Guide vs. Online Research

| Topic | CIT 316 (Course) | Online Research | Agreement | New Insight |
|-------|------------------|-----------------|-----------|-------------|
| Grammar classes | Chomsky 4 types | EBNF/LBNF/XEBNF formalisms | ✅ Agree | LBNF labels for AST nodes; versioned grammars |
| Lexer | regex→NFA→DFA | Stateful mode-stacked; parallel lexer/parser (Latte); pre-scan (Twig) | ✅ Agree | Parallel execution for component tags; pre-scan optimization |
| Parsing | Recursive descent, left-recursion elimination | Pratt parsing for expressions; loops replace formal elimination; error recovery tricks | ✅ Agree | Pratt parsing is the modern standard for expressions |
| AST | Parse tree = AST | Node::compile(), SourceLocation, getIterator(), NodeVisitor | ✅ Agree | Source locations, traversability, visitor pattern |
| Codegen | IR → optimization → target | Flat arrays, hoisting, OPcache integration, debug info | ✅ Agree | Flat arrays for single-opcode compilation |
| Semantic analysis | Embedded in front-end | Embedded; scope tracking; sandbox passes | ✅ Agree | Scope tracking, compiler passes for security |
| Error recovery | Panic mode, phrase-level | AST placeholders, synchronizing tokens, precise first-error | ✅ Agree | AST placeholders for partial parse trees |
| Symbol table | Hash table | Sheaf-of-tables, O(1) expected | ✅ Agree | Scope entry/exit, name hiding, single table with kind |
| Optimization | Optional pass | OPcache handles; flat arrays; packed arrays | ✅ Agree | Defer to OPcache; focus on OPcache-friendliness |
| PHP engines | Not covered | Blade (preprocessor), Twig (AST+Visitor), Smarty (yacc), Latte (dual lexer+passes) | N/A | Latte is the most relevant model; Blade validates AST decision |
| Grammar spec | BNF/EBNF | ISO 14977, LBNF, versioned | ✅ Agree | LBNF labels; version annotations |
| Modern patterns | Not covered | Query-based, incremental, fault-tolerant, concurrent | N/A | Fault-tolerant parsing for future tooling |

**Conclusion:** The CIT 316 course guide and online research are in strong agreement on all core topics. Online research adds practical implementation patterns (Pratt parsing, stateful lexer, NodeVisitor, compiler passes, flat arrays, fault-tolerant parsing) that the course covers theoretically but not in implementation detail.

---

## 15. Updated Evidence Level

| Claim | Source | Verification |
|-------|--------|--------------|
| All Section 1–12 claims | CIT 316 course guide + ADR-005 | ✅ Verified |
| Recursive descent best practices | jayconrod, matklad, chibivue, TACO Lab, Rice | ✅ Web (2014–2026) |
| Lexer patterns (stateful, dual, pre-scan) | Twig source, php-dissect, Sharp, Latte, phplrt | ✅ Web (source code + docs) |
| AST design (Node::compile, SourceLocation, Visitor) | Twig, Latte, Sharp, Semantic UI, ruitl | ✅ Web (source code + specs) |
| Codegen (flat arrays, hoisting, OPcache) | Twig, Sharp, Tangible, PHPX, Docket Cache, PHP manual | ✅ Web (source code + docs) |
| Semantic analysis (scope tracking, sandbox) | Kida, LiquidScript, LiquidJS, Latte, Citry, Jinja2 | ✅ Web (source code + docs) |
| Error recovery (panic, phrase-level, placeholders) | NYU lecture, CPCT+, CUP, HLP, Hylo | ✅ Web (papers + docs) |
| Symbol table (sheaf-of-tables, hash) | Nand2Tetris, Wellesley, UMN, WPI, Cooper/Torczon | ✅ Web (course notes + textbook) |
| PHP optimization (OPcache, flat arrays, packed) | PHP manual, nikic blog, Docket Cache, Blackfire | ✅ Web (docs + benchmarks) |
| PHP engine internals (Twig, Blade, Smarty, Latte) | Official docs + source code | ✅ Web (docs + source) |
| Grammar spec (EBNF, LBNF, versioned) | ISO 14977, BNFC, Protobuf spec, XEBNF | ✅ Web (standards + specs) |
| Textbooks (Dragon, Engineering, Appel, Wirth) | Pearson, compilerprogramming.github.io | ✅ Web (publisher + reviews) |
| Modern patterns (query, incremental, fault-tolerant) | Rust dev guide, RFC 1298, Salsa, jfecher | ✅ Web (docs + source) |

---

## 16. Updated Open Research Questions

In addition to the 5 questions in Section 10, the online research raises these further questions for owner review:

6. Should NeoSPX adopt **Pratt parsing** for `~setup` expressions, or full recursive descent? (Pratt is the modern standard per matklad; recursive descent is simpler for a small grammar. Both are course-covered.)
7. Should the lexer and parser run **in parallel** (Latte-style) for component tags, or sequentially (Twig-style)? (Parallel enables parser-influenced tokenization but adds complexity.)
8. Should NeoSPX implement **fault-tolerant parsing** with AST placeholders from the start (for future IDE/LSP support), or defer to a later iteration?
9. Should NeoSPX implement **early cutoff** at the AST level (whitespace-only edits skip recompilation) in the initial cache design, or keep it file-level (checksum)?
10. Should NeoSPX adopt **context-aware escaping** (Latte-style, tracking HTML/JS/CSS/URL/XML content types) from MVP, or defer?
11. Should NeoSPX emit **debug info** (source-to-compiled line mapping) in compiled output from MVP, or defer?
12. Should the grammar be specified in **LBNF** (with constructor labels = AST node names) for formal precision, or informal EBNF?

---

## 17. Decisions Still Explicitly NOT Made

All items from Section 11 remain not made. Additionally:
- No selection of Pratt vs recursive descent for expressions
- No decision on parallel lexer/parser
- No decision on fault-tolerant parsing
- No decision on early cutoff
- No decision on context-aware escaping from MVP
- No decision on debug info in MVP
- No decision on LBNF vs EBNF for grammar spec
- No change to ADR-005

---

**End of research report (updated).** This is a research deliverable. It does not authorize any repository change, ADR amendment, or implementation. All recommendations require owner approval.
