# PROMPTS — Operating Instructions for AI Agents (SDLC-AGRD Companion Module)

**Status:** Canonical companion to `SDLC-AGRD.md`. This module was split out of `SDLC-AGRD-v3.4(2).md` §11 so
the methodology body stays focused and the agent-operating instructions stay in one place. Every agent the
solo tech lead drives — regardless of product name — reads this plus `MEMORY.md` before any task.

**Sandbox note:** the source drafts referenced `/home/z/my-project/*` paths. In the live repo those map to
`Architecture/CrossCutting/*` (MEMORY.md, worklog.md, this file). All references below use the live-repo paths.

---

## 11.1 Why this module exists, and what it is not

This module **operationalizes** `SDLC-AGRD.md` for the varied AI agents the solo tech lead actually runs. It is
**not** a methodology statement — that is `SDLC-AGRD.md`. It is not the project's curated entry-point — that is
`MEMORY.md`. It is the *how to drive an agent against this project without silent drift* layer.

It is calibrated to `SDLC-AGRD` v3.4(3) rules. When v3.5 lands (after lap-1 data, per `SDLC-AGRD.md` §10), this
module needs re-calibration too.

## 11.2 Agent taxonomy — capability classes, not product names

This module is **agent-agnostic**. The rows below are **capability classes** — the requirement. The "Examples"
column lists product names (Cline, Zoo, Kilo, Aider, Jules, Devin, Claude web, Codex, Copilot Workspace, etc.)
purely as **illustrative, not prescriptive**. New agents slot in by capability match, not by being listed here.
Classify a new agent by capability class and use the prompts that match that class; do not wait for this table
to be updated.

Not all capability classes fit all tasks. Picking the wrong class for a task is a real failure mode — a Cloud
Async Agent on a lint-scope expansion (which requires deep iterative testing against `run.php`) will produce
something that looks plausible but doesn't actually catch drift; a Local Editor Agent on a dependency-graph
analysis (which benefits from a fresh-clone perspective and async autonomy) will take longer than a Cloud Async
Agent for the same output.

