# MEMORY_INSTRUCTIONS.md — DGLab Wheel Project

**What this is:** the operational playbook for any AI agent working on the DGLab Wheel project.
Read this SECOND — immediately after `MEMORY.md`, before any task prompt, before the shared context
block (SDLC-AGRD v3.4 §11.3), before `worklog.md`.

**Relationship to other files:**

| File | Holds | Question it answers |
|---|---|---|
| `MEMORY.md` | Durable STATE — project identity, team, methodology, current snapshot, OD table, vocabulary, failure history. | "What is the project and where is it right now?" |
| `MEMORY_INSTRUCTIONS.md` (this file) | Durable PROCESS — boot sequence, pre-task checklist, kill-switch decision trees, hand-off protocol, worklog protocol, surface-don't-decide rules. | "How do I act on a task without breaking the methodology?" |
| `worklog.md` | Per-task execution log (append-only). | "What have prior agents actually done?" |
| `SDLC-AGRD-v3.4.md` §11 | The full prompt library per capability class. | "What exact prompt should I run for this task type?" |
| `Architecture/OPEN-DECISIONS.md` | Live OD list with analysis. | "What decisions are still open and why?" |

This file is **curated, not append-only**. The tech lead updates it when the methodology itself
changes (v3.4 → v3.5, prompt recalibration after lap 1 data, a new kill-trigger discovered). Per-task
findings go in `worklog.md`. If you find this file stale, surface it — do not edit it without
tech-lead approval (same rule as `MEMORY.md`).

---

## 1. Boot sequence — what to read, in what order

Every agent, every task, every capability class. No exceptions, no skipping.

1. **`MEMORY.md`** — entire file. This is non-negotiable. If you read only one file, read this one.
2. **This file (`MEMORY_INSTRUCTIONS.md`)** — entire file. Yes, even if you "already know it." Read it.
3. **`worklog.md`** — at minimum the last 3 entries. If fewer than 3 exist, read all of them.
   Pay attention to the most recent `Hand-off notes` field — it may be addressed to you.
4. **The task prompt** from SDLC-AGRD v3.4 §11.4–11.13 (whichever subsection matches your task type).
   Cloud agents receive this in the hand-off bundle; local agents look it up themselves.
5. **The shared context block** (v3.4 §11.3). Paste it into your task prompt with current-state fields
   filled in from `MEMORY.md` §7. Do not run with the placeholder `<...>` fields unfilled.
6. **Execute the task.** Surface, don't decide, when you hit any of the §4 hard stops below.
7. **Append to `worklog.md`** when done. Use the §7 template. Append-only — never overwrite.

If you are a **cloud agent** (Cloud PR / Cloud Async / Cloud Conversational per §11.2), the
hand-off bundle from the originating local agent must contain items 1, 3, 4, 5 already prepared
for you. If it does not, that is a hand-off protocol violation — surface it, do not improvise
context from your training data.

---

## 2. Pre-task checklist

Atomic, in order. Tick each box mentally (or literally in your worklog entry). Do not skip steps.

- [ ] Read `MEMORY.md` end to end.
- [ ] Read `MEMORY_INSTRUCTIONS.md` (this file) end to end.
- [ ] Read the last 3 `worklog.md` entries.
- [ ] Identified my capability class (Local Editor / Local CLI / Local Inline / Cloud PR / Cloud Async / Cloud Conversational per v3.4 §11.2). If unsure, default is Cloud Async.
- [ ] Identified the task type (Cooldown 0 / Milestone 0 / lap widen / lap deepen / cooldown lint expansion / ADR-gated event / bet-kill / Milestone-0-kill / review pass).
- [ ] Located the matching prompt in v3.4 §11.4–11.13.
- [ ] Filled in the shared context block (v3.4 §11.3) with current values from `MEMORY.md` §7.
- [ ] Checked `Architecture/OPEN-DECISIONS.md` for ODs whose trigger the task might trip (per §8 of `MEMORY.md`).
- [ ] Confirmed no frozen interface in scope without an ADR cited (per `MEMORY.md` §4 rule 1).
- [ ] Estimated the bet time-box. Computed 1.5× kill threshold (per `MEMORY.md` §4 rule 4).
- [ ] If cloud agent: confirmed the hand-off bundle is complete (§6 below).
- [ ] Execute. Surface at the first hard stop (§4 below). Do not push through.

