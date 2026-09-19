# ADR-016: Library/Application Boundary — Split `packages/` from `app/`

**Status:** **Proposed** — not yet Accepted. Ratification as Accepted is deferred until
the first Spoke (ISPOKE-01) ships against the new layout and empirically validates that
the boundary sharpens (not blurs) the tier model. Until then, this ADR records the
decision-in-principle; the new path conventions are de facto canonical because the
blueprints reference them.
**Date:** 2026-08-17
**Deciders:** DGLab architecture team

## Context

ADR-001 established the polyrepo model with three strict tiers (Core, Hub, Spoke) all
physically co-located under `packages/{tier}/{name}/`. ADR-001's stated intent was
that *"the monorepo at `DGCodeIdeas/DGLab` is the documentation and blueprint root
only; it is not the runtime code root."* In practice, two Core packages
(`packages/core/container`, `packages/core/event-dispatcher`) have been implemented
in-tree as library packages — ADR-001's polyrepo model holds at the SemVer/CI/release
layer (per-package `composer.json`, per-package tags, Loom as cross-repo orchestrator),
but the *physical* layout is monorepo.

This ADR sharpens a structural distinction that ADR-001 left implicit: **library
packages** (independently versioned, no application dependencies, downstream
consumers `require` them) versus **application code** (consumes the library, has
runtime opinions, not independently distributed). Core and Hub are the former;
Spokes and Bridge are the latter. The current `packages/{tier}/{name}/` structure
conflated the two — every tier lived in the same root directory, even though only
library-tier packages belonged in the Loom release matrix and the per-package
`composer.json`/CI pipeline.

The split:

- `packages/core/`, `packages/hub/` — **library tier**, versioned, SemVer-released via
  `release.yml`, downstream-`require`-able. Unchanged from ADR-001.
- `app/Spokes/Internal/`, `app/Spokes/External/` — **application tier**. Internal and
  External Spokes are PSR-7/15 applications that consume `packages/core/` and
  `packages/hub/`. They have their own `composer.json` (typically `type: project`),
  are not independently SemVer-tagged, and are not in the Loom release matrix.
- `app/Bridge/` — **application tier**. The Vanguard (BRIDGE-01) is the
  external-facing boundary middleware. It is application code that composes
  library-tier contracts (CORE-16, HUB-02, HUB-04, HUB-06, HUB-08, HUB-15) into a
  deployable boundary. The `app/Bridge/Http/` subdirectory holds thin controller
  abstractions that delegate to Spokes (modular-monolith pattern).
  `app/Bridge/Resources/` holds shared layout/assets — per-Spoke views/assets defer
  to each Spoke's own Resources directory (the "Defer to Spokes" convention).
- `config/`, `public/`, `tests/` — application-tier conventional directories. `config/`
  is root-level bootstrap (routes, services); each Spoke ships its own
  `config/routes.{php,yaml}` and `config/services.{php,yaml}` (the "Defer to Spokes"
  convention). `public/` is the web document root. `tests/` holds application-tier
  tests (Spoke integration, Bridge integration, end-to-end); per-package library
  tests remain co-located under `packages/{tier}/{name}/tests/`.

## Decision

We split the repository's physical layout into a **library tier** (`packages/`) and
an **application tier** (`app/`):

| Directory | Tier | Versioned? | In Loom release matrix? | composer.json type |
|---|---|---|---|---|
| `packages/core/{name}/` | Core | Yes (per ADR-001) | Yes | `library` |
| `packages/hub/{name}/` | Hub | Yes (per ADR-001) | Yes (when first Hub ships) | `library` |
| `app/Spokes/Internal/{name}/` | Spoke (internal) | No | No | `project` |
| `app/Spokes/External/{name}/` | Spoke (external) | No | No | `project` |
| `app/Bridge/Vanguard/` | Bridge | No | No | `project` |
| `app/Bridge/Http/` | Bridge (controllers) | No | No | (part of app composer.json) |
| `app/Bridge/Resources/` | Bridge (shared views/assets) | No | No | (part of app composer.json) |
| `config/`, `public/`, `tests/` | Application | No | No | (part of app composer.json) |

