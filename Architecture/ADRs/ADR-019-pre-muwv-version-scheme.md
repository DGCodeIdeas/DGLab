# ADR-019: Pre-MUWV version scheme (v0.X.Y.Z)

**Status:** Accepted — **MUWV FLIPPED (2026-09-18) — see §8 Flip Log**
**Date:** 2026-09-11
**Decided by:** Architecture lead (DGCI)

## Context

DGLab has been tagging releases as `v1.0.0`, `release-1.0.0`, `release-1.1.0`, `release-1.2.0`, and per-tier tags like `core-v1.0.0`. This is misleading: DGLab has not yet reached its **Minimally Usable Working Version (MUWV)** — the Milestone 0 success criterion (CORE-18 Kernel wiring the full request pipeline end-to-end). Tagging pre-MUWV work as `1.x` sends the wrong signal to anyone looking at the repo: it implies production-ready stability that does not yet exist.

Conventional SemVer does not have a clean way to signal "this is pre-MUWV." The `0.x.y` convention is the standard answer, but DGLab's `0.x.y` space is already used for Hub/Bridge/Spoke tiers (`hub-v0.1.0`) — those tiers are pre-1.0 by tier maturity, not by project maturity. Using `0.x.y` for the whole project would conflate two different concepts.

SDLC-AGRD (ADR-014) defines project phases in terms of milestones and laps. The version scheme should reflect this structure so that anyone reading a version number can immediately tell:
1. Whether the project has reached MUWV
2. Which milestone the release belongs to
3. How many laps have been completed within that milestone

## Decision

### 1. Four-segment version scheme: `v<MUWV>.<Milestone>.<Lap>.<Patch>`

All monorepo releases and per-tier releases use a four-segment scheme:

```
v0.1.3.0+abc1234
│ │ │ │ │
│ │ │ │ └─ patch within lap (0 = first release of this lap)
│ │ │ └─── lap within milestone (3 = third lap)
│ │ └───── milestone number (1 = Milestone 0, 2 = Milestone 1, etc.)
│ └─────── MUWV marker (0 = pre-MUWV, 1 = post-MUWV)
└───────── 'v' prefix (literal)
```

**Segment semantics:**