---

## 3. Capability class — how to classify yourself

Per v3.4 §11.2. Match on capability, not on vendor. New agents (Kilo, Zoo, future tools) slot in by
capability match — do not wait for §11.2 to be updated to admit yourself.

| Capability class | Defining trait | Default prompt subsection |
|---|---|---|
| Local Editor | Runs inside the editor; can read+write the workspace directly; you see the diff in real time. | §11.4–11.13 as task dictates |
| Local CLI | Runs in a terminal; can read+write the workspace via shell; you do NOT see editor state. | §11.4–11.13 as task dictates |
| Local Inline | Inline completion only (suggests text in the editor); cannot run multi-step tasks autonomously. | N/A — used for snippet-level assists, not task prompts |
| Cloud PR | Opens a PR from a cloud environment; has repo access but no editor; review happens on GitHub. | §11.4–11.13 + PR-mode notes in §11.13 |
| Cloud Async | Long-running cloud task; no live conversation; result is delivered as a report or PR. | §11.4–11.13 + async hand-off bundle per §6 below |
| Cloud Conversational | Live chat with cloud agent; conversation history is the context window. | §11.4–11.13 + conversation-mode notes in §11.13 |

**Default when uncertain:** Cloud Async. This is the conservative classification — it forces a
hand-off bundle (§6), which prevents silent context loss. A Local Editor misclassified as Cloud
Async wastes some time on bundle overhead; a Cloud Async misclassified as Local Editor silently
loses context. Asymmetric risk → conservative default.

---

## 4. Surface-don't-decide — the 7 hard stops

These are the conditions where you STOP and surface to the tech lead. Do not "be helpful" by
resolving them yourself. Pushing through any of these is a methodology violation, even if the
resulting code works.

For each: the trigger, what to do, what NOT to do, and the worklog entry format.

### 4.1 Interface freeze violation

- **Trigger:** the task touches a public contract that has already been implemented (i.e., is
  frozen per v3.4 §2.1), and no ADR is cited in the task authorizing the change.
- **Do:** STOP. Note which interface, which ADR (if any) was expected, append to worklog. Surface
  to tech lead.
- **Do NOT:** "improve" the interface, even if the improvement is obviously correct. Do NOT rename
  a parameter, do NOT tighten a return type, do NOT add a required field, do NOT remove a deprecated
  field. Frozen means frozen.
- **Worklog entry:** `HARD STOP — interface freeze: <blueprint-id> public contract touched without ADR. Expected ADR: <id or "none cited">. Surfaced to tech lead. No code changes made.`

### 4.2 Global lap floor temptation

- **Trigger:** you find yourself wanting to apply a uniform depth target across all blueprints in a
  lap ("every blueprint deepens to depth N this lap") because it seems simpler.
- **Do:** STOP. Re-read v3.4 §4.3 and `MEMORY.md` §4 rule 2. The per-blueprint relative floor is
  the rule. Each blueprint's target depth = `admission_depth + laps_since_admission`, capped at 5.
- **Do NOT:** apply a global floor "for simplicity." That was the v3.2 spec bug. v3.4 explicitly
  rejected it.
- **Worklog entry:** `HARD STOP — global lap floor temptation: considered applying uniform depth <N> across ring <X>. Re-read §4.3. Correct per-blueprint floor is <list>. Proceeding with per-blueprint.`

### 4.3 Mid-bet lint scope creep

- **Trigger:** mid-bet, you notice a lint check that "would be quick to add" — a missing assertion,
  an extra rule, a tightened threshold.
- **Do:** STOP. Log it for the next cooldown. Do not add the check now.
- **Do NOT:** add the check mid-bet, even if it's "one line." Lint scope expansion is a cooldown
  activity per v3.4 §6. Mid-bet lint changes invalidate throughput calibration.
