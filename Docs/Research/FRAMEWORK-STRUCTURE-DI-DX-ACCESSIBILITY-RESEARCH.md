# Framework File Structures, Dependency Injection, DX & Accessibility Research

**Created:** 2026-10-22
**Status:** Research deliverable — NOT an implementation requirement, NOT an ADR, NOT an authorization to change repository structure, frozen contracts, or governance files.
**Parent agenda:** `RESEARCH-AGENDA.md` (root)
**Related:** `SPX-EASE-OF-USE-RESEARCH.md`, `RESEARCH-INDEX.md`, `Architecture/INDEX.md`, `FROZEN-CONTRACTS.md`
**Research question (owner-supplied context):** Inform DGLab's Core/Hub/Spoke monorepo architecture and SuperPHP's design with evidence on how other frameworks organize files, inject dependencies, onboard developers, and handle accessibility — so that "easy to use" is evidence-based, not aesthetic.

---

## 1. Executive Summary

1. **Convention vs. flexibility is a spectrum, not a binary.** Spring Boot/NestJS enforce layered architecture via DI + annotations; FastAPI/Express leave it to the developer. Research consistently shows structured frameworks reduce hidden architecture costs (days spent deciding folders, inconsistent patterns, team friction) that accumulate in "freedom" frameworks (BSWEN 2026-03-24).
2. **Feature-based / vertical-slice organization outperforms layer-based for change locality.** Empirical comparisons (vertical-slice ADRs, Feature-Sliced Design, FFCA) show a single business change touches one folder instead of 5+, improving onboarding from 2–4 weeks to 1–2 days and making deletion trivial.
3. **DI is converging on PSR-11 (PHP) / constructor injection + autowiring.** PSR-11 standardizes only *fetching* (`get`/`has`), deliberately leaving configuration to implementations. Laravel, Symfony, NestJS all autowire from type-hints; service locator is discouraged.
4. **Developer experience is empirically measurable** across 16 facets (JSS 2025 mapping of 231 articles) — emotions, values, perceptions — using SUS, ASQ, task-completion, error rates. Sendbird's onboarding study: SUS 71.11 (acceptable) vs 56.67 (unacceptable) for competing tools.
5. **Frameworks do not make apps accessible; patterns and enforcement do.** WebAIM Million 2024: 95.9% of home pages fail accessibility checks regardless of framework. Accessibility must be encoded in component primitives + linting + CI, not assumed from framework choice.
6. **Power vs. simplicity is best resolved by Alan Kay's principle:** "simple tasks must be simple, complex ones must be possible." Don Norman: complexity is good; confusion is the enemy. Maeda's Law 10: "subtract the obvious, add the meaningful."
7. **For DGLab specifically:** the Core/Hub/Spoke rings already enforce dependency direction; the gap is consistent *within-ring* file conventions and a published onboarding path. For SuperPHP: component-level a11y defaults + small core + progressive disclosure + formal grammar (per `SPX-EASE-OF-USE-RESEARCH.md`).

---

## 2. Framework Directory Structures Comparison

### 2.1 Representative layouts

| Framework | Paradigm | Canonical top-level layout | Architectural "flavor" |
|---|---|---|---|
| **Laravel 12** | PHP, MVC-ish | `app/Http, Models, Services`, `config/`, `database/`, `routes/`, `tests/` | Active Record (Eloquent); flexible |
| **Symfony 7** | PHP, component-based | bundles/components; `src/`, `config/`, `public/` | Clean/Hexagonal friendly; high configurability |
| **Django** | Python, MVT | `project/`, `apps/<name>/{models,views,services,repositories,serializers,urls,tests}` | Data Mapper leaning; batteries included |
| **Rails** | Ruby, MVC | `app/{controllers,models,views,services}`, `config/`, `db/`, `lib/`, `spec/` | Active Record + Convention over Configuration |
| **Spring Boot** | Java | `src/main/java/com/.../{controller,service,repository}`, `resources/` | Layered/Clean/Hexagonal; enforced via DI+annotations |
| **Express** | Node.js | `src/{index,app,config,routes,controllers,services,models,middleware,utils}`, `tests/` | Minimal; freedom → hidden costs |
| **NestJS 11** | TS | `src/modules/<name>/{module,controller,service,repository,dto,entities,guards}` + `common/`, `config/` | Spring-like; modular + DI |
| **FastAPI** | Python | `app/{main,routers,services,repositories,domain,schemas,core,dependencies}` | Router-based; freedom at scale |
| **Django (Fast Django template)** | Python | `src/<pkg>/{core,entrypoints,foundation,infrastructure,ioc}` with per-feature `delivery/` subfolders | Hexagonal-ish with composition roots |

