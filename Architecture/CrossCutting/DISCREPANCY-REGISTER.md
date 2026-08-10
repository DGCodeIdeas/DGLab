# DISCREPANCY-REGISTER.md — Contradictions Found & Corrected During Consolidation

**Purpose:** every contradiction between the source drafts (`Design_Models_Misc/`) and the canonical repo, or
between the drafts themselves, is recorded here. Each was **corrected inline** in the relevant consolidated doc
AND logged here, per the consolidation plan.

**Legend:** Corrected = fixed in the consolidated doc(s); Logged = recorded here for traceability; Open = not
resolvable by this effort (needs a decision/commit).

---

## D-01 — Lap-1 widen exclusion for CORE-16 / HUB-04
- **Source claim:** `SDLC-AGRD-v3.4(2).md` §4.3 and §11 (shared-context Rule 8), `MEMORY.md` Rule 7, and
  `MEMORY_INSTRUCTIONS(1).md` §4.6 excluded `CORE-16` and `HUB-04` from lap-1 widening until OD-02 resolves.
- **Canonical truth:** `v3.4(3)` **dropped** the exclusion after verifying OD-02 against the actual interfaces
  (`CORE-16.EncrypterInterface` keeps cipher choice internal, ships no asymmetric primitive; JWT signing in
  `HUB-04` via internal `TokenService`, per `ADR-003`). OD-02 is expected to land as an internal change under an
  already-frozen `HUB-04` contract.
- **Corrected in:** `SDLC-AGRD.md` §4.3, `PROMPTS.md` §11.3 Rule 8, `MEMORY.md` §5 rules 6/7,
  `MEMORY_INSTRUCTIONS.md` §4.6, `WORKLOG.md` (Task 2/3 annotations).
- **Severity:** High (would have wrongly constrained the first lap).

## D-02 — Blueprint count: 96 vs 101
- **Source claim:** Kimi chat and `MEMORY(1).md` state "101 total (96 canonical + 5 hospitality)".
- **Canonical truth:** the repo has **96**. The 5 hospitality blueprints (`ISPOKE-26/27`, `ESPOKE-16/17/18`)
  and `ADR-015` were designed in chat but **never committed**.
- **Corrected in:** `MEMORY.md` §1, `REPO-STATE-AUDIT.md` §1, `HOSPITALITY-VERTICAL.md` §7.
- **Severity:** High (wrong denominator breaks every SDLC calibration).

## D-03 — "CI lint is green / runs on every PR"
- **Source claim:** multiple drafts state `architecture-lint.yml` runs on PR/push via GitHub Actions and the
  build fails on mismatch.
- **Canonical truth:** **no `.github/` directory exists.** `architecture-lint.yml` lives at
  `Architecture/Verification/lint/` and is **not wired to any workflow**. The linter is a manual script.
- **Corrected in:** `REPO-STATE-AUDIT.md` §6, `SDLC-AGRD.md` §6 (reality-check note), `PULSE-MODEL.md` §8.
- **Severity:** High (false assurance of automated gating).

## D-04 — run.php scope overstated
- **Source claim:** `SDLC-AGRD.md` §6 lists Pulse 6-tuple consistency, naming-drift, Soft-Freeze auto-reject,
  and blueprint-fidelity diffing as active lint checks.
- **Canonical truth:** `run.php` performs **exactly 3 checks** — reference existence, misattribution phrases,
  structural completeness. The others are intended cooldown-expansion targets, not current behavior.
- **Corrected in:** `SDLC-AGRD.md` §6 (reality-check note added), `REPO-STATE-AUDIT.md` §5.
- **Severity:** Medium (aspirational vs actual capability).

## D-05 — Sandbox paths `/home/z/my-project/*`
- **Source claim:** MEMORY/PROMPTS/worklog drafts point to `/home/z/my-project/MEMORY.md`,
  `/home/z/my-project/worklog.md`, `/home/z/my-project/upload|download/`, `/home/z/my-project/scripts/`.
- **Canonical truth:** in the repo these map to `Architecture/CrossCutting/*`. The `scripts/`, `wheel.html`,
  `wheel.png` artifacts are **sandbox-only and absent from the repo**.
