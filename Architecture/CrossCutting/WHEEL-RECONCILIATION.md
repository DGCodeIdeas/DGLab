# WHEEL-RECONCILIATION.md — The Wheel Structure, Reconciled

**Purpose:** reconcile the competing Wheel-architecture descriptions that existed across the source drafts into
the single canonical model now in the repo (`Architecture/CrossCutting/STRUCTURE-01-Wheel.md`, status
**Canonical v0.4 — consolidation merge**), and record what was kept, what was dropped, and what remains a
proposed (not adopted) delta.

**Authoritative source:** `STRUCTURE-01-Wheel.md` v0.4. This file explains *how* that version was arrived at.
It does not override it.

---

## 1. The canonical model (v0.4)

The Wheel is **6 rings**, organized as **4 layers + 2 checkpoints**:

| # | Ring | Class | Role |
|---|---|---|---|
| 1 | Core | Layer | Framework kernel / domain model (innermost invariants) |
| 2 | Hub | Layer | Stateful aggregates / application services around Core |
| 3 | Thick Spokes (Inner Spokes) | Layer | Staff-facing service adapters |
| 4 | Inner Rim | **Checkpoint** | `BRIDGE-01` — stateless contract, gates Internal↔External crossing |
| 5 | Thin Spokes (Outer Spokes) | Layer | Public-facing / edge adapters |
| 6 | Outer Rim | **Checkpoint** | `HUB-08` Gateway — public traffic entry, stateless enforcement |

The 96 blueprints occupy these rings as: Core 20 (`CORE-01..20`), Hub 30 (`HUB-01..30`), Thick Spokes 25
(`ISPOKE-01..25`), Inner Rim 1 (`BRIDGE-01`), Thin Spokes 15 (`ESPOKE-01..15`), Outer Rim 5 (`DEPLOY-00..04`).

## 2. The reconciliation criterion (layer vs. checkpoint)

The source drafts contained a real dispute about the **ring count** — specifically whether the Hub is a
boundary or a layer, and whether the two Rims are rings or something else. `STRUCTURE-01` v0.4 resolved this
with a decisive test (carried from the v3 review chain in `DGLab architectural blueprint refinement(2).md`):

> **A ring is a "layer" if it has a catalog of independently-stateful members. It is a "checkpoint" if it is a
> single stateless enforcement contract.**

Applied:
- Core, Hub, Thick Spokes, Thin Spokes are **layers** (each holds many independently-stateful members).
- Inner Rim (`BRIDGE-01`) and Outer Rim (`HUB-08`) are **checkpoints** (each is a single stateless enforcement
  contract).

This holds the **ring count at 6** — but with the precise reading: **6 rings spatially = 4 layers + 2
checkpoints conceptually.** The count is defended by a criterion, not by assertion.

## 3. The radial-flow exception (don't over-read "free radial flow")

A recurring misstatement: "free radial flow to adjacent layers." That is **false across a checkpoint**. The
correct rule:

> Free lateral mesh **within** any layer; free radial flow to **adjacent** layers **except across a
> checkpoint**.

Concretely: **Thin Spoke → Inner Spoke is NOT free** — it passes through the Inner Rim checkpoint (`BRIDGE-01`).
That gate is the *entire point* of the Inner Rim. Every cross-Rim crossing is an enforcement point, not a
passthrough.

## 4. How the divergence was resolved (v0.2 vs v0.3)

Two substantially different Structure-01 specs existed in the working archive:

| Version | Origin | Character |
|---|---|---|
| v0.2 | Kimi (Radial Incremental author) | Full 20 KB Wheel model, emphasis on ring-lock gates and the Pulse formalism |
| v0.3 | Z.ai (ADR-Gated Shape Up author) | 9 KB spec, emphasis on ADR gating and Shape Up cadence |

These were **two competing final answers to the same question, not a lineage** — they disagreed on the Hub
boundary question and on ring/layer accounting. The repo's consolidation commit (`19bb1cb`, Aug 5) resolved it
decisively by adopting **v0.3's model as the normative structure** (it matched the live blueprint inventory and
`INDEX.md`), while **keeping v0.2's Pulse formalism** (the 6-tuple) and **adding v0.1's ID map** (blueprint
numbering). The result is `STRUCTURE-01-Wheel.md` v0.4: "Canonical — consolidation merge."

So the reconciliation is: **v0.3 shape + v0.2 Pulse + v0.1 IDs = v0.4 canonical.** Neither v0.2 nor v0.3 is
independently canonical; v0.4 is.

## 5. The proposed v0.5 delta — NOT adopted

A later refinement pass (`DGLab architectural blueprint refinement(2).md`) and the Kimi synthesis chat proposed
a **v0.5** Wheel delta with, e.g., the "ten fixes" (security moves left, soft→hard freeze, OD resolution
embedded, 1-week cooldowns, cross-repo Pulse tests, canonical versioning v0.4→v1.0, ring-aligned bets, ADR
queue by DAG position, lint `--ring` scope, meta-ADR before Bet 1) and a "two-layer architecture" reading
(Macro = Radial Incremental skeleton of 8 Ring Bets; Micro = ADR-Gated nervous system inside each ring).

**Status: proposed only. The repo's `STRUCTURE-01` remains v0.4.** v0.5's "1-week cooldowns" in particular
conflicts with the canonical v3.4 rule that cooldowns are 2 weeks under solo mode (`SDLC-AGRD.md` §7). Any v0.5
adoption must wait for post-lap-1 data and a methodology version bump, per `SDLC-AGRD.md` §10's "stop iterating
without lap data" assessment. Listed here so the idea isn't lost, not because it is current.

## 6. Open structural questions

- **HUB-31** (Real-Time Analytics) is Proposed (ADR-011), not part of the 96. If accepted, it would extend the
  Hub layer, not add a ring.
- **Hospitality vertical** (`ISPOKE-26/27`, `ESPOKE-16/17/18`) is designed but not in the repo — see
  `HOSPITALITY-VERTICAL.md`. If committed, it extends the Spoke layers within the existing 6-ring model.
- **Agent prompts** that still reference "6 layers" or "Radial Incremental" are stale — the model is 4 layers +
  2 checkpoints, delivered by Spiral Deepening (`SDLC-AGRD.md` §2).

---

### Provenance

Reconciled from `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` (v0.4 canonical), the layer-vs-checkpoint
criterion and v0.2/v0.3 divergence analysis in `Design_Models_Misc/DGLab architectural blueprint
refinement(2).md`, and the AGRD synthesis discussion in `Design_Models_Misc/2026-08-10-Check Repo
Updates-Kimi.md`. The v0.5 delta is recorded as proposed-but-not-adopted, consistent with the repo's current
v0.4 status.