### 2.2 Key structural findings

- **Convention over Configuration (Rails)** gives fastest prototyping when aligned with common patterns; steepens learning for non-convention projects (Redberry 2025-10-12).
- **Layered (Spring/NestJS)** forces controllers→services→repositories separation; beginners can't easily create spaghetti code; costs are upfront and documented (BSWEN 2026-03-24).
- **Feature-based organization** (vertical slices) reduces "shotgun surgery": one business change → one folder; deletion = `rm -rf`; onboarding 1–2 days vs 2–4 weeks (pragmatic-franken ADR-0001).
- **Monorepo best practice** (Turborepo): `apps/` for deployables, `packages/` for libraries; one purpose per package; no nested packages; strong package boundaries with `exports` (Turborepo docs).
- **DGLab relevance:** DGLab already has Core/Hub/Spoke rings (six-ring Wheel). The reported LMS gaps (missing enrollment/progress services, unwired middleware) are consistent with "freedom" costs. A published, feature-sliced convention inside each ring would reduce them. **No change authorized** — this is research input only.

---

## 3. Dependency Injection Patterns & Container Design

### 3.1 PSR-11 (PHP-FIG)

- `Psr\Container\ContainerInterface` exposes only `get(string $id)` and `has(string $id)` (PSR-11 accepted).
- Scope is deliberately narrow: standardizes *fetching* only; configuration, autowiring, compilation, aliasing are implementation-specific (PSR-11 META §3.1–3.2).
- Service Locator usage is discouraged: don't inject the container to fetch own dependencies; inject dependencies directly (PSR-11 META §4).
- Laravel's container implements PSR-11; Symfony's DependencyInjection component compiles the container before use; PHP-DI adds `make()`/`call()`/`injectOn()` beyond PSR-11.

### 3.2 Patterns across frameworks

| Mechanism | Laravel | Symfony | NestJS | Spring Boot | FastAPI |
|---|---|---|---|---|---|
| Autowiring | type-hint based | `autowire: true` from `__construct()` type-hints | `@Injectable()` + constructor | constructor injection required | `Depends()` function injection |
| Configuration | PHP code / bindings | YAML/PHP/XML + compiler passes | `@Module({providers})` | annotations + `@Configuration` | `Depends()` factories |
| Compile-time | runtime | `compile()` freezes container | runtime | runtime (with AOT option) | runtime |
| Private services | n/a | private by default (no `$container->get()`) | n/a | n/a | n/a |

### 3.3 Design principles extracted

1. **Constructor injection is the default**; setter/property injection only where framework constraints force it (Spring's enforced constructor injection; PHP-DI's `injectOn` as escape hatch).
2. **Autowiring from type-hints** removes most configuration; explicit configuration remains for ambiguity (multiple implementations of same type).
3. **Compile/freeze the container** before runtime to validate wiring and enable optimization (Symfony).
4. **Don't pass the container as Service Locator** — hides dependencies, hurts testability, couples to container naming.
5. **Delegate lookup** (PSR-11 proposed) lets multiple containers share entries — relevant for DGLab's composition root spanning rings.

### 3.4 DGLab relevance

DGLab's reported missing pieces (`MySQLEnrollmentRepository`, `EnrollmentApplicationService`, `ProgressRepositoryInterface`, unwired `AuthMiddleware`) are composition-root tasks. Research supports: one composition root per ring boundary, constructor injection, autowiring where unambiguous, explicit registration for ports. **No implementation authorized** by this research.

---

## 4. Developer Experience (DX) Research

### 4.1 Empirical foundations

