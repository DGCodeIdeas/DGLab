# ADR-005: Build SuperPHP (Custom Lexer + Parser + Compiler) over Blade / Twig / Plates

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

The Sovereign Stack's three Core-tier blueprints CORE-07 (SuperPHP Lexer), CORE-11 (SuperPHP Parser), and CORE-12 (SuperPHP Compiler) define a purpose-built template language that compiles `.super.php` files to native PHP. The language introduces first-class directives (`@global`, `@persist`, `~setup`), a custom component tag syntax (`<s:ui:button />`), and a "Reactive Bridge" that injects `s-data` JSON attributes into the rendered HTML so the frontend SPA runtime can hydrate server-rendered state. This is a deliberate departure from Blade, Twig, and Plates.

Finding 19 in `00_CRITIQUE.md` flags "Why SuperPHP (a custom template language) over Blade/Twig/Plates" as undocumented. The three blueprints (CORE-07/11/12) assume the decision is already made — CORE-07 opens with *"The first stage of the SuperPHP engine"* with no preamble justifying the engine's existence — but Finding 19 is correct that the *rationale* lives nowhere in the repo. This ADR records that rationale.

Three forces shaped the decision. First, the Sovereign Stack's stated design principle is "node-free": the asset pipeline must not require Node.js, npm, webpack, Vite, or any JavaScript build step. Server-rendered HTML is the source of truth; reactivity is layered on via the `s-data` attribute bridge. Every off-the-shelf PHP templating engine was designed *before* the reactive-HTML pattern, and grafting reactive-bridge semantics onto them would require monkey-patching their compilers. Second, SuperPHP's directives are *semantic* — `@persist` means "serialize this variable into the `s-data` attribute on the rendered component" — and they need AST-level support, not preprocessor-style string substitution. Third, the compiled output must be OPcache-friendly: flat PHP arrays, no `eval()`, no string-built closures, so it preloads cleanly under ADR-010.

## Decision

