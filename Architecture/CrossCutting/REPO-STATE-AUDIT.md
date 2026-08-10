# REPO-STATE-AUDIT.md — Live Repo Ground Truth (verified 2026-08-10)

**Status:** Verified snapshot of the actual `Architecture/` tree, taken 2026-08-10. This is the authority for
all "how many", "what exists", and "what the linter does" claims across the consolidated docs. Where a source
draft (in `Design_Models_Misc/`) claims something this audit contradicts, **this audit wins** and the
contradiction is logged in `DISCREPANCY-REGISTER.md`.

---

## 1. Blueprint inventory — 96 canonical

| Tier | Count | IDs |
|---|---|---|
| Core | 20 | `CORE-01..20` |
| Hub | 30 | `HUB-01..30` |
| Internal Spoke (Thick) | 25 | `ISPOKE-01..25` |
| External Spoke (Thin) | 15 | `ESPOKE-01..15` |
| Bridge (Inner Rim) | 1 | `BRIDGE-01` |
| Deploy (Outer Rim) | 5 | `DEPLOY-00..04` |
| **Total** | **96** | |

**Not counted:** 5 hospitality blueprints (`ISPOKE-26/27`, `ESPOKE-16/17/18`) and `ADR-015` — designed in chat,
**never committed** (see `HOSPITALITY-VERTICAL.md`). Any source draft claiming "101 blueprints" is counting the
uncommitted hospitality set. These five IDs are out of the current `INDEX.md` range, so `run.php` check 1 flags
them wherever named — an expected, correct failure (see `DISCREPANCY-REGISTER.md` D-15) that resolves on promotion.

**Implementation status:** only `CORE-01` (Loom, orchestrator) and `CORE-03` (event dispatcher) have real,
tested code. `CORE-02` (DI Container) is a stub (`.gitkeep` only) and is the critical-path blocker. Do not
assume any other component is built.

## 2. INDEX.md — the numbering authority

- `INDEX.md` §2 is the canonical tier/ID catalog.
- `INDEX.md` §5.1 carries the dependency DAG with **binding edge-direction semantics** (a fix from the
  refinement audit — the `HUB-03`↔`HUB-11` cycle was resolved here).