- **Dev-X has 16 facets** spanning emotions, values, perceptions; a 2025 JSS mapping study of 231 articles identifies gaps in facet coverage and overuse of surveys (JSS 2025-10-07, DOI 10.1016/j.jss.2025.112648).
- **Usability evaluation methods** (UEMs) need adaptation for DX: creating software ≠ using software (Aalto thesis 2021).
- **Learnability** is a distinct usability attribute with its own metrics and evaluation protocol (Autodesk survey).
- **API learning obstacles** (ACM Empirical SE): the top obstacles are documentation and learning resources — documentation of intent, code examples, scenario matching, penetrability, format/presentation (ACM 2010).
- **Optimized API documentation** (empirical test) reduced task errors and planning/execution time vs. non-optimized docs (ACM 2020-09-29).
- **Onboarding** benefits from solid docs + working samples + dedicated resource site; SUS and ASQ are standard metrics (Sendbird 2022-07-21: SUS 71.11 acceptable vs 56.67 unacceptable).
- **Newcomer onboarding in OSS**: 4 of 15 task-related recommendations positively correlate with first-patch acceptance; 3 are newcomer-specific and should be abandoned after initial contribution (IEEE TSE 2025-03-13).
- **LLM-assisted onboarding** (SPAC-B) scored 4.6/5 relevance, 4.3/5 completeness/correctness; 8/10 developers found it helpful (ACM FSE 2025-07-28).

### 4.2 DX principles for frameworks

1. **Documented conventions beat freedom** at scale; hidden costs of freedom accumulate (BSWEN 2026-03-24).
2. **Working out-of-the-box** (Rails "working out of the box") accelerates ramp-up; Laravel balances conventions with expressive APIs.
3. **Clear errors with file/line** (Twig) and **clean errors** reduce debugging time.
4. **Progressive disclosure**: small core, advanced features opt-in (Kölling 2024, Wirth lineage).
5. **Measure, don't assume**: SUS + task-completion + error rates across skill tiers.

---

## 5. Accessibility (a11y) in Framework Design

### 5.1 Empirical findings

- **Frameworks don't make apps accessible; patterns do.** React/Vue/Angular/Svelte each provide tools that help or hinder; none automatically produce accessible apps. Client-side routing breaks the page-load model screen readers expect; hydration causes timing mismatches (TestParty 2026-02-14).
- **WebAIM Million 2024:** 95.9% of home pages fail accessibility checks — framework choice isn't solving the problem.
- **Templates account for 40–50% of web content**; template-level fixes yield 17–39% reduction in reported problems (W3C 2011 symposium) — relevant to SuperPHP's component tags.
- **Design systems act as accessibility enforcement layer:** accessible labeling as default, correct HTML structure/semantics, predefined contrast tokens (ACM Web for All 2026-06-02).
- **Accessibility barriers in UI frameworks** significantly affect programmers with visual impairments: inaccessible GUI builders force manual coding, inaccessible components break debugging/testing, lack of official a11y documentation forces trial-and-error (UI frameworks accessibility study, from.so PDF).
- **Lion design system:** covers keyboard accessibility, semantics, tested against WCAG 2.1 AA across screen readers; but visual design remains the developer's responsibility (lion.js.org 2021-12-07).

### 5.2 Framework design implications

1. **Encode a11y in component primitives** (keyboard, semantics, ARIA) so it's the default, not an opt-in.
2. **Provide linting + CI enforcement** (jsx-a11y, axe-core, Lighthouse) — "shifting accessibility left" (Digital.gov).
3. **Document screen-reader compatibility** officially; don't claim "out-of-the-box accessible" without evidence.
4. **Avoid framework restrictions** that prevent accessible patterns; some frameworks are "more forgiving" (Smashing Magazine 2021-03-16).
5. **For SuperPHP:** component tags (`<s:ui:button />`) should carry built-in a11y defaults; generated HTML should be auditable; template-level a11y (40–50% of content) is high-leverage.

---

## 6. "Easy to Use" Framework Design Principles

### 6.1 Power vs. simplicity

