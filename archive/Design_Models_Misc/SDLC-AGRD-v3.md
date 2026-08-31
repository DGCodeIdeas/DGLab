# SDLC-AGRD v3: Spiral Deepening for a Solo Tech Lead

**Status:** Draft, ready for Ring Lock review.
**Supersedes:** `AGRD_v1_0.md` (Kimi, 3-engineer parallel model), `AGRD-v2.md` (Z.ai, 3-engineer
sequential-with-parallel-tail model), and the "3 engineers" assumption embedded in both.
**Team reality this is written for:** 1 tech lead (solo, AI-agent-augmented — Cline/Jules), 1 marketer,
1 media. Every estimate, gate, and ceremony below is sized for that team, not a hypothetical one.

---

## 1. Why the prior team-size assumption invalidates more than the schedule

Both predecessor documents sized 6-week bets and (in v2's case) a 3-way parallel Bet 6/7/8 stretch on
collaborative throughput from three engineers. With one, two things are true simultaneously:

- **All engineering parallelism is void, not risky.** Sequential, no exceptions.
- **The per-bet week estimates aren't just "3× longer" — they're wrong in kind, not just magnitude.**
  AI agents (Cline, Jules) multiply *implementation* throughput; they don't substitute for the *review
  and judgment* capacity three people provided. One person's attention still gates every merge. That
  bottleneck doesn't scale down linearly with headcount, so no simple division of the old estimates
  produces a trustworthy number.

Marketer and media are real, non-blocking capacity — just not engineering capacity, and not usable
against the critical path without the fix in §3.

**Conclusion: no timeline in this document is trustworthy until Milestone 0 (§4) produces a measured
number. Anywhere a week-count appears below, treat it as a placeholder, not a commitment.**

## 2. Methodology shift: Spiral Deepening replaces Radial Incremental

The prior model was **horizontal completion of a vertical ring**: finish Core, then Hub, then Spokes.
That's viable for a team that can absorb a late integration surprise. Solo, a surprise at week 30 (the
first time two rings actually meet) is close to fatal — there's no one else to reprioritize onto it.

The new model is **vertical deepening of a horizontal slice**:

- **Milestone 0** builds a genuine end-to-end Pulse — Exemplar One, made real — touching all six rings
  at minimal depth (roughly 10% of Core's eventual surface, one Hub service, one Bridge stub, one
  Internal Spoke, one External Spoke). This is the walking skeleton.
- **Every subsequent bet deepens one ring's slice toward production quality**, informed by what
  Milestone 0 already proved works end-to-end.
- The bet map is therefore a **matrix — blueprints × depth level — not a linear sequence.** A given
  blueprint may be touched across several non-adjacent bets as it deepens.

### 2.1 Interface freeze vs. implementation depth — the gap this creates, closed

Spiral Deepening has one failure mode the original Ring Lock didn't: if a blueprint gets revisited
across many bets as it deepens, every dependent could be building against a moving target the whole
project, which is strictly worse than Ring Lock's original guarantee ("this interface is stable now").
**Fix: separate the two things Ring Lock used to bundle together.**

- **Interface freeze** — a blueprint's public contract (its `interface`s, its DTO shapes, its declared
  dependencies) freezes the first time it's implemented at *any* depth, including Milestone 0's minimal
  version. This is a one-time event per blueprint, early, cheap to get right because the surface is
  small.
- **Implementation depth** — the internal logic behind that frozen interface can deepen across many
  later bets without requiring a new freeze, *as long as the public contract doesn't change.*
- **Changing an already-frozen interface is an ADR-gated event**, same discipline as the old Ring Lock
  violation — not a casual edit, regardless of which bet is "supposed" to own that blueprint.

This is what makes the matrix model safe: dependents build against a contract that's stable from
Milestone 0 forward, even while the implementation behind it keeps deepening.

## 3. Cooldown 0 — before Milestone 0, not optional

Marketer and media working "unblocked" through `HUB-13` and `HUB-26` is only true for the *plumbing*.
Content written against an imagined UI gets thrown away when the real layout ships. **Cooldown 0**
happens before Milestone 0 starts and freezes three things:

1. **`ESPOKE-05`'s UI wireframe** — marketer writes copy against this, not the eventual implementation.
2. **`HUB-26`'s theme token contract** — media produces assets that fit these slots, not guessed
   dimensions.
3. **`HUB-13`'s string key taxonomy** — marketer writes strings against real keys from day one, not a
   retrofit pass later.

**Change control for Cooldown-0 artifacts:** these three freeze under the same ADR-gate discipline as a
Core interface change (§2.1) — not because they're architecturally risky, but because the entire point
of Cooldown 0 is giving marketer/media a stable target. Letting them drift casually reintroduces the
exact rework problem Cooldown 0 exists to prevent, just moved from "engineering vs. content" to
"content vs. re-content."

With Cooldown 0, marketer and media genuinely start unblocked in week 1. Without it, "non-blocking
track" is true in name only.

## 4. Milestone 0

**Scope (~10 blueprints):** minimal `CORE-02` (unblocks everything — see prior session's Finding 8),
`CORE-04`/`05`/`06` stubs (HTTP Message → Middleware → Router, just enough to route one request), one
Hub service (`HUB-01`, Config — lowest-dependency Hub component), `BRIDGE-01` stub (enforcement shape
only, not full facet set), one Internal Spoke (`ISPOKE-09`, Codex — already the concrete example used
throughout this session's Exemplar One), one External Spoke (`ESPOKE-01`, Canvas).

**Success criterion:** a real HTTP request enters at the Outer Rim, crosses the Inner Rim, resolves
against the Inner Spoke, and returns — the actual synchronous-radial Pulse trace, not a diagram of it.

**This is a calibration instrument, not a feature.** Its job is producing a real number for §5, not
shipping anything user-facing.

## 5. Calibration — formula, not a vibes-check

```
N  = blueprints in Milestone 0 (≈10)
W  = weeks Milestone 0 actually took (measured, not estimated)
throughput = N / W                    (blueprints/week)
remaining  = 96 − N ≈ 86 blueprints
projected  = remaining / throughput   (weeks)
```

Example: Milestone 0 takes 6 weeks → 1.67 blueprints/week → 52 more weeks. Takes 4 weeks → 2.5/week →
34 more weeks. The spread is wide on purpose — it's exactly why no total is stated as fact anywhere in
this document.

**Refinement beyond the original formula: recalibrate after every subsequent milestone, not once.**
Throughput on later blueprints in a given ring will very likely differ from Milestone 0's — probably
faster, as patterns repeat within a ring; possibly slower, if a ring turns out harder than its first
blueprint suggested. Treating the post-Milestone-0 number as fixed for the rest of the project just
relocates the "false precision" problem instead of solving it. Recalibrate `throughput` at the end of
every milestone; only the *current* projection is ever trusted.

**Until Milestone 0 completes:** the timeline is "TBD, estimated 40–70 weeks depending on solo-with-
agents velocity." That range is a placeholder for planning purposes, explicitly not a commitment.

## 6. The linter is the second reviewer, not the notary

With one engineer, `Verification/lint/run.php` is the only thing that catches what a second reviewer
would have. Scope expands accordingly:

| Check | Status |
|---|---|
| Cross-reference validity against `INDEX.md` (existing) | Already load-bearing |
| Pulse 6-tuple consistency across repos | Promote from nice-to-have to required — this is now the only thing catching Structure-doc drift |
| Naming drift, folder vs. `INDEX.md` (OD-05) | Promote from "docs cleanup" to lint-enforced, not manually tracked |
| Soft-Freeze violations — a PR touching a frozen interface (§2.1) without a citing ADR | Auto-reject at CI, not a review comment |
| Blueprint-fidelity drift — does the implementation still match its `STRUCTURE-XX` spec | **Honest caveat, not oversold:** full semantic diffing of code against prose spec isn't a solved CI problem. Start as a lighter-weight periodic check (a scripted structural diff — class/interface names present, method signatures match — not full behavioral equivalence) rather than claiming this is a complete automated gate from day one. Tighten it as patterns emerge from real drift instances. |

The distinction on the last row matters: claiming an unimplemented capability is fully automated would
be exactly the kind of unverified assertion this whole audit exists to catch, just relocated into the
process document instead of the architecture docs.

## 7. Cooldowns: 2 weeks, not 1

A 3-person team's 1-week cooldown gave each person ~2.3 days of catch-up. Solo, one week has to cover
doc reconciliation, OD triage, refactor backlog, the review backlog that accumulated during the bet,
*and* recovery time nobody else provides by covering for you. Solo burnout carries higher risk than
team burnout precisely because there's no redundancy to absorb it. **Cooldowns are 2 weeks for the
duration of solo operation.** Total timeline goes up; sustainability goes up more, and an unsustainable
schedule that collapses at week 25 is a worse outcome than a longer one that actually completes.

## 8. What's kept unchanged from predecessor documents

- **The OD table** — adopted from `AGRD-v2.md` (Z.ai) as-is; it's the one that actually matches the
  live `Architecture/OPEN-DECISIONS.md`. `AGRD_v1_0.md`'s (Kimi) OD-03–06 table is discarded — it
  doesn't match the current repo and is internally self-contradictory about what OD-06 even refers to.
- **ADR-gating** on architectural decisions.
- **Kill-the-bet triggers**, unchanged in spirit — a bet that's clearly not converging gets killed, not
  ridden out.
- **Governance Rule 2** (no benchmark/timeline claim without a stated method) — this document's own
  §5 is the SDLC-level application of the same rule that governs individual blueprint performance
  claims.

## 9. Explicit unknowns — do not silently resolve these

- The actual Milestone 0 duration, and therefore every downstream week-count.
- Whether `CORE-02`'s real build time matches the "unblocks everything" framing it's had since Finding
  8, or turns out harder than expected as the first thing built solo.
- Whether blueprint-fidelity drift-checking (§6) needs to become a harder automated gate sooner than
  planned, based on how much real drift Milestone 0 and the first few deepening bets actually produce.

Per Governance Rule 9 (this session's own precedent): open questions get recorded, never silently
resolved. The three above go in `OPEN-DECISIONS.md` as new entries once this document is ratified.
