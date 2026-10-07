# SDLC-03: Interface Freeze & ADR-Gated Changes

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document specifies the rules for interface freezing and ADR-gated changes. The rules themselves are a starting point, not a complete specification. Every freeze rule, every ADR-gated change trigger, every Fidelity Bar requirement is open to challenge when implementation reality contradicts it. The number of rules specified here is not the number of rules that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (replaces the interface-freeze, ADR, and authoring content of `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3), per Tech-Lead directive 2026-10-06: "rewrite with proper details the entire SDLC").
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) (ratified AGRD as canonical SDLC) + [ADR-021](../ADRs/ADR-021-tier-stratified-build-order.md) (governance model).
**Related:** [SDLC-01](SDLC-01-Foundations.md) (Foundations & Spiral Deepening), [SDLC-02](SDLC-02-Governance.md) (Two-DAG Governance & Eligibility), [FROZEN-CONTRACTS](../FROZEN-CONTRACTS.md), [AUTHORING_GUIDE](../AUTHORING_GUIDE.md), [OPEN-DECISIONS](../OPEN-DECISIONS.md), [BLIND-SPOT-DOCTRINE](../CrossCutting/BLIND-SPOT-DOCTRINE.md).

---

## §1. Interface Freeze — When Contracts Become Binding

### §1.1 The Freeze Rule

A blueprint's public contract (interface, class signature, method signatures, exception types, behavior contracts) **freezes the first time the blueprint is implemented at any depth**. Once frozen:

- The contract MAY NOT change without an ADR (per §3).
- The implementation MAY change without an ADR (refactors, performance improvements, bug fixes) as long as the contract is preserved.
- The contract's frozen state is recorded in [`FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md).

### §1.2 Why Freezing Matters

In a team-scale methodology, contract changes propagate through code review — peers see the change, push back if it breaks their code, the change is negotiated. In DGLab's solo model, there is no peer to push back. A contract change can silently break consumers that the Tech Lead hasn't remembered.

The freeze rule makes contract changes EXPLICIT. They require an ADR (per §3). The ADR forces:
- Documentation of what's changing and why.
- Identification of consumers (via the Verified DAG).
- A SemVer impact assessment (per §4).
- A migration path (per §5).

### §1.3 What Is a "Contract"?

A contract is the publicly-visible surface of a component:

- **Interface signatures** — method names, parameter types, return types, exceptions thrown.
- **Class signatures** — for non-interface classes, the public method signatures + constructor signature.
- **Behavior contracts** — documented invariants the implementation must preserve (e.g., "this method is idempotent", "this method never returns null").
- **Exception types** — the typed exceptions the component throws (per [NUCLEAR-GRADE-DOCTRINE](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) §2 — the 5-class error taxonomy).
- **Configuration schema** — the configuration keys the component reads, their types, and their defaults.

What is NOT a contract (and may change without an ADR):

- **Private methods** — implementation detail, not part of the public surface.
- **Protected methods** — only contract if the class is documented as subclassable.
- **Internal data structures** — how the implementation stores state internally.
- **Performance characteristics** — unless explicitly documented as a contract (e.g., "this method is O(1)").

### §1.4 The FROZEN-CONTRACTS Registry