- **Alan Kay:** "simple tasks must be simple, and complex ones must be possible" — the trade-off between power of expression and simplicity (Substrates paper, CHI 2025).
- **Don Norman:** complexity is good; confusion is the enemy. Simplicity ≠ fewer features; it's understandability (jnd.org).
- **John Maeda (Laws of Simplicity):** Reduce, Organize, Time, Learn, Differences, Context, Emotion, Trust, Failure, The One ("subtract the obvious, add the meaningful") (MIT Press).
- **BetterExplained four-quadrant model:** Simple (easy to understand) vs Complicated (hard to understand); Advanced (powerful) vs Basic (limited). Goal: Simple + Advanced, avoid Complicated.
- **Maeda's SHE:** Shrink, Hide, Embody — after reducing functionality, shrink size, hide visibility, make remainder appealing.
- **Firefox design values:** "Balances power and simplicity" — 80/20/2 rule: default to surface minimalism, easy access to the rest; user-agency and understanding, not just less.

### 6.2 Principles for frameworks

1. **Manage complexity, don't eliminate it** — modularization, mapping, conceptual models (Norman).
2. **Familiarity reduces learning cost** — reuse existing language skills (Latte: "you already know the syntax"; Play/Twirl: "not a new language").
3. **Small core + progressive disclosure** (Kölling 2024): most users won't become professionals; keep core small, keyword-based, orthogonal.
4. **Helpful defaults + customization** (Kolibri design system).
5. **Clear errors with location** (Twig) and **clean errors** reduce frustration.
6. **Measure ease** with SUS across skill tiers rather than assuming.

---

## 7. File Organization Patterns

### 7.1 Pattern taxonomy

| Pattern | Core idea | Trade-off | Exemplars |
|---|---|---|---|
| Convention over Configuration | Strong defaults; naming/architecture/scaffolding | Fast if aligned; steep if divergent | Rails, Laravel |
| Layer-based (technical) | `controllers/`, `services/`, `repositories/` | Familiar; shotgun surgery at scale | Express, Django default |
| Feature-based / vertical slices | One folder per feature owns all layers | High locality; needs discipline | Feature-Sliced Design, FFCA, pragmatic-franken |
| Domain-driven | `domains/<name>/{components,services,hooks}` | Mirrors business logic; multi-team | React domain-driven, ABP modules |
| Atomic Design | atoms/molecules/organisms/templates/pages | UI consistency; for design systems | Storybook, design systems |
| Component-focused (CFA) | Scoped folders per component; no global barrels | Predictable imports; fast builds | dev.to CFA article |

### 7.2 Vertical slices evidence

- **ADR-0001 (pragmatic-franken):** "Architecture is optimised for deletion, not reusability." Onboarding 1–2 days vs 2–4 weeks; feature deletion = one `rm -rf` (ADR-0001).
- **Feature-Sliced Design:** feature directories contain everything (queries, components, hooks, state, utils); co-located; no global barrel files; top-down dependency flow (dev.to, geistjs, mozia.dev).
- **FFCA (Very Good Ventures):** dependency rules flow one direction; `features/` self-contained; `shared/` only for true shared; cycles forbidden (VGV engineering).
- **Derived Modular Architecture:** staged growth (file → flat folder → segments → split → package); cross-module imports target `*/public/*` direct file paths; no barrels (spec/modules.md).
- **Module architecture (ABP):** split Domain/Domain.Shared, Application/Application.Contracts, per-ORM infrastructure, HttpApi/HttpApi.Client, Web — explicit dependency direction (ABP docs).

### 7.3 DGLab relevance

DGLab's Core/Hub/Spoke rings enforce *ring-level* dependency direction. Within rings, the reported LMS work lacks a published convention. Research supports adding a documented feature-sliced convention *inside* each ring (e.g., `Hub/Lms/Enrollment/{entity,repository,service,controller}`) — but this requires an owner decision and must not be introduced ad hoc during feature work.

---

## 8. Framework Onboarding & Learning Curve Research

### 8.1 Empirical findings

- **Structured frameworks reduce hidden costs**: time spent deciding folder structure, inconsistent patterns, learning architecture through mistakes, team friction (BSWEN 2026-03-24).
- **Spring Boot most structured out-of-box** (Reddit consensus cited); NestJS similar with TS/Angular patterns; Django batteries-included but heavy; FastAPI low barrier to start, high at scale.
- **OSS newcomer onboarding**: 4 recommendations positively correlate with first-patch acceptance; 3 are newcomer-specific (IEEE TSE 2025-03-13).
- **New-hire comprehension**: short modelling interventions (5 sessions) significantly help below-median new hires (+15 pts) but not above-median (arXiv 2025-10-08).
- **LLM-assisted onboarding** (SPAC-B) improves implementation planning; 8/10 found helpful (ACM FSE 2025-07-28).
- **Onboarding as dynamic learning**: formal + informal + self-regulated learning combined; individualized paths (Frontiers 2025-07-07).
- **Software solutions for onboarding**: recommendation systems most prevalent; many barriers remain unaddressed; diversity/inclusion mostly addresses experience level (arXiv 2024-08-28).

