# SPX / SuperPHP — Ease-of-Use & Human/AI Comprehension Research

**Created:** 2026-10-20
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR, NOT an authorization to change ADR-005 or blueprints
**Parent agenda:** `RESEARCH-AGENDA.md` (root) — SuperPHP Naming Investigation section
**Related:** `SPX-NAMING-COLLISION-REPORT.md`, `RESEARCH-INDEX.md`, `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md`
**Research question (owner-supplied requirement):** SPX/SuperPHP must be **easier than PHP** and **understandable by both humans and various AIs with varying knowledge levels**.

---

## 1. Executive Summary

The owner requirement is that SuperPHP's authoring surface must be *easier than PHP* and *comprehensible across a spectrum of human skill (non-developer designers through senior engineers) and AI skill (small/weak models through frontier models)*. This is a **dual-audience design constraint**: the syntax must simultaneously reduce human cognitive load and be reliably generable/parseable by LLMs of widely differing capability.

Key findings from the web research:

1. **There is direct empirical support that "LLM-friendly" design and "human-easy" design overlap but are not identical.** Research on *AI-oriented grammar* (Sun et al., 2024) shows that human-centric notations (intuitive operators, formatting) are *unnecessary for LLMs*, and removing them cuts tokens 8.6–34.7% with equivalent or better accuracy — but this is an *efficiency* optimization, not necessarily a *comprehension* improvement, and it can hurt human readability.
2. **DSLs built around verbose English keywords, one canonical form, named intermediate results, and explicit step scaffolding measurably reduce LLM error rates.** Anka (arXiv 2512.23214) reports 99.9% parse success on a never-before-seen DSL from prompt alone and 100% vs 60% accuracy on multi-step pipelines vs Python. These principles are directly transferable to SuperPHP.
3. **Template engines that minimize delimiter confusion and boilerplate (Smarty, Latte, Twig) have long argued they are "easier than PHP" for non-developers.** Smarty's comparison (smarty.net) demonstrates PHP uses 5 punctuation chars where Smarty uses 2; Latte argues "you already know the syntax" because it reuses PHP expressions; Twig argues PHP is "ridiculously verbose" for escaping.
4. **A new class of "LLM-native markup languages" (LLMON, POML) explicitly designs for *both* human and LLM audiences via two equivalent syntaxes (succinct human form + verbose machine form) connected by converters.** This two-form pattern is the strongest precedent for SuperPHP's "easier than PHP for humans, reliable for AIs" requirement.
5. **Format-choice evidence (TOON benchmarks) shows LLM comprehension accuracy varies dramatically by format and model size** (e.g., 98.1% XML vs 55% JSON-compact for one model; 0% TOON on tiny models). This cautions against assuming any single syntax is "AI-friendly" for *all* models — SuperPHP must be tested across model tiers, not just frontier models.
6. **Markdown/YAML-based template languages (YTE, Pandoc templates, YAML frontmatter) demonstrate that indentation/whitespace structure and human-readable data serialization can serve as the authoring surface**, but introduce scalar-typing and whitespace hazards (YAML 1.2 spec; RFC 9512).
7. **Pedagogical language design research (Kölling, Wirth lineage) gives concrete principles for varying expertise: small core, keyword-based syntax, orthogonality, less redundancy, awareness that most users will not become professionals.** These map onto "understandable by humans with varying knowledge levels."

Implication: SuperPHP should be designed as a **small, regular, keyword-verbose, single-canonical-form DSL** with **PHP-familiar expressions** (Latte's strategy) and **an optional machine-oriented serialization** (LLMON's two-form strategy), validated by **comprehension benchmarks across model tiers and human skill levels** — not assumed from aesthetics.

---

## 2. Requirement Decomposition

The owner requirement splits into three testable sub-requirements:

| # | Sub-requirement | Audience | Verification approach |
|---|-----------------|----------|----------------------|
| R1 | Easier than PHP (less boilerplate, fewer punctuation chars, no `<?php` tag confusion with HTML) | Human designers/non-devs; junior devs | SUS/usability studies; task-completion time; error rate |
| R2 | Understandable by humans with varying knowledge levels (novice → expert) | All human skill tiers | Kölling/Wirth principles; progressive disclosure; docs |
| R3 | Understandable by various AIs with varying knowledge levels (small → frontier models) | LLMs of differing capability | Comprehension/generation benchmarks across model tiers (Anka-style; TOON-style) |

These three are **not automatically co-satisfiable** — R1's "fewer tokens" can conflict with R3's "verbose keywords help weak models." The design must make explicit trade-offs (see §7).

---

## 3. Findings by Research Question

### 3.1 Template Languages Designed for AI/LLM Comprehension

**LLMON (arXiv 2603.22519, v3, 2026-03-30)** — "An LLM-native Markup Language to Leverage Structure and Semantics at the LLM Interface"
- URL: https://arxiv.org/html/2603.22519v3
- Proposes a markup language with **two equivalent syntaxes**: a human-friendly "Shorthand" and a more verbose "Machine LLMON" sent to the tokenizer, with converters between them.
- Design requirements include explicit tag naming, hierarchical structure in tag names, and parsimonious closing tags (contrasting XML's mandatory close tags with YAML's indentation push/pop).
- Directly relevant: this is the closest published precedent for "human-easy AND AI-reliable" via dual forms. SuperPHP could adopt the same pattern (authoring syntax ↔ a canonical machine representation).

**POML (Microsoft, arXiv 2508.13948, 2025-08-19)** — "Prompt Orchestration Markup Language"
- URL: https://github.com/microsoft/poml ; https://www.alphaxiv.org/overview/2508.13948
- HTML-like semantic components (`<role>`, `<task>`, `<example>`), CSS-like styling decoupled from content, built-in templating (`{{ }}`, `for`, `if`, `<let>`), and an IDE toolchain (syntax highlighting, autocomplete, live preview, inline diagnostics).
- Relevant: demonstrates that an HTML-like, semantic-tag template language with tooling is a viable "AI-friendly" surface; the IDE/tooling investment is part of what makes it usable across skill levels.

**Anka (arXiv 2512.23214)** — "A Domain-Specific Language for Reliable LLM Code Generation"
- URL: https://arxiv.org/html/2512.23214
- Four principles, each tied to an observed LLM error pattern:
  1. **One Canonical Form** (eliminates decision points; 3^5 → 1 programs for a 5-step pipeline)
  2. **Named Intermediate Results** (explicit state)
  3. **Explicit Step Structure** (STEP blocks as scaffolding)
  4. **Verbose Keywords** (English keywords over operators; leverages LLM natural-language strength)
- Results: Claude 3.5 Haiku achieved 99.9% parse success on a never-seen DSL from prompt alone; 100% vs 60% accuracy on multi-step pipelines vs Python.
- Relevant: SuperPHP's directives (`@if`, `@foreach`, `@persist`) should be **verbose, keyword-based, single-canonical-form**, with named intermediates and explicit blocks — not terse operator syntax.

**MoonBit (LLM4Code '24, 2024-04)** — "Explore the Design of an AI-Friendly Programming Language"
- URL: https://www.computer.org/csdl/proceedings-article/llm4code/2024/057900a079/21sMUVfh3b2
- AI integration, synergy of traditional code intelligence + LLM, real-time semantics-based sampler to guide inference so output is syntactically correct and type-error-free with minimal performance impact.
- Relevant: a *semantics-based sampler/constraint* during generation (like SynCode) is a viable path to make SuperPHP reliably generable by weaker models without changing the language.

**Pel (arXiv 2505.13453, 2025-06-09)** — LLM orchestration language
- URL: https://arxiv.org/pdf/2505.13453v2
- Minimal Lisp-like S-expression grammar; uniform syntax; "inherently LLM-friendly"; grammar-level capability control instead of sandboxing.
- Relevant: regularity/uniformity aids in-context learning; but S-expressions are *not* human-friendly for designers — SuperPHP should keep HTML-like surface, not Lisp-like.

**SynthFlow / Moss / AiLang** — AI-native languages (GitHub, 2026)
- SynthFlow: clean Python/TypeScript-like syntax, native AI integration. URL: https://github.com/WEE-TECHNOLOGY-SOLUTION/SynthFlow
- Moss: explainable, long-lived human+AI codebases; explicit effects; self-hosting; AI-built. URL: https://github.com/Fujo930/moss-lang
- AiLang: deterministic, canonical formatting, structured syntax, reproducible builds. URL: https://dev.to/todd_henderson_e43e8836a7/ailang-exploring-deterministic-ai-first-programming-with-gemma-4-135i
- Relevant: "canonical formatting" + "explicit semantics" + "reproducibility" recur as AI-friendly traits; Moss's emphasis on *explainability for long-lived human/AI collaboration* matches SuperPHP's component-document interchange goal.

**AI-oriented grammar / SimPy (Sun et al., arXiv 2404.16333, 2024-04-25)**
- URL: https://arxiv.org/abs/2404.16333v1
- Proposes grammar designed for AI *instead of* humans: discard formatting tokens, minimize tokens; "DualCode" converts between human-centric and AI-oriented grammar sharing the same AST.
- SimPy reduced tokens 8.6–34.7% (13.5% / 10.4% for CodeLlama / GPT-4) with equivalent or improved accuracy.
- Relevant: the **DualCode conversion idea** is directly applicable — SuperPHP could keep a human-friendly authoring syntax and a machine-oriented canonical form sharing one AST, exactly matching the component-document interchange contract in the SPX report §2.1.

### 3.2 Simplified PHP Alternatives / PHP-Adjacent Template Syntax for Non-Experts

**Smarty (smarty.net)** — "Template Syntax: Smarty vs PHP"
- URL: https://www.smarty.net/syntax_comparison
- Argues PHP's terse statement syntax embedded in HTML is "difficult to manage"; Smarty uses 2 punctuation chars vs PHP's 5 for a variable; "No PHP knowledge is required to manage Smarty templates"; "simplified, English-reading logic statements"; boilerplate handled (e.g., `empty()` test); left-to-right filter flow `{$foo|lower|escape}`.
- Relevance: the canonical "easier than PHP for non-developers" argument; delimiter confusion (`<?php` vs HTML `<>`) is a documented pain point SuperPHP must avoid.

**Latte (nette.org)** — "the safest & truly intuitive templates for PHP"
- URL: https://latte.nette.org/en/ ; https://github.com/nette/latte
- Core claim: "You already know the syntax" — expressions are written *exactly* like PHP, so no second language to learn; one `{...}` delimiter (no `{% %}` vs `{{ }}` split); n:attributes for compact HTML-attribute control flow.
- Relevance: **Latte's strategy (reuse PHP expressions verbatim) directly serves "easier than PHP" for PHP developers and "understandable by AIs trained on PHP"** — models already know PHP. This is a strong precedent for SuperPHP keeping PHP expression syntax rather than inventing a new expression language (Twig's Python-like syntax is the counter-example).

**Twig (twig.symfony.com)** — "What makes Twig better than PHP as a template engine?"
- URL: https://twig.symfony.com/
- Argues PHP is "verbose and becomes ridiculously verbose when it comes to output escaping" (`htmlspecialchars(...)` vs `{{ var|escape }}`); template-oriented shortcuts (default text on empty loops); clean error messages with filename/line number.
- Relevance: escaping verbosity is a real PHP pain point; SuperPHP should make safe output the default (Twig/Smarty do) and provide clean errors.

**CodeIgniter alternate PHP syntax** (codeigniter.com)
- URL: https://codeigniter.com/user_guide//outgoing/alternative_php.html
- Recommends PHP's built-in alternative control-structure syntax (`foreach (): ... endforeach;`) to minimize braces and `echo`, for view files without a template engine.
- Relevance: shows the "minimum viable" simplification of PHP for templates; SuperPHP should beat this baseline in punctuation count and clarity.

**EasyTpl / simple-template-engine** (GitHub)
- URL: https://github.com/phppkg/easytpl ; https://github.com/ddycai/simple-template-engine
- EasyTpl: "No learning costs, syntax like PHP template"; auto-append `$`; chained array access `{{ $map.user.name }}`; filters; auto-escaping by default.
- Relevance: confirms the design space of "PHP-like but safer/conciser" micro-engines; auto-escaping and auto-`$` are small ergonomic wins.

**Handlebars / Liquid (logic-less)**
- URL: https://github.com/salesforce/handlebars-php ; https://github.com/sanko/template-liquid
- Liquid's stated requirements: "simple markup and beautiful results"; "non-evaling and secure" (users can edit templates safely); "stateless"; excellent for customer-facing themes.
- Relevance: if SuperPHP templates are ever authored by non-trusted users (themes/plugins), a logic-less/sandboxed subset is needed; ADR-005 already rejects third-party engines, so sandboxing must be built in.

**Blade (Laravel)**
- URL: cited in ADR-005 and https://distantjob.com/blog/twig-vs-blade (2025-06-13)
- "thin layer of syntactic sugar over PHP without adding a large abstraction layer"; beginners can fall back to raw PHP; tight framework coupling.
- Relevance: Blade's "fall back to PHP" property lowers the barrier for PHP developers; SuperPHP should preserve an escape hatch to raw PHP (as EasyTpl/Blade do) to avoid blocking experts.

### 3.3 Human-AI Collaborative Programming Languages & Syntax Design

