# MEMORY.md — DGLab Sovereign Stack (Canonical Agent Entry-Point)

**What this is:** the curated, long-term-context file every AI agent reads FIRST — before any task prompt,
before the shared context block in `PROMPTS.md` §11.3, before the worklog. If an agent reads only one file
before starting, it is this one.

**Update protocol:** curated, not append-only. The tech lead updates it when durable facts change. Per-task
progress goes in `worklog.md`, not here. Agents that find it stale should surface it, not self-edit (the
curation discipline is what makes it trustworthy).

**Ground truth:** when this file contradicts the live repo, the repo wins. Flag the discrepancy in
`DISCREPANCY-REGISTER.md`, don't silently trust this file over live source.

---

## 1. Project identity

**DGLab / Sovereign Stack** — a custom PHP MVC framework, multi-tenant, event-driven, structured as a
concentric wheel: **6 rings, 102 canonical blueprints**.

| Ring | Blueprint count | Example IDs | Role |
|---|---|---|---|
| Core | 20 | CORE-01..20 | Framework kernel / domain model — innermost invariants |
| Hub | 31 | HUB-01..31 | Stateful aggregates / application services around Core |
| Thick Spokes (Inner Spokes) | 27 | ISPOKE-01..27 | Service adapters (Codex, etc.) — staff-facing |
| Inner Rim | 1 | BRIDGE-01 | Stateless contract checkpoint between Inner and Outer Spokes |
| Thin Spokes (Outer Spokes) | 18 | ESPOKE-01..18 | UI/edge adapters (Canvas, etc.) — public-facing, untrusted |
| Outer Rim | 5 | DEPLOY-00..04 | Deployment / runtime edge |

**Pulse** — the canonical end-to-end request trace: an HTTP request enters at the Outer Rim, crosses the Inner
Rim, resolves against an Inner Spoke, returns. Milestone 0's success criterion is a real Pulse round trip
working, not a diagram of it.

**Polyrepo (Vision B canonical)** — multiple repos, not a monolith. Cross-repo consistency is enforced by the
Pulse 6-tuple (see `PULSE-MODEL.md`).

> **Canonical count = 102.** The 5 hospitality blueprints (`ISPOKE-26/27`, `ESPOKE-16/17/18`)
> were promoted to canonical on 2026-08-12 per `ADR-015` (Proposed; ratification deferred until the
> hospitality V1 track ships against Bet 3 Hub Full). `HUB-31` was accepted on 2026-08-13 per `ADR-011`.
> See `HOSPITALITY-VERTICAL.md` for design source, `DISCREPANCY-REGISTER.md` D-02/D-15 for hospitality
> closure, and `ADR-011` for HUB-31 acceptance.

## 2. Team reality

- **1 solo tech lead** — AI-agent-augmented (drives Local Editor / Cloud Async / Cloud Conversational agents
  per `PROMPTS.md` §11.2). All engineering review capacity sits here. No second engineer.
- **1 marketer** — writes copy against `HUB-13` string keys and `ESPOKE-05` wireframe.
- **1 media** — produces assets against `HUB-26` theme tokens.

This team size invalidates the 3-engineer parallelism assumptions of the v1/v2 SDLC predecessors. Everything
in v3.4 is sized for one engineer's attention as the bottleneck. Do not re-introduce parallel-engineer
assumptions.

## 3. Technology stack

| Layer | Choice | Canonical Source |
|---|---|---|
| Language | PHP 8.3+ | CORE-01 (Loom) |
| Framework | Custom (Sovereign Stack) | All CORE blueprints |
| Database | **MySQL 8.0+ (InnoDB)** | ADR-013 |
| Cache | Redis 7 | HUB-02 |
| Queue | RabbitMQ / AWS SQS | HUB-10 |
| Events | Internal event dispatcher | CORE-03, HUB-09 |
| PK format | ULID — `CHAR(26) CHARACTER SET ascii` | Master Index §10 |
| Tenant isolation | DBAL-enforced `tenant_id` context | HUB-21 + CORE-19 |
| JSON | MySQL `JSON` (NOT `JSONB`) | ADR-013 |
| Auth | JWT via HUB-04 + HUB-05 | HUB-04, HUB-05 |
| Frontend | Vanilla JS + HUB-26 theme tokens | HUB-26 |

