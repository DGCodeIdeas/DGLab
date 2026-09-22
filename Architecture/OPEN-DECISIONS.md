# OPEN-DECISIONS.md

**Purpose.** This file records every fork the consolidation pass encountered but could **not** close
without an owner's decision. Per governance Rule 9, open questions are recorded here, never silently
resolved. When a decision is made, move the entry to *Resolved* and cite the deciding artefact.

> Entries are intentionally concise. Each lists: the fork, why it is open, the options, the owner, and
> the decision route.

---

## Open

### OD-11 — Cooldown policy under solo operation (mini cooldowns interim)
- **Fork:** `SDLC-AGRD.md` §7 fixes cooldowns at 2 weeks for solo operation. Earlier session directive deferred all cooldowns "until sometime next year" to keep Milestone 0 unblocked. Refinement: short integration checkpoints *within* a lap (between depth bumps, between adjacent Steps in the build order) are now acceptable, even though the full 2-week *between-lap* cooldowns remain deferred.
- **Definition — "mini cooldown":** A bounded checkpoint of approximately 1 working day (≤4 hours active work) inserted between Steps within a single lap, *not* a substitute for the §7 between-lap cooldown. Scope is limited to: (a) worklog reconciliation for the just-shipped component(s), (b) interface-freeze audit against `INDEX.md` §5.1, (c) optional refactor backlog triage limited to the just-shipped component, (d) lint-scope expansion *only if* trivially small. Mini cooldowns do **not** consume OD-triage time, do **not** run marketer/media review (Cooldown 0 only), and do **not** count toward the §7 cooldown total.
- **Decision:** Accept mini cooldowns as the interim operating mode. The §7 2-week between-lap cooldown remains deferred until next year per the earlier directive; mini cooldowns fill the integration-discipline gap that pure skipping opened.
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Recorded here as OD-11. No ADR — this is an operating-mode refinement, not an architectural change. Will be re-evaluated when the §7 cooldown is reinstated next year; at that point mini cooldowns either fold into the standard cadence or are retired.

### OD-08 — Async I/O library choice (ReactPHP vs Amp vs Swoole)
- **Fork:** The Fiber-based runtime (ADR-017) requires an event loop / async I/O library as its "hardware abstraction layer." Three candidates.
- **Option A — ReactPHP:** Mature, largest ecosystem, PSR-7/15/17 native. Blocking-implicit model (promise chains). Largest community.
- **Option B — Amp:** Fiber-native design, cleaner API, smaller ecosystem. Better alignment with PHP 8.1+ Fiber semantics.
- **Option C — Swoole:** Runtime replacement (not a library), highest performance, but locks DGLab to Swoole's runtime model. Incompatible with FrankenPHP.
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Deferred until ADR-017 interfaces are proven in Phase 0. `DGLAB-AS-OS-RUNTIME.md` defines a library-agnostic `EventLoopInterface` as the abstraction boundary.

