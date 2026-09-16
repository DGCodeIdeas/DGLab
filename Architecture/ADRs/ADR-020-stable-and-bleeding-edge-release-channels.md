# ADR-020: Stable and Bleeding Edge release channels

**Status:** Accepted
**Date:** 2026-09-17
**Decided by:** Architecture lead (DGCI)

## Context

After the MUWV flip (ADR-019 §8), DGLab transitions from `v0.X.Y.Z` (pre-MUWV, all prerelease) to `v1.X.Y.Z` (post-MUWV). At that point, every merge to `main` produces a release — but not every release is production-ready. Some contain new features that need a cooldown to harden; others are bug fixes that are safe immediately.

DGLab expects external contributors. A contributor opening a PR needs to know:
1. Is `main` the bleeding edge, or is there a separate `stable` branch?
2. Which branch should their PR target?
3. Which release should a downstream consumer install — the latest, or the last known-good?

ADR-019 §9 marks all `v0.*` releases as prerelease on GitHub. After the MUWV flip, this policy needs to evolve: some `v1.*` releases are stable (hardened, cooldown-verified) and some are bleeding edge (latest features, may have rough edges).

## Decision

### 1. Two release channels: `stable` and `edge`

| Channel | Branch | Prerelease flag | SemVer segment | Description |
|---------|--------|-----------------|----------------|-------------|
| **Edge** | `main` | `--prerelease` | Lap segment increments per merge | Every merge to `main` produces an edge release. Latest features, may not be hardened. |
| **Stable** | `stable` | (no flag) | Patch segment increments per promote | Cherry-picked or fast-forwarded from `main` after a cooldown confirms the release is production-ready. |

### 2. Tag format

```
Edge:    v1.2.3.0-edge+abc1234    (prerelease=True on GitHub)
Stable:  v1.2.3.0+abc1234         (prerelease=False on GitHub)
```

The `-edge` suffix in the tag name makes the channel visible in `git tag --list` — no ambiguity. When an edge release is promoted to stable, a new tag is created pointing at the same commit but without the `-edge` suffix.

### 3. Branch model

```
main (edge):
  Every PR merge → tag v1.2.N.0-edge+sha (prerelease)
  Contributors open PRs against main.
  CI runs on every push.

stable:
  Created by `loom release:promote` after a cooldown.
  Points at a specific commit on main (fast-forward or cherry-pick).
  Tag: v1.2.N.0+sha (no -edge, no prerelease flag).
  Hotfixes: cherry-pick from main, or direct PR to stable (requires 2-reviewer sign-off).
```

### 4. Promote workflow

```bash
# Promote the latest edge release to stable:
loom release:promote --latest

# Promote a specific edge tag:
loom release:promote v1.2.3.0-edge+abc1234
```

The promote command:
1. Fast-forwards `stable` to the specified commit (or creates it if it doesn't exist)
2. Creates a new tag `v1.2.3.0+abc1234` (without `-edge`) pointing at the same commit
3. Creates a GitHub Release with `prerelease=False`
4. The edge tag remains in history — it's not deleted (backward compat for anyone who pinned it)

### 5. Contributor workflow

| Task | Target branch |
|------|---------------|
| Bug fix / feature PR | `main` (edge) |
| Hotfix for a stable release | `stable` (requires 2-reviewer sign-off, then cherry-pick to `main`) |
| Security fix | `main` (then immediate promote to `stable`) |
| Documentation PR | `main` (then promote to `stable` if it's a user-facing doc) |

Contributors never need to interact with `stable` directly unless they're doing a hotfix. The `main` branch is always the latest edge — the canonical development target.

### 6. AGRD integration

- **Cooldown** (per OD-11): after each lap on `main`, take a mini-cooldown (interface-freeze audit + refactor triage). If the cooldown passes, `loom release:promote` creates the stable release.
- **Not every edge release is promoted.** Only releases that pass the cooldown + have no known P1/P2 issues are promoted. An edge release that introduces a regression is skipped — the next promote picks up the fixed release.
- **SemVer semantics**: stable releases are the SemVer contract. Edge releases are "best effort" — breaking changes may appear in edge between stable releases (within the same milestone).

### 7. Composer constraints

Downstream consumers use stability flags:

```json
{
    "require": {
        "sovereign-stack/core-container": "v1.2.3.0"     // stable only
    },
    "minimum-stability": "stable"
}
```

or

```json
{
    "require": {
        "sovereign-stack/core-container": "v1.2.4.0-edge"  // bleeding edge
    },
    "minimum-stability": "dev"
}
```

The `minimum-stability: stable` setting excludes `-edge` tagged releases from resolution, so consumers who want stability never accidentally pull a bleeding-edge release.

## Alternatives considered

### A. Pre-release flag only (no branch, no tag suffix)

- Pro: simplest, zero branch management
- Con: tag names identical for stable and edge — `git tag --list` can't distinguish. Contributors can't tell which release is which from the tag alone. Rejected for contributor clarity.

### B. Per-tier stable/edge (each package independently promoted)

- Pro: finer-grained — core-container can be stable while core-kernel is edge
- Con: combinatorial explosion of release states. A consumer can't say "give me stable" — they have to check each package. Rejected for consumer simplicity.

## Consequences

1. **New `stable` branch** created at the MUWV flip, pointing at the same commit as `main`.
2. **`release.yml` updated**: `main` pushes create `-edge` tags with `--prerelease`. A new `promote.yml` workflow (or `loom release:promote`) creates stable tags.
3. **ADR-019 §9 updated**: the `--prerelease` flag on `release.yml` stays for edge releases. Stable releases are created without it.
4. **CONTRIBUTING.md updated**: documents the `main` (edge) vs `stable` branch model and the contributor workflow.
5. **README.md updated**: "Current version" section shows both the latest edge and the latest stable.

## References

- [ADR-019](ADR-019-pre-muwv-version-scheme.md) — pre-MUWV version scheme + prerelease labeling
- [SDLC-AGRD](../CrossCutting/SDLC-AGRD.md) §7 — cooldowns (2 weeks, or mini cooldowns per OD-11)
- [Git Flow](https://nvie.com/posts/a-successful-git-branching-model/) — the original branch model (adapted: no `develop` branch, `main` IS the edge)
