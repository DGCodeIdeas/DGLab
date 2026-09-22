# SDLC-HISTORY.md — How the Canonical SDLC & Structure Docs Were Produced

**Status:** Historical record. The current canonical SDLC is `SDLC-AGRD.md` (v3.4(3)); the current canonical
structure docs are `STRUCTURE-01`..`STRUCTURE-09` in this folder. This file explains *how those got here* so a
future reader can tell settled fact from superseded draft.

---

## 1. The SDLC version lineage

Each version had a real flaw the next fixed. (See also `MEMORY.md` §9.)

| Version | What it was | Flaw found | Fix in next |
|---|---|---|---|
| AGRD v1 (Kimi) | Radial Incremental — rings as delivery roadmap, ring-lock gates | Arithmetic error: claimed 48 weeks, actually 62 | v2 re-derived |
| AGRD v2 (Z.ai) | ADR-Gated Continuous Architecture on Shape Up | Assumed 3 engineers; sized 6-week bets on that | v3 restructured for solo |
| SDLC-AGRD v3 | Solo-mode restructure | N=10 counting error (Milestone 0 actually 8) | v3.1 corrected to N=8 |
| v3.1 | Added lap structure | Phase structure secretly rebuilt Radial Incremental at smaller scale | v3.2 replaced with lap structure |
| v3.2 | Lap structure | Global lap floor broke for blueprints admitted at different laps (owed 3+ levels in one lap) | v3.3 per-blueprint relative floor |
| v3.3 | Per-blueprint floor | §4.2 stale global-floor language; Bet 1 kill unit mismatch; lap-1 widen didn't respect OD-02 | v3.4 fixed all three |
| v3.4 / v3.4(2) | Added §11 PROMPTS module; de-specified agent names; created MEMORY.md | Lap-1 widen exclusion for CORE-16/HUB-04 (defensive) | **v3.4(3) dropped the exclusion** after verifying OD-02 against the actual interfaces |
| v3.4(3) | Current canonical | — | Stop iterating without lap-1 data |

## 2. The blueprint refinement audit (Claude, `refinement(2).md`)

A separate, parallel critique pass produced `00_CRITIQUE.md` (13+ evidence-backed findings) and
`01_MASTER_INDEX.md` (governance layer). Key findings that landed in the repo:

- **Finding 3 (Core/Hub ID mislabeling):** the wrong Core/Hub IDs referenced across many files. Expanded to
  **five recurring mislabel patterns** (6–15 files each) — not just the one `BRIDGE-01` instance originally
  reported.
- **Finding 15 (Internal Spoke ID drift):** three External Spokes each guessed a different wrong mapping for
  where "Insight"/"Ledger"/"Nexus" live — every incorrect pairing except the correct one showed up. Signals the
  Internal Spoke tier was renumbered without downstream updates.
- **ULID policy (Master Index §10):** cross-service IDs are ULID (`CHAR(26)`); integer only for purely-local
  surrogate keys. Added because `HUB-06`'s audit schema had typed `tenant_id` as `int` while `HUB-01`/`HUB-21`
  used ULID.
- **Datastore conflict:** `ADR-007` (PostgreSQL 16 + JSONB) vs. MySQL-everywhere. Resolved by **`ADR-013`
  reversing `ADR-007`** — MySQL 8 (InnoDB) primary, PostgreSQL disabled-by-default. Leftover "JSONB"/"PostgreSQL
  16" text in `ISPOKE-16` was caught as the exact bug class this audit exists to find.
- **Dependency-DAG cycle:** `HUB-03`↔`HUB-11` each listed as the other's downstream. Fixed with binding
  edge-direction semantics in `INDEX.md` §5.1.

**Governance Rules (carried into the canonical tree):**

1. `INDEX.md` is the single numbering authority.
2. No benchmark/timeline claim without a stated method (this is the SDLC-level rule behind `SDLC-AGRD.md` §5).
3–8. (cross-reference enforcement, naming discipline, etc.)
9. Open questions get recorded, never silently resolved.

## 3. The consolidation commit (`19bb1cb`, Aug 5 2026)

A consolidation commit merged multiple parallel critique efforts — the Claude refinement pass (Findings cited
verbatim, v0.3 structure adopted as normative), plus at least one other pass (evidenced by `Kimi.md`,
`Z.ai GLM-5.2` exports) — into a new canonical `Architecture/` tree:

- `Core/`, `Hub/`, `Spoke/{Internal,External,Bridge}/`, `Deploy/`
- 10 accepted `ADRs/` + 1 proposed (`ADR-011`, correctly still *Proposed*)
- 9 `CrossCutting/` structure docs (`STRUCTURE-01`..`STRUCTURE-09`, `GLOSSARY`, `OBSERVABILITY`,
  `THREAT_MODEL`)
- `INDEX.md` governance authority
- **A working `Verification/lint/run.php`** that re-derives cross-references against `INDEX.md` and fails the
  build on a mismatch — the exact mechanism Governance Rule 1 recommended as "should exist."

`STRUCTURE-01-Wheel.md` Part A is the v0.3 model verbatim (kept as "most correct"); Part B re-attaches v0.2's
Pulse formalism; Part C adds v0.1's ID map.

## 4. What the audit caught in its own work (intellectual honesty)

- `HUB-09` and `HUB-15` were both named "Sovereign Pulse" in the rewrite. The repo fixed it: `HUB-09` →
  "Sovereign Signal", "Sovereign Pulse" reserved for `HUB-15` (the Pulse noun is reserved architecturally).
- `CORE-09` (Logging) was repeatedly mislabeled as the crypto component; crypto is `CORE-16`.
- "Sovereign Forge" is a still-unresolved 4-way collision (`CORE-20`, `ISPOKE-02`, `ISPOKE-11`, `ESPOKE-12`) —
  open as OD-05.

## 5. v3.4(3) — the final pre-lap correction

After v3.4(2), an external review re-verified the lap-1 widen exclusion. It found the exclusion was
**unnecessary**: `CORE-16`'s `EncrypterInterface` keeps cipher choice internal and ships no asymmetric primitive;
JWT signing lives in `HUB-04` (per `ADR-003` ES256) with the algorithm expected inside an internal `TokenService`,
not the frozen interface. So OD-02 is expected to land as an internal change, and the exclusion was dropped. The
consolidated `SDLC-AGRD.md` §4.3, `PROMPTS.md` §11.3, `MEMORY.md` §5, and `MEMORY_INSTRUCTIONS.md` §4.6 all
reflect the drop.

## 6. What remains data-dependent (do not resolve without lap data)

Per `SDLC-AGRD.md` §9 and §10: the actual Milestone 0 duration `W`; whether deepening throughput differs from
build throughput; how many laps the widen/deepen structure takes; and whether §5.2's dependency-graph coverage
(37/96) needs extending before widening exhausts it. These are deliberately left open.

---

### Provenance

Compiled from `Design_Models_Misc/DGLab architectural blueprint refinement(2).md` (critique findings, governance
rules, consolidation narrative, ADR-013/MySQL, naming collisions, dependency cycle) and
`Design_Models_Misc/2026-08-10-Check Repo Updates-Kimi.md` (AGRD synthesis, lineage). The live repo
(`INDEX.md`, `ADRs/`, `STRUCTURE-01`, `Verification/lint/run.php`) is the ground truth these histories describe.