The [`FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md) registry lists every frozen contract in the project. Each entry records:

- The component (CORE-NN, HUB-NN, etc.).
- The frozen interface/class.
- The depth at which it froze (depth 1 = stub freeze, depth 2 = happy-path freeze, etc.).
- The PR/commit that froze it.
- The SemVer impact of changes to it.

A contract NOT in the registry is NOT frozen — it may change freely. Adding a contract to the registry is itself an ADR-gated event (per §3).

---

## §2. ADR Format

### §2.1 The ADR Structure

Every Architecture Decision Record (ADR) follows this structure:

```markdown
# ADR-NNN: <Decision Title>

> **This project is developed by both humans and AI systems.** [Blind-Spot banner]

> **⚠️ Blind-Spot Awareness:** [Context-specific blind-spot note]

**Status:** Accepted | Proposed | Superseded by ADR-NNN | Deprecated
**Date:** YYYY-MM-DD
**Author:** <name>
**Supersedes:** ADR-NNN (if applicable)
**Superseded by:** ADR-NNN (if applicable)
**Related:** ADR-NNN, <other-docs>

---

## Context

[What is the issue? What are the forces? What constraints apply?]

## Decision

[What is the decision? What was chosen, and what was rejected?]

## Status

[Current status. "Accepted" means ratified; "Proposed" means awaiting ratification; "Superseded" means replaced by a later ADR; "Deprecated" means withdrawn.]

## Consequences

[What follows from this decision? Positive, negative, neutral. What becomes easier? What becomes harder? What risks are accepted?]
```

### §2.2 ADR Numbering

ADRs are numbered sequentially: ADR-001, ADR-002, ..., ADR-021 (current count). Numbers are NEVER reused, even if an ADR is deprecated or superseded.

The canonical ADR set lives in [`Architecture/ADRs/`](../ADRs/). The current canonical range is ADR-001..021 (per the architecture-lint's structural expectations, updated in PR #314).

### §2.3 ADR Authority

An ADR is **ratified** when its Status field is `Accepted`. A `Proposed` ADR is not yet binding — it documents a pending decision. A `Superseded` ADR is historical — its decision has been replaced, but the ADR remains for provenance.

Where an ADR and a per-package blueprint disagree, the more recent ADR wins. Where an ADR and a doctrine (Blind-Spot, Nuclear-Grade) disagree, the doctrine wins (per the doctrine's own §0).

---

## §3. ADR-Gated Changes — What Requires an ADR

### §3.1 Changes That REQUIRE an ADR

The following changes require an ADR before they may be merged:

1. **Frozen contract changes** (per §1.1) — adding, removing, or modifying a frozen interface, class signature, or behavior contract.
2. **Tier-boundary changes** — adding a new tier, removing a tier, or changing the tier of an existing component.
3. **Build-order changes** — changing the Eligible(X) formula, the wave computation algorithm, or the four-tool lint separation.
4. **Methodology changes** — changing the depth scale, the lap structure, the cooldown rules, or the versioning scheme.
5. **Doctrine changes** — adding, removing, or modifying a binding doctrine (Blind-Spot, Nuclear-Grade, Integrity Gate).
6. **MUWV flips** — flipping the version's MUWV segment (per [SDLC-01](SDLC-01-Foundations.md) §6.1).
7. **Milestone transitions** — declaring a new Milestone, or transitioning to a new Milestone.
8. **Component renumbering** — changing the number of a CORE-NN, HUB-NN, etc. (extremely disruptive; usually avoided).

### §3.2 Changes That Do NOT Require an ADR

The following changes do NOT require an ADR (they may be merged with a normal PR):

1. **Implementation refactors** — changing private methods, internal data structures, or performance characteristics (as long as the frozen contract is preserved).
2. **Bug fixes** — fixing defects that violate a documented contract (the fix restores the contract; it doesn't change it).
3. **Documentation updates** — improving blueprint clarity, adding examples, fixing typos.
4. **Depth deepening** — moving a component from depth N to depth N+1 (per [SDLC-01](SDLC-01-Foundations.md) §2.1 — this is the normal Spiral Deepening motion).
5. **Test additions** — adding tests for existing behavior.
6. **Generated artifact regeneration** — regenerating build orders, DAGs, etc. (per [SDLC-02](SDLC-02-Governance.md) §2.2 — these are deterministic outputs).

### §3.3 The ADR Process

When a change requires an ADR:

1. **Open an OD** (per §4) documenting the decision to be made.
2. **Discuss the OD** — the Tech Lead (with AI assistance) analyzes options.
3. **Author the ADR** — the ADR is written with Context, Decision, Status (Proposed), Consequences.
4. **Ratify the ADR** — Status changes to Accepted. The OD is resolved.
5. **Implement the change** — the ADR's decision is implemented in a PR.
6. **Update FROZEN-CONTRACTS** (if the change froze/unfroze a contract).
7. **Update INDEX** (if the change added/removed/renumbered a component).

The ADR is authored BEFORE the implementation PR, not after. The ADR documents the decision; the PR implements it.

---

## §4. Open Decisions (OD) Lifecycle

### §4.1 What an OD Is

An Open Decision (OD) is a recorded question that has not yet been decided. ODs live in [`OPEN-DECISIONS.md`](../OPEN-DECISIONS.md). Every OD has:

- An OD number (OD-NN, sequential).
- A statement of the question.
- Options considered.
- A disposition: Resolved (became an ADR), Deferred (with rationale + revisit condition), Closed (with verification evidence).

### §4.2 OD Lifecycle

```
  ┌─────────────────┐
  │  Question asked │
  └────────┬────────┘
           │
           ▼
  ┌─────────────────┐
  │  OD opened      │  (added to OPEN-DECISIONS.md with OD-NN)
  └────────┬────────┘
           │
           ▼
  ┌─────────────────┐
  │  Options listed │
  └────────┬────────┘
           │
           ▼
  ┌─────────────────┐
  │  Discussion     │  (Tech Lead + AI analysis; recorded in OD)
  └────────┬────────┘
           │
           ▼
   ┌───────────────┐
   │ Disposition? │
   └───┬───────┬───┘
       │       │
       ▼       ▼
  Resolved  Deferred
       │       │
       ▼       ▼
  ADR-NNN   revisit-when: <condition>
  opened    (recorded in OD)
       │
       ▼
  ADR-NNN
  Accepted
       │
       ▼
  OD marked
  "Resolved by
   ADR-NNN"
```

### §4.3 OD Authority

An OD is NOT a decision — it is a recorded question. No implementation may proceed based on an OD alone; the OD must be Resolved (via an ADR) or Deferred (with explicit revisit condition) before the implementation may proceed.

A `Deferred` OD has a `revisit-when` condition. The condition is a verification event (e.g., "revisit when HUB-02 ships at depth 2" or "revisit when ADR-021 Amendment 3 is proposed"). The OD is not forgotten — it is parked until the condition fires.

### §4.4 Current Open Decisions

The current OD ledger is at [`OPEN-DECISIONS.md`](../OPEN-DECISIONS.md). Notable open ODs (as of this rewrite):

- **OD-12 (APIfy):** Declarative API exposure for Hub capabilities. Recorded; implementation deferred.

---

## §5. The Fidelity Bar

### §5.1 What the Fidelity Bar Is

The Fidelity Bar is the minimum content standard for an approved blueprint. A blueprint that does not meet the Fidelity Bar is NOT approved — it cannot be implemented, and its contract cannot freeze.

Per [`GLOSSARY.md`](../CrossCutting/GLOSSARY.md), the Fidelity Bar requires:

1. **PHP 8.4 interface contracts** — the interface(s) the component exposes, as compilable PHP code.
2. **A compilable class** — at least one class that implements the interface, with `declare(strict_types=1)`.
3. **SQL DDL where state is persisted** — if the component persists state, the schema is specified.
4. **A Mermaid sequence diagram** — visualizing the primary interaction.
5. **Dependency lists cross-referenced to INDEX §2** — every dependency points to a defined ID.
6. **Benchmark methodology** — how the component's performance is measured.
7. **CI criteria** — what tests must pass for the component to ship.
8. **Security invariants** — what the component guarantees about security (per [NUCLEAR-GRADE-DOCTRINE](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)).
9. **Migration notes** — how to migrate from a previous version (if applicable).
10. **SemVer impact** — what kind of version bump changes to this component require.

### §5.2 Declared Enforcement ≠ Actual Enforcement

The Fidelity Bar is a declared standard. Whether a blueprint actually meets it is a verification question, not a declaration question. The architecture-lint's structural completeness check (per [SDLC-02](SDLC-02-Governance.md) §3) verifies that expected blueprint FILES exist — but it does NOT verify that the files MEET the Fidelity Bar.

This is a known gap. The planned `architecture-state-validator` (per [SDLC-02](SDLC-02-Governance.md) §3.1) will eventually verify Fidelity Bar compliance. Until then, Fidelity Bar compliance is a review-time check, not a CI check.

Per SAAI directive 2026-10-06: "declared enforcement ≠ actual enforcement." The Fidelity Bar is declared. The actual enforcement is incomplete. This is documented, not hidden.

### §5.3 Negative Regression Test Requirement

Per SAAI directive: "A structural checker that only tests the happy path isn't sufficient." The architecture-lint's `--self-test` flag (added in PR #314) provides a negative regression test for the structural completeness check.

For the Fidelity Bar, an equivalent negative regression test would:
1. Take a blueprint that fails the Fidelity Bar (e.g., missing SQL DDL).
2. Run the (future) Fidelity Bar checker.
3. Verify the checker reports the failure.

This is a planned future expansion, not a current capability. The gap is documented in [`SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md).