### OD-09 — DGLab SCSS framework: scope, inspirations, SuperPHP authoring model
- **Fork:** Build a personalized SCSS framework for DGLab, or adopt an existing one (Tailwind, Bootstrap, Bulma, etc.). Resolution of the "full control" question for the styling layer.
- **High-level decision (deferred implementation):** Build custom, per the "full control" rationale established by `ADR-005` (SuperPHP vs. Blade/Twig) and continued in `CORE-02` (custom DI vs. Symfony DI), `CORE-03` (custom event dispatcher). The styling layer is part of the application stack DGLab controls end-to-end.
- **Interim decision (active now):** Use Tailwind + hand-rolled custom styles for any UI work that needs styling before the custom framework ships. Tailwind is consumed as a build-time tool, not a runtime dependency. Custom styles live alongside Tailwind output. This unblocks UI work without committing to the full custom framework build before its prerequisites are ready.
- **Authoring model (when implemented):** SuperPHP components with `<dg:style>` blocks — the Vue SFC / Svelte / Astro pattern. Component-scoped styles compiled at build time, no runtime CSS-in-JS overhead. NOT raw `.scss` files; NOT CSS-in-JS. This requires `<dg:style>` block support in `CORE-12` (SuperPHP compiler), which is not currently in `CORE-12`'s blueprint and must be added before implementation starts.
- **Four-layer model (when implemented):**
  1. **Token binding layer** — SuperPHP components that import `HUB-26`'s frozen token manifest (`Architecture/Cooldown0/HUB-26-theme-tokens.md`) and expose them as SCSS variables + CSS custom properties. This is the bridge between HUB-26 (Markdown spec) and runtime CSS. **HUB-26 is frozen (Cooldown 0) and is NOT redefined by this framework — the framework consumes it.**
  2. **Utility layer** — SuperPHP mixins/partials that generate atomic utility classes (`dg-u-flex`, `dg-u-gap-4`). Hand-rolled, not Tailwind's JIT engine. ~200-300 lines of SCSS in SuperPHP `<dg:style>` blocks. Naming convention stolen from Tailwind (`text-sm`, `flex`, `gap-4`); JIT-purge concept stolen from Tailwind; **not** Tailwind's Node toolchain.
  3. **Component layer** — SuperPHP components (`<dg:button>`, `<dg:card>`, `<dg:nav>`) that emit markup + scoped SCSS. Bootstrap-style opinionated component patterns (`.btn`, `.card`, `.navbar`); Bulma-style modifier syntax (`is-primary`, `has-shadow`); MD3 state-layer + elevation concepts. **Not** Bootstrap's jQuery.
  4. **Class-less content layer** (optional) — Pico.css-inspired semantic styling for content pages where component classes are overkill (marketing pages, ESPOKE-05-style surfaces, blog posts). Lives in a single SuperPHP `<dg:style global>` block, not per-component. Opt-in — doesn't impose on sites that don't import it.
- **Inspirations (synthesized from a survey of 50+ CSS frameworks):**
  - **Tailwind** — utility-class naming convention; JIT-purge concept; config-as-token-source. NOT the Node toolchain.
  - **Bootstrap** — component class patterns (`.btn`, `.card`, `.navbar`); 12-col grid; opinionated form/table defaults. NOT the jQuery.
  - **MD3 / Material Components Web** — token system (already in HUB-26); state-layer concept; elevation as `box-shadow` tiers; ripple as `::after` pseudo. HUB-26 is already MD3-aligned; this extends it.
  - **Pico.css** — class-less semantic styling for `<article>`, `<form>`, `<nav>`, `<table>`. Needed for ESPOKE-05 marketing pages and content-heavy External Spokes.
  - **Open Props** — `--*` custom property as first-class; namespace conventions (`--color-*`, `--size-*`); promo of CSS vars over SCSS vars for runtime theming. Aligns with HUB-26's already-frozen token approach.
  - **Carbon / Primer** — design-system organization: tokens → primitives → components → patterns. Documentation structure. Scales; gives the framework a navigable shape.
  - **Bulma** — modifier syntax (`is-primary`, `has-shadow`); column system naming. Cleaner class names than Bootstrap's `.btn-primary` BEM-ish hybrid.
- **Distribution model (for External Spokes):** External Spokes cannot `composer require` a Hub-tier package — they're external apps running outside the Hub's process. Three candidate paths:
  - **(a) Tarball on GitHub Release** — Loom already creates releases. Add a build step that produces `dglab-styles-vX.Y.Z.tar.gz` containing compiled CSS + compiled SuperPHP components. External Spokes download at build time. **Simplest. No registry. Preferred for initial release.**
  - **(b) npm package mirror** — publish `@dglab/styles` to npm via a release workflow. External Spokes `npm install`. Standard tooling for JS-side builds. Requires npm account + a release workflow that publishes.
  - **(c) Composer path-repository in a separate dist repo** — like `DGCodeIdeas/DGLab-Styles-Dist` containing only built artifacts. External Spokes add it as a Composer repo. Keeps everything in PHP toolchain.
  - Migrate from (a) to (b) or (c) later if External Spokes multiply and version-pinning across multiple spokes becomes painful.
