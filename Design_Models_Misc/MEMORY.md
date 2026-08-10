# MEMORY.md — DGLab Wheel Project

**What this is:** the entry-point memory file for any AI agent (any capability class — Local Editor,
Local CLI, Local Inline, Cloud PR, Cloud Async, Cloud Conversational; per SDLC-AGRD v3.4 §11.2)
working on the DGLab Wheel project. Read this FIRST, before any task prompt, before the shared
context block (§11.3), before the worklog. If you read only one file before starting work, read
this one.

**How this differs from other files:**
- `worklog.md` is append-only per-task execution log. MEMORY.md is curated long-term context.
- `SDLC-AGRD-v3.4.md` is the process spec. MEMORY.md is the orientation index pointing into it.
- `OPEN-DECISIONS.md` (in the repo) is the live OD list. MEMORY.md summarizes OD status, doesn't
  analyze.
- `INDEX.md` (in the repo) is the architecture catalog. MEMORY.md names the rings and key
  blueprints, doesn't enumerate all 96.

**Update protocol:** MEMORY.md is curated, not append-only. The tech lead updates it when durable
facts change (Milestone 0 completes, an OD resolves, a new ring's blueprints start being admitted,
methodology version bumps). Per-task progress goes in `worklog.md`, not here. If you're an agent
and you think MEMORY.md is stale, surface it — don't edit it without tech-lead approval (see §11
below).

---

## 1. Project identity

**DGLab Wheel** — a PHP/TypeScript application structured as a concentric wheel: 6 rings, 96
blueprints total.

| Ring | Blueprint count | Example IDs | Role |
|---|---|---|---|
| Core | 20 | CORE-01..20 | Domain model — the innermost business invariants |
| Hub | 30 | HUB-01..30 | Aggregates / application services around Core |
| Thick Spokes (Inner Spokes) | 25 | ISPOKE-01..25 | Service adapters (Codex, etc.) |
| Inner Rim | 1 | BRIDGE-01 | API bridge / stateless contract between Inner Spokes and Outer Spokes |
| Thin Spokes (Outer Spokes) | 15 | ESPOKE-01..15 | UI/edge adapters (Canvas, etc.) |
| Outer Rim | 5 | DEPLOY-00..04 | Deployment / runtime edge |

**Pulse** — the canonical end-to-end request trace: an HTTP request enters at the Outer Rim, crosses
the Inner Rim, resolves against an Inner Spoke, returns. Milestone 0's success criterion is a real
Pulse round trip working, not a diagram of it.

**Polyrepo** (Vision B canonical) — multiple repos, not a monolith. Cross-repo consistency is
enforced by the Pulse 6-tuple (per `INDEX.md` and the lint check in SDLC-AGRD v3.4 §6).

## 2. Team reality

- **1 solo tech lead** — AI-agent-augmented (drives Local Editor / Cloud Async / Cloud Conversational
  agents per §11.2). All engineering review capacity sits here. No second engineer to catch mistakes
  in review.
- **1 marketer** — writes copy against `HUB-13` string keys and `ESPOKE-05` wireframe.
- **1 media** — produces assets against `HUB-26` theme tokens.

This team size invalidates the 3-engineer parallelism assumptions of the v1/v2 SDLC predecessors.
Everything in v3.4 is sized for this team. Do not re-introduce parallel-engineer assumptions — the
prompts, the bet durations, the cooldown lengths, and the kill triggers all assume one engineer's
attention as the bottleneck.

## 3. Methodology

**SDLC-AGRD v3.4: Spiral Deepening** — vertical deepening of horizontal slices, NOT horizontal
completion of vertical rings.

Canonical document: `/home/z/my-project/upload/SDLC-AGRD-v3.4.md` (mirror at
`/home/z/my-project/download/SDLC-AGRD-v3.4.md`). Target destination when pushed:
`Architecture/CrossCutting/SDLC-AGRD.md`.

Read these sections of v3.4 for the model:
- §§1–2 — why solo mode changes everything; Spiral Deepening defined.
- §3 — Cooldown 0 (content contract freezing before Milestone 0).
- §4 — Milestone 0 (8-blueprint walking skeleton, 8-week kill, depth scale, lap structure,
  per-blueprint relative floor).
- §5 — calibration formula (`throughput = N/W`, build-only estimate, recalibrate per lap).
- §6 — linter as second reviewer (scope expansion in cooldowns only).
- §7 — cooldowns are 2 weeks (solo burnout risk).
- §8 — what's preserved from predecessors; OD sequencing under solo mode.
- §9 — six explicit unknowns that must NOT be silently resolved.
- §10 — v3.3 → v3.4 changelog; "stop iterating without lap data" assessment.
- §11 — PROMPTS module (agent operating instructions, capability-class-based).