---

## §6. AUTHORING_GUIDE — Blueprint Structure

The [`AUTHORING_GUIDE.md`](../AUTHORING_GUIDE.md) specifies the structure every blueprint must follow. Indicative structure:

```markdown
# <CORE-NN|HUB-NN|...>: <Component Name>

> [Blind-Spot banner]

> [Blind-Spot Awareness note]

**Status:** <Canonical depth | Stub | Shipped at depth N>
**Tier:** Core | Hub | Application | Runtime | Deploy
**Namespace:** <PHP namespace>
**PHP:** 8.4+

## Component Name
<One-line description>

## Tier
<Which tier this component belongs to>

## Resolves
<If this blueprint supersedes a prior blueprint or resolves an OD, list it here>

## Architectural Design
### Components
<Sub-components and their roles>

### Sequence Diagram
<Mermaid sequence diagram>

## Interface Contracts
<PHP 8.4 interface definitions — compilable>

## Implementation
<Compilable class(es) that implement the interfaces>

## SQL DDL
<If state is persisted, the schema>

## Dependencies
### Direct Dependencies
<Cross-referenced to INDEX §2>

### Transitive Dependencies
<Derived from Direct Dependencies>

## Benchmark & Verification Methodology
<How performance is measured>

## CI Criteria
<What tests must pass for ship>

## Security Invariants
<Per Nuclear-Grade Doctrine>

## Migration Notes
<If applicable>

## SemVer Impact
<Major | Minor | Patch, with rationale>

## Build Status
<Shipped at depth N | Stub | Not started>
```

