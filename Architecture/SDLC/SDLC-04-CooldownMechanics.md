# SDLC-04: Cooldown Mechanics

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document specifies the mechanics of cooldowns — the governance phases between laps. The mechanics themselves are a starting point, not a complete specification. Every gate, every rest-check criterion, every reconciliation protocol is open to challenge when reality contradicts it. The number of cooldown mechanics specified here is not the number of cooldown mechanics that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (Batch 2 of the SDLC rewrite, per Tech-Lead directive 2026-10-06).
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) + [SDLC-01](SDLC-01-Foundations.md) §5 (cooldowns reference).
**Related:** [SDLC-01](SDLC-01-Foundations.md) (Foundations), [SDLC-02](SDLC-02-Governance.md) (Governance), [SDLC-03](SDLC-03-InterfaceFreeze.md) (Interface Freeze), [BLIND-SPOT-DOCTRINE](../CrossCutting/BLIND-SPOT-DOCTRINE.md), [INTEGRITY-GATE](../Verification/INTEGRITY-GATE.md).

---

## §1. Why Cooldowns Exist

A cooldown is NOT a break. It is a required governance phase between laps where:

1. **Worklog reconciliation** — every agent (human or AI) that worked on the lap appends to `worklog.md`. The cooldown verifies the worklog is complete and consistent.
2. **Open Decisions triage** — ODs are reviewed. Some are resolved (becoming ADRs per [SDLC-03](SDLC-03-InterfaceFreeze.md) §3), some are deferred (with explicit `revisit-when` condition per [SDLC-03](SDLC-03-InterfaceFreeze.md) §4), some are closed (with verification evidence).
3. **Refactor backlog review** — technical debt accumulated during the lap is reviewed. Some is scheduled for the next lap; some is paid down immediately; some is accepted with explicit rationale.
4. **Rest check** — the Tech Lead verifies they are not running on fumes. Solo development under fatigue produces confident-but-wrong work (the exact failure mode the Blind-Spot Doctrine warns about — per [SDLC-02](SDLC-02-Governance.md) §5 rule #4).

Skipping any of these gates is NOT an option. A lap is not complete until the cooldown's four gates pass.

---

## §2. Variable Duration (v3.5)

### §2.1 Pre-v3.5: Fixed 2 Weeks

Pre-v3.5, cooldowns were a fixed 2 weeks. This was simple but problematic:

- **Too short for hard laps** — a lap that introduced a major refactor or surfaced significant findings needs more than 2 weeks of reconciliation.
- **Too long for easy laps** — a lap that added a single package at depth 2 with no findings doesn't need 2 weeks of cooldown.
- **Rest-blind** — the fixed duration didn't check whether the Tech Lead was actually rested. A 2-week cooldown where the Tech Lead was sick or fatigued the entire time didn't actually rest them.

### §2.2 v3.5: Variable Duration, Gated on Rest

v3.5 (ratified 2026-10-06 per the SDLC rewrite) makes duration variable:

- The cooldown lasts until the four gates above (worklog, OD, refactor, rest) all pass.
- The minimum is 1 week; the maximum is 4 weeks.
- If the rest check fails at 4 weeks, the cooldown extends with explicit Tech-Lead acknowledgment that the project is in low-throughput mode.

### §2.3 Why v3.5 Wasn't Acknowledged in ADR-014

Per finding S-084 (in [`SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md)), ADR-014 acknowledges v3.5 as a future state but doesn't record that v3.5 has shipped. This is a documentation gap, not a methodology gap — the methodology (this document) is the canonical expression of v3.5. The ADR amendment to formally acknowledge v3.5 ratification is a pending targeted correction (per the rewrite track).

This is a documented example of "declared enforcement ≠ actual enforcement" (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §5.2). The ADR declared v3.5 as future; the methodology actually shipped v3.5. The gap is recorded, not hidden.

---

## §3. The Four Gates

### §3.1 Worklog Reconciliation Gate

**What it checks:** Every agent that worked on the lap has appended to `worklog.md` per the worklog protocol.

**Protocol:**

```
  ┌──────────────────────────────────────────────┐
  │  1. Identify all agents that worked on the   │
  │     lap (from PR commits, co-author lines,  │
  │     and the worklog itself)                 │
  ├──────────────────────────────────────────────┤
  │  2. For each agent, verify their Task entry  │
  │     in worklog.md contains:                  │
  │     - Task ID (sequential)                   │
  │     - Agent name (main or subagent name)     │
  │     - Task description                       │
  │     - Work Log (concrete steps taken)        │
  │     - Stage Summary (key results / decisions)│
  ├──────────────────────────────────────────────┤
  │  3. Verify no two agents have the same Task  │
  │     ID (uniqueness check)                    │
  ├──────────────────────────────────────────────┤
  │  4. Verify the worklog's last entry's Task   │
  │     ID is the highest (ordering check)       │
  ├──────────────────────────────────────────────┤
  │  5. Verify the worklog has no overwrites     │
  │     (append-only check — entries are never   │
  │     edited, only appended)                   │
  └──────────────────────────────────────────────┘
```

**Failure mode:** If any check fails, the cooldown extends until the worklog is reconciled. The reconciliation may require contacting the agent (if external) or reconstructing the entry from PR commits (if the agent is unavailable).

### §3.2 Open Decisions Triage Gate

**What it checks:** Every OD opened during the lap has a disposition (Resolved / Deferred / Closed).

**Protocol:**

```
  ┌──────────────────────────────────────────────┐
  │  1. List all ODs in OPEN-DECISIONS.md with    │
  │     status "Open" opened during the lap       │
  ├──────────────────────────────────────────────┤
  │  2. For each Open OD:                         │
  │     - Resolved? → verify an ADR was opened    │
  │       and Accepted, and the OD references it  │
  │     - Deferred? → verify a revisit-when       │
  │       condition is recorded (per SDLC-03 §4.2)│
  │     - Closed? → verify verification evidence  │
  │       is recorded (what was checked, when,    │
  │       by whom)                                │
  ├──────────────────────────────────────────────┤
  │  3. ODs with NO disposition are blocking —   │
  │     the cooldown extends until dispositioned  │
  └──────────────────────────────────────────────┘
```

**Failure mode:** ODs without disposition are blocking. The Tech Lead must disposition each one before the cooldown can end.

### §3.3 Refactor Backlog Review Gate

**What it checks:** Technical debt accumulated during the lap is reviewed and dispositioned.

**Protocol:**

```
  ┌──────────────────────────────────────────────┐
  │  1. List all "TODO", "FIXME", "HACK",        │
  │     "XXX" comments added during the lap     │
  ├──────────────────────────────────────────────┤
  │  2. List all suboptimal patterns introduced   │
  │     (identified via code review or AI         │
  │     analysis)                                 │
  ├──────────────────────────────────────────────┤
  │  3. For each item:                            │
  │     - Pay down now? → schedule for the next   │
  │       lap's first PR                         │
  │     - Defer? → record in refactor backlog     │
  │       with revisit-when condition             │
  │     - Accept? → record with explicit          │
  │       rationale (why this debt is OK to keep) │
  └──────────────────────────────────────────────┘
```

**Failure mode:** Items without disposition are blocking. The cooldown extends until each is dispositioned.

### §3.4 Rest Check Gate

**What it checks:** The Tech Lead is not running on fumes.

**Protocol:**

```
  ┌──────────────────────────────────────────────┐
  │  1. Self-assessment: "Am I able to make      │
  │     confident decisions without             │
  │     second-guessing myself?"                │
  │     - YES → rest check passes               │
  │     - NO → rest check fails, cooldown       │
  │       extends (per §2.2)                    │
  ├──────────────────────────────────────────────┤
  │  2. If rest check fails at 4 weeks:          │
  │     - Explicit Tech-Lead acknowledgment that │
  │       the project is in low-throughput mode │
  │     - Consider whether the lap's scope was  │
  │       too ambitious (post-mortem)           │
  │     - Consider external assistance (peer    │
  │       review, AI-assisted analysis)         │
  └──────────────────────────────────────────────┘
```

**Failure mode:** Rest check failure is NOT a defect — it's a signal. The project extends the cooldown rather than proceeding with confident-but-wrong work. Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #4): "Both humans and AI systems produce confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning." Fatigue amplifies this failure mode.

---

## §4. Mini-Cooldowns (OD-11)

### §4.1 What a Mini-Cooldown Is

A mini-cooldown is a ~1-day checkpoint between Steps within a lap (not between laps). It provides:

- **Worklog update** — the active task appends its work log.
- **OD triage** — any ODs opened during the Step are dispositioned.
- **Refactor check** — any debt incurred is noted (not necessarily paid down).
- **CI verification** — all CI for the Step's PRs is verified green, with explicit check that the lint actually executed (per [SDLC-02](SDLC-02-Governance.md) §3.2 — UNVERIFIED is not PASS).

### §4.2 What a Mini-Cooldown Is NOT

A mini-cooldown is NOT:

- **A rest period** — too short for meaningful rest. The rest check gate (§3.4) is for between-lap cooldowns only.
- **A scope-reduction opportunity** — mini-cooldowns don't change the lap's scope. They checkpoint progress, not redefine it.
- **A merge gate** — mini-cooldowns don't block merges. They are advisory checkpoints between Steps.

### §4.3 OD-11 Origin

OD-11 was the Open Decision that proposed mini-cooldowns as a calibration mechanism for between-Step checkpoints. It was Resolved (became part of the SDLC v3.x lineage) and is now part of the canonical methodology.

---

## §5. The CI Verification Step (Where the Blind-Spot Doctrine Bites Hardest)

### §5.1 The Rule

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #6):

> "CI green ≠ verification conditions met."

The cooldown's CI verification step is where this rule applies hardest. It is NOT sufficient to check that the workflow step exited 0. The verification step must check:

1. **The lint actually executed** — `files_scanned > 0` (per the [SDLC-02](SDLC-02-Governance.md) §3.2 PASS/FAIL/UNVERIFIED invariant).
2. **The specific verification conditions for the Step's claims passed** — not just "tests passed" but "the SPECIFIC tests that verify the SPECIFIC claims passed."
3. **The self-test ran** (per [SDLC-02](SDLC-02-Governance.md) §3.3 — the negative regression test that verifies the lint correctly detects missing files).

### §5.2 The Three Recurrences

Three recurrences of the "CI green ≠ verification" pattern during the integrity phase confirmed the rule's necessity:

- **S-054** — test file was missing; CI was green because the test wasn't being run.
- **S-033** — DAG count was stale; CI was green because no test verified the count.
- **S-077** — register was not updated; CI was green because no test verified the register's freshness.

Each was a case where CI reported success but the specific verification condition hadn't been checked. The cooldown's CI verification step is the response — it requires explicit verification, not just CI green.

### §5.3 The Integrity Gate Cross-Reference

The [Integrity Gate](../Verification/INTEGRITY-GATE.md) (PASSED 2026-10-05) defines 7 convergence criteria, all of which must be met for the gate to pass. The gate is a finite stopping condition for the integrity phase — it does not get reopened without a Tech-Lead directive.

The cooldown's CI verification step is the BETWEEN-LAP analog of the Integrity Gate's convergence criteria. Both enforce the same invariant: verification conditions must actually be checked, not just CI green.

---

## §6. Worklog Protocol — Append-Only, Multi-Agent

### §6.1 The Worklog File

The worklog lives at `/worklog.md` (repository root). It is the multi-agent execution log — every agent (human or AI) that works on the project appends to it.

### §6.2 The Append-Only Rule

The worklog is append-only. Entries are NEVER:

- **Edited** — once written, an entry stays as-is. Corrections go in a new entry that references the old one.
- **Deleted** — entries are never removed. If an entry was wrong, the correction is a new entry.
- **Reordered** — entries stay in chronological order. The Task ID is sequential and monotonic.

### §6.3 The Entry Format

Every worklog entry follows this format:

```markdown
---
Task ID: <sequential number>
Agent: <agent name (main, or subagent name)>
Task: <one-line description of what was asked>

Work Log:
- <concrete step 1>
- <concrete step 2>
- ...

Stage Summary:
- <key results>
- <important decisions>
- <produced artifacts>
```

The `---` separator at the top is required — it delimits entries visually and supports tooling.

### §6.4 The Multi-Agent Protocol

When multiple agents work on the same task (e.g., main agent + subagent):

1. The main agent assigns a Task ID that reflects the global order and possible parallelism (e.g., `1`, `2-a`, `2-b`, `3` where `2-a` and `2-b` are parallel tasks at step 2).
2. Each agent appends their own entry to the worklog, using their assigned Task ID.
3. The main agent's entry includes the coordination decisions; subagent entries include the subagent's specific work.
4. The cooldown's worklog reconciliation gate (§3.1) verifies all entries are present and consistent.

### §6.5 Why This Matters

In a team-scale methodology, peer review catches coordination failures — a peer asks "did you coordinate with X?" and the gap becomes visible. In DGLab's solo + AI model, there is no peer. The worklog is the coordination mechanism. If the worklog is incomplete, coordination failures are invisible — until they surface as defects.

The append-only rule ensures the worklog is a reliable historical record. If entries could be edited, the record would be unreliable — later readers couldn't trust what they see.

---

## §7. Cooldown Failure Modes — What Can Go Wrong

### §7.1 Skipping the Cooldown

If the Tech Lead skips the cooldown (proceeds to the next lap without the four gates passing), the consequences are:

- **Worklog gaps** — work from the previous lap may not be recorded. Future readers can't reconstruct what happened.
- **Unresolved ODs** — questions from the previous lap may be silently resolved (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §7 — the explicit unknowns rule). This creates blind spots.
- **Unpaid refactor debt** — technical debt accumulates. Eventually it becomes blocking.
- **Fatigue-driven defects** — confident-but-wrong work ships. The Blind-Spot Doctrine's failure mode amplifies under fatigue.

### §7.2 Over-Extending the Cooldown

If the cooldown extends beyond 4 weeks (per §2.2 — the maximum), the project enters low-throughput mode. This is NOT a defect — it's a signal:

- The lap's scope was too ambitious. Post-mortem: what could have been split into smaller laps?
- The Tech Lead is overloaded. Consider whether external assistance (peer review, AI-assisted analysis) is needed.
- The methodology is mis-calibrated. The depth scale or lap structure may need revision.

### §7.3 False-Positive Rest Check

If the Tech Lead self-assesses "yes I'm rested" but is actually fatigued, the rest check passes incorrectly. This is the blind-spot pattern applied to self-assessment.

There is no perfect solution. The mitigation is:

- **Honest self-assessment** — the Tech Lead must be willing to say "I'm not sure if I'm rested."
- **Output-quality monitoring** — if the next lap's PRs have an unusual defect rate, that's a signal the rest check was a false positive.
- **AI-assisted analysis** — an AI reviewer (per [SDLC-05](SDLC-05-AI-Assisted-Development-Protocol.md)) can flag confident-but-wrong patterns that the fatigued Tech Lead missed.

---

## §8. What This Document Does NOT Specify

- **Spiral Deepening model** — see [SDLC-01](SDLC-01-Foundations.md).
- **Two-DAG governance / Eligible formula** — see [SDLC-02](SDLC-02-Governance.md).
- **Interface freeze / ADR format / Fidelity Bar** — see [SDLC-03](SDLC-03-InterfaceFreeze.md).
- **AI-assisted development protocol** — see [SDLC-05](SDLC-05-AI-Assisted-Development-Protocol.md).
- **Generator specifications** — see [SDLC-06](SDLC-06-Generator-Specifications.md).

---

## §9. Provenance

This document is part of the SDLC rewrite (Batch 2, PR #318, 2026-10-07), per Tech-Lead directive: "then 3 SDLC the next" (after Core Batch 1).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §3.4 rest check + §5 CI-green-≠-verification + §7.3 false-positive rest check)
- Nuclear-Grade Doctrine (cross-reference at §3.3 refactor backlog — depth 5 binding affects what debt is acceptable)
- Integrity Gate (§5.3 between-lap analog of convergence criteria)
- Two-DAG Governance (§3.2 OD triage — dispositions are recorded in OPEN-DECISIONS.md, which is part of the governance ledger)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link points to a canonical document.
- Doctrine application: every doctrine listed in §9 is actually applied in the document body.

This document is a starting point. It is not a complete specification. The number of cooldown mechanics specified here is not the number of cooldown mechanics that exist.
