# SDLC-AGRD v3.1: Spiral Deepening for a Solo Tech Lead

**Status:** Draft, ready for Ring Lock review.
**Supersedes:** `AGRD_v1_0.md` (Kimi, 3-engineer parallel model), `AGRD-v2.md` (Z.ai, 3-engineer
sequential-with-parallel-tail model), `SDLC-AGRD-v3` (fixed a self-inconsistent blueprint count in
Milestone 0, added the depth scale, Phase structure, post-Milestone-0 bet shape, Milestone-0-specific
kill trigger, lint-expansion budgeting, Cooldown 0 duration + content Ring Lock, and OD-02/OD-06
re-sequencing — per external review), and the "3 engineers" assumption embedded in all predecessors.
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

**Duration: 2 weeks — matching the general cooldown length in §7, deliberately, not as a special
unstated one-off.** Freezing three artifacts with a real marketer/media review cycle behind each isn't
faster than an ordinary cooldown, and giving it an unstated duration would leave Milestone 0's actual
start date unbounded.

With Cooldown 0, marketer and media genuinely start unblocked in week 1. Without it, "non-blocking
track" is true in name only.

**A content Ring Lock, not just a content freeze.** Cooldown 0 freezes the *contracts* content is
written against; it doesn't by itself catch drift in the *content* over time. Every subsequent cooldown
(§7) includes one marketer/media review checkpoint — content checked against the current state of its
frozen contracts, same cadence as engineering's own cooldown reconciliation. Without this, content
drift gets discovered at integration instead of at a cooldown, which is exactly the failure mode
Cooldown 0 exists to prevent, just deferred to a later point in the project instead of eliminated.

## 4. Milestone 0

**Scope — 8 blueprints, corrected count (v3 stated "~10" against a list of 8; that mismatch is fixed
here, not carried forward — the calibration in §5 depends on this count being honest):** minimal
`CORE-02`, `CORE-04`/`05`/`06` stubs, one Hub service (`HUB-01`), `BRIDGE-01` stub, one Internal Spoke
(`ISPOKE-09`, Codex), one External Spoke (`ESPOKE-01`, Canvas).

**Depth applies here too (§4.1):** most of these targets are depth 1–2 for Milestone 0's purposes.
`BRIDGE-01`, `ISPOKE-09`, and `ESPOKE-01` need at least depth 2 (happy path) — a stub alone doesn't
demonstrate a genuine round trip, which is the entire point of the milestone.

**Success criterion:** a real HTTP request enters at the Outer Rim, crosses the Inner Rim, resolves
against the Inner Spoke, and returns — the actual synchronous-radial Pulse trace, not a diagram of it.

**Kill trigger, specific to Milestone 0:** if it exceeds 8 weeks, that is not "calibrate and push
forward" — it's a signal the architecture or the solo-mode assumption itself may be wrong. Stop and
reassess before continuing, rather than folding an 8+-week result into §5's formula as if it were a
normal data point.

**This is a calibration instrument, not a feature.** Its job is producing a real number for §5, not
shipping anything user-facing.

### 4.1 Depth scale

| Depth | Meaning |
|---|---|
| 1 | Stub — interface exists, no real logic |
| 2 | Happy path — the intended case works end-to-end |
| 3 | Error paths — failure modes handled, not just the happy case |
| 4 | Observability — logging, metrics, audit wired per its blueprint's CI criteria |
| 5 | Production hardening — stated performance targets met, security-reviewed |
| 6 | At-scale verified — load-tested against real, not assumed, targets |

"Production depth" (§2.1) means depth 5 unless a blueprint's own document states otherwise. The bet
matrix (§2) has this scale as its second axis.

### 4.2 After Milestone 0 lands — the missing chapter

§5 describes the *measurement*; this describes what happens with it. Once Milestone 0 completes and
throughput is known, the first deepening bet is scoped as: pick the ring with the lowest average depth
across its Milestone-0-touched blueprints, and deepen it to depth 3 (error paths) before touching
anything else. Concretely, if `CORE-02`/`04`/`05`/`06` are sitting at depth 1–2 after Milestone 0 while
`BRIDGE-01`/`ISPOKE-09`/`ESPOKE-01` are at depth 2, Bet 1 is "Core to depth 3" — not a vague "keep
deepening," a named target ring and depth. Each subsequent bet repeats this pick-lowest-ring rule.

### 4.3 Phase structure

The spiral needs laps, not just a direction:

- **Phase 1** — every ring has at least one blueprint at depth 3 (error paths handled somewhere in
  each ring).
- **Phase 2** — every ring's Milestone-0 blueprints reach depth 4 (observability wired stack-wide).
- **Phase 3** — every ring's Milestone-0 blueprints reach depth 5 (production-ready) before any
  blueprint goes to depth 6.

Only after Phase 3 does the matrix expand beyond Milestone 0's original 8 blueprints into the remaining
88. Without this, "spiral" stays a metaphor instead of a sequenced plan.

## 5. Calibration — formula, not a vibes-check

```
N  = blueprints in Milestone 0 = 8 (corrected — see §4)
W  = weeks Milestone 0 actually took (measured, not estimated)
throughput = N / W                    (blueprints/week)
remaining  = 96 − N = 88 blueprints
projected  = remaining / throughput = 11W   (weeks, bet-work only, excludes cooldowns)
```

Example: Milestone 0 takes 4 weeks → 44 weeks of remaining bet-work. Takes 6 weeks → 66 weeks.

**On the "40–70 week" range stated in the prior draft of this document: it was wrong, and re-deriving
it properly matters, not just re-labeling it.** It was built on the uncorrected N=10, and a review of
this document correctly flagged that the arithmetic didn't actually derive it from the stated formula.
But re-running that same formula with the *correct* N=8 doesn't produce a lower, tighter number — it
produces a *higher* one, because a smaller Milestone 0 measured over the same W implies lower
throughput. At W=4–6: 44–66 weeks of bet-work, before cooldowns (§7, now 2 weeks each) and before
Cooldown 0 (§3, now 2 weeks, fixed). Cooldown *count* isn't a number this document will manufacture —
under the matrix/Phase model, bet count isn't fixed in advance the way it was in the linear model, so
a precise cooldown-overhead figure would be exactly the kind of false precision this section exists to
avoid. Treat total elapsed time as **Milestone 0 (W) + Cooldown 0 (2wk) + bet-work (11W) + cooldown
overhead, re-estimated once Phase 1 establishes a real bet cadence** — not as a single number, and not
until Phase 1 actually produces one.

**Recalibrate throughput after every subsequent milestone, not once.** Within-ring pattern reuse will
very likely change throughput as the project goes on — probably faster inside a ring after its first
blueprint, possibly slower if a ring turns out harder than its first blueprint suggested. Only the
*current* projection, refreshed at every Phase boundary, is ever trusted.

**Until Milestone 0 completes and Phase 1 establishes cadence:** no total is stated as fact anywhere in
this document, including here.

## 6. The linter is the second reviewer, not the notary

With one engineer, `Verification/lint/run.php` is the only thing that catches what a second reviewer
would have. Scope expands accordingly — and this expansion is itself real engineering work, budgeted
explicitly rather than left to compete silently with blueprint deepening: **lint-scope expansion
happens during cooldown weeks (§7), never during bet weeks.** If it doesn't fit in a cooldown, it
carries to the next one rather than eating into bet time.

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

### 8.1 OD sequencing under solo mode — two entries re-timed, not re-decided

Solo, the cost of a wrong architectural call lands entirely on one person, with no second engineer to
catch it in review. Two of the six open decisions need earlier resolution than the timing implied
elsewhere:

- **OD-06** (`ADR-010` opcache baseline, blocked on `CORE-02`) is mechanically forced to resolve
  **during Milestone 0** — `CORE-02` is the first thing built there, so there's no later point where
  this stays genuinely open without also blocking the milestone itself.
- **OD-02** (post-quantum/algorithm-agility JWT signing) resolves **by the end of Milestone 0**, not
  folded into Cooldown 0. Cooldown 0 is scoped tightly to content-facing contracts (§3) so
  marketer/media can start on schedule; a deep crypto-architecture decision doesn't belong in that
  phase without diluting what it's for. `CORE-16`/`HUB-04` groundwork is already being scoped by the
  end of Milestone 0 regardless, which is the natural point to close it out — earlier than the
  originally-planned "Bet 6," later than Cooldown 0.

OD-01, OD-03, OD-04, OD-05 keep their existing owners and timing from `OPEN-DECISIONS.md` — nothing
about solo mode changes their urgency.

## 9. Explicit unknowns — do not silently resolve these

- The actual Milestone 0 duration, and therefore every downstream week-count.
- Whether `CORE-02`'s real build time matches the "unblocks everything" framing it's had since Finding
  8, or turns out harder than expected as the first thing built solo.
- Whether blueprint-fidelity drift-checking (§6) needs to become a harder automated gate sooner than
  planned, based on how much real drift Milestone 0 and the first few deepening bets actually produce.

Per Governance Rule 9 (this session's own precedent): open questions get recorded, never silently
resolved. The three above go in `OPEN-DECISIONS.md` as new entries once this document is ratified.