**Tier ordering is unchanged.** ADR-004's DAG (`Core → Hub → Bridge → Spokes → Deploy`)
is the logical dependency order; the physical layout split does not change it. Bridge
and Spokes are both application-tier physically, but Bridge still sits between Hub and
External Spokes in the runtime request path.

**Namespace mapping is deferred to per-Spoke implementation.** The canonical namespace
root for Internal Spokes per `INDEX.md` §2.3 is `SovereignStack\Internal\<Name>`; for
External Spokes `SovereignStack\External\<Name>`; for Bridge `SovereignStack\Bridge\`.
PSR-4 autoload mapping for `app/` will be declared in the root-level `composer.json`
(which does not yet exist — it lands with the first Spoke). Three reasonable options
exist:

- **A (preserve canonical namespaces):** `app/Spokes/Internal/Codex/src/` →
  `SovereignStack\Internal\Codex\` — recommended, zero churn to existing blueprint
  namespace references.
- **B (mirror structure):** `app/Spokes/Internal/Codex/` →
  `App\Spokes\Internal\Codex\` — visually distinct from `SovereignStack\Core\` /
  `SovereignStack\Hub\`, reinforces the library-vs-application line at the namespace
  layer.
- **C (short App):** `app/Spokes/Internal/Codex/` → `App\Internal\Codex\` — shortest,
  but loses the `Spokes` segment that mirrors the directory.

This ADR does not pick one. The first Spoke implementation MUST pick one in its own
`composer.json` and update `INDEX.md` §2.3 if deviating from canonical. **Recommendation:
Option A** — preserves all existing blueprint namespace references, zero churn.

**Casing convention.** `app/` subdirectories use PascalCase (`Spokes/`, `Bridge/`,
`Internal/`, `External/`, `Vanguard/`, `Http/`, `Resources/`) matching the existing
pattern in `Architecture/Spoke/Internal/` and `Architecture/Spoke/External/`
(PascalCase for tier subdirs). Root-level conventional directories (`config/`,
`public/`, `tests/`) remain lowercase, matching PSR / framework conventions and the
existing `packages/` lowercase root.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **Split: `packages/` (library) + `app/` (application)** (this ADR) | Sharpens the library/app boundary ADR-001 left implicit; Spokes and Bridge were always the awkward fit in `packages/` since they're never `composer require`d downstream; conventional PHP framework layout (Symfony Bundle / Laravel Modular Monolith pattern); preserves all load-bearing references (Loom matrix, `MonorepoPackage::discover()` glob, existing tags `v1.0.0` and `core-event-dispatcher-v1.0.0`, ADR-001) | Three blueprint files need path-string updates (8 path references across BRIDGE-01, CORE-17, CORE-20); `packages-ci.yml` will need an `app/**` path filter + matrix entry when the first Spoke ships; root-level `composer.json` does not yet exist | **Proposed** |
| **Keep all tiers under `packages/` per ADR-001 literally** | Zero churn — no blueprint updates, no ADR-016 | Conflates library packages with application code; Spokes and Bridge sitting in `packages/` is conceptually wrong since they're never `composer require`d downstream; future contributors cannot tell at a glance which dirs are versioned vs internal | Rejected — ADR-001's "polyrepo" model is at the SemVer/CI layer, not the physical layout; the original three-tier `packages/` layout was an artefact of that conflation |
| **Move everything to `app/` (full MVC restructure)** | Maximally conventional MVC layout | Breaks Loom release pipeline (`MonorepoPackage::discover()` glob, `release.yml` matrix, `packages-ci.yml` path filter); invalidates existing tags `v1.0.0` and `core-event-dispatcher-v1.0.0`; contradicts ADR-001's library-package model; ~10–15 hour restructure cost | Rejected — ADR-001's library-package model is load-bearing and intentionally chosen |
| **Move only Spokes, keep Bridge in `packages/`** | Slightly smaller blast radius | Bridge (Vanguard) is unambiguously application code — it composes Hub-tier contracts into a deployable boundary; keeping it in `packages/` perpetuates the conflation this ADR exists to fix | Rejected — Bridge is application-tier by the same criterion as Spokes |

## Consequences

**Positive:**

- The library/app boundary becomes physically visible at the repo root. A new
  contributor sees `packages/` and knows "this is the versioned library"; sees `app/`
  and knows "this is the application that consumes the library."
- The Loom release pipeline gets cleaner: `MonorepoPackage::discover()` (PR #110) uses
  `glob(packages/*/*/composer.json)` with a `type:project` skip — Spokes/Bridge in
  `app/` are simply invisible to Loom, which is the correct behavior (they're not
  independently released library packages).
- The future `packages-ci.yml` matrix can scope CI per Spoke as it lands — no more
  "where does this Spoke's test go?" ambiguity.
- ADR-001's polyrepo decision is sharpened, not reversed. The library-tier SemVer
  automation you spent PRs #109–#116 + `LOOM_RELEASE_PAT` + branch protection enabling
  continues to work unchanged.

**Negative (and how they are contained):**

- Three blueprint files need path-string updates: BRIDGE-01 (5 references to
  `packages/bridge/vanguard/`), CORE-17 (2 references to `packages/spoke/` in the
  kernel's `addScanDirectory()` examples), CORE-20 (1 reference to
  `packages/spoke/<internal|external>/<name>/` in the `MakeSpokeCommand` description).
  All are in spec/prose, not in code — code patches are zero. Contained by this ADR's
  companion PR (this commit).
- `packages-ci.yml` does not currently trigger on `app/**` changes. Until the first
  Spoke ships and the matrix is extended, app-tier code would have no CI. Containment:
  no app-tier code exists yet — the first Spoke's PR must add the `app/**` path filter
  and matrix entry alongside its implementation.
- Root-level `composer.json` does not exist yet. PSR-4 autoload for `app/` will be
  declared when the first Spoke lands. Containment: the same PR that adds the first
  Spoke creates the root `composer.json` with the chosen namespace mapping (Option A
  recommended above).
- ADR-001's "three strict tiers (Core, Hub, Spoke)" prose becomes "two physical layout
  groups: library (Core, Hub) and application (Spokes, Bridge)" — but the tier DAG
  (ADR-004) is unchanged. Containment: ADR-001 is not superseded; this ADR sharpens
  its physical-layout ambiguity. A future amendment to ADR-001 may clarify the
  polyrepo-vs-monorepo distinction if it becomes load-bearing again.

**Neutral:**

- This ADR does not change any code, any interface, or any runtime behavior. Its sole
  effect is to (a) update 8 path strings in 3 blueprint files (spec only),
  (b) document the library/app boundary decision, (c) commit the casing convention
  (PascalCase under `app/`, lowercase for conventional root dirs).
- The 2 currently-existing tags (`v1.0.0`, `core-event-dispatcher-v1.0.0`) point at
  `packages/core/container/` and `packages/core/event-dispatcher/` files that are
  unchanged by this ADR.

## Links

- Closes: nothing open in `DISCREPANCY-REGISTER.md`
- Related ADRs: ADR-001 (polyrepo model — sharpened, not superseded), ADR-004 (tier
  enforcement DAG — unchanged), ADR-014 (AGRD canonical SDLC — this ADR follows the
  v3.4(3) spiral-deepening model)
- Related blueprints: BRIDGE-01 (Vanguard — 5 path strings updated by this ADR's
  companion commit), CORE-17 (Service Provider System — 2 path strings updated),
  CORE-20 (Sovereign Forge — 1 path string updated), ISPOKE-09 and ESPOKE-01 (zero
  path strings — verified 2026-08-17)
- Related code: `orchestrator/src/MonorepoPackage.php` (uses glob
  `packages/*/*/composer.json` with `type:project` skip — Spokes/Bridge in `app/`
  are correctly invisible to Loom), `.github/workflows/release.yml` (matrix
  unchanged), `.github/workflows/packages-ci.yml` (path filter and matrix need
  extension when first Spoke ships)
- Related documents: `Architecture/INDEX.md` §2.3 (namespace mapping unchanged —
  Option A preserves `SovereignStack\Internal\<Name>` and
  `SovereignStack\External\<Name>`), `Architecture/CrossCutting/SDLC-HISTORY.md`
  (this ADR follows the canonical SDLC)
- Related findings: zero — this is a structural decision, not a discrepancy closure.
  The five live-repo verification checks run 2026-08-17 confirming the move is
  cost-bounded to "8 path strings in 3 blueprint files" are documented in the
  companion PR description.
