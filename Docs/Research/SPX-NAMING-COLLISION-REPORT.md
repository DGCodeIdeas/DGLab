# SPX / SuperPHP — Standalone Research Report

**Created:** 2026-10-20  
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR, NOT a rename authorization  
**Parent agenda:** `RESEARCH-AGENDA.md` (root) — SuperPHP Naming Investigation section  
**Related:** `RESEARCH-RECONCILIATION-MATRIX.md` §5

---

## 1. Executive Summary

SuperPHP is the planned template-language capability for DGLab, encompassing three Core-tier blueprints: `CORE-07` (Lexer), `CORE-11` (Parser), `CORE-12` (Compiler). All three are currently `📝 Not started` (per `Architecture/INDEX.md` §1). ADR-005 ratified SuperPHP over Blade/Twig/Plates.

The owner has considered renaming SuperPHP but has **not approved** any replacement. Five candidate names were recorded: Forge, Foundry, Nexus, SPX, Sovereign. No rename is authorized.

This report provides:
1. **Responsibilities and boundaries** for the SuperPHP pipeline
2. **Architecture comparison** with comparable systems
3. **Format-neutral information model** analysis (JSON-first vs JSON+YAML)
4. **Naming collision audit** (internal + external)
5. **Research questions and deliverables**

---

## 2. Responsibilities and Boundaries

### 2.1 Component Pipeline (Per ADR-005)

```
Authoring syntax (.super.php)
    │
    ▼
Syntax Driver (pluggable — multiple authoring syntaxes)
    │
    ▼
Public Component Document (language-neutral interchange contract)
    │
    ▼
AST (ComponentNode / SetupNode / DirectiveNode / RawHtmlNode)
    │
    ▼
Validation / Semantic Analysis
    │
    ▼
Compilation → native PHP (OPcache-friendly, flat arrays, s-data injection)
    │
    ▼
Cache (storage/framework/views, keyed by source checksum)
```

### 2.2 Stage Responsibilities

| Stage | Component | Responsibility | Boundary |
|-------|-----------|----------------|----------|
| **Lexer** | CORE-07 | Char-by-char tokenization → TokenStream. Regex-and-DFA-based. | Input: source text. Output: TokenStream. No semantic knowledge. |
| **Parser** | CORE-11 | Recursive-descent parsing → AST. Handles directives (`@global`, `@persist`, `~setup`), component tags (`<s:ui:button />`), raw HTML. | Input: TokenStream. Output: AST. No code generation. |
| **Compiler** | CORE-12 | AST walk → native PHP. Injects `s-data` JSON attributes (Reactive Bridge). Caches by source checksum. | Input: AST. Output: PHP string. No parsing. |
| **Semantic Analysis** | (embedded in parser/compiler) | Variable scope validation, function/filter validation, type checking, security (sandbox), namespace resolution. | Part of parser + compiler, not a separate stage. |

### 2.3 Key Design Constraints (from ADR-005, ADR-010, ADR-002)

1. **Node-free asset pipeline** — No Node.js, npm, webpack, Vite, or JS build step. Server-rendered HTML is source of truth.
2. **Reactive Bridge** — `@persist` serializes variables into `s-data` JSON attributes for frontend SPA hydration.
3. **OPcache-friendly output** — Flat PHP arrays, no `eval()`, no string-built closures. Preloadable under ADR-010.
4. **Custom container integration** — Compiled output uses flat-array service lookups matching ADR-002's container shape.
5. **Semantic directives** — `@persist` means "serialize this variable into `s-data` attribute on rendered component." Needs AST-level support, not preprocessor string substitution.

### 2.4 What SuperPHP Is NOT

- **Not a runtime dependency for LMS** — SuperPHP is not needed for the LMS backend MVP
- **Not yet implemented** — All three blueprints are `📝 Not started`
- **Not a rename target** — No rename approved; ADR-005 remains authoritative

---

## 3. Architecture Comparison with Comparable Systems

### 3.1 Comparable Systems Analyzed (Web Research 2026-10-20)

