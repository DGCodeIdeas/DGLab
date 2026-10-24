# Vue SFC and Component-Format Research — Comparative Analysis

**Created:** 2026-10-20  
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR  
**Parent:** `SPX-NAMING-COLLISION-REPORT.md` §3 (architecture comparison), `SPX-EASE-OF-USE-RESEARCH.md`  
**Research question:** How do Vue SFC and similar component formats handle ease-of-use, human/AI comprehension, and architecture? What can NeoSPX learn from them?

---

## 1. Executive Summary

Vue SFC and its peers (Svelte, Astro, Solid, Qwik, Marko, Lit) represent the dominant **sectioned single-file component** pattern in modern web development. They share a common architecture: **one file, multiple sections (template/script/style), compiled to framework-specific output, with HTML as the base syntax**.

Key findings relevant to NeoSPX:

1. **Sectioned SFC is the proven pattern** — Vue, Svelte, Astro, and Marko all use `<template>`/`<script>`/`<style>` sections. A PHP-native variant (phpsfc) uses HTML-comment markers (`<!-- phpsfc:xxx -->`) for a build-free approach.
2. **Dual authoring modes exist** — Marko supports both HTML-mode and concise (indentation-based) mode, directly paralleling NeoSPX's "multiple syntaxes through syntax drivers."
3. **HTML-first is viable but has limits** — HTMX/Alpine show that pure HTML attributes can drive interactivity, but hit reusability limits at scale. Components need a structured format.
4. **AI-friendly metadata is emerging** — DSDS, AWS component docs, and Mintlify all point to machine-readable component metadata (JSON/YAML) with explicit audience tagging (`for: human|agent|all`).
5. **Developer experience is measurable** — Angular's 2024 survey shows ~90% satisfaction but boilerplate remains a pain point; HCI research links "affordance coherence" and "diagnostic translucency" to reduced debugging time.

---

## 2. Format Comparison Matrix

| Format | File Ext | Sections | Base Syntax | Build Step | Reactivity | Component Doc Interchange |
|--------|----------|----------|-------------|------------|------------|--------------------------|
| **Vue SFC** | `.vue` | `<template>`, `<script>`, `<style>` | HTML superset | Required (Vite) | Proxy-based (Composition API) | No (Vue-specific) |
| **Svelte 5** | `.svelte` | `<script>`, markup, `<style>` | HTML superset | Required (Vite) | Runes (`$state`, `$derived`) | No (Svelte-specific) |
| **Astro** | `.astro` | Code fence `---`, template | HTML-first | Required (Vite) | Islands (selective hydration) | No (Astro-specific) |
| **SolidJS** | `.jsx`/`.tsx` | JSX in JS/TS file | JSX | Required (Vite) | Signals (`createSignal`) | No |
| **Qwik** | `.tsx` | JSX in TS file | JSX | Required | Resumability (`$`-suffix) | No |
| **Marko** | `.marko` | Single file, HTML superset | HTML (concise alt mode) | Optional | Reactive by default | No |
| **Lit** | `.js`/`.ts` | Tagged template literals | JS tagged templates | Optional | Reactive properties | No |
| **HTMX** | `.html` | N/A (attributes) | Pure HTML | None | Server-driven | No |
| **Alpine.js** | `.html` | N/A (attributes) | Pure HTML | None | Reactive attributes | No |
| **phpsfc** | `.php` | HTML-comment markers | PHP + HTML | None | N/A (server-only) | No |

**Key observation:** Every format except HTMX/Alpine requires a build step. Marko and Lit offer optional build. phpsfc is the only PHP-native, build-free SFC variant found.

---

## 3. Vue SFC — Deep Dive

### 3.1 Syntax and Structure

```vue
<template>
  <div class="greeting">{{ msg }}</div>
</template>

<script setup>
import { ref } from 'vue'
const msg = ref('Hello!')
</script>

<style scoped>
.greeting { color: red; }
</style>
```

**Key features:**
- `<template>` — HTML-based, supports Vue directives (`v-if`, `v-for`, `v-model`)
- `<script setup>` — Composition API shorthand, auto-exposed to template
- `<style scoped>` — CSS scoping via attribute selectors (no build-time class hashing required)
- Custom blocks (`<i18n>`, `<docs>`) — extensible
- `lang` attribute — pre-processor support (TypeScript, SCSS, Pug)
- `src` attribute — import external files

### 3.2 Design Rationale

Vue's SFC design (per Vue.js docs and style guide):
1. **Colocation of inherently coupled concerns** — template, logic, and style for one component
2. **Pre-compiled templates** — compile-time optimizations (hoisting, patch flags)
3. **Component-scoped CSS** — prevents style leakage
4. **HMR** — hot module replacement during development
5. **IDE support** — language servers understand SFC structure

