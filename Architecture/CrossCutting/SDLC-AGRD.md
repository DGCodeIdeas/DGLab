# SDLC-AGRD: Spiral Deepening for a Solo Tech Lead

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This file is a **redirect**. The SDLC methodology has been rewritten (per Tech-Lead directive 2026-10-06: "rewrite with proper details the entire SDLC"). The canonical SDLC documents now live in [`Architecture/SDLC/`](../SDLC/). This file is retained for backward compatibility — references to `SDLC-AGRD.md` will resolve here, but the actual content is in the new documents. See [`../SDLC/README.md`](../SDLC/README.md) for the index.

---

## Redirect Notice

**The SDLC methodology has been rewritten.** Per Tech-Lead directive (2026-10-06): "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time."

The original `SDLC-AGRD.md` (v3.4(3), 475 lines, single file) has been replaced by 3 new documents in [`Architecture/SDLC/`](../SDLC/):

| Original Section (v3.4(3)) | New Document |
|---|---|
| §1 Why the prior team-size assumption invalidates more than the schedule | [`SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) §1 |
| §2 Methodology shift: Spiral Deepening replaces Radial Increment | [`SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) §2 |
| §3 Cooldown 0 — before Milestone 0 | [`SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) §5 |
| §4 Milestone 0 | [`SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) §4 |
| §5 Calibration — formula, not a vibes-check | [`SDLC-02-Governance.md`](../SDLC/SDLC-02-Governance.md) §4 |
| §6 The linter is the second reviewer, not the notary | [`SDLC-02-Governance.md`](../SDLC/SDLC-02-Governance.md) §3.3 |
| §7 Cooldowns: variable duration, gated on a recorded rest check (v3.5) | [`SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) §5 |
| §8 What's kept unchanged from predecessor documents | (subsumed by the rewrite; the rewrite is the new baseline) |
| §9 Explicit unknowns — do not silently resolve these | [`SDLC-03-InterfaceFreeze.md`](../SDLC/SDLC-03-InterfaceFreeze.md) §7 |
| §10 What changed across the v3.x lineage (consolidated changelog) | (subsumed; the v3.x lineage is historical. The rewrite is v4.0.) |

The original v3.4(3) file is preserved at [`archive/pre-A3-audit/Architecture/CrossCutting/SDLC-AGRD-v3.4.3.md`](../../archive/pre-A3-audit/Architecture/CrossCutting/SDLC-AGRD-v3.4.3.md) for historical reference.

---

## Authority

This redirect is a transitional artifact. Once all SDLC rewrite batches are merged and references throughout the repo are updated to point directly to [`Architecture/SDLC/`](../SDLC/), this file will be removed and the redirect will be replaced by a 404 (or, more practically, by references that already point to the new location).

Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md), the SDLC methodology is canonical. The new documents in [`Architecture/SDLC/`](../SDLC/) are the canonical expression of that methodology.

---

## Cross-References

- [`Architecture/SDLC/README.md`](../SDLC/README.md) — index of SDLC documents.
- [`Architecture/SDLC/SDLC-01-Foundations.md`](../SDLC/SDLC-01-Foundations.md) — Spiral Deepening model, depth scale, laps, milestones, cooldowns.
- [`Architecture/SDLC/SDLC-02-Governance.md`](../SDLC/SDLC-02-Governance.md) — Two-DAG model, Eligible(X) formula, four-tool lint separation.
- [`Architecture/SDLC/SDLC-03-InterfaceFreeze.md`](../SDLC/SDLC-03-InterfaceFreeze.md) — Interface freeze, ADR format, Fidelity Bar, AUTHORING_GUIDE.
- [`archive/pre-A3-audit/Architecture/CrossCutting/SDLC-AGRD-v3.4.3.md`](../../archive/pre-A3-audit/Architecture/CrossCutting/SDLC-AGRD-v3.4.3.md) — original v3.4(3) file (historical).
