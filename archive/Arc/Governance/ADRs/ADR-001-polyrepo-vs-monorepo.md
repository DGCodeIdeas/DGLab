# ADR-001: Polyrepo with CORE-01 (Loom) Orchestration

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

The DGLab repository currently lives across more than fifty independently versioned Git repositories under the `DGCodeIdeas/DGLab` GitHub organization. This is the canonical Vision B layout: a single "monorepo of blueprints" (`DGLab`) accompanied by ~50+ product repositories (`packages/core/container`, `packages/core/event-dispatcher`, `orchestrator`, plus 30+ Hub blueprints that each resolve to their own repo at build time). Finding 1 in `00_CRITIQUE.md` documents that the repo simultaneously carries the *legacy* Vision A narrative (`docs/architecture/origin/`, a monolith prose description) and the *canonical* Vision B narrative (`docs/blueprints/`, a polyrepo). This ADR records the decision to make Vision B's polyrepo layout the source of truth.

The polyrepo decision is already structurally enforced. `orchestrator/src/DependencyGraph.php` models every tier (Core/Hub/Spoke) as a node graph with explicit inter-repo dependencies, throws on Core→Hub cross-tier pollution, and resolves an ordered release sequence via Kahn's algorithm. The orchestrator's `composer.json` declares no `replace` or `provide` entries that would suggest a merged package. `CORE-01` ("Polyrepo Orchestrator", a.k.a. the "Loom") exists *specifically* because the polyrepo layout would otherwise be unmanageable: with ~50+ independently versioned packages, manual SemVer bookkeeping, cross-repo CI gating, and ordered release promotion would consume a human full-time.

Finding 19 in `00_CRITIQUE.md` flags that this architectural choice — like the other nine covered by ADRs 002–010 — was made without a recorded decision. The Vision A narrative in `docs/architecture/origin/` predates Vision B and describes a Laravel-style monolith; absent this ADR, a future maintainer could reasonably ask "why are we paying the orchestration tax when we could collapse to a monorepo?" with no documented answer. This ADR fixes that.

## Decision

We adopt a **polyrepo architecture with ~50+ independently versioned repositories**, organized into three strict tiers (Core, Hub, Spoke), with cross-tier dependency ordering enforced by the CORE-01 Orchestrator ("Loom"). The monorepo at `DGCodeIdeas/DGLab` is the *documentation and blueprint* root only; it is not the runtime code root. Each Core, Hub, and Spoke component ships as its own Composer package with its own `composer.json`, its own SemVer, its own CI workflow, and its own release cadence.

Tier ordering is non-negotiable: Core repos may depend only on other Core repos; Hub repos may depend on Core and Hub; Spoke repos may depend on Core, Hub, and (for External Spokes) on BRIDGE-01. The Orchestrator (`SovereignStack\Orchestrator\DependencyGraph`) is the single source of truth for this ordering; CI gates fail closed if a tier violation is detected.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **Monorepo with Lerna-style workspaces** (single Git repo, multi-package Composer workspace, e.g. using `wikimedia/composer-merge-plugin` or `symfony/flex`) | Atomic cross-repo refactors; single CI pipeline; one PR touches multiple packages; simpler dependency graph; no Loom required for cross-repo coordination | Loses independent versioning; one broken package blocks all releases; CI matrix grows combinatorially; doesn't match how Spoke applications will be deployed (one Spoke per customer); forces a single deploy cadence on heterogenous services | Rejected — independent deployability is a hard product requirement (Spokes are customer-scoped), and the per-package release cadence is a documented Sovereign Stack value |
| **Monolith** (single deployable Laravel/Symfony application, like Vision A in `docs/architecture/origin/`) | Lowest operational complexity; well-trodden path; PHP ecosystem has 20 years of tooling | Directly contradicts the tier-isolation boundary (BRIDGE-01); forces all Spokes into one deployment; defeats the multi-tenant isolation story in HUB-21; the Bridge enforcement layer becomes a no-op; cannot scale teams independently | Rejected — Vision A was superseded for these reasons |
| **Hybrid: monorepo for Core, polyrepo for Hub/Spoke** | Compromise; Core benefits from atomic refactors, Hub/Spoke keep independence | Adds two orchestration models; breaks the uniform `DependencyGraph` model; CORE-01 would need a special-case for "Core is one repo but Hub is many"; CI complexity is doubled, not halved | Rejected — uniformity of the dependency model is worth more than the marginal Core-refactor ergonomics |
| **Polyrepo + third-party monorepo tool** (e.g. adopt `monorepo-builder` or `phase2/soa` even though packages live in separate repos) | Some tooling benefits (changelog generation, cross-repo consistency checks) | Adds a non-trivial dependency to solve a problem CORE-01 already solves; CORE-01 is purpose-built for tier ordering and is already implemented and tested | Rejected — CORE-01 already covers this; the Loom *is* the monorepo tool, just at the orchestration layer |

## Consequences

**Positive:**
- Each repo can release on its own cadence: a security patch in `packages/core/container` ships without forcing a re-release of every Hub package. This is essential for the Spoke model where customer-facing applications must absorb upstream patches at their own pace.
- Tier isolation becomes physically enforceable, not just conventionally enforced. The Orchestrator's `addDependency()` throws `RuntimeException` on any Core→Hub violation; this would be impossible to enforce with equal rigor inside a monorepo where imports are file-path based.
- Teams can own repos end-to-end (one team owns `HUB-04 Identity`, another owns `HUB-11 Cloud Storage`) with clear ownership boundaries, separate CI runners, separate secrets, and separate deploy targets.

**Negative:**
- Cross-repo refactors are expensive. Renaming a Core-tier interface (e.g. `ContainerInterface` in CORE-02) requires coordinated PRs across every consumer, with version constraints updated in each downstream `composer.json`. The Orchestrator mitigates but does not eliminate this cost.
- CI complexity scales linearly with repo count: ~50+ GitHub Actions workflows, ~50+ `phpunit.xml.dist` files, ~50+ `phpstan.neon` configs. Inconsistencies drift in. A future "Repo Bootstrap" tool (CORE-20 Sovereign Forge) is needed to scaffold new repos from a single template.
- The Orchestrator itself becomes a critical-path dependency. If CORE-01 breaks, releases across the entire ecosystem block. This is why CORE-01 is implemented and tested before CORE-02 (Finding 8 in `00_CRITIQUE.md` notes CORE-01 is ✅ Implemented while CORE-02 is ❌ Stub).

**Neutral:**
- The Loom must be maintained indefinitely as the tier-ordering authority. Its `DependencyGraph` algorithm (Kahn's with tier-priority re-sort — see ADR-004) is now a load-bearing component of the release process.
- Documentation lives in a separate monorepo (`DGLab` itself) from the code it documents, which is unusual but intentional: blueprints are versioned and released as a unit, decoupled from the runtime code cadence.

## Links
- Related ADRs: ADR-002 (PSR-11 container — first Core repo to land), ADR-004 (Kahn's algorithm in the Orchestrator), ADR-010 (OPcache preload — depends on per-repo class maps)
- Related blueprints: CORE-01 (Polyrepo Orchestrator / "Loom"), CORE-17 (Service Provider System), BRIDGE-01 (Strict Boundary Policy)
- Related findings: Finding 1 (two incompatible architectures), Finding 19 (empty ADR directory)
- External references: Nygard ADR template; Semantic Versioning 2.0.0 (semver.org); Conventional Commits 1.0.0
