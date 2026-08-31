# MEMORY.md — DGLab / Sovereign Stack

**Read this before doing anything else in this repo.** This is settled state, not a place to reason
from scratch. If you find something here that contradicts the actual repo, the repo wins — flag the
discrepancy, don't silently trust this file over live source (see `MEMORY_INSTRUCTIONS.md`).

---

## Team

**One tech lead, solo, AI-agent-augmented** (Cline/Jules/other coding agents) — not three engineers.
Every SDLC estimate and gate in `SDLC-AGRD.md` is sized for this. Plus one marketer and one media
person — real capacity, but non-engineering, and structurally non-blocking (see `SDLC-AGRD.md` §3,
Cooldown 0). Do not assume parallel engineering capacity exists. It doesn't.

## Where the truth lives

- **`Architecture/INDEX.md`** — the single governance and ID-numbering authority. Anything in
  `docs/architecture/origin/`, `docs/blueprints/`, or `docs/evaluation/` is **archived, read-only,
  historical** — those trees describe an earlier, abandoned monolith design and/or superseded
  numbering. Never treat them as current.
- **`Architecture/OPEN-DECISIONS.md`** — deliberately unresolved questions. Never silently resolve one
  of these yourself; flag it and ask.
- **`Architecture/CrossCutting/SDLC-AGRD.md`** — the delivery methodology (see below).
- **`Architecture/Verification/lint/run.php`** — the primary second reviewer in a one-person team. Runs
  against `INDEX.md`'s cross-reference map; catches ID mismatches, naming drift, and Soft-Freeze
  violations. Expanded lint scope only becomes active from the first post-Milestone-0 cooldown onward
  — not during Milestone 0 itself.

## Datastore — MySQL, not PostgreSQL

`ADR-013` (current) mandates **MySQL 8 (InnoDB)** as the primary datastore — `JSON` columns, `ULID`
primary keys, generated-column indexes. This **reversed** an earlier decision (`ADR-007`,
PostgreSQL + JSONB) — that ADR is archived and correct for its own historical record, but nothing
current should reference PostgreSQL/JSONB as active. If you see "JSONB" or "PostgreSQL 16" anywhere in
current (non-archived) content, that's leftover drift from before the reversal — flag it, it's a known
recurring cleanup pattern in this repo (multiple files had this exact leftover before it was caught).

**ID policy:** every cross-service entity identifier (tenant, user, audit record, etc.) is a **ULID**,
not a UUID and not an auto-increment integer — except purely-local surrogate keys never referenced
outside their own table, which may stay `BIGINT AUTO_INCREMENT`.

## SDLC — Spiral Deepening, not the original 3-person Radial Incremental model

Full methodology: `Architecture/CrossCutting/SDLC-AGRD.md` (currently v3.4). Key concepts an agent
needs before doing implementation work:

- **Depth scale (1–6):** 1=stub, 2=happy path, 3=error paths, 4=observability, 5=production hardening,
  6=at-scale verified. "Production depth" means 5 unless stated otherwise.
- **Milestone 0:** the walking skeleton — `CORE-02`, `CORE-04`/`05`/`06` stubs, `HUB-01`, `BRIDGE-01`
  stub, `ISPOKE-09`, `ESPOKE-01` — proving one real end-to-end request round trip before anything else
  is trusted. Its measured duration (`W`) calibrates every later estimate. **No timeline in this
  project is trustworthy before Milestone 0 completes.**
- **Lap structure:** widens (admits new blueprints at depth 1, picked by real dependency-graph edges in
  `INDEX.md` §5.2) *and* deepens (every blueprint advances toward its own per-blueprint depth target)
  every cycle — never fully deepen a fixed set before widening.
- **Interface freeze vs. implementation depth:** a blueprint's public contract freezes once, at
  admission (even at depth 1). Only its internal implementation deepens afterward. Changing an
  already-frozen interface needs an ADR (Soft-Freeze violation otherwise).
- **Cooldowns:** 2 weeks, not 1 — solo burnout carries higher risk than team burnout, no redundancy.
  Lint-scope expansion and doc reconciliation happen during cooldowns, never during bet weeks.

## Real implementation status (verify against the repo before trusting this — it goes stale fast)

As of the most recent check: only `CORE-01` (`orchestrator/`, the Loom polyrepo tool) and `CORE-03`
(`packages/core/event-dispatcher/`) have real, tested code. `CORE-02` (DI Container) is the critical
path blocker — everything else transitively depends on it and it does not yet exist. Don't assume any
other component is built without checking.

## Known naming collisions (don't reintroduce these)

"Sovereign Pulse" is `HUB-15` (Health) only — `HUB-09` was renamed "Sovereign Signal" after this
collision was found. "Sovereign Forge" is still an **unresolved** 4-way collision across `CORE-20`,
`ISPOKE-02`, `ISPOKE-11`, `ESPOKE-12` — tracked as an open decision, not yet fixed. "Sovereign Ledger"
(`HUB-22` vs. `ISPOKE-13`) and "Sovereign Nexus" (`HUB-21` vs. `ISPOKE-14` vs. `ESPOKE-07`) are
disambiguated with notes in their respective files, not renamed.

## A pattern worth knowing about, not just a fact

Earlier architecture docs in this repo had a specific, recurring bug: `CORE-09` was mislabeled as the
cryptography component in over a dozen files (real `CORE-09` is PSR-3 logging; crypto is `CORE-16`).
It's fixed now in `Architecture/`, but the pattern — a wrong component ID copy-pasted across many files
faster than it gets caught — is the single most common defect class this repo has produced. Treat any
cross-reference you're not 100% sure of as worth checking against `INDEX.md` §2, not assuming correct.

## Provenance

`Architecture/` and `SDLC-AGRD.md` are products of iterative review across multiple AI tools (this
session's Claude instance, Kimi, Z.ai/GLM) plus the human tech lead — not one pass. Citations like "per
external review" or "Finding 3" embedded in these docs refer to that history. If something looks
under-explained, check whether the fuller reasoning lives in the doc's own revision history/changelog
before re-deriving it.

## Security note

A GitHub PAT was pasted in plaintext in chat during this project's early sessions. If you're ever
handed a credential directly in a prompt, treat it as compromised — flag it for rotation, don't just
use it silently.