**PostgreSQL is disabled-by-default.** `ADR-013` reversed `ADR-007` (PostgreSQL + JSONB). Do not generate
PostgreSQL-specific code (JSONB, RLS, GIN). If you see "JSONB"/"PostgreSQL 16" in current content, that is
leftover pre-reversal drift — flag it.

## 4. Methodology — SDLC-AGRD v3.4(3) Spiral Deepening

Canonical document: `Architecture/CrossCutting/SDLC-AGRD.md` (companion prompts in `PROMPTS.md`). **Current
phase:** Pre-Cooldown 0.

Key concepts to internalize before any task:

- **Depth scale (1–6):** 1=stub, 2=happy path, 3=error paths, 4=observability, 5=production hardening,
  6=at-scale verified. "Production depth" = 5 unless stated otherwise.
- **Milestone 0** — 8-blueprint walking skeleton (`CORE-02`, `CORE-04/05/06` stubs, `HUB-01`, `BRIDGE-01`
  stub, `ISPOKE-09`, `ESPOKE-01`). 8-week kill trigger. Calibration instrument, not a feature. Its measured
  duration `W` calibrates every later estimate. **No timeline is trustworthy before Milestone 0 completes.**
- **Lap structure** — every lap does *widen* (admit next-most-depended-upon blueprint per ring at depth 1,
  chosen by real `INDEX.md` §5.2 edges) **and** *deepen* (each blueprint below its per-blueprint relative floor
  advances +1). Never fully deepen a fixed set before widening.
- **Per-blueprint relative floor** — target depth = admission_depth + laps_since_admission, capped at 5. NOT
  a global lap floor (that was the v3.2 spec bug).
- **Interface freeze vs. implementation depth** — a blueprint's public contract freezes once, at admission
  (even at depth 1). Only its internal implementation deepens afterward. Changing an already-frozen interface
  needs an ADR (Soft-Freeze violation otherwise).
- **Cooldowns** — 2 weeks, not 1. Lint-scope expansion and content Ring Lock happen during cooldowns, never
  during bet weeks.

## 5. The 7 rules every agent must internalize

Non-negotiable. If a task prompt conflicts with these, the rules win — surface, don't decide.

1. **Interface freeze (§2.1 of SDLC-AGRD).** A blueprint's public contract freezes at first implementation.
   Changing it is ADR-gated. Do not "improve" a frozen interface mid-task — STOP and surface.
2. **Per-blueprint relative floor (§4.3).** Each blueprint deepens at +1 level per lap since its own
   admission, capped at 5. Do NOT apply a global lap floor — that was the v3.2 spec bug, explicitly rejected.
3. **Lint in cooldowns only (§6).** Lint-scope expansion happens during cooldowns, NEVER during bet weeks.
   Log mid-bet lint gaps for the next cooldown.
4. **Bet kill at 1.5× (§4.2).** A bet exceeding 1.5× its time-box stops. Keep partial progress (interface was
   already frozen, so partial deepening is never wasted). Surface for re-scoping.
5. **Milestone 0 kill at 8 weeks (§4).** Milestone 0 exceeding 8 weeks is a stop-the-line reassessment, NOT a
   calibrate-forward event. Do not push to week 9. This is qualitatively different from a bet kill.
6. **OD sequencing (§8.1).** OD-06 resolves during Milestone 0 (mechanically forced by CORE-02). OD-02
   (post-quantum JWT) resolves in Cooldown 1 — after Milestone 0, NOT during it, NOT during Cooldown 0. Do
   not pre-resolve either. OD-01/03/04/05 keep their `OPEN-DECISIONS.md` timing. *(The lap-1 widen exclusion
   for CORE-16/HUB-04 that earlier v3.4 drafts imposed was **DROPPED in v3.4(3)** — OD-02 was verified
   against the actual interfaces and is expected to land as an internal change under an already-frozen HUB-04
   contract, not an interface change. Do not re-add the exclusion.)*
7. **You are a tool, not an architect.** Implement, critique, or verify against canonical docs. Do not invent
   components, rename things, resolve ODs, or emit PostgreSQL/JSONB code. Cite a canonical source for every
   architectural claim; if you cannot, say "I don't have a canonical source for this."

## 6. Vocabulary