Key concepts to internalize before any task:
- **Milestone 0** — 8-blueprint walking skeleton (CORE-02, CORE-04/05/06 stubs, HUB-01, BRIDGE-01
  stub, ISPOKE-09, ESPOKE-01). 8-week kill trigger. Calibration instrument, not a feature.
- **Depth scale** (1=stub, 2=happy path, 3=error paths, 4=observability, 5=production hardening,
  6=at-scale verified). Production depth = 5 unless stated otherwise.
- **Lap structure** — every lap does widen (admit next-most-depended-upon blueprint per ring at
  depth 1) + deepen (each blueprint below its per-blueprint relative floor advances +1).
- **Per-blueprint relative floor** — each blueprint's target depth = admission_depth +
  laps_since_admission, capped at 5. NOT a global lap floor (that was the v3.2 spec bug).
- **Interface freeze** — public contract freezes at first implementation. Changes are ADR-gated.
- **Cooldowns** — 2 weeks under solo mode. Cooldown 0 (before Milestone 0) freezes content
  contracts. Subsequent cooldowns expand lint scope + run content Ring Lock checkpoint.

## 4. The 7 rules every agent must internalize

These are non-negotiable. If a task prompt conflicts with these, the rules win. Surface, don't
decide.

1. **Interface freeze (§2.1).** A blueprint's public contract freezes at first implementation.
   Changing it is ADR-gated. Do not "improve" a frozen interface mid-task — STOP and surface.
2. **Per-blueprint relative floor (§4.3).** Each blueprint deepens at +1 level per lap since its own
   admission, capped at 5. Do NOT apply a global lap floor — that was the v3.2 spec bug, explicitly
   rejected in v3.4. If you find yourself wanting to apply a global floor "for simplicity," STOP.
3. **Lint in cooldowns only (§6).** Lint scope expansion happens during cooldowns, NEVER during bet
   weeks. Log mid-bet lint gaps for the next cooldown; do not act on them mid-bet, even if "it's
   just one quick check."
4. **Bet kill at 1.5× (§4.2).** A bet exceeding 1.5× its time-box stops. Keep partial progress
   (interface was already frozen, so partial deepening is never wasted). Surface for re-scoping.
   Do not silently extend.
5. **Milestone 0 kill at 8 weeks (§4).** Milestone 0 exceeding 8 weeks is a stop-the-line
   reassessment, NOT a calibrate-forward event. Do not push to week 9. This is qualitatively
   different from a bet kill — conflation leads to premature methodology-reassessment or premature
   push-through, both bad.
6. **OD sequencing (§8.1).** OD-06 resolves during Milestone 0 (mechanically forced by CORE-02).
   OD-02 resolves in Cooldown 1 (after Milestone 0, NOT during it, NOT during Cooldown 0). Do not
   pre-resolve either. OD-01/03/04/05 keep their existing OPEN-DECISIONS.md timing.
7. **Lap-1 widen constraint (§4.3).** CORE-16 and HUB-04 are EXCLUDED from lap 1 widening until
   OD-02 resolves. Do not admit them early "to save a lap" — that's the ADR-thrash on
   freshly-frozen interfaces that v3.4 explicitly prevents.

## 5. File map

| Path | What | Update cadence |
|---|---|---|
| `/home/z/my-project/MEMORY.md` | This file. Curated long-term context. | When durable facts change (tech lead only) |
| `/home/z/my-project/worklog.md` | Append-only per-task execution log. Every agent appends after every task. | Per task |
| `/home/z/my-project/upload/SDLC-AGRD-v3.4.md` | Canonical SDLC spec (also mirrored at `/download/`). | Per methodology version |
| `/home/z/my-project/upload/SDLC-AGRD-v3.{1,3}.md` | Prior versions — read for history, do not act on. | Frozen |
| `/home/z/my-project/download/wheel.html`, `wheel.png` | Wheel visualization deliverable. | On architecture changes |
| `/home/z/my-project/scripts/build_wheel.py` | Wheel generation script (behind canonical v0.4 by two versions — needs refresh). | On demand |
| Repo: `Architecture/OPEN-DECISIONS.md` | Live OD list. | When ODs resolve |
| Repo: `Architecture/CrossCutting/SDLC-AGRD.md` | Where v3.4 lands when pushed (target destination). | One-time push |
| Repo: `Architecture/STRUCTURE-XX.md` | Per-blueprint spec (96 docs). | Per blueprint revision |
| Repo: `Architecture/INDEX.md` | Architecture catalog. §5.2 has the real Mermaid dependency graph (37/96 coverage — see §9 unknown). | On architecture changes |
| Repo: `Architecture/ADR/ADR-XXX.md` | Architectural Decision Records. | Per ADR |
| Repo: `Architecture/Cooldown0/` | Frozen content contracts (ESPOKE-05 wireframe, HUB-13 stringkeys, HUB-26 tokens). | Frozen after Cooldown 0 |
| Repo: `Verification/lint/run.php` | Linter (244 lines PHP as of last known commit `19bb1cb`, Aug 5 14:33 UTC). | Per cooldown (§6) |
| Repo: `.github/workflows/architecture-lint.yml` | Lint CI gate. | Per cooldown |