We build **SuperPHP** as a custom three-stage template engine: a regex-and-DFA-based lexer (CORE-07) producing a `TokenStream`, a recursive-descent parser (CORE-11) producing an AST of `ComponentNode`/`SetupNode`/`DirectiveNode`/`RawHtmlNode`, and a compiler (CORE-12) that walks the AST and emits native PHP with the Reactive Bridge injected. The compiler caches output to `storage/framework/views` keyed by source-file checksum; only changed files are recompiled. The generated PHP is what OPcache preloads (see ADR-010). Blade, Twig, and Plates are explicitly rejected as the primary template engine. Interop with legacy templates is one-way and at the HTML layer — any Blade/Twig output that produces HTML can be wrapped in a SuperPHP component — not at the template-syntax layer.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **Blade** (Laravel's templating engine, `illuminate/view`) | Mature (10+ years); huge ecosystem of community extensions; `@extends`, `@section`, `@component` directives are well-understood; Laravel developers onboard instantly | Tied to `illuminate/view` and transitively to `illuminate/container`, `illuminate/events`, `illuminate/filesystem` — violates ADR-002's "no framework" principle; Blade's directives are preprocessor-style string substitution, not AST-level, so `@persist` cannot mean "serialize to `s-data`" without forking Blade's compiler; Blade's `@component` syntax does not support `<s:ui:button />` XML-style component tags | Rejected — framework coupling and preprocessor limitations |
| **Twig 3.x** (`twig/twig`) | Sandboxed mode; template inheritance; mature expression language; decoupled from any framework (standalone) | Template syntax (`{{ }}`, `{% %}`) is a learning curve for PHP developers; extending Twig with a custom directive requires a `Twig\Extension\AbstractExtension` plus a `Twig_TokenParser` — far more work than adding a directive to a custom parser; the Reactive Bridge would need a `Twig\Node` subclass — invasive; compiled output interleaves `echo` with control flow, harder to OPcache-preload | Rejected — extension model too heavy; syntax divergence from PHP is unnecessary friction |
| **Plates 4.x** (`league/plates`) | Native PHP templates (no new syntax); PSR-7 compatible; lightweight; template inheritance via `layout()` calls | No compiler step — Plates evaluates PHP files directly, so there is no AST to transform and no place to inject the Reactive Bridge; `@persist` semantics cannot be expressed without a convention-based helper call inside the template (fragile and easy to forget); no compiled cache, no AST-level optimization pass | Rejected — no compilation step means no Reactive Bridge |
| **Latte** (`latte/latte`) | Mature; sandboxed; context-aware escaping; compile-to-PHP; well-documented extension API | Tied to Nette ecosystem (`nette/utils` transitive); context-aware escaping overlaps with `s-data` JSON serialization; `<s:ui:button />` syntax still needs a custom macro | Rejected — Nette coupling is the same anti-pattern as Laravel coupling |
| **Pure PHP (no template engine)** | Zero abstraction; maximum OPcache friendliness; no new syntax | Loses all template ergonomics (component composition, directive-based control flow, layout inheritance); the entire reason a template engine exists is to make server-rendered HTML maintainable; pure PHP requires discipline that doesn't scale to a 30-blueprint ecosystem | Rejected — would force every Hub and Spoke developer to invent their own conventions |

## Consequences

**Positive:**
- Total control over the compiled output shape: the SuperPHP compiler can emit flat-array service lookups (matching ADR-002's container shape), explicit `s-data` JSON attribute injection (matching the Reactive Bridge contract), and OPcache-preloadable PHP (matching ADR-010). No third-party template engine gives us this level of control.
- The directive set is purpose-built for the Sovereign Stack's reactive-first server rendering. `@persist` is a first-class concept that maps to a specific runtime behavior, not a convention layered on top of an engine that doesn't know about it.
- No new runtime dependencies on the Core tier. CORE-07/11/12 depend only on `php: ^8.3` and the PHP `pcres` extension. The Composer dependency graph for the entire Sovereign Stack stays minimal.

**Negative:**
- We must build and maintain a full lexer, parser, and compiler — three Core-tier components with non-trivial test surfaces. CORE-07 specifies "100% coverage on a suite of complex SuperPHP syntax samples"; this is a serious testing commitment. Finding 8 in `00_CRITIQUE.md` notes that all three of CORE-07, CORE-11, CORE-12 are currently `📝 Not started`.
- No community extensions: every directive we add (`@persist`, `@global`, `~setup`, `@if`, `@foreach`) is one we maintain. The Twig ecosystem ships dozens of extensions (Markdown, Intl, Cache, etc.); we will have to write our own equivalents.
- The onboarding cost for new Sovereign Stack developers is real: they must learn SuperPHP syntax before they can build a Spoke. Mitigation: the language is intentionally small (single-pass lexer, recursive-descent parser, bounded directive set) and the AST node types are listed in CORE-11.

**Neutral:**
- The `storage/framework/views/` cache directory must be writable by the PHP-FPM process and cleared on every deploy. This is a standard pattern (Laravel does the same) but it must be documented in DEPLOY-01.
- The compiler's output format (a PHP file per template) must be versioned: changing the emitted PHP shape is a SemVer major for CORE-12 because downstream tools (e.g. a future debug bar that reads compiled templates) will depend on it.

## Links
- Related ADRs: ADR-002 (custom container — same "build over adopt" philosophy), ADR-010 (OPcache preload — SuperPHP compiler output must be preload-friendly), ADR-001 (polyrepo — CORE-07/11/12 are three separate repos)
- Related blueprints: CORE-07 (SuperPHP Lexer), CORE-11 (SuperPHP Parser), CORE-12 (SuperPHP Compiler), CORE-18 (Core Kernel — bootstraps the template engine)
- Related findings: Finding 19 (no ADRs existed), Finding 8 (Core tier has multiple stubs, including all three SuperPHP components)
- External references: Nygard ADR template; Twig documentation (twig.symfony.com); Blade documentation (laravel.com/docs/blade); Plates documentation (platesphp.com); React Server Components spec (react.dev/reference/rsc/server-components — the inspiration for the Reactive Bridge pattern)
