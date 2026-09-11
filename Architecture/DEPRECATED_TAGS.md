# Deprecated Tags Register

**Status:** Active deprecation — tags remain in git history but are no longer the canonical release identifiers.
**Effective date:** 2026-09-11 (ADR-019 acceptance)
**Superseded by:** [ADR-019](ADRs/ADR-019-pre-muwv-version-scheme.md) — `v<MUWV>.<Milestone>.<Lap>.<Patch>+<git-sha>`

## Why these tags are deprecated

DGLab tagged its first releases as `v1.0.0`, `release-1.X.0`, `core-v1.0.0`, and per-package `core-*-v1.0.0`. This was misleading: DGLab has not yet reached its **Minimally Usable Working Version (MUWV)** — the Milestone 0 success criterion (CORE-18 Kernel wiring the full Pulse round-trip). Tagging pre-MUWV work as `1.x` implied production-ready stability that did not exist.

ADR-019 introduced the four-segment pre-MUWV scheme (`v0.X.Y.Z+sha`) to honestly signal project maturity. The old tags remain in git history (deleting them would break any external clone or `composer.lock` that references them) but are no longer the canonical release identifiers.

## What "deprecated" means here

- **The tags still exist** in git history and on GitHub. They are not deleted.
- **No new tags** in the old format will be created. All future releases use `v0.X.Y.Z+sha`.
- **GitHub Release pages** for each deprecated tag have been updated with a deprecation notice pointing to the replacement.
- **`composer.json` `version` fields** in all packages have been updated from `1.0.0` to `0.1.0.0`. Cross-package `require` constraints have been updated from `^1.0` to `^0.1`.
- **CI lint** flags any new reference to a deprecated tag pattern in source files or documentation.

## Deprecated tags

### Monorepo-level tags

| Deprecated tag | Replacement | Reason |
|----------------|-------------|--------|
| `v1.0.0` | `v0.1.0.0+<sha>` | Implied production-ready stability pre-MUWV |
| `release-1.0.0` | `v0.1.0.0+<sha>` | `release-` prefix dropped; four-segment scheme used instead |
| `release-1.1.0` | `v0.1.1.0+<sha>` | Same |
| `release-1.2.0` | `v0.1.2.0+<sha>` | Same |
| `release-1.3.0` | `v0.1.3.0+<sha>` | Same |

### Per-tier tags

| Deprecated tag | Replacement | Reason |
|----------------|-------------|--------|
| `core-v1.0.0` | `core-v0.1.0.0+<sha>` | Tier prefix preserved; version scheme changed per ADR-019 |

### Per-package tags (already deprecated by ADR-018 §4)

These were deprecated earlier when ADR-018 centralized releases to per-tier. They are listed here for completeness — ADR-019 does not change their status.

| Deprecated tag | Replacement | Reason |
|----------------|-------------|--------|
| `core-event-dispatcher-v1.0.0` | `core-v0.1.0.0+<sha>` | Per-package tags replaced by per-tier tags (ADR-018) |
| `core-http-message-v1.0.0` | `core-v0.1.0.0+<sha>` | Same |
| `core-middleware-v1.0.0` | `core-v0.1.0.0+<sha>` | Same |
| `core-router-v1.0.0` | `core-v0.1.0.0+<sha>` | Same |

## Migration guide

### For consumers (composer require)

If your `composer.json` pins to a deprecated tag:

```json
{
    "require": {
        "sovereign-stack/core-http-message": "^1.0"
    }
}
```

Update to the new constraint:

```json
{
    "require": {
        "sovereign-stack/core-http-message": "^0.1"
    }
}
```

The `^0.1` constraint matches `>=0.1.0.0, <0.2.0.0` — all releases within Milestone 0 starting from lap 0. This is intentionally narrow: pre-MUWV releases may have breaking changes between laps, and consumers should opt-in to each lap explicitly.

### For deployments (git checkout)

If your deployment script checks out a deprecated tag:

```bash
git checkout release-1.2.0
```

Update to the new tag format:

```bash
git checkout v0.1.2.0+abc1234
```

To find the new tag equivalent to a deprecated tag, use the mapping table above. The git commit pointed to by the deprecated tag is the same commit pointed to by the replacement tag (where a retroactive new-scheme tag was created) or is the closest historical equivalent (where no retroactive tag was created — see ADR-019 §4 for why we don't retag).

### For CI/CD pipelines

If your pipeline grep's for tags matching `release-*` or `v1.*`:

```bash
LATEST=$(git tag -l 'release-*' --sort=-v:refname | head -1)
```

Update to match the new scheme:

```bash
LATEST=$(git tag -l 'v0.*' --sort=-v:refname | head -1)
```

The `release.yml` workflow has already been updated to use the new pattern — see [`.github/workflows/release.yml`](../.github/workflows/release.yml).

## Enforcement

Deprecation is enforced through three mechanisms:

1. **`composer.json` migration** — all package `version` fields now use `0.1.0.0`; all `sovereign-stack/*` require constraints use `^0.1`. New packages must follow this pattern.
2. **`release.yml`** — the release workflow creates tags in the new `v0.X.Y.Z+sha` / `<tier>-v0.X.Y.Z+sha` format only. No new tags in the old format can be created by the automated release pipeline.
3. **PR review** — reviewers should reject PRs that introduce new references to deprecated tag patterns in source code or package `composer.json` files. Historical references in `Architecture/` docs, `README.md`, and `CONTRIBUTING.md` (which document the deprecation) are expected and allowed.

The deprecated tags themselves are **not deleted** from git history — deleting them would break any external clone or `composer.lock` that references them.

## References

- [ADR-018](ADRs/ADR-018-centralized-per-tier-releases.md) — centralized per-tier release model (originally established `core-v1.0.0` format)
- [ADR-019](ADRs/ADR-019-pre-muwv-version-scheme.md) — pre-MUWV four-segment version scheme (supersedes the tag format)
- [SDLC-AGRD](CrossCutting/SDLC-AGRD.md) §4 — Milestone 0 success criterion (the MUWV flip trigger)
- [`release.yml`](../.github/workflows/release.yml) — release automation (updated for new tag format)
- [`RepoManager.php`](../orchestrator/src/RepoManager.php) — Loom tag creation (accepts both formats for backward compatibility)
