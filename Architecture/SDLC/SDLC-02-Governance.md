# SDLC-02: Two-DAG Governance & Eligibility

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document specifies the governance model that gates which components may be built and in what order. The model itself (two-DAG, edge dimensions, Eligible formula) is a starting point, not a complete specification. Every edge classification, every requiredness decision, every gate is open to challenge when implementation reality contradicts it. The number of governance rules specified here is not the number of governance rules that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (replaces the governance content of `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3), per Tech-Lead directive 2026-10-06: "rewrite with proper details the entire SDLC").
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-021](../ADRs/ADR-021-tier-stratified-build-order.md) (two-DAG governance model + edge dimensions) + [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) (ratified AGRD as canonical SDLC).
**Related:** [SDLC-01](SDLC-01-Foundations.md) (Foundations & Spiral Deepening), [SDLC-03](SDLC-03-InterfaceFreeze.md) (Interface Freeze & ADR-Gated Changes), [INTEGRITY-GATE](../Verification/INTEGRITY-GATE.md), [BLIND-SPOT-DOCTRINE](../CrossCutting/BLIND-SPOT-DOCTRINE.md), [SHORTCOMINGS-REGISTER](../Verification/SHORTCOMINGS-REGISTER.md).

---

## §1. The Two-DAG Model

### §1.1 Why Two DAGs, Not One

A single DAG cannot distinguish two fundamentally different questions:

1. **What does the architecture intend to depend on?** (Declared — what the blueprints say should exist.)
2. **What does the implementation actually depend on?** (Verified — what the repository proves exists.)

A single DAG conflates these. If the architecture declares that HUB-02 depends on CORE-15, but the implementation has not yet imported CORE-15 anywhere, a single DAG would either:
- Show the edge (declared intent) — masking the implementation gap.
- Hide the edge (verified reality) — losing the architectural intent.

Neither is correct. The two-DAG model preserves both:

- **Declared Architecture DAG** — what the architecture intends. Source: blueprints (Upward/Downward dependency lists), ADRs, SPEC. Authority: architectural intent.
- **Verified Implementation DAG** — what the repository proves. Source: `composer.json` requires + source `use` statements + filesystem evidence. Authority: implementation reality.

Both are authoritative for different purposes. They are NEVER silently merged. When they disagree, that disagreement is a finding in [`SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing one DAG to match the other.

### §1.2 Edge Dimensions (per ADR-021 Amendment 2)

Every edge in either DAG has four orthogonal dimensions:

| Dimension | Values | Meaning |
|---|---|---|
| `edge_type` | `COMPILE` / `RUNTIME` / `INTEGRATION` / `CAPABILITY` | HOW the dependency works (mechanics + delivery semantics). |
| `requiredness` | `REQUIRED` / `OPTIONAL` | WHETHER the dependency is mandatory (gating decision). |
| `status` | `VERIFIED` / `DECLARED_ONLY` / `UNDECLARED_VERIFIED` / `INVALID` | Whether the edge exists in both DAGs, one, or neither. |
| `gates` | list of gate names | Which admission gates apply to this edge. |

These are orthogonal. An edge can be `COMPILE + REQUIRED`, `RUNTIME + OPTIONAL`, `INTEGRATION + REQUIRED`, etc. The `edge_type` describes mechanics; `requiredness` describes the gating decision.

### §1.3 Multigraph Semantics

A pair of nodes can have MULTIPLE edges between them, distinguished by `edge_type`. The edge identity is `(source, target, edge_type)` — not just `(source, target)`. This means HUB-06 can depend on HUB-04 via a `COMPILE` edge (for type references) AND a `RUNTIME` edge (for service calls) — both are real, both are tracked.

### §1.4 Status Taxonomy

An edge's status is computed from its presence in the two DAGs:

| Declared? | Verified? | Status | Meaning |
|---|---|---|---|
| Yes | Yes | `VERIFIED` | Architecture intends it; implementation proves it. The healthy state. |
| Yes | No | `DECLARED_ONLY` | Architecture intends it; implementation hasn't caught up. May be a gap or a deferred edge. |
| No | Yes | `UNDECLARED_VERIFIED` | Implementation has it; architecture doesn't say so. Likely a blueprint gap — the blueprint should declare what the code does. |
| No | No | (no edge) | Neither intends nor proves. The edge doesn't exist. |
| Yes (but invalid) | — | `INVALID` | The declared edge violates a governance rule (e.g., wrong direction, forbidden edge_type). |

A `DECLARED_ONLY` edge is not necessarily a problem — it may be a planned future dependency. An `UNDECLARED_VERIFIED` edge is not necessarily a problem — it may be a legitimate implementation detail not worth blueprinting. But BOTH are findings to disposition (per [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md)).

---

## §2. The Eligible(X) Formula

### §2.1 The Formula

A capability `X` is **build-ready** (`Eligible(X) = true`) when ALL of the following are true:

1. **All REQUIRED dependencies at depth ≥ 2.** Every edge into `X` with `requiredness=REQUIRED` points to a component at depth ≥ 2 in the verified DAG. (OPTIONAL edges do not gate admission.)
2. **Blueprint ratified at depth 1.** The blueprint for `X` exists and has been ratified (interface declared, lint passes, H1 matches filename).
3. **Architecture gate passed.** The architecture-lint and architecture-boundary-lint both pass with `PASS` (not `UNVERIFIED` — see §5).
4. **SDLC admission criteria met.** Per [SDLC-01](SDLC-01-Foundations.md) §3 (lap structure), the build-order computation has placed `X` in a wave where all its REQUIRED dependencies are in earlier waves.

If ANY of these fail, `Eligible(X) = false`. The component cannot be built in this lap.

### §2.2 The Build Order Is Generated, Not Authored

Build orders are **generated artifacts** derived from:
- The Declared DAG (architectural intent — which edges exist)
- The Verified DAG (implementation reality — which dependencies are at depth ≥ 2)
- Governance resolutions (per [SHORTCOMINGS-REGISTER.md](../Verification/SHORTCOMINGS-REGISTER.md) — explicit dispositions for ambiguous edges)
- SDLC admission state (which components are in which wave)

Build orders are NOT independently authored. They are regenerated on each merge. They are status-driven — link to the generated artifact, not to specific wave content (waves change).

Canonical generated artifacts:
- [`Architecture/Core/CORE-BUILD-ORDER.md`](../Core/CORE-BUILD-ORDER.md) — Core-tier topological waves.
- [`Architecture/Hub/HUB-BUILD-ORDER.md`](../Hub/HUB-BUILD-ORDER.md) — Hub-tier topological waves.

### §2.3 Wave Computation (Kahn-Style)

Waves are computed via Kahn's algorithm (per [ADR-004](../ADRs/ADR-004-tier-enforcement-dag.md)):

1. **Initial built set** = components already at depth ≥ 2 (the foundation).
2. **Wave N** = unbuilt components whose ALL REQUIRED dependencies are in `built ∪ Wave 0..N-1` AND whose Core blockers (if any) are resolved.
3. **Blocked** = components with at least one REQUIRED dependency not at depth ≥ 2, OR blocked by an unimplemented Core package.

Waves are deterministic — the same DAG + admission state always produces the same waves. This is a verification condition: if two runs of the generator produce different waves, that is a defect in the generator, not a feature.

---

## §3. The Four-Tool Lint Separation

### §3.1 Why Four Tools, Not One

A single monolithic linter would conflate different concerns:
- Documentation reference integrity (Architecture/ files)
- Executable ring-boundary enforcement (PHP source code)
- Repository hygiene (.gitignore, archive/, root detritus)
- Canonical semantic model (DAGs, INDEX, governance consistency)

Each concern has a different scope, different inputs, different failure modes. A monolithic linter either does too much (slow, hard to maintain) or too little (claims to check X but doesn't actually).

The four-tool separation gives each tool ONE clear responsibility:

| Tool | Scope | Inputs | Failure mode |
|---|---|---|---|
| **architecture-lint** | `Architecture/` documentation | `.md` files under `Architecture/` | Invalid tokens, misattribution phrases, missing structural files. |
| **architecture-boundary-lint** | Executable source | `packages/**/src/*.php`, `app/*.php` | Ring-boundary violations, service-locator anti-patterns, export-allow-list violations. |
| **repository-hygiene** (planned) | Root, config, archive, .gitignore | `.gitignore`, root files, archive/ | Tracked scratch files, archive boundary violations, generated artifacts in tracked state. |
| **architecture-state-validator** (planned) | Canonical semantic model | DAGs, INDEX, governance consistency | Two-DAG drift, eligibility formula violations, build-order artifact staleness. |

### §3.2 The PASS/FAIL/UNVERIFIED Invariant

Every CI check has three states, not two:

| State | Meaning | Reported as green? |
|---|---|---|
| `PASS` | Control executed and passed. | ✅ Yes |
| `FAIL` | Control executed and failed. | ❌ No |
| `UNVERIFIED` | Control did not execute (e.g., 0 files scanned, ImportError, runner misconfiguration). | ❌ NO — must NOT be reported as green. |

The critical rule (per SAAI directive 2026-10-06):

> **A green CI result MUST mean the control actually executed and inspected the boundary — not that the workflow step succeeded without running.**

PR #312 exposed this: `architecture-boundary-lint` reported `files_scanned=0` and the script exited 0 (no violations, because no files to violate). The workflow step succeeded. CI was "green." But the boundary was NOT inspected.

PR #314 fixed this by adding a "Verify scan coverage" step that fails the workflow if `files_scanned == 0`. The invariant is now enforced in CI.

### §3.3 The Linter Is the Second Reviewer

In a team-scale methodology, peer review catches defects before merge. In DGLab's solo model, there is no peer reviewer inside the project. The linter is the second reviewer.

This imposes constraints:
- The linter MUST actually execute (per §3.2 — UNVERIFIED is not PASS).
- The linter MUST check what it claims to check (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §5 — declared enforcement ≠ actual enforcement; if a check is claimed in the docstring, it must be implemented).
- The linter MUST have a negative regression test (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §6 — a structural checker that only tests the happy path isn't sufficient).

Where the linter does not check, there is no review. This is a known limitation, not a defect. The four-tool separation (§3.1) is the response: expand the linter's scope incrementally, with each expansion including a negative regression test.

---

## §4. Calibration — Formula, Not Vibes-Check

### §4.1 Why Calibration Is a Formula

A solo Tech Lead's subjective sense of "how is the project going?" is unreliable. Fatigue, recent wins, recent losses, and confirmation bias all distort the assessment. The SDLC's calibration is a formula — measurable inputs, deterministic output.

### §4.2 The Calibration Formula (Indicative, Not Binding)

The specific formula is defined in the original `SDLC-AGRD.md` §5 and is being refined in this rewrite. Indicative inputs:

- **Open FATAL findings count** (per [SHORTCOMINGS-REGISTER.md](../Verification/SHORTCOMINGS-REGISTER.md)) — must be 0.
- **Open HIGH findings count** — must all have dispositions (Deferred / Accepted / Closed).
- **Two-DAG drift count** — number of `DECLARED_ONLY` and `UNDECLARED_VERIFIED` edges without dispositions.
- **Build-order artifact staleness** — has the generated build order been regenerated since the last merge?
- **CI lint execution rate** — percentage of CI runs where the lint actually executed (not UNVERIFIED).

The output is a calibration verdict: `GREEN` (proceed), `YELLOW` (proceed with caution, explicit acknowledgment), `RED` (stop, cooldown extended).

### §4.3 The Integrity Gate Cross-Reference

The [Integrity Gate](../Verification/INTEGRITY-GATE.md) is the convergence criterion for the integrity phase. It defines 7 gate criteria, all of which must be met for the gate to pass. The gate is a binding governance gate — the roadmap may not proceed past it without the gate being PASSED.

The Integrity Gate was PASSED on 2026-10-05. This is a finite stopping condition — the integrity phase does not get reopened without a Tech-Lead directive.

---

## §5. The Blind-Spot Doctrine Binding Rules

The [Blind-Spot Doctrine](../CrossCutting/BLIND-SPOT-DOCTRINE.md) is binding governance, not advisory. Its six rules constrain how findings are closed and how audits are interpreted:

1. **An audit is a starting point, not a complete inventory.** The number of findings found is not the number of findings that exist. (Applies to: every audit, including the A3 audit and the SDLC rewrite itself.)
2. **A finding is closed only when its verification condition passes** — not when code changes, not when CI is green. (Applies to: §3.2 PASS/FAIL/UNVERIFIED; §4 calibration formula.)
3. **Every architectural document carries a blind-spot awareness note.** (Applies to: this document's banner; every SDLC and Core document in the rewrite.)
4. **Both humans and AI systems produce confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.** (Applies to: §3.3 the linter as second reviewer; §7 the solo invariant.)
5. **The Dunning-Kruger failure mode is about the blind spots — the shortcomings you DON'T know about, not just the ones you do.** (Applies to: the SDLC rewrite itself — the rewrite will introduce new blind spots.)
6. **CI green ≠ verification conditions met.** (Applies to: §3.2 PASS/FAIL/UNVERIFIED; §4.2 calibration; every PR's CI status.)

These rules are binding. Where the SDLC's procedural rules conflict with the doctrine, the doctrine wins.

---

## §6. Practical Roadmap Constraints (Deferred)

The Eligible(X) formula (§2) computes DAG-derived eligibility — which components CAN be built. It does NOT compute practical roadmap constraints — which components SHOULD be built, in what order, given:

- Team availability (in DGLab's case: solo Tech Lead + AI assistance).
- Release sequencing (per [ADR-018](../ADRs/ADR-018-centralized-per-tier-releases.md)).
- MVP scoping (per business pressure — currently LMS MVP).
- Risk tolerance (per [NUCLEAR-GRADE-DOCTRINE](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) — depth 5 components bound to doctrine).

Practical roadmap constraints are ASSESSED AFTER the Eligible(X) computation, not before. The DAG says what's possible; the roadmap says what's chosen.

This document does NOT specify the practical roadmap. The LMS MVP timeline (current business driver) is a Tech-Lead decision, not a DAG-derived output.

---

## §7. What This Document Does NOT Specify

- **Spiral Deepening model** (depth scale, laps, milestones, cooldowns) — see [SDLC-01](SDLC-01-Foundations.md).
- **Interface freeze rules** — see [SDLC-03](SDLC-03-InterfaceFreeze.md).
- **ADR format** — see [SDLC-03](SDLC-03-InterfaceFreeze.md) §2.
- **Nuclear-Grade Doctrine** (depth 5 binding) — see [NUCLEAR-GRADE-DOCTRINE.md](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md).
- **Blind-Spot Doctrine** (binding rules) — see [BLIND-SPOT-DOCTRINE.md](../CrossCutting/BLIND-SPOT-DOCTRINE.md).
- **Integrity Gate convergence criteria** — see [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md).
- **Practical roadmap** (LMS MVP, release sequencing) — Tech-Lead decision, not DAG-derived.

---

## §8. Provenance

This document is part of the SDLC rewrite (PR #316, 2026-10-07), per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time."

It replaces the governance content of `SDLC-AGRD.md` v3.4(3) (specifically the two-DAG model from ADR-021, the Eligible formula, the linter-as-reviewer principle, the calibration formula, and the cooldown-as-gate concept).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §5 binding rules + §3.3 linter as second reviewer)
- Nuclear-Grade Doctrine (§6 practical roadmap constraints — depth 5 binding)
- Integrity Gate (§4.3 convergence criterion cross-reference)
- Two-DAG Governance (§1 the entire model; §2 Eligible formula; §3 build order generation)
- FROZEN-CONTRACTS (§3.3 linter checks what it claims to check — declared enforcement must equal actual enforcement)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link points to a canonical document. Verified by lint's reference-existence check.
- Doctrine application: every doctrine listed in §8 is actually applied in the document body.
- PASS/FAIL/UNVERIFIED invariant: this document's references to "CI green" must be read with §3.2's three-state model in mind.

This document is a starting point. It is not a complete specification. The number of governance rules specified here is not the number of governance rules that exist.
