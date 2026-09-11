# ADR-018: Centralized per-tier release model

**Status:** Accepted (extended by ADR-019)
**Date:** 2026-09-08
**Decided by:** Architecture lead (DGCI)

> **Note:** This ADR established the per-tier release model. The tag format
> (`<tier>-v<X.Y.Z>`) was subsequently revised by [ADR-019](ADR-019-pre-muwv-version-scheme.md)
> to use the four-segment pre-MUWV scheme (`<tier>-v<MUWV>.<Milestone>.<Lap>.<Patch>+<sha>`).
> The model described here (per-tier centralized SemVer, monorepo release snapshots,
> composer.json version sync) is unchanged — only the tag format has evolved.

## Context

DGLab is a monorepo with multiple Composer packages across four tiers (Core, Hub, Bridge, Spoke). The current release model — established in PRs #109–#114 — is **per-package**: each package gets its own SemVer tag (`core-http-message-v1.0.0`, `core-middleware-v1.0.0`, etc.) independently, driven by path-scoped commit analysis.

As the package count grows (5 Core packages today, 30+ Hub packages planned, 45+ Spoke packages), the per-package model produces:

- **Tag explosion:** a single push touching 3 packages creates 3 tags, 3 releases, 3 GitHub Release pages. At scale this is noise.
- **Version fragmentation:** `core/container` at v1.2.0, `core/http-message` at v1.0.1, `core/router` at v1.1.0 — consumers must track 5+ version numbers to know "what version of Core am I on?"
- **Compatibility uncertainty:** if `core/middleware` v1.1.0 depends on `core/http-message` v1.0.1 but the consumer has v1.0.0, the dependency resolution may succeed but break at runtime. Per-package SemVer doesn't guarantee the packages were tested together.

## Decision

### 1. Per-tier centralized SemVer

All packages within a tier share a single SemVer version. When ANY package in the tier changes, ALL packages in the tier are bumped together.

**Tag format:** `<tier>-v<X.Y.Z>`
- `core-v1.0.0` — all `packages/core/*`
- `hub-v0.1.0` — all `packages/hub/*`
- `bridge-v0.1.0` — all `packages/bridge/*`
- `spoke-v0.1.0` — all `packages/spoke/*` (internal + external)

**Version determination:** the loom analyzes ALL path-scoped commits across ALL packages in the tier. The highest bump level wins:
- If any package has a `feat:` commit → minor bump for the whole tier
- If any package has a `BREAKING CHANGE` or `feat!:` → major bump for the whole tier
- If only `fix:` commits → patch bump for the whole tier

**All `composer.json` files in the tier** are bumped to the same version in a single commit. Packages that didn't change still get the new version number — they're identical code at a new version, which is semantically correct (they were tested alongside the changed packages and are known-compatible at that version).

### 2. Monorepo-level release

Separate from the per-tier SemVer tags, a monorepo-level release represents the state of the entire codebase at a point in time. This is a **deployment snapshot**, not a Composer version.

**Tag format:** `release-<SemVer>` (e.g., `release-1.0.0`)

**Purpose:** DEPLOY-01 builds container images from this tag. It aggregates all tier versions into a single release note. Created AFTER all tier releases complete.

**SemVer rules for monorepo releases:**
- Major: architectural shift (new tier added, deployment model changes)
- Minor: new component shipped (CORE-XX lands, HUB-XX lands)
- Patch: bug fixes, CI fixes, doc updates

**Example release note:**
```
release-1.0.0

Core: v1.1.0 (was v1.0.0)
  - core/error-handler: new package (CORE-08)
  - core/logging: new package (CORE-09)
  - core/config: new package (CORE-10)
  - core/container: unchanged
  - core/http-message: unchanged

Hub: not yet released
Bridge: not yet released
Spoke: not yet released
```

### 3. Composer.json version field

**Kept.** All `composer.json` files within a tier carry the same `"version"` value. The loom's `Manifest::setVersion()` updates ALL files in the tier atomically in a single commit. The `composer validate --strict --no-check-version` workaround in CI remains (this is a Composer design choice, not ours).

### 4. Existing per-package tags

