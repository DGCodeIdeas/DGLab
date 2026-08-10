# MEMORY_INSTRUCTIONS.md — DGLab Agent Operational Playbook

**What this is:** the operational playbook for any AI agent working on the DGLab project. Read this SECOND —
immediately after `MEMORY.md`, before any task prompt, before the shared context block (`PROMPTS.md` §11.3),
before `worklog.md`.

**Relationship to other files:**

| File | Holds | Question it answers |
|---|---|---|
| `MEMORY.md` | Durable STATE — identity, team, methodology, snapshot, ODs, vocabulary, failure history. | "What is the project and where is it right now?" |
| `MEMORY_INSTRUCTIONS.md` (this file) | Durable PROCESS — boot sequence, pre-task checklist, surface-don't-decide hard stops, hand-off, worklog, OD/interface-freeze protocols. | "How do I act on a task without breaking the methodology?" |
| `MEMORY-GOVERNANCE.md` | Explicit-memory policy — store/replace/remove, prohibited content, cross-session rules, and the rules for maintaining `MEMORY.md` itself. | "How is memory itself governed?" |
| `worklog.md` | Per-task execution log (append-only). | "What have prior agents actually done?" |
| `PROMPTS.md` §11 | The full prompt library per capability class. | "What exact prompt should I run for this task type?" |

This file is **curated, not append-only**. The tech lead updates it when the methodology's process rules change.
Per-task findings go in `worklog.md`. If you find it stale, surface it — do not edit without tech-lead approval.

---

## 1. Boot sequence — what to read, in what order

Every agent, every task, every capability class. No exceptions, no skipping.

1. **`MEMORY.md`** — entire file. If you read only one file, read this one.
2. **This file (`MEMORY_INSTRUCTIONS.md`)** — entire file.
3. **`worklog.md`** — at minimum the last 3 entries. Pay attention to the most recent `Hand-off notes` field.
4. **The task prompt** from `PROMPTS.md` §11.4–11.13 (whichever subsection matches your task type).
5. **The shared context block** (`PROMPTS.md` §11.3). Paste it with current-state fields filled from
   `MEMORY.md` §7. Do not run with `<...>` placeholders unfilled.
6. **Execute.** Surface, don't decide, at the first hard stop (§4).
7. **Append to `worklog.md`** when done (§7). Append-only — never overwrite.

If you are a **cloud agent** (Cloud PR / Cloud Async / Cloud Conversational), the hand-off bundle from the
originating local agent must contain items 1, 3, 4, 5 prepared for you. If it does not, that is a hand-off
protocol violation — surface it, do not improvise context from training data.

## 2. Pre-task checklist

- [ ] Read `MEMORY.md` end to end.
- [ ] Read this file end to end.
- [ ] Read the last 3 `worklog.md` entries.
- [ ] Identified my capability class (`PROMPTS.md` §11.2). If unsure, default is **Cloud Async**.
- [ ] Identified the task type (Cooldown 0 / Milestone 0 / lap widen / lap deepen / cooldown lint expansion /
  ADR-gated event / bet-kill / Milestone-0-kill / review pass).
- [ ] Located the matching prompt in `PROMPTS.md` §11.4–11.13.
- [ ] Filled in the shared context block (`PROMPTS.md` §11.3) from `MEMORY.md` §7.
- [ ] Checked `Architecture/OPEN-DECISIONS.md` for ODs whose trigger the task might trip.
- [ ] Confirmed no frozen interface in scope without an ADR cited (`MEMORY.md` §5 rule 1).
- [ ] Estimated the bet time-box; computed the 1.5× kill threshold.
- [ ] If cloud agent: confirmed the hand-off bundle is complete (§6).
- [ ] Execute. Surface at the first hard stop (§4). Do not push through.

## 3. Capability class — how to classify yourself

Per `PROMPTS.md` §11.2. Match on capability, not vendor. New agents (Kilo, Zoo, future tools) slot in by
capability match — do not wait for the table to be updated.

| Capability class | Defining trait | Default prompt subsection |
|---|---|---|
| Local Editor | Runs inside the editor; reads+writes the workspace; sees the diff live. | `PROMPTS.md` §11.4–11.13 as task dictates |
| Local CLI | Runs in a terminal; reads+writes via shell; no editor state. | `PROMPTS.md` §11.4–11.13 as task dictates |
| Local Inline | Inline completion only; cannot run multi-step tasks autonomously. | Snippet-level assists, not task prompts |
| Cloud PR | Opens a PR from a cloud environment; review on GitHub. | `PROMPTS.md` §11.4–11.13 + PR-mode notes |
| Cloud Async | Long-running cloud task; no live conversation; result is a report/PR. | `PROMPTS.md` §11.4–11.13 + async hand-off bundle |
| Cloud Conversational | Live chat; conversation history is the context window. | `PROMPTS.md` §11.4–11.13 + conversation-mode notes |

