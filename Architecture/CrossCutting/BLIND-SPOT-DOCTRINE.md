# Blind-Spot Doctrine

**Status:** Binding governance doctrine (per tech-lead directive 2026-10-02)
**Scope:** All architecture integrity work — audits, reviews, remediation, and verification
**Audience:** All stakeholders — humans (tech lead, future engineers, contractors) and AIs (SAAI, Z.ai, Grok, any future AI reviewer)

---

## The Problem

> **The Dunning-Kruger effect is about the blind spots — the shortcomings you DON'T know about, not just the ones you do.**

The DGLab architecture integrity phase identified 47 shortcomings (4 FATAL, 25 HIGH, 14 MEDIUM, 4 LOW). That audit was thorough. **It was also incomplete.**

The 47 findings are the shortcomings we **know** about. The dangerous ones are the shortcomings we **don't** know about — the blind spots in the audit itself.

An audit conducted by a single AI, reading code and docs, has systematic blind spots. Different auditors with different lenses would find different things. Some issues only manifest at runtime. Some are architectural assumptions we've stopped questioning. Some are interactions between subsystems we've never verified.

**This doctrine establishes that acknowledging blind spots is not a weakness — it's the foundation of architectural integrity.**

---

## The Governance Principle

> **An audit is a starting point, not a complete inventory. The number of findings found is not the number of findings that exist.**

When we say "47 shortcomings," we mean "47 shortcomings we have found so far." We do NOT mean "47 is the total." There are more. We must actively try to find them.

