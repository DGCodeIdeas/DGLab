# PROMPTS.md — Agent Prompt Library for DGLab / Sovereign Stack

**Purpose:** reusable, tool-agnostic prompts for whatever AI coding agent is in use — VSCode-local
(Cline, Copilot, Cursor, Kilo, Roo, or anything else) or cloud (Jules or similar). None of these
assume a specific tool's syntax — no `@`-mentions, no slash commands, no tool-specific config format.
Paste the relevant block as plain instructions into whatever chat interface is active.

**Read this first, every session, regardless of which prompt below you're using:** point the agent at
`MEMORY.md` before anything else (see §0). An agent that starts cold on this repo without it will
re-derive — or worse, re-decide — things that are already settled.

---

## §0. Session bootstrap — paste this first, always

```
Before doing anything else, read these three files in order and confirm you've absorbed them:
1. MEMORY.md — current project state, settled decisions, active constraints
2. Architecture/INDEX.md — the governance and numbering authority; §1-§4 especially
3. Architecture/OPEN-DECISIONS.md — questions that are deliberately still open; do not
   silently resolve any of these yourself

Then tell me, in a few lines: what team size and SDLC model this project runs on, what the
current primary datastore is, and what depth scale governs blueprint completeness. If you
can't answer all three confidently from what you just read, say so before I give you a task —
don't guess.
```

Why this shape: the three-question check isn't busywork — it's a cheap, fast way to catch an agent
that skimmed instead of read, before it produces work built on a wrong assumption (the MySQL/PostgreSQL
history in this repo is exactly the kind of thing a stale assumption breaks silently).

## §1. Implementing a blueprint to a target depth

Use this for the actual "deepen X to depth N" work a bet consists of.

```
Task: deepen [BLUEPRINT-ID] from its current depth to depth [N], per the depth scale in
SDLC-AGRD.md §4.1.

Before writing any code:
1. Read Architecture/[Tier]/[BLUEPRINT-ID].md in full — its interface contracts are frozen
   (SDLC-AGRD.md §2.1) and must not change. If achieving depth [N] seems to require an
   interface change, stop and tell me instead of proceeding — that needs an ADR, not a PR.
2. Read Architecture/OPEN-DECISIONS.md and check whether any open decision affects this
   blueprint. If one does and it's unresolved, tell me before proceeding.
3. Confirm what depth [N] actually requires for this blueprint type (error paths / observability
   / hardening — see the depth scale) before writing code toward it.

Then implement. Constraints:
- Only this blueprint's scope — no drive-by fixes elsewhere, even if you spot something.
  Flag anything else you notice at the end instead.
- Match the existing code style and namespace conventions already in this package.
- Write or extend tests to the level depth [N] requires (e.g., depth 3 needs error-path tests,
  not just happy-path).
- Show me a diff before committing anything.

When done, tell me: what depth this blueprint is actually at now (it may be less than [N] if
you hit a real blocker — say so plainly, don't round up), and anything you noticed that should
become a new OPEN-DECISIONS.md entry rather than something you decided unilaterally.
```

## §2. Scoped documentation/text fix (the pattern this session used for the MySQL/JSONB cleanup)

```
In [repo/path], [describe the exact leftover/incorrect text and where it came from — e.g.,
"a leftover reference to X from before ADR-YYY changed the decision to Y"].

Task: in [exact file list, or "files matching this pattern"], replace [old text] with
[new text] — matching the phrasing already used correctly in [reference file].

Do not touch:
- [Any file/section that legitimately keeps the old text, and why — e.g., an archived ADR
  documenting the original, superseded decision]
- [Any file where the old term appears in a different, still-valid context]

Scope this to exactly that text fix — no other edits, no reformatting, no touching unrelated
content in the same files. Show me a diff before committing.
```

## §3. Second-reviewer pass on a diff (solo-mode substitute for a human reviewer)

Per `SDLC-AGRD.md` §6, the linter is the primary second reviewer, but it doesn't catch everything —
use this for a semantic pass a human teammate would normally do.

```
Review this diff [paste it, or point at the branch/PR] as if you were a second engineer on
this team, not the one who wrote it. Specifically check:

1. Does it touch any interface that Architecture/INDEX.md or the relevant blueprint marks as
   frozen (SDLC-AGRD.md §2.1)? If so, is there a cited ADR, or should this be flagged as a
   Soft-Freeze violation?
2. Does it match the target depth it claims to reach (SDLC-AGRD.md §4.1) — e.g., if this is
   claimed as "depth 4," is observability actually wired, not just error handling?
3. Does it introduce any of the ID-mislabeling or naming-collision patterns already found and
   fixed elsewhere in this repo (Architecture/CrossCutting or the critique history) — wrong
   component IDs, reused component names across tiers, stale cross-references?
4. Any claim in the diff's comments or commit message stated as fact that should actually be
   "provisional, unverified" per Governance Rule 2 (no benchmark/timeline claim without a
   stated method)?

Give me a plain list: real problems, things worth a second look, and nothing found in a given
category (don't pad the list to seem thorough — say clearly if a category is clean).
```

## §4. Bootstrapping a fresh cloud-agent task (Jules or similar, PR-based workflow)

Cloud agents typically work from a repo snapshot and produce a PR rather than running interactively in
a terminal you're watching — so the prompt needs to front-load what a local agent could otherwise ask
about mid-task.

```
Repo: DGCodeIdeas/DGLab. Before starting, read MEMORY.md and Architecture/INDEX.md at the repo
root — do not rely on any prior knowledge of this repo's structure or decisions, they may be
stale.

Task: [specific, bounded task — see §1 or §2 templates above for the actual scoping].

Constraints specific to a PR-based workflow:
- Open the PR against a new branch, never directly to main.
- The repo's CI will run Architecture/Verification/lint/run.php against your changes — it
  checks cross-references against INDEX.md and will fail on any ID mismatch. If you're
  genuinely uncertain whether a reference is correct, say so in the PR description rather
  than guessing and letting CI catch it.
- Do not resolve anything listed in Architecture/OPEN-DECISIONS.md as part of this task unless
  explicitly asked to — those are deliberately left open.
- PR description should state: what depth (if applicable) the changed blueprint(s) are now at,
  and anything encountered that should become a new OPEN-DECISIONS.md entry.
```

## §5. Writing a new ADR

```
Task: draft a new ADR for [decision], following the format already used in Architecture/ADRs/
(check ADR-013 for a recent, well-structured example — it documents a reversal of an earlier
decision, which is useful structure if this is also revisiting something).

Include: Status (Proposed, not Accepted, until I say otherwise), Context, Decision,
Consequences (both positive and negative — an ADR with no stated negative consequences hasn't
looked hard enough), and Alternatives Considered.

Check Architecture/OPEN-DECISIONS.md first — if this decision is already tracked there, the
ADR should reference and close that entry, not create a parallel, disconnected record.

Do not mark this ADR "Accepted" — that's a decision for me to make, not something to imply by
the ADR's existence.
```

## §6. When an agent's answer conflicts with something already decided

This isn't a prompt template so much as a standing instruction worth keeping visible in whatever
system/custom-instructions field the current tool supports, since it's the single most common failure
mode across sessions and tools:

```
If anything you're about to tell me contradicts MEMORY.md, an ADR marked Accepted, or
Architecture/INDEX.md, say so explicitly and ask before proceeding — don't silently work
around the conflict, and don't silently defer to your own prior reasoning over the repo's
stated decisions. The repo is the source of truth; you are not.
```
