# SDLC-01: Foundations & Spiral Deepening

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document establishes the foundational methodology for DGLab's Software Development Lifecycle. The methodology itself is a starting point, not a complete specification. Every assumption here — the depth scale, the lap structure, the cooldown gates — is open to challenge when reality contradicts it. The number of methodology decisions made is not the number of methodology decisions that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (replaces the foundations section of `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3), per Tech-Lead directive 2026-10-06: "rewrite with proper details the entire SDLC").
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) (ratified AGRD as canonical SDLC) + [ADR-021](../ADRs/ADR-021-tier-stratified-build-order.md) (two-DAG governance model).
**Related:** [SDLC-02](SDLC-02-Governance.md) (Two-DAG Governance & Eligibility), [SDLC-03](SDLC-03-InterfaceFreeze.md) (Interface Freeze & ADR-Gated Changes), [BLIND-SPOT-DOCTRINE](../CrossCutting/BLIND-SPOT-DOCTRINE.md), [NUCLEAR-GRADE-DOCTRINE](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md), [INTEGRITY-GATE](../Verification/INTEGRITY-GATE.md).

---

## §1. Why a Custom SDLC Exists

Most software methodologies assume a team. They optimize for parallel work, handoffs, code review by peers, and the assumption that knowledge is distributed across people who must communicate. DGLab is built by a solo Tech Lead, optionally assisted by AI systems. The methodology must be calibrated for this reality — not borrowed from team-scale frameworks that assume what a single contributor cannot provide.

The deeper problem: a solo Tech Lead has no peer reviewer inside the project. The AI systems that assist are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning (per the Blind-Spot Doctrine). This is not a defect of the AI — it is a property of any contributor working alone, human or AI. A methodology that assumes peer review catches blind spots will fail when there is no peer.

Spiral Deepening (AGRD — ADR-Gated Radial Delivery) is the response. It is calibrated for:

1. **Single-contributor throughput** — no parallelism assumption, no handoff overhead.
2. **AI-assisted development** — explicit acknowledgment that AI produces work that may look correct while being subtly wrong, with governance gates that require verification conditions (not just CI green).
3. **Sustained pace without burnout** — variable-duration cooldowns gated on rest, not fixed-time sprints.
4. **Depth-graded delivery** — components ship at increasing depth across laps, not all at once.
5. **Architecture-first** — the build order is derived from governance (DAGs + Eligibility formula), not from a developer's preference.

---

## §2. Spiral Deepening — The Core Model

### §2.1 The Depth Scale (1–6)

Every component in DGLab is built at increasing depth across laps. A component does not ship "complete" — it ships at a depth, and the depth increases over time. The depth scale is:

| Depth | What exists | What does NOT exist yet | Gates |
|---|---|---|---|
| **1 — Stub** | Interface declared, class exists, throws "not implemented" on call. | Any behavior. | Architecture-lint (interface must reference real IDs); blueprint ratified at depth 1. |
| **2 — Happy path** | The primary use case works end-to-end. Error paths throw or no-op. Tests cover the happy path only. | Edge cases, failure modes, observability, hardening. | PHPUnit happy-path tests pass; PHPStan level max; architecture-boundary-lint clean. |
| **3 — Error paths** | All documented failure modes are handled with typed exceptions. Tests cover negative paths. | Observability, resource ceilings, chaos tests. | Negative-path tests pass; exception types frozen per FROZEN-CONTRACTS. |
| **4 — Observability** | Structured logging, metrics, traces, health checks. Audit feed populated. | Production hardening, chaos tests. | Observability spec met per [OBSERVABILITY.md](../CrossCutting/OBSERVABILITY.md); audit feed verified. |
| **5 — Production hardening** | Resource ceilings, circuit breakers, idempotency, atomicity, validation at every boundary. Per [NUCLEAR-GRADE-DOCTRINE](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) §4 per-package application. | At-scale verification, chaos testing. | Nuclear-Grade §9 merge gate passed; chaos tests designed. |
| **6 — At-scale verification** | Chaos tests pass. Load tests meet benchmark methodology. Integrity Gate convergence criteria met for this component. | — (this is the deepest depth in the current model) | Integrity Gate criteria per [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md); chaos tests pass. |