| Capability class | Examples (illustrative) | Strengths in solo-mode DGLab | Use for | Do NOT use for |
|---|---|---|---|---|
| **Local Editor Agent** — VSCode-embedded, full repo context, iterative editor | Cline, Zoo (formerly Roo Code), Kilo, Continue.dev (deeper modes) | Full repo context, iterative edits, runs CLI, reads files on demand | Bet execution (depth 1→5), lint scope expansion, ADR implementation, hand-off verification | Fresh-perspective review of v3.x docs (too close to repo) |
| **Local CLI Agent** — terminal, git-aware, atomic commits | Aider, GitHub Copilot CLI | Atomic commits with messages, conversational, strong on small-surface deepening | Deepening bets where many small commits matter, refactors within a single blueprint | Wide-surface widen steps (better with Local Editor's integration) |
| **Local Inline Agent** — lightweight completion, single-file | Continue.dev inline, Copilot inline, Tabnine | Inline completions, quick Q&A | Quick struct lookups, docstring generation, small mechanical edits | Anything requiring multi-file reasoning |
| **Cloud PR Agent** — scoped task brief → PR | GitHub Copilot Workspace, Sweep, CodeRabbit | Takes a task brief, produces a PR | Widen-step stubs (mechanical depth-1 work), test generation, doc cleanup | Anything touching frozen interfaces, anything requiring judgment on ODs |
| **Cloud Async Agent** — fresh-clone, longer-horizon, autonomous | Jules, Devin, Factory | Scoped brief → PR, fresh clone, more agency than Cloud PR | Dependency analyses vs. `INDEX.md` §5.2, isolated lint-check implementation, scaffold generation | Lint expansion (no iterative test loop), anything requiring worklog awareness mid-task |
| **Cloud Conversational Agent** — chat, no repo access | Claude web, ChatGPT/Codex, Gemini web | Gap-hunting review, OD analysis, ADR drafting, doc review | §11.12 review passes, OD pre-resolution analysis, cross-document consistency checks, second-opinion on a PR | Direct repo edits without tech-lead review |

**Classification rule for a new agent:** match on capability, not on vendor. The two axes that matter are (a)
does the agent have full repo context (Local classes) or run against a fresh clone / no repo (Cloud classes), and
(b) does it operate iteratively with the tech lead in the loop (Local Editor, Local CLI, Cloud Conversational)
or autonomously to a PR (Cloud PR, Cloud Async). When in doubt, classify conservatively — treat an unknown
agent as Cloud Async (lowest context, highest autonomy) until proven otherwise, because the hand-off bundle
discipline (§11.13) is the safety net for exactly that case.

**The cloud-vs-local split that matters most:** cloud agents run against a fresh clone or no repo, with no
worklog awareness and no conversation history. They cannot see prior agent invocations. Local agents inherit the
worklog and the in-editor state. **Any task handed to a cloud agent requires an explicit hand-off bundle
(§11.13).** This is not optional politeness; it is the contract that keeps cloud-agent work inside the §2.1
interface-freeze discipline.

## 11.3 The shared context block — paste verbatim before any task-specific prompt

Every prompt below assumes TWO things have been delivered to the agent first: (a) **MEMORY.md**
(`Architecture/CrossCutting/MEMORY.md`), read end-to-end; (b) the **shared context block** below, with
current-state fields filled in. Without both, the agent will optimize for the locally visible goal and silently
violate one of the global rules. With both, the rules are in the agent's context window alongside the task.

**Step 0 — read MEMORY.md first.** Non-negotiable, regardless of capability class. If MEMORY.md is missing or
stale, ASK before proceeding — its absence is a red flag, not a permission to skip orientation. Cloud agents in
particular: MEMORY.md must be in the hand-off bundle (§11.13) since you have no other way to see it.

```
SHARED CONTEXT — DGLab Wheel SDLC, solo tech lead mode

You are assisting one solo tech lead (no second engineer) on the DGLab Wheel project
(96 blueprints, 6 rings: Core/Hub/Thick Spokes/Inner Rim/Thin Spokes/Outer Rim).
Methodology: SDLC-AGRD v3.4(3) Spiral Deepening — vertical deepening of horizontal slices,
NOT horizontal completion of vertical rings.

CURRENT STATE (fill in before sending):
- Lap: <N>
- Cooldown 0: <complete | in progress>
- Milestone 0: <complete at W=<weeks> | in progress, week <W>>
- Throughput (if Milestone 0 complete): <N/W = X blueprints/week, build-only>
- OD-02 status: <resolved in Cooldown 1 | unresolved>
- OD-06 status: <resolved during Milestone 0 via ADR-010 | blocked>
- Matrix snapshot: <paste current depth-per-blueprint table from worklog>

RULES THAT APPLY TO EVERY TASK:
1. Interface freeze (SDLC-AGRD §2.1): a blueprint's public contract freezes at first
   implementation. Changing a frozen interface is ADR-gated. If your task touches a frozen
   interface, STOP and ask for the ADR before proceeding.
2. Per-blueprint relative floor (§4.3): each blueprint's target depth is its own admission
   depth + laps since its admission, capped at 5. Do NOT apply a global lap floor.
3. Lint is the second reviewer (§6): lint-scope expansion happens during cooldowns, NEVER
   during bet weeks. If you find a lint gap during a bet, log it for the next cooldown.
4. Bet kill trigger (§4.2): if a bet exceeds 1.5× its time-box, stop. Keep what's done — the
   interface was already frozen at admission, so partial deepening is never wasted.
5. Milestone 0 kill trigger (§4): if Milestone 0 exceeds 8 weeks, that is a stop-the-line
   signal. Surface immediately. Do not push to week 9.
6. Worklog (§11.13): every agent appends to Architecture/CrossCutting/worklog.md. Never
   overwrite prior sections. If you cannot find the worklog, ASK before proceeding.
7. ODs (§8.1): OD-02 resolves in Cooldown 1 (NOT Milestone 0, NOT Cooldown 0). OD-06
   resolves during Milestone 0. Do not pre-resolve either — surface the decision point.
8. Lap-1 widen constraint — REMOVED in v3.4(3). CORE-16 and HUB-04 are NOT excluded from
   lap-1 widening; OD-02 was verified against their actual interfaces and is expected to land
   as an internal implementation change under an already-frozen HUB-04 contract, not an
   interface change. (Earlier v3.4 drafts excluded them — that guidance is superseded. Do not
   re-add the exclusion.)

If any of these rules conflict with your task instructions, the rules win. Ask before proceeding.
```

The block is verbose by design. Trimming it to "be more efficient" is the most common way an agent introduces
silent drift — every rule exists because some prior version of this project violated it.

## 11.4 Cooldown 0 prompts — content contract freezing

Cooldown 0 (`SDLC-AGRD.md` §3) freezes three contracts before Milestone 0 starts: `ESPOKE-05` wireframe,
`HUB-26` theme tokens, `HUB-13` string keys. The prompt below is for one of the three; the other two follow the
same shape with their spec swapped in.

```
TASK: Freeze ESPOKE-05 UI wireframe under Cooldown 0 (§3)
AGENT: any Local Editor Agent (§11.2) — e.g., Cline, Zoo, Kilo
PRECONDITION: §11.3 step 0 (MEMORY.md) and shared context block delivered. Cooldown 0 has
started; Milestone 0 has not.

GOAL: produce a frozen wireframe artifact at Architecture/Cooldown0/ESPOKE-05-wireframe.md
that the marketer can write copy against for the next 6+ months without rework.

STEPS:
1. Read ESPOKE-05's STRUCTURE-XX spec. Identify every user-visible surface.
2. For each surface, produce a wireframe (ASCII or Mermaid + dimension notes). Enumerate the
   copy slots (headline, button labels, error strings, empty-state copy, tooltip text, alt-text).
3. Cross-reference HUB-13's string-key taxonomy. Every copy slot MUST map to a real HUB-13 key.
   If a needed key doesn't exist, surface it as a Cooldown 0 scope expansion — do not invent one.
4. Cross-reference HUB-26's theme tokens. Every visual slot MUST map to a real HUB-26 token.
5. Output the wireframe document. Tag its header: "Frozen under Cooldown 0 ADR-gate
   (SDLC-AGRD v3.4 §3). Changes require a new ADR."
6. Do NOT begin implementation. Cooldown 0 freezes contracts, not code.

ACCEPTANCE CRITERIA: every copy slot has a HUB-13 key citation; every visual slot has a HUB-26
token citation; header carries the §3 freeze tag; no source files modified; worklog entry appended.

ESCALATION: if HUB-13 or HUB-26 lacks a needed entry, STOP — do not invent a key/token, do not
silently extend either taxonomy.
```

The `HUB-26` and `HUB-13` versions swap the spec reference and adjust step 2 (produce the token/key taxonomy
rather than a wireframe consuming it). Everything else — freeze tag, acceptance, escalation — is identical.

## 11.5 Milestone 0 prompts — walking skeleton, kill-trigger-aware

Milestone 0 (§4) is the calibration instrument. Its 8-blueprint scope and 8-week ceiling are the two numbers
everything downstream depends on. The prompt below is for the first blueprint (`CORE-02`); the other seven follow
the same shape with their spec swapped in. The kill-trigger language is non-negotiable.

```
TASK: Build CORE-02 (DI Container) at depth 1-2 for Milestone 0 (§4)
AGENT: Local Editor or Local CLI Agent
PRECONDITION: §11.3 delivered. Cooldown 0 complete.

GOAL: a minimal but real CORE-02 whose interface freezes at first implementation (§2.1),
demonstrating the walking-skeleton Pulse round trip end-to-end.

STEPS:
1. Read CORE-02's STRUCTURE-XX spec. Implement the public interface only — no depth beyond
   happy path (depth 2) for Milestone 0.
2. Freeze the public contract the moment it compiles. Any later change to it is ADR-gated (§2.1).
3. Wire it into the Exemplar One round trip: Outer Rim -> Inner Rim (BRIDGE-01) -> Inner Spoke
   (ISPOKE-09) -> Hub (HUB-01) -> Core (CORE-02). The success criterion is a real HTTP request
   making this trace, not a diagram.
4. Append a worklog entry with the depth reached per blueprint.

KILL TRIGGER: if Milestone 0 exceeds 8 weeks, STOP and surface to tech lead — do not push to
week 9. This is a stop-the-line signal, not a calibrate-forward signal.
```

## 11.6 Lap widen prompts — admit next-most-depended-upon blueprint

```
TASK: Lap k widen — admit next-most-depended-upon unadmitted blueprint per ring
AGENT: Cloud PR or Local CLI Agent (mechanical depth-1 stubs)
PRECONDITION: §11.3 delivered; current lap = k; matrix snapshot in context.

GOAL: for each ring that still has unadmitted blueprints, admit its next-most-depended-upon
not-yet-touched blueprint at depth 1. Decide "next-most-depended-upon" against INDEX.md §5.2
real edges, not arbitrarily. A fully-widened ring contributes no admission this lap (expected).

STEPS:
1. For each ring, consult INDEX.md §5.2. Pick the unadmitted blueprint most depended upon by
   the already-touched set.
2. Create a depth-1 stub: interface present, no real logic, freeze the contract immediately.
3. If a ring's §5.2 coverage is exhausted (no edge data for any remaining blueprint in that
   ring), surface it — extend that ring's §5.2 coverage first (trigger: 50% of the covered set
   already admitted; see SDLC-AGRD §9).
4. Append a worklog entry: ring, blueprint admitted, admit depth.
```

## 11.7 Lap deepen prompts — per-blueprint relative floor

```
TASK: Lap k deepen — bring below-floor blueprints to their per-blueprint target
AGENT: Local Editor Agent (bet execution, depth 1->5)
PRECONDITION: §11.3 delivered.

GOAL: for each blueprint below its own relative floor (admission depth + laps since admission,
capped at 5), deepen it. Scope each deepening bet to a single named ring and target (Gap A).

STEPS:
1. From the matrix snapshot, list every blueprint below its per-blueprint floor.
2. For each, deepen only — do NOT change the frozen public interface. If you find the interface
   must change, STOP and raise an ADR (§2.1).
3. Per target depth, apply the §11.9 per-depth-level checklist.
4. On hitting 1.5× the bet time-box, STOP, keep what's done, surface for re-scoping (Gap B).

Do NOT apply a global lap floor — that was the v3.2 spec bug.
```

## 11.8 Cooldown prompts — lint-scope expansion and content Ring Lock

```
TASK: Lap k cooldown — expand lint scope + reconcile content contracts
AGENT: Local Editor + Cloud Conversational (review pass)
PRECONDITION: §11.3 delivered; bet weeks complete.

GOAL: (a) promote one lint check from the §6 expansion table to active; (b) run the marketer/
media content Ring Lock — content checked against its frozen contracts (ESPOKE-05 / HUB-26 /
HUB-13).

STEPS:
1. Pick one not-yet-active lint check (Pulse 6-tuple consistency, naming drift, soft-freeze
   auto-reject, or blueprint-fidelity structural diff). Implement/test it against run.php.
   Lint expansion NEVER happens during bet weeks.
2. Run a content Ring Lock: diff current content against the Cooldown-0-frozen contracts.
   Report drift.
3. Triage OD backlog (OPEN-DECISIONS.md); assign/update owners.
4. Append worklog: lint check promoted, drift found, ODs touched.
```

## 11.9 Per-depth-level prompts — compact reference

| Depth | Prompt focus |
|---|---|
| 1 | Stub: interface exists, no logic. Freeze contract. |
| 2 | Happy path end-to-end works. |
| 3 | Error/failure modes handled (not just happy case). |
| 4 | Logging, metrics, audit wired per blueprint CI criteria. |
| 5 | Performance targets met, security-reviewed. |
| 6 | At-scale load-tested against real targets. |

Use these as the per-blueprint acceptance checklist inside §11.7 deepen prompts.

## 11.10 ADR-gated event prompts — interface freeze violations and OD resolution

```
TASK: resolve an interface-freeze violation or an OD
AGENT: Cloud Conversational (draft) + tech lead (decide)
PRECONDITION: a frozen interface change is proposed, OR an OD needs closing.

GOAL: produce an ADR (or OD-resolution) under the ADR-gate discipline, not a casual edit.

STEPS:
1. If a frozen interface must change: draft an ADR citing the blueprint, the change, the
   reason, and the impact on dependents. Do NOT edit the interface until the ADR is accepted.
2. If an OD closes (e.g., OD-02 in Cooldown 1): record the decision, the chosen option, the
   rejected options, and the downstream blueprints affected. Update OPEN-DECISIONS.md.
3. Surface to tech lead for the decision. Cloud agents: include this in the hand-off bundle.
```

## 11.11 Bet-kill and Milestone-0-kill prompts — when to stop, not push

```
TASK: evaluate kill triggers
AGENT: any (usually the tech lead, informed by agent reports)

Bet kill (§4.2): if a bet exceeds 1.5× its time-box, STOP. Keep reached depth (never wasted —
interface frozen at admission). Re-scope: same ring next slot, or defer to a later lap. Routine
event, not a methodology failure.

Milestone 0 kill (§4): if Milestone 0 exceeds 8 weeks, STOP-THE-LINE. Reassess the architecture
or the solo-mode assumption. Do not fold into §5's formula as a normal data point.
```

## 11.12 Review-pass prompts — the gap-hunting that found real issues across v3.x

```
TASK: gap-hunting review pass on a v3.x document
AGENT: Cloud Conversational Agent (fresh perspective; no repo access needed)

GOAL: find spec contradictions, self-inconsistent schedules, mislabeled references, undefined
terms, false-precision claims.

STEPS:
1. Read the target doc end-to-end. Cross-check every number against its stated derivation.
2. Specifically hunt: (a) global floors that contradict per-blueprint admissions; (b) week
   counts presented as fact before Milestone 0; (c) capability claims the linter doesn't
   actually implement; (d) references to blueprints/ADRs that don't exist in INDEX.md.
3. Report each finding with file + line + the contradiction, not a vague "looks off."
```

## 11.13 Worklog discipline and cloud-local hand-off

- **Every agent appends to `Architecture/CrossCutting/worklog.md`.** Never overwrite prior sections. If the
  worklog is missing, ASK before proceeding.
- Each entry: date, lap/cooldown, agent class + product, blueprints touched, depth reached, OD/lint items
  surfaced, hand-off notes.
- **Cloud agents** receive a hand-off bundle containing: MEMORY.md, the shared context block (current state
  filled), the specific task brief, and the relevant STRUCTURE-XX spec(s). They return: the PR plus a
  worklog-ready summary the tech lead appends. The bundle is the safety net that keeps cloud work inside the
  §2.1 interface-freeze discipline.

## 11.14 What this module deliberately does NOT promise

- It does not make blueprint-fidelity semantic diffing automatic (see `SDLC-AGRD.md` §6 honest caveat).
- It does not substitute for the tech lead's OD decisions — it surfaces them.
- It does not guarantee a timeline — every week-count is a placeholder until Milestone 0 measures `W`.
- It does not auto-classify a never-seen agent; classify conservatively (Cloud Async) until proven.

---

### Anti-drift bootstrap (for the tech lead)

1. Before any agent task: deliver MEMORY.md + the §11.3 shared context block (current state filled).
2. Pick the agent by capability class (§11.2), not by product habit.
3. Cloud agents always get a hand-off bundle (§11.13); their output never merges without tech-lead review.
4. Lint expansion is cooldown work only (§6). Bet weeks deepen; cooldowns expand guardrails.
5. If an agent cites a global lap floor, a lap-1 exclusion for CORE-16/HUB-04, or a CI lint that checks Pulse
   6-tuples today — it is reading a stale draft. Correct it to v3.4(3).

### Provenance

Synthesized from `Design_Models_Misc/SDLC-AGRD-v3.4(2).md` §11 (PROMPTS module). **Correction applied:** the
§11.3 shared-context Rule 8 (lap-1 widen exclusion for `CORE-16`/`HUB-04`) was superseded by `v3.4(3)`, which
dropped the exclusion after verifying OD-02 against the actual interfaces — this file's Rule 8 reflects the
correction. The methodology body this module serves is `SDLC-AGRD.md`; the agent entry-point is `MEMORY.md`.
