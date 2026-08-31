# MEMORY_INSTRUCTIONS.md — How to maintain MEMORY.md

This governs `MEMORY.md` itself: when to read it, when and how to update it, and what does and doesn't
belong in it. `MEMORY.md` is a summary an agent orients from in seconds, not a second copy of the
architecture docs — if it grows to the point where reading it takes as long as reading `INDEX.md`
directly, it has failed at its one job.

## When to read it

**Every session, before any task, no exceptions.** This is `PROMPTS.md` §0's bootstrap prompt — it's
not optional context, it's the first thing that happens. An agent that skips this and produces work
built on a stale or wrong assumption (the MySQL/PostgreSQL history in this repo is the concrete example
of how expensive that gets) has cost more time than reading the file would have.

## When to update it

**During cooldowns, never during bet weeks.** This directly mirrors `SDLC-AGRD.md` §6's lint-expansion
rule: if memory maintenance competes with actual blueprint work during a bet, it loses, silently, and
the file goes stale exactly when it's most likely to matter (mid-implementation, when facts are
changing). If something discovered mid-bet clearly needs to update `MEMORY.md`, note it inline in that
bet's summary and apply the actual edit at the next cooldown — don't stop bet work to fix the memory
file the moment the need is noticed.

**Trigger conditions, not a schedule:**
- An ADR moves from Proposed to Accepted (or gets reversed, like `ADR-013` did to `ADR-007`).
- `SDLC-AGRD.md`'s version bumps (a new methodology fix that changes how work is scoped).
- A component's real implementation status changes (something moves from unbuilt to built, or a
  previously-assumed-built thing turns out not to be — verify, don't assume, before writing this down).
- A naming collision gets resolved (moved from "known collision" to "fixed," or vice versa if a fix
  gets reverted).
- An Open Decision resolves and its outcome is settled enough to state as fact rather than track as
  open.

## What belongs in MEMORY.md vs. elsewhere

| Content | Belongs in |
|---|---|
| A settled fact everyone needs before starting any task (team size, datastore, ID policy) | `MEMORY.md` |
| A deliberately unresolved question | `Architecture/OPEN-DECISIONS.md`, **not** `MEMORY.md` |
| A formal decision with alternatives considered and consequences | An `ADR` — `MEMORY.md` links to it and states the one-line outcome, never reproduces the full reasoning |
| Implementation-level detail about how one blueprint works | The blueprint's own file — `MEMORY.md` doesn't duplicate blueprint content |
| A pattern of recurring bugs worth an agent's general caution | `MEMORY.md`, briefly — the point is calibrating suspicion, not cataloging every instance |

**Rule of thumb: if it's the kind of thing that would derail a task if an agent didn't know it, and
it's genuinely settled (not open, not implementation detail), it belongs here. Otherwise it doesn't.**

## Update discipline

- **No claim without a citable source.** Any edit that states a fact ("the datastore is X," "component
  Y is built") must be traceable to a specific ADR, commit, or direct verification — the same
  Governance Rule 2 discipline (`INDEX.md` §7) that governs performance claims elsewhere in this repo
  applies to memory claims too. "I believe this is still true" is not sufficient to write it down as
  settled.
- **Verify before writing, don't trust the existing file over a fresh check.** If `MEMORY.md` says
  something and you have a reason to doubt it (it's been a while, something seems inconsistent), check
  the live source before either trusting or overwriting the claim. Both silent trust and silent
  overwrite are failure modes — the correct move is to check and then update accurately either way.
- **State the commit or date an update is based on**, inline, briefly — not a full audit trail, just
  enough that the next reader can tell how fresh a given section is.

## Size and pruning

Once a lap or milestone completes and its details stop being load-bearing for *new* work (e.g., "what
Milestone 0 built" stops mattering once several laps have deepened past it), move that detail out of
`MEMORY.md` into the relevant historical doc (the SDLC doc's own changelog, or a dedicated archive) and
leave only what's still actively relevant. A memory file that only ever grows will eventually be worse
than no memory file, because it trains whoever reads it to skim instead of absorb.

## What NOT to put here, ever

- Live credentials, tokens, or secrets of any kind — see `MEMORY.md`'s own security note for what to do
  if one shows up in a prompt instead.
- Anything from `Architecture/OPEN-DECISIONS.md` restated as if it were settled. If an update to this
  file would resolve an open decision by assertion rather than by an actual decision being made, that's
  exactly the failure mode `Governance Rule 9` (`INDEX.md` §7) exists to prevent — don't do it here
  either, just because this file feels lower-stakes than the architecture docs.