| Segment | Name | Meaning | Range |
|---------|------|---------|-------|
| 1st | MUWV | 0 = pre-MUWV (project hasn't reached walking skeleton), 1 = post-MUWV (Milestone 0 complete) | `0` or `1` |
| 2nd | Milestone | Milestone number + 1 (Milestone 0 = 1, Milestone 1 = 2, etc.) | `1+` |
| 3rd | Lap | Lap number within the milestone. Resets to 0 at the start of each milestone. | `0+` |
| 4th | Patch | Patch within the lap. 0 = first release of the lap; increments for bug fixes within the same lap. | `0+` |

**Build metadata (SemVer `+` suffix):** the 7-character git short SHA of the release commit. This identifies the exact commit the release was built from, without affecting version ordering (per SemVer spec, build metadata is ignored for precedence).

### 2. Examples

| Version | Interpretation |
|---------|----------------|
| `v0.1.0.0+abc1234` | Pre-MUWV, Milestone 0, lap 0, first release (the first walking-skeleton attempt) |
| `v0.1.1.0+def5678` | Pre-MUWV, Milestone 0, lap 1, first release of lap 1 |
| `v0.1.2.1+ghi9012` | Pre-MUWV, Milestone 0, lap 2, patch 1 (second release within lap 2) |
| `v0.1.3.0+jkl3456` | Pre-MUWV, Milestone 0, lap 3, first release |
| `v1.2.0.0+mno7890` | Post-MUWV, Milestone 1, lap 0, first release of Milestone 1 |

### 3. Tag formats

**Monorepo releases** (deployment snapshots — what DEPLOY-01 builds container images from):

```
v<MUWV>.<Milestone>.<Lap>.<Patch>+<sha>
```

Example: `v0.1.3.0+abc1234`

**Per-tier releases** (Composer version source — what `composer require` resolves against):

```
<TIER>-v<MUWV>.<Milestone>.<Lap>.<Patch>+<sha>
```

Example: `core-v0.1.3.0+abc1234`

The tier prefix (`core-`, `hub-`, `bridge-`, `spoke-`) is preserved from ADR-018.

### 4. Existing tags — deprecated, then deleted (2026-09-13)

The following tags were deprecated on 2026-09-11 and **deleted on 2026-09-13** per user directive. They no longer exist in the git repository or on GitHub:

| Deleted tag | New equivalent (not created retroactively) |
|---|---|
| `v1.0.0` | `v0.1.0.0+<sha>` (the first Core release) |
| `release-1.0.0` | `v0.1.0.0+<sha>` (same — `release-` prefix is dropped) |
| `release-1.1.0` | `v0.1.1.0+<sha>` |
| `release-1.2.0` | `v0.1.2.0+<sha>` |
| `core-v1.0.0` | `core-v0.1.0.0+<sha>` |
| `core-event-dispatcher-v1.0.0` | (legacy per-package tag — ADR-018 §4 already deprecated these) |
| `core-http-message-v1.0.0` | (same) |
| `core-middleware-v1.0.0` | (same) |
| `core-router-v1.0.0` | (same) |

**Why deleted (not retagged):** the tags were initially deprecated (kept in history) on 2026-09-11. On 2026-09-13, per user directive, they were deleted entirely — both the git tag refs and the GitHub Releases. The commits they pointed to remain in git history (immutable); only the tag labels were removed. New releases use the new `v0.X.Y.Z+sha` scheme going forward.

### 5. Composer version constraints

Composer's `version` field in `composer.json` and the `require` constraints use the **four-segment version without build metadata**:

```json
{
    "version": "0.1.3.0",
    "require": {
        "sovereign-stack/core-config": "^0.1.3"
    }
}
```

The `+sha` build metadata is git-tag-only — it does not appear in `composer.json` because Composer's version parser does not support build metadata in the `version` field (it would be dropped anyway).

The constraint `^0.1.3` matches `>=0.1.3.0, <0.2.0.0` — i.e., all releases within Milestone 0 starting from lap 3. This is intentionally narrow: pre-MUWV releases may have breaking changes between laps, and consumers should opt-in to each lap explicitly.

### 6. When does the MUWV segment flip to 1?

The MUWV segment flips from `0` to `1` exactly once: when the **Milestone 0 success criterion** is met (per SDLC-AGRD §4). The success criterion is the Pulse round-trip working end-to-end — a PSR-7 `ServerRequest` flows through middleware, matches a route, dispatches to a controller, and returns a PSR-7 `Response`, all wired together by CORE-18 (Kernel).

Once MUWV is reached:
- The next release is `v1.2.0.0+<sha>` (post-MUWV, Milestone 1 = second milestone, lap 0, patch 0)
- The milestone counter increments by 1 (Milestone 0 → milestone number 1 → becomes "2" in the second segment after the flip; the first post-MUWV release starts Milestone 1 = segment value 2)
- Future pre-MUWV releases are no longer possible

### 7. Precedence

Precedence is calculated by comparing segments left-to-right, ignoring build metadata (per SemVer spec):

```
v0.1.3.0+abc < v0.1.3.0+def    (same version, different builds — equal precedence)
v0.1.3.0     < v0.1.3.1        (patch differs)
v0.1.3.5     < v0.1.4.0        (lap differs)
v0.1.99.99   < v0.2.0.0        (milestone differs)
v0.99.99.99  < v1.0.0.0        (MUWV flip — milestone counter resets to 0 for Milestone 0 post-MUWV? No — see below)
```

**MUWV flip edge case:** when the MUWV segment flips from 0 to 1, the milestone counter does NOT reset. The first post-MUWV release is `v1.2.0.0` (Milestone 1 = second milestone), not `v1.0.0.0` (which would imply a re-do of Milestone 0). This ensures strict monotonic precedence: every post-MUWV release is greater than every pre-MUWV release.

## 8. MUWV Flip Criterion (corrected)

### The flip criterion is the FULL AGRD §4 success criterion, not just the integration test

The MUWV segment flips from `0` to `1` exactly once: when the **complete AGRD §4 success criterion** is met. Per SDLC-AGRD §4, the criterion is:

> *a real HTTP request enters at the Outer Rim, crosses the Inner Rim, resolves against the Inner Spoke, and returns — the actual synchronous-radial Pulse trace, not a diagram of it.*

This requires **all 8 Milestone 0 blueprints** to be shipped at depth 1–2:

| # | Blueprint | Step | Status |
|---|-----------|------|--------|
| 1 | CORE-02 (DI Container) | 1 | ✅ Depth 2 |
| 2 | CORE-04 (HTTP Message) | 4 | ✅ Depth 2 |
| 3 | CORE-05 (Middleware) | 4 | ✅ Depth 2 |
| 4 | CORE-06 (Router) | 4 | ✅ Depth 2 |
| 5 | CORE-18 (Kernel) | 3 | ✅ Depth 2 |
| 6 | HUB-01 (Hub Config & Flags) | 8 | ✅ Depth 2 (PR #162) |
| 7 | BRIDGE-01 (Vanguard) | 9 | ✅ Depth 2 (PR #163) |
| 8 | ISPOKE-09 (Codex) | 10 | ✅ Depth 2 (PR #164) |
| 9 | ESPOKE-01 (Canvas) | 11 | ✅ Depth 2 (PR #165) |

**All 8 Milestone 0 blueprints shipped. The flip criterion IS met.**

### Flip Log — 2026-09-18

**Authorized by:** Architecture lead (DGCI) — user explicitly said "flip" after verifying:
- All 8 Milestone 0 blueprints shipped at depth 2 ✅
- `public/index.php` wired to boot the Kernel via BRIDGE-01 Vanguard ✅
- Real HTTP round-trip through the full Rim verified: `curl → 200 Hello World`
  through Caddy → Tengine → FrankenPHP → Kernel → Vanguard → Router → HelloController ✅
- Health endpoint `/health` returns 200 `{"status":"ok"}` ✅
- P0 (4/4), P1 (18/18), P2 (38/38) audit findings all fixed ✅
- P3 (~52/54) substantially complete ✅
- PHP 8.4 + PHPUnit 11 + PHPStan 2.2 with strict-rules ✅
- FROZEN-CONTRACTS.md registry (32 declarations) ✅

**Flip actions:**
1. ADR-019 status: pre-MUWV → **MUWV FLIPPED**
2. `release.yml`: removed `--prerelease` flag (see below)
3. First post-MUWV tag: `v1.2.0.0+<sha>` (Milestone 1 = second milestone, Lap 0, Patch 0)
4. ADR-020 stable/edge branch model activated
5. All future releases are regular (non-prerelease) GitHub Releases

**The flip is a one-time governance action — it cannot be reversed.**

### Premature flip event (2026-09-12) — reverted

On 2026-09-12, the MUWV segment was flipped to `1` prematurely based on the integration test passing (PR #156 CORE-18 Kernel merge). The first post-MUWV tag `v1.2.0.0+b4ed694` was created manually on the PR #158 merge commit.

**This was wrong.** The AGRD §4 criterion explicitly requires a "real HTTP request" through the full Rim — not a test-proven architectural round-trip. The premature flip has been reverted:

- Tag `v1.2.0.0+b4ed694` deleted (GitHub Release also deleted)
- ADR-019 status reverted from "MUWV FLIPPED" to "pre-MUWV (flip criterion NOT yet met)"
- README Project Status reverted to "Pre-MUWV"
- release.yml: the 4-segment tag-pattern fix from PR #160 is kept (it correctly excludes the legacy 3-segment `v1.0.0` tag); the only valid 4-segment tags today are `v0.*`

### When the flip will actually happen

The flip is authorized when all 8 Milestone 0 blueprints (the table above) are shipped at depth 1–2 AND `public/index.php` is wired to boot the Kernel and serve a real HTTP request through the full Pulse trace (BRIDGE-01 Vanguard territory). At that point:

- The next release is `v1.2.0.0+<sha>` (post-MUWV, Milestone 1 = second milestone, lap 0, patch 0)
- The milestone counter increments by 1 (Milestone 0 → milestone number 1 → becomes "2" in the second segment after the flip)
- Future pre-MUWV releases are no longer possible
- The flip is a one-time governance action — it cannot be reversed once authorized

## 9. Prerelease labeling (2026-09-12)

**All `v0.*` releases are prerelease.** Per SemVer §9, a version with MUWV=0 is inherently pre-1.0 and is treated as a prerelease. Concretely:

- **GitHub Releases**: every `v0.*` release (and the deprecated legacy tags) is marked with the `prerelease=True` flag on GitHub. This makes them visible as "Pre-release" in the GitHub UI and excludes them from "Latest release" until the MUWV flip.
- **`release.yml`**: the `gh release create` commands in both the per-tier release job and the monorepo-release job include the `--prerelease` flag. All future `v0.*` releases created by the workflow will automatically be marked as prerelease.
- **When MUWV flips**: the `--prerelease` flag is removed from `release.yml` (in the same PR that documents the flip per §8). The first `v1.2.0.0+<sha>` release is a regular (non-prerelease) release.
- **Existing releases retagged**: all 16 existing GitHub releases (7 `v0.*` + 4 `release-*` + 4 `core-*-v1.0.0` + 1 `v1.0.0`) were marked `prerelease=True` on 2026-09-12. The git tags themselves are unchanged (immutable history); only the GitHub Release metadata was updated.

This labeling makes the prerelease status visible to anyone browsing the GitHub Releases page — no one mistakes a `v0.*` tag for a stable release.

## Alternatives considered

### A. Standard `0.x.y` SemVer (no MUWV marker)

- Pro: standard, widely understood, Composer-native
- Con: conflates project-level pre-MUWV with tier-level pre-1.0 (Hub tier is `hub-v0.1.0` — is that pre-MUWV Hub or post-MUWV Hub at 0.1.0? Ambiguous)
- Rejected: loses the explicit MUWV signal

### B. CalVer (`2026.09.0`)

- Pro: no semver debates, chronologically ordered
- Con: no semantic meaning (can't tell if a release has breaking changes from the version number); doesn't align with AGRD's milestone/lap structure
- Rejected: AGRD is the canonical SDLC; the version scheme should reflect AGRD's vocabulary

### C. Keep `1.x.y` and document "actually pre-MUWV"

- Pro: no migration, no tag changes
- Con: actively misleading. `1.0.0` implies a stable API contract per SemVer. DGLab has frozen interfaces per SDLC-AGRD §2.1, but the project as a whole has not reached MUWV. Consumers who see `v1.0.0` and assume stability will be surprised by breaking changes between laps
- Rejected: honesty over convenience

### D. Four-segment with epoch instead of MUWV (`v0.2026.1.3`)

- Pro: combines CalVer with AGRD structure
- Con: year is already in the git commit and the GitHub release date; duplicating it in the version number adds noise without information
- Rejected: redundant

## Consequences

1. **Loom refactoring required.** The `RepoManager::tag()` method's regex `/^\d+\.\d+\.\d+$/` must be extended to `/^\d+\.\d+\.\d+\.\d+$/` (four segments). The `buildTagName()` method must prepend the tier prefix and append `+<sha>` build metadata.

2. **release.yml tag pattern updated.** The `TAG_NAME="${TIER}-v${{ steps.bump.outputs.new_version }}"` line becomes `TAG_NAME="${TIER}-v${NEW_VERSION}+${SHORT_SHA}"`. The monorepo release step uses `v${NEW_VERSION}+${SHORT_SHA}` (no tier prefix).

3. **composer.json `version` field drops build metadata.** The `version` field is `"0.1.3.0"` (four segments, no `+sha`). The `+sha` appears only in git tags and GitHub Release titles.

4. **Existing tags were deprecated then deleted.** All 9 deprecated tags (v1.0.0, release-1.0.0–1.3.0, core-*-v1.0.0 × 4) were deleted from git and GitHub on 2026-09-13. The commits remain in history; only the tag labels were removed. A "Deprecated Tags" section in the README and [`DEPRECATED_TAGS.md`](../DEPRECATED_TAGS.md) document the mapping.

5. **First release under the new scheme.** The next release after this ADR is merged will be `v0.1.3.0+<sha>` — pre-MUWV, Milestone 0, lap 3, patch 0. This reflects the current state: Step 2 triplet (CORE-10/09/08) shipped, CORE-18 (Kernel) not yet shipped, Milestone 0 success criterion not yet met.

6. **PR/Issue templates updated.** PR template gains AGRD-aware checkboxes (Step #, mini cooldown taken Y/N, interfaces frozen Y/N). Issue templates gain AGRD classification field (bug / feature / deepening / lap-marker).

7. **README and CONTRIBUTING updated.** Both gain an "SDLC-AGRD Workflow" section explaining spiral deepening, laps, mini cooldowns (OD-11), and the build order Steps. The README's "Project status" section is updated to reflect the actual current state (Step 2 complete, Step 3 in progress).

## References

- `ADR-014` — ratified SDLC-AGRD v3.4(3) as the canonical SDLC
- `ADR-018` — centralized per-tier release model (this ADR extends the tag format)
- `SDLC-AGRD.md` §4 — Milestone 0 success criterion (the MUWV flip trigger)
- `SDLC-AGRD.md` §7 — cooldowns (2 weeks, or mini cooldowns per OD-11)
- `OPEN-DECISIONS.md` OD-11 — mini cooldown interim policy
- [SemVer 2.0.0 spec](https://semver.org/) §10 — build metadata (`+`) is ignored for precedence