### 3.3 DX Research
- Angular 2024 developer survey: ~90% satisfaction, but "double imports" and boilerplate remain pain points
- Vue style guide recommends: one component per file, PascalCase filenames, scoped styles, <200 lines
- HCI study (JISEM, Dec 2024): "affordance coherence" and "diagnostic translucency" predict reduced debugging time (MTTR)

### 3.4 Relevance to NeoSPX

| Vue SFC Feature | NeoSPX Applicability |
|-----------------|----------------------|
| Sectioned SFC (template/script/style) | **High** — component tags (`<s:ui:button />`) already imply component boundaries; SFC structure could organize component source |
| HTML as base syntax | **High** — ADR-005's "node-free" principle aligns with HTML-first |
| Scoped styles | **Medium** — NeoSPX's `<dg:style>` (per OD-09) is similar; SCSS framework authoring model |
| Pre-compilation | **High** — ADR-005 requires OPcache-friendly compiled output |
| Custom blocks | **Medium** — could map to NeoSPX directives (`@persist`, `~setup`) |
| `lang` pre-processors | **Low** — NeoSPX is PHP-native; no pre-processor needed |
| `src` imports | **Medium** — component composition via file references |

---

## 4. Svelte 5 — Runes and `.svelte` Format

### 4.1 Syntax

```svelte
<script>
  let count = $state(0);
  let doubled = $derived(count * 2);
</script>

<button onclick={() => count++}>
  {count} (doubled: {doubled})
</button>

<style>
  button { color: red; }
</style>
```

### 4.2 Key Innovation: Runes

Svelte 5 replaced implicit reactivity (`let` → reactive, `$:` → derived) with **explicit runes** (`$state`, `$derived`, `$effect`, `$props`, `$bindable`). This was a major DX change:
- **Pros:** Explicit, no magic, easier to understand for beginners, works in `.js`/`.ts` files too
- **Cons:** More verbose, breaking change from Svelte 4, learning curve for existing users

### 4.3 Relevance to NeoSPX

| Svelte Feature | NeoSPX Applicability |
|----------------|----------------------|
| Explicit reactivity runes | **Medium** — NeoSPX's `@persist` is similar (explicit serialization directive) |
| `.svelte.js` (logic modules) | **Low** — NeoSPX is template-focused, not logic-focused |
| Snippets (replace slots) | **Medium** — component composition patterns |
| No VDOM (compile to DOM) | **High** — aligns with ADR-005's "compiled native PHP" approach |

---

## 5. Astro — Islands Architecture

### 5.1 Syntax

```astro
---
// Server-side script (frontmatter)
const title = "Hello";
---
<html>
  <body>
    <h1>{title}</h1>
    <Counter client:load />  <!-- Client island -->
  </body>
</html>
```

### 5.2 Key Innovation: Islands

Astro's **islands architecture** sends zero JavaScript by default. Interactive components are "islands" that hydrate independently via `client:*` directives (`client:load`, `client:idle`, `client:visible`).

### 5.3 Relevance to NeoSPX

| Astro Feature | NeoSPX Applicability |
|---------------|----------------------|
| Code fence frontmatter (`---`) | **High** — NeoSPX's `~setup` block is similar; could use frontmatter-style separation |
| Islands (selective hydration) | **High** — ADR-005's Reactive Bridge (`s-data` attributes) is essentially islands: server renders HTML, client hydrates specific regions |
| Zero JS by default | **High** — aligns with "node-free" principle |
| Multi-framework islands | **Low** — NeoSPX is PHP-only |

---

## 6. Marko — Dual Authoring Modes

### 6.1 Syntax (HTML Mode)

```marko
<div class="greeting">
  Hello ${name}!
</div>

<script>
  // Component logic
</script>

<style>
  .greeting { color: red; }
</style>
```

### 6.2 Syntax (Concise Mode)

```marko
div.greeting
  -- Hello ${name}!
```

### 6.3 Key Innovation: Dual Modes

Marko supports **two authoring modes** for the same component:
- **HTML mode** — standard HTML with Marko extensions
- **Concise mode** — indentation-based, minimal punctuation

Both compile to the same output. This is the **closest published precedent** for NeoSPX's "multiple authoring syntaxes through syntax drivers" (SPX report §2.1).

### 6.4 Relevance to NeoSPX

| Marko Feature | NeoSPX Applicability |
|---------------|----------------------|
| Dual authoring modes | **Very High** — directly validates NeoSPX's syntax-driver approach |
| HTML-first base | **High** — ADR-005's "node-free" principle |
| Attributes as JS expressions | **Medium** — NeoSPX uses PHP expressions (Latte strategy) |
| Server/client/static statements | **Medium** — could inform NeoSPX's component lifecycle |