## 6. Vocabulary

- **Pulse** — end-to-end request trace, Outer Rim → Inner Rim → Inner Spoke → back. The
  integration test.
- **Ring Lock** — original ceremony (full ring freezes before next ring starts). v3.4 replaces this
  with interface freeze + per-blueprint relative floor.
- **Depth** — 1=stub, 2=happy path, 3=error paths, 4=observability, 5=production hardening,
  6=at-scale verified. See §4.1.
- **Lap** — one cycle of widen + deepen. Not a fixed count (terminates when all 96 at depth 5).
- **Widen** — admit one new blueprint per ring at depth 1, chosen by inbound-edge count from
  already-admitted blueprints in `INDEX.md` §5.2.
- **Deepen** — advance each blueprint below its per-blueprint relative floor by exactly +1.
- **Bet** — one named deepening task, one blueprint, one depth level. Time-boxed with 1.5× kill.
- **Cooldown** — 2-week gap between bets. Lint expansion + content Ring Lock + recovery.
- **Milestone 0** — 8-blueprint walking skeleton. Calibration instrument. 8-week kill.
- **Exemplar** — ISPOKE-09 (Codex) and ESPOKE-01 (Canvas), the two non-Core/Hub/Bridge blueprints
  in Milestone 0.
- **ADR** — Architectural Decision Record. Required for interface freeze changes and OD resolutions.
- **OD** — Open Decision. Six currently open (OD-01 through OD-06). See §8 below.
- **Soft-Freeze violation** — a PR touching a frozen interface without citing an ADR. Auto-reject at
  CI.
- **Per-blueprint relative floor** — each blueprint's target depth = admission_depth +
  laps_since_admission, capped at 5. The v3.4 fix for the v3.2 global-lap-floor spec bug.
- **Capability class** — agent classification (Local Editor, Local CLI, Local Inline, Cloud PR,
  Cloud Async, Cloud Conversational). Product names (Cline, Zoo, Kilo, Aider, Jules, Devin,
  Claude web, Codex, etc.) are illustrative examples, not prescriptive. See v3.4 §11.2.

## 7. Current state snapshot

**Last updated:** 2026-08-10. (This section is the only part of MEMORY.md updated regularly. When
it changes, update the "Last updated" date too.)

- **Methodology version:** SDLC-AGRD v3.4 (with §11 PROMPTS module, capability-class-based).
- **Cooldown 0:** not yet started.
- **Milestone 0:** not yet started. Repo last known commit `19bb1cb` (Aug 5 14:33 UTC) — not
  re-checked since.
- **Lap count:** 0.
- **Throughput:** unknown (Milestone 0 not complete; `W` not measured).
- **Matrix snapshot:** empty — no blueprints admitted yet. First admissions happen in Milestone 0
  (CORE-02, CORE-04/05/06 stubs, HUB-01, BRIDGE-01 stub, ISPOKE-09, ESPOKE-01).

When this changes, update this section. Do not let it drift — a stale snapshot is worse than no
snapshot because agents will trust it.

## 8. Open decisions

Six ODs currently open. Status only — analysis lives in `Architecture/OPEN-DECISIONS.md` (in the
repo).

| OD | Topic | Resolution timing (per §8.1) | Status |
|---|---|---|---|
| OD-01 | HUB-31 real-time analytics | Lap 1+ (per OPEN-DECISIONS.md) | Open |
| OD-02 | Post-quantum / algorithm-agility JWT (ADR-012) | Cooldown 1 (after Milestone 0) | Open — lap-1 widen constraint active (excludes CORE-16, HUB-04 from lap-1 widening) |
| OD-03 | Soft name collision | Before public API | Open |
| OD-04 | Exemplar labeling | Before public API | Open |
| OD-05 | ISPOKE folder naming | Before ISPOKE interface freeze | Open |
| OD-06 | Opcache baseline (ADR-010) | During Milestone 0 (mechanically forced by CORE-02) | Open |

Do not pre-resolve any of these. Surface the decision point when its trigger arrives; let the tech
lead close it with an ADR.

## 9. Known failure modes — the v3.x history

