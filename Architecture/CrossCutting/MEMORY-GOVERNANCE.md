# MEMORY-GOVERNANCE.md — Explicit Memory Policy & MEMORY.md Maintenance

**Status:** Canonical — governs (a) how explicit cross-session memory is stored, updated, and retired, and
(b) how `MEMORY.md` itself is maintained. Distinct from `MEMORY_INSTRUCTIONS.md` (the per-task agent
playbook) and `MEMORY.md` (the curated state file).

---

# Part A — Explicit Memory (cross-session instructions)

## A.1 Two kinds of memory

| Type | What it is | How it works | Who controls it |
|---|---|---|---|
| **Dream Memory** | Auto-consolidated conversation summaries | The platform compresses conversations and stores patterns | Automatic — you don't manage this |
| **Explicit Memory** (`memory_instruction`) | Short standing instructions you deliberately save | You say "remember that…" and the agent stores a concise instruction | You — explicit request only |

This document governs **Explicit Memory only**. Dream Memory is passive context; Explicit Memory is active
directive. Explicit Memory is the only thing that travels with you across platforms — Claude does not know what
Kimi knows; Cline does not know what Jules knows.

## A.2 When to store Explicit Memory

Store only when you **explicitly** tell an agent to remember something. A fact merely mentioned is NOT a memory
request.

**Valid triggers:** "Remember that…", "Store this…", "Note that…", "Don't forget that…", "Add to memory…",
"Keep this in mind…" (and equivalents in other languages). If uncertain whether the user asked, ask — never
assume.

**What to store:** project conventions, personal preferences, domain facts that affect output, workflow habits,
tool preferences.
**What NOT to store:** anything about minors; race/ethnicity/religion (unless clearly project-relevant and
explicit); criminal history; precise location (addresses/coordinates); political affiliations; health/medical
info; temporary state (Dream Memory handles it); conversation/file contents (Dream Memory + attachments).

## A.3 Format and constraints

| Constraint | Rule |
|---|---|
| Length | ≤ 500 characters per instruction |
| Language | Same as the user's request |
| Form | Single concise instruction — one fact, one rule, one preference |
| Scope | Project-relevant only |
| Count | Maximum 50 instructions total across all sessions |

**Good:** "Generate PHPUnit tests alongside every implementation task."
**Bad (too long, mixes facts):** one instruction bundling tool choice + comment style + pre-merge check.
**Bad (personal):** "I have diabetes." — prohibited.

## A.4 Management operations

- **Add:** verify allowed → distill to ≤500 chars → store in request language → confirm "Added memory #[N]: …".
  Never say "I'll remember" without actually storing.
- **Replace:** when corrected or when reality changes (e.g., old "PostgreSQL" → new "MySQL 8"). Prefer replace
  over add to avoid duplicates.
- **Remove:** on explicit "forget/delete", or when no longer relevant. For full reset, confirm before deleting
  all content — irreversible.

## A.5 Project-specific memory inventory (suggested)

These are canonical for DGLab. Store them explicitly — they are NOT auto-stored.

| # | Instruction | Why |
|---|---|---|
| 1 | "DGLab uses MySQL 8 (InnoDB) as primary datastore, not PostgreSQL. ADR-013 is canonical." | Prevents PostgreSQL hallucinations |
| 2 | "ULID (CHAR(26)) is the canonical cross-service PK format." | Prevents UUID/int drift |
| 3 | "HUB-31 (Real-Time Analytics) is Proposed (ADR-011), not Accepted. Never depend on it." | Prevents phantom dependencies |
| 4 | "Team is 1 tech lead (solo, AI-augmented), 1 marketer, 1 media. No 3-engineer assumptions." | Prevents parallel-bet hallucinations |
| 5 | "AGRD v3.4(3) Spiral Deepening is canonical SDLC. Milestone 0 first, then calibration." | Prevents timeline fiction |
| 6 | "Frozen interfaces (SDLC-AGRD §2.1) require an ADR to change. No casual edits." | Prevents interface drift |
| 7 | "Canonical source hierarchy: INDEX.md > OPEN-DECISIONS.md > ADRs > CrossCutting > Blueprints > Code." | Prevents source confusion |
| 8 | "The 'Sovereign' prefix has known collisions — check INDEX.md before using it." | Prevents naming collisions |
| 9 | "Anvil's install.sh breaks Xubuntu DNS. Use NetworkManager dnsmasq plugin, not system dnsmasq on port 53." | Prevents repeated DNS breakage |
| 10 | "Every agent output must cite canonical file + section. No uncited architectural claims." | Prevents hallucinated authority |

