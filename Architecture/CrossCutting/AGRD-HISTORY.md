# AGRD-HISTORY.md — The AGRD Methodology, From Two Models to One

**Status:** Historical record of how the **AGRD** (ADR-Gated Radial Delivery) methodology was synthesized from
two competing recommendations, and how it evolved into the canonical `SDLC-AGRD.md` (currently v3.4(3)). For the
document/audit lineage, see `SDLC-HISTORY.md`.

---

## 1. The two source models

| Model | Author | Got right | Got wrong |
|---|---|---|---|
| **Radial Incremental** | Kimi | Wheel rings as delivery roadmap; Ring Lock gates freeze interfaces; dependency-DAG-aware sequencing; 3-person team optimization; pulse-driven demos | Didn't leverage existing ADR/lint infrastructure; treated architecture as static; no cooldown |
| **ADR-Gated Continuous Architecture on Shape Up** | Z.ai (GLM) | ADRs as the unit of work; `run.php` as merge gate; doc-first culture; lightweight rituals; Open Decisions as backlog | Didn't map bets to Wheel rings; Shape Up "appetite" collides with the DAG (can't appetite past CORE-02 being a stub); under-emphasized the ring structure |

## 2. The synthesis: AGRD v1.0

**"The Wheel's rings are the roadmap. ADRs are the gates. 6-week bets are the cadence. The linter is the
notary."**

| From Radial Incremental | From Z.ai's ADR-Gated Shape Up | Unified in AGRD |
|---|---|---|
| Ring Lock freezes interfaces | ADR ratification at merge | **Ring Lock Ceremony** — demo + lint + ADR ratify + interface freeze |
| Tangential team flow | Facet ownership | **Tangential ownership** — 3 engineers own facets (Identity, Data, Gateway), rotate every 2 bets |
| Pulse-driven demos | Contract tests | **Pulse contract tests** — cross-repo 6-tuple verification mandatory |
| Dependency DAG sequencing | ADR queue | **OD → ADR → Bet** — Open Decisions are the only backlog |
| — | 6-week bets + 2-week cooldown | **Ring Bet** — 6 weeks per ring, never extended (kill or descope) |
| — | `run.php` as notary | **Automated + human gates** — linter enforces, ADR Review decides |

**Ring Bet map (v1.0):** 8 bets, 48 weeks + 7 cooldowns = 55 weeks. Bet 1 Core → Bet 2 Hub Critical → Bet 3
Hub Full → Bet 4 Thick Spokes (a) → Bet 5 Thick Spokes (b) → Bet 6 Inner Rim → Bet 7 Thin Spokes + Outer Rim →
Bet 8 Hardening. Each bet ends at a Ring Lock gate.

**The one rule that prevents both failure modes:** *Don't let the linter become the architect.* The ADR Review
is where decisions are made; the linter is the notary; the Ring Lock is the seal. This prevents Kimi's risk
(architecture drifting with no decision ritual) and Z.ai's risk (editing canonical docs to satisfy the linter).

## 3. Synthesis V2 — the two-layer architecture + ten fixes

A second synthesis reframed AGRD as **two layers**:

```
Layer 1 (Macro): Radial Incremental — the skeleton
  → 8 Ring Bets, 48 weeks + 7 cooldowns = 55 weeks
  → DAG-enforced build order: Core → Hub → Thick Spokes → Inner Rim → Thin Spokes → Hardening
Layer 2 (Micro): ADR-Gated — the nervous system
  → Inside each ring: ADR queue, lint gates, PR flow, rituals
  → Governance lives inside the ring, not above it
```

**The ten fixes applied:**

| # | Fix | From | Impact |
|---|---|---|---|
| 1 | Security moves left | Z.ai | BRIDGE-01 built incrementally from Bet 1; Bet 6 shrinks 6→3 weeks |
| 2 | Soft Freeze → Hard Freeze | Z.ai | Soft during build; Hard after production cutover |
| 3 | OD resolution embedded | Z.ai | Each bet explicitly resolves the ODs it blocks |
| 4 | 1-week cooldowns | Z.ai | Between every bet; doc reconciliation |
| 5 | Cross-repo Pulse tests | Z.ai | Mandatory at every Ring Lock; built in Bet 1 |
| 6 | Canonical doc versioning | Z.ai | STRUCTURE-01 v0.4 → v1.0 across 8 bets |
| 7 | Ring-aligned bets | Z.ai self-fix | Bets = ring increments, not arbitrary scope |
| 8 | ADR queue by DAG position | Z.ai self-fix | ODs prioritized by which bet they block |
| 9 | Lint scope grows per ring | Z.ai self-fix | `run.php --ring=core` → `--ring=hub` → full lint |
| 10 | Meta-ADR before Bet 1 | Z.ai self-fix | ADR-014 ratifies the SDLC itself |

**Four honest gaps closed:** bus factor (primary + reviewer per blueprint, rotation every 2 bets); production ops
(9th increment: on-call, incident response, patching, quarterly reconciliation); performance verification
(per-ring benchmarks from Bet 1, blocking from Bet 7); rollback strategy (ring snapshots at every Ring Lock,
48-hour fix-or-kill).

## 4. The solo-mode rewrite (v3 → v3.4(3))

When the team size was corrected to **1 solo tech lead**, the 3-engineer assumptions collapsed. The 8-bet /
55-week plan became the v3.x lineage (see `SDLC-HISTORY.md` §1): Radial Incremental's ring-completion was
replaced by **Spiral Deepening** (vertical deepening of horizontal slices), the lap structure replaced phase
structure, and cooldowns became **2 weeks** (not 1). The two-layer Macro/Micro framing from V2 survives as the
*lap = widen + deepen* structure, but the "8 fixed Ring Bets" cadence was dropped — laps are not a fixed count.

**Corrected N-count (the gap-hunting that found a real error in its own doc):** an AGRD review computed the
schedule from N=10 components and got a tighter range; re-counting found N=8 (Milestone 0 scope), which pushes
the honest projection *higher* (44–66 weeks build-only at W=4–6), not lower. The error was caught and stated
plainly rather than handed over as a third confidently-wrong range.

## 5. Adoption

The consolidated `SDLC-AGRD.md` is the canonical SDLC. The companion `PROMPTS.md` operationalizes it for agents.
`ADR-014` (ratify AGRD as canonical SDLC) is referenced in the V2 migration plan but is **not present in the
repo's `ADRs/`** — the SDLC was adopted by practice/consolidation commit, not yet by a ratified ADR. Tracked in
`DISCREPANCY-REGISTER.md`.

---

### Provenance

Synthesized from `Design_Models_Misc/2026-08-10-Check Repo Updates-Kimi.md` (the AGRD synthesis, Synthesis V2
two-layer architecture, ten fixes, four gaps, corrected N-count) and cross-referenced with `SDLC-HISTORY.md` and
`SDLC-AGRD.md`. The V2 "1-week cooldowns" and fixed 8-bet cadence are recorded as the 3-engineer-era model and
are superseded by v3.4's 2-week cooldowns and lap-based (non-fixed-count) structure.