Each prior SDLC version had a real flaw that the next version fixed. Knowing these prevents
re-introducing them.

| Version | Flaw | Fix in next version |
|---|---|---|
| AGRD v1 (Kimi) | Arithmetic error: claimed 48 weeks, actually 62. | v2 re-derive. |
| AGRD v2 (Z.ai) | Assumed 3 engineers; sized 6-week bets on that. | v3 restructured for solo. |
| v3 | N=10 counting error (Milestone 0 actually has 8). | v3.1 corrected to N=8. |
| v3.1 | Phase structure secretly rebuilt Radial Incremental at smaller scale. | v3.2 replaced with lap structure. |
| v3.2 | Global lap floor broke for blueprints admitted at different laps (newly-widened owed 3+ levels in one lap). | v3.3 per-blueprint relative floor. |
| v3.3 | §4.2 still had stale global-floor language; Bet 1 kill-trigger unit mismatched build vs. deepen; lap-1 widen didn't respect OD-02. | v3.4 fixed all three. |

Lesson: every version had a real flaw. v3.4 will too — gap-hunt it (v3.4 §11.12) when lap data
arrives. The pattern is: each review pass finds 1–3 real issues. If a pass finds zero, the pass
wasn't real.

## 10. Agent conventions

**Capability-class framing, not product names.** Agents are classified by capability (Local Editor
/ Local CLI / Local Inline / Cloud PR / Cloud Async / Cloud Conversational — see SDLC-AGRD v3.4
§11.2). Product names (Cline, Zoo, Kilo, Aider, Jules, Devin, Claude web, Codex, etc.) are
illustrative examples, not prescriptive. New agents slot in by capability match — classify by
capability, use the matching prompt, do not wait for §11.2 to be updated.

**Before every task:**
1. Read this file (MEMORY.md) — you're doing that now.
2. Read `/home/z/my-project/worklog.md` — at minimum the last 3 entries.
3. Read the relevant task prompt from SDLC-AGRD v3.4 §11.4–11.13.
4. Paste the shared context block (v3.4 §11.3) into your task prompt with current-state fields
   filled in from §7 above.
5. Execute. Append to worklog when done.

**Worklog discipline (v3.4 §11.13):** append-only. Never overwrite. Every agent, every task. If you
can't find the worklog, ASK — its absence is a red flag, not permission to skip logging. Template:
Task ID, Agent (capability class + product name), Task, Work Log, Stage Summary, Hand-off notes.

**Hand-off between agents (v3.4 §11.13):** when a task moves from one agent to another (especially
local ↔ cloud), produce a hand-off bundle in worklog before the target agent starts. Cloud agents
have no conversation history and no worklog awareness — the bundle is the contract. Include:
current state, task definition for target, files to read, files NOT to touch, ODs/ADRs in play,
the shared context block re-delivered.

**Kill triggers are success conditions, not failures.** If you hit a bet kill (1.5× time-box) or
approach the Milestone 0 kill (8 weeks), STOPPING is the correct behavior. Pushing through is the
failure. See v3.4 §11.11.

**The two kill triggers are different in kind, not just in size.** A bet kill is a routine
re-scoping event (§4.3 Gap B) — keep partial progress, surface for re-scoping, continue. The
Milestone 0 kill is a stop-the-line reassessment (§4) — the architecture or the solo-mode
assumption itself may be wrong. Do not conflate the two.

## 11. Update protocol

MEMORY.md is curated, not append-only. The tech lead updates it when durable facts change:

- Milestone 0 completes → update §7 with measured `W`, update §8 OD-06 status to resolved.
- An OD resolves → update §8 status, cite the ADR.
- Methodology version bumps → update §3, §7, §9; add the prior version's flaw to §9's table.
- A ring starts being admitted beyond Milestone 0 cohort → update §7 matrix snapshot.
- File map changes → update §5.
- Current state changes (lap count, cooldown status, throughput recalibration) → update §7.

**Per-task progress does NOT go here.** That goes in `worklog.md`.

**Agents: if you find MEMORY.md stale** (e.g., you resolved an OD but §8 still says "Open"), surface
it to the tech lead. Do not edit MEMORY.md without tech-lead approval — the curation discipline is
what makes this file trustworthy. An agent-edited MEMORY.md with stale-then-half-correct entries is
worse than a cleanly-stale one, because the next agent can't tell which parts are current.

**The one exception:** §7's "Last updated" date and matrix snapshot may be updated by any agent that
completes a Milestone-0 blueprint, a lap widen/deepen step, or a cooldown — because that's
durable factual state, not interpretation. If you do this, append a worklog entry noting the MEMORY.md
§7 update, so the tech lead can audit.

---

End of MEMORY.md. Read `worklog.md` next.
