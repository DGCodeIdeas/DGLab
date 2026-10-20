# SuperPHP Naming Investigation — Standalone Research Report


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This CrossCutting document records **research questions, not decisions**. The naming investigation recorded here is an open investigation, not an architectural requirement, not an ADR, and not an implementation commitment. This report is marked **Research — Not an Implementation Requirement**. No rename is approved. **A governance rule is only as good as its enforcement — verify that any future claim based on this report is actually checked against the adopted decision, not against the research question itself.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Status:** Research — Not an Implementation Requirement. **No rename is approved.**

**Purpose.** This is a **standalone** research report on the SuperPHP naming question, extracted from `Architecture/CrossCutting/RESEARCH-AGENDA.md` V7 per the handoff requirements (per Kilo's analysis 2026-10-20: "Extract SuperPHP naming investigation into standalone report"). The agenda proper (`RESEARCH-AGENDA.md`) records the five product-research tracks + connecting-principles track; this file records the naming investigation separately so it can be reviewed, referenced, and resolved independently.

**Origin.** Extracted from `RESEARCH-AGENDA.md` V7 (commit `581945d`, originally authored 2026-10-13; reapplied to `origin/main` at `ae410ea` in PR #365 commit `90ae8ff` for owner review). See Appendix D of `RESEARCH-AGENDA.md` for the placement-divergence history across renditions V5/V6/V7.

---

## Background

SuperPHP is currently the planned template-language capability encompassing `CORE-07` (Lexer), `CORE-11` (Parser), and `CORE-12` (Compiler), per `Architecture/INDEX.md` §1 (lines 109, 113, 114 — all three 📝 Not started). It is referenced in `ADR-005-superphp-vs-blade-twig.md` and in `OPEN-DECISIONS.md` §OD-09 (DGLab SCSS framework authoring model).

SAAI's directive (2026-10-13) records five candidate replacement names: **Forge, Foundry, Nexus, SPX, Sovereign**. The directive is explicit: *"No rename is approved. Preserve the current name until its responsibilities and architectural boundaries are documented and the proposed alternatives have been evaluated for collisions."*

## Why a separate report instead of an OD entry

SAAI's directive required that, before editing `OPEN-DECISIONS.md`, the exact scope and title of OD-09 be inspected and confirmed as the correct home for the naming question. OD-09 is titled *"DGLab SCSS framework: scope, inspirations, SuperPHP authoring model"* and is scoped to the **SCSS framework's authoring model** (Tailwind vs. custom, four-layer model, `<dg:style>` block support in CORE-12). SuperPHP appears in OD-09 only as the *authoring layer for SCSS components* — not as a naming decision. **OD-09 is therefore not the correct home for the naming question.** Recording the candidates in this standalone research report (rather than creating a new OD entry) preserves the distinction: the naming question is still in research, not yet a decision-fork awaiting resolution. When the research matures into a fork, a new OD entry (candidate: OD-13) should be opened and this report should reference it.

## Candidate name collision audit (performed 2026-10-13 against `Architecture/INDEX.md` §1 and §2.3, `Architecture/CrossCutting/*.md`, and `Architecture/ADRs/*.md`)

| Candidate | Collision status | Evidence |
|---|---|---|
| **Forge** | ❌ **COLLISION** | `CORE-20` is canonically named *"Sovereign Forge"* (Developer CLI Toolchain), namespace `SovereignStack\Forge`, per `INDEX.md` §1 line 122. Also referenced in `CrossCutting/DGLAB-AS-OS.md` line 244 as `bin/forge` and line 255 as "gcc + make" build toolchain equivalent. The historical collision was resolved 2026-08-12 in `OPEN-DECISIONS.md` §OD-05 by assigning `Forge` to CORE-20 (the other three contenders — ISPOKE-02, ISPOKE-11, ESPOKE-12 — were renamed to A1 Atlas, B1 Penumbra, C1 Pulsar). Reusing `Forge` for SuperPHP would re-open this resolved collision and create a 4-way ambiguity with the developer CLI toolchain. |
| **Foundry** | ✅ **No collisions found.** No matches for `\bFoundry\b` across `Architecture/` or `docs/`. Lexically clean. Caveat: not yet audited against PHP-ecosystem package names on Packagist; this is a future research item if Foundry becomes a serious candidate. |
| **Nexus** | ❌ **COLLISION (2-way)** | `HUB-17` is *"Sovereign Webhook Nexus"* and `HUB-21` is *"Sovereign Nexus (Tenancy)"* per `INDEX.md` §2.3 lines 146, 149. Two existing Hub-tier components already use `Nexus` as their noun. Reusing it for the template engine would create cross-tier ambiguity. |
| **SPX** | ✅ **No internal collisions found.** No matches for `\bSPX\b` across `Architecture/` or `docs/`. Caveat: `SPX` is also an acronym used in the broader PHP ecosystem (Simple PHp Aggregate eXtension — a profiling extension), which is a *lexical* rather than *architectural* collision risk but worth noting if SPX becomes a serious candidate. |
| **Sovereign** | ❌ **MASSIVE COLLISION (Hub-tier naming prefix).** Every Hub-tier component is prefixed with `Sovereign` — `Sovereign Identity` (HUB-04), `Sovereign Cache` (HUB-02), `Sovereign Auditor` (HUB-06), `Sovereign Ledger` (HUB-22), `Sovereign Nexus` (HUB-21), `Sovereign Forge` (CORE-20), and 26+ more. The word `Sovereign` is also the project's own naming-convention prefix (per `INDEX.md` §1, §2.3, and the `SovereignStack\` namespace). Renaming SuperPHP to `Sovereign` would create total namespace confusion with the Hub tier. |

## Working hypothesis

Of the five candidates SAAI recorded, only **Foundry** and **SPX** are lexically clean inside the project. **Forge**, **Nexus**, and **Sovereign** all collide with existing canonical component names. This audit does not recommend either Foundry or SPX — it only establishes that the other three carry collision risk that should rule them out unless SAAI explicitly accepts the collision and the cost of resolving it (renaming the colliding component or accepting the ambiguity).

## Research questions for the naming investigation

- What are SuperPHP's *actual* responsibilities and architectural boundaries? Until these are documented, a rename is premature — naming should follow function, not lead it.
- Are there external collisions (Packagist, npm, GitHub org names, PHP-FIG proposals) for any of the candidates?
- What is the cost of renaming the 100+ references to "SuperPHP" across `Architecture/`, `ADR-005`, `OPEN-DECISIONS.md` §OD-09, and the future-implementation blueprints `CORE-07`, `CORE-11`, `CORE-12`?
- Should the template engine be named for its *function* (template, view, render) or for its *character* (forge, foundry, etc.)? This is a naming-philosophy question, not a collision question.

## Deliverables

- A **responsibilities and boundaries document** for SuperPHP, covering Lexer / Parser / Compiler responsibilities and the seams between them.
- An **external collision audit** for Foundry and SPX against Packagist, npm, and the broader PHP ecosystem.
- A **rename-cost estimate** for the existing `Architecture/` references.
- A **naming-philosophy note** distinguishing function-names from character-names, with SAAI's directive as input.

## Related doctrines and contracts

- `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md` — the existing ADR that ratified SuperPHP over Blade/Twig. Any rename requires amending this ADR.
- `Architecture/OPEN-DECISIONS.md` §OD-09 — the SCSS-framework authoring-model OD that mentions SuperPHP. Verified 2026-10-13: OD-09 is scoped to SCSS framework, not to naming.
- `Architecture/INDEX.md` §1, §2.3 — the canonical component-name registry and the collision target for any candidate.
- `Architecture/CrossCutting/DGLAB-AS-OS.md` — uses `bin/forge` and "Sovereign Forge" terminology; relevant to the Forge collision.
- `Architecture/CrossCutting/GLOSSARY.md` — defines "Sovereign Forge" as the codename for CORE-20.

## Placement divergence (V5 → V6 → V7)

The SuperPHP naming investigation has an unsettled placement history across the seven renditions of the research agenda. See `RESEARCH-AGENDA.md` Appendix D for the full side-by-side comparison. Summary:

| Rendition | SuperPHP placement | Notes |
|---|---|---|
| V1–V4 | Not present | SuperPHP naming not yet in scope |
| V5 (lines 29136–29297) | Added to agenda as a section | "SAAI→Z.ai sync brief" — SuperPHP enters as a research track |
| V6 (lines 29298–29586) | **Removed from agenda — "Handle SuperPHP separately"** | SuperPHP leaves the research agenda; explicit instruction that SuperPHP is not part of the agenda proper |
| V7 (lines 31829–31940) | Re-included as a 6th section | V7 draft (this report's origin) — diverges from V6's "handle separately" instruction |

**V8 resolution (this report):** SuperPHP naming is extracted to this standalone file, satisfying V6's "handle separately" instruction while preserving V7's research content. The agenda proper (`RESEARCH-AGENDA.md`) cross-references this file but does not contain the SuperPHP section inline.

## Out of scope

- Any rename of SuperPHP. **Not authorized.**
- Any amendment to ADR-005. **Not authorized.**
- Any new blueprint, namespace, or package rename. **Not authorized.**

---

## Governance of this report

- **Adding a new finding** to this report requires SAAI or tech-lead approval.
- **Promoting to ADR** (e.g., ADR-023 SuperPHP Rename Decision) requires the deliverables above to be produced, reviewed, and cited in the ADR.
- **Closing this report** requires SAAI or tech-lead approval and a closing note explaining why the investigation is no longer pursued.
- **No silent merge with `OPEN-DECISIONS.md`.** When the naming question matures into a decision-fork (candidate: OD-13), the OD entry cites this report and this report's status is updated.
- **No silent merge with `Verification/SHORTCOMINGS-REGISTER.md`.** A naming investigation is not a shortcoming.