## A.6 Cross-session context rules

- **Same session:** full conversation context.
- **Across sessions, same platform:** Dream Memory consolidation.
- **Across platforms:** NOTHING. Explicit Memory is the only cross-platform bridge.

**Handoff gap:** canonical files are the only shared state; Explicit Memory is second-best; conversation history
is NOT shared state. Use the `MEMORY_INSTRUCTIONS.md` §6 hand-off bundle when switching agents mid-task.

## A.7 Verification & hygiene

- Check stored instructions every cooldown (2 weeks): "Show me my stored memory instructions."
- Hygiene: prefer replace over add (duplicates); remove stale; enforce ≤500 chars; reject prohibited content;
  when count nears 50, ask which to drop.
- **Emergency (suspected bad memory):** stop the session → request the list → identify → remove/replace →
  restart with corrected state. Do NOT try to "override" a bad memory by repeating the correct fact in chat —
  fix the stored instruction.

---

# Part B — Maintaining MEMORY.md itself

This part governs `MEMORY.md` (the curated state file), not explicit memory.

## B.1 When to read it

**Every session, before any task, no exceptions.** It is the bootstrap read (`MEMORY_INSTRUCTIONS.md` §1), not
optional context. An agent that skips it and builds on a stale/wrong assumption — the MySQL/PostgreSQL history
here is the concrete, expensive example — costs more than reading the file.

## B.2 When to update it

**During cooldowns, never during bet weeks** (mirrors `SDLC-AGRD.md` §6's lint-expansion rule). If memory
maintenance competes with blueprint work mid-bet, it loses silently and goes stale exactly when it matters.
Note the need inline in that bet's summary; apply the edit at the next cooldown.

**Trigger conditions (not a schedule):** an ADR moves Proposed→Accepted (or reverses, like ADR-013→ADR-007);
the SDLC version bumps; a component's real implementation status changes (verify, don't assume); a naming
collision resolves; an OD resolves and its outcome is settled enough to state as fact.

## B.3 What belongs in MEMORY.md vs. elsewhere

| Content | Belongs in |
|---|---|
| Settled fact everyone needs before any task (team, datastore, ID policy) | `MEMORY.md` |
| Deliberately unresolved question | `Architecture/OPEN-DECISIONS.md`, **not** `MEMORY.md` |
| Formal decision with alternatives + consequences | An `ADR` (MEMORY.md states the one-line outcome, links to it) |
| Implementation detail of one blueprint | That blueprint's file |
| Recurring-bug pattern worth general caution | `MEMORY.md`, briefly |

**Rule of thumb:** if not knowing it would derail a task, and it's genuinely settled (not open, not
implementation detail), it belongs in `MEMORY.md`.

## B.4 Update discipline

- **No claim without a citable source** — traceable to an ADR, commit, or direct verification (Governance Rule
  2 discipline, `INDEX.md` §7). "I believe this is still true" is insufficient.
- **Verify before writing** — don't trust the existing file over a fresh check. Both silent trust and silent
  overwrite are failure modes; check, then update accurately.
- **State the commit/date** an update is based on, briefly, so the next reader can judge freshness.

## B.5 Size and pruning

Once a lap/milestone completes and its details stop being load-bearing for *new* work, move that detail out of
`MEMORY.md` into the relevant historical doc (the SDLC changelog or an archive); leave only what's still
actively relevant. A memory file that only grows trains readers to skim instead of absorb.

## B.6 What NOT to put here, ever

- Live credentials/tokens/secrets (see `MEMORY.md` security note).
- Anything from `OPEN-DECISIONS.md` restated as if settled — resolving an open decision by assertion here is the
  Governance Rule 9 failure mode; don't do it just because this file feels lower-stakes.

---

### Provenance

Part A synthesized from `Design_Models_Misc/MEMORY_INSTRUCTIONS.md` (canonical explicit-memory policy: Dream vs
Explicit, store/replace/remove, project inventory, cross-session rules, hygiene, emergency). Part B synthesized
from `Design_Models_Misc/MEMORY_INSTRUCTIONS(2).md` ("How to maintain MEMORY.md": read/update triggers, what
belongs, update discipline, size/pruning, exclusions). Sandbox references re-pointed to the live-repo
`Architecture/CrossCutting/` paths; source-hierarchy citations corrected to `MEMORY.md` §10.