### 8.2 Onboarding best practices for frameworks

1. **Working out-of-the-box** with a documented quickstart (Sendbird: "if it doesn't build right out of the box, I'm not gonna fiddle").
2. **Solid docs + working samples + dedicated resource site** (Sendbird study).
3. **Measure SUS/ASQ** across skill tiers; target acceptable range (≥68/100).
4. **Provide architecture templates** so teams don't reinvent structure (BSWEN).
5. **LLM-friendly documentation** (markdown, 80–90% token reduction) aids both human and AI onboarding (Fern 2026-03).

---

## 9. Component-Based Architecture File Organization

### 9.1 Findings

- **Co-location** groups all files for a component (tsx, stories, tests, styles, assets) in one folder; avoids scattered navigation (modularcode styleguide 2015).
- **Scoped folders** per component with related styles/types/logic; no global barrel files (CFA article dev.to 2025-04-27).
- **Start with everything in one file**; split only when it earns a boundary (~300 lines threshold) (digital-go-jp component rules).
- **No cross-component references** or premature abstraction; component bodies contain no logic (digital-go-jp).
- **Component barrel conventions**: explicit named exports by section; no `export *` (compozy directory layout).
- **Feature folders own their UI/hooks/services/types/styles/tests**; shared only when truly shared (3+ callers) (FaasJS, React pythonguides 2025-10-16).
- **Avoid deep nesting** (>3 levels) and `utils/` as junk drawer (frogrammer.co 2025-08-07).

### 9.2 DGLab / SuperPHP relevance

- SuperPHP's component document (stable interchange contract) maps naturally to component-scoped folders: one folder per component with authoring syntax, tests, and (future) tooling.
- DGLab's `packages/` already follows monorepo package boundaries; component-level conventions should be published before LMS frontend work (which the founder owns).

---

## 10. Module / Package Structure Best Practices

### 10.1 Monorepo principles (Turborepo, pnpm, Bun)

1. `apps/` for deployables, `packages/` for libraries (Turborepo).
2. One purpose per package; no nested packages (Turborepo).
3. Strong package boundaries: no relative imports across packages; public API via `exports` (monorepo.guide).
4. Few dependencies in root; install where used (Turborepo).
5. Namespace packages (`@org/name`) to avoid registry conflicts.
6. Lockfile required for reproducibility (Turborepo).
7. Only global settings live at root (monorepo.guide).

### 10.2 Module boundary patterns

- **Nx monorepo patterns**: swimlanes per domain; layer libraries (feature/ui/api/domain/util); shared kernel; access constraints enforce direction (manfredsteyer 2019).
- **ABP module architecture**: Domain/Domain.Shared, Application/Application.Contracts, per-ORM infrastructure, HttpApi/HttpApi.Client, Web — each with explicit allowed dependencies (ABP docs).
- **Hexagonal per app**: `domain/` (pure), `ports/` (interfaces), `application/` (use cases, injected deps), `adapters/` (one folder per vendor), `http/` (driving adapter), `server.ts` (composition root) — no DI container, hand-wired (Miragon ADR 0003).
- **Bun monorepo**: apps → feature packages → foundational packages; extract only after shape settles; a package that needs to know its caller isn't shared yet (sergiodxa.com).

### 10.3 DGLab relevance

DGLab's Core/Hub/Spoke rings already mirror the layered/module pattern. Research supports:
- Hand-wired composition root (Miragon ADR 0003) matches DGLab's reported need for "composition-root/DI wiring" without a heavy container.
- Ports/interfaces for persistence (already in DGLab: `ProgressRepositoryInterface` missing) align with hexagonal ports.
- One-purpose-per-package for `packages/` (Turborepo) aligns with monorepo best practice.

---

## 11. Cross-Cutting Synthesis: Implications for DGLab & SuperPHP

