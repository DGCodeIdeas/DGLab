# ADR-014: Ratify SDLC-AGRD v3.4(3) as Canonical Software Development Lifecycle

**Status:** Accepted

**Date:** 2026-08-12

**Author:** DGCI (solo tech lead), with gap-hunting review by Kimi, Z.ai, Claude

---

## Context

The DGLab project has operated under three competing SDLC models since inception:

1. **AGRD v1.0 (Kimi)** — Radial Incremental, 3-engineer parallel model, 48-week claim (actually 62 weeks due to arithmetic error).
2. **AGRD v2.0 (Z.ai)** — ADR-Gated Continuous Architecture on Shape Up, sequential-with-parallel-tail, still assumed 3 engineers.
3. **SDLC-AGRD v3.x series** — Solo-mode Spiral Deepening, iteratively refined through v3 → v3.1 → v3.2 → v3.3 → v3.4 → v3.4(3).

Each version had a real flaw the next fixed (see `SDLC-HISTORY.md` §1). v3.4(3) is the first version where every section is internally consistent with every other section — no global-floor language contradicting per-blueprint floors, no build-rate formula applied to deepening work, no ambiguous triggers, no unhandled edge cases in widening.

The team reality (1 solo tech lead + 1 marketer + 1 media) invalidates every predecessor's 3-engineer assumption. v3.4(3) is the first SDLC sized for that reality.

## Decision

**Ratify `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3) as the canonical, sole SDLC for the DGLab project.**

All predecessor documents (`AGRD_v1_0.md`, `AGRD-v2.md`, `SDLC-AGRD-v3`, `v3.1`, `v3.2`, `v3.3`, `v3.4`, `v3.4(2)`) are superseded. They remain in `Design_Models_Misc/` and archive directories for historical reference only.

## Consequences

### Positive

- Single source of truth for methodology — no competing models confusing agents or the tech lead.
- Calibrated to actual team size (1 engineer) — all estimates, gates, and ceremonies sized correctly.
- Spiral Deepening (vertical slices, not horizontal rings) prevents the late-integration-surprise failure mode that would be fatal solo.
- Interface freeze + per-blueprint relative floor (§2.1, §4.3) replaces Ring Lock with a model safe for matrix-style deepening.
- Cooldown 0 (§3) gives marketer/media a stable target before any code ships.
- Milestone 0 (§4) is a calibration instrument — every downstream estimate derives from measured `W`, not assertion.

### Negative / Risks

- **No lap data yet.** v3.4(3) explicitly states it will not iterate to v3.5 without lap-1 data (§10). If Milestone 0 exceeds 8 weeks, the solo-mode assumption itself may be wrong — this is a stop-the-line signal, not a calibrate-forward event.
- **Deepening throughput unknown.** §5's `throughput = N / W` is build-only (depth 0→2). Whether deepening (2→5) is faster or slower is genuinely unknown until Bet 1 runs. The projection is honest about this, but it means total timeline remains unstateable.
- **Lint is manual, not fully automated.** `run.php` performs 3 checks today; the §6 expansion target (Pulse 6-tuple consistency, naming drift, soft-freeze auto-reject, blueprint-fidelity structural diff) is aspirational. `.github/workflows/architecture-lint.yml` wires the existing 3 checks into CI; expanded checks remain cooldown work.
- **Agent orchestration is new.** `PROMPTS.md` + `MEMORY.md` are first-of-kind for this project. Their effectiveness will be validated only after agents actually run against them.

## Rejected Alternatives

1. **Keep v2.0 as canonical, stretch estimates 3×.** Rejected: 3× stretching doesn't account for the qualitative difference between 3-person review capacity and 1-person attention bottleneck. AI agents multiply implementation throughput, not judgment.
2. **Adopt v3.4 without the (3) correction.** Rejected: The lap-1 widen exclusion for CORE-16/HUB-04 was defensive but unnecessary. Keeping it would constrain widening for a risk that verification showed probably won't materialize — planning against an assumption instead of a check.
3. **Wait for v3.5 before ratifying.** Rejected: §10 explicitly says "stop iterating without lap data." Gap-hunting data-dependent unknowns is analysis paralysis. Ratify v3.4(3) now, amend after lap-1 data if needed.

## Related

- `SDLC-AGRD.md` (canonical methodology body)
- `PROMPTS.md` (agent operating instructions)
- `MEMORY.md` (agent entry-point)
- `SDLC-HISTORY.md` (version lineage and flaw history)
- `DISCREPANCY-REGISTER.md` (D-01, D-12 resolved by this ratification)
- `OPEN-DECISIONS.md` (OD-06 resolves during Milestone 0; OD-02 in Cooldown 1)

---

### Provenance

Drafted in response to `DISCREPANCY-REGISTER.md` D-06 (AGRD ratification gap) and the broader need to establish a single canonical SDLC after the v3.x refinement chain. Consolidation commit `bd25141b` (2026-08-10) prepared the documents; this ADR ratifies them.