- **Pulse** — end-to-end request trace, Outer Rim → Inner Rim → Inner Spoke → back. The integration test.
- **Ring Lock** — original ceremony (full ring freezes). v3.4 replaces it with interface freeze + per-blueprint
  relative floor.
- **Depth** — see §4.
- **Lap / Widen / Deepen / Bet / Cooldown / Milestone 0 / Exemplar** — see `SDLC-AGRD.md` §4 and `PROMPTS.md` §11.9.
- **ADR** — Architectural Decision Record. Required for interface-freeze changes and OD resolutions.
- **OD** — Open Decision. Six currently open (OD-01..06). See §8.
- **Soft-Freeze violation** — a PR touching a frozen interface without citing an ADR.
- **Capability class** — agent classification (Local Editor, Local CLI, Local Inline, Cloud PR, Cloud Async,
  Cloud Conversational). Product names (Cline, Zoo, Kilo, Aider, Jules, Devin, Codex, etc.) are illustrative.

## 7. Current state snapshot

**Last updated:** 2026-08-24. (Only this section is updated regularly; when it changes, update the date too. A
stale snapshot is worse than none — agents will trust it.)

- **Methodology version:** SDLC-AGRD v3.4(3) Spiral Deepening (companion `PROMPTS.md`).
- **Cooldown 0:** **COMPLETE** (2026-08-13). Three frozen contract artifacts delivered:
  `ESPOKE-05-wireframe.md`, `HUB-26-theme-tokens.md`, `HUB-13-string-keys.md`.
- **Milestone 0:** not yet started.
- **Lap count:** 0. **Throughput:** unknown (`W` not measured).
- **Matrix snapshot:** empty — first admissions happen in Milestone 0.
- **Real implementation status (verify against repo):** `CORE-01` (Loom), `CORE-02` (DI Container), and
  `CORE-03` (Event Dispatcher) all have real, tested code. `CORE-02` shipped v1.0.0 on 2026-08-16 with PSR-11
  conformance suite, 97.2% coverage, and PHPStan level:max clean. It is **no longer a blocker**.

## 8. Open decisions

**OD-01 through OD-06 resolved on 2026-08-12–13.** OD-07 and OD-08 opened on 2026-08-22. See
`Architecture/OPEN-DECISIONS.md` for full resolution records.

| OD | Topic | Decision | Status |
|---|---|---|---|
| OD-01 | HUB-31 real-time analytics | Accept `ADR-011`. HUB-31 canonical. Count 101→102. | **Resolved** 2026-08-13 |
| OD-02 | Post-quantum JWT agility | Author `ADR-012`. 3-phase plan (registry→hybrid→PQ). Targets Cooldown 1. | **Resolved** 2026-08-12 |
| OD-03 | Soft component-name collisions | CI enforcement (`run.php` check 1 + `architecture-lint.yml`) sufficient. | **Resolved** 2026-08-12 |
| OD-04 | Exemplar labeling | Split: `ISPOKE-01` = Internal Exemplar, `ESPOKE-01` = External Exemplar. | **Resolved** 2026-08-12 |
| OD-05 | ISPOKE naming + Forge collision | Keep ID-only filenames. Resolve collision: ISPOKE-02→A1 Atlas, ISPOKE-11→B1 Penumbra, ESPOKE-12→C1 Pulsar. | **Resolved** 2026-08-12 |
| OD-06 | Opcache baseline provisional | Keep provisional, gated behind `CORE-02`. Already flagged in `STRUCTURE-09`. | **Resolved** 2026-08-12 |

**OD-07 and OD-08 are open** (see `Architecture/OPEN-DECISIONS.md`). OD-07 (Fiber-based cooperative
  runtime) is provisionally directed to Option A; OD-08 (async I/O library) is deferred until OD-07
  ratification. New decision points beyond these must use OD-09+.

## 9. Known failure modes & hazards