- `INDEX.md` §5.2 carries a **Mermaid dependency graph covering 37 of 96 blueprints** (all 20 Core, 10 "selected
  critical" Hub, `BRIDGE-01`, exemplar `ISPOKE-01`/`ESPOKE-01`, all 4 Deploy). `HUB-02` and `HUB-04` are in the
  covered set with real edges. This coverage is what the SDLC widen rule computes against; it must be extended
  before widening exhausts the 37 nodes (trigger: 50% of a ring's covered set admitted — `SDLC-AGRD.md` §9).

## 3. OPEN-DECISIONS.md — 6 open ODs

| OD | Topic | Status in repo |
|---|---|---|
| OD-01 | HUB-31 real-time analytics | Open (ADR-011 Proposed) |
| OD-02 | Post-quantum / algorithm-agility JWT | Open — resolves Cooldown 1; **no lap-1 widen exclusion** (v3.4(3)) |
| OD-03 | Soft component-name collisions | Open |
| OD-04 | Exemplar count | Open |
| OD-05 | ISPOKE folder names vs INDEX.md | Open (filename↔name mapping cosmetic item remains) |
| OD-06 | ADR-010 opcache vs CORE-02 blocker | Open — forced during Milestone 0 |

## 4. ADRs — 001–011 + 013 (no 012, 014, 015)

| Present | Note |
|---|---|
| ADR-001..011 | Accepted (ADR-011 = HUB-31, **Proposed** not Accepted) |
| ADR-013 | MySQL 8 primary — **reverses ADR-007** (PostgreSQL). ADR-007 remains archived/correct for its era. |
| **Absent: ADR-012** | Referenced as the home for OD-02 (post-quantum JWT) — **not yet written**. OD-02 is unresolved. |
| **Absent: ADR-014** | Referenced by AGRD V2 migration ("ratify AGRD as canonical SDLC") — **not written**. SDLC adopted by practice, not by ratified ADR. |
| **Absent: ADR-015** | Hospitality Vertical Integration — **not written / not committed** (designed only). |

## 5. The linter — `Verification/lint/run.php`

**Verified: exactly 3 checks.** Source: the file's own header comment (lines 8–14):

1. **Reference existence** — every `CORE-/HUB-/ISPOKE-/ESPOKE-/BRIDGE-/DEPLOY-` token must resolve to an
   in-range ID.
2. **Misattribution phrases** — the two historically-wrong phrasings are rejected.
3. **Structural completeness** — every expected blueprint file must exist.

**Run result (2026-08-10):** `php Architecture/Verification/lint/run.php` → `architecture-lint: OK (127 files
scanned)`, exit 0.

**What it does NOT do (despite some docs implying more):** it does **not** check Pulse 6-tuple consistency
across repos, naming drift vs `INDEX.md`, Soft-Freeze violations, or blueprint-fidelity semantic diffing. Those
are *intended* cooldown-expansion targets in `SDLC-AGRD.md` §6, not current capabilities.

## 6. CI status — there is none

**Verified: no `.github/` directory exists.** The `architecture-lint.yml` referenced in some docs lives at
`Architecture/Verification/lint/` and is **not wired to any GitHub Actions workflow**. Any claim that "CI lint
is green" or "the linter runs on every PR" is **false** — the linter is a manual script. This is the single most
important contradiction between the aspirational docs and the repo.

## 7. Structure docs present (`CrossCutting/`)

Canonical: `STRUCTURE-01-Wheel.md` (v0.4), `STRUCTURE-02-Pulse.md`, `STRUCTURE-03-Security.md`,
`STRUCTURE-04-Events.md`, `STRUCTURE-05-Persistence.md`, `STRUCTURE-06-Boot.md`, `STRUCTURE-07-Testing.md`,
`STRUCTURE-08-Deployment.md`, `STRUCTURE-09-Performance.md`, `GLOSSARY.md`, `OBSERVABILITY.md`,
`THREAT_MODEL.md`. Plus the 17 consolidated docs produced in this effort (this file, `SDLC-AGRD.md`,
`PROMPTS.md`, `MEMORY.md`, `MEMORY_INSTRUCTIONS.md`, `MEMORY-GOVERNANCE.md`, `WORKLOG.md`,
`WHEEL-RECONCILIATION.md`, `PULSE-MODEL.md`, `VISUAL-DESIGN-SYSTEM.md`, `SDLC-HISTORY.md`, `AGRD-HISTORY.md`,
`HOSPITALITY-VERTICAL.md`, `DISCREPANCY-REGISTER.md`, `RUNBOOK-ANVIL-DNS.md`, `RUNBOOK-BLUETOOTH.md`,
`README.md`).

## 8. Known recurring defect classes (from the audit)

- **Mislabel-copy-paste:** wrong component ID replicated across many files (e.g., `CORE-09` called "crypto";
  crypto is `CORE-16`). Treat any cross-ref you're unsure of as worth checking against `INDEX.md` §2.
- **PostgreSQL ghost:** "JSONB"/"PostgreSQL 16" in current (non-archived) content is pre-`ADR-013` drift.
- **Sovereign naming collisions:** `HUB-09`→"Sovereign Signal"; "Sovereign Pulse" reserved for `HUB-15`;
  "Sovereign Forge" 4-way collision unresolved (OD-05).

---

### Provenance

Verified directly against the live repo on 2026-08-10: `ls Architecture/ADRs/`, `ls .github` (absent), `grep`
of `OPEN-DECISIONS.md`, `sed` of `run.php` header, and the `php run.php` execution. Blueprint and ADR counts
cross-checked against `INDEX.md` and `STRUCTURE-01-Wheel.md`.