- **Worklog entry:** `DEFERRED — lint gap noticed mid-bet: <description>. Logged for next cooldown. Not acted on.`

### 4.4 Bet kill at 1.5× time-box

- **Trigger:** the bet has consumed 1.5× its time-box and is not complete.
- **Do:** STOP. Keep partial progress (interface was already frozen, so partial deepening is
  never wasted — v3.4 §4.3 Gap B). Surface for re-scoping. This is a routine event, not a failure.
- **Do NOT:** silently extend the time-box. Do NOT push through to "just finish it." Do NOT conflate
  this with the Milestone 0 kill (they are different in kind, see §5 below).
- **Worklog entry:** `BET KILL — <blueprint-id> <depth-level> exceeded 1.5× time-box (<elapsed> vs <budget>). Partial progress: <what was completed>. Surfaced for re-scoping.`

### 4.5 Milestone 0 kill at 8 weeks

- **Trigger:** Milestone 0 has consumed 8 weeks and is not complete.
- **Do:** STOP. This is a stop-the-line reassessment, NOT a calibrate-forward event. The
  architecture itself, or the solo-mode assumption, may be wrong. Surface immediately.
- **Do NOT:** push to week 9. Do NOT treat this as a routine bet kill. Do NOT quietly re-baseline
  the timeline. The qualitative difference from a bet kill (§5 below) is the entire point.
- **Worklog entry:** `MILESTONE 0 KILL — 8 weeks elapsed, Milestone 0 incomplete. Stop-the-line. <state of the 8 blueprints>. Surfaced for methodology reassessment.`

### 4.6 OD pre-resolution

- **Trigger:** the task trips the trigger of an OD that is not yet due to resolve (per `MEMORY.md`
  §8 / v3.4 §8.1), and the "obvious" resolution is sitting right there.
- **Do:** STOP. Surface the decision point. Let the tech lead close it with an ADR.
- **Do NOT:** resolve the OD silently in code. Even if the resolution is "obviously correct," an
  uncited resolution is invisible to future agents and will cause ADR thrash.
- **Worklog entry:** `OD TRIGGER — <OD-id> trigger encountered during <task>. Did not resolve. Surfaced for ADR. <one-line summary of the decision shape>.`
- **Lap-1 widen special case:** CORE-16 and HUB-04 are EXCLUDED from lap 1 widening until OD-02
  resolves (`MEMORY.md` §4 rule 7). Do not admit them early "to save a lap."

### 4.7 MEMORY.md drift

- **Trigger:** you complete a task and notice `MEMORY.md` is stale (e.g., you resolved an OD but
  §8 still says "Open"; or §7's matrix snapshot doesn't include a blueprint you just admitted).
- **Do:** Two paths, depending on what's stale:
  - **§7 current-state fields** (Last updated date, lap count, throughput, matrix snapshot,
    cooldown status, Milestone 0 status): you MAY update these directly if you completed the
    triggering event (a Milestone-0 blueprint, a lap widen/deepen step, a cooldown). Append a
    worklog entry noting the §7 update so the tech lead can audit.
  - **Everything else** (§1–6, §8–11): surface it. Do not edit. Curation discipline is what makes
    `MEMORY.md` trustworthy; an agent-edited `MEMORY.md` with stale-then-half-correct entries is
    worse than a cleanly-stale one because the next agent can't tell which parts are current.
- **Do NOT:** rewrite §1–6, §8–11 inline because "it's faster." Surface.

---

## 5. Kill triggers — qualitative difference, not just size

Two kill triggers in this methodology. Conflating them is the most common agent failure mode. Read
this section twice.

| Dimension | Bet kill (§4.4) | Milestone 0 kill (§4.5) |
|---|---|---|
| Trigger | 1.5× time-box on a single bet | 8 weeks elapsed, Milestone 0 incomplete |
| Frequency | Routine — expected to happen, multiple times per lap | Once-ever — happens at most once in project history |
| Severity | Re-scoping event | Stop-the-line reassessment |
| What it questions | This bet's scope | The architecture, the solo-mode assumption, or the methodology itself |
| After-action | Keep partial progress, re-scope, continue | Do not continue. Tech lead reassesses. Possibly bumps methodology version. |
| Worklog tone | "Routine bet kill, partial progress kept, re-scoping." | "Stop-the-line. Milestone 0 incomplete at 8 weeks. Surfacing for methodology reassessment." |

