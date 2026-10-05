# Integrity Gate

**Status:** Binding governance gate (per tech-lead directive 2026-10-05)
**Purpose:** Establish a finite, auditable condition for declaring the current architecture/governance baseline internally consistent enough to resume roadmap work.
**Audience:** All stakeholders — humans and AIs

---

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This governance document's assumptions should be actively questioned. The gate criteria are candidates, not certainties. A passing gate does not mean the architecture has no unknown shortcomings — it means we have performed the required search using the currently defined discovery mechanisms. See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`.

<!-- End Blind-Spot Awareness -->

---

## Gate Criteria

The architecture may resume roadmap work only when ALL of the following are satisfied:

1. **FATAL findings:** zero `Open`; every FATAL has executable verification evidence
2. **HIGH findings:** every HIGH is either:
   - `Closed` (remediated and verified), or
   - `Accepted` with owner, rationale, and review/target milestone, or
   - `Deferred` with owner, rationale, and target milestone
3. **Governance consistency:** canonical artifacts and the register agree on current state
4. **Verification:** targeted verification confirms FATAL conditions; no broad audit required
5. **Tooling:** CI and architecture-lint pass on `main`
6. **Convergence:** targeted verification introduces zero new FATALs and zero new HIGHs without disposition
7. **Traceability:** every blocking finding has current status, owner/disposition, and verification condition

## What does NOT block the gate

- **MEDIUM/LOW findings in Open state** — tracked but don't block roadmap resumption
- **Deferred documentation drift** with explicit owner + rationale + target milestone
- **Stale documentation** that has been Deferred (not Contradictory — a Deferred finding is known-stale; a Contradictory finding is a governance defect)

## Disposition values

| Value | Meaning | Blocks gate? |
|---|---|---|
| **Closed** | Remediated and verified | No |
| **Accepted** | Known issue deliberately retained; explicit risk acceptance with owner + rationale + milestone | No |
| **Deferred** | Valid work, intentionally postponed to a named milestone with owner + rationale + milestone | No |
| **Invalid/Superseded** | No longer represents current architecture truth; preserved in audit history | No |
| **Open** | Identified, not yet addressed | **YES** (if FATAL or HIGH) |

## Gate state machine

```
                    ┌───────────┐
                    │   AUDIT   │
                    └─────┬─────┘
                          ↓
                    ┌───────────┐
                    │  REGISTER │
                    │syncronized│
                    └─────┬─────┘
                          ↓
                    ┌───────────┐
                    │ REMEDIATE │
                    └─────┬─────┘
                          ↓
                    ┌───────────┐
                    │  VERIFY   │
                    └─────┬─────┘
                          ↓
              ┌───────────────────┐
              │ Integrity criteria │
              │   satisfied?       │
              └────┬──────────┬────┘
                   │YES       │NO
                   ↓          ↓
            ┌──────────┐  ┌────────────────────┐
            │ RESUME   │  │ Targeted           │
            │ ROADMAP  │  │ remediation        │
            └──────────┘  │ (NOT broad audit)  │
                          └────────┬───────────┘
                                   ↓
                              ┌───────────┐
                              │  VERIFY   │
                              └─────┬─────┘
                                    ↓
                              (loop back to gate check)
```

**Key:** FAIL does NOT mean "audit everything again." It means "remediate the specific unmet gate conditions, then re-verify."

## FATAL lifecycle

```
discovered → reconciled → remediated → verified → closed

  Open    →  Fixed   →  Verified  →  Closed
                              ↑
                    verification condition
                    demonstrated (CI green +
                    specific evidence exists)
```

A merged PR = **Fixed** (not Verified, not Closed).
CI green + verification evidence = **Verified** (not Closed).
Re-audit confirms = **Closed**.

## Convergence signal

The meaningful metric is not "how many findings exist" but:

```
new FATALs per audit → should be zero
new HIGHs per audit without disposition → should be zero
finding closure rate → should be increasing
reopened findings → should be zero
```

When these trend toward zero, the architecture is converging.

## Provenance

Established 2026-10-05 per tech-lead directive: "Define the Integrity Gate now, but don't start another broad audit."

This gate is binding on all architecture integrity work. It supplements (does not replace) the Blind-Spot Doctrine, the shortcomings register, and ADR-021's governance model.

**Companion documents:**
- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — the governance framework
- `Architecture/Verification/SHORTCOMINGS-REGISTER.md` — the reconciled register
- `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` — the two-DAG governance model