- **Scope line for "full control":** DGLab controls the application stack above the language runtime — DI (`CORE-02`), events (`CORE-03`), HTTP message (`CORE-04`), middleware (`CORE-05`), router (`CORE-06`), templates (SuperPHP, `CORE-07`/`11`/`12`), CSS framework (this OD). DGLab does NOT control: PHP itself, Composer, PHPUnit, PHPStan, Caddy, Tengine, FrankenPHP (configured, not built). The scope line is the decision boundary — below the line, consume; above the line, build.
- **Prerequisites (must land before implementation starts):**
  1. `CORE-07` (SuperPHP Lexer) — Step 6 of `INDEX.md` §5 build order.
  2. `CORE-11` (SuperPHP Parser) — blocked on `CORE-07`.
  3. `CORE-12` (SuperPHP Compiler) — blocked on `CORE-11`.
  4. `<dg:style>` block support in `CORE-12`'s blueprint — NOT currently specified. Must be added (via OD amendment or ADR) before `CORE-12` implementation starts, otherwise `<dg:style>` becomes a post-1.0 addition and delays this framework by another lap.
- **Open dimensions (sub-decisions to make when the prerequisites land):**
  - **(i) Package location:** A new Hub-tier blueprint (next available HUB number at the time — clean, under `packages/hub/styles/`, SemVer-tagged, depends on HUB-26; blocks on Hub tier not being started yet) vs. cross-cutting package (sidesteps Hub-tier blocking; needs new tier or exception to `ADR-001`) vs. app-tier (consumer-local under `app/Resources/styles/`; isn't a library, won't be `require`d). Lean: new Hub-tier blueprint even though it blocks — same precedent as `CORE-02` waiting for its tier.
  - **(ii) Utility layer implementation:** hand-rolled SCSS mixins (~200-300 lines, full control, no Node beyond Dart Sass) vs. adopt Tailwind's JIT engine as a build step (battle-tested, adds Node toolchain to a PHP-only repo). Lean: hand-rolled for consistency with the "build our own" pattern; the Node toolchain argument is weak since DGLab already has Node via the web UI's `app.js`.
  - **(iii) Class-less content layer in scope:** yes (Pico-inspired, for ESPOKE-05-style marketing pages) vs. no (every surface uses component classes). Lean: yes, opt-in.
  - **(iv) `<dg:style>` block scope:** scoped-only (per-component, Vue SFC-style) vs. scoped + global (also supports framework-wide styles like resets + class-less content layer). Lean: scoped + global — the class-less content layer (iv) requires global.
  - **(v) Does `CORE-12` blueprint need amending for `<dg:style>`?** Yes — `CORE-12`'s current blueprint doesn't specify `<dg:style>` as a first-class feature. Either amend `CORE-12` directly (file an OD amendment referencing this one) or ship `<dg:style>` as a separate ADR-gated extension after `CORE-12` 1.0. Lean: amend `CORE-12` directly to avoid delaying this framework by a lap.
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Resolve via `ADR-018` once `CORE-07`/`11`/`12` ship and `<dg:style>` block support is in `CORE-12`'s spec. `ADR-018` will cite `ADR-005` (build-vs-adopt precedent for templating), `ADR-001` (package location), `HUB-26` (token consumer, not definer), and this OD. Until then, the interim decision (Tailwind + custom styles) is active.