The AUTHORING_GUIDE is the binding template. Blueprints that deviate from this structure must document why.

---

## §7. Explicit Unknowns — Don't Silently Resolve

### §7.1 The Rule

When the Tech Lead (or an AI assistant) encounters a question that doesn't have an answer, the question is recorded as an OD (per §4). It is NOT silently resolved by:

- Picking an option and proceeding (without recording the decision).
- Inventing an answer that "seems right" (without verifying).
- Deferring the question indefinitely (without a `revisit-when` condition).

### §7.2 Why This Rule Exists

In a team-scale methodology, unresolved questions get caught in code review — a peer asks "why did you do it this way?" and the question becomes visible. In DGLab's solo model, there is no peer to ask. A silently-resolved question is invisible — until it surfaces as a defect.

The OD ledger makes questions visible. Even if the Tech Lead doesn't know the answer, recording the QUESTION is governance. The question can be revisited, discussed with AI assistance, or resolved by future events.

### §7.3 The Blind-Spot Doctrine Cross-Reference

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5):

> "The Dunning-Kruger failure mode is about the blind spots — the shortcomings you DON'T know about, not just the ones you do."

Silently resolving questions CREATES blind spots. Recording them as ODs makes them visible — which is the first step to addressing them.

---

## §8. Blind-Spot Doctrine Binding Rules

The [Blind-Spot Doctrine](../CrossCutting/BLIND-SPOT-DOCTRINE.md) is binding on this document. Its six rules (per [SDLC-02](SDLC-02-Governance.md) §5) apply specifically:

1. **Audit is starting point, not inventory.** Applies to: the Fidelity Bar (§5) — meeting the Bar is not a guarantee of completeness.
2. **Verification condition passes.** Applies to: §1.4 FROZEN-CONTRACTS registry — a contract is frozen only when its verification condition (implementation at any depth + registry entry) passes.
3. **Every doc carries blind-spot note.** Applies to: this document's banner; every blueprint per AUTHORING_GUIDE (§6).
4. **AI work is confident-but-may-be-wrong.** Applies to: §7 explicit unknowns — AI assistants may propose answers; the Tech Lead must verify, not accept.
5. **Dunning-Kruger blind spots.** Applies to: §7.3 cross-reference.
6. **CI green ≠ verification.** Applies to: §5.2 declared vs actual enforcement; §5.3 negative regression test requirement.

---

## §9. What This Document Does NOT Specify

- **Spiral Deepening model** — see [SDLC-01](SDLC-01-Foundations.md).
- **Two-DAG governance / Eligible formula** — see [SDLC-02](SDLC-02-Governance.md).
- **Nuclear-Grade Doctrine** (depth 5 binding, 5-class error taxonomy, hard resource ceilings) — see [NUCLEAR-GRADE-DOCTRINE.md](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md).
- **Integrity Gate convergence criteria** — see [INTEGRITY-GATE.md](../Verification/INTEGRITY-GATE.md).
- **Shortcomings Register** (the findings ledger) — see [SHORTCOMINGS-REGISTER.md](../Verification/SHORTCOMINGS-REGISTER.md).
- **Specific ADRs** — see [`Architecture/ADRs/`](../ADRs/).
- **Open Decisions** — see [`OPEN-DECISIONS.md`](../OPEN-DECISIONS.md).

---

## §10. Provenance

This document is part of the SDLC rewrite (PR #316, 2026-10-07), per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time."

It replaces the interface-freeze, ADR, authoring, and explicit-unknowns content of `SDLC-AGRD.md` v3.4(3).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §7 explicit unknowns + §8 binding rules cross-reference)
- Nuclear-Grade Doctrine (§1.3 exception types per 5-class error taxonomy; §5.1 Fidelity Bar security invariants requirement)
- Integrity Gate (§5.3 negative regression test requirement; §5.2 declared vs actual enforcement gap documentation)
- Two-DAG Governance (§1.4 FROZEN-CONTRACTS references Verified DAG; §3.3 ADR process references INDEX)
- FROZEN-CONTRACTS (§1 the entire interface-freeze concept; §3.1 frozen contract changes require ADR)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link points to a canonical document.
- Doctrine application: every doctrine listed in §10 is actually applied in the document body.
- Fidelity Bar compliance: this document is a methodology document, not a component blueprint. The Fidelity Bar applies to component blueprints (per §5), not to SDLC documents. This document follows the AUTHORING_GUIDE structure (§6) where applicable, but deviates where the structure doesn't fit methodology content.

This document is a starting point. It is not a complete specification. The number of rules specified here is not the number of rules that exist.