**The failure pattern:** an agent hits the Milestone 0 kill at 8 weeks, treats it as a bet kill
("routine, just push through with re-scoping"), and pushes to week 9. This is the single most
damaging agent failure mode in this methodology. If you feel the urge to "just push through one
more week," STOP — that urge is the failure mode.

---

## 6. Hand-off bundle protocol — local ↔ cloud

When a task moves from one agent to another — especially local → cloud or cloud → local — the
originating agent produces a hand-off bundle in `worklog.md` BEFORE the target agent starts.

Cloud agents have no conversation history, no `worklog.md` awareness, no `MEMORY.md` awareness.
The bundle IS the contract. A cloud agent that received an incomplete bundle must surface it, not
improvise.

### 6.1 Bundle template (paste into worklog entry, `Hand-off notes` field)

```text
HAND-OFF BUNDLE — to: <capability class of target agent> (<product name if known>)

Task for target agent:
<one-paragraph task definition, self-contained — target agent has no other context>

Files to read before starting:
- <path 1> — <why>
- <path 2> — <why>

Files NOT to touch:
- <path 1> — <why not — e.g., frozen interface, OD-02 territory, another agent's in-flight work>

Current state (from MEMORY.md §7, snapshot as of <date>):
- Methodology version: <e.g., v3.4>
- Cooldown 0 status: <started/complete/not started>
- Milestone 0 status: <in progress, week N of 8 / complete / not started>
- Lap count: <N>
- Last bet: <blueprint-id> <depth-level>, <result>

ODs/ADRs in play for this task:
- <OD-id>: <status, e.g., "trigger expected during this task — do not resolve, surface">
- <ADR-id>: <relevance, e.g., "cited in the task; interface change authorized">

Shared context block (v3.4 §11.3) with current-state fields filled:
<paste the filled-in block here — do not leave <...> placeholders>

Kill triggers active for this task:
- Bet kill at 1.5× time-box: budget = <hours/days>, threshold = <hours/days>
- Milestone 0 kill (if applicable): week <N> of 8

Worklog entries to read for context:
- Task ID <most recent>
- Task ID <prior>
- Task ID <prior>
```

### 6.2 Hand-off scenarios

- **Local Editor → Cloud Async:** local agent has full editor context, cloud has none. Bundle is
  mandatory. Cloud Async agent's first worklog entry must acknowledge receipt of the bundle or
  surface any missing field.
- **Cloud Async → Local Editor:** cloud agent's report PR or output IS the bundle. Local agent
  must still write a `Hand-off notes` field pointing to the cloud agent's report file/PR.
- **Local Editor → Local Editor (different session):** bundle is still recommended — the next
  session has no prior conversation history. Treat like local→cloud.
- **Cloud Conversational → any:** the conversation transcript is the context, but a written bundle
  in `worklog.md` is still required — the transcript may be lost when the session ends.

### 6.3 What does NOT go in the bundle

