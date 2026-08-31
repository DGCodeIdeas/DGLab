# SDLC-AGRD: Spiral Deepening for a Solo Tech Lead

**Status:** Canonical. Supersedes `AGRD_v1_0.md` (Kimi, 3-engineer parallel model),
`AGRD-v2.md` (Z.ai, 3-engineer sequential-with-parallel-tail model), `SDLC-AGRD-v3`
(fixed a self-inconsistent blueprint count, added the depth scale and a one-shot Phase structure),
`SDLC-AGRD-v3.1` (replaced the one-shot Phase structure with a repeating lap structure; stated bet
duration; separated build/deepening throughput; fixed the OD-02/Milestone-0 contradiction),
`SDLC-AGRD-v3.2`/`v3.3` (per external review: fixed a real spec contradiction in the lap floor schedule
with a per-blueprint relative floor; verified `INDEX.md` §5.2's actual graph coverage against the live
repo), `SDLC-AGRD-v3.3`→`v3.4` (per external review: §4.2 brought in line with the per-blueprint floor;
resolved Bet 1's kill-trigger unit mismatch using `W`; closed the fully-widened-ring edge case;
disambiguated the §9 coverage-extension trigger's "50%"), and the "3 engineers" assumption embedded in
all predecessors — **and `v3.4(3)` (this version): dropped the lap-1 widen exclusion for `CORE-16`/
`HUB-04` after verifying OD-02 against their actual interfaces.**

**Team reality this is written for:** 1 tech lead (solo, AI-agent-augmented — Cline/Jules/Kilo), 1
marketer, 1 media. Every estimate, gate, and ceremony below is sized for that team, not a hypothetical one.

> **This document is §§1–10 of the canonical SDLC.** The operating-instructions module (agent prompts,
> capability taxonomy, bootstrap, anti-drift) is split out into the companion **`PROMPTS.md`** in this same
> folder. `MEMORY.md` is the curated entry-point every agent reads first (see `PROMPTS.md` §11.3 / this
> doc's §2.1).

---

## 1. Why the prior team-size assumption invalidates more than the schedule

Both predecessor documents sized 6-week bets and (in v2's case) a 3-way parallel Bet 6/7/8 stretch on
collaborative throughput from three engineers. With one, two things are true simultaneously:

- **All engineering parallelism is void, not risky.** Sequential, no exceptions.
- **The per-bet week estimates aren't just "3× longer" — they're wrong in kind, not just magnitude.**
  AI agents (Cline, Jules, Kilo) multiply *implementation* throughput; they don't substitute for the
  *review and judgment* capacity three people provided. One person's attention still gates every merge.
  That bottleneck doesn't scale down linearly with headcount, so no simple division of the old estimates
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
  version. This is a one-time event per blueprint, early, cheap to get right because the surface is small.
- **Implementation depth** — the internal logic behind that frozen interface can deepen across many later
  bets without requiring a new freeze, *as long as the public contract doesn't change.*
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

**A content Ring Lock, not just a content freeze.** Cooldown 0 freezes the *contracts* content is written
against; it doesn't by itself catch drift in the *content* over time. Every subsequent cooldown (§7)
includes one marketer/media review checkpoint — content checked against the current state of its frozen
contracts, same cadence as engineering's own cooldown reconciliation. Without this, content drift gets
discovered at integration instead of at a cooldown, which is exactly the failure mode Cooldown 0 exists
to prevent, just deferred to a later point in the project instead of eliminated.

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

> **Two distinct scales — do not confuse them.** The *blueprint-maturity* scale above (1=stub … 6=at-scale)
> is a property of each blueprint's implementation. The *Pulse entry-radius* scale (1=Outer Rim … 6=Core)
> is a property of where a runtime Pulse enters the Wheel (see `PULSE-MODEL.md`). They use overlapping
> numbers by coincidence; they measure different things.

### 4.2 After Milestone 0 lands — the missing chapter

§5 describes the *measurement*; this describes what happens with it.

**Bet duration — stated, not left open, with the unit mismatch resolved for Bet 1 specifically.** A bet's
time-box is the throughput-derived estimate for its scoped work (§5), with a 1.5× ceiling as its own kill
trigger — except Bet 1, where that formula can't actually be computed: §5's throughput is a *build* rate
(depth 0→2), and Bet 1 is *deepening* work (depth 2→3+), the exact unit mismatch §5/§9 warn about. **Bet 1's
ceiling uses `W` (Milestone 0's measured duration) directly, at 1.5×W** — not because build and deepening
throughput are assumed equal, but because `W` is the best available reference point before any real
deepening data exists. Bet 1's actual duration becomes the first true deepening-throughput baseline, and
Bet 2 onward derive their ceilings from that instead of from `W`.

**Bet scoping rule — per-blueprint, matching §4.3's floor, not a single ring-wide depth.** Pick the ring
with the lowest average depth across its currently-touched blueprints, and deepen its below-floor
blueprints to their own per-blueprint targets (§4.3) — there is no single shared "ring depth" to name,
since each blueprint's target depends on its own admission point. Concretely: if Milestone 0 leaves
`CORE-02` at depth 2 and `CORE-04`/`05`/`06` at depth 1 (illustrative — exact Milestone-0 exit depths per
component aren't pinned down until it actually runs), Bet 1 is "Core's below-floor blueprints to their
per-blueprint targets" — `CORE-02` to 3, `CORE-04`/`05`/`06` to 2 — not the single number "Core to depth 3"
a global floor would have implied.

### 4.3 Lap structure — widens and deepens every cycle, not deepen-then-widen

The prior draft of this document defined three one-shot phases that deepened Milestone 0's original 8
blueprints to depth 5 *before* admitting a 9th. An external review caught this correctly: that's not
Spiral Deepening, it's Radial Incremental at smaller scale — the same late-integration-surprise problem
this document exists to prevent, just relocated from "ring boundaries" to "blueprint #9." The fix proposed
alongside that catch (widen at two of three phase exits) was a real improvement but only covered a handful
of blueprints through three named phases — it can't reach 96 blueprints without repeating. So: laps, not
phases, and each lap does both things every time.

**Lap k, for k = 1, 2, 3…:**

1. **Widen.** For each ring that still has unadmitted blueprints, admit its next-most-depended-upon
   not-yet-touched blueprint at depth 1. (Once a ring is fully widened, it contributes no admission that
   lap — the rule has nothing left to act on for it, which is expected, not an error.) "Next-most-depended-upon"
   is decided against the current matrix's real dependency edges (`INDEX.md` §5.2 — verified to be an
   actual Mermaid graph with real `A --> B` edges, not prose; see the coverage caveat under gap C below),
   not arbitrarily — e.g., once `HUB-01` is in, the next Hub admission is whichever other Hub blueprint the
   already-touched Bridge/Spoke set most needs (`HUB-02` Cache and `HUB-04` Identity are the likely first
   candidates, since `BRIDGE-01`/`ISPOKE-09`/`ESPOKE-01` all reference them even at low depth — both
   confirmed present with real edges in `§5.2`'s current coverage).

   **OD-02 and lap-1 widening — checked against the actual `CORE-16` blueprint, not assumed (v3.4(3)
   correction).** `CORE-16` and `HUB-04` are both plausible early widening candidates (real graph edges into
   `BRIDGE-01`). An earlier draft (v3.4/v3.4(2)) excluded them from lap-1 widening to avoid ADR-thrash on
   freshly-frozen interfaces if OD-02 (post-quantum JWT) were still open. **v3.4(3) re-verified directly
   and DROPPED that exclusion:** `CORE-16`'s actual interface (`EncrypterInterface`) doesn't expose its cipher
   choice in its method signatures — algorithm detail is internal, and the blueprint states plainly
   `CORE-16` "ships no asymmetric primitive" at all; JWT signing lives in `HUB-04` per `ADR-003` (ES256), not
   in `CORE-16`. `HUB-04`'s own public contract (`attempt()`, `login()`, `check()`, …) is similarly
   high-level, with the signing algorithm expected to live inside an internal `TokenService`, not the frozen
   interface. **Expectation, not a guarantee: OD-02 resolves as an internal implementation change under an
   already-frozen `HUB-04` interface, not an interface change.** No lap-1 exclusion remains — constraining
   widening for a risk verification suggests probably won't materialize would be its own instance of planning
   against an assumption instead of a check. If OD-02's actual resolution turns out to need a `HUB-04`
   interface change anyway, that's an ADR-gated change like any other (§2.1) — expensive, but not a scenario
   this document failed to anticipate.

2. **Deepen — per-blueprint floor, not a global lap floor.** Each blueprint's target floor is *its own
   admission depth plus the number of laps completed since its own admission*, capped at depth 5 — not a
   single floor number shared by every blueprint regardless of when it entered the matrix.

**Why per-blueprint, not the global "lap 1→3, lap 2→4, lap 3→5" schedule stated in an earlier draft:** an
external review correctly caught that the global version contradicts itself — a blueprint widened into the
matrix during lap 2's widen step enters at depth 1, and lap 2's own deepen step would then require it to
reach depth 4 in the same lap: three depth levels in one bet-cycle, alongside a full widen pass across all
6 rings, for something that had zero prior laps to build in. The fix reviewed alongside that catch (exempt a
blueprint from the floor for its admission lap only, deepening starts next lap) is directionally right but
only *defers* the cliff by one lap rather than removing it — a blueprint admitted in lap 2 would still owe a
4-level jump (1→5) by lap 3 under that version. The per-blueprint relative floor removes the cliff entirely:
every blueprint deepens at the same steady +1-level-per-lap pace starting from its own admission, regardless
of the global lap number, so nothing is ever asked to catch up faster than anything else did. Applied to the
original 8: Milestone 0 gets them to depth 1–2 (their "lap 0"), lap 1 brings them to depth 3, lap 2 to depth
4, lap 3 to depth 5 — which reproduces the original schedule's intent for the blueprints it was actually
designed around, while extending consistently to every later admission instead of breaking on them.

**Termination:** the lap structure ends when all 96 blueprints are at depth 5 and the matrix has fully
widened — not at a fixed lap count, since how many laps that takes depends on how fast widening outpaces
deepening, which isn't known until real laps run (see §5's throughput-unit caveat).

This keeps the matrix genuinely two-dimensional throughout — width and depth grow together every cycle, at a
uniform per-blueprint pace — rather than fully deepening a fixed subset before ever widening, or asking
newly-widened blueprints to deepen faster than anything else did.

**Gap A — lap ↔ bet relationship, stated:** a lap is not one atomic bet. It is one widen pass (up to 6
ring-admissions) plus up to 6 deepening bets — one per ring that currently has a blueprint below its own
per-blueprint floor (§4.2 scopes each such bet to a single named ring and target). Not every ring
necessarily needs a deepening bet in every lap; a ring with nothing below floor that lap contributes only
its widen-step admission.

**Gap B — bet kill semantics, stated:** hitting the 1.5× ceiling (§4.2) means: stop the bet, keep whatever
depth was actually reached — no forced revert, since the interface was already frozen at admission (§2.1), so
partial deepening progress is never wasted. Re-scope explicitly: either the same ring gets the next
deepening bet-slot with adjusted expectations, or the architecture lead defers that ring's remaining
deepening to a later lap. This is a routine re-scoping event, not Milestone 0's "stop and reassess the whole
model" — killing one bet doesn't imply the methodology itself is wrong, the way Milestone 0 exceeding its own
ceiling would.

**Gap C — is `INDEX.md` §5.2 actually queryable, checked against the live repo, not assumed:** yes, and more
precisely than "yes/no" — it's a real Mermaid graph with genuine `A --> B` edges, but its current coverage is
37 of 96 blueprints (all 20 Core, 10 Hub components explicitly marked "selected critical," `BRIDGE-01`, the 2
exemplar Spokes `ISPOKE-01`/`ESPOKE-01`, and all 4 Deploy). `HUB-02` and `HUB-04` — this document's own named
lap-1 widening candidates — are both in that covered set with real edges, so early laps have genuine data to
widen against, not the arbitrary fallback gap C worried about. The real constraint: once widening needs a
blueprint outside that 37-node coverage (most of `HUB-11`–`30` beyond the 10 already included, and every
`ISPOKE`/`ESPOKE` beyond the 2 exemplars), `§5.2` has no edge data for it and the "next-most-depended-upon"
rule has nothing to compute against. This isn't a lap-1 blocker — it's a known, scoped point where `§5.2`'s
coverage needs extending, roughly once widening exhausts the 37-node set already covered. Tracked in §9, not
treated as resolved.

## 5. Calibration — formula, not a vibes-check

```
N  = blueprints in Milestone 0 = 8 (corrected — see §4)
W  = weeks Milestone 0 actually took (measured, not estimated)
throughput = N / W                    (blueprints/week)
remaining  = 96 − N = 88 blueprints
projected  = remaining / throughput = 11W   (weeks, bet-work only, excludes cooldowns)
```

Example: Milestone 0 takes 4 weeks → 44 weeks of remaining bet-work. Takes 6 weeks → 66 weeks.

**Caveat this projection doesn't state on its own: `throughput` here is a *build* rate (depth 0→2, what
Milestone 0 measures), applied to 88 blueprints that also need *deepening* passes (2→3→4→5), which is very
likely a different unit, not just a smaller version of the same one.** Deepening could be faster than initial
build — interfaces are already frozen (§2.1), patterns repeat within a ring — or slower, since depth 4–5 work
(observability, hardening, security review) is often genuinely harder than getting a happy path working. The
11W figure is therefore a **build-only estimate**, not a full project estimate, and should be read as such
until real lap data (§4.3) produces a separate deepening throughput to add to it. This is tracked as an
explicit unknown in §9, not resolved here by assertion.

**On the "40–70 week" range stated in an earlier draft of this document: it was wrong, and re-deriving it
properly matters, not just re-labeling it.** It was built on an uncorrected blueprint count, and a review of
this document correctly flagged that the arithmetic didn't actually derive it from the stated formula.
Re-running that same formula with the *correct* N=8 doesn't produce a lower, tighter number — it produces a
*higher* one, because a smaller Milestone 0 measured over the same W implies lower throughput. At W=4–6:
44–66 weeks of build-only bet-work (see caveat above), before cooldowns (§7, now 2 weeks each) and before
Cooldown 0 (§3, now 2 weeks, fixed). Cooldown *count* isn't a number this document will manufacture — under
the lap model, bet count isn't fixed in advance the way it was in the linear model, so a precise
cooldown-overhead figure would be exactly the kind of false precision this section exists to avoid. Treat
total elapsed time as **Milestone 0 (W) + Cooldown 0 (2wk) + build-work (11W) + deepening-work (unknown until
lap 1) + cooldown overhead (unknown until a real bet cadence exists)** — not as a single number, and not until
laps actually produce one.

**Recalibrate throughput after every lap, not once.** Build throughput and deepening throughput are separate
numbers (see caveat above), and within-ring pattern reuse will likely change both as the project goes on.
Only the *current* projection, refreshed at every lap boundary, is ever trusted.

**Until Milestone 0 completes and lap 1 establishes cadence:** no total is stated as fact anywhere in this
document, including here.

## 6. The linter is the second reviewer, not the notary

With one engineer, `Verification/lint/run.php` is the only thing that catches what a second reviewer would
have. Scope expands accordingly — and this expansion is itself real engineering work, budgeted explicitly
rather than left to compete silently with blueprint deepening: **lint-scope expansion happens during cooldown
weeks (§7), never during bet weeks.** If it doesn't fit in a cooldown, it carries to the next one rather than
eating into bet time.

**Not live during Milestone 0 itself.** Cooldown 0 (§3) is scoped to content contracts, not engineering lint
— so Milestone 0 runs against the *existing* lint scope only. The expanded checks below become active
starting with the first post-Milestone-0 cooldown. Don't assume the expanded scope is protecting Milestone 0's
own build.

| Check | Status |
|---|---|
| Cross-reference validity against `INDEX.md` (existing) | Already load-bearing |
| Pulse 6-tuple consistency across repos | Promote from nice-to-have to required — this is now the only thing catching Structure-doc drift |
| Naming drift, folder vs. `INDEX.md` (OD-05) | Promote from "docs cleanup" to lint-enforced, not manually tracked |
| Soft-Freeze violations — a PR touching a frozen interface (§2.1) without a citing ADR | Auto-reject at CI, not a review comment |
| Blueprint-fidelity drift — does the implementation still match its `STRUCTURE-XX` spec | **Honest caveat, not oversold:** full semantic diffing of code against prose spec isn't a solved CI problem. Start as a lighter-weight periodic check (a scripted structural diff — class/interface names present, method signatures match — not full behavioral equivalence) rather than claiming this is a complete automated gate from day one. Tighten it as patterns emerge from real drift instances. |

The distinction on the last row matters: claiming an unimplemented capability is fully automated would be
exactly the kind of unverified assertion this whole audit exists to catch, just relocated into the process
document instead of the architecture docs.

> **Reality check (verified against the live repo, 2026-08-10):** `run.php` in `Architecture/Verification/lint/`
> performs exactly **three** checks — (1) reference targets exist / are in-range, (2) misattribution-phrase
> guard, (3) structural completeness. It does **not** currently perform Pulse 6-tuple consistency, naming-drift,
> soft-freeze, or blueprint-fidelity checks. Those rows above are the *intended* expansion target, not a
> description of what the linter does today. See `DISCREPANCY-REGISTER.md` and `REPO-STATE-AUDIT.md`.

## 7. Cooldowns: 2 weeks, not 1

A 3-person team's 1-week cooldown gave each person ~2.3 days of catch-up. Solo, one week has to cover doc
reconciliation, OD triage, refactor backlog, the review backlog that accumulated during the bet, *and*
recovery time nobody else provides by covering for you. Solo burnout carries higher risk than team burnout
precisely because there's no redundancy to absorb it. **Cooldowns are 2 weeks for the duration of solo
operation.** Total timeline goes up; sustainability goes up more, and an unsustainable schedule that collapses
at week 25 is a worse outcome than a longer one that actually completes.

## 8. What's kept unchanged from predecessor documents

- **The OD table** — adopted from `AGRD-v2.md` (Z.ai) as-is; it's the one that actually matches the live
  `Architecture/OPEN-DECISIONS.md`. `AGRD_v1_0.md`'s (Kimi) OD-03–06 table is discarded — it doesn't match the
  current repo and is internally self-contradictory about what OD-06 even refers to.
- **ADR-gating** on architectural decisions.
- **Kill-the-bet triggers**, unchanged in spirit — a bet that's clearly not converging gets killed, not ridden
  out.
- **Governance Rule 2** (no benchmark/timeline claim without a stated method) — this document's own §5 is the
  SDLC-level application of the same rule that governs individual blueprint performance claims.

### 8.1 OD sequencing under solo mode — two entries re-timed, not re-decided

Solo, the cost of a wrong architectural call lands entirely on one person, with no second engineer to catch
it in review. Two of the six open decisions need earlier resolution than the timing implied elsewhere:

- **OD-06** (`ADR-010` opcache baseline, blocked on `CORE-02`) is mechanically forced to resolve **during
  Milestone 0** — `CORE-02` is the first thing built there, so there's no later point where this stays
  genuinely open without also blocking the milestone itself.
- **OD-02** (post-quantum/algorithm-agility JWT signing) resolves **during Cooldown 1** (the first cooldown
  after Milestone 0), not folded into Cooldown 0 and not read as a Milestone 0 completion gate. To be explicit
  about a real tension an earlier draft left ambiguous: Milestone 0's success criterion (§4) is the Pulse round
  trip working, full stop — OD-02 is not part of that criterion, and the round trip can and should succeed
  before OD-02 is decided. Cooldown 0 is scoped tightly to content-facing contracts (§3) so marketer/media can
  start on schedule; a deep crypto-architecture decision doesn't belong there without diluting what that phase
  is for. `CORE-16`/`HUB-04` groundwork is already being scoped by Cooldown 1 regardless, which is the natural
  point to close it out — earlier than the originally-planned "Bet 6," and unambiguously after Milestone 0's
  own completion rather than entangled with it.

OD-01, OD-03, OD-04, OD-05 keep their existing owners and timing from `OPEN-DECISIONS.md` — nothing about solo
mode changes their urgency.

## 9. Explicit unknowns — do not silently resolve these

- The actual Milestone 0 duration, and therefore every downstream week-count.
- Whether `CORE-02`'s real build time matches the "unblocks everything" framing it's had since Finding 8, or
  turns out harder than expected as the first thing built solo.
- Whether blueprint-fidelity drift-checking (§6) needs to become a harder automated gate sooner than planned,
  based on how much real drift Milestone 0 and the first few deepening bets actually produce.
- **Whether deepening throughput (depth 2→5) differs from build throughput (depth 0→2), and by how much (§5).**
  Genuinely unresolvable until lap 1 (§4.3) produces a real deepening data point — the projection in §5 is
  explicitly build-only until then.
- **How many laps the widen/deepen structure (§4.3) actually takes to reach full coverage.** Depends on the
  relative rate of widening vs. deepening, which is itself unknown until laps 1–2 run.
- **`INDEX.md` §5.2's dependency-graph coverage (37 of 96 blueprints, verified) needs extending before widening
  exhausts it.** Not a lap-1 blocker — `HUB-02`/`HUB-04` are both covered — but there's no stated trigger for
  *when* to extend §5.2's coverage before the widen rule runs out of real edge data to compute against.
  Trigger, disambiguated: extend a ring's §5.2 coverage once 50% **of that ring's currently-covered set** has
  been admitted into the matrix (e.g., Hub's covered set is 10 of its 30 blueprints — extend once 5 of those 10
  are admitted, not once 15 of all 30 are), since that's the point where the widen rule is about to run out of
  real edges to choose from for that ring, which is the actual failure mode this trigger exists to prevent.

Per Governance Rule 9 (this session's own precedent): open questions get recorded, never silently resolved. The
six above go in `OPEN-DECISIONS.md` as new entries once this document is ratified.

## 10. What changed across the v3.x lineage (consolidated changelog)

| From → To | Section | Fix |
|---|---|---|
| v3.2 → v3.3 | §4.3 | Fixed a real spec contradiction in the lap floor schedule with a **per-blueprint relative floor** (was a global "lap 1→3, lap 2→4, lap 3→5" that self-contradicted). |
| v3.3 → v3.4 | §4.2 | Removed stale "global lap floor" / "Core to depth 3" language; per-blueprint target formulation. |
| v3.3 → v3.4 | §4.2 | Bet 1 kill trigger: replaced build-throughput-derived ceiling with `W`-based placeholder (1.5×W) to avoid the build-vs-deepening unit mismatch. |
| v3.3 → v3.4 | §4.3 | **Added** "For each ring that still has unadmitted blueprints…" to the widen step — closed the fully-widened-ring edge case (Gap A). |
| v3.3 → v3.4 | §9 | Clarified "50% admitted" = 50% of the **covered set** (blueprints already in §5.2's subgraph for that ring), not 50% of the ring's total. |
| v3.4 → v3.4(3) | §4.3 | **Dropped** the lap-1 widen exclusion for `CORE-16`/`HUB-04`: OD-02 checked against actual `EncrypterInterface`/`HUB-04` contracts; no interface change expected, so no exclusion needed (see §4.3 OD-02 note). Companion `PROMPTS.md` Rule 8 was corrected to match. |
| split out | §11 | The PROMPTS operating-instructions module moved to the companion `PROMPTS.md` in this folder. |

**Assessment (carried from v3.4):** gap-hunting is still finding real things, but they are getting smaller and
more localized. After v3.4(3), stop iterating without lap data. The remaining unknowns (§9's six entries) are
data-dependent by design, and gap-hunting on data-dependent unknowns is the analysis paralysis this whole
process exists to prevent.

---

### Provenance

This document consolidates the canonical SDLC body from `Design_Models_Misc/SDLC-AGRD-v3.4(3).md` (§§1–9) and
`Design_Models_Misc/SDLC-AGRD-v3.4(2).md` (§10 changelog), with the v3.4(3) correction applied to the lap-1
widen exclusion and the PROMPTS module split to `PROMPTS.md`. The operating-instructions companion is
`PROMPTS.md`; the curated agent entry-point is `MEMORY.md`. Ground truth for counts, ADRs, and the dependency
graph is the live `Architecture/` tree (`INDEX.md`, `OPEN-DECISIONS.md`, `ADRs/`, `Verification/lint/run.php`).