---

## 7. Lit — Tagged Template Literals

### 7.1 Syntax

```javascript
import { LitElement, html } from 'lit';

class MyElement extends LitElement {
  render() {
    return html`<p>Hello ${this.name}</p>`;
  }
}
```

### 7.2 Key Innovation: No Custom Syntax

Lit's claim: **"No custom syntax, no compilation required."** It uses JavaScript tagged template literals — standard JS that any JS developer already knows.

### 7.3 Relevance to NeoSPX

| Lit Feature | NeoSPX Applicability |
|-------------|----------------------|
| No custom syntax (standard language) | **High** — Latte's "reuse PHP expressions" is the same philosophy |
| No build step required | **Medium** — ADR-005 doesn't require build step for templates, but OPcache preload benefits from compilation |
| Shadow DOM scoping | **Low** — server-side rendering focus |

---

## 8. HTMX / Alpine.js — HTML-First Approach

### 8.1 HTMX Syntax

```html
<button hx-get="/api/data" hx-target="#result" hx-swap="innerHTML">
  Load Data
</button>
<div id="result"></div>
```

### 8.2 Key Characteristics

- **Pure HTML attributes** — no new syntax, no build step, no compilation
- **Server-driven** — server returns HTML fragments, not JSON
- **Progressive enhancement** — works without JavaScript (degrades gracefully)
- **Limitations:** "Attribute soup" at scale; reusability limited; complex state management difficult

### 8.3 Relevance to NeoSPX

HTMX demonstrates that **HTML-first is viable for simple interactions** but hits limits for complex state. NeoSPX should:
- Support HTML-first authoring (components as HTML tags)
- Provide structured directives for complex logic (`@if`, `@foreach`, `@persist`)
- Not force a build step for simple templates (but allow compilation for optimization)

---

## 9. Human + AI Dual Audience — Emerging Patterns

### 9.1 AI-Friendly Design System Patterns (2025-2026)

| Pattern | Source | Description |
|---------|--------|-------------|
| **Semantic props (enums over booleans)** | Hashnode (2025) | Machine-readable component APIs; agents can reason about valid values |
| **Structured tokens (JSON/YAML)** | DSDS (GitHub, 2025) | Design tokens in machine-readable format with `for: human\|agent\|all` audience tagging |
| **Component metadata JSON** | AWS Image Builder docs (2025) | Schema-versioned component documents with validation |
| **Minimal formatting** | Mintlify (2025-10) | Clean markdown/YAML frontmatter for AI+human docs |
| **Code-like serialized formats** | Retool ToolScript | Formats LLMs parse natively (not prose) |

### 9.2 DSDS (Design System Documentation Schema)

DSDS (github.com/designsystemdocs/dsds) is particularly relevant:
- **YAML-based** document format with `schema_type` field
- **JSON Schema validation** for structure
- **`for: human|agent|all`** sections for audience-specific content
- **Points to other formats** rather than restating (single source of truth)
- **Graph of entries** with dependency relationships

This is the closest published analog to NeoSPX's "public, language-neutral component document" as an interchange contract.

### 9.3 Relevance to NeoSPX