### OD-10 — Kernel-level driver architecture for multi-runtime spoke support
- **Fork:** DGLab's kernel (`CORE-18`) currently assumes a single runtime context: PHP-native, with SuperPHP as the view engine and PSR-7/15 as the HTTP pipeline. Tenants who want to use a JavaScript framework (React, Vue, Svelte, Solid, Astro) or a non-PHP runtime (Node.js, Bun, Deno) for their spoke have no clean integration path — they'd have to bypass the kernel entirely and build a standalone app that talks to the Hub via REST API only. This works for External Spokes (they're already external apps) but misses the opportunity for SuperPHP to "fuse" with JS frameworks at the kernel level: shared routing, shared middleware, shared asset pipeline, shared state.
- **Concept — "kernel-level drivers":** A driver architecture where the kernel (`CORE-18`) exposes extension points (driver interfaces) that allow non-PHP runtimes and view engines to plug into the Sovereign Stack's request lifecycle as first-class citizens, not external API consumers. Drivers would have many uses beyond JS-framework support (see below), but JS-framework fusion is the motivating use case.
- **Driver categories (initial design space):**
  1. **View Engine Driver** — The kernel hands request data (route params, parsed body, attributes) to the view driver, which returns rendered output (HTML, JSON, or a streaming response). SuperPHP is the default driver; a JS-framework driver would delegate rendering to a Node.js/Bun SSR process or a pre-built static bundle with hydration. The driver interface is the seam where SuperPHP can "fuse" with a JS framework: SuperPHP renders the shell/layout, the JS driver renders the interactive components inside `<dg:slot>` boundaries. This is the Astro/SvelteKit "islands" model applied to DGLab's component architecture.
  2. **Asset Pipeline Driver** — The kernel delegates asset compilation to the driver. Default driver: dart-sass (DGLab's current pipeline). JS-framework driver: Vite/esbuild/webpack, producing JS bundles + CSS extraction + source maps. The driver interface defines inputs (source files, HUB-26 tokens, ESPOKE-05 wireframe slots) and outputs (compiled assets, manifest, purge list). Multiple drivers can coexist — a spoke can use the JS driver for JS/CSS and the default driver for SCSS.
  3. **Runtime Context Driver** — The kernel delegates the execution context to the driver. Default: FrankenPHP worker (PHP 8.3, Fiber-based per ADR-017). JS-runtime driver: Node.js/Bun/Deno process managed alongside FrankenPHP, receiving requests via a Unix socket or HTTP loopback. The driver interface defines lifecycle hooks (boot, health-check, graceful-shutdown, deploy-swap) that integrate with `anvilctl deploy`'s blue/green mechanism.
  4. **State/Cache Driver** — The kernel delegates state management to the driver. Default: PHP session + Redis (HUB-02). JS-runtime driver: the JS framework's own state management (React Query, SWR, TanStack) backed by the same Redis instance, or a separate state store. The driver interface defines the cache key namespace, TTL policy, and invalidation protocol so PHP and JS components sharing a page don't produce inconsistent state.
- **JS-framework fusion model (the motivating use case):**
  - A tenant wants to build their Internal Spoke (e.g. `ISPOKE-09`) with React for the admin panel, while keeping SuperPHP for the marketing pages (ESPOKE-05) and the Hub's own admin chrome.
  - With kernel-level drivers, the spoke declares its view engine driver in its service-provider manifest (CORE-17). The kernel routes requests to the spoke, hands them to the React SSR driver, gets rendered HTML back, and wraps it in the SuperPHP shell layout — all within a single FrankenPHP request cycle, no separate Node.js process needed for SSR (if using a driver that supports server-side rendering via a PHP-JS bridge like V8Js or a sidecar process).
  - For client-side hydration: the asset pipeline driver produces the JS bundle, the view engine driver injects the `<script>` tags and hydration data into the SuperPHP-rendered shell, and the JS framework hydrates in the browser. SuperPHP owns the document shell (`<html>`, `<head>`, `<body>`, layout chrome); the JS framework owns the interactive regions.
  - This is the "islands" model (popularized by Astro, Eleventy, Marko): most of the page is static HTML rendered by SuperPHP; interactive islands are hydrated by the JS framework. DGLab's component architecture (`<dg:component>`, `<dg:slot>`) is already well-suited for this — a `<dg:component>` can declare `engine="react"` and the kernel delegates its rendering to the React driver.
- **Beyond JS frameworks (other driver uses):**
  - **Markdown/content driver** — for CMS-style spokes that render Markdown articles. The driver compiles Markdown to HTML at build time or runtime, with HUB-13 string-key integration for localized content.
  - **GraphQL driver** — a spoke that exposes a GraphQL endpoint instead of REST. The driver registers the schema with the kernel's router (CORE-06) and handles the GraphQL request lifecycle (query parsing, resolver dispatch, DataLoader batching).
  - **WebSocket driver** — for real-time spokes (chat, dashboards, HUB-31 real-time analytics). The driver upgrades the HTTP connection to WebSocket and manages the persistent connection lifecycle outside the PSR-15 request-response cycle.
  - **AI/ML inference driver** — for spokes that run model inference (ISPOKE-17 Sovereign Concierge). The driver manages the model lifecycle (load, warm, infer) and exposes it through the kernel's request pipeline.
  - **Email rendering driver** — for transactional email spokes. The driver renders email templates (MJML, plaintext, inline-CSS) and hands the rendered email to HUB-09 (Logging) or a queue for delivery.
- **Relationship to existing ADRs:**
  - `ADR-005` (SuperPHP vs Blade/Twig) — SuperPHP is the default view engine; drivers are the extension point for alternative view engines. ADR-005's "build our own" rationale applies to the default; drivers are the "consume when the use case demands it" escape valve.
  - `ADR-016` (library-app boundary split) — drivers live in the library tier (packages), not the application tier. A spoke's driver choice is declared in its service-provider, not hardcoded in the kernel.
  - `ADR-017` (Fiber-based cooperative runtime) — runtime context drivers must integrate with the Fiber scheduler. A JS-runtime sidecar process runs outside the Fiber scheduler but communicates via the kernel's IPC layer.
  - `ADR-001` (polyrepo-vs-monorepo) — drivers are Composer packages under `packages/core/drivers/` or `packages/hub/drivers/`, depending on tier. Third-party drivers (e.g. a community React driver) can be installed via Composer.
- **Prerequisites:**
  1. `CORE-18` (Kernel) — must ship with the driver interface contracts.
  2. `CORE-17` (Service Providers) — must support driver registration in the manifest.
  3. `CORE-07`/`11`/`12` (SuperPHP) — must support `<dg:component engine="react">` or similar delegation syntax.
  4. `CORE-06` (Router) — must support routing to non-PSR-15 handlers (driver-mediated dispatch).
- **Open dimensions (sub-decisions to make when prerequisites land):**
  - **(i) Driver interface location:** `SovereignStack\Core\Kernel\Driver\*Interface` (in CORE-18) vs. `SovereignStack\Core\Contracts\Driver\*Interface` (in a separate contracts package). Lean: CORE-18 — drivers are kernel extensions.
  - **(ii) SSR bridge mechanism:** V8Js (PHP extension, no separate process, but limited JS runtime) vs. sidecar process (Node.js/Bun via Unix socket, full JS runtime, but adds a process to manage) vs. build-time pre-rendering (no runtime JS, but no dynamic SSR). Lean: sidecar for dev/staging, build-time pre-rendering for production where possible.
  - **(iii) Driver discovery:** Composer package naming convention (`sovereign-stack/driver-react`, `sovereign-stack/driver-vue`) vs. service-provider registration (any package can register a driver). Lean: both — convention for discovery, service-provider for registration.
  - **(iv) Multiple drivers per spoke:** can a single spoke use multiple view engine drivers simultaneously (e.g. SuperPHP for layout, React for the admin panel, Markdown for the blog)? Lean: yes — this is the islands model, and DGLab's component architecture already supports per-component delegation.
  - **(v) Driver lifecycle management:** does the kernel manage the driver's process lifecycle (start/stop/restart/health-check), or does the driver manage itself and the kernel just calls the interface? Lean: kernel manages for production (integrated with `anvilctl deploy` blue/green), driver self-manages for dev (simpler, no anvilctl overhead).
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Resolve via `ADR-019` (after OD-09/ADR-018 for the SCSS framework) once `CORE-18` and `CORE-17` ship with driver interface contracts. `ADR-019` will cite `ADR-005` (view engine precedent), `ADR-016` (library-app boundary), `ADR-017` (runtime model), and this OD. The first concrete driver implementation (likely a React or Astro driver) becomes a Hub-tier blueprint.

## Resolved

### OD-01 — HUB-31 (Real-Time Analytics & Metrics Ledger): accepted as full Hub tier
- **Decision:** Accept `ADR-011` as-is. HUB-31 promoted from Proposed to accepted.
- **Action:** `INDEX.md` updated — HUB-31 added to Hub tier table; count updated to 97 blueprints.
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-011-hub-31-real-time-analytics.md` (already existed, now accepted).

### OD-02 — Post-quantum / algorithm-agility for JWT signing
- **Decision:** Author `ADR-012` with a three-phase key-rotation + `alg` agility plan (Phase 1 = registry infrastructure during Cooldown 1; Phase 2 = hybrid experiment when PHP supports ML-DSA; Phase 3 = full PQ migration in project Phase 2).
- **Action:** `ADR-012-post-quantum-jwt-agility.md` created; `THREAT_MODEL.md` §10 reference updated.
- **Decided by:** Security lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-012-post-quantum-jwt-agility.md`.

### OD-03 — "Soft" component-name collisions inside a single document
- **Decision:** CI enforcement (`run.php` check 1 + `.github/workflows/architecture-lint.yml`) is sufficient. No pre-commit hook needed for solo operation.
- **Action:** Mark resolved; no file changes required.
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `SDLC-AGRD.md` §6, `REPO-STATE-AUDIT.md` §6.

### OD-04 — Exemplar count
- **Decision:** Split the label. `ISPOKE-01` = "Internal Exemplar", `ESPOKE-01` = "External Exemplar".
- **Action:** Both file headers updated with qualified exemplar labels.
- **Decided by:** Docs lead (DGCI), 2026-08-12.
- **Citing artefact:** `ISPOKE-01.md`, `ESPOKE-01.md`.

### OD-05 — `ISPOKE-*` folder names vs. `INDEX.md` component names + Sovereign Forge collision
- **Decision:** Keep ID-only filenames (Governance Rule 1 — `INDEX.md` is naming authority). Resolve the 4-way "Sovereign Forge" collision by assigning distinct names:
  - `CORE-20` → "Sovereign Forge" (kept as canonical)
  - `ISPOKE-02` → "A1 Atlas"
  - `ISPOKE-11` → "B1 Penumbra"
  - `ESPOKE-12` → "C1 Pulsar"
- **Action:** `INDEX.md` §2.3 updated; 3 blueprint files renamed in content (filenames unchanged per decision).
- **Decided by:** Architecture lead (DGCI), 2026-08-12.
- **Citing artefact:** `INDEX.md` §2.3, `ISPOKE-02.md`, `ISPOKE-11.md`, `ESPOKE-12.md`.


### OD-07 — Runtime model: Fiber-based cooperative scheduler vs. multi-process worker pool
- **Decision:** Accept Option A (Fibers). Ratified as `ADR-017`.
- **Action:** `ADR-017-fiber-based-cooperative-runtime.md` created and accepted. `DGLAB-AS-OS-RUNTIME.md` updated to reflect Accepted status. `CORE-02.md` updated with `pulse()` scope and `WeakMap` cache. `DEPLOY-01.md` FrankenPHP runtime confirmed canonical; PHP-FPM excluded.
- **Decided by:** Architecture lead (DGCI), 2026-08-24.
- **Citing artefact:** `ADR-017-fiber-based-cooperative-runtime.md`, `DGLAB-AS-OS-RUNTIME.md`.

**Confirmed consequences:**
1. FrankenPHP is the canonical PHP runtime. PHP-FPM is incompatible (one process per request); RoadRunner is theoretically compatible but untested.
2. `singleton()` semantics are worker-scoped (shared across all Pulses in a worker). `pulse()` (per-Fiber scope) added to `ContainerInterface` to replace the old "per-request" mental model.
3. Singleton audit remains a gated sub-decision — all existing `singleton()` bindings must be classified as worker-scoped vs. pulse-scoped.

### OD-06 — `ADR-010` opcache scope vs. `CORE-02` blocker interaction
- **Decision:** Keep target provisional and gated behind `CORE-02`. `STRUCTURE-09` already flags the ~5 ms target as provisional.
- **Action:** Mark resolved; no file changes required (provisional flag already present).
- **Decided by:** Performance lead (DGCI), 2026-08-12.
- **Citing artefact:** `ADR-010-opcache-preload-strategy.md`, `STRUCTURE-09`.

---

## Previously Resolved (during consolidation)

## Resolved (during consolidation)

- **Two-architecture ambiguity (Vision A vs. Vision B).** Resolved by `INDEX.md` §1 declaring
  `Architecture/` the sole source of truth and archiving `docs/architecture/origin/` (Vision A) and the
  `Legacy/` code.
- **`HUB-28` = API Versioning, not analytics.** Resolved in `INDEX.md` §2.2; the five spokes were
  corrected (Finding 15, Pattern B).
- **`HUB-09` "Sovereign Pulse" → "Sovereign Signal".** Resolved; "Sovereign Pulse" is reserved for
  `HUB-15`, and *Pulse* is the reserved architectural noun (`STRUCTURE-01-Wheel.md`).
- **`DEPLOY-00` rename.** The docs-only `DEPLOY-01` was renamed `DEPLOY-00` and its document root
  corrected to `Architecture/`; the application deployment is now `DEPLOY-01`.
 - **MySQL → PostgreSQL → MySQL (decision shift 2026-08-05).** The first consolidation adopted PostgreSQL
   16 as primary per `ADR-013`; a later decision shift **reversed** it — `ADR-013` now makes **MySQL 8
   (InnoDB)** the primary datastore, with the PostgreSQL driver **relegated behind CORE-19 and disabled by
   default** (re-enabled only at the next decision scale). `STRUCTURE-05/07/08/09`, `CORE-19`, `DEPLOY-02`,
   and the spoke/datastore blueprints are aligned to MySQL; the `docs/blueprints/` tree (MySQL) is archived
   and never merged. The reversal is recorded in `INCONSISTENCIES.md` #1.
- **ADR number collision.** `THREAT_MODEL.md` §10's "ADR-011" → ADR-012 (pending); `Migration/04`'s
  "ADR-011" SuperPHP reference → ADR-005 (already Accepted).
- **Placeholder/stub blueprints promoted to full fidelity.** On 2026-08-05, `ISPOKE-16`–`25` (10
  Internal Spokes) and `DEPLOY-02`–`04` (3 Deploy) were rewritten from placeholders/stubs into
  implementation-ready blueprints (class maps, PHP interface contracts, MySQL/InnoDB DDL, integration
  strategy, security properties, CI criteria). `ISPOKE-21` was renamed **Sovereign Scan** to avoid the
  `HUB-27` "Sentinel" collision. `INDEX.md` §4 reported 96 documented / 0 placeholder and the CI lint
  was green at that point. This closes the "below the fidelity bar" gap (OD-05's concern about
  placeholder content is resolved); the filename↔name mapping question in OD-05 remains a cosmetic
  open item.
- **Hospitality vertical promoted to canonical.** On 2026-08-12, per `ADR-015` (Proposed; ratification
  deferred until V1 ships against Bet 3 Hub Full), 5 hospitality blueprints (`ISPOKE-26` Sovereign
  Reservations, `ISPOKE-27` Sovereign Front Desk, `ESPOKE-16` Sovereign Booking Portal, `ESPOKE-17`
  Sovereign Concierge, `ESPOKE-18` Sovereign Mobile Check-in) were promoted from design-only (tracked
  in `HOSPITALITY-VERTICAL.md`) to canonical. `INDEX.md` §4 now reports **101 documented / 0
  placeholder**. `Verification/lint/run.php` `buildValidIds()` + `checkStructure()` extended to cover
  the new ranges; lint check 1 passes cleanly on every doc that references the hospitality IDs. This
  closes D-02 (96 vs 101 count) and D-15 (hospitality references fail lint by design); see
  `DISCREPANCY-REGISTER.md` for the closure record.
