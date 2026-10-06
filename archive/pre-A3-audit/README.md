# Pre-A3-Audit Archive

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** The archived material below pre-dates the A3 audit (2026-10-02) and the two-DAG governance model (ADR-021 Amendment 1, 2026-10-01). It reflects earlier architectural thinking that has since been superseded. **Do not treat it as authoritative.** The number of contradictions found in this material is not the number that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md).

---

## What this is

This directory holds the pre-A3-audit `docs/` tree, moved here in PR (correction #1) per the SAAI directive:

> "Archive, don't delete, the pre-A3 `docs/` tree."

All 84 files have mtime 2026-09-19 — the day before the A3 audit began. They pre-date:

- The two-DAG governance model ([ADR-021 Amendment 1](../../Architecture/ADRs/ADR-021-tier-stratified-build-order.md), 2026-10-01).
- The edge dimension refinement ([ADR-021 Amendment 2](../../Architecture/ADRs/ADR-021-tier-stratified-build-order.md), 2026-10-01).
- The Shape C `pulse()` contract (PR #303, 2026-10-04).
- The Integrity Gate ([`INTEGRITY-GATE.md`](../../Architecture/Verification/INTEGRITY-GATE.md), PASSED 2026-10-05).
- The ADR numbering reconciliation (this archive preserves the historical ADR-001..005 in `docs/architecture/decisions/` while the canonical ADRs live in [`Architecture/ADRs/`](../../Architecture/ADRs/)).

## Why it's archived, not deleted

These files are historical records. They document the architectural thinking that preceded the current canonical set, including some ideas that were superseded and some that were refined. Per the SAAI directive: "Preserving their original paths/numbers inside the archive is preferable because they are historical records."

The historical ADR-001..005 in `docs/architecture/decisions/` cover different topics than the canonical `Architecture/ADRs/ADR-001..005`. This collision is preserved as a historical record — the canonical ADRs remain authoritative. (See the inventory report at `download/DOC-INVENTORY-AND-CLASSIFICATION.md` for the full collision table.)

## What's canonical instead

| Subject | Pre-A3 (here, archived) | Canonical (current) |
|---|---|---|
| ADRs | `docs/architecture/decisions/ADR-001..005` | [`Architecture/ADRs/ADR-001..021`](../../Architecture/ADRs/) |
| Hub taxonomy | `docs/hub-taxonomy/` | [`Architecture/Hub/`](../../Architecture/Hub/) (32 Hub blueprints + DAGs) |
| Internal spokes | `docs/internal-spokes/` | [`Architecture/Spoke/Internal/`](../../Architecture/Spoke/Internal/) (ISPOKE-01..27) |
| External spokes | `docs/external-spokes/` | [`Architecture/Spoke/External/`](../../Architecture/Spoke/External/) (ESPOKE-01..19) |
| Cache patterns | `docs/cache-patterns/` | [`Architecture/Core/CORE-15.md`](../../Architecture/Core/CORE-15.md) + nuclear-grade doctrine §4.2 |
| Queue patterns | `docs/queue-patterns/` | [`Architecture/Hub/HUB-02.md`](../../Architecture/Hub/HUB-02.md) (Sovereign Cache & State) + RUNTIME-03 (Queue Worker) |
| Implementation guides | `docs/implementation-guides/` | Per-package READMEs + blueprints |
| Design patterns | `docs/design-patterns/` | [`Architecture/CrossCutting/GLOSSARY.md`](../../Architecture/CrossCutting/GLOSSARY.md) |
| CI configuration | `docs/ci/` | [`.github/workflows/`](../../.github/workflows/) |
| Testing recipes | `docs/testing/recipes.md` | Per-package `tests/` directories |
| Operations / runbooks | `docs/operations/runbooks/` | [`Architecture/CrossCutting/RUNBOOK-*.md`](../../Architecture/CrossCutting/) |
| Tenancy | `docs/tenancy/` | [`Architecture/Hub/HUB-21.md`](../../Architecture/Hub/HUB-21.md) (Multi-tenancy Coordination Layer) |
| Extensibility | `docs/extensibility/` | [`Architecture/OPEN-DECISIONS.md`](../../Architecture/OPEN-DECISIONS.md) (OD-12 APIfy) |
| Plans | `docs/plans/` | `download/plans/` (active plans, not committed) |
| Roadmap | `docs/roadmap/` | [`Architecture/Hub/HUB-BUILD-ORDER.md`](../../Architecture/Hub/HUB-BUILD-ORDER.md) (generated) |

## How to use this archive

- **Architectural archaeology only.** Read for historical context, not for current decisions.
- **Do not link here from canonical docs.** The README and other canonical docs link to current sources, not to this archive.
- **Do not edit.** Add new material to the canonical locations above.
- **Do not assume completeness.** This archive preserves what was in `docs/` at the time of the move; it does not include every historical version of every document. Earlier drafts may live in `archive/Arc/` or `archive/Design_Models_Misc/`.

## Related archives

- [`archive/Arc/`](../Arc/) — earlier canonical drafts (Blueprints + Governance ADRs, before consolidation into `Architecture/`).
- [`archive/Design_Models_Misc/`](../Design_Models_Misc/) — design-phase notes, SDLC drafts, memory file versions.
- [`archive/docs/architecture/origin/`](../docs/architecture/origin/) — Vision A monolith (the deprecated single-repo framework-style rebuild).
- [`archive/docs/evaluation/`](../docs/evaluation/) — stale quality scores against a pre-renumbering Core tier.
- [`archive/docs/Legacy/`](../docs/Legacy/) — pre-DGLab Laravel application code.

---

**Authority:** This archive is a historical record. The canonical architecture lives in [`Architecture/`](../../Architecture/). The repository README is [`/README.md`](../../README.md).