- **Corrected in:** `MEMORY.md`, `MEMORY_INSTRUCTIONS.md`, `PROMPTS.md`, `WORKLOG.md` (all paths re-pointed;
  sandbox-only artifacts flagged, not treated as canonical).
- **Severity:** Medium (broken pointers for any agent reading the consolidated docs).

## D-06 — AGRD "ratify by ADR-014"
- **Source claim:** AGRD V2 migration plan: "Write ADR-014: Adopt AGRD as canonical SDLC" as step 1.
- **Canonical truth:** **ADR-014 does not exist** in `Architecture/ADRs/`. The SDLC was adopted by the
  consolidation commit / practice, not by a ratified ADR.
- **Logged / Open:** `REPO-STATE-AUDIT.md` §4, this register. Recommend writing ADR-014 to close the gap.
- **Severity:** Low (process hygiene).

## D-07 — OD-02's home ADR-012 absent
- **Source claim:** OD-02 (post-quantum JWT) is to be resolved via ADR-012.
- **Canonical truth:** **ADR-012 does not exist.** OD-02 remains open; its resolution target ADR is unwritten.
- **Logged / Open:** `REPO-STATE-AUDIT.md` §4.
- **Severity:** Low.

## D-08 — Wheel ring count / Hub-as-layer dispute
- **Source claim:** two competing Structure-01 versions (v0.2 Kimi "5 rings if Hub is a boundary"; v0.3 Z.ai
  "6 rings"). Some chat summaries imply the count is 5.
- **Canonical truth:** **6 rings = 4 layers + 2 checkpoints** (Inner Rim `BRIDGE-01` and Outer Rim `HUB-08` are
  checkpoints; Core/Hub/Thick/Thin are layers). Resolved by the layer-vs-checkpoint criterion in
  `STRUCTURE-01` v0.4.
- **Corrected in:** `WHEEL-RECONCILIATION.md` §1–§3, `MEMORY.md` §1 table.
- **Severity:** Medium (structural misaccounting).

## D-09 — Two "1–6" depth scales conflated
- **Source claim:** "depth" used interchangeably for blueprint maturity and Pulse entry-radius.
- **Canonical truth:** (a) blueprint-maturity 1=stub…6=at-scale (`SDLC-AGRD.md` §4.1); (b) Pulse entry-radius
  1=Outer Rim…6=Core (`STRUCTURE-01` §D.1). Numerically overlapping, semantically distinct.
- **Corrected in:** `PULSE-MODEL.md` §5 (explicit two-scales callout), `SDLC-AGRD.md` §4.1 note.
- **Severity:** Medium (defect class).

## D-10 — Radial Incremental / 8 fixed Ring Bets / 1-week cooldowns (3-engineer-era model)
- **Source claim:** Kimi/Z.ai AGRD v1/v2 describe Radial Incremental, 8 fixed Ring Bets over 55 weeks, 1-week
  cooldowns.
- **Canonical truth:** solo-mode rewrite = **Spiral Deepening**, lap-based (no fixed bet count), **2-week
  cooldowns**. The Macro/Micro two-layer framing survives as "lap = widen + deepen", but the fixed cadence and
  1-week cooldowns are superseded.
- **Corrected in:** `AGRD-HISTORY.md` §3 (marked V2 as 3-engineer-era, superseded), `SDLC-HISTORY.md` §1,
  `SDLC-AGRD.md` §2/§7.
- **Severity:** Medium.

## D-11 — STRUCTURE-01 v0.5 proposed but not adopted
- **Source claim:** refinement(2)/Kimi chat propose a v0.5 Wheel delta (ten fixes, two-layer architecture).
- **Canonical truth:** repo `STRUCTURE-01` remains **v0.4**. v0.5 is proposed only; its "1-week cooldowns"
  conflict with canonical §7.
- **Corrected in:** `WHEEL-RECONCILIATION.md` §5 (marked proposed-not-adopted).
- **Severity:** Low.