| Pattern | NeoSPX Applicability |
|---------|----------------------|
| `for: human\|agent\|all` audience tagging | **Very High** — NeoSPX's component document could include audience-specific sections |
| Machine-readable component metadata | **Very High** — aligns with "stable interchange contract for third-party tools" |
| JSON Schema validation | **High** — research candidate (not approved) for component document validation |
| Single source of truth (point, don't restate) | **High** — component document as canonical source; views derive from it |

---

## 10. Component Document / Interchange Formats

### 10.1 AWS Image Builder Component Documents

- **Format:** YAML or JSON
- **Structure:** `schemaVersion`, `phases`, `steps` with `action`, `inputs`, `name`
- **Validation:** Editor-based schema validation
- **Relevance:** Schema-versioned component documents with phased execution — similar to NeoSPX's compile pipeline stages

### 10.2 BugBug Component Documents

- **Format:** YAML with `schema_type` envelope
- **Validation:** JSON Schema in CI
- **Relevance:** Schema versioning + CI validation pattern

### 10.3 OSCAL (Open Security Controls Assessment Language)

- **Format:** Lossless JSON/YAML interchange
- **Relevance:** Demonstrates that JSON↔YAML lossless conversion is achievable with disciplined schema design

### 10.4 Relevance to NeoSPX's Component Document

The emerging pattern is clear:
1. **Schema-versioned** documents (AWS, BugBug, DSDS)
2. **JSON Schema validation** (AWS, BugBug, DSDS)
3. **YAML for human authoring, JSON for machine interchange** (AWS, OSCAL)
4. **Explicit audience tagging** (DSDS)
5. **Single source of truth with derived views** (DSDS)

This reinforces the SPX report's recommendation: **JSON-first canonical serialization with YAML as supported authoring format**, validated by JSON Schema 2020-12, with schema versioning and audience-specific sections.

---

## 11. Synthesis: What NeoSPX Should Learn

### 11.1 From Vue SFC
- **Sectioned SFC pattern** is proven and familiar — template/script/style sections
- **HTML as base syntax** lowers barrier for designers
- **Scoped styles** prevent leakage (maps to `<dg:style>` / OD-09)
- **Pre-compilation** enables optimizations (aligns with ADR-005/ADR-010)

### 11.2 From Svelte 5
- **Explicit over implicit** — runes replaced magic; NeoSPX's `@persist` should be similarly explicit
- **Single canonical form** — no shorthand variants (Anka principle)

### 11.3 From Astro
- **Code fence frontmatter** (`---`) — could inform NeoSPX's `~setup` block separation
- **Zero JS by default** — aligns with "node-free" principle
- **Islands = selective hydration** — maps to Reactive Bridge (`s-data` attributes)

### 11.4 From Marko
- **Dual authoring modes** — directly validates NeoSPX's syntax-driver approach
- **HTML-first with concise alternative** — human-friendly default, power-user option

### 11.5 From HTMX/Alpine
- **HTML-first is viable for simple cases** but hits reusability limits
- **No build step** is a significant DX advantage
- **Progressive enhancement** — templates should work without compilation

### 11.6 From DSDS/AWS/BugBug
- **Schema-versioned component documents** with JSON Schema validation
- **Audience tagging** (`for: human|agent|all`)
- **YAML for authoring, JSON for interchange** (lossless conversion achievable)

### 11.7 From AI-Friendly Design Research
- **Semantic props** (enums over booleans) for machine reasoning
- **Structured metadata** in machine-readable format
- **Minimal formatting** for LLM consumption
- **Retrieval pipelines** for component discovery

---

## 12. Open Questions (Owner Decision Required)

1. Should NeoSPX adopt a sectioned SFC format (like Vue/Svelte) or remain directive-based (like Blade/Twig)?
2. Should NeoSPX support dual authoring modes (like Marko) from v1 or add them later?
3. Should the component document include `for: human|agent|all` audience tagging (like DSDS)?
4. Should NeoSPX templates be build-free (like HTMX/Lit) or compiled (like Vue/Svelte)?
5. Should NeoSPX support scoped styles (like Vue) or delegate to the SCSS framework (OD-09)?

---

## 13. Source Index

| # | Source | Date | Section |
|---|--------|------|---------|
| 1 | Vue SFC Syntax Specification (vuejs.org) | 2025 | §3 |
| 2 | Vue Style Guide (vuejs.org) | 2025 | §3.3 |
| 3 | Svelte 5 Docs (svelte.dev) | 2025 | §4 |
| 4 | Astro Docs (docs.astro.build) | 2025 | §5 |
| 5 | Marko Docs (markojs.com) | 2025 | §6 |
| 6 | Lit Docs (lit.dev) | 2025 | §7 |
| 7 | HTMX (htmx.org) | 2025 | §8 |
| 8 | Alpine.js (alpinejs.dev) | 2025 | §8 |
| 9 | phpsfc (github.com/ahwm/phpsfc) | 2025 | §2, §3.4 |
| 10 | Angular 2024 Developer Survey | 2025-01 | §3.3 |
| 11 | JISEM HCI Study (Dec 2024) | 2024-12 | §3.3 |
| 12 | Component Party (component-party.dev) | 2025 | §4 |
| 13 | DSDS (github.com/designsystemdocs/dsds) | 2025 | §9.2, §10 |
| 14 | AWS Image Builder Component Docs | 2025 | §10.1 |
| 15 | BugBug Component Docs | 2025 | §10.2 |
| 16 | Hashnode AI-Friendly Design Systems | 2025 | §9.1 |
| 17 | Mintlify LLM-Friendly Docs | 2025-10 | §9.1 |
| 18 | Retool ToolScript | 2025 | §9.1 |
| 19 | ADR-005 (repo) | 2026-08-04 | §1, §3.4 |
| 20 | SPX-NAMING-COLLISION-REPORT.md (repo) | 2026-10-20 | §1 |
| 21 | SPX-EASE-OF-USE-RESEARCH.md (repo) | 2026-10-20 | §1 |

---

**End of research deliverable.** This document is research, not an implementation requirement, not an ADR. All recommendations require owner approval. No code, ADR, frozen contract, or governance file has been modified.