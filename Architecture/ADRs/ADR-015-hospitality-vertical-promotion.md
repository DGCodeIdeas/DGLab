# ADR-015: Promote the Hospitality Vertical from Design to Canonical

**Status:** **Proposed** — not yet Accepted. Ratification as Accepted is deferred until lap 1
completes and the hospitality vertical's V1 track (wk 17–29 per `HOSPITALITY-VERTICAL.md` §3) is
empirically validated against actual integration with the Hub services it depends on (Bet 3 Hub
Full ring lock is the gating dependency). Until then, the five blueprints and this ADR are
*de facto* canonical (they exist in the repo, the linter accepts them, the counts include them);
ratification as Accepted locks the decision against future reversal.
**Date:** 2026-08-12
**Deciders:** DGLab architecture team
**Closes:** DISCREPANCY-REGISTER.md D-02 (96 vs 101 count) and D-15 (hospitality references fail
`run.php` check 1 by design)

## Context

The DGLab Sovereign Stack has carried the hospitality vertical as **design-only** since the
2026-08-10 consolidation pass. The five blueprints (`ISPOKE-26` Sovereign Reservations, `ISPOKE-27`
Sovereign Front Desk, `ESPOKE-16` Sovereign Booking Portal, `ESPOKE-17` Sovereign Concierge,
`ESPOKE-18` Sovereign Mobile Check-in) were produced in a Kimi chat session and preserved in
`Architecture/CrossCutting/HOSPITALITY-VERTICAL.md` as a design artifact — but never committed
to `Architecture/Spoke/Internal/` or `Architecture/Spoke/External/`. `ADR-015` itself was named
as the future home for the promotion decision but never authored.

This left two open discrepancies:

1. **D-02 (High severity):** the canonical blueprint count was 96, but multiple drafts (Kimi chat,
   `MEMORY(1).md`, `Design_Models_Misc/`) claimed "101 total (96 canonical + 5 hospitality)".
   The 5 hospitality blueprints were not canonical — they were design artifacts. The mismatch
   was corrected in `MEMORY.md` §1 and `REPO-STATE-AUDIT.md` §1 to "96 canonical; 5 hospitality
   designed-not-committed," but the underlying gap (no committed blueprints) remained.

2. **D-15 (Informational):** because `INDEX.md` §2's canonical range was `ISPOKE-01..25` and
   `ESPOKE-01..15`, `Verification/lint/run.php` check 1 (reference existence) flagged every
   `ISPOKE-26`/`ISPOKE-27`/`ESPOKE-16`/`ESPOKE-17`/`ESPOKE-18` reference as an "undefined
   reference" — in `HOSPITALITY-VERTICAL.md` (25 references), `DISCREPANCY-REGISTER.md` D-02
   (2 references), `REPO-STATE-AUDIT.md` §1 (1 reference), `WHEEL-RECONCILIATION.md` §6
   (1 reference), `MEMORY.md` §1 (1 reference), `WORKLOG.md` (1 reference). The linter was
   working correctly — it was the mechanical proof behind D-02. But it meant the linter could
   not be wired to CI (D-03) without producing a permanent red signal.

The promotion decision was always intended — `HOSPITALITY-VERTICAL.md` §7 documented the four
steps to make the design canonical: drop the five blueprints, add `ADR-015`, update `INDEX.md`
§2, extend `run.php` lint scope. This ADR executes that decision.

## Decision

**The five hospitality blueprints are promoted from design-only to canonical, effective
2026-08-12.** Ratification as Accepted is deferred until lap 1 completes (per `SDLC-AGRD.md`
§10); until then, this ADR records the decision-in-principle and closes D-02 and D-15 by
artefact-existence + linter-pass.

- **Canonical blueprints added:**
  - `Architecture/Spoke/Internal/ISPOKE-26.md` — Sovereign Reservations (Reservation Ops)
  - `Architecture/Spoke/Internal/ISPOKE-27.md` — Sovereign Front Desk (Front Desk Ops)
  - `Architecture/Spoke/External/ESPOKE-16.md` — Sovereign Booking Portal (Guest Booking Portal)
  - `Architecture/Spoke/External/ESPOKE-17.md` — Sovereign Concierge (AI Concierge)
  - `Architecture/Spoke/External/ESPOKE-18.md` — Sovereign Mobile Check-in (Guest Mobile Check-in)
- **Canonical count:** 96 → **101**. `INDEX.md` §1, §2.3, §4 updated. `MEMORY.md` §1,
  `REPO-STATE-AUDIT.md` §1, `WHEEL-RECONCILIATION.md` §6 updated.
- **Linter scope:** `Verification/lint/run.php` `buildValidIds()` and `checkStructure()` extended
  to `ISPOKE-01..27` and `ESPOKE-01..18`. `run.php` check 1 now passes cleanly on every doc that
  references the hospitality IDs — D-15 is mechanically resolved.
- **Naming convention:** the five blueprints follow the cosmic-name scheme ratified in commit
  `07e7de8` (Sovereign Atlas / Penumbra / Pulsar et al.). No new "Sovereign X" names are
  introduced that collide with existing components.
- **Hub extensions:** `HUB-22` (Sovereign Ledger) gains a Payment Gateway extension (Stripe/Adyen
  adapter) and `HUB-02` (Sovereign Cache & State) gains an Availability Cache extension. These
  are extensions to existing Hub blueprints, not new Hub blueprints — they are documented in the
  spoke blueprints themselves and do not require ADR-015.1 or similar. The Hub contract surface
  is unchanged; the extensions are additive adapters.
- **ADR-015 itself:** filed as **Proposed**. Ratification as Accepted is deferred until the
  hospitality V1 track (wk 17–29 per `HOSPITALITY-VERTICAL.md` §3) ships against Bet 3 Hub Full.
  If V1 uncovers a design flaw that requires pulling back the blueprints, this ADR is reverted
  and D-02/D-15 are re-opened.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **Promote to canonical as Proposed, ratify post-V1** (this ADR) | Closes D-02 and D-15 mechanically; lets `run.php` lint pass cleanly so D-03 (CI wiring) becomes unblocked; gives the hospitality vertical a single citeable decision record; matches the always-intended `HOSPITALITY-VERTICAL.md` §7 plan | None — ratification is conditional on V1 shipping, not unconditional adoption | **Proposed** |
| **Defer promotion to post-Bet-3 ring lock** | Lets the Hub services stabilize before any hospitality blueprint is canonical | Leaves D-02 and D-15 open indefinitely; the linter stays red; D-03 (CI wiring) stays blocked on a false signal | Rejected — the design is stable enough to commit; the bet-3 dependency is documented in each blueprint's Build Status |
| **Promote only the 2 ISPOKEs (staff-side), defer the 3 ESPOKEs** | Smaller blast radius; staff-facing blueprints have a narrower attack surface | Incoherent — the hospitality Pulse requires both sides; promoting ISPOKE-26 without ESPOKE-16 leaves the booking Pulse half-defined | Rejected — the vertical is a unit |
| **Add the IDs to `INDEX.md` and `run.php` without authoring the blueprints** | Trivially closes D-15 | Silently contradicts D-02 — the blueprints still don't exist, but the linter would no longer flag them; this is the exact "fix" D-15 says NOT to do | Rejected — explicitly forbidden by `HOSPITALITY-VERTICAL.md` §"Lint expectation" |
| **No ADR, just commit the blueprints** | Zero additional work | Leaves D-02 open at the governance level (no decision record); future agents cannot point at "why these 5 are canonical"; violates Governance Rule 8 | Rejected — the cost of one ADR is trivial vs. an unanchored count change |

## Consequences

**Positive:**
- **D-02 closes (ratification pending).** The 96-vs-101 mismatch is mechanically resolved — the
  canonical count is 101, the 5 hospitality blueprints are committed, the linter accepts them.
  D-02 fully closes when this ADR is promoted to Accepted after V1 ships; until then, the count
  is documented as 101 with the decision-in-principle recorded.
- **D-15 closes.** `run.php` check 1 no longer flags `ISPOKE-26/27` / `ESPOKE-16/17/18` — they
  are in the canonical ID range. The 25+1+1+1+1+1 = 30 previously-flagged references across 6
  docs are now valid. The linter can be wired to CI (D-03) without a permanent red signal.
- **D-03 unblocked.** With D-15 resolved, `architecture-lint.yml` can be wired to GitHub Actions
  on PR/push without producing a false failure. (The wiring itself is a separate task — this ADR
  only removes the blocker.)
- **The hospitality vertical has a decision record.** Future agents asking "why are these 5 in
  the repo when `HOSPITALITY-VERTICAL.md` said designed-only?" can be pointed at this ADR.
- **Naming consistency.** The 5 blueprints follow the cosmic-name scheme (Sovereign X), matching
  the ISPOKE-02 / ISPOKE-11 / ESPOKE-12 renames in commit `07e7de8`. No new collisions are
  introduced.

**Negative (and how they are contained):**
- **Canonical count grows by 5.** Every doc that cites "96 blueprints" must be updated. This ADR
  updates `INDEX.md` §1/§4, `MEMORY.md` §1, `REPO-STATE-AUDIT.md` §1, `WHEEL-RECONCILIATION.md`
  §6. Future docs that cite the count should cite 101 (or "101 canonical, 1 Proposed HUB-31"
  where the distinction matters).
- **Implementation pressure.** Promoting to canonical creates an implicit expectation that the
  blueprints will be implemented. This is contained by the Build Status field in each blueprint
  (all five are marked 📝 **Documented — ready for implementation**, blocked on `CORE-02` and
  Bet 3 Hub Full) and by the V1 timeline in `HOSPITALITY-VERTICAL.md` §3 (wk 17–29).
- **Reversal cost.** If V1 uncovers a fatal design flaw, reverting this ADR requires (a) deleting
  5 blueprint files, (b) reverting `INDEX.md` / `run.php` / `MEMORY.md` / `REPO-STATE-AUDIT.md`
  / `WHEEL-RECONCILIATION.md` changes, (c) re-opening D-02 and D-15. This is a documented,
  bounded cost — not a silent drift.

**Neutral:**
- This ADR does not change any code, any interface, or any existing blueprint. It adds 5 new
  blueprints and 1 ADR, and updates 5 cross-cutting docs + the linter. Its sole effect is to
  close documentation-governance gaps D-02 and D-15.
- `MEMORY.md` §7 (current state snapshot) gains a line: "Hospitality vertical promoted to
  canonical by ADR-015 (Proposed); count is 101."

## Links

- Closes: `DISCREPANCY-REGISTER.md` D-02 (96 vs 101 count), `DISCREPANCY-REGISTER.md` D-15
  (hospitality references fail `run.php` check 1 by design)
- Related ADRs: ADR-013 (MySQL 8 InnoDB primary datastore — the 5 blueprints' DDL follows this),
  ADR-009 (ULID over UUID — the 5 blueprints' PKs follow this), ADR-003 (ES256 JWT signing —
  ESPOKE-18's guest-session tokens follow this), ADR-014 (AGRD canonical SDLC — the hospitality
  V1/V2/V3 timeline follows the v3.4(3) spiral-deepening model)
- Related documents: `Architecture/CrossCutting/HOSPITALITY-VERTICAL.md` (the design source —
  §1 domain mapping, §2 Pulse flows, §3 AGRD integration, §4 new components, §5 tenant model,
  §6 integration rule, §7 promotion steps executed by this ADR), `Architecture/INDEX.md` §2
  (canonical ID map updated), `Architecture/Verification/lint/run.php` (linter scope extended),
  `Architecture/CrossCutting/PULSE-MODEL.md` (the 5 hospitality Pulse flows are concrete
  instances of the 6-tuple documented here)
- Related findings: D-02 (count mismatch), D-15 (linter false-failure), D-03 (CI wiring —
  unblocked by D-15 closure)