## D-12 — ISPOKE-16 PostgreSQL leftover
- **Source claim/audit:** `ISPOKE-16.md` described persistence as "MySQL 16 / JSONB" — a pre-`ADR-013` leftover
  (16 = Postgres version, JSONB = Postgres-only).
- **Canonical truth:** MySQL 8 + plain `JSON` (`ADR-013`). The line slipped through a 42-file find-and-replace.
- **Logged / Open:** `SDLC-HISTORY.md` §2 (audit caught it). Needs a one-line fix in `ISPOKE-16.md` (out of
  scope for this cross-cutting consolidation).
- **Severity:** Low (single-line drift, but exactly the class this audit exists to catch).

## D-13 — "Sovereign" naming collisions
- **Source claim:** multiple drafts used "Sovereign Pulse" for `HUB-09`; "Sovereign Forge" used across 4
  components.
- **Canonical truth:** `HUB-09` → "Sovereign Signal"; "Sovereign Pulse" reserved for `HUB-15`. "Sovereign Forge"
  4-way collision (`CORE-20`, `ISPOKE-02`, `ISPOKE-11`, `ESPOKE-12`) remains **unresolved** (OD-05).
- **Corrected in:** `MEMORY.md` §9, `REPO-STATE-AUDIT.md` §8, `PULSE-MODEL.md` (reserved-word note).
- **Severity:** Low–Medium.

## D-14 — v3.4(2) §10 changelog lists the lap-1 exclusion as a "fix"
- **Source claim:** `SDLC-AGRD-v3.4(2).md` §10 records "Lap 1 widen constraint — excluded CORE-16/HUB-04" as a
  v3.4 fix.
- **Canonical truth:** v3.4(3) **reversed** that fix (see D-01). The §10 row is now partially superseded.
- **Corrected in:** `SDLC-AGRD.md` §10 (rewritten as a consolidated v3.x→v3.4(3) changelog; the drop is its own
  row).
- **Severity:** Low (changelog accuracy).

---

## D-15 — Hospitality references fail `run.php` check 1 by design
- **Observation:** `Verification/lint/run.php` flags `ESPOKE-16/17/18` and `ISPOKE-26/27` as "undefined
  reference" in `HOSPITALITY-VERTICAL.md` and in every cross-cutting doc that names them
  (`DISCREPANCY-REGISTER.md` D-02, `REPO-STATE-AUDIT.md` §1, `WHEEL-RECONCILIATION.md` §6, `MEMORY.md` §1,
  `WORKLOG.md`).
- **Why this is correct, not a bug:** those five IDs are out of the current `INDEX.md` range
  (`ESPOKE-01..15`, `ISPOKE-01..25`). The linter is proving D-02 — the blueprints are not in the repo.
- **Status:** **Expected / Open-by-design.** Do not "fix" by adding the IDs to `INDEX.md` or creating stub
  files (that would silently make them canonical and contradict D-02). Resolves itself when the blueprints are
  promoted per `HOSPITALITY-VERTICAL.md` §7, at which point check 1 passes.
- **Severity:** Informational (the linter is doing its job).

## Summary

| ID | Topic | Severity | Status |
|---|---|---|---|
| D-01 | Lap-1 widen exclusion | High | Corrected |
| D-02 | 96 vs 101 count | High | Corrected |
| D-03 | "CI lint green" | High | Corrected |
| D-04 | run.php scope | Medium | Corrected |
| D-05 | Sandbox paths | Medium | Corrected |
| D-06 | ADR-014 absent | Low | Logged/Open |
| D-07 | ADR-012 absent | Low | Logged/Open |
| D-08 | Ring count dispute | Medium | Corrected |
| D-09 | Two depth scales | Medium | Corrected |
| D-10 | Radial Incremental-era model | Medium | Corrected |
| D-11 | v0.5 not adopted | Low | Logged |
| D-12 | ISPOKE-16 PG leftover | Low | Logged/Open |
| D-13 | Sovereign collisions | Low–Med | Corrected/Open (OD-05) |
| D-14 | §10 changelog superseded | Low | Corrected |

**Three items (D-06, D-07, D-12) require repo commits/ADRs to fully close** and are intentionally left Open
rather than silently resolved.