**Key invariant:** depth is monotonic. A component at depth N has ALL the properties of depth N-1, plus the new ones. There is no "depth 3 without depth 2" — the depth scale is cumulative.

### §2.2 What "Depth" Is NOT

Depth is not:
- **A version number.** Depth is a property of the component's implementation, not the package version. A package can be at v1.5.0 and depth 2.
- **A completion percentage.** A component at depth 2 is not "33% complete." It is "happy-path complete" — a different state than "error-paths complete" (depth 3).
- **A schedule.** Depth is not "we will reach depth 3 by Q3." Depth is reached when the verification conditions for that depth pass, not when the calendar says so.
- **Reversible.** A component does not "go back to depth 2" once at depth 3. If a depth-3 property is found to be broken, that is a defect to fix, not a depth reversion.

### §2.3 The Nuclear-Grade Doctrine Cross-Reference

Depth 5 (production hardening) is the depth at which the [Nuclear-Grade Doctrine](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) becomes binding. The doctrine's 12 principles, 5-class error taxonomy, and per-package application sections (§4.1–§4.5+) are REQUIRED for depth-5 admission. A component cannot be at depth 5 without the doctrine's §9 merge gate passing.

This is not optional. The doctrine is binding governance, not advisory. Where the doctrine and a per-package blueprint disagree, the doctrine wins (per the doctrine's own §0).

---

## §3. Laps — The Spiral's Widen-and-Deepen Cycle

### §3.1 What a Lap Is

A lap is one pass through the build order. The build order is tier-stratified (Runtime → Core → Hub → Applications → Deploy) and per-tier topological (derived from the two-DAG governance model — see [SDLC-02](SDLC-02-Governance.md)).

A lap has two motions:

1. **Widen** — add new components at depth 1 (stubs) or depth 2 (happy path) to the existing set. The set of shipped components grows.
2. **Deepen** — increase the depth of existing components. A component at depth 2 in lap N may reach depth 3 in lap N+1, depth 4 in lap N+2, etc.

Both motions happen in every lap. This is the "Spiral Deepening" name: the spiral widens (more components) AND deepens (greater depth) with each cycle.

### §3.2 Lap Structure

```
Lap N:
  ┌─────────────────────────────────────────────┐
  │  1. Build order computation (per SDLC-02)   │
  │     - DAG-derived waves                     │
  │     - Eligible(X) gates applied             │
  │     - Practical roadmap constraints applied │
  ├─────────────────────────────────────────────┤
  │  2. Implementation phase                    │
  │     - New components: depth 1 or 2          │
  │     - Existing components: deepen +1        │
  │     - Per-component: PRs are focused        │
  ├─────────────────────────────────────────────┤
  │  3. Mini-cooldowns (OD-11)                  │
  │     - ~1-day checkpoints between Steps      │
  │     - OD triage, refactor backlog           │
  ├─────────────────────────────────────────────┤
  │  4. Lap merge                               │
  │     - All Step PRs merged to main           │
  │     - Tag: v<MUWV>.<Milestone>.<Lap>.0+sha   │
  ├─────────────────────────────────────────────┤
  │  5. Cooldown (variable duration, §5)        │
  │     - Worklog reconciliation                │
  │     - OD triage                             │
  │     - Refactor backlog                      │
  │     - Rest check (gated, not time-boxed)    │
  └─────────────────────────────────────────────┘
```

### §3.3 Lap Counting

Lap count is tracked in the version number per [ADR-019](../ADRs/ADR-019-pre-muwv-version-scheme.md):

```
v<MUWV>.<Milestone+1>.<Lap>.<Patch>+<git-sha>
```

- `MUWV` = 0 pre-walking-skeleton, 1 post-walking-skeleton (flipped 2026-09-18).
- `Milestone` = Milestone number + 1 (Milestone 0 → segment 1, Milestone 1 → segment 2).
- `Lap` = lap within the milestone (resets at each milestone).
- `Patch` = patch within the lap (0 = first release of the lap).

Example: `v1.2.3.0+abc1234` = post-MUWV, Milestone 1, lap 3, patch 0, git SHA `abc1234`.

---

## §4. Milestones — The Walking-Skeleton Gates

### §4.1 Milestone 0 — Walking Skeleton

Milestone 0 is the "walking skeleton" milestone. It requires:

1. **All 8 Milestone 0 blueprints at depth 1–2:**
   - CORE-02 (Container), CORE-04 (HTTP Message), CORE-05 (Middleware), CORE-06 (Router), CORE-18 (Kernel)
   - HUB-01 (Hub Config & Flags), BRIDGE-01 (Vanguard), ESPOKE-01 (Canvas)
2. **A real HTTP request through the full Pulse trace:** Outer Rim → Inner Rim → Inner Spoke → return. Not a unit test — a real request through `public/index.php` → `ApplicationFactory` → `Kernel` → `Router` → controller → 200 response.
3. **The MUWV criterion per ADR-019 §8:** all 8 blueprints + real HTTP request.

**MUWV flip:** When Milestone 0 is complete, the version's MUWV segment flips from `0` to `1`. This is an ADR-gated event (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §3) — it cannot happen silently.

The MUWV flip for DGLab happened on 2026-09-18, after all 8 Milestone 0 blueprints shipped and the full Pulse trace was verified end-to-end via `HelloWorldTest::testHelloWorldRoundTrip`.

### §4.2 After Milestone 0 — The Missing Chapter

Pre-Milestone-0, the SDLC was about reaching walking-skeleton. Post-Milestone-0, the SDLC must govern sustained development. The transition is significant:

- Pre-MUWV: every PR is "are we walking yet?" The bar is binary — does the skeleton walk?
- Post-MUWV: every PR is "are we deepening correctly?" The bar is graded — does this PR advance the depth of a component without regressing others?

The post-Milestone-0 chapter requires:
- The two-DAG governance model (per [SDLC-02](SDLC-02-Governance.md)) — declared vs verified reality, never silently merged.
- The Eligible(X) formula (per [SDLC-02](SDLC-02-Governance.md) §3) — admission is gated on dependency closure, not preference.
- The Integrity Gate (per [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md)) — convergence criteria for the integrity phase, with a finite stopping condition.
- The Blind-Spot Doctrine (per [BLIND-SPOT-DOCTRINE.md](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — explicit acknowledgment that audits are starting points, not complete inventories.

### §4.3 Lap Structure — Widens and Deepens Every Cycle

A common misunderstanding: laps are not "deepen-then-widen" (deepen existing components first, then add new ones). They are "widen AND deepen" — both motions happen in every lap.

Why: if you deepen-first, you spend a lap making depth-2 components depth-3, but no new components ship. Then in the next lap you widen — but now your new components are entering a tier where everything else is at depth 3, and they are at depth 1. The depth gap creates integration risk.

Widen-and-deepen simultaneously keeps the depth distribution more uniform. New components enter at depth 1–2; existing components deepen by +1. The gap between any two components is at most 1–2 depth levels.

---

## §5. Cooldowns — Variable Duration, Gated on Rest

### §5.1 Why Cooldowns Exist

Cooldowns are not breaks. They are required governance phases between laps where:

1. **Worklog reconciliation** — every agent (human or AI) that worked on the lap appends to `worklog.md`. The cooldown verifies the worklog is complete and consistent.
2. **OD triage** — Open Decisions are reviewed. Some are resolved (becoming ADRs), some are deferred (with explicit rationale), some are closed (with verification evidence).
3. **Refactor backlog** — technical debt accumulated during the lap is reviewed. Some is scheduled for the next lap; some is paid down immediately; some is accepted with explicit rationale.
4. **Rest check** — the Tech Lead verifies they are not running on fumes. Solo development under fatigue produces confident-but-wrong work (the exact failure mode the Blind-Spot Doctrine warns about).

### §5.2 Variable Duration (v3.5)

Pre-v3.5, cooldowns were a fixed 2 weeks. v3.5 (ratified 2026-10-06, per [SDLC-03](SDLC-03-InterfaceFreeze.md) §10 changelog) makes duration variable:

- The cooldown lasts until the four gates above (worklog, OD, refactor, rest) all pass.
- The minimum is 1 week; the maximum is 4 weeks.
- If the rest check fails at 4 weeks, the cooldown extends with explicit Tech-Lead acknowledgment that the project is in low-throughput mode.

### §5.3 Mini-Cooldowns (OD-11)

Between Steps within a lap (not between laps), mini-cooldowns provide ~1-day checkpoints:

- **Worklog update** — the active task appends its work log.
- **OD triage** — any ODs opened during the Step are dispositioned.
- **Refactor check** — any debt incurred is noted (not necessarily paid down).
- **CI verification** — all CI for the Step's PRs is verified green, with explicit check that the lint actually executed (not just "the workflow step succeeded").

Mini-cooldowns do NOT include a rest check — they are too short for meaningful rest. They are governance checkpoints, not recovery periods.

### §5.4 The Blind-Spot Doctrine Cross-Reference

The cooldown's CI verification step is where the Blind-Spot Doctrine's "CI green ≠ verification conditions met" rule bites hardest. Per the doctrine:

> "A finding is closed only when its verification condition passes — not when code changes, not when CI is green."

Three recurrences of this pattern during the integrity phase (S-054 test file missing, S-033 DAG count stale, S-077 register not updated) confirmed: CI green is necessary but not sufficient. The cooldown's CI verification step must check that the SPECIFIC verification conditions for the Step's claims pass, not just that the workflow step exited 0.

---

## §6. Versioning — The Four-Segment Scheme

Per [ADR-019](../ADRs/ADR-019-pre-muwv-version-scheme.md), DGLab uses a four-segment version scheme:

```
v<MUWV>.<Milestone+1>.<Lap>.<Patch>+<git-sha>
```

| Segment | Meaning | Example |
|---|---|---|
| `MUWV` | 0 = pre-walking-skeleton, 1 = post-walking-skeleton | `0` → `1` flip on 2026-09-18 |
| `Milestone+1` | Milestone number + 1 (Milestone 0 → `1`, Milestone 1 → `2`) | Milestone 1 → `2` |
| `Lap` | Lap within the milestone (resets at each milestone) | Lap 0, 1, 2, ... |
| `Patch` | Patch within the lap (0 = first release) | 0, 1, 2, ... |
| `+sha` | 7-char git short SHA (build metadata, ignored for precedence) | `abc1234` |

**Tag formats:**
- Monorepo releases: `v1.2.0.0+abc1234`
- Per-tier releases: `core-v1.2.0.0+abc1234` (per [ADR-018](../ADRs/ADR-018-centralized-per-tier-releases.md))

**Deprecated tags** (historical, not retagged): see [`DEPRECATED_TAGS.md`](../DEPRECATED_TAGS.md).

### §6.1 The MUWV Flip Criterion

The MUWV segment flips from `0` to `1` when:

1. All 8 Milestone 0 blueprints are at depth 1–2.
2. A real HTTP request through the full Pulse trace works end-to-end.
3. The flip is authorized by an ADR-gated event (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §3).

The MUWV flip is not a milestone that gets reached on a schedule — it is a verification condition that gets passed. Pre-MUWV history (`v0.1.0.0` → `v0.1.35.0`) remains marked as prerelease on GitHub.

### §6.2 Premature Flip — The 2026-09-12 Incident

The MUWV segment was flipped to `1` prematurely on 2026-09-12 based on the integration test passing. The AGRD §4 criterion requires all 8 blueprints + a real HTTP request through the full Rim. The premature `v1.2.0.0+b4ed694` tag and GitHub release were deleted; ADR-019 §8 documents the corrected criterion.

This is a documented case of the blind-spot pattern: a verification condition (integration test green) was conflated with a different verification condition (full Pulse trace). The correction is recorded to prevent recurrence.

---

## §7. The Solo Tech Lead's Invariant

DGLab is built by a solo Tech Lead, optionally assisted by AI systems. This imposes invariants that team-scale methodologies do not have:

1. **No peer review inside the project.** The linter is the second reviewer (per [SDLC-02](SDLC-02-Governance.md) §8). Where the linter does not check, there is no review.
2. **AI work is not automatically verified.** Every AI-assisted change is treated as a hypothesis, not a fact. The verification condition for the change must pass — not just CI green.
3. **The Tech Lead's fatigue is a project risk.** Cooldowns gate on rest because solo development under fatigue produces confident-but-wrong work. The rest check is not optional.
4. **The architecture is the contract.** When the Tech Lead is unavailable, the architecture (blueprints, ADRs, DAGs) is what continues to govern. Without peer review, the architecture must be self-enforcing — via lints, gates, and verification conditions.

These invariants are the reason SDLC-AGRD exists. They are not preferences; they are constraints imposed by the solo development model.

---

## §8. What This Document Does NOT Specify

This document establishes foundations. It does NOT specify:

- **The two-DAG governance model** — see [SDLC-02](SDLC-02-Governance.md).
- **The Eligible(X) formula** — see [SDLC-02](SDLC-02-Governance.md) §3.
- **Interface freeze rules** — see [SDLC-03](SDLC-03-InterfaceFreeze.md).
- **ADR format** — see [SDLC-03](SDLC-03-InterfaceFreeze.md) §2.
- **Open Decisions lifecycle** — see [SDLC-03](SDLC-03-InterfaceFreeze.md) §4.
- **Fidelity Bar** — see [SDLC-03](SDLC-03-InterfaceFreeze.md) §5.
- **Blind-Spot Doctrine binding rules** — see [BLIND-SPOT-DOCTRINE.md](../CrossCutting/BLIND-SPOT-DOCTRINE.md).
- **Nuclear-Grade Doctrine** — see [NUCLEAR-GRADE-DOCTRINE.md](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md).
- **Integrity Gate convergence criteria** — see [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md).

This document references these specifications; it does not duplicate them. When a referenced specification changes, this document's references remain valid (they are pointers, not copies).

---

## §9. Provenance

This document is part of the SDLC rewrite (PR #316, 2026-10-07), per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time."

It replaces the foundational content of `SDLC-AGRD.md` v3.4(3) (specifically §1 Why, §2 Methodology shift, §3 Cooldown 0, §4 Milestone 0). The original `SDLC-AGRD.md` is retained as a redirect to this document, [SDLC-02](SDLC-02-Governance.md), and [SDLC-03](SDLC-03-InterfaceFreeze.md).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §5.4 cross-reference + §7 invariants)
- Nuclear-Grade Doctrine (§2.3 cross-reference: depth 5 = doctrine binding)
- Integrity Gate (§2.1 depth 6 = convergence criteria; §5.4 CI-green-≠-verification rule)
- Two-DAG Governance (§3.2 build order computation per SDLC-02; §6.1 MUWV flip per ADR-019)
- FROZEN-CONTRACTS (§2.1 depth 3 = exception types frozen; details in SDLC-03)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link in this document points to a canonical document. Verified by lint's reference-existence check.
- Doctrine application: every doctrine listed in §9 is actually applied in the document body (not just listed).

This document is a starting point. It is not a complete specification. The number of methodology decisions made here is not the number of methodology decisions that exist.