### 11.1 For DGLab Core/Hub/Spoke

1. **Publish a within-ring file convention** (feature-sliced or vertical-slice) before further LMS increments — reduces hidden architecture costs. *Owner decision required.*
2. **Hand-wired composition root** per ring boundary (no heavy DI container) — aligns with Miragon ADR 0003 and DGLab's reported gaps.
3. **Ports for persistence** (interfaces in Hub, implementations in Spoke/infrastructure) — already partially present; complete missing ones (Progress, Enrollment).
4. **Strong package boundaries** in `packages/` with `exports` — aligns with Turborepo/monorepo.guide.
5. **Onboarding path**: quickstart + working samples + SUS-measured docs; target ≥68/100.
6. **Accessibility as enforcement**: linting + CI + component primitives with a11y defaults (when frontend work begins; founder owns UI).

### 11.2 For SuperPHP

1. **Component-scoped folders** with co-located tests/tooling (per §9).
2. **Small core + progressive disclosure** (Kölling/Wirth) — aligns with `SPX-EASE-OF-USE-RESEARCH.md`.
3. **Built-in a11y defaults** in component tags (W3C template-level finding: 40–50% of content).
4. **Formal grammar (EBNF)** for tooling/constrained decoding (SynCode finding from prior research).
5. **Measure, don't assume**: SUS + comprehension benchmarks across skill/model tiers.

### 11.3 Tensions to resolve (owner decision required)

| Tension | Structured side | Flexible side | Proposed resolution |
|---|---|---|---|
| Convention vs. freedom | Enforced layers (Spring/NestJS) | Freedom (FastAPI/Express) | Publish convention but allow opt-out per ring with ADR |
| Feature-slice vs. layer | Vertical slices (one folder/feature) | Technical layers (controllers/services/repos) | Feature-sliced within rings; shared kernel in Core |
| Hand-wired vs. container | Miragon style (server.ts) | Symfony-style compiled container | Hand-wired composition root (matches DGLab gaps) |
| a11y default vs. freedom | Enforced primitives | Developer responsibility | Defaults + opt-out + linting |

---

## 12. Decisions NOT Made (Owner Decision Required)

- Whether to adopt a published within-ring file convention, and which pattern (feature-sliced vs. vertical-slice vs. hexagonal).
- Whether to use a compiled DI container (Symfony-style) or hand-wired composition root.
- Whether to enforce folder-width limits (e.g., 12 files) via architecture tests.
- Whether to add accessibility linting/CI to the backend pipeline (frontend is founder-owned).
- Whether to publish these findings into `Architecture/CrossCutting/RESEARCH-AGENDA.md` (authorization pending).
- Any change to frozen contracts, ADRs, or governance files.

---

## 13. Source Index (with dates)

