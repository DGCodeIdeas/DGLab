# OPEN-DECISIONS.md

**Purpose.** This file records every fork the consolidation pass encountered but could **not** close
without an owner's decision. Per governance Rule 9, open questions are recorded here, never silently
resolved. When a decision is made, move the entry to *Resolved* and cite the deciding artefact.

> Entries are intentionally concise. Each lists: the fork, why it is open, the options, the owner, and
> the decision route.

---

## Open

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
  - **(i) Package location:** HUB-32 (clean — new Hub-tier blueprint under `packages/hub/styles/`, SemVer-tagged, depends on HUB-26; blocks on Hub tier not being started yet) vs. cross-cutting package (sidesteps Hub-tier blocking; needs new tier or exception to `ADR-001`) vs. app-tier (consumer-local under `app/Resources/styles/`; isn't a library, won't be `require`d). Lean: HUB-32 even though it blocks — same precedent as `CORE-02` waiting for its tier.
  - **(ii) Utility layer implementation:** hand-rolled SCSS mixins (~200-300 lines, full control, no Node beyond Dart Sass) vs. adopt Tailwind's JIT engine as a build step (battle-tested, adds Node toolchain to a PHP-only repo). Lean: hand-rolled for consistency with the "build our own" pattern; the Node toolchain argument is weak since DGLab already has Node via the web UI's `app.js`.
  - **(iii) Class-less content layer in scope:** yes (Pico-inspired, for ESPOKE-05-style marketing pages) vs. no (every surface uses component classes). Lean: yes, opt-in.
  - **(iv) `<dg:style>` block scope:** scoped-only (per-component, Vue SFC-style) vs. scoped + global (also supports framework-wide styles like resets + class-less content layer). Lean: scoped + global — the class-less content layer (iv) requires global.
  - **(v) Does `CORE-12` blueprint need amending for `<dg:style>`?** Yes — `CORE-12`'s current blueprint doesn't specify `<dg:style>` as a first-class feature. Either amend `CORE-12` directly (file an OD amendment referencing this one) or ship `<dg:style>` as a separate ADR-gated extension after `CORE-12` 1.0. Lean: amend `CORE-12` directly to avoid delaying this framework by a lap.
- **Owner:** Architecture lead (DGCI)
- **Decision route:** Resolve via `ADR-018` once `CORE-07`/`11`/`12` ship and `<dg:style>` block support is in `CORE-12`'s spec. `ADR-018` will cite `ADR-005` (build-vs-adopt precedent for templating), `ADR-001` (package location), `HUB-26` (token consumer, not definer), and this OD. Until then, the interim decision (Tailwind + custom styles) is active.

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