**Default when uncertain:** Cloud Async. Misclassifying Cloud Async as Local Editor silently loses context; the
reverse only costs bundle overhead. Asymmetric risk → conservative default.

## 4. Surface-don't-decide — the 7 hard stops

STOP and surface to the tech lead. Do not "be helpful" by resolving these yourself. Pushing through any of them
is a methodology violation, even if the code works.

### 4.1 Interface freeze violation
- **Trigger:** the task touches a public contract already implemented (frozen per `SDLC-AGRD.md` §2.1) with no
  ADR cited authorizing the change.
- **Do:** STOP. Note which interface, which ADR, append to worklog, surface.
- **Do NOT:** "improve" the interface — no rename, no return-type tightening, no required field added, no
  deprecated field removed. Frozen means frozen.
- **Worklog:** `HARD STOP — interface freeze: <blueprint-id> public contract touched without ADR. Expected ADR: <id|none>. Surfaced. No code changes made.`

### 4.2 Global lap floor temptation
- **Trigger:** you want to apply a uniform depth target across all blueprints in a lap "for simplicity."
- **Do:** STOP. Re-read `SDLC-AGRD.md` §4.3. The per-blueprint relative floor is the rule: target =
  `admission_depth + laps_since_admission`, capped at 5.
- **Do NOT:** apply a global floor. That was the v3.2 spec bug, explicitly rejected in v3.4.
- **Worklog:** `HARD STOP — global lap floor temptation: considered uniform depth <N> on ring <X>. Correct per-blueprint floor applied.`

### 4.3 Mid-bet lint scope creep
- **Trigger:** mid-bet, you notice a lint check "that would be quick to add."
- **Do:** STOP. Log it for the next cooldown. Lint-scope expansion is a cooldown activity (`SDLC-AGRD.md` §6);
  mid-bet changes invalidate throughput calibration.
- **Worklog:** `DEFERRED — lint gap noticed mid-bet: <description>. Logged for next cooldown.`

### 4.4 Bet kill at 1.5× time-box
- **Trigger:** the bet consumed 1.5× its time-box and is not complete.
- **Do:** STOP. Keep partial progress (interface was already frozen, so partial deepening is never wasted,
  `SDLC-AGRD.md` §4.3 Gap B). Surface for re-scoping. Routine event, not a failure.
- **Do NOT:** silently extend; "just finish it"; conflate with the Milestone 0 kill.
- **Worklog:** `BET KILL — <blueprint-id> <depth> exceeded 1.5× (<elapsed> vs <budget>). Partial: <what>. Re-scoping.`

### 4.5 Milestone 0 kill at 8 weeks
- **Trigger:** Milestone 0 consumed 8 weeks and is incomplete.
- **Do:** STOP. Stop-the-line reassessment, NOT calibrate-forward. The architecture or solo-mode assumption may
  be wrong. Surface immediately.
- **Do NOT:** push to week 9; treat as a routine bet kill; re-baseline the timeline silently.
- **Worklog:** `MILESTONE 0 KILL — 8 weeks, incomplete. Stop-the-line. <state of the 8 blueprints>. Methodology reassessment.`

### 4.6 OD pre-resolution
- **Trigger:** the task trips the trigger of an OD not yet due to resolve (`MEMORY.md` §8 / `SDLC-AGRD.md`
  §8.1), and the "obvious" resolution is right there.
- **Do:** STOP. Surface the decision point; let the tech lead close it with an ADR.
- **Do NOT:** resolve silently in code.
- **Worklog:** `OD TRIGGER — <OD-id> encountered during <task>. Did not resolve. Surfaced for ADR. <one-line shape>.`

  > **Lap-1 widen / OD-02 — corrected (v3.4(3)):** earlier drafts excluded `CORE-16` and `HUB-04` from lap-1
  > widening until OD-02 resolved. **v3.4(3) dropped that exclusion** after verifying OD-02 against the actual
  > `EncrypterInterface` / `HUB-04` contracts — it is expected to land as an internal change under an
  > already-frozen `HUB-04` interface, not an interface change. Do **not** hold CORE-16/HUB-04 out of lap-1
  > widening.