| # | Source | Date | Used in |
|---|---|---|---|
| 1 | rest-api-blueprints (GitHub) — multi-framework DDD comparison | current | §2 |
| 2 | design-patterns framework-catalog (GitHub) | current | §2 |
| 3 | BSWEN "Structured Frameworks for Beginners" | 2026-03-24 | §2, §4, §8 |
| 4 | Redberry "Laravel vs Django/Rails/Symfony" | 2025-10-12 | §2 |
| 5 | Fast Django project structure | current | §2 |
| 6 | takt.dev "Architectural Flavor" | current | §2 |
| 7 | PSR-11 accepted + META (PHP-FIG) | accepted | §3 |
| 8 | SitePoint "Build Your Own DI Container" | 2016-02-12 | §3 |
| 9 | Symfony Service Container docs | current | §3 |
| 10 | PHP-DI container docs | current | §3 |
| 11 | Laravel Service Container docs | current | §3 |
| 12 | JSS "Empirical pathways to Dev-X" (231 articles) | 2025-10-07 | §4 |
| 13 | Aalto DX usability thesis | 2021-08-23 | §4 |
| 14 | Autodesk software learnability survey | 2023 | §4 |
| 15 | ACM "API learning obstacles" | 2010 | §4 |
| 16 | ACM "Optimizing API Documentation" | 2020-09-29 | §4 |
| 17 | IEEE "Programmer eXperience SLR" (73 articles) | 2019 | §4 |
| 18 | IEEE "Developer Productivity Part 5: Onboarding" | 2023-09 | §4, §8 |
| 19 | Sendbird onboarding UX benchmarking | 2022-07-21 | §4, §8 |
| 20 | Umich accessibility framework questions | current | §5 |
| 21 | web.dev accessibility patterns | current | §5 |
| 22 | Smashing "Client-Rendered Accessibility" | 2015-05-06 | §5 |
| 23 | TestParty "Accessibility in Modern JS Frameworks" | 2026-02-14 | §5 |
| 24 | UI frameworks accessibility (from.so PDF) | current | §5 |
| 25 | Lion accessibility blog | 2021-12-07 | §5 |
| 26 | MFA11y core accessibility principles | current | §5 |
| 27 | Digital.gov front-end accessibility | current | §5 |
| 28 | ACM "Shifting Accessibility Left" (W4A 2026) | 2026-06-02 | §5 |
| 29 | Firefox design values | current | §6 |
| 30 | Don Norman "Simplicity Is Not the Answer" | 2008-11-17 | §6 |
| 31 | John Maeda "Laws of Simplicity" (MIT Press) | 2006 | §6 |
| 32 | BetterExplained "Combining Simplicity and Complexity" | current | §6 |
| 33 | Interaction Substrates (CHI 2025) | 2025 | §6 |
| 34 | UI friction / ease-of-use report | 2006 | §6 |
| 35 | IVANBALL ADR-109 feature-by-folder | current | §7 |
| 36 | living-architecture ADR-002 folder structures | current | §7 |
| 37 | Vertical slices skill (mateonunez) | current | §7 |
| 38 | pragmatic-franken ADR-0001 vertical slices | current | §7 |
| 39 | VGV FFCA project structure | current | §7 |
| 40 | example-project-structure design decisions | current | §7 |
| 41 | maintainable-typescript feature owners | current | §7 |
| 42 | slides monorepo structure (mintlify) | current | §7 |
| 43 | tactical DDD monorepo (manfredsteyer) | 2019-08-26 | §7, §10 |
| 44 | FaasJS file conventions | current | §9 |
| 45 | dev.to Component-Focused Architecture | 2025-04-27 | §9 |
| 46 | mozia.dev system architecture patterns | 2025-02-09 | §9 |
| 47 | AkashJS project structure | current | §9 |
| 48 | modularcode styleguide (2015) | 2015-11-05 | §9 |
| 49 | frogrammer.co frontend structure | 2025-08-07 | §9 |
| 50 | geistjs Nuxt colocation | 2025-01-07 | §9 |
| 51 | compozy system directory layout | current | §9 |
| 52 | digital-go-jp component rules | current | §9 |
| 53 | pythonguides React structure | 2025-10-16 | §9 |
| 54 | Turborepo structuring docs | current | §10 |
| 55 | monorepo.guide "thinking in monorepos" | current | §10 |
| 56 | Turborepo best practices skill | current | §10 |
| 57 | Kriskowal "designing modules" | current | §10 |
| 58 | sergiodxa Bun monorepo | current | §10 |
| 59 | ABP module architecture | rel-1.0 | §10 |
| 60 | Miragon ADR 0003 hexagonal | current | §10 |
| 61 | derived-modular-architecture spec | current | §7, §10 |
| 62 | hub-spoke (mise) architecture | current | §10 |
| 63 | Frontiers onboarding learning (2025) | 2025-07-07 | §8 |
| 64 | IEEE OSS newcomer onboarding | 2025-03-13 | §8 |
| 65 | arXiv new-hire modelling onboarding | 2025-10-08 | §8 |
| 66 | arXiv software solutions for onboarding | 2024-08-28 | §8 |
| 67 | ACM SPAC-B LLM onboarding | 2025-07-28 | §8 |
| 68 | IEEE DL newcomer onboarding | 2024-01-12 | §8 |
| 69 | Emerald onboarding process review | 2024-07-18 | §8 |
| 70 | Aalto integrated onboarding framework | 2025-04-23 | §8 |

---

**End of research deliverable.** This document is research, not an implementation requirement, not an ADR, not a rename or restructuring authorization. All recommendations require owner approval. No code, ADR, frozen contract, or governance file has been modified by this research. Research remains independent of LMS implementation PRs.