- Generic methodology explanations (the target agent reads `MEMORY.md` and `MEMORY_INSTRUCTIONS.md`
  for that — don't duplicate).
- Full file contents (cite paths; let the target agent read).
- The originating agent's reasoning trace (only the conclusions matter to the target).
- Anything not relevant to the specific task (keep the bundle tight — a bloated bundle is read
  less carefully than a tight one).

---

## 7. Worklog append protocol

`worklog.md` is append-only. Every agent, every task, no exceptions. If you can't find
`worklog.md`, ASK — its absence is a red flag, not permission to skip logging.

### 7.1 Template

```text
---
Task ID: <e.g., 4, or 4-a for parallel subtask>
Agent: <capability class> (<product name>) — e.g., "Local Editor (Cline)" or "Cloud Async (Jules)"
Task: <the task you were asked to do, in one sentence>

Work Log:
- <concrete step 1>
- <concrete step 2>
- <concrete step 3>
- ...

Stage Summary:
- <key results / important decisions / produced artifacts>
- <if deliverables: paths under /home/z/my-project/download/>

Hand-off notes:
- <for the next agent — see §6.1 template if a hand-off is in flight>
- <"none" if no hand-off>
```

### 7.2 Do's and don'ts

- **Do** append at the end of the file. Never overwrite, never insert mid-file.
- **Do** include concrete step descriptions, not narrative. "Read v3.4 §4.3" not "reviewed the
  methodology."
- **Do** cite file paths for every artifact produced (under `/home/z/my-project/download/`).
- **Do** fill the `Hand-off notes` field even if it's just "none" — empty fields look like an
  omission.
- **Don't** summarize or paraphrase prior entries. The log is the source of truth, not a summary.
- **Don't** omit failures. A worklog entry that says "Bet killed at 1.5× time-box" is more
  valuable than one that says "Bet completed." Failures calibrate the methodology.
- **Don't** editorialize. "The spec is wrong" is a surface-to-tech-lead, not a worklog entry. The
  worklog records what happened, not your opinion of it.

---

## 8. OD encounter protocol — what to do when you trip a trigger

Six ODs are currently open (see `MEMORY.md` §8 for the table). Each has a resolution timing per
v3.4 §8.1. When a task trips a trigger:

1. **Identify which OD.** Match the trigger condition against `Architecture/OPEN-DECISIONS.md`.
2. **Check resolution timing.** Per `MEMORY.md` §8:
   - OD-06 resolves during Milestone 0 (mechanically forced by CORE-02).
   - OD-02 resolves in Cooldown 1 (after Milestone 0, NOT during it, NOT during Cooldown 0).
   - OD-01/03/04/05 keep their existing `OPEN-DECISIONS.md` timing.
3. **If the timing has arrived:** surface to tech lead. Do not resolve yourself. The tech lead
   closes with an ADR; the ADR is then cited in the implementing task.
4. **If the timing has NOT arrived:** surface as a "pre-resolution attempt" — note that the trigger
   fired early. Do not implement either way. Early resolution is as bad as silent resolution.
5. **Worklog entry:** see §4.6 above.

The single most common OD failure: an agent trips OD-02 (post-quantum JWT) during Milestone 0
because "the JWT lib choice has to be made now." It does not. Surface — the JWT choice is
intentionally deferred to Cooldown 1.

---

## 9. Interface freeze protocol — what to do when you encounter a frozen interface

Per v3.4 §2.1 and `MEMORY.md` §4 rule 1.

1. **Check if the interface is frozen.** A blueprint's public contract freezes at first
   implementation. If the blueprint has been implemented at any depth ≥ 1, the contract is frozen.
   Check `Architecture/INDEX.md` for admission status and `Architecture/STRUCTURE-XX.md` for the
   blueprint's spec.
2. **If frozen and an ADR is cited in the task:** proceed. The ADR authorizes the change.
3. **If frozen and no ADR:** STOP. See §4.1 above.
4. **If NOT frozen:** proceed; this is the first implementation, the freeze happens as part of your
   task. Document the frozen contract in the blueprint's STRUCTURE file. Cite the freezing commit
   in the worklog.

The freeze applies to: public method signatures, public field names, public types, wire formats
(HTTP request/response shapes, JSON schemas), and configuration keys. It does NOT apply to: private
implementations, internal helpers, test fixtures (unless the test is itself a public contract).

---

## 10. `MEMORY.md` update protocol — when you may edit, when you must surface

Per `MEMORY.md` §11. Summary here for fast reference.

| Field | Who may update | When |
|---|---|---|
| `MEMORY.md` §7 "Last updated" date | Any agent completing the triggering event | Milestone-0 blueprint completed / lap widen+deepen step done / cooldown completed |
| `MEMORY.md` §7 matrix snapshot | Any agent completing the triggering event | Same — update which blueprints are admitted, at what depth |
| `MEMORY.md` §7 throughput | Tech lead only | After Milestone 0 completes, after each lap recalibration |
| `MEMORY.md` §8 OD status | Tech lead only (after ADR merged) | When an OD resolves |
| `MEMORY.md` §§1–6, 9–11 | Tech lead only | When durable facts change (methodology version bump, file map change, etc.) |
| `MEMORY_INSTRUCTIONS.md` (this file) | Tech lead only | When the methodology's process rules change |

**The audit rule:** if you update §7, you MUST append a worklog entry noting the §7 update. The
tech lead audits `worklog.md` to catch §7 drift. An unlogged §7 update is a violation.

**The "looks stale" rule:** if you find `MEMORY.md` stale in any field you may not edit, surface
it — even if "the obvious fix is one line." Curation discipline beats speed here.

---

## 11. Anti-patterns — things that look reasonable but are forbidden

Each of these has happened in prior SDLC versions and caused real damage. Recognition is the first
defense.

| Anti-pattern | Why it looks reasonable | Why it's forbidden | Reference |
|---|---|---|---|
| "Just push through one more week" on Milestone 0 | Sunk cost; feels like a bet kill | Milestone 0 kill is qualitatively different — pushes hide architecture flaws | §5 above, `MEMORY.md` §4 rule 5 |
| Silent OD resolution | "The answer is obvious, why ask?" | Uncited resolutions are invisible to future agents; cause ADR thrash | §4.6 above, `MEMORY.md` §4 rule 6 |
| Mid-bet lint scope creep | "It's just one quick check" | Invalidates throughput calibration; lint is a cooldown activity | §4.3 above, `MEMORY.md` §4 rule 3 |
| Global lap floor | "Simpler than per-blueprint" | Was the v3.2 spec bug; v3.4 explicitly rejected it | §4.2 above, `MEMORY.md` §4 rule 2 |
| Interface "improvement" mid-task | "Obviously better" | Frozen means frozen; improvements are ADR-gated | §4.1 above, `MEMORY.md` §4 rule 1 |
| Editing `MEMORY.md` without approval | "It's faster than waiting" | Curation discipline is what makes the file trustworthy | §10 above |
| Conflating bet kill with Milestone 0 kill | "Both are kills" | Different in kind, not just size; conflating leads to push-through | §5 above |
| Cloud agent improvising context from training data | "I know this domain" | Project context (DGLab Wheel, v3.4 rules) is not in training data; improvised context is wrong context | §6 above |
| Skipping the worklog because the task was small | "It was just a typo fix" | The log is the audit trail; small tasks compound; gaps make the log untrustworthy | §7 above |
| Treating the §11 prompts as gospel after v3.5 lands | "It's the spec" | §11.14 of v3.4 explicitly says prompts need recalibration after lap 1 data | v3.4 §11.14 |

---

## 12. Honest caveats — what this file does NOT promise

Same pattern as v3.4 §11.14. This file is honest about its own limits.

- **This file does not promise completeness.** It captures the rules and failure modes known as of
  v3.4. After lap 1 data lands, some rules will need recalibration. The §11 anti-patterns table is
  not exhaustive — it's the patterns that have actually bitten prior versions.
- **This file does not replace the prompts.** The prompts in v3.4 §11.4–11.13 encode the
  methodology in operational form. This file encodes the meta-rules around them (when to surface,
  how to hand off, how to log). Both are needed.
- **This file does not replace `MEMORY.md`.** `MEMORY.md` is state; this file is process. An agent
  that reads only this file will know how to behave but not what the project is. An agent that
  reads only `MEMORY.md` will know what the project is but not how to behave. Both are required
  reads.
- **This file does not promise to catch every novel failure.** Every prior SDLC version had a real
  flaw that the next version fixed (see `MEMORY.md` §9). v3.4 will too. The §11.12 review-pass
  prompt is the mechanism for finding the next flaw — run it after lap 1 data, not before.
- **This file does not override the spec.** If anything in this file conflicts with v3.4 §§1–10,
  the spec wins. Surface the conflict — do not silently pick one.

---

End of `MEMORY_INSTRUCTIONS.md`. Read `worklog.md` next.
