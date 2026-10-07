# SDLC-05: AI-Assisted Development Protocol

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document specifies how AI assistants participate in DGLab development. The protocol itself is a starting point, not a complete specification. Every rule about what AI may decide, what requires Tech-Lead verification, what counts as "verification conditions met" is open to challenge when AI-produced work surfaces defects that the protocol didn't prevent. The number of rules specified here is not the number of rules that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (Batch 2 of the SDLC rewrite, per Tech-Lead directive 2026-10-06).
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) + the Blind-Spot Doctrine's rule #4 (AI work is confident-but-may-be-wrong).
**Related:** [SDLC-01](SDLC-01-Foundations.md) (Foundations), [SDLC-02](SDLC-02-Governance.md) (Governance), [SDLC-03](SDLC-03-InterfaceFreeze.md) (Interface Freeze), [SDLC-04](SDLC-04-CooldownMechanics.md) (Cooldown Mechanics), [BLIND-SPOT-DOCTRINE](../CrossCutting/BLIND-SPOT-DOCTRINE.md), [PROMPTS](../CrossCutting/PROMPTS.md).

---

## §1. Why This Document Exists

DGLab is built by a solo Tech Lead, optionally assisted by AI systems. The AI systems are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning. This is not a defect of the AI — it is a property of any contributor working without peer review (per the Blind-Spot Doctrine, rule #4).

Without a protocol, AI-assisted development fails in two ways:

1. **Over-trust** — the Tech Lead accepts AI work as verified when it hasn't been. The CI is green, the code looks correct, but a subtle defect (an edge case the AI didn't consider, a contract the AI silently violated, a dependency the AI assumed but didn't verify) ships.
2. **Under-trust** — the Tech Lead re-verifies every AI contribution from scratch, defeating the purpose of AI assistance. The AI becomes a fancy autocomplete, not a collaborator.

This protocol defines the middle path: AI assistants do substantial work; the Tech Lead verifies the verification conditions (not the work itself); the boundary between AI work and Tech-Lead decision is explicit.

---

## §2. What AI Assistants May Do

### §2.1 Implementation Work

AI assistants may:

- **Write new code** that implements an existing frozen interface (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §1). The interface is the contract; the implementation is the work.
- **Refactor existing code** as long as the frozen contract is preserved. AI identifies opportunities, proposes refactors, executes them. The Tech Lead reviews the diff.
- **Write tests** for existing behavior. AI identifies gaps, proposes test cases, writes the test code.
- **Update documentation** — improving clarity, adding examples, fixing typos. The Tech Lead reviews the changes for accuracy.
- **Run lint, test, and verification tooling** — AI executes the commands, reads the output, reports findings.

### §2.2 Analysis Work

AI assistants may:

- **Analyze the repository** — read blueprints, code, ADRs, ODs, and provide analysis. This document (and the other SDLC rewrite documents) are examples.
- **Identify blind spots** — AI can flag patterns the Tech Lead may have missed. The Blind-Spot Doctrine explicitly acknowledges this (rule #1: "An audit is a starting point, not a complete inventory").
- **Propose ADRs** — AI may draft ADRs (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §2). The Tech Lead ratifies.
- **Triage Open Decisions** — AI may propose dispositions for ODs. The Tech Lead makes the final disposition.

### §2.3 Coordination Work

AI assistants (specifically the main agent, not subagents) may:

- **Coordinate subagents** — the main agent may dispatch subagents for parallel work. Each subagent's work is recorded in the worklog per [SDLC-04](SDLC-04-CooldownMechanics.md) §6.4.
- **Manage PRs** — the main agent may create branches, push commits, open PRs, merge PRs (with Tech-Lead authorization).
- **Run CI** — the main agent may query CI status, download logs, parse failures.

---

## §3. What AI Assistants May NOT Do

### §3.1 May Not Ratify ADRs

AI assistants may draft ADRs, but they may NOT ratify them. Ratification (changing Status from `Proposed` to `Accepted`) is a Tech-Lead decision per [SDLC-03](SDLC-03-InterfaceFreeze.md) §3.3.

This rule exists because ADRs encode architectural decisions that bind the project. An AI may propose a decision, but the decision's authority comes from the Tech Lead's ratification — not from the AI's confidence in the decision.

### §3.2 May Not Silently Resolve Open Decisions

Per [SDLC-03](SDLC-03-InterfaceFreeze.md) §7, unresolved questions must be recorded as ODs, not silently resolved. AI assistants are particularly prone to this failure mode — an AI that encounters a question may "decide" an answer and proceed, without recording the question or the decision.

The rule: if an AI encounters a question that doesn't have an answer, it must:
1. Stop the work that depends on the question.
2. Open an OD (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §4) recording the question.
3. Optionally propose options for the OD.
4. Wait for Tech-Lead disposition (or proceed with a different task that doesn't depend on the question).

### §3.3 May Not Modify Frozen Contracts

AI assistants may NOT modify frozen interfaces, class signatures, or behavior contracts. Such changes require an ADR (per [SDLC-03](SDLC-03-InterfaceFreeze.md) §3.1). The AI may PROPOSE the change (by drafting an ADR), but it may not IMPLEMENT the change without the ADR being ratified.

### §3.4 May Not Skip the Cooldown Gates

AI assistants may NOT skip the cooldown's four gates (per [SDLC-04](SDLC-04-CooldownMechanics.md) §3). The worklog reconciliation, OD triage, refactor backlog review, and rest check are all Tech-Lead decisions. The AI may PREPARE the materials for each gate (e.g., generate the worklog summary), but the gate's pass/fail is the Tech Lead's.

### §3.5 May Not Self-Assess Rest

The rest check (per [SDLC-04](SDLC-04-CooldownMechanics.md) §3.4) is a Tech-Lead self-assessment. AI assistants may NOT substitute their assessment of the Tech Lead's fatigue. The rest check is explicitly a human decision, not an AI-observable property.

---

## §4. Verification Conditions — What "Verified" Means for AI Work

### §4.1 The Rule

AI-produced work is a hypothesis, not a fact. The Tech Lead's verification is the act that converts the hypothesis to a fact. Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #2):

> "A finding is closed only when its verification condition passes — not when code changes, not when CI is green."

For AI work, the "finding" is the AI's claim ("this implementation is correct", "this test covers the behavior", "this refactor preserves the contract"). The verification condition is the SPECIFIC check that confirms the claim.

### §4.2 What Verification Conditions Look Like

| AI Claim | Verification Condition |
|---|---|
| "This implementation is correct" | The frozen interface's tests pass, AND the implementation's specific edge cases are tested, AND PHPStan level max passes, AND the architecture-boundary-lint passes (files_scanned > 0). |
| "This test covers the behavior" | The test FAILS when the behavior is broken (mutation testing or manual verification), AND the test passes when the behavior is correct. |
| "This refactor preserves the contract" | The frozen interface's tests still pass, AND the diff shows no changes to public method signatures, AND the architecture-boundary-lint passes. |
| "This documentation is accurate" | The documented interface matches the actual interface (verifiable by reading the code), AND the documented behavior matches the actual behavior (verifiable by reading the tests), AND no invalid tokens are introduced (verifiable by architecture-lint). |
| "This lint actually executed" | `files_scanned > 0` in the JSON output, AND the workflow's "Verify scan coverage" step passed (per [SDLC-02](SDLC-02-Governance.md) §3.2). |

### §4.3 What Verification Is NOT

- **"CI is green"** is not verification. CI green means the workflow steps exited 0. It does NOT mean the specific verification conditions for the AI's claims were checked (per [SDLC-04](SDLC-04-CooldownMechanics.md) §5.1).
- **"The code looks correct"** is not verification. AI-produced code can look correct while being subtly wrong (per the Blind-Spot Doctrine).
- **"The AI is confident"** is not verification. AI confidence is a property of the AI's output, not a property of the work's correctness.

### §4.4 The Three Recurrences (Why This Matters)

During the integrity phase, three recurrences of the "CI green ≠ verification" pattern confirmed the rule's necessity:

- **S-054** — test file was missing. CI was green (the test wasn't being run). The AI's claim: "the test exists." Verification condition: "the test file is present and named correctly." The verification condition was NOT checked.
- **S-033** — DAG count was stale. CI was green (no test verified the count). The AI's claim: "the DAG has N edges." Verification condition: "the DAG's edge count matches the actual file content." NOT checked.
- **S-077** — register was not updated. CI was green. The AI's claim: "the register is current." Verification condition: "the register's last-updated date is after the last finding's resolution date." NOT checked.

Each was a case where the AI's claim was accepted without verification. The cooldown's CI verification step (per [SDLC-04](SDLC-04-CooldownMechanics.md) §5) is the response.

---

## §5. The Subagent Protocol

### §5.1 What a Subagent Is

A subagent is an AI assistant dispatched by the main agent for a specific, well-defined task. Subagents do NOT have access to the full conversation context — they only receive the prompt the main agent passes.

### §5.2 What Subagents Are Good For

Subagents are suitable for:

- **Independent, well-defined subtasks** — e.g., "search the codebase for files matching pattern X", "read file Y and report its structure", "verify that all references to Z are valid".
- **Parallel work** — multiple subagents can work on independent subtasks simultaneously.

### §5.3 What Subagents Are NOT Good For

Subagents are NOT suitable for:

- **Tasks requiring full conversation context** — the subagent doesn't have it.
- **Tasks requiring skill compliance** — the subagent doesn't have the skill instructions unless the main agent passes them.
- **Tasks requiring content depth standards** — the subagent doesn't know the standards unless the main agent specifies them.
- **Tasks requiring document formatting rules** — same.

### §5.4 The Subagent Worklog Protocol

When the main agent dispatches a subagent:

1. The main agent assigns a Task ID that reflects the global order and possible parallelism (e.g., `2-a`, `2-b` for parallel tasks at step 2).
2. The main agent passes the Task ID to the subagent in the prompt.
3. The subagent appends its work record to `worklog.md` per [SDLC-04](SDLC-04-CooldownMechanics.md) §6.3.
4. The main agent's worklog entry references the subagent's Task ID.

### §5.5 The Subagent Verification Rule

The main agent is RESPONSIBLE for verifying subagent work. The subagent's worklog entry is a CLAIM, not a verified fact. The main agent must:

- Verify the subagent's claimed artifacts actually exist (file present, content matches the claim).
- Verify the subagent's claimed verification conditions actually passed (re-run the lint, re-check the test).
- Verify the subagent didn't silently resolve questions (check for ODs that should have been opened).

This is the multi-agent analog of the Tech-Lead-verifies-AI-work rule. The main agent is to the subagent what the Tech Lead is to the main agent.

---

## §6. The "Confident but Wrong" Failure Mode

### §6.1 The Pattern

AI systems (and fatigued humans) produce work that:
- **Looks correct** — the code is well-formed, the documentation is clear, the tests pass.
- **Is confidently presented** — the AI's output doesn't hedge, doesn't express uncertainty.
- **Contains a subtle defect** — an edge case not considered, a contract silently violated, a dependency assumed but not verified.

This is the "confident but wrong" failure mode. It is the central risk of AI-assisted development.

### §6.2 Why It Happens

AI systems are trained to produce confident-sounding output. They are NOT trained to express uncertainty proportionally to their actual uncertainty. When the AI doesn't know something, it often produces a plausible-sounding answer rather than saying "I don't know."

This is not a defect of the AI — it is a property of the training. The protocol's response is to require verification conditions, not to expect the AI to express uncertainty.

### §6.3 The Mitigation

The mitigation is NOT "be more suspicious of AI work." Suspicion without verification is just as bad as trust without verification — both are vibes-based, not evidence-based.

The mitigation IS "verify the specific claims." For each AI claim, identify the verification condition (per §4.2) and check it. The check is the verification — not the AI's confidence, not the Tech Lead's suspicion.

### §6.4 The Blind-Spot Doctrine Cross-Reference

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #4):

> "Both humans and AI systems produce confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning."

This rule applies to ALL AI work, including this document. This document was written by an AI assistant (the main agent). It may contain confident-sounding claims that are subtly wrong. The Tech Lead's verification is the act that converts this document from a hypothesis to a fact.

The verification conditions for this document:
- Architecture-lint: scans for invalid tokens, misattribution phrases, structural completeness.
- Cross-reference integrity: every link points to a canonical document.
- Doctrine application: every doctrine listed in §7 is actually applied.

If the Tech Lead finds a defect in this document, the correction is a new commit (not an edit — per the worklog's append-only rule, per [SDLC-04](SDLC-04-CooldownMechanics.md) §6.2).

---

## §7. What This Document Does NOT Specify

- **Spiral Deepening model** — see [SDLC-01](SDLC-01-Foundations.md).
- **Two-DAG governance / Eligible formula** — see [SDLC-02](SDLC-02-Governance.md).
- **Interface freeze / ADR format** — see [SDLC-03](SDLC-03-InterfaceFreeze.md).
- **Cooldown mechanics** — see [SDLC-04](SDLC-04-CooldownMechanics.md).
- **Generator specifications** — see [SDLC-06](SDLC-06-Generator-Specifications.md).
- **PROMPTS** (AI agent operating instructions) — see [`../CrossCutting/PROMPTS.md`](../CrossCutting/PROMPTS.md).

---

## §8. Provenance

This document is part of the SDLC rewrite (Batch 2, PR #318, 2026-10-07), per Tech-Lead directive: "then 3 SDLC the next" (after Core Batch 1).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §1 over/under-trust + §3.2 silently-resolve prohibition + §4 verification conditions + §6 confident-but-wrong failure mode)
- Nuclear-Grade Doctrine (cross-reference at §4.2 verification conditions for frozen contract changes — depth 5 binding affects what verification is required)
- Integrity Gate (§4.4 three recurrences — the integrity phase's findings documented the "CI green ≠ verification" pattern)
- Two-DAG Governance (§4.2 verification conditions reference the DAGs — declared intent vs verified reality)
- FROZEN-CONTRACTS (§3.3 AI may not modify frozen contracts; §4.2 verification conditions for "refactor preserves contract" claim)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link points to a canonical document.
- Doctrine application: every doctrine listed in §8 is actually applied in the document body.
- Self-application: this document was written by an AI assistant. Per §6.4, the Tech Lead's verification is the act that converts this document from a hypothesis to a fact.

This document is a starting point. It is not a complete specification. The number of rules specified here is not the number of rules that exist.
