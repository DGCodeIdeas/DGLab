# Architecture / CrossCutting — Index

**This folder** holds the cross-cutting methodology, memory, and operational docs for the DGLab Sovereign Stack
Wheel. It is the consolidation target of the `Design_Models_Misc/` draft archive (see `README.md` provenance
below).

**Canonical count = 96 blueprints** (20 Core / 30 Hub / 25 Thick Spokes / 1 Inner Rim / 15 Thin Spokes / 5
Outer Rim). The 5 hospitality blueprints + `ADR-015` are **designed but not in the repo** — see
`HOSPITALITY-VERTICAL.md`.

---

## How to read these (boot order for any agent)

1. **`MEMORY.md`** — curated entry-point: project identity, team, methodology, current snapshot, ODs, vocabulary.
2. **`MEMORY_INSTRUCTIONS.md`** — operational playbook: boot sequence, 7 hard stops, hand-off, worklog.
3. **`MEMORY-GOVERNANCE.md`** — explicit-memory policy + how to maintain `MEMORY.md`.
4. **`SDLC-AGRD.md`** — the canonical delivery methodology (Spiral Deepening, v3.4(3)).
5. **`PROMPTS.md`** — agent operating instructions (capability classes, shared-context block, per-phase prompts).
6. **`WORKLOG.md`** — append-only execution log.

Structure / model docs: `STRUCTURE-01-Wheel.md` (v0.4 canonical), `STRUCTURE-02-Pulse.md`, `STRUCTURE-03..09`,
`GLOSSARY.md`, `OBSERVABILITY.md`, `THREAT_MODEL.md`.

---

## Consolidated documents (this effort)

| File | What it is |
|---|---|
| `SDLC-AGRD.md` | Canonical SDLC — Spiral Deepening, v3.4(3). §§1–10; §11 split to `PROMPTS.md`. |
| `PROMPTS.md` | Unified agent-prompt module (was §11): capability taxonomy, shared-context block, per-phase prompts, anti-drift bootstrap. |
| `MEMORY.md` | Curated agent entry-point (merged 3 variants). |
| `MEMORY_INSTRUCTIONS.md` | Operational playbook (boot, hard stops, hand-off, worklog). |
| `MEMORY-GOVERNANCE.md` | Explicit-memory policy + MEMORY.md maintenance rules. |
| `WORKLOG.md` | Execution log (re-pointed from sandbox paths). |
| `WHEEL-RECONCILIATION.md` | Reconciles v0.2/v0.3 Wheel divergence → canonical v0.4 (6 rings = 4 layers + 2 checkpoints). |
| `PULSE-MODEL.md` | The Pulse 6-tuple, classes, axioms; the two distinct depth scales. |
| `VISUAL-DESIGN-SYSTEM.md` | The "Imagine" color system (9 ramps × 7 stops, assignment rules, SVG safety). |
| `SDLC-HISTORY.md` | How the canonical SDLC & structure docs were produced (audit chain, governance rules, consolidation commit). |
| `AGRD-HISTORY.md` | The AGRD methodology's birth (Radial Incremental + ADR-Gated Shape Up → synthesis → solo-mode rewrite). |
| `HOSPITALITY-VERTICAL.md` | Hospitality vertical — designed, **not committed** (5 spokes + ADR-015). |
| `REPO-STATE-AUDIT.md` | **Verified** live-repo ground truth (96 blueprints, ADRs, run.php = 3 checks, no CI). |
| `DISCREPANCY-REGISTER.md` | Every contradiction found and corrected (D-01…D-14). Read this alongside any claim. |
| `RUNBOOK-ANVIL-DNS.md` | Ops: Anvil install broke DNS (`resolv.conf` → `127.0.0.1`); restore + permanent fix. |
| `RUNBOOK-BLUETOOTH.md` | Ops: receive a file from Android to Xubuntu via OBEX. |
| `README.md` | This index. |

---

## Critical corrections applied in this consolidation

- **Lap-1 widen exclusion dropped** (D-01): `CORE-16`/`HUB-04` are NOT excluded from lap 1 (v3.4(3)).
- **Count = 96, not 101** (D-02): hospitality set is uncommitted.
- **No CI** (D-03): `architecture-lint.yml` exists but is not wired to GitHub Actions; run.php is manual.
- **run.php = 3 checks only** (D-04): not the Pulse/naming/soft-freeze/fidelity checks some docs imply.
- **Sandbox paths re-pointed** (D-05): `/home/z/my-project/*` → `Architecture/CrossCutting/*`; wheel artifacts are repo-absent.
- **Two depth scales kept separate** (D-09): blueprint-maturity vs Pulse entry-radius.

Full detail in `DISCREPANCY-REGISTER.md`.

---

## Canonical source hierarchy (when in doubt)

1. `Architecture/INDEX.md` — numbering authority
2. `Architecture/OPEN-DECISIONS.md` — open decisions
3. `Architecture/ADRs/ADR-XXX.md` — accepted decisions
4. `Architecture/CrossCutting/` — structure docs, SDLC, prompts (this folder)
5. `Architecture/{Core,Hub,Spoke,Deploy}/` — blueprints
6. `Architecture/Verification/lint/run.php` — automated checks
7. `packages/*/` — live code

Lower number wins. If a consolidated doc contradicts the live repo, the repo wins — and it's logged in
`DISCREPANCY-REGISTER.md`.

---

### Provenance

The 17 documents in this folder were consolidated on 2026-08-10 from `Design_Models_Misc/` source drafts:
`SDLC-AGRD-v3.4(3).md`, `SDLC-AGRD-v3.4(2).md`, three `MEMORY*.md` variants, three `MEMORY_INSTRUCTIONS*.md`
variants, `PROMPTS*.md`, `worklog.md`, `AGRD_v2.0_Solo.md`, `DGLab architectural blueprint refinement(2).md`,
`2026-08-10-Check Repo Updates-Kimi.md`, and `Notes-9-8-2026(1).txt`. Every file carries a provenance footer.
Contradictions were corrected inline and logged in `DISCREPANCY-REGISTER.md`. The live repo (`INDEX.md`,
`OPEN-DECISIONS.md`, `ADRs/`, `Verification/lint/run.php`, `STRUCTURE-01`) is the ground truth; `REPO-STATE-AUDIT.md`
records the verified snapshot.