| System | Lexer/Parser/Compiler | Component Document | Key Pattern |
|--------|---------------------|-------------------|-------------|
| **Blade (Laravel)** | PHP-based, compiled to PHP | No separate document; `.blade.php` files | Directives as PHP, component classes |
| **Twig (Symfony)** | Lexer → Parser → Compiler (5 stages) | No separate document; `.twig` files | TokenStream → AST → ModuleNode → PHP |
| **Vue SFC** | Rust-based (Verter) or JS-based | No separate document; `.vue` files | Template/Script/Style blocks → AST → render function |
| **Sharp (.sp)** | Lexer → Parser → AST → CodeGen | No separate document; `.sp` files | DependencyGraph + AstValidator + OptimizePass + CodeGen |
| **Templane** | YAML schema + TIR (typed IR) | **Yes** — YAML-based schema document + TIR | Language-neutral protocol; type-safe templates |
| **JSXpression** | TSX parser → AST → validation → compile | No separate document; TSX source | Schema-validated sandboxed execution |

### 3.2 Key Insight: SuperPHP's Unique Position

SuperPHP is the **only** system in this comparison that:
1. Targets **PHP compilation** (like Blade/Twig) but with **semantic directives** (like Vue's `v-persist`)
2. Requires a **stable interchange contract** (component document) for third-party tools
3. Must be **OPcache-preloadable** (like ADR-010 requires)
4. Must integrate with a **custom DI container** (ADR-002 flat-array shape)

The "public, language-neutral component document" is SuperPHP's distinguishing feature — no comparable system has this. Templane comes closest with its YAML schema + TIR, but Templane is a type-safety protocol, not a template engine.

### 3.3 Templane as Closest Analog

Templane (SPEC.md, 2026-04-24) defines:
- A **YAML-based schema document format** describing template data shape
- A **typed intermediate representation (TIR)** that any output adapter can render
- A **type checker** that validates data against schemas
- A **conformance fixture suite** for compliance testing

**Comparison with SuperPHP:**
| Dimension | Templane | SuperPHP |
|-----------|----------|----------|
| Schema format | YAML | Not yet defined (research candidate) |
| IR format | TIR (JSON) | AST (PHP objects) |
| Type safety | Compile-time | Not specified |
| Output targets | HTML, YAML | PHP (OPcache-friendly) |
| Language neutrality | Yes (protocol) | Partially (component document is language-neutral, but compiler targets PHP) |

**Implication:** Templane's approach of defining a language-neutral schema + IR could inform SuperPHP's component document design, but SuperPHP's PHP-compilation requirement is fundamentally different from Templane's multi-target rendering.

---

## 4. Format-Neutral Information Model

### 4.1 The Core Design Question

Should the component document be:
- **JSON-first** (JSON only, YAML as optional convenience)
- **JSON + YAML from day one** (both first-class)

### 4.2 JSON-First Approach

**Advantages:**
- Single canonical serialization
- JSON Schema 2020-12 provides validation framework
- Wider tooling ecosystem (JSON parsers in every language)
- Simpler canonicalization (JSON has one representation per value)
- Media type: `application/schema+json` / `application/schema-instance+json`

**Disadvantages:**
- Less human-readable for large component documents
- No comments (JSON doesn't support comments)
- No multi-document streams
- Verbose for nested structures

### 4.3 JSON + YAML from Day One

**Advantages:**
- YAML is more human-readable (comments, anchors, multi-line strings)
- YAML 1.2.2 is a superset of JSON (JSON is valid YAML)
- Multi-document streams (YAML `---` separator) enable component collections
- RFC 9512 (YAML media type) provides IANA registration and interoperability guidance
- YAML anchors/aliases reduce repetition in component definitions

**Disadvantages:**
- YAML has non-JSON types (`!!timestamp`, `!!python/object`, `!date`) that break interoperability
- YAML scalar typing is ambiguous (e.g., `yes`/`no` as booleans in YAML 1.1)
- YAML anchors create cyclic references (not JSON-compatible)
- Multi-document streams require special handling
- Canonicalization is harder (YAML has multiple valid representations of the same data)
- Comments are lost on JSON round-trip

### 4.4 YAML Interoperability Issues (from RFC 9512)

Features that cause JSON interoperability problems:
1. **Multi-document streams** — JSON has no equivalent; applications must error or ignore extras
2. **Non-UTF-8 encoding** — JSON mandates UTF-8; YAML allows UTF-16/UTF-32
3. **Non-string mapping keys** — JSON requires string keys; YAML allows numbers, sequences, maps as keys
4. **Cyclic references** — YAML anchors can create cycles; JSON is a tree
5. **`.inf` and `.nan` float values** — JSON doesn't support them
6. **Non-JSON types** — YAML tags like `!!timestamp`, `!!python/object`, custom tags
7. **Tags in general** — custom/local tags like `!mytag`

### 4.5 JSON Schema 2020-12 Key Features (for Component Validation)

| Feature | Relevance to SuperPHP |
|---------|----------------------|
| **Redesigned array/tuple keywords** (`prefixItems` + `items`) | Component prop arrays with fixed + variable elements |
| **Dynamic references** (`$dynamicRef` / `$dynamicAnchor`) | Recursive component definitions (component contains itself) |
| **Embedded schemas / bundling** (`$id` in subschema) | Component document bundling (multiple components in one file) |
| **`$defs` (renamed from `definitions`)** | Reusable component prop type definitions |
| **Vocabulary changes** (format-annotation vs format-assertion) | Component prop format validation (email, uri, date) |
| **Unicode regex support** | Component name validation with Unicode |

### 4.6 Recommendation (For Owner Review — NOT a Decision)

**JSON-first with YAML as a supported authoring format**, using the following approach:
1. **Canonical serialization: JSON** — all tooling, validation, and interchange use JSON
2. **Authoring format: JSON or YAML** — YAML is accepted as input but normalized to JSON internally
3. **Validation: JSON Schema 2020-12** — schema validates the JSON-normalized document
4. **YAML features restricted** — only JSON-compatible YAML subset allowed (string keys, JSON types, no anchors/aliases, no multi-document, no custom tags)
5. **Canonicalization: JSON** — output is always canonical JSON; YAML input is round-tripped through JSON

This approach gets the human-readability benefits of YAML while maintaining a single canonical serialization and avoiding the interoperability pitfalls.

**Alternative:** JSON + YAML as equal first-class formats with a defined canonicalization spec. Higher complexity, but preserves YAML-specific features (comments, anchors) in round-tripping.

**Owner Decision Required:** Choose between JSON-first (simpler) and JSON+YAML-equal (more complex, more features).

---

## 5. Naming Collision Audit (Standalone Finding)

### Internal Collision Status (Verified 2026-10-20)

| Candidate | Internal Status | Evidence |
|-----------|----------------|----------|
| **Forge** | ❌ **COLLISION** | `CORE-20` = "Sovereign Forge" (CLI Toolchain). Namespace `SovereignStack\Forge`. `bin/forge` in `DGLAB-AS-OS.md`. Collision resolved 2026-08-12 via OD-05 (other contenders renamed). |
| **Foundry** | ✅ **Clean** | No matches in `Architecture/` or `docs/`. |
| **Nexus** | ❌ **COLLISION (2-way)** | `HUB-17` = "Sovereign Webhook Nexus", `HUB-21` = "Sovereign Nexus (Tenancy)". |
| **SPX** | ✅ **Clean** | No matches for `\bSPX\b` in `Architecture/` or `docs/`. |
| **Sovereign** | ❌ **MASSIVE** | Hub-tier naming prefix (26+ components). Project namespace `SovereignStack\`. |

### External Collision Status (Web Research 2026-10-20)

| Candidate | External Package | Registry | Risk Assessment |
|-----------|-----------------|----------|-----------------|
| **Foundry** | `coderstm/foundry` (SaaS application framework, Laravel-based, MIT license) | Packagist | **Medium** — Different domain (SaaS vs template engine). Vendor namespace `coderstm` ≠ `SovereignStack`. Composer vendor namespacing mitigates direct collision. Risk: developer confusion when searching. |
| **SPX** | `noisebynorthwest/php-spx` (PHP profiling extension with web UI) | Packagist | **Medium-High** — Same PHP ecosystem. Profiling extension vs template engine. Composer vendor namespacing mitigates direct collision, but name similarity could cause confusion in tutorials/docs. |
| **Forge** | `laravel/forge` (server management), `forge-php/forge` (code generation) | Packagist | **High** — Already internal collision; external reinforces rejection. |
| **Nexus** | `nexusphp/*` (PHP utility library) | Packagist | **High** — Already internal collision. |
| **Sovereign** | No direct external collision found | N/A | **Low external, but internal collision is disqualifying** |

### Packagist Namespace Protection (from Packagist docs)

- Vendor names are protected once a package is published
- Package names are `vendor/package` format
- No one can publish under an existing vendor name without maintainer permission
- This means `SovereignStack/foundry` and `coderstm/foundry` can coexist without technical collision
- However, **developer confusion** remains a risk (search, tutorials, Stack Overflow)

### Naming Philosophy Question (Unresolved)

Should the template engine be named for:
- **Function** (template, view, render, compile) — descriptive, clear purpose
- **Character** (forge, foundry, spx) — brand identity, memorable

This is a naming-philosophy question, not a collision question. The owner's directive (SAAI briefing §332) says "The owner has considered renaming SuperPHP but has not approved a replacement."

---

## 6. Research Questions (For Future Investigation)

1. **What are SuperPHP's actual responsibilities and architectural boundaries?** Until documented, a rename is premature — naming should follow function.
2. **Are there external collisions for Foundry/SPX on npm, GitHub org names, PHP-FIG proposals?** Only Packagist checked so far.
3. **What is the cost of renaming 100+ references to "SuperPHP" across `Architecture/`, `ADR-005`, `OPEN-DECISIONS.md`, and `CORE-07/11/12` blueprints?**
4. **Should the component document be JSON-first or JSON+YAML-equal?** (See §4)
5. **What existing systems (Templane, Sharp, Vue SFC) can inform the component document design?** (See §3)
6. **How should the component document handle versioning, extension rules, and conformance testing?**

---

## 7. Deliverables (Research Outputs)

| Deliverable | Status | Notes |
|-------------|--------|-------|
| Responsibilities and boundaries document | **§2 of this report** | Covers Lexer/Parser/Compiler seams |
| External collision audit | **§5 of this report** | Packagist checked; npm/GitHub pending |
| Rename-cost estimate | **Not yet produced** | Requires grep across all `Architecture/` and `packages/` |
| Naming-philosophy note | **§5 of this report** | Function vs character naming |
| Format-neutral information model analysis | **§4 of this report** | JSON-first vs JSON+YAML |
| Component document schema (JSON Schema 2020-12) | **Not yet produced** | Research candidate, not approved |
| Conformance fixture suite | **Not yet produced** | Research candidate |

---

## 8. Out of Scope (Per RESEARCH-AGENDA.md)

- Any rename of SuperPHP. **Not authorized.**
- Any amendment to ADR-005. **Not authorized.**
- Any new blueprint, namespace, or package rename. **Not authorized.**
- Implementation of the SuperPHP runtime. **Not authorized.**
- Creation of `Architecture/CrossCutting/RESEARCH-AGENDA.md`. **Not authorized.**

---

## 9. Related Doctrines and Contracts

- `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md` — ratified SuperPHP over Blade/Twig. Any rename requires amending this ADR.
- `Architecture/OPEN-DECISIONS.md` §OD-09 — SCSS framework authoring model mentions SuperPHP. Verified: OD-09 is scoped to SCSS, not naming.
- `Architecture/INDEX.md` §1, §2.3 — canonical component-name registry and collision target.
- `Architecture/CrossCutting/DGLAB-AS-OS.md` — uses `bin/forge` and "Sovereign Forge" terminology.
- `Architecture/CrossCutting/GLOSSARY.md` — defines "Sovereign Forge" as codename for CORE-20.
- `Architecture/Core/CORE-07.md`, `CORE-11.md`, `CORE-12.md` — SuperPHP blueprints (all `📝 Not started`).

---

**End of SPX report.** This is a research deliverable. It does not authorize any rename, ADR amendment, or implementation. All findings are labeled with verification status. Owner authorization is required for any action beyond research.