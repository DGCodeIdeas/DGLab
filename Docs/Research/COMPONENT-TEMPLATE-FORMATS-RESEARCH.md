# Component-Based Template Formats — Research (Vue SFC and peers)

**Created:** 2026-10-20
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR, NOT an authorization to change ADR-005 or any blueprint
**Parent agenda:** `RESEARCH-AGENDA.md` (root) — SuperPHP Naming Investigation + Track 5 connecting-theme implications
**Related:** `SPX-EASE-OF-USE-RESEARCH.md`, `SPX-NAMING-COLLISION-REPORT.md`, `RESEARCH-INDEX.md`, `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md`
**Research question (owner-supplied requirement):** SuperPHP must be **easier than PHP** and **understandable by both humans and various AIs with varying knowledge levels**. This report surveys the major component-based template formats to extract design rationale, single-file vs multi-file organization, template-syntax decisions, AI/LLM comprehension evidence, and DX research relevant to that requirement.

---

## 1. Executive Summary

1. **Single-file components (SFC) won the web.** Vue, Svelte, Astro, Marko, and Solid all converge on colocating template + logic (+ often styles) in one file, justified by "inherently coupled concerns" (Vue docs) and "separation of concerns ≠ separation of file types" (Vue v2 docs). Multi-file remains a fallback (`src` imports in Vue; `.svelte.js`/`.svelte.ts` in Svelte).
2. **The dominant surface is "HTML superset + host-language expressions."** Astro calls its syntax "a superset of HTML"; Svelte calls `.svelte` "a superset of HTML"; Vue SFC is "syntactically compatible with HTML"; Marko "HTML re-imagined." All keep the host language (JS/TS/PHP-equivalent) for expressions rather than inventing a new expression language — matching Latte's strategy cited in `SPX-EASE-OF-USE-RESEARCH.md` §3.2.
3. **Control flow diverges sharply:** Vue uses directives (`v-if`, `v-for`); Svelte uses block syntax (`{#if}`, `{#each}`); Astro/JSX use JS expressions (`{cond && <p/>}`, `.map`); Marko uses tags (`<if>`, `<for>`); Lit uses JS. Directive vs block vs JS-expression is a key design fork with no consensus winner.
4. **Reactivity model is the differentiator, not template syntax.** Solid (render-once + fine-grained signals), Svelte 5 (runes, explicit), Qwik (resumability, no hydration), Vue (mutable fine-grained + compiler). Template syntax is largely cosmetic; reactivity semantics dominate DX and AI comprehension.
5. **AI/LLM comprehension of template formats is format-sensitive.** Research on prompt/output formats (NAACL 2025 "LLMs Are Biased Towards Output Formats"; EACL 2026 "Lost in Formatting"; "Price of Format" EMNLP 2025) shows LLMs perform measurably differently across structurally equivalent formats, with explicit tagged structure (XML-like) aiding weak models and minimal formatting aiding diversity. This cautions against assuming any single syntax is "AI-friendly" for all model tiers — echoing TOON findings in `SPX-EASE-OF-USE-RESEARCH.md` §4.
6. **HTML-first (HTMX/Alpine) is a distinct paradigm:** behavior via attributes on standard HTML, server returns HTML fragments, no build step, no component runtime. Strong fit for "easier than PHP" for non-devs and for AI (HTML is heavily in pretraining corpora), but limited to server-driven apps.
7. **Meta-frameworks (SvelteKit/Nuxt/Next) layer routing/data/loading on top of components** without changing component syntax — confirming that "component format" and "app framework" are separable concerns. Relevant to SuperPHP: the component document interchange contract (SPX report §2.1) should be separable from any app framework.
8. **Implication for SuperPHP:** the strongest transferable pattern is **HTML-superset surface + host-language (PHP) expressions + verbose keyword directives + explicit tagged structure + single canonical form + optional dual surface (human shorthand ↔ machine canonical form)** — combining Vue/Svelte/Astro familiarity with Anka's verbose-keyword/single-canonical-form and LLMON's dual-form (per `SPX-EASE-OF-USE-RESEARCH.md`).

---

## 2. Scope and Method

- Sources: official documentation (Vue, Svelte, Astro, Solid, Qwik, Marko, Lit, MDN Web Components, HTMX, Alpine, Nuxt, Next.js, SvelteKit), framework design-principle pages, peer-reviewed papers (NAACL/EACL/EMNLP 2025–2026), and the repo's existing research (`SPX-EASE-OF-USE-RESEARCH.md`).
- Focus: ease-of-use, human/AI comprehension, architecture, single-file vs multi-file, template-syntax rationale, DX research, dual-audience handling.
- All recommendations are **proposed, owner decision required**. No ADR, frozen contract, or code is modified by this research.

---

## 3. Format-by-Format Analysis

### 3.1 Vue SFC (`.vue`)

**Syntax (from Vue docs, SFC Syntax Specification):**
```vue
<script setup>
import { ref } from 'vue'
const greeting = ref('Hello World!')
</script>
<template>
  <p class="greeting">{{ greeting }}</p>
</template>
<style scoped>
.greeting { color: red; }
</style>
```
- HTML-like, syntactically compatible with HTML. Top-level blocks: `<template>` (at most one), `<script>` / `<script setup>` (at most one each), `<style>` (multiple allowed), plus custom blocks.
- `src` attribute allows splitting any block into an external file.
- Component name inferred from filename.

**Design rationale (Vue docs "Why SFC"):**
- Author modularized components using familiar HTML, CSS, JavaScript syntax.
- Colocation of inherently coupled concerns.
- Pre-compiled templates (no runtime compile cost).
- Component-scoped CSS.
- More ergonomic syntax with Composition API.
- Compile-time optimizations by cross-analyzing template and script.
- IDE support with auto-completion and type-checking for template expressions.
- Out-of-the-box HMR.

**Key statement:** "separation of concerns is not equal to separation of file types… it makes much more sense to divide them into loosely-coupled components and compose them" (Vue v2 docs).

**AI/comprehension note:** Vue's directive syntax (`v-if`, `v-for`, `v-bind`, `@click`) is a small, learnable vocabulary; models are heavily trained on `.vue` files. The `{{ }}` mustache interpolation is unambiguous. Weakness: two script forms (`<script>` vs `<script setup>`) and the Options/Composition split create decision points (Anka's "one canonical form" anti-pattern).

**Sources:** vuejs.org/guide/scaling-up/sfc.md; vuejs.org/api/sfc-spec (current); vuejs.org/v2/guide/single-file-components (2018-era, still canonical rationale).

### 3.2 Svelte (`.svelte`) and Svelte 5 Runes

**Syntax (Svelte docs):**
```svelte
<script>
  let count = $state(0);
</script>
<button onclick={() => count++}>clicks: {count}</button>
<style>
  button { font-size: 2em; }
</style>
```
- "A superset of HTML." All three sections (script, markup, style) optional. `<script module>` for module-level logic.
- Template syntax: `{#if}`, `{#each}`, `{#await}`, `{#snippet}`, `{@render}`, `{@html}`, bindings `bind:`, transitions `transition:`, actions `use:`.
- Scoped CSS by default.

**Svelte 5 runes (migration guide, InfoQ 2023-10-02):**
- Runes are compiler instructions prefixed with `$`: `$state`, `$derived`, `$effect`, `$props`, `$bindable`, `$inspect`, `$host`.
- Replaces Svelte 4 implicit reactivity (`let` at top level reactive; `$:` reactive statements; `export let` props) with explicit reactivity.
- Runes usable in `.svelte.js`/`.svelte.ts` modules too — enabling reuse of reactive logic outside components.
- Backward compatible: Svelte 4 and 5 syntax mix.
- `$props` leans on JS destructuring (renaming, rest, defaults) — reduces Svelte-specific syntax.

**Rationale:** "as applications grow in complexity, figuring out which values are reactive and which aren't can get tricky" (Svelte team). Explicit reactivity aids maintainability and compiler optimizations; "everything special to reactivity in Svelte is a rune."

**AI/comprehension note:** Runes are function-call-shaped (`$state(0)`), familiar to JS/TS developers and models. The `$` prefix is unusual but consistent. Block syntax (`{#if}…{/if}`) is explicit and pairs open/close; models handle it well. Risk: two syntax generations (Svelte 4 vs 5) in training data.

**Sources:** svelte.dev/docs/svelte/svelte-files (current); svelte.dev/docs/svelte/v5-migration-guide (current); infoq.com/news/2023/10/svelte-5-runes (2023-10-02).

### 3.3 Astro (`.astro`) and Islands Architecture

**Syntax (Astro docs):**
```astro
---
import Avatar from '../components/Avatar.astro';
const name = 'Astro';
---
<div><h1>Hello {name}!</h1></div>
<style>
  h1 { color: red; }
</style>
```
- Superset of HTML. Code fence `---` (Markdown frontmatter-inspired) separates component script (runs at build/server time, never ships to client) from template.
- JSX-like expressions in `{}`; values are non-reactive (run once at render).
- Can't attach event handlers to HTML elements in `.astro` (by design — static output).
- Supports Markdown/MDX content with YAML/TOML frontmatter.

**Islands architecture (Astro docs):**
- By default, all components render to static HTML + CSS, stripping client JS.
- Client islands opt in via `client:*` directives (`client:load`, `client:idle`, `client:visible`, `client:media`, `client:only`).
- Server islands via `server:defer` for personalized/dynamic SSR fragments.
- Multi-framework: React, Preact, Svelte, Vue, Solid, HTMX, web components in one project.
- "It should be impossible to build a slow website in Astro."

**Design principles (Astro docs "Why Astro"):**
1. Content-driven.
2. Server-first.
3. Fast by default.
4. Easy to use — ".astro UI language is a superset of HTML: any valid HTML is valid Astro templating syntax! So, if you can write HTML, you can write Astro components!"
5. (Developer-focused) — built to render on the server, not in browser.

**AI/comprehension note:** The frontmatter fence is a strong, explicit boundary between "runs once on server" and "markup" — arguably the clearest separation of any SFC. HTML-superset surface means models' HTML knowledge transfers directly. The non-reactivity of `.astro` (vs JSX components) is a conceptual distinction AI must track.

**Sources:** docs.astro.build/en/basics/astro-components (current); docs.astro.build/en/concepts/islands (current); docs.astro.build/en/reference/astro-syntax (current); starlog.is/articles/automation/withastro-astro (2026-05-10).

### 3.4 SolidJS (JSX, render-once)

**Model (Solid docs):**
- "A Solid component is a function that runs once." Sets up state, returns JSX, not called again.
- Fine-grained reactivity: signals (`createSignal`), memos, effects; JSX expressions are tracking scopes.
- No virtual DOM; compiles to real DOM operations.
- Components are just functions; JSX for markup.

**Design rationale:** "Render-once mental model: your components are regular JavaScript functions that run once to set up your view"; "Simple: learn a few powerful concepts that can be reused, combined, and built on top of"; fine-grained updates only where state is used.

**AI/comprehension note:** Solid's model demands that developers read signals inside tracking scopes (JSX), not in component body — a subtle rule that causes bugs (`STRICT_READ_UNTRACKED`). This is a known comprehension hazard for both humans and AI. Solid is essentially "React syntax, non-React semantics," a mismatch risk.

**Sources:** docs.solidjs.com/concepts/components/basics (current); docs.solidjs.com/concepts/reactivity (current); solidjs.com (current).

### 3.5 Qwik (component$/JSX + resumability)

**Model (Qwik docs):**
- `component$(() => <div>Hello</div>)` — JSX wrapped in `component$`; trailing `$` enables Optimizer to split into lazy chunks.
- Resumable: server pauses execution, client resumes without hydration/replaying; state/listeners serialized into HTML.
- `useSignal`, `useStore`, `useComputed$`, `useTask$`, `useVisibleTask$`.
- Event handlers serialized as `q-e:click="./chunk.js#handler"` attributes.

**Rationale:** "Qwik is designed from the ground up to produce lots of lazy loadable boundaries"; "bundle size should not be something developers should think about"; resumability solves hydration cost.

**AI/comprehension note:** The `$` suffix convention and `component$` wrapper are Qwik-specific; the JSX itself is standard. The resumability mental model (serialization as design constraint) is a significant conceptual load. Qwik is the most architecture-heavy of the surveyed formats.

**Sources:** qwik.dev/docs/core/overview (current); next.qwik.dev/docs/concepts/resumable (current); builder.io/blog/qwik-2-coming-soon (2024-02-09).

### 3.6 Marko (eBay) — HTML re-imagined

**Syntax (Marko v5/v6 docs):**
- "Just about any valid HTML is valid Marko"; extends HTML declaratively.
- Dynamic text via `${expr}` (escaped by default).
- Attributes parsed as JavaScript expressions (not strings): `number=1`, `boolean`, `array=[1,2,3]`, `object={hello:"world"}`.
- Concise syntax (indentation-based) optional: `div -- Hello ${name}`.
- Components discovered via folder structure (tags-dir); no imports needed.
- `<try>`/`<@placeholder>`/`<@catch>` for async; streaming SSR.
- `<script>` runs client-side reactively; `<style>` scoped; `<style/styles>` CSS modules.

**Rationale (eBay/Marko):** "erase all boilerplate and conceal the underlying framework so developers new to Marko can hit the ground running" (Marko v4 goal); streaming server rendering + partial hydration; ~10KB client runtime; optimized for both server and browser.

**AI/comprehension note:** Marko's attribute-as-JS-expression (`checked=isChecked`, `array=[1,2,3]`) is elegant but unusual — models trained on HTML expect quoted string attributes. The concise/HTML dual syntax is a two-form system (like LLMON). Marko is the closest precedent to "HTML superset with rich JS in attributes."

**Sources:** v5.markojs.com/docs/syntax (current); github.com/ebay/evo-web (current); innovation.ebayinc.com/stories/the-future-of-marko (2017-10-26); TerminalSkills/skills marko SKILL.md (current, Marko 6.4.0).

### 3.7 Web Components / Custom Elements

**Model (MDN, W3C):**
- Three technologies: Custom Elements, Shadow DOM, HTML Templates (`<template>`, `<slot>`).
- Autonomous custom elements require a hyphen in name (`<my-element>`); customized built-ins use `is="..."`.
- Class extends `HTMLElement`; register via `customElements.define`; lifecycle callbacks (`connectedCallback`, etc.).
- No framework, no build step required; vanilla JS/HTML/CSS.
- W3C guidelines: self-contained, declarative configuration via attributes, events for outward data, attributes for non-complex data.

**AI/comprehension note:** Web components are the platform baseline; their JS class boilerplate is verbose. The `<template>`/`<slot>` model is well understood by models (HTML knowledge). Declarative shadow DOM is emerging. For SuperPHP, custom-element tag names (`<s:ui:button />` style) align with this ecosystem.

**Sources:** developer.mozilla.org/en-US/docs/Web/API/Web_components (current); w3.org/TR/html53/custom-elements.html (current); web.dev/articles/custom-elements-v1 (current).

### 3.8 Lit (LitElement / lit-html)

**Model (Lit docs):**
- `LitElement` extends `HTMLElement` (ReactiveElement base + lit-html templating).
- Templates via tagged template literals: `` html`<h1>Hello ${name}</h1>` ``.
- Tagged template literals split static strings from dynamic values; Lit caches the parsed template by string identity and updates only changed parts.
- No compile step needed; ~5KB; directives for control flow; scoped styles via `css` tagged literals; Shadow DOM by default.
- "lit-html is lazily rendered… no real work happens until rendered to DOM."

**Design rationale:** combines two web primitives (JS tagged template literals + `<template>` element); "UI as a function of state"; fast because only binding positions update; works tool-free.

**AI/comprehension note:** Lit templates are JS template literals — models know template literals well. But the `${}` interpolation inside HTML strings can be confused with string interpolation; the binding-position semantics (attribute/property/event/child) require learning. Lit is the most "JS-native" of the surveyed formats.

**Sources:** lit.dev/docs (current); lit.dev/docs/templates/overview (current); github.com/lit/lit dev-docs/design/how-lit-html-works.md (current).

### 3.9 HTMX / Alpine.js (HTML-first)

**Model (HTMX docs, arXiv 2602.17193):**
- HTMX: access modern browser features (AJAX, SSE, WebSockets) directly from HTML attributes (`hx-get`, `hx-post`, `hx-target`, `hx-swap`, `hx-trigger`). Server returns HTML fragments.
- Alpine: tiny (~10KB) reactive state in HTML attributes (`x-data`, `x-show`, `@click`, `x-model`).
- No build step; no component runtime; "HTML in one file"; state lives on server (HTMX) or client (Alpine).
- "HTML First" manifesto (Ennis 2023): use modern HTML before considering other options; minimalism.

**Evidence (arXiv 2602.17193, 2026-02):** HTML-oriented development can reduce codebase size ~67% (React→HTMX port case study) with maintenance benefits; open questions remain about alignment with AI-driven development.

**AI/comprehension note:** HTMX/Alpine are extremely LLM-friendly in one sense: HTML attributes are plain, models are heavily trained on HTML, and there is no new syntax beyond attributes. The InfoWorld article (2025-03-29) notes both "appear to be an extension of HTML." Weakness: complex client state becomes attribute soup; security requires escaping all user content (XSS surface grows with expressiveness).

**Sources:** htmx.org/docs (current); arxiv.org/html/2602.17193v1 (2026-02); infoworld.com/article/3856520 (2025-03-29); dev.to cloudstreet HTMX-in-2025 (2025).

### 3.10 Meta-framework component patterns (SvelteKit / Nuxt / Next.js)

- **SvelteKit:** file-based routing (`src/routes`), `+page.svelte`/`+layout.svelte`, load functions (`+page.js`/`+page.server.js`), form actions, SSR then hydration, runes in components. Components remain `.svelte`; framework adds routing/data/lifecycle.
- **Nuxt:** auto-imports of components/composables, file-based routing, layers (DDD), islands (`<NuxtIsland>`), server components, Nitro server. Components are Vue SFCs; framework adds conventions.
- **Next.js (App Router):** React Server Components by default (zero client JS), nested layouts with partial rendering, streaming, `loading.tsx`/`error.tsx`/`not-found.tsx`, dynamic imports, hybrid rendering (SSG/ISR/SSR/CSR).

**Pattern:** all three keep the underlying component format (Svelte/Vue/React) and add app-level conventions. This confirms "component format" and "app framework" are separable — directly relevant to SuperPHP's component-document interchange contract (SPX report §2.1), which should not be coupled to a specific app framework.

**Sources:** kit.svelte.dev/docs/introduction (current); svelte.dev/docs/kit/project-structure (current); nuxt.com/docs/4.x (current); patterns.dev/react/nextjs (current); contentful.com/blog/nuxt-next-js-compared (2024-11-29).

---

## 4. Cross-Cutting Analysis

### 4.1 Single-file vs multi-file

| Format | Default | Multi-file option |
|--------|---------|-------------------|
| Vue SFC | single `.vue` | `src` imports per block; separate files |
| Svelte | single `.svelte` | `.svelte.js`/`.svelte.ts` for shared reactive logic |
| Astro | single `.astro` | standard JS/TS modules; content collections |
| Solid | single JSX file | standard modules |
| Qwik | single JSX/TSX file | standard modules |
| Marko | single `.marko` | standard modules |
| Lit | single JS/TS file | standard modules |
| Web Components | single JS file | standard modules |
| HTMX/Alpine | HTML (server templates) | separate server templates |

**Consensus:** single-file is the default for component-scoped concerns; multi-file is for shared/cross-cutting logic. Vue's "separation of concerns ≠ separation of file types" (v2 docs) is the canonical justification.

**For SuperPHP:** a single-file component format (template + `~setup`/PHP + styles) matches the dominant convention and the repo's existing `<s:ui:button />` component-tag direction; multi-file via `src`-style imports should be a supported fallback.

### 4.2 Template syntax design decisions

| Decision | Vue | Svelte | Astro | Marko | Lit | HTMX/Alpine |
|----------|-----|--------|-------|-------|-----|-------------|
| Surface | HTML-like | HTML superset | HTML superset | HTML re-imagined | JS tagged templates | HTML attributes |
| Expressions | `{{ }}` mustache + JS | `{}` JS | `{}` JSX-like | `${}` JS | `${}` in template literal | attribute values (JS) |
| Control flow | directives (`v-if`) | blocks (`{#if}`) | JS (`&&`, `.map`) | tags (`<if>`, `<for>`) | JS (`if`, `.map`) | server-side (N/A) |
| Props | `defineProps` / attrs | `$props()` rune | `Astro.props` | `input` | class properties | N/A (server) |
| Styles | `<style scoped>` | `<style>` scoped | `<style>` scoped | `<style>` scoped | `css` tagged | external CSS |
| Reactivity | mutable fine-grained | explicit runes | none (run once) | fine-grained | property-driven | server-driven |

**Key fork:** directives (Vue) vs blocks (Svelte) vs host-language expressions (Astro/JSX/Lit). No format has clearly won; each trades discoverability (directives are greppable and short) against generality (JS expressions need no new syntax). For SuperPHP, verbose keyword directives (`@if`, `@foreach`, `@persist`) were already proposed in `SPX-EASE-OF-USE-RESEARCH.md` §5.1/§6 — this survey supports that: directives keep the template readable for non-devs and give models a small, regular vocabulary.

### 4.3 AI/LLM comprehension of template formats

Direct evidence (2025–2026 papers):
- **NAACL 2025 "LLMs Are Biased Towards Output Formats"** (Zhou et al.): LLM performance varies significantly across structurally equivalent output formats (lists, mappings, wrapping methods); format bias is measurable and mitigable via demonstrations/fine-tuning. Implication: format choice is not cosmetic for AI.
- **EACL 2026 "Lost in Formatting"**: output formatting (a seemingly trivial design choice) exposes fundamental fragilities in LLMs; most studies adopt a default format without justification. Implication: SuperPHP must justify and benchmark its format, not assume.
- **EMNLP 2025 "Price of Format" (Diversity Collapse)**: structural tokens reduce output diversity; format consistency between fine-tuning and inference matters for structure-sensitive tasks. Implication: if SuperPHP is to be reliably generated by models, its format should be in pretraining/fine-tuning corpora or at least simple enough to learn in-context.
- **IPCCC 2025 (XSP)**: XML-structured prompts reduce syntax errors up to 60% and logic errors up to 30% across 30 models (0.5B–671B). Implication: explicit tagged structure (like Vue/Svelte component tags and Marko tags) aids weak models — supports SuperPHP's tagged component syntax (`<s:ui:button />`) over implicit/whitespace structure.
- **TOON benchmarks** (in `SPX-EASE-OF-USE-RESEARCH.md` §4): comprehension accuracy varies sharply by format × model tier (e.g., XML 98.1% vs JSON-compact 55% for one model; TOON 0% on tiny models). Reinforces: benchmark across tiers.

Additional qualitative points:
- HTML is one of the most represented formats in LLM pretraining corpora; formats that are HTML supersets (Vue, Svelte, Astro, Marko) inherit that prior. HTMX/Alpine benefit most (pure HTML attributes).
- Models are also heavily trained on JSX/TSX (React), so JSX-like expression syntax (Astro, Solid, Qwik) transfers well.
- Vue's `.vue` SFC is extremely common in training data → high prior for `{{ }}`, `<template>`, `<script setup>`.
- Two-form systems (Marko concise/HTML; LLMON shorthand/machine) match the dual-surface pattern proposed in `SPX-EASE-OF-USE-RESEARCH.md` §5.1 point 6.

### 4.4 Dual audience (human + AI)

The strongest precedent in this survey is the **two-form pattern**: Marko (concise indentation syntax + HTML syntax, switchable), and conceptually LLMON (human shorthand + machine form, shared AST). The HTML-first approach (HTMX/Alpine) is a single form that is simultaneously human- and AI-friendly because HTML is the lingua franca of both. SuperPHP's proposed dual surface (authoring shorthand ↔ canonical machine form, per `SPX-EASE-OF-USE-RESEARCH.md` §5.1/§6) is consistent with both.

### 4.5 DX research / usability

- **Astro** explicitly targets "Easy to use: You don't need to be an expert to build something with Astro" and measures accessibility by HTML-superset familiarity.
- **Vue** positions SFC as "the recommended approach" for non-trivial frontends, with IDE support and HMR as DX pillars.
- **Svelte** emphasizes "less boilerplate" and compiler-optimized output; runes reduce "figuring out which values are reactive."
- **Marko v4 goal:** "erase all boilerplate… developers new to Marko can hit the ground running."
- **Lit:** "no compile step needed," tool-free development, ~5KB.
- **HTMX:** "ship features at the speed of thought," no build step; case studies show 67% codebase reduction.
- **Kölling/Wirth principles** (in `SPX-EASE-OF-USE-RESEARCH.md` §3.3/§3.9): small core, keyword syntax, orthogonality, progressive disclosure, awareness most users won't be professionals — applies directly to SuperPHP's varying-expertise audience.

No formal SUS/usability-study numbers were found for these formats in this search; the evidence is design-rationale and case-study based. SuperPHP should not claim "easier than PHP" without its own SUS + comprehension benchmarks (per `SPX-EASE-OF-USE-RESEARCH.md` principle 10).

---

## 5. Implications for SuperPHP (Proposed — Owner Decision Required)

These extend (do not amend) `SPX-EASE-OF-USE-RESEARCH.md` §5.1/§6:

1. **HTML-superset surface** (Astro/Svelte/Vue/Marko consensus): SuperPHP templates should be valid-ish HTML with PHP expressions, so HTML knowledge and models' HTML prior transfer directly. Avoid inventing a non-HTML surface.
2. **Keep PHP expressions verbatim** (Latte strategy, reinforced by Astro/Solid/Qwik JSX prior): models and PHP devs already know them; don't invent a new expression language (Twig's Python-like syntax is the counter-example).
3. **Verbose, keyword-based, single-canonical-form directives** (Anka) for control flow: `@if`/`@foreach`/`@persist`/`@global`/`~setup`, one written form each, no operator shorthand. Directives (Vue-style) over blocks or JS-expression control flow, for greppability and a small vocabulary.
4. **Explicit tagged component structure** (POML/LLMON/XML-prompt evidence): `<s:ui:button />`-style tags are self-describing and aid weak models; avoid implicit/whitespace structure for control flow (YAML hazard).
5. **Single-file default with multi-file fallback** (Vue `src`, Svelte modules): template + `~setup`/PHP + styles colocated; splitting supported.
6. **Dual surface (human shorthand ↔ canonical machine form)** (Marko two-syntax, LLMON two-form, DualCode): authoring stays terse for humans; a canonical, more regular form is what tools/constrained decoders consume; both share one AST/component document. This matches the SPX component-document interchange contract (SPX report §2.1) and the JSON-first vs JSON+YAML candidate (R3).
7. **Ship a formal grammar (EBNF)** (SynCode): enables constrained decoding so weaker models generate valid SuperPHP; the format-bias papers (EACL 2026) caution that format must be justifiable and benchmarked.
8. **Safe-by-default escaping** (Twig/Smarty/EasyTpl/Marko `${}` escaped by default): `{{ $x }}` escapes; raw output explicit.
9. **Small core + progressive disclosure** (Kölling/Wirth): minimal directive set in v1; advanced features (macros, inheritance) opt-in.
10. **Built-in tooling** (POML): highlighting, autocomplete, live preview, diagnostics are part of accessibility.
11. **Sandboxed subset** (Liquid) for untrusted template authors (themes/plugins).
12. **Evaluate with SUS + comprehension/generation benchmarks** across human skill tiers and model tiers before claiming "easier than PHP" — and benchmark format choice itself (EACL 2026 "Lost in Formatting").

**Tensions to resolve (owner decision required):**
- Directives (Vue-style) vs blocks (Svelte-style) vs JS expressions (Astro/JSX). This report proposes directives; the choice affects AI comprehension evidence (explicit tagged structure aids weak models).
- Single syntax vs dual surface. Marko/LLMON support dual; cost is two forms to maintain.
- HTML-superset strictness: Astro allows any valid HTML; Marko parses attributes as JS. How far should SuperPHP go (e.g., unquoted attributes, JS-in-attributes)?

---

## 6. Decisions NOT Made (Owner Decision Required)

- Whether SuperPHP adopts an HTML-superset surface or a stricter custom format.
- Whether control flow uses directives, blocks, or host-language expressions.
- Whether to adopt a dual surface (Marko/LLMON-style) or a single syntax.
- Whether escaping is on by default and what the raw-output marker is.
- Whether a sandboxed subset ships in v1.
- The concrete benchmark suite and model tiers to test against.
- Whether SuperPHP's component document interchange format is JSON-first or JSON+YAML (per SPX report R3) — this research adds HTML-superset/JSX evidence but does not resolve it.
- Whether to rename SuperPHP (separate; per SPX naming report, no rename authorized).
- Whether to publish these findings into `Architecture/CrossCutting/RESEARCH-AGENDA.md` (authorization pending).

---

## 7. Source Index (with dates)

| # | Source | Date | Used in |
|---|--------|------|---------|
| 1 | Vue SFC docs (vuejs.org/guide/scaling-up/sfc) | current | §3.1, §4.1 |
| 2 | Vue SFC Syntax Specification (vuejs.org/api/sfc-spec) | current | §3.1 |
| 3 | Vue v2 SFC rationale (vuejs.org/v2/guide/single-file-components) | 2018-era | §3.1, §4.1 |
| 4 | Vue Composition API FAQ (vuejs.org/guide/extras/composition-api-faq) | current | §3.1 |
| 5 | Svelte `.svelte` files docs (svelte.dev/docs/svelte/svelte-files) | current | §3.2 |
| 6 | Svelte 5 migration guide (svelte.dev/docs/svelte/v5-migration-guide) | current | §3.2 |
| 7 | InfoQ "Svelte 5 Introduces Runes" | 2023-10-02 | §3.2 |
| 8 | Astro components docs (docs.astro.build/en/basics/astro-components) | current | §3.3 |
| 9 | Astro islands docs (docs.astro.build/en/concepts/islands) | current | §3.3 |
| 10 | Astro syntax reference (docs.astro.build/en/reference/astro-syntax) | current | §3.3 |
| 11 | Starlog "Astro's Islands Architecture" | 2026-05-10 | §3.3 |
| 12 | Solid components/JSX docs (docs.solidjs.com) | current (2026-09-12) | §3.4 |
| 13 | Solid reactivity docs (docs.solidjs.com) | current | §3.4 |
| 14 | Qwik overview docs (qwik.dev/docs/core/overview) | current | §3.5 |
| 15 | Qwik resumability docs (next.qwik.dev/docs/concepts/resumable) | current | §3.5 |
| 16 | Builder.io "Towards Qwik 2.0" | 2024-02-09 | §3.5 |
| 17 | Marko syntax docs (v5.markojs.com/docs/syntax) | current | §3.6 |
| 18 | eBay Innovation "The Future of Marko" | 2017-10-26 | §3.6 |
| 19 | TerminalSkills marko SKILL.md (Marko 6.4.0) | current (2026-09-29) | §3.6 |
| 20 | MDN Web Components | current | §3.7 |
| 21 | W3C Custom Elements / HTML Standard | current | §3.7 |
| 22 | web.dev "Custom Elements v1" (Bidelman) | current | §3.7 |
| 23 | Lit docs (lit.dev/docs) | current | §3.8 |
| 24 | Lit templates overview (lit.dev/docs/templates/overview) | current | §3.8 |
| 25 | Lit how-lit-html-works (github.com/lit/lit) | current | §3.8 |
| 26 | HTMX docs (htmx.org/docs) | current (v2) | §3.9 |
| 27 | arXiv 2602.17193 "The Case for HTML First Web Development" | 2026-02 | §3.9, §4.5 |
| 28 | InfoWorld "HTMX and Alpine.js" | 2025-03-29 | §3.9 |
| 29 | SvelteKit docs (kit.svelte.dev) | current | §3.10 |
| 30 | SvelteKit project structure (svelte.dev/docs/kit/project-structure) | current | §3.10 |
| 31 | Nuxt docs 4.x (nuxt.com/docs) | current | §3.10 |
| 32 | patterns.dev "Overview of Next.js" | current | §3.10 |
| 33 | Contentful "Nuxt vs Next.js" | 2024-11-29 | §3.10 |
| 34 | NAACL 2025 "LLMs Are Biased Towards Output Formats" | 2025-04-29 | §4.3 |
| 35 | EACL 2026 "Lost in Formatting" | 2026-03-10 | §4.3, §5 |
| 36 | EMNLP 2025 "Price of Format" (Diversity Collapse) | 2025-10-30 | §4.3 |
| 37 | IPCCC 2025 XSP (XML-structured prompts) | 2025-11 | §4.3 |
| 38 | TOON benchmarks (github.com/bast-ar/tolon) | current | §4.3 (via SPX-EASE-OF-USE-RESEARCH §4) |
| 39 | LLMON paper (arXiv 2603.22519) | 2026-03-30 | §4.4 (via SPX-EASE-OF-USE-RESEARCH §3.1) |
| 40 | Anka DSL (arXiv 2512.23214) | 2025 | §5 (via SPX-EASE-OF-USE-RESEARCH §3.1) |
| 41 | Kölling educational language design | 2024-12-11 | §4.5 (via SPX-EASE-OF-USE-RESEARCH §3.3) |
| 42 | ADR-005 (repo) | 2026-08-04 | §1 |
| 43 | SPX-EASE-OF-USE-RESEARCH.md (repo) | 2026-10-20 | throughout |
| 44 | SPX-NAMING-COLLISION-REPORT.md (repo) | 2026-10-20 | §6 |

---

**End of research deliverable.** This document is research, not an implementation requirement, not an ADR, not a rename authorization. All recommendations require owner approval. No code, ADR, frozen contract, or governance file has been modified by this research.