| Issue | Impact | Mitigation |
|---|---|---|
| **3-engineer assumption** | Predecessor docs assumed parallel bets | Solo sequential — no exceptions |
| **CORE-02 stub** | `.gitkeep` only — blocks everything | Milestone 0 priority #1 |
| **CORE-09 mislabel** | Called "crypto" in many files | CORE-09 = Logging (PSR-3); crypto is CORE-16 (Hashing/Signing) |
| **Sovereign naming collisions** | HUB-09 "Sovereign Pulse" → renamed "Sovereign Signal" | Check `INDEX.md` before using "Sovereign [X]" |
| **Sovereign Forge 4-way collision** | CORE-20 / ISPOKE-02 / ISPOKE-11 / ESPOKE-12 | Unresolved — OD-05; do not pick a winner |
| **PostgreSQL ghost** | Agents hallucinate JSONB/RLS/GIN | ADR-013 guard — reject non-MySQL DDL |
| **Anvil DNS bug** | install breaks Xubuntu internet (`/etc/resolv.conf` → `127.0.0.1`) | Use NetworkManager dnsmasq plugin, not system dnsmasq on port 53 (see `RUNBOOK-ANVIL-DNS.md`) |
| **Mislabel-copy-paste pattern** | Wrong component ID replicated across many files | Treat any cross-ref you're unsure of as worth checking against `INDEX.md` §2 |

v3.x history lesson: each SDLC version had a real flaw the next fixed (v1 arithmetic error → v2 3-engineer
assumption → v3 N=10 count → v3.1 Radial-at-small-scale → v3.2 global-floor cliff → v3.3 stale language →
v3.4(3) dropped lap-1 exclusion). Gap-hunt each review pass; if a pass finds zero, it wasn't real.

## 10. Canonical source hierarchy (quick reference)

When uncertain, check in this order; **lower number wins**:

1. `Architecture/INDEX.md` — numbering authority, component IDs
2. `Architecture/OPEN-DECISIONS.md` — ODs (OPEN)
3. `Architecture/ADRs/ADR-XXX.md` — accepted decisions
4. `Architecture/CrossCutting/` — structure docs, SDLC, prompts
5. `Architecture/{Core,Hub,Spoke,Deploy}/` — individual blueprints
6. `Architecture/Verification/lint/run.php` — automated checks
7. `packages/*/` — live code

`docs/architecture/origin/`, `docs/blueprints/`, `docs/evaluation/` (if present) are **archived, read-only,
historical** — an earlier abandoned monolith design. Never treat them as current.

## 11. Agent conventions & hand-off

- **Capability-class framing, not product names** (see `PROMPTS.md` §11.2). New agents slot in by capability
  match.
- **Before every task:** (1) read this file; (2) read `worklog.md` last 3 entries; (3) read the relevant
  `PROMPTS.md` §11.4–11.13 prompt; (4) paste the §11.3 shared context block with current state filled; (5)
  execute; (6) append to worklog.
- **Worklog discipline:** append-only, never overwrite. Template: Task ID, Agent (class + product), Task, Work
  Log, Stage Summary, Hand-off notes. If you can't find the worklog, ASK.
- **Hand-off bundle (local ↔ cloud):** include current state, task definition, files to read, files NOT to
  touch, ODs/ADRs in play, and the shared context block re-delivered. Cloud agents have no history — the
  bundle is the contract.
- **Kill triggers are success conditions, not failures.** Stopping at a kill trigger is correct; pushing
  through is the failure.

## 12. Security note

A GitHub PAT was pasted in plaintext in chat during early sessions. If you're ever handed a credential directly
in a prompt, treat it as compromised — flag it for rotation, don't use it silently.

---

### Provenance

Merged from three `Design_Models_Misc` variants: `MEMORY.md` (curated entry-point, 7-rule set, vocabulary,
update protocol), `MEMORY(1).md` (tech-stack table, hazard matrix, source hierarchy), and `MEMORY(2).md`
(repo-accurate spine: live-repo truth rule, ADR-013 MySQL, naming-collision pattern, implementation status, PAT
security note). **Corrections applied:** MEMORY-rule-7 lap-1 widen exclusion for CORE-16/HUB-04 **removed**
(per v3.4(3)); inventory normalized to 96 canonical on 2026-08-05 (hospitality 5 + ADR-015 noted as
designed-but-not-in-repo), then **promoted to 101 canonical on 2026-08-12** per ADR-015, then **102 canonical on 2026-08-13** per ADR-011 (hospitality
vertical blueprints committed, linter scope extended, D-02/D-15 closed);
all sandbox `/home/z/my-project/*` paths re-pointed to `Architecture/CrossCutting/*`.