**Moss (github.com/Fujo930/moss-lang, 2026-06, v0.5.0)**
- "AI-built programming language for explainable, long-lived human and AI software projects"; self-hosting; explicit effects; project-wide static checks; LSP; deterministic module graph exposed "for humans, CI, and AI agents."
- Relevance: SuperPHP's component document is itself an interchange contract for tools/AIs — Moss's "expose deterministic structure to humans, CI, and AI agents" is a matching philosophy.

**DualCode (Sun et al., 2024, arXiv 2404.16333)**
- Converts between human-centric and AI-oriented grammar sharing one AST; "ensure the changes remain imperceptible for human developers."
- Relevance: strongest precedent for SuperPHP maintaining **one AST** with **multiple surface syntaxes** (authoring syntax → syntax driver → component document), which the SPX report §2.1 already proposes.

**Educational language design (Kölling, "Principles of Educational Programming Language Design", 2024-12-11)**
- URL: https://infedu.vu.lt/journal/INFEDU/article/797/file/pdf
- Wirth lineage principles: simplicity, modularity, orthogonality, readability; keyword-based syntax; pedagogical languages are smaller, less redundant; "most users aim at acquiring a general understanding… will most likely not become software professionals"; warns against teaching more syntax without deeper understanding.
- Relevance: directly addresses "understandable by humans with varying knowledge levels" — keep the core small, keyword-based, orthogonal, with progressive disclosure of advanced features.

**Wirth's principles (via Kölling)**: simplicity, modularity, orthogonality, readability — keyword-based syntax aids human readers.

### 3.4 Template Engine Accessibility & Ease-of-Use Research

**W3C template-aware accessibility metric (w3.org/WAI/RD/2011/metrics/paper3)**
- Templates account for 40–50% of web content; accessibility problems in a template multiply across all pages using it; fixing template-level issues yields 17–39% reduction in reported problems.
- Relevance: SuperPHP's component tags (`<s:ui:button />`) make accessibility a *component-level* property — a strong argument for component primitives with built-in a11y defaults, and for auditing generated HTML.

**SUS (System Usability Scale) (usertesting.com)**
- Standard 10-item usability metric; distinguishes easy/hard-to-use systems; comparable across samples.
- Relevance: SuperPHP should be evaluated with SUS across skill tiers (designer, junior dev, senior dev, non-dev) rather than assumed easy.

**FreeMarker (freemarker.apache.org)**
- "A simple, specialized language (not a full-blown programming language like PHP)"; separates data preparation (Java) from presentation (templates); designers won't face complicated logic.
- Relevance: the "not a full programming language" positioning is exactly the "easier than PHP" claim; SuperPHP should keep logic minimal in templates (push logic to `~setup`/PHP).