This applies to:
- The shortcomings audit (47 findings → there are more)
- The Container Fiber state model (A0 spec → there may be deeper issues)
- The Core/Hub DAGs (verified edges → there may be edges we missed)
- The ADR-021 governance model (two-DAG + edge dimensions → there may be assumptions we haven't questioned)
- Every architectural claim we've ratified

---

## Blind-Spot Types

| Type | Why it's invisible | Example |
|---|---|---|
| **Runtime-only issues** | Static analysis (reading code) can't see what only manifests at runtime | The `Container::pulse()` bug was found because tests FAILED, not because someone read the code and noticed it |
| **Cross-tier drift** | Auditing each tier separately misses inconsistencies BETWEEN tiers | Hub DAG declares edges to Core — are those Core packages actually at the depth Hub expects? |
| **Architectural assumptions** | Things we assume are correct because "everyone knows" them | The SDLC depth scale, the Pulse model, the Ring direction — these are axioms, not verified claims |
| **Missing tests** | Checking if existing tests pass ≠ checking if tests that SHOULD exist DO exist | What Fiber lifecycle scenario has nobody thought to test? |
| **Auditor biases** | An AI (or human) trained on certain patterns will systematically miss things outside those patterns | The audit didn't flag the HUB-33 lint issue until the lint actually ran and failed |
| **Unknown unknowns** | Questions nobody has asked yet | What if the ADR-017 Fiber model itself has a flaw that only manifests under specific concurrency patterns? |
| **Interaction blind spots** | Each subsystem may be correct in isolation, but their interaction may be wrong | Container is correct + Kernel is correct + Fiber scheduling is correct → but their COMBINATION may produce contamination |
| **Temporal blind spots** | Issues that only manifest after time, load, or specific sequencing | A memory leak that only appears after 10,000 requests; a race that only triggers under specific Fiber interleaving |

---

## Approaches to Surface Blind Spots

No single approach catches everything. The union of multiple approaches is more comprehensive than any single one.

### 1. Adversarial Review (Red Team)

Instead of "what's wrong?", ask **"what would break this?"**

For each architectural claim, design a scenario that would violate it. Then test that scenario.

**Example:** ADR-021 says "No pulse-local value may cross a Fiber boundary." What would break this?
- Nested Fibers (child sees parent's pulse?)
- Fiber reuse (stale pulse from previous use?)
- Signal handling during Fiber suspension
- Exception propagation across Fiber boundaries
- `Fiber::resume()` from a different calling context
- Fiber suspension during `make()` resolution (cycle detection state)

Test EACH of these. The ones that fail are blind spots the audit missed.

### 2. Multiple Independent Auditors with Different Lenses

No single auditor catches everything. Launch multiple auditors, each with a DIFFERENT lens:

- **Code auditor**: reads every PHP file, checks for type safety, resource leaks, exception handling, concurrency patterns
- **Architecture auditor**: reads every ADR + blueprint + DAG, checks for internal consistency, missing contracts, unstated assumptions
- **Runtime auditor**: reads every test file, identifies what's NOT tested, proposes tests for gaps
- **Adversarial auditor**: tries to find scenarios that break architectural claims

Each will find different things. The **union** is more comprehensive than any single pass. The **intersection** (things all auditors agree on) is high-confidence.

### 3. Property-Based Verification

For each contract claim, define a **property** that must hold, then generate random scenarios to test it:

- "For any two Fibers A and B, and any service ID X, `pulse(A, X, v1)` must not affect `make(B, X)`"
- "For any Fiber that has terminated, all its pulse-scoped state must be unreachable"
- "For any nested Fiber, the child must not see the parent's pulse bindings"
- "For any sequence of pulse() + make() calls, the last pulse() value is authoritative"

Properties are stronger than examples — they must hold for ALL inputs, not just the ones we thought to test.

### 4. Cross-Layer Consistency Verification

For EVERY claim in one layer, verify it in ALL other layers:

- ADR-021 says HUB-32 exists → does INDEX agree? does a blueprint exist? does the lint accept it? does the DAG reference it?
- CORE-02 says pulse() is Fiber-scoped → does ADR-017 agree? does the implementation match? do the tests verify it?
- INDEX says 102 blueprints → do the files actually exist? does the lint expect them? does the baseline count match?

Every layer should agree. Disagreement = a blind spot.

### 5. The "What Haven't We Asked?" Exercise

The hardest and most important. Explicitly ask:
- What architectural assumptions have we never questioned?
- What scenarios have we never tested?
- What interactions between subsystems have we never verified?
- What would a brand-new engineer be confused by that we've stopped noticing?
- What claims have we ratified that we haven't actually verified?

---

## Binding Rules

1. **No audit is declared complete.** An audit produces a candidate inventory, not a final count. The register is always "open" — new findings can be added at any time.

2. **No finding is closed without verification.** Code changes alone don't close a finding. CI green doesn't close a finding. The stated verification condition must pass — and then a re-audit must confirm it.

3. **Every fix must be tested against blind spots.** Before implementing a fix, ask: "What scenario would prove this fix is incomplete?" Then test that scenario.

4. **Multiple lenses are required for FATAL findings.** A single-auditor pass is insufficient for FATAL-severity issues. At least two independent reviews (different auditors, different lenses) must agree before a FATAL is remediated.

5. **"We don't know what we don't know" is not an excuse.** It's a mandate to actively try to find the unknown unknowns — through adversarial review, property-based testing, cross-layer verification, and the "what haven't we asked?" exercise.

6. **The integrity phase does not end when the register reaches zero.** The integrity phase ends when the tech lead is satisfied that blind spots have been actively surfaced — not when all known findings are closed.

---

## Provenance

Established 2026-10-02 per tech-lead directive: "The Dunning-Kruger effect is about the blind spots — the shortcomings you DON'T know about, not just the ones you do."

This doctrine is binding on all architecture integrity work. It supplements (does not replace) ADR-021's governance model, the shortcomings register's closure rules, and the SDLC's admission process.

**Companion documents:**
- `Architecture/Verification/SHORTCOMINGS-REGISTER.md` — the 47-finding register (always open)
- `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` — the A0 state model (a candidate spec, not a verified contract)
- `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` — the two-DAG governance model (ratified, but its assumptions must be actively questioned)