### 4.7 MEMORY.md drift
- **Trigger:** you complete a task and notice `MEMORY.md` is stale.
- **Do:** if it's a §7 current-state field (date, lap count, throughput, matrix snapshot, cooldown/Milestone-0
  status) and you completed the triggering event, you MAY update it directly — but append a worklog entry noting
  the §7 update so the tech lead can audit. Everything else (§1–6, §8–12): surface, do not edit.
- **Do NOT:** rewrite §1–6/§8–12 inline "because it's faster."

## 5. Kill triggers — qualitative difference, not just size

| Dimension | Bet kill (§4.4) | Milestone 0 kill (§4.5) |
|---|---|---|
| Trigger | 1.5× time-box on one bet | 8 weeks, Milestone 0 incomplete |
| Frequency | Routine — expected, multiple per lap | Once-ever in project history |
| Severity | Re-scoping event | Stop-the-line reassessment |
| Questions | This bet's scope | Architecture, solo-mode assumption, or methodology itself |
| After-action | Keep partial progress, re-scope, continue | Do not continue. Tech lead reassesses. |

**The failure pattern:** hitting the Milestone 0 kill at 8 weeks and treating it as a bet kill ("just push
through one more week"). This is the single most damaging agent failure mode. If you feel the urge to push
through, STOP — that urge is the failure mode.

## 6. Hand-off bundle protocol — local ↔ cloud

Cloud agents have no conversation history, no worklog awareness, no MEMORY.md awareness. The bundle IS the
contract. A cloud agent that received an incomplete bundle must surface it, not improvise.

### 6.1 Bundle template (paste into the worklog `Hand-off notes` field)

```text
HAND-OFF BUNDLE — to: <capability class> (<product name if known>)

Task for target agent:
<one-paragraph, self-contained task definition>

Files to read before starting:
- <path> — <why>

Files NOT to touch:
- <path> — <why: frozen interface / OD-02 territory / another agent's in-flight work>

Current state (from MEMORY.md §7, as of <date>):
- Methodology version: <e.g., v3.4(3)>
- Cooldown 0 status: <started|complete|not started>
- Milestone 0 status: <in progress, week N/8 | complete | not started>
- Lap count: <N>
- Last bet: <blueprint-id> <depth>, <result>

ODs/ADRs in play:
- <OD-id>: <status, e.g., "trigger expected — do not resolve, surface">
- <ADR-id>: <relevance, e.g., "cited; interface change authorized">

Shared context block (PROMPTS.md §11.3) with current-state fields filled:
<paste the filled-in block>

Kill triggers active:
- Bet kill at 1.5×: budget = <…>, threshold = <…>
- Milestone 0 kill (if applicable): week <N> of 8

Worklog entries to read:
- Task ID <most recent>
- Task ID <prior>
- Task ID <prior>
```

### 6.2 Scenarios
- **Local Editor → Cloud Async:** bundle mandatory. Cloud Async's first worklog entry must acknowledge receipt
  or surface any missing field.
- **Cloud Async → Local Editor:** the report/PR IS the bundle; local agent still writes a `Hand-off notes`
  field pointing to it.
- **Local Editor → Local Editor (different session):** bundle still recommended — next session has no
  conversation history.
- **Cloud Conversational → any:** transcript is context, but a written worklog bundle is still required.

### 6.3 What does NOT go in the bundle
Generic methodology explanations (the target reads MEMORY.md + this file), full file contents (cite paths), the
originating agent's reasoning trace (only conclusions matter), anything irrelevant to the task.

## 7. Worklog append protocol

`worklog.md` is append-only. Every agent, every task. If you can't find it, ASK — its absence is a red flag.

### 7.1 Template
```text
---
Task ID: <e.g., 4, or 4-a for a subtask>
Agent: <capability class> (<product name>) — e.g., "Local Editor (Cline)"
Task: <one sentence>

Work Log:
- <concrete step 1>
- <concrete step 2>

Stage Summary:
- <key results / decisions / artifacts — cite paths under Architecture/CrossCutting/>

Hand-off notes:
- <for next agent, or "none">
```

### 7.2 Do's and don'ts
- **Do** append at end. Never overwrite, never insert mid-file.
- **Do** cite file paths for every artifact produced.
- **Do** fill `Hand-off notes` even if "none."
- **Don't** summarize/paraphrase prior entries — the log is the source of truth.
- **Don't** omit failures — a "Bet killed at 1.5×" entry calibrates the methodology better than "Bet
  completed."
- **Don't** editorialize ("the spec is wrong" is a surface-to-tech-lead, not a worklog entry).

## 8. OD encounter protocol

Six ODs open (`MEMORY.md` §8). When a task trips a trigger:
1. Identify which OD (match against `Architecture/OPEN-DECISIONS.md`).
2. Check timing (`MEMORY.md` §8): OD-06 during Milestone 0 (forced by CORE-02); OD-02 in Cooldown 1;
   OD-01/03/04/05 keep their existing timing.
3. If timing arrived → surface; tech lead closes with an ADR.
4. If not arrived → surface as pre-resolution attempt; do not implement either way.

**Most common OD failure:** an agent trips OD-02 (post-quantum JWT) during Milestone 0 because "the JWT lib
choice has to be made now." It does not. Surface — deferred to Cooldown 1.

## 9. Interface freeze protocol

Per `SDLC-AGRD.md` §2.1 and `MEMORY.md` §5 rule 1.
1. Check if frozen: a blueprint's public contract freezes at first implementation (depth ≥ 1). Check
   `INDEX.md` admission status and the blueprint's `STRUCTURE-XX.md`.
2. Frozen + ADR cited in task → proceed.
3. Frozen + no ADR → STOP (§4.1).
4. Not frozen → proceed; document the frozen contract in the blueprint's STRUCTURE file; cite the freezing
   commit in the worklog.

Freeze applies to: public method signatures, public field names, public types, wire formats (HTTP
request/response, JSON schemas), config keys. It does NOT apply to: private implementations, internal helpers,
test fixtures (unless the test is itself a public contract).

## 10. MEMORY.md update protocol

| Field | Who may update | When |
|---|---|---|
| §7 "Last updated" date | Any agent completing the triggering event | Milestone-0 blueprint / lap step / cooldown done |
| §7 matrix snapshot | Any agent completing the triggering event | Same |
| §7 throughput | Tech lead only | After Milestone 0; after each lap recalibration |
| §8 OD status | Tech lead only | When an OD resolves (ADR merged) |
| §§1–6, 8–12 | Tech lead only | Durable facts change |
| This file | Tech lead only | Process rules change |

**Audit rule:** if you update §7, you MUST append a worklog entry. An unlogged §7 update is a violation.

## 11. Anti-patterns — look reasonable, but forbidden

| Anti-pattern | Why forbidden | Reference |
|---|---|---|
| "Push through one more week" on Milestone 0 | Milestone 0 kill is qualitatively different | §5, `MEMORY.md` §5 rule 5 |
| Silent OD resolution | Invisible to future agents; ADR thrash | §4.6, `MEMORY.md` §5 rule 6 |
| Mid-bet lint scope creep | Invalidates throughput calibration | §4.3, `MEMORY.md` §5 rule 3 |
| Global lap floor | Was the v3.2 spec bug | §4.2, `MEMORY.md` §5 rule 2 |
| Interface "improvement" mid-task | Frozen means frozen | §4.1, `MEMORY.md` §5 rule 1 |
| Editing MEMORY.md without approval | Curation discipline | §10 |
| Conflating bet kill with Milestone 0 kill | Different in kind | §5 |
| Cloud agent improvising context | Project context is not in training data | §6 |
| Skipping worklog on small tasks | Log is the audit trail | §7 |
| Treating §11 prompts as gospel after v3.5 | §11.14 says recalibrate post-lap-1 | `PROMPTS.md` §11.14 |

## 12. Honest caveats

- This file does **not** promise completeness — after lap-1 data, some rules recalibrate. The §11 table is the
  patterns that have actually bitten, not an exhaustive list.
- It does **not** replace the prompts (`PROMPTS.md` §11) — those encode the methodology operationally; this
  encodes the meta-rules (surface, hand off, log).
- It does **not** replace `MEMORY.md` — state vs. process.
- It does **not** override the spec. If it conflicts with `SDLC-AGRD.md` §§1–10, the spec wins. Surface the
  conflict.

---

### Provenance

Operational playbook synthesized from `Design_Models_Misc/MEMORY_INSTRUCTIONS(1).md` (boot sequence, 7 hard
stops, hand-off/worklog/OD/interface-freeze protocols, anti-patterns) with all `SDLC-AGRD v3.4 §11` references
re-pointed to `PROMPTS.md` §11 and sandbox paths re-pointed to `Architecture/CrossCutting/`. **Correction
applied:** §4.6 lap-1 widen exclusion for CORE-16/HUB-04 updated to reflect v3.4(3), which dropped it.
