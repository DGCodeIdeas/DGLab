# SDLC — Software Development Lifecycle

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This directory contains the rewritten SDLC documents (PR #316, 2026-10-07). The rewrite is a starting point — the methodology will continue to evolve as implementation reality tests it. The number of SDLC documents written is not the number of SDLC documents that will exist. See [`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md).

---

## What This Directory Contains

The SDLC documents specify DGLab's development methodology — Spiral Deepening for a Solo Tech Lead. The methodology is being rewritten (per Tech-Lead directive 2026-10-06: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time").

### Current Documents (Batch 1 — PR #316)

| Document | Subject | Status |
|---|---|---|
| [`SDLC-01-Foundations.md`](SDLC-01-Foundations.md) | Spiral Deepening model, depth scale 1-6, laps, milestones, cooldowns, versioning, solo invariants | Canonical (rewrite of SDLC-AGRD §1-4) |
| [`SDLC-02-Governance.md`](SDLC-02-Governance.md) | Two-DAG model, edge dimensions, Eligible(X) formula, four-tool lint separation, PASS/FAIL/UNVERIFIED invariant, calibration | Canonical (rewrite of SDLC-AGRD §5-7 + ADR-021 governance) |
| [`SDLC-03-InterfaceFreeze.md`](SDLC-03-InterfaceFreeze.md) | Interface freeze rules, ADR format, ADR-gated changes, OD lifecycle, Fidelity Bar, AUTHORING_GUIDE, explicit unknowns | Canonical (rewrite of SDLC-AGRD §8-10 + authoring/freeze rules) |

### Superseded Material

The original `SDLC-AGRD.md` (v3.4(3), 37 KB, single file) at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md) is superseded by the documents in this directory. It is retained as a historical redirect during the rewrite transition. Once the rewrite is complete (all batches merged), the redirect will be removed and the file archived.

---

## Doctrines Applied

Per Tech-Lead directive, the rewrite applies these binding doctrines:

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — every SDLC document carries a blind-spot awareness banner; the doctrine's 6 binding rules constrain how findings are closed and audits are interpreted.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — depth 5 (production hardening) is binding to the doctrine; the 5-class error taxonomy informs exception type freezing.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires Integrity Gate convergence criteria; the gate is a finite stopping condition for the integrity phase.
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Eligible(X) formula gates admission on dependency closure from both DAGs; build orders are generated, not authored.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — interface freeze rules (per SDLC-03 §1) are enforced via the FROZEN-CONTRACTS registry.

---

## Future Batches

Per Tech-Lead directive: "three documents at a time." This is Batch 1. Future batches will add more SDLC documents as the methodology continues to be specified with proper detail. Candidate future documents (not yet written):

- **SDLC-04: Cooldown Mechanics** — variable-duration cooldowns, rest-check gates, mini-cooldowns (OD-11), worklog reconciliation protocol.
- **SDLC-05: AI-Assisted Development Protocol** — how AI assistants participate, what they may and may not decide, verification conditions for AI-produced work.
- **SDLC-06: Generator Specifications** — deterministic generation of build orders, DAGs, and other derived artifacts; CI regeneration checks.

These are candidates, not commitments. The Tech Lead decides which batches come next based on implementation reality.

---

## Authority

This directory is canonical for SDLC methodology. Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) (ratified AGRD as canonical SDLC), the SDLC documents here are binding on all DGLab development.

Where an SDLC document and a per-package blueprint disagree, the SDLC document wins (methodology governs implementation). Where an SDLC document and a doctrine (Blind-Spot, Nuclear-Grade) disagree, the doctrine wins (per the doctrine's own §0).

This directory is a starting point. It is not a complete specification.