**Play/Twirl (playframework.com)**
- "compact, expressive, and fluid"; minimizes keystrokes; "not a new language" — uses existing Scala skills; smart parser infers code blocks from `@`.
- Relevance: "not a new language" (Latte's claim too) is a proven onboarding strategy; SuperPHP's expressions should remain PHP so AI models and PHP devs transfer skills.

### 3.5 LLM-Friendly Programming Language Design Principles

**Anka principles (§3.1)** — canonical form, named intermediates, explicit steps, verbose keywords.

**AI-oriented grammar (Sun et al.)** — minimal tokens, discard formatting, DualCode conversion.

**MoonBit** — semantics-based sampler for syntactic/semantic correctness during generation.

**SynCode (Ugare et al., arXiv 2403.01632, 2024-04-30; TMLR 05/2025)**
- URL: https://arxiv.org/pdf/2403.01632v4
- Grammar-augmented decoding imposes CFG constraints during generation; eliminated JSON syntax errors; reduced Python/Go syntax errors 96.07%.
- Relevance: even with a well-designed syntax, **constrained decoding** is needed for reliable generation by weaker models; SuperPHP should ship a formal grammar (EBNF) usable by SynCode-like tools.

**PAR (PARSE, EMNLP Industry 2025)** — schema optimization for LLM consumption; treats schemas as optimizable contracts.
- URL: https://aclanthology.org/anthology-files/pdf/emnlp/2025.emnlp-industry.184.pdf
- Relevance: SuperPHP's component document (JSON Schema 2020-12 candidate per SPX report) should be **optimized for LLM consumption**, not just human authors.

**PyLang (arXiv 2605.15607)** — synthetic language to study syntax acquisition vs semantic competence; finds bottleneck is implementation fidelity, not reasoning transfer.
- URL: https://arxiv.org/html/2605.15607
- Relevance: cautions that even with good syntax, models fail on *low-level implementation details*; SuperPHP's small, regular surface helps, but tests must check end-to-end generation, not just parsing.

### 3.6 "AI-Friendly Syntax" / "LLM-Friendly Template" Research

- **LLM-friendly docs (Fern, 2026-03)** — serving markdown to agents reduces token use 80–90%; keep docs accurate. URL: https://buildwithfern.com/post/how-to-write-llm-friendly-documentation
- **Markup formatting in LLMs (Medium, 2024-10-12)** — markdown/HTML markup in prompts; practical guidance. URL: https://medium.com/@SimplifyingFutureTech/markup-formatting-in-llm-77eaa3e08e9d
- **XML-structured prompts (IPCCC 2025)** — XSP reduces syntax errors up to 60% and logic errors up to 30% across 30 models (0.5B–671B). URL: https://www.computer.org/csdl/proceedings-article/ipccc/2025/11304661/2cQfEbIi2zK
  - Relevance: structured, explicit markup (XML-like) aids comprehension across model sizes — supports SuperPHP's explicit, tagged component syntax over implicit/whitespace structure for the AI audience, while keeping a human shorthand.

### 3.7 Twig / Blade / Liquid Simplicity Comparisons

- **ResearchGate: "Comparison of template engines of PHP frameworks" (2022)** — Blade vs Twig study; Twig and Blade are the most-used PHP engines; Blade is ~5x faster than Twig; Twig handles sandboxing, Blade exposes raw PHP. URL: https://www.researchgate.net/publication/384724044_Comparison_of_template_engines_of_PHP_frameworks
- **DistantJob "Twig vs Blade 2025" (2025-06-13)** — Blade easier for Laravel devs; Twig moderate learning curve; Blade = thin sugar over PHP; Twig = DSL. URL: https://distantjob.com/blog/twig-vs-blade
- **SitePoint "Twig — the Most Popular Stand-Alone PHP Template Engine" (2017-07-30)** — Twig concise; `{{ name }}` vs `<?php echo ... ?>`. URL: https://www.sitepoint.com/twig-popular-stand-alone-php-template-engine
- **GeeksforGeeks "Comparing Jinja to Other Templating Engines" (2023-10-16)** — Jinja/Twig/Handlebars/Mustache syntax comparison. URL: https://www.geeksforgeeks.org/python/comparing-jinja-to-other-templating-engines
- **Cortance "Twig vs Liquid"** — Liquid prioritizes safety/non-eval for user-editable themes; Twig richer logic. URL: https://cortance.com/answers/twig/twig-vs-liquid-which-is-better-for-theme-development
- **Latte vs Twig (latte.nette.org)** — Latte based on PHP, Twig on Python; designers in Latte don't switch conventions; one delimiter. URL: https://latte.nette.org/en/

Synthesis: **Latte's "reuse PHP expressions" wins on onboarding for PHP devs and AI models trained on PHP; Twig's conciseness wins on escaping/boilerplate; Liquid wins on safety for untrusted authors.** SuperPHP can combine: PHP expressions (Latte) + safe-by-default escaping (Twig) + sandboxed subset (Liquid) + component tags (POML/LLMON-style explicit structure).

### 3.8 Markdown-Based or YAML-Based Template Languages

- **YTE — YAML Template Engine (github.com/yte-template-engine/yte)** — uses YAML structure for control flow (conditionals/loops via `?` expressions); templates remain valid YAML; rationale: exploit YAML structure to reduce special chars and improve human readability; whitespace handling becomes unnecessary.
  - URL: https://github.com/yte-template-engine/yte
- **Pandoc templates (Stack Overflow, 2014-05-09)** — markdown-to-markdown templating with `$samples.name$` / `$endfor$`; Haskell-based programmatic generation alternative. URL: https://stackoverflow.com/questions/23568628/template-language-for-markdown-to-markdown
- **YAML spec 1.2.2 (yaml.org)** — "human-friendly, cross language, Unicode based data serialization"; indentation-based scoping (Python-like); core type system; recommends JSON Schema as lowest common denominator; warns about scalar typing. URL: https://yaml.org/spec/current.html
- **YAML RFC 9512** — documents JSON/YAML interoperability issues (per RESEARCH-INDEX E9).
- **Markdown frontmatter / R-markdown (cran.r-project.org, yamlme)** — YAML headers in markdown documents. URL: https://cran.r-project.org/web/packages/yamlme/vignettes/yamlme-intro.html
- **GitHub issue forms** — YAML form definitions with markdown bodies. URL: https://docs.github.com/.../syntax-for-issue-forms

Relevance: Markdown/YAML surfaces are **human-friendly and LLM-familiar** (models are heavily trained on markdown and YAML), but YAML's indentation/whitespace hazards and scalar typing (the `no`/`yes` boolean trap, etc.) are documented risks. SuperPHP's component document (JSON-first vs JSON+YAML candidate per SPX report §2.1/R3) should weigh this: **Markdown for prose/docs, YAML for human-edited config, JSON for canonical machine representation**, consistent with LLMON's dual-form approach.

### 3.9 Design Principles for Languages Understandable by Varying Expertise Levels

- **Kölling (2024)** — small core, keyword syntax, orthogonality, progressive disclosure, awareness most users won't be professionals.
- **Wirth lineage** — simplicity, modularity, orthogonality, readability.
- **Berners-Lee design principles (w3.org/DesignIssues/Principles)** — "Keep it simple, stupid"; simplicity ≠ easy to understand; modularity enables independent change; decentralization/tolerance.
  - URL: https://www.w3.org/DesignIssues/Principles
- **Kolibri Design System principles** — "easy to use for people of any experience level"; "introduce each new experience in a clear way that leaves no room for guesswork"; "design helpful defaults but allow customization."
  - URL: https://design-system.learningequality.org/principles
- **Windows 11 design principles** — "Familiar: no learning curve; pick it up and go"; "Effortless"; "Calm."
  - URL: https://learn.microsoft.com/en-us/windows/apps/design/design-principles
- **Anka** — explicit scaffolding (STEP blocks) helps *both* humans and models follow sequential logic.
- **POML** — IDE tooling (highlighting, autocomplete, live preview, diagnostics) is treated as part of the language's accessibility.

Synthesis for varying expertise: **familiarity (reuse PHP), small core with progressive disclosure, helpful defaults, clear errors, good tooling, and explicit structure** (not clever/terse syntax).

---

## 4. TOON Benchmark Evidence (Format Comprehension Across Model Tiers)

Source: TOON repo (github.com/bast-ar/tolon) benchmarks; Medium analysis (2025-12-12, https://medium.com/@michael.hannecke/beyond-json-picking-the-right-format-for-llm-pipelines-b65f15f77f7d)

Key data points:
- Comprehension accuracy varies sharply by **format × model**:
  - gemini-3-flash-preview: XML 98.1%, JSON 97.1%, YAML 97.1%, TOON 96.7%, JSON-compact 96.7%
  - gpt-5-nano: TOON 90.9%, JSON 89.0%, YAML 87.1%, XML 80.9%
  - grok-4-1-fast-non-reasoning: TOON 58.4%, YAML 57.9%, JSON 56.5%, XML 54.1%, JSON-compact 52.2%
  - claude-opus-4-5: CSV 100% (4/4), XML 100%, TOON 0%, JSON-pretty 0%, YAML 0%, JSON-compact 0% on a small set (likely a parsing quirk)
- XML has worst token efficiency (+14% vs formatted JSON, +114% vs TOON) but high comprehension; every open tag needs a close tag (doubling structural overhead).
- TOON forced declaring all fields upfront with null gaps — token count ballooned past JSON for sparse data.
- Deeply nested configs → stick with JSON (minified) or YAML.
- Field ordering affects accuracy (reasoning before answer enforces chain-of-thought).
- XML is recommended *only* for prompt templates humans edit/maintain (self-describing mixed content); for data interchange, JSON/TOON by shape.

Implication for SuperPHP: **"AI-friendly" is not a single property.** SuperPHP must be benchmarked like TOON — comprehension + generation accuracy across **multiple model tiers** (small/weak, mid, frontier) and **multiple task types** (read, generate, modify, explain). Do not assume a syntax that helps frontier models helps weak ones (weak models in the TOON data sometimes score ~55% on everything).

---

## 5. Cross-Cutting Synthesis: Implications for SuperPHP

### 5.1 Recommended design posture (proposed, owner decision required)

1. **Keep PHP expressions verbatim** (Latte strategy) — models and PHP devs already know them; don't invent a new expression language.
2. **Verbose, keyword-based, single-canonical-form directives** (Anka) — `@if`/`@foreach`/`@persist`/`@global`/`~setup`, one way to write each; no operator shorthand.
3. **Safe-by-default output escaping** (Twig/Smarty) — `{{ $x }}` escapes; raw output explicit (`{!! !!}` or similar, decided later).
4. **Explicit, tagged component structure** (POML/LLMON/XML-structured prompts) — `<s:ui:button />` style tags are self-describing and aid weak models; avoid implicit/whitespace structure for control flow.
5. **Small core + progressive disclosure** (Kölling/Wirth) — minimal directive set in v1; advanced features (macros, inheritance) added deliberately.
6. **Dual surface: human shorthand ↔ machine canonical form** (LLMON/DualCode) — authoring syntax stays terse for humans; a canonical, more verbose/regular form is what tools and constrained decoders consume; converters between them; both share one AST/component document.
7. **Ship a formal grammar (EBNF)** (SynCode) — enables constrained decoding so weaker models generate valid SuperPHP.
8. **Optimize the component document for LLM consumption** (PARSE) — descriptions, clear field names, example pairs.
9. **Built-in tooling** (POML) — highlighting, autocomplete, live preview, diagnostics are part of accessibility, not optional.
10. **Sandboxed subset** (Liquid) — for untrusted template authors (themes/plugins).
11. **Evaluate with SUS + comprehension benchmarks** across human skill tiers and model tiers before claiming "easier than PHP."

### 5.2 Tensions to resolve (owner decision required)

| Tension | Human-easy side | AI-reliable side | Proposed resolution |
|---------|-----------------|------------------|---------------------|
| Verbosity | Fewer tokens (Smarty/Liquid) | Verbose keywords (Anka) | Verbose directives but concise variable output (`{{ $x }}`); canonical machine form can be more verbose |
| Structure | Indentation/whitespace (YAML) | Explicit tags (XML prompts) | Explicit tags for control flow; indentation only for HTML nesting |
| Expression language | Reuse PHP (Latte) | Simpler subset | PHP expressions but with a documented "safe subset" for templates |
| Closing tags | Parsimonious (LLMON) | Explicit close (XML) | Parsimonious in machine form; optional explicit close in human shorthand |
| Escaping | Automatic (Twig) | Explicit (teaches) | Automatic by default; opt-out explicit |

### 5.3 Anti-patterns to avoid (from research)

- **Inventing a new expression language** (Twig's Python-like syntax) — raises onboarding cost for PHP devs and models trained on PHP.
- **Whitespace-significant control flow** (YAML-style) — documented hazard for both humans (invisible bugs) and models (tokenization of indentation).
- **Mandatory closing tags for every element** (XML) — doubles structural overhead; LLMON shows parsimonious forms work.
- **Terse operator syntax** (`+=`, `?:`) — human-efficient but model-error-prone; Anka shows keywords win for generation.
- **Assuming frontier-model performance generalizes** — TOON data shows weak models score ~55% regardless of format; benchmark across tiers.
- **No formal grammar** — prevents constrained decoding; SynCode shows grammar guidance cuts syntax errors ~96%.
- **No tooling** — POML shows IDE support is part of accessibility.

---

## 6. Candidate SuperPHP Design Principles (Proposed)

These are **proposed** for owner review; they do not amend ADR-005 or any blueprint.

1. **Principle of Familiarity:** SuperPHP expressions are PHP. A developer or model that knows PHP can read SuperPHP without learning a new expression language. (Latte, Play/Twirl)
2. **Principle of Canonical Form:** Every construct has exactly one written form. No aliases, no shorthand variants. (Anka)
3. **Principle of Verbose Control Flow:** Directives use full English keywords (`@if`, `@foreach`, `@persist`), not symbols. (Anka)
4. **Principle of Explicit Structure:** Control flow is expressed with explicit tags/directives, not indentation or implicit block boundaries. (LLMON, XML-structured prompts)
5. **Principle of Safe Defaults:** Output is escaped unless explicitly marked raw. (Twig, Smarty, EasyTpl)
6. **Principle of Small Core:** v1 ships a minimal directive set; advanced features are opt-in and documented. (Kölling, Wirth)
7. **Principle of Progressive Disclosure:** Novices see a small subset; experts unlock advanced features; nothing is mandatory to learn up front. (Kölling)
8. **Principle of Dual Surface:** Authoring syntax (human) and canonical machine form (tool/AI) are convertible and share one AST/component document. (LLMON, DualCode)
9. **Principle of Formal Grammar:** A published EBNF grammar enables constrained decoding and tooling. (SynCode)
10. **Principle of Measured Ease:** "Easier than PHP" is a claim verified by SUS and comprehension/generation benchmarks across skill tiers and model tiers, not an aesthetic judgment. (TOON, SUS)

---

## 7. Decisions NOT Made (Owner Decision Required)

- Whether to adopt the dual-surface (LLMON-style) approach or a single syntax.
- Whether escaping is on by default and what the raw-output marker is.
- Whether a sandboxed subset ships in v1 (Liquid-style) for untrusted authors.
- The concrete benchmark suite and model tiers to test against.
- Whether SuperPHP's component document interchange format is JSON-first or JSON+YAML (per SPX report R3) — this research adds Markdown/YAML evidence but does not resolve it.
- Whether to rename SuperPHP (separate; per SPX naming report, no rename authorized).
- Whether to publish these findings into `Architecture/CrossCutting/RESEARCH-AGENDA.md` (authorization pending).

---

## 8. Source Index (with dates)

| # | Source | Date | Used in |
|---|--------|------|---------|
| 1 | LLMON paper (arXiv 2603.22519v3) | 2026-03-30 | §3.1, §5.1 |
| 2 | POML (Microsoft, arXiv 2508.13948) | 2025-08-19 | §3.1, §3.4 |
| 3 | Anka DSL (arXiv 2512.23214) | 2025 (arXiv) | §3.1, §3.5, §6 |
| 4 | MoonBit (LLM4Code '24) | 2024-04 | §3.1, §3.5 |
| 5 | Pel (arXiv 2505.13453) | 2025-06-09 | §3.1 |
| 6 | SynthFlow (GitHub) | 2026 | §3.1 |
| 7 | Moss (GitHub) | 2026-06-04 | §3.1, §3.3 |
| 8 | AiLang (dev.to) | 2026-05-22 | §3.1 |
| 9 | AI-oriented grammar / SimPy (arXiv 2404.16333) | 2024-04-25 | §3.1, §3.5 |
| 10 | PyLang (arXiv 2605.15607) | 2026 | §3.5 |
| 11 | SynCode (arXiv 2403.01632; TMLR 05/2025) | 2024-04-30 / 2025-05 | §3.5 |
| 12 | PARSE (EMNLP Industry 2025) | 2025 | §3.5 |
| 13 | Smarty syntax comparison (smarty.net) | n/a (current) | §3.2, §5.2 |
| 14 | Latte (latte.nette.org) | current | §3.2, §3.7, §5.1 |
| 15 | Twig (twig.symfony.com) | current (v3.22.1, 2025-11-16) | §3.2, §3.7 |
| 16 | CodeIgniter alternate PHP syntax | current (v4.7.5) | §3.2 |
| 17 | EasyTpl (github.com/phppkg/easytpl) | current | §3.2 |
| 18 | simple-template-engine (GitHub) | current | §3.2 |
| 19 | Handlebars PHP (GitHub) | current | §3.2 |
| 20 | Liquid (Perl port docs) | current | §3.2, §5.1 |
| 21 | Blade vs Twig (DistantJob) | 2025-06-13 | §3.2, §3.7 |
| 22 | Blade/Twig comparison (ResearchGate) | 2022 | §3.7 |
| 23 | SitePoint Twig article | 2017-07-30 | §3.7 |
| 24 | GeeksforGeeks Jinja comparison | 2023-10-16 | §3.7 |
| 25 | Twig vs Liquid (Cortance) | current | §3.7 |
| 26 | Kölling educational language design | 2024-12-11 | §3.3, §3.9, §6 |
| 27 | W3C template-aware a11y metric | (2011 symposium) | §3.4 |
| 28 | SUS (UserTesting) | current | §3.4 |
| 29 | FreeMarker (Apache) | current | §3.4 |
| 30 | Play/Twirl (playframework.com) | current (v2.9.x) | §3.4 |
| 31 | Berners-Lee design principles (W3C) | 1998 (last updated) | §3.9 |
| 32 | Kolibri design system principles | current | §3.9 |
| 33 | Windows 11 design principles (Microsoft Learn) | current | §3.9 |
| 34 | YTE (GitHub) | current | §3.8 |
| 35 | Pandoc templates (Stack Overflow) | 2014-05-09 | §3.8 |
| 36 | YAML spec 1.2.2 (yaml.org) | 2021-10-01 | §3.8 |
| 37 | YAML frontmatter / R-markdown (cran.r-project.org) | 2022-11-09 | §3.8 |
| 38 | GitHub issue forms (YAML) | current | §3.8 |
| 39 | TOON benchmarks (GitHub) | current | §4 |
| 40 | Medium "Beyond JSON" analysis | 2025-12-12 | §4 |
| 41 | Fern LLM-friendly docs | 2026-03 | §3.6 |
| 42 | XML-structured prompts (IPCCC 2025) | 2025-11 | §3.6 |
| 43 | ADR-005 (repo) | 2026-08-04 | §1, §2 |
| 44 | SPX-NAMING-COLLISION-REPORT.md (repo) | 2026-10-20 | §1, §7 |

---

**End of research deliverable.** This document is research, not an implementation requirement, not an ADR, not a rename authorization. All recommendations require owner approval. No code, ADR, frozen contract, or governance file has been modified by this research.