The 5 existing per-package tags (`v1.0.0`, `core-event-dispatcher-v1.0.0`, `core-http-message-v1.0.0`, `core-middleware-v1.0.0`, `core-router-v1.0.0`) remain for history. No new per-package tags are created. The first centralized release will be `core-v1.0.0` — a retroactive tag at the current `main` HEAD that represents "all 5 Core packages at v1.0.0."

### 5. Cross-tier dependencies

Packages in one tier can depend on packages in another tier. The dependency constraint uses the tier version:

```json
{
    "require": {
        "sovereign-stack/core-http-message": "^1.0"
    }
}
```

All packages within a tier share the same version, so `^1.0` matches any `1.x.y` release of any Core package. This is correct because all Core packages at the same version were tested together.

### 6. Release workflow changes

**Current (per-package matrix):**
```yaml
matrix:
  package:
    - core/container
    - core/event-dispatcher
    - core/http-message
    - core/middleware
    - core/router
```

**New (per-tier matrix):**
```yaml
matrix:
  tier:
    - core
    # - hub    # uncomment when Hub packages ship
    # - bridge # uncomment when Bridge packages ship
    # - spoke  # uncomment when Spoke packages ship
```

The loom gains a new command: `loom version:release:tier <tier>` that:
1. Scans all `packages/<tier>/*/composer.json` via `MonorepoPackage::discover()`
2. Analyzes ALL path-scoped commits across ALL packages in the tier (union of all per-package commit sets)
3. Determines the highest bump level
4. Bumps ALL `composer.json` files in the tier to the same version
5. Creates ONE commit, ONE tag (`<tier>-v<X.Y.Z>`), pushes
6. Creates ONE GitHub Release with a changelog listing which packages actually changed

### 7. Monorepo release workflow

After all tier releases complete, a separate job creates the monorepo release:

```yaml
  monorepo-release:
    name: Monorepo Release
    needs: release  # wait for all tier releases
    if: success()
    runs-on: ubuntu-latest
    steps:
      - name: Determine monorepo version
        run: |
          # Determine bump level from ALL tier releases in this run
          # Create release-<SemVer> tag
          # Create GitHub Release aggregating all tier changelogs
```

## Alternatives considered

### A. Keep per-package SemVer (status quo)
- Pro: granular version tracking, packages that don't change don't get bumped
- Con: tag explosion at scale, version fragmentation, no guarantee packages were tested together
- Rejected: doesn't scale to 100+ packages

### B. Single monorepo version (no per-tier)
- Pro: simplest — one version number for everything
- Con: coupling between tiers (a Spoke fix shouldn't bump Core)
- Rejected: tiers have different release cadences and stability levels

### C. Date-based versioning (CalVer)
- Pro: no semver debates, chronologically ordered
- Con: no semantic meaning (can't tell if a release has breaking changes from the version number)
- Rejected: SemVer is already deeply integrated into the loom, CI, and Composer

## Consequences

1. **Loom refactoring required:** `version:release` gains a `:tier` variant. The existing per-package `version:release <package>` command stays for backward compatibility but is deprecated.

2. **All Core packages bump together.** A patch fix in `core/router` bumps `core/container` from 1.0.0 to 1.0.1 even though `core/container` didn't change. This is correct — they were tested together and are known-compatible at 1.0.1.

3. **Release page is cleaner.** One release per tier per push, not 5+ per package.

4. **The 5 existing per-package tags are orphaned.** They remain in git history but are not referenced by the new release model. `core-v1.0.0` becomes the canonical tag for "all Core packages at v1.0.0."

5. **Monorepo releases (`release-X.Y.Z`) are separate from tier releases.** They track deployment state, not Composer dependencies. DEPLOY-01 uses monorepo releases; Composer uses tier releases.

## References

- `ADR-001` (polyrepo-vs-monorepo) — established the monorepo structure
- `CORE-01.md` §SemVer Automation Plan — original per-package release design (now superseded by this ADR for the release model)
- `CORE-01.md` §Tag-naming convention — per-package convention (`<tier>-<short>-v<X.Y.Z>`) superseded by per-tier convention (`<tier>-v<X.Y.Z>`)
- PRs #109–#114 — built the per-package release tooling (loom commands stay; the matrix and tag format change)
