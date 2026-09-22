# SDLC-AGRD v3.4: Spiral Deepening for a Solo Tech Lead

**Status:** Ready to push as `Architecture/CrossCutting/SDLC-AGRD.md`.
**Supersedes:** `AGRD_v1_0.md` (Kimi, 3-engineer parallel model), `AGRD-v2.md` (Z.ai, 3-engineer
sequential-with-parallel-tail model), `SDLC-AGRD-v3` (fixed a self-inconsistent blueprint count, added
the depth scale and a one-shot Phase structure), `SDLC-AGRD-v3.1` (replaced the one-shot Phase
structure with a repeating lap structure; stated bet duration; separated build/deepening throughput;
fixed the OD-02/Milestone-0 contradiction), `SDLC-AGRD-v3.2` (per external review: found and fixed a
real spec contradiction in the lap floor schedule — global lap-indexed floors broke for any blueprint
not admitted at Milestone 0, since a newly-widened blueprint would owe 3+ depth levels in a single lap;
replaced with a per-blueprint relative floor; stated the lap↔bet relationship and bet-kill semantics
explicitly; verified `INDEX.md` §5.2's actual dependency-graph coverage against the live repo rather
than assuming it), `SDLC-AGRD-v3.3` (per external review: fixed §4.2's stale global-floor language to
match §4.3's per-blueprint floor; resolved Bet 1 kill-trigger unit mismatch via W-based placeholder;
constrained lap-1 widen to respect OD-02 sequencing; clarified §9's 50% trigger; handled fully-widened
rings in §4.3), and the "3 engineers" assumption embedded in all predecessors.
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

---

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

---

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

---

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

§5 describes the *measurement*; this describes what happens with it.

**Bet scoping rule:** pick the ring with the lowest average depth across its currently-touched
blueprints, and deepen its below-floor blueprints to their own per-blueprint targets (§4.3). Each
blueprint's target is its own admission depth plus the number of laps completed since its own
admission, capped at depth 5 — not a single floor number shared by every blueprint regardless of when
it entered the matrix. Concretely, if `CORE-02` is at depth 2 post-Milestone 0 while `CORE-04`/`05`/`06`
are at depth 1, Bet 1 deepens `CORE-02` toward depth 3 and `CORE-04`/`05`/`06` toward depth 2 — each to
its own per-blueprint target, not a uniform "Core to depth 3."

**Bet duration — stated, not left open.** Before Milestone 0 completes there is no real throughput
number to derive a time-box from — that's expected. For Bet 1 specifically, the time-box is `W`
(Milestone 0's measured duration in weeks), with a 1.5× ceiling as its kill trigger. This is a
placeholder: Bet 1's actual duration becomes the first deepening-throughput data point, and Bet 2's
time-box is derived from the average of Bet 1's measured duration and Milestone 0's `W`. No deepening
bet before lap 1 produces real data uses a build-rate-derived ceiling — that would be the unit mismatch
§5 warns against, applied to the very first bet it would affect.

### 4.3 Lap structure — widens and deepens every cycle, not deepen-then-widen

The prior draft of this document defined three one-shot phases that deepened Milestone 0's original 8
blueprints to depth 5 *before* admitting a 9th. An external review caught this correctly: that's not
Spiral Deepening, it's Radial Incremental at smaller scale — the same late-integration-surprise problem
this document exists to prevent, just relocated from "ring boundaries" to "blueprint #9." The fix
proposed alongside that catch (widen at two of three phase exits) was a real improvement but only
covered a handful of blueprints through three named phases — it can't reach 96 blueprints without
repeating. So: laps, not phases, and each lap does both things every time.

**Lap k, for k = 1, 2, 3…:**

1. **Widen.** For each ring that still has unadmitted blueprints, admit its next-most-depended-upon
   not-yet-touched blueprint at depth 1. "Next-most-depended-upon" is decided against the current
   matrix's real dependency edges (`INDEX.md` §5.2 — verified to be an actual Mermaid graph with real
   `A --> B` edges, not prose; see the coverage caveat under gap C below), not arbitrarily — e.g., once
   `HUB-01` is in, the next Hub admission is whichever other Hub blueprint the already-touched
   Bridge/Spoke set most needs (`HUB-02` Cache and `HUB-04` Identity are the likely first candidates,
   since `BRIDGE-01`/`ISPOKE-09`/`ESPOKE-01` all reference them even at low depth — both confirmed
   present with real edges in `§5.2`'s current coverage).

   **Lap 1 widen constraint:** `CORE-16` (Crypto) and `HUB-04` (Identity) are excluded from lap 1's
   widen step. Both are referenced by `BRIDGE-01` (already in the matrix) and would otherwise be
   plausible next-most-depended-upon candidates, but `OD-02` (post-quantum JWT signing) resolves during
   Cooldown 1 — *after* Bet 1 runs — and may require an ADR-gated interface change on whichever
   component owns the signing surface. Admitting either at depth 1 in lap 1 would freeze their
   interfaces (§2.1) before the OD that may change them. They become eligible for widening starting
   lap 2, once OD-02 is closed.

2. **Deepen — per-blueprint floor, not a global lap floor.** Each blueprint's target floor is *its own
   admission depth plus the number of laps completed since its own admission*, capped at depth 5 — not
   a single floor number shared by every blueprint regardless of when it entered the matrix.

**Why per-blueprint, not the global "lap 1→3, lap 2→4, lap 3→5" schedule stated in an earlier draft:**
an external review correctly caught that the global version contradicts itself — a blueprint widened
into the matrix during lap 2's widen step enters at depth 1, and lap 2's own deepen step would then
require it to reach depth 4 in the same lap: three depth levels in one bet-cycle, alongside a full
widen pass across all 6 rings, for something that had zero prior laps to build in. The fix reviewed
alongside that catch (exempt a blueprint from the floor for its admission lap only, deepening starts
next lap) is directionally right but only *defers* the cliff by one lap rather than removing it — a
blueprint admitted in lap 2 would still owe a 4-level jump (1→5) by lap 3 under that version. The
per-blueprint relative floor removes the cliff entirely: every blueprint deepens at the same steady
+1-level-per-lap pace starting from its own admission, regardless of the global lap number, so nothing
is ever asked to catch up faster than anything else did. Applied to the original 8: Milestone 0 gets
them to depth 1–2 (their "lap 0"), lap 1 brings them to depth 3, lap 2 to depth 4, lap 3 to depth 5 —
which reproduces the original schedule's intent for the blueprints it was actually designed around,
while extending consistently to every later admission instead of breaking on them.

**Termination:** the lap structure ends when all 96 blueprints are at depth 5 and the matrix has fully
widened — not at a fixed lap count, since how many laps that takes depends on how fast widening
outpaces deepening, which isn't known until real laps run (see §5's throughput-unit caveat).

This keeps the matrix genuinely two-dimensional throughout — width and depth grow together every
cycle, at a uniform per-blueprint pace — rather than fully deepening a fixed subset before ever
widening, or asking newly-widened blueprints to deepen faster than anything else did.

**Gap A — lap ↔ bet relationship, stated:** a lap is not one atomic bet. It is one widen pass (up to 6
ring-admissions, fewer once rings are fully widened) plus up to 6 deepening bets — one per ring that
currently has a blueprint below its own per-blueprint floor (§4.2 scopes each such bet to a single
named ring and target). Not every ring necessarily needs a deepening bet in every lap; a ring with
nothing below floor that lap contributes only its widen-step admission (if it still has unadmitted
blueprints) or nothing (if fully widened).

**Gap B — bet kill semantics, stated:** hitting the 1.5× ceiling (§4.2) means: stop the bet, keep
whatever depth was actually reached — no forced revert, since the interface was already frozen at
admission (§2.1), so partial deepening progress is never wasted. Re-scope explicitly: either the same
ring gets the next deepening bet-slot with adjusted expectations, or the architecture lead defers that
ring's remaining deepening to a later lap. This is a routine re-scoping event, not Milestone 0's "stop
and reassess the whole model" — killing one bet doesn't imply the methodology itself is wrong, the way
Milestone 0 exceeding its own ceiling would.

**Gap C — is `INDEX.md` §5.2 actually queryable, checked against the live repo, not assumed:** yes, and
more precisely than "yes/no" — it's a real Mermaid graph with genuine `A --> B` edges, but its current
coverage is 37 of 96 blueprints (all 20 Core, 10 Hub components explicitly marked "selected critical,"
`BRIDGE-01`, the 2 exemplar Spokes `ISPOKE-01`/`ESPOKE-01`, and all 4 Deploy). `HUB-02` and `HUB-04` —
this document's own named lap-1 widening candidates — are both in that covered set with real edges, so
early laps have genuine data to widen against, not the arbitrary fallback gap C worried about. The real
constraint: once widening needs a blueprint outside that 37-node coverage (most of `HUB-11`–`30` beyond
the 10 already included, and every `ISPOKE`/`ESPOKE` beyond the 2 exemplars), `§5.2` has no edge data
for it and the "next-most-depended-upon" rule has nothing to compute against. This isn't a lap-1
blocker — it's a known, scoped point where `§5.2`'s coverage needs extending, roughly once widening
exhausts the 37-node set already covered.

---

## 5. Calibration — formula, not a vibes-check

```
N  = blueprints in Milestone 0 = 8 (corrected — see §4)
W  = weeks Milestone 0 actually took (measured, not estimated)
throughput = N / W                    (blueprints/week)
remaining  = 96 − N = 88 blueprints
projected  = remaining / throughput = 11W   (weeks, bet-work only, excludes cooldowns)
```

Example: Milestone 0 takes 4 weeks → 44 weeks of remaining bet-work. Takes 6 weeks → 66 weeks.

**Caveat this projection doesn't state on its own: `throughput` here is a *build* rate (depth 0→2,
what Milestone 0 measures), applied to 88 blueprints that also need *deepening* passes (2→3→4→5),
which is very likely a different unit, not just a smaller version of the same one.** Deepening could be
faster than initial build — interfaces are already frozen (§2.1), patterns repeat within a ring — or
slower, since depth 4–5 work (observability, hardening, security review) is often genuinely harder than
getting a happy path working. The 11W figure is therefore a **build-only estimate**, not a full
project estimate, and should be read as such until real lap data (§4.3) produces a separate deepening
throughput to add to it. This is tracked as an explicit unknown in §9, not resolved here by assertion.

**On the "40–70 week" range stated in an earlier draft of this document: it was wrong, and re-deriving
it properly matters, not just re-labeling it.** It was built on an uncorrected blueprint count, and a
review of this document correctly flagged that the arithmetic didn't actually derive it from the stated
formula. Re-running that same formula with the *correct* N=8 doesn't produce a lower, tighter number —
it produces a *higher* one, because a smaller Milestone 0 measured over the same W implies lower
throughput. At W=4–6: 44–66 weeks of build-only bet-work (see caveat above), before cooldowns (§7, now
2 weeks each) and before Cooldown 0 (§3, now 2 weeks, fixed). Cooldown *count* isn't a number this
document will manufacture — under the lap model, bet count isn't fixed in advance the way it was in the
linear model, so a precise cooldown-overhead figure would be exactly the kind of false precision this
section exists to avoid. Treat total elapsed time as **Milestone 0 (W) + Cooldown 0 (2wk) + build-work
(11W) + deepening-work (unknown until lap 1) + cooldown overhead (unknown until a real bet cadence
exists)** — not as a single number, and not until laps actually produce one.

**Recalibrate throughput after every lap, not once.** Build throughput and deepening throughput are
separate numbers (see caveat above), and within-ring pattern reuse will likely change both as the
project goes on. Only the *current* projection, refreshed at every lap boundary, is ever trusted.

**Until Milestone 0 completes and lap 1 establishes cadence:** no total is stated as fact anywhere in
this document, including here.

---

## 6. The linter is the second reviewer, not the notary

With one engineer, `Verification/lint/run.php` is the only thing that catches what a second reviewer
would have. Scope expands accordingly — and this expansion is itself real engineering work, budgeted
explicitly rather than left to compete silently with blueprint deepening: **lint-scope expansion
happens during cooldown weeks (§7), never during bet weeks.** If it doesn't fit in a cooldown, it
carries to the next one rather than eating into bet time.

**Not live during Milestone 0 itself.** Cooldown 0 (§3) is scoped to content contracts, not engineering
lint — so Milestone 0 runs against the *existing* lint scope only. The expanded checks below become
active starting with the first post-Milestone-0 cooldown. Don't assume the expanded scope is protecting
Milestone 0's own build.

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

---

## 7. Cooldowns: 2 weeks, not 1

A 3-person team's 1-week cooldown gave each person ~2.3 days of catch-up. Solo, one week has to cover
doc reconciliation, OD triage, refactor backlog, the review backlog that accumulated during the bet,
*and* recovery time nobody else provides by covering for you. Solo burnout carries higher risk than
team burnout precisely because there's no redundancy to absorb it. **Cooldowns are 2 weeks for the
duration of solo operation.** Total timeline goes up; sustainability goes up more, and an unsustainable
schedule that collapses at week 25 is a worse outcome than a longer one that actually completes.

---

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
- **OD-02** (post-quantum/algorithm-agility JWT signing) resolves **during Cooldown 1** (the first
  cooldown after Milestone 0), not folded into Cooldown 0 and not read as a Milestone 0 completion
  gate. To be explicit about a real tension an earlier draft left ambiguous: Milestone 0's success
  criterion (§4) is the Pulse round trip working, full stop — OD-02 is not part of that criterion, and
  the round trip can and should succeed before OD-02 is decided. Cooldown 0 is scoped tightly to
  content-facing contracts (§3) so marketer/media can start on schedule; a deep crypto-architecture
  decision doesn't belong there without diluting what that phase is for. `CORE-16`/`HUB-04` groundwork
  is already being scoped by Cooldown 1 regardless, which is the natural point to close it out —
  earlier than the originally-planned "Bet 6," and unambiguously after Milestone 0's own completion
  rather than entangled with it.

OD-01, OD-03, OD-04, OD-05 keep their existing owners and timing from `OPEN-DECISIONS.md` — nothing
about solo mode changes their urgency.

---

## 9. Explicit unknowns — do not silently resolve these

- The actual Milestone 0 duration, and therefore every downstream week-count.
- Whether `CORE-02`'s real build time matches the "unblocks everything" framing it's had since Finding
  8, or turns out harder than expected as the first thing built solo.
- Whether blueprint-fidelity drift-checking (§6) needs to become a harder automated gate sooner than
  planned, based on how much real drift Milestone 0 and the first few deepening bets actually produce.
- **Whether deepening throughput (depth 2→5) differs from build throughput (depth 0→2), and by how
  much (§5).** Genuinely unresolvable until lap 1 (§4.3) produces a real deepening data point — the
  projection in §5 is explicitly build-only until then.
- **How many laps the widen/deepen structure (§4.3) actually takes to reach full coverage.** Depends on
  the relative rate of widening vs. deepening, which is itself unknown until laps 1–2 run.
- **`INDEX.md` §5.2's dependency-graph coverage (37 of 96 blueprints, verified) needs extending before
  widening exhausts it.** Not a lap-1 blocker — `HUB-02`/`HUB-04` are both covered — but there's no
  stated trigger for *when* to extend §5.2's coverage before the widen rule runs out of real edge data
  to compute against. Extend §5.2 for a ring's remaining blueprints once its covered set is 50%
  admitted (meaning 50% of the blueprints already listed in that ring's §5.2 subgraph have been
  admitted to the matrix — not 50% of the ring's total blueprints, which would be a much later and
  less useful trigger). Add that extension during whichever cooldown first sees widening approach the
  37-node boundary.

Per Governance Rule 9 (this session's own precedent): open questions get recorded, never silently
resolved. The six above go in `OPEN-DECISIONS.md` as new entries once this document is ratified.

---

## 10. What changed from v3.3 → v3.4

| Fix | Section | Description |
|---|---|---|
| §4.2 per-blueprint rewrite | §4.2 | Removed stale "global lap floor" and "Core to depth 3" language; replaced with per-blueprint target formulation matching §4.3's relative floor. |
| Bet 1 kill trigger placeholder | §4.2 | Replaced build-throughput-derived ceiling with `W`-based placeholder (1.5×W kill trigger) to avoid the build-vs-deepening unit mismatch §5 warns against. |
| Lap 1 widen constraint | §4.3 | Excluded `CORE-16` and `HUB-04` from lap 1 widening until OD-02 resolves, preventing ADR-thrash on freshly-frozen interfaces. |
| §9 50% trigger clarification | §9 | Clarified "50% admitted" means 50% of the *covered set* (the blueprints already in §5.2's subgraph for that ring), not 50% of the ring's total. |
| Fully-widened ring handling | §4.3 | Added "For each ring that still has unadmitted blueprints..." to the widen step, closing the gap where fully-widened rings had no stated behavior. |

**Assessment:** gap-hunting is still finding real things, but they are getting smaller and more
localized. v3.2 → v3.3 found a real spec contradiction; v3.3 → v3.4 found two spec inconsistencies plus
edge-case ODs. That's still worth the round, but the marginal return is dropping fast. **After v3.4,
stop iterating without lap data.** The remaining unknowns (§9's six entries) are data-dependent by
design, and gap-hunting on data-dependent unknowns is the analysis paralysis this whole process exists
to prevent.

§11 below is the one addition v3.4 makes that is *not* a gap-fix on v3.3: a PROMPTS module that
operationalizes the model above for the varied AI agents (Cline, Aider, Jules, Claude web, etc.) the
solo tech lead actually drives. §11 is calibrated to v3.4's rules — when v3.5 lands (after lap 1 data,
per §10), §11 needs re-calibration too.

---

## 11. PROMPTS module — operating instructions for varied AI agents

### 11.1 Why this module exists, and what it is not

Every prior section of this document is written for the tech lead. This section is written for the
*agents* the tech lead drives — the local VSCode-class tools (Cline, Roo Code, Continue, Aider) and
the cloud async tools (Jules, Devin, Claude web, Codex) that multiply a solo engineer's implementation
throughput. The premise of solo mode (§1) is that these agents multiply *implementation* throughput
without substituting for *review and judgment* capacity. That premise only holds if the prompts driving
the agents encode the rules in §§2–9. Without that encoding, an agent will optimize for the locally
visible goal ("implement this blueprint at depth 3") and silently violate a global constraint ("don't
touch a frozen interface", "don't expand lint mid-bet", "don't admit CORE-16 before OD-02 resolves").

This module is **not** an autonomous-agent spec. Every prompt here assumes a tech lead reviewing the
output. None of them are "run Jules and merge without looking." The bottleneck in solo mode is
attention, not typing speed — agents buy back typing, not attention. Prompts that pretend otherwise
reintroduce exactly the silent-drift problem §6 (lint as second reviewer) exists to catch.

This module is also **not stable**. It is calibrated to v3.4's rules. When v3.5 lands after lap 1
data (§10), the prompts need re-calibration alongside the rest of the document. Treat §11 as
v3.4-specific operational guidance, not as a permanent operating manual.

### 11.2 Agent taxonomy — local vs. cloud, capability matrix

Not all agents fit all tasks. Picking the wrong agent for a task is a real failure mode — Jules on a
lint-scope expansion (which requires deep iterative testing against `run.php`) will produce something
that looks plausible but doesn't actually catch drift; Cline on a dependency-graph analysis (which
benefits from a fresh-clone perspective and async autonomy) will take longer than Jules for the same
output. The matrix below is the calibration.

| Agent | Class | Strengths in solo-mode DGLab | Use for | Do NOT use for |
|---|---|---|---|---|
| **Cline / Roo Code** | Local, VSCode-embedded | Full repo context, iterative edits, runs CLI tools, reads specific files on demand | Bet execution (depth 1→5), lint scope expansion, ADR implementation, hand-off verification | Fresh-perspective review of v3.x docs (too close to repo) |
| **Aider** | Local, CLI, git-aware | Atomic commits with messages, conversational, strong on small-surface deepening | Deepening bets where many small commits matter, refactors within a single blueprint | Wide-surface widen steps (better with Cline's editor integration) |
| **Continue.dev** | Local, lightweight | Inline completions, quick Q&A | Quick struct lookups, docstring generation, small mechanical edits | Anything requiring multi-file reasoning |
| **GitHub Copilot Workspace** | Cloud-ish, PR-oriented | Takes a task brief, produces a PR | Widen-step stubs (mechanical depth-1 work), test generation, doc cleanup | Anything touching frozen interfaces, anything requiring judgment on ODs |
| **Jules** | Cloud, async autonomous | Scoped task brief → PR, runs in fresh clone | Dependency analyses against `INDEX.md` §5.2, isolated implementation of well-defined lint checks, scaffold generation | Lint expansion (no iterative test loop), anything requiring worklog awareness mid-task |
| **Devin** | Cloud, async autonomous, more agency than Jules | Longer-horizon scoped tasks | Generating test suites for a single blueprint, ADR template filling | Anything where interface-freeze discipline could be silently violated — Devin's extra agency cuts both ways |
| **Claude web / Claude Code** | Cloud, conversational | Gap-hunting review passes, OD analysis, ADR drafting, document review | §11.12 review passes, OD pre-resolution analysis, cross-document consistency checks | Direct repo edits without tech-lead review |
| **Codex (ChatGPT)** | Cloud, conversational | Cross-checking prompts, alternative implementations | Second-opinion on a Jules PR, alternative algorithm for a Hub service | Primary implementation path — too easy to lose track of §2.1 freeze discipline in chat context |

**The cloud-vs-local split that matters most:** cloud agents (Jules, Devin, Copilot Workspace) run
against a fresh clone with no worklog awareness. They cannot see prior agent invocations. Local
agents (Cline, Aider) inherit the worklog and the in-editor state. **Any task handed to a cloud agent
requires an explicit hand-off bundle (§11.13) — the cloud agent cannot infer context from
conversation history it doesn't have.** This is not optional politeness; it is the contract that keeps
cloud-agent work inside the §2.1 interface-freeze discipline.

### 11.3 The shared context block — paste verbatim before any task-specific prompt

Every prompt below assumes this block has been delivered to the agent first. Without it, the agent
will optimize for the locally visible goal and silently violate one of the global rules. With it, the
rules are in the agent's context window alongside the task.

```
SHARED CONTEXT — DGLab Wheel SDLC, solo tech lead mode

You are assisting one solo tech lead (no second engineer) on the DGLab Wheel project
(96 blueprints, 6 rings: Core/Hub/Thick Spokes/Inner Rim/Thin Spokes/Outer Rim).
Methodology: SDLC-AGRD v3.4 Spiral Deepening — vertical deepening of horizontal slices,
NOT horizontal completion of vertical rings.

CURRENT STATE (fill in before sending):
- Lap: <N>
- Cooldown 0: <complete | in progress>
- Milestone 0: <complete at W=<weeks> | in progress, week <W>>
- Throughput (if Milestone 0 complete): <N/W = X blueprints/week, build-only>
- OD-02 status: <resolved in Cooldown 1 | unresolved, lap-1 widen constraint active>
- OD-06 status: <resolved during Milestone 0 via ADR-010 | blocked>
- Matrix snapshot: <paste current depth-per-blueprint table from worklog>

RULES THAT APPLY TO EVERY TASK:
1. Interface freeze (§2.1): a blueprint's public contract freezes at first implementation.
   Changing a frozen interface is ADR-gated. If your task touches a frozen interface,
   STOP and ask for the ADR before proceeding. Do not "improve" a frozen interface mid-task.
2. Per-blueprint relative floor (§4.3): each blueprint's target depth is its own admission
   depth + laps since its admission, capped at 5. Do NOT apply a global lap floor — that
   was the v3.2 spec bug, and v3.4 explicitly rejects it.
3. Lint is the second reviewer (§6): lint scope expansion happens during cooldowns, NEVER
   during bet weeks. If you find a lint gap during a bet, log it for the next cooldown —
   do not expand lint mid-bet, even if the gap looks small.
4. Bet kill trigger (§4.2): if a bet exceeds 1.5× its time-box, stop. Keep what's done —
   the interface was already frozen at admission, so partial deepening is never wasted.
   Surface to tech lead for re-scoping. Do not silently extend.
5. Milestone 0 kill trigger (§4): if Milestone 0 exceeds 8 weeks, that is a stop-the-line
   signal, not a calibrate-forward signal. Surface immediately. Do not push to week 9.
6. Worklog (§11.13): every agent appends to /home/z/my-project/worklog.md. Never overwrite
   prior sections. If you cannot find the worklog, ASK before proceeding — its absence is
   a red flag, not a permission to skip logging.
7. ODs (§8.1): OD-02 resolves in Cooldown 1 (NOT Milestone 0, NOT Cooldown 0). OD-06
   resolves during Milestone 0. Do not pre-resolve either — surface the decision point
   and let the tech lead close it.
8. Lap 1 widen constraint: CORE-16 and HUB-04 are EXCLUDED from lap 1 widening until
   OD-02 resolves. Do not admit them early "to save a lap" — that's the ADR-thrash on
   freshly-frozen interfaces that v3.4 explicitly prevents.

If any of these rules conflict with your task instructions, the rules win. Ask before
proceeding.
```

The block is verbose by design. Trimming it to "be more efficient" is the most common way an agent
introduces silent drift — every rule in the block exists because some prior version of this project
violated it. Treat the block's length as a feature, not a bug.

### 11.4 Cooldown 0 prompts — content contract freezing

Cooldown 0 (§3) freezes three contracts before Milestone 0 starts: ESPOKE-05 wireframe, HUB-26 theme
tokens, HUB-13 string keys. These are content-facing contracts, not engineering ones — marketer and
media write against them for the duration. The prompt below is for one of the three; the other two
follow the same shape with their spec swapped in.

```
TASK: Freeze ESPOKE-05 UI wireframe under Cooldown 0 (§3)
AGENT: Cline (local, full repo context)
PRECONDITION: §11.3 shared context block has been delivered. Cooldown 0 has started; Milestone 0
has not.

GOAL: produce a frozen wireframe artifact at
Architecture/Cooldown0/ESPOKE-05-wireframe.md that the marketer can write copy against for
the next 6+ months without rework.

STEPS:
1. Read ESPOKE-05's STRUCTURE-XX spec. Identify every user-visible surface (forms, lists,
   modals, navigation, empty states, error states).
2. For each surface, produce a wireframe (ASCII or Mermaid + dimension notes). For each
   wireframe, explicitly enumerate the copy slots (headline, button labels, error strings,
   empty-state copy, tooltip text, accessibility alt-text).
3. Cross-reference HUB-13's string-key taxonomy (also being frozen in this cooldown). Every
   copy slot MUST map to a real HUB-13 key — no free-form strings. If a needed key doesn't
   exist, surface it as a Cooldown 0 scope expansion, not an ESPOKE-05 workaround.
4. Cross-reference HUB-26's theme tokens (also being frozen in this cooldown). Every visual
   slot MUST map to a real HUB-26 token — no hardcoded dimensions. If a needed token doesn't
   exist, surface it the same way.
5. Output the wireframe document. Tag its header: "Frozen under Cooldown 0 ADR-gate
   (SDLC-AGRD v3.4 §3). Changes require a new ADR."
6. Do NOT begin implementation. Cooldown 0 freezes contracts, not code. Any .php/.ts/.vue
   file edits in this task are wrong — STOP if you find yourself wanting to make them.

ACCEPTANCE CRITERIA:
- Every copy slot has a HUB-13 key citation.
- Every visual slot has a HUB-26 token citation.
- Document header carries the §3 freeze tag.
- No source code files modified.
- Worklog entry appended (§11.13) with: files produced, scope expansions surfaced, ODs
  touched (should be none — Cooldown 0 is content contracts, not ODs).

ESCALATION:
- If HUB-13 or HUB-26 lacks a needed entry, STOP — do not invent a key/token, do not
  silently extend either taxonomy. Surface the gap to the tech lead. The whole point of
  Cooldown 0 is a stable target; silently extending the contract mid-freeze reintroduces
  the rework problem Cooldown 0 exists to prevent.
```

The HUB-26 and HUB-13 versions of this prompt swap the spec reference in step 1 and adjust step 2
(produce the token/key taxonomy rather than a wireframe consuming it). Everything else — freeze tag,
acceptance, escalation — is identical, deliberately: the discipline is the point, not the artifact
shape.

### 11.5 Milestone 0 prompts — walking skeleton, kill-trigger-aware

Milestone 0 (§4) is the calibration instrument. Its 8-blueprint scope and 8-week ceiling are the two
numbers everything downstream depends on. The prompt below is for the first blueprint (CORE-02); the
other seven follow the same shape with their spec swapped in. The kill-trigger language is non-negotiable
— it's what separates Milestone 0 from a feature bet.

```
TASK: Milestone 0 walking skeleton — implement CORE-02 at depth 2
AGENT: Cline (local, full repo context)
PRECONDITION: §11.3 shared context block delivered. Cooldown 0 complete. Milestone 0 in progress.

GOAL: depth-2 (happy path) CORE-02 that participates in the end-to-end Pulse round trip
(§4 success criterion).

STEPS:
1. Read CORE-02's STRUCTURE-XX spec. Read OD-06 (ADR-010 opcache baseline) — OD-06 resolves
   DURING Milestone 0 because CORE-02 is the first thing built (§8.1). If ADR-010 is not
   yet committed, surface the decision point — do not silently pick a baseline and proceed.
2. Implement CORE-02 to depth 2: interface frozen at first commit (§2.1), happy path works
   end-to-end with HUB-01, BRIDGE-01 (stub), ISPOKE-09, ESPOKE-01. The Pulse round trip
   must succeed with this CORE-02 in place.
3. Do NOT implement error paths (depth 3), observability (depth 4), or hardening (depth 5).
   Those are later laps, scoped by per-blueprint relative floor (§4.3). Implementing them
   now is scope creep, not progress.
4. Commit atomic. Every commit message references the depth explicitly:
   "CORE-02 depth 2: <summary>". This makes §5's throughput calculation possible — depth
   in the commit message is the data source for "build vs. deepen" throughput separation.
5. Update worklog (§11.13) with: blueprints touched, depth reached, blockers hit, OD-06
   resolution state at end of task.

ACCEPTANCE CRITERIA:
- Pulse round trip succeeds end-to-end with this CORE-02 in place.
- Interface frozen (no further contract changes without ADR).
- OD-06 has a recorded resolution: ADR-010 committed, or explicitly deferred with a
  stated reason and a stated trigger for re-opening.
- Worklog updated.
- No depth-3+ work snuck in "while I was here."

KILL TRIGGER (§4, specific to Milestone 0):
If elapsed time on CORE-02 alone exceeds 4 weeks (half of Milestone 0's 8-week ceiling),
STOP and surface to tech lead. This is NOT "push harder." It's the signal that §9's
"does CORE-02's real build time match the unblocks-everything framing" unknown is
resolving the wrong way, and the model may need adjustment before more blueprints pile
on top of a shaky CORE-02. §4 is explicit: hitting the 8-week total ceiling is a
stop-the-line reassessment, not a calibrate-forward event — CORE-02 at 4 weeks is the
early-warning version of the same trigger.

DO NOT:
- Pre-resolve OD-02. It's a Cooldown 1 decision (§8.1). Milestone 0's success criterion
  is the Pulse round trip, full stop — OD-02 is not part of that criterion.
- Expand lint scope (§6). Lint expansion starts after Milestone 0, in cooldowns.
- Touch other blueprints' interfaces. Even if CORE-02's needs suggest a BRIDGE-01
  interface change, that's ADR-gated (§2.1) — surface, don't edit.
```

The other seven Milestone 0 blueprints (CORE-04/05/06 stubs, HUB-01, BRIDGE-01 stub, ISPOKE-09,
ESPOKE-01) follow this shape with two adjustments: (a) swap the spec in step 1, (b) for the three that
need depth 2 per §4 (`BRIDGE-01`, `ISPOKE-09`, `ESPOKE-01`), keep the depth-2 acceptance; for the
four that can stay at depth 1 (CORE-04/05/06 stubs, possibly HUB-01), drop "happy path works
end-to-end" to "interface exists, type-checks against dependents." The Pulse round trip still has to
succeed at the milestone level, but it succeeds via the depth-2 blueprints carrying the load.

### 11.6 Lap widen prompts — admit next-most-depended-upon blueprint

The widen step (§4.3) admits one new blueprint per ring per lap, chosen by inbound-edge count from
already-admitted blueprints in `INDEX.md` §5.2. This is a two-agent task by design: Jules for the
dependency analysis (fresh-clone perspective, async autonomy, good at structured graph queries), Cline
for the actual admission commit (full repo context, iterative editor). The hand-off between them is
where §2.1 freeze discipline is most at risk — Jules doesn't see the worklog, Cline doesn't see Jules's
analysis unless explicitly given it.

```
TASK: Lap <N> widen step — admit next-most-depended-upon Hub blueprint
AGENT: Jules (cloud, async) for dependency analysis; Cline (local) for admission commit.
PRECONDITION: §11.3 shared context block delivered to BOTH agents. Lap <N> widen step active.

GOAL: identify which not-yet-admitted Hub blueprint the current matrix most needs, and admit
it at depth 1 (stub). One blueprint per ring per lap — do not admit multiple Hub blueprints
in the same widen step.

PHASE 1 — JULES (dependency analysis):
1. Read INDEX.md §5.2. Parse the Mermaid graph (it's real `A --> B` edges, not prose).
2. For each Hub blueprint NOT YET admitted to the matrix (check the matrix snapshot in the
   shared context block), count inbound edges FROM already-admitted blueprints.
3. Rank by inbound-edge count, descending. Return top 3 with the edge list for each.
4. Cross-check against the §4.3 lap-1 widen constraint: if this is lap 1 AND OD-02 is
   unresolved, EXCLUDE CORE-16 and HUB-04 from the candidate set. Surface this exclusion
   explicitly in the response — do not silently omit them.
5. If §5.2's coverage for Hub is exhausted (all covered Hub blueprints already admitted),
   surface that as a §9 trigger — §5.2 needs extending before widen can continue for this
   ring. Do not pick from uncovered blueprints; the "next-most-depended-upon" rule has no
   data to compute against there.
6. Return: ranked candidate list, recommended admit, exclusion notes, §5.2 coverage status.

PHASE 2 — HAND-OFF (§11.13):
7. Tech lead reviews Jules's analysis. Produces hand-off bundle for Cline: recommended
   blueprint, in-scope files, out-of-scope files (frozen interfaces, dependents outside
   scope), §5.2 status.

PHASE 3 — CLINE (admission commit):
8. Receive hand-off bundle. Read the recommended blueprint's STRUCTURE-XX spec.
9. Implement depth 1: interface exists, no real logic. Freeze interface at first commit
   (§2.1). Commit message: "<BLUEPRINT-ID> depth 1 (lap <N> widen): <summary>".
10. Update matrix snapshot in worklog. Mark blueprint as admitted at depth 1, lap <N>,
    per-blueprint floor = 1.

ACCEPTANCE CRITERIA:
- One new blueprint admitted at depth 1.
- Interface frozen at first commit.
- OD-02 sequencing respected (no CORE-16/HUB-04 admission before OD-02 resolves, if lap 1).
- §5.2 coverage status recorded (either "still has covered candidates" or "exhausted, §9
  trigger fired").
- Worklog updated by BOTH agents (Jules appends Phase 1 entry; Cline appends Phase 3 entry).

DO NOT:
- Deepen this blueprint in the same lap. Widen and deepen are separate bet-slots (§4.3
  Gap A). Deepening happens in a separate bet later in the same lap, scoped to its own
  per-blueprint floor — which for a freshly-admitted blueprint is just +0 this lap (it
  was admitted this lap, so its "laps since admission" counter is 0).
- Admit multiple blueprints per ring per lap. The widen step is one per ring, deliberately
  — admitting more would let widening outpace deepening and reintroduce the late-integration
  surprise Spiral Deepening exists to prevent.
- Skip the Jules phase and admit "the obvious next one." The whole point of the dependency
  analysis is that "obvious" is usually wrong — §5.2's edges often surprise.
```

### 11.7 Lap deepen prompts — per-blueprint relative floor

The deepen step (§4.3) advances each blueprint below its own per-blueprint floor by exactly one depth
level. The per-blueprint floor is the v3.4 fix for the v3.2 spec bug — global lap floors broke for
blueprints admitted at different laps. The prompt below encodes the per-blueprint rule explicitly so
the agent doesn't fall back to a global floor "for simplicity."

```
TASK: Lap <N> deepen step — advance <BLUEPRINT-ID> from depth <D> to depth <D+1>
AGENT: Cline (local, iterative). Aider acceptable for single-blueprint refactors with many
small commits.
PRECONDITION: §11.3 shared context block delivered. <BLUEPRINT-ID> is below its per-blueprint
floor for lap <N>.

GOAL: advance <BLUEPRINT-ID> by exactly one depth level. Not "as far as you can get in the
time-box" — exactly one level. The per-blueprint relative floor (§4.3) sets the pace; do not
exceed it.

PER-BLUEPRINT FLOOR CHECK (do this FIRST, before any implementation):
1. Look up <BLUEPRINT-ID>'s admission lap in the worklog.
2. Compute: target_depth = min(admission_depth + (current_lap - admission_lap), 5).
3. If <BLUEPRINT-ID>'s current depth is already at or above target_depth, STOP — this
   blueprint is not below floor for lap <N>. Surface the mis-assignment to the tech lead;
   do not deepen "because the prompt asked."
4. If current_depth + 1 > target_depth, also STOP — the floor allows +1, not +2. Surface
   this; the tech lead may have intended a different scope.

STEPS (if floor check passes):
5. Read <BLUEPRINT-ID>'s STRUCTURE-XX spec. Identify what depth <D+1> requires:
   - 2→3: error paths — enumerate failure modes from the spec, implement each. Every named
     failure mode returns a defined error, not a crash.
   - 3→4: observability — logging, metrics, audit wired per the blueprint's CI criteria.
     Logs structured (not free-form strings); every public call produces a metric.
   - 4→5: production hardening — stated performance targets met (with measurement, not
     assertion); security-review checklist complete. If the spec lacks a performance
     target, surface that — do not invent one.
   - 5→6: at-scale verified — load-tested against real targets. Rare; only if explicitly
     scoped by the tech lead. If you reach this without explicit scope, STOP.
6. Implement. Do NOT change the frozen interface (§2.1). If you find the interface
   insufficient at this depth, STOP — that's an ADR-gated event (§11.10), not a casual
   edit. Surface the deficiency, draft an ADR request, wait for tech-lead decision.
7. Commit atomic. Commit message: "<BLUEPRINT-ID> depth <D>→<D+1>: <summary>".
8. Update worklog (§11.13) with: depth reached, surprises, any ADR requests surfaced,
   per-blueprint floor recomputed for next lap.

ACCEPTANCE CRITERIA:
- Depth advanced by exactly one level. Not zero, not two.
- Interface unchanged (or ADR drafted if a change is genuinely needed — see §11.10).
- STRUCTURE-XX's depth-<D+1>-specific criteria met (error paths enumerated, observability
  wired, performance targets measured, etc. — whichever applies at this depth).
- Worklog updated.

BET-KILL TRIGGER (§4.2):
If elapsed time on this deepening bet exceeds 1.5× its time-box, STOP. Keep what's done —
the interface was already frozen at admission (§2.1), so partial deepening is never wasted.
Surface to tech lead for re-scoping decision. This is a routine re-scoping event (§4.3
Gap B), NOT a methodology failure. Killing one bet does not imply Spiral Deepening is
wrong, the way Milestone 0 exceeding its ceiling would. Do not conflate the two —
conflation leads to either premature methodology-reassessment or premature push-through,
both bad.

DO NOT:
- Apply a global lap floor ("everything goes to depth <N+2> this lap"). That's the v3.2
  spec bug. The per-blueprint floor is the v3.4 fix. If you find yourself wanting to
  apply a global floor "for simplicity," STOP — you're about to reintroduce a known bug.
- Deepen multiple blueprints in one bet. One bet = one blueprint = one depth level. The
  matrix model (§2) is two-dimensional precisely because widening and deepening are
  separately paced.
- Skip the floor check. It's step 1 for a reason — without it, the per-blueprint rule
  is just words.
```

### 11.8 Cooldown prompts — lint-scope expansion and content Ring Lock

Cooldowns (§7) are 2 weeks under solo mode. Each cooldown carries two kinds of work: engineering
lint-scope expansion (§6) and content Ring Lock reconciliation (§3). The prompts below cover both.
Lint-scope expansion is explicitly **Cline-only** — Jules and Devin lack the iterative test loop
needed to actually validate that a new lint check catches what it claims to catch. Don't be tempted to
delegate this to a cloud agent to "save Cline's time for bet work" — that trade produces lint checks
that look plausible and miss real drift.

```
TASK: Cooldown <N> — lint-scope expansion (one drift class)
AGENT: Cline (local). Do NOT use Jules, Devin, or Copilot Workspace for lint expansion.
PRECONDITION: §11.3 shared context block delivered. Cooldown <N> active (NOT a bet week).

GOAL: extend Verification/lint/run.php to catch ONE specific drift class. Pick from:
  (a) Pulse 6-tuple consistency across repos
  (b) Naming drift, folder vs. INDEX.md (OD-05)
  (c) Soft-Freeze violations — PR touching a frozen interface (§2.1) without citing an ADR
  (d) Blueprint-fidelity drift — structural diff of code against STRUCTURE-XX spec
Do NOT pick more than one. Cooldowns are 2 weeks; scope discipline matters. If multiple
need doing, queue the others for subsequent cooldowns.

STEPS:
1. Read the existing run.php (244 lines as of last known commit; verify line count). Read
   architecture-lint.yml. Understand the current scope and the existing check patterns.
2. Pick ONE drift class (see above). Read the relevant OD or governance rule.
3. Implement the check. Add fixtures under Verification/lint/fixtures/ — both positive
   (must pass) and negative (must fail) cases. Without fixtures, the check is untested
   and §6's "lint is the second reviewer" promise is hollow.
4. Run the new check against the current repo. It MUST pass on all current code. If it
   fails, that's real drift — surface it to the tech lead BEFORE merging the lint change.
   Don't suppress the failure to make the lint change mergeable.
5. Wire the check into architecture-lint.yml so CI runs it on every PR.
6. Update worklog (§11.13) with: check added, fixture count, any drift discovered,
   OD touched (if (b), then OD-05; if (c), cite §2.1; if (d), note §6's honest-caveat
   language about full semantic diffing not being solved).

ACCEPTANCE CRITERIA:
- One new lint check live in CI.
- Both positive and negative fixtures committed.
- No new drift suppressed (or, if drift found, escalated to tech lead before merge).
- Worklog updated.

DO NOT:
- Expand lint during bet weeks. §6 is explicit: lint-scope expansion happens during
  cooldowns, NEVER during bet weeks. If you find a lint gap mid-bet, log it for the
  next cooldown — do not act on it then, even if "it's just one quick check."
- Batch multiple drift classes. One per cooldown, deliberately — batching produces
  shallow checks that miss edge cases, which is worse than no check at all (false
  confidence).
- Skip the fixture step. A lint check without fixtures is an untested assertion, which
  is exactly the kind of thing §6's "honest caveat" row on blueprint-fidelity drift
  exists to prevent.
```

Content Ring Lock cooldown prompt (separate work-stream, same cooldown):

```
TASK: Cooldown <N> — content Ring Lock checkpoint (marketer/media review)
AGENT: Human-driven checkpoint (marketer + media + tech lead). Cline assists on the diff.
PRECONDITION: §11.3 shared context block delivered. Cooldown <N> active.

GOAL: verify content (copy written against HUB-13 keys, assets produced against HUB-26
tokens, copy fitted to ESPOKE-05 wireframe) still matches the frozen contracts. This is
§3's promised cadence: every cooldown includes one content Ring Lock checkpoint, not
just engineering reconciliation. Skipping it moves content drift to integration time,
which is the failure mode Cooldown 0 exists to prevent, just deferred.

STEPS:
1. (Cline) Diff the content tree against the contract tree since the last cooldown:
   - For each HUB-13 key referenced in content files, verify the key still exists in
     HUB-13's frozen taxonomy (Architecture/Cooldown0/HUB-13-stringkeys.md).
   - For each HUB-26 token referenced in asset manifests, verify the token still exists
     in HUB-26's frozen theme (Architecture/Cooldown0/HUB-26-tokens.md).
   - For each ESPOKE-05 wireframe slot referenced in copy, verify the slot still exists
     in the frozen wireframe (Architecture/Cooldown0/ESPOKE-05-wireframe.md).
2. (Cline) Output a drift report: matches, mismatches, orphans.
   - Mismatches: content references a key/token/slot that no longer exists (contract
     drifted).
   - Orphans type 1: content references a key/token/slot that exists but no content
     uses it (contract gap, candidate for deprecation).
   - Orphans type 2: content has free-form strings/dimensions not mapped to any
     key/token (content gap, candidate for migration to a real key/token).
3. (Human — marketer + media + tech lead) Review the drift report. For each item:
   - Mismatch → content rework (cheap, content side fixes) OR contract ADR (expensive,
     engineering + content sides both rework). Default to content rework; only ADR if
     the contract change is architecturally necessary.
   - Orphan type 1 → content gap (write the missing copy) OR contract deprecation ADR.
   - Orphan type 2 → migrate content to use real keys/tokens, OR extend the contract
     (ADR-gated per §2.1 / §3 change control).
4. (Cline) Apply the agreed changes. Update worklog.

ACCEPTANCE CRITERIA:
- Drift report produced (committed under Architecture/Cooldown<N>/content-drift.md).
- Decisions recorded (which side reworks, which ADRs drafted).
- Worklog updated.

This is the cadence §3 promised. Every cooldown, not just engineering's. If a cooldown
goes by without this checkpoint, the drift that's accumulated gets discovered at
integration time — which is exactly the failure mode Cooldown 0 exists to prevent,
just deferred to a later point in the project instead of eliminated.
```

### 11.9 Per-depth-level prompts — compact reference

The prompts in §§11.4–11.8 are task-specific. The table below is the compact depth-level reference
that any task-specific prompt can cite inline ("implement to depth 3 per §11.9"). It exists so each
prompt doesn't have to re-state the depth semantics every time, and so an agent driving an unusual
task (a one-off ADR-implementation, a refactor) can still look up what "depth N" means without
reading §4.1 of the SDLC doc itself.

| Depth | Goal | Prompt prefix | Acceptance signal |
|---|---|---|---|
| **1** (stub) | Interface exists, no real logic | "Implement `<ID>` at depth 1 per §11.9. Read STRUCTURE-XX. Define the interface only. No behavior. Freeze the interface at first commit (§2.1)." | Interface compiles; dependents can type-reference it; no real logic exists; commit message contains "depth 1". |
| **2** (happy path) | The intended case works end-to-end | "Implement `<ID>` at depth 2 per §11.9. Happy path must work with all admitted dependents. Do not implement error paths. Do not change the frozen interface." | Pulse round trip succeeds with this blueprint in place; no error handling beyond "crash and let the caller see it"; commit message contains "depth 2". |
| **3** (error paths) | Failure modes handled | "Advance `<ID>` from depth 2 to 3 per §11.9. Enumerate failure modes from STRUCTURE-XX. Implement each error path. Do not change the frozen interface." | Each named failure mode returns a defined error, not a crash; interface unchanged; commit message contains "depth 2→3" or "depth 3". |
| **4** (observability) | Logging, metrics, audit per STRUCTURE-XX CI criteria | "Advance `<ID>` from depth 3 to 4 per §11.9. Wire observability per STRUCTURE-XX. Every error path produces a structured log entry; every public call produces a metric. Do not change the frozen interface." | STRUCTURE-XX's CI criteria for observability pass; logs structured (JSON or key=value, not free-form); commit message contains "depth 3→4" or "depth 4". |
| **5** (production hardening) | Stated performance targets met, security-reviewed | "Advance `<ID>` from depth 4 to 5 per §11.9. Meet the performance targets stated in STRUCTURE-XX. Surface any target that's missing or unmeasurable — do not invent one. Run the security-review checklist. Do not change the frozen interface." | Performance targets met with measurement (not assertion); security review checklist complete; interface unchanged; commit message contains "depth 4→5" or "depth 5". |
| **6** (at-scale verified) | Load-tested against real targets | "Advance `<ID>` from depth 5 to 6 per §11.9. This is rare — only if explicitly scoped by the tech lead. Run the load-test profile. Produce a report with real numbers, not extrapolations." | Load-test report committed with real numbers; no "should handle X" language; tech-lead scope citation in worklog; commit message contains "depth 5→6" or "depth 6". |

Two cross-cutting rules apply to every depth level above 1, and are stated here once rather than
repeated in every row: (a) the frozen interface does not change without an ADR (§2.1 / §11.10), and
(b) the commit message must contain the depth so §5's throughput calculation can separate build from
deepening work. An agent that "doesn't bother" with the depth in the commit message is breaking §5's
data pipeline — surface this as a lint-gap candidate for the next cooldown (§11.8).

### 11.10 ADR-gated event prompts — interface freeze violations and OD resolution

Two scenarios trigger ADR-gated workflows: a frozen-interface change request (§2.1 violation, possibly
surfaced mid-bet by a deepening task) and an OD resolution (§8.1, on its stated cadence). Both follow
the same shape: agent surfaces, drafts ADR, tech lead approves, then implementation — never
implementation before approval.

```
TASK: ADR — frozen interface change request for <BLUEPRINT-ID>
AGENT: Claude web (conversational) for ADR drafting; Cline (local) for implementation after
approval.
TRIGGER: a deepening bet (§11.7) discovered that <BLUEPRINT-ID>'s frozen interface is
insufficient at the target depth. The deepening task has already STOPPED per §11.7 step 6.

GOAL: produce an ADR that lets the tech lead make an informed decision about whether to
change the frozen interface. NOT to make the decision for them.

STEPS:
1. (Cline) STOP implementation. Do not edit the interface.
2. (Cline) Document the specific deficiency: what couldn't be done at the target depth with
   the current interface? Be concrete — code-level (which method signature, which DTO field,
   which contract clause), not vibes ("the interface feels limiting").
3. (Cline) Capture current state in worklog: blueprints affected, depth reached before
   STOP, ADR request ID assigned.
4. (Claude web) Draft the ADR using the project's ADR template. Include:
   - The current interface (verbatim from the frozen commit).
   - The proposed change (minimal — only what's needed to unblock the depth target).
   - The dependents that would be affected (run §11.6's INDEX.md §5.2 inbound-edge query
     against <BLUEPRINT-ID>; list every dependent with its current depth).
   - The cost of not changing (workaround, technical debt, deferred depth).
   - The cost of changing (rework across dependents, in person-weeks if Milestone 0 is
     complete, in "unknown until W measured" otherwise).
   - Alternatives considered (at least one — the status quo is always an alternative).
5. (Tech lead) Review. Approve, reject, or request alternatives.
6. (Cline) Only after approval: implement the change. Bump the interface version (per the
   project's versioning convention). Migrate dependents in the same commit (or a sequence
   of commits within the same bet — do not leave a half-migrated state at end of bet).
7. (Cline) Update worklog with: ADR ID, dependents migrated, depth reached post-change,
   per-blueprint floor recomputed for dependents whose admission depth effectively changed.

ACCEPTANCE CRITERIA:
- ADR committed before any interface edit (timestamp on ADR commit < timestamp on first
  interface edit).
- All dependents migrated (no orphan references — this is a lint check, §11.8 candidate).
- Worklog updated.

This is the §2.1 discipline: changing a frozen interface is an ADR-gated event, same as
the old Ring Lock violation. Casual edits to a frozen interface are forbidden regardless
of which bet "owns" the blueprint. An agent that "just improves" a frozen interface mid-
deepening without surfacing it has broken §2.1 — even if the improvement is genuinely
better, the dependents weren't given a chance to migrate deliberately.
```

OD resolution prompt (rarer; runs on §8.1's cadence):

```
TASK: Resolve OD-<NN> (per §8.1 cadence)
AGENT: Claude web (conversational) for analysis and ADR drafting; Cline (local) for any
implementation after approval.
TRIGGER: OD-<NN> has reached its §8.1 resolution point:
  - OD-06 (opcache baseline / ADR-010): during Milestone 0, when CORE-02 is being built.
  - OD-02 (post-quantum / algorithm-agility JWT): during Cooldown 1, after Milestone 0
    completes. NOT earlier, NOT later.
  - OD-01, OD-03, OD-04, OD-05: per their existing OPEN-DECISIONS.md owners/timing.

GOAL: close OD-<NN> with an ADR that records the decision, the alternatives considered,
and the trigger for re-opening (if any).

STEPS:
1. (Claude web) Read OD-<NN>'s entry in Architecture/OPEN-DECISIONS.md. Read every
   cross-reference it cites.
2. (Claude web) Draft the ADR. Include:
   - The decision (concrete, not "we will evaluate").
   - The alternatives considered (at least two — the status quo, plus at least one other).
   - The reasoning (why this alternative, citing real constraints from the repo).
   - The trigger for re-opening (what would change in the project that would make this
     decision wrong — e.g., for OD-02, "NIST standardizes a different PQ algorithm").
   - The implementation cost (which blueprints touched, at what depth, by when).
3. (Tech lead) Review. Approve, reject, or request alternatives.
4. (Cline) Implement per the ADR. Update OPEN-DECISIONS.md to mark OD-<NN> resolved with
   ADR ID and date.
5. (Cline) Update worklog.

ACCEPTANCE CRITERIA:
- ADR committed.
- OPEN-DECISIONS.md updated (status flipped to resolved, ADR ID cited).
- Worklog updated.

DO NOT:
- Pre-resolve. §8.1 is explicit about cadence. Resolving OD-02 during Milestone 0 "because
  we're already thinking about JWT" is exactly the kind of decision-smuggling that breaks
  the §3 Cooldown 0 / §8.1 Cooldown 1 separation. Surface, don't decide.
- Resolve without an ADR. "Resolved in conversation" is not resolution — it's a future
  dispute. The ADR is the artifact.
```

### 11.11 Bet-kill and Milestone-0-kill prompts — when to stop, not push

The kill triggers in §§4, 4.2 are the safety net for solo mode. The prompts below encode the kill
behavior explicitly, because the most common agent failure mode is pushing through a kill trigger
"to finish the task." Agents don't naturally stop; the prompt has to make stopping the success
condition.

```
TASK: Bet kill — <BET-NAME> exceeded 1.5× time-box
AGENT: Cline (local) for state capture; tech lead for re-scoping decision.
TRIGGER: elapsed time on the current bet has exceeded 1.5× its time-box (§4.2). The agent
is responsible for surfacing this; the tech lead is responsible for the decision.

STEPS:
1. (Cline) STOP work immediately. Do not start new files. Do not start new test cases. Do
   not "just finish this one method."
2. (Cline) Capture current state in a kill-event worklog entry:
   - Depth reached (was target <D>, reached <D-actual>).
   - Work committed (atomic commits, interface frozen — partial progress is preserved).
   - Work in progress (uncommitted; commit as WIP with explicit "WIP, not for merge" tag,
     or discard).
   - Blockers encountered (be specific — "OD-06 unresolved" is useful, "things were hard"
     is not).
   - Time elapsed vs. time-box.
3. (Cline) Surface kill event to tech lead. Suggest re-scoping options:
   - Option A: same ring gets next bet-slot with adjusted depth target (likely <D-actual+1>
     rather than the original <D>).
   - Option B: defer remaining deepening to a later lap; pick a different ring for the
     next bet.
   - Option C: surface an OD or ADR if the kill revealed an architectural issue (the
     interface was insufficient; the spec was wrong; the dependency assumption broke).
4. (Tech lead) Decide. Record the decision in the kill-event worklog entry.
5. (Cline) Apply the decision. Begin next bet per the tech lead's scope.

ACCEPTANCE CRITERIA:
- Kill event recorded in worklog with full state capture.
- Re-scope decision recorded (which option, why).
- No silent extension (no "let me just finish this one thing").

This is a routine re-scoping event (§4.3 Gap B), NOT a methodology failure. Killing one
bet does NOT imply Spiral Deepening is wrong, the way Milestone 0 exceeding its ceiling
would. Don't conflate the two — conflation leads to either premature methodology-
reassessment (treating a bet kill as a sign the whole model is broken) or premature
push-through (treating Milestone 0's 8-week ceiling as just a bet kill, when it's
actually a stop-the-line signal). The two triggers are different in kind, not just in
size.
```

Milestone 0 kill prompt (qualitatively different from a bet kill):

```
TASK: Milestone 0 kill — 8-week ceiling exceeded
AGENT: This is a tech-lead decision, NOT an agent decision. Agents surface; tech lead
decides. Cline captures state; Claude web assists on reassessment analysis.
TRIGGER: Milestone 0 has exceeded 8 weeks (§4). Cline surfaces this at week 7 (warning)
and week 8 (hard stop).

STEPS:
1. (Cline) At week 7 of Milestone 0: surface warning to tech lead. Include current state
   of all 8 blueprints (depth reached, blockers), end-to-end Pulse trace status.
2. (Cline) At week 8 of Milestone 0: surface hard-stop. STOP all Milestone 0 work. Do not
   push to week 9.
3. (Cline) Capture full state:
   - Which of the 8 blueprints reached depth 2 (the milestone target)?
   - Which are stuck, and on what (be specific — file/line/blocker)?
   - End-to-end Pulse trace status: does any round trip work, even partially?
   - Worklog of every Milestone 0 task, with depth reached and time spent.
4. (Tech lead + Claude web) Reassess. Three branches:
   - Branch A: architecture sound, solo throughput assumption wrong.
     → Options: bring on a second engineer; descope to a smaller Milestone 0 (fewer
       blueprints, lower depth target) and re-measure; pause and invest in agent-driven
       throughput improvements (better Cline prompts, more Jules delegation).
   - Branch B: architecture itself wrong.
     → Options: re-open ODs; restructure rings; abandon Spiral Deepening for a different
       SDLC model (Radial Incremental, despite its late-integration risk; or waterfall,
       despite its inflexibility).
   - Branch C: lint/review infrastructure insufficient, causing silent drift eating
     throughput.
     → Options: pause Milestone 0; invest in lint scope (§6/§11.8) before continuing;
       treat the first cooldown as the priority, not a post-Milestone-0 event.
5. (Tech lead) Record the reassessment outcome in a new ADR. Do not silently continue
   with the same plan.
6. (Cline) Apply the ADR's decision. Update worklog.

ACCEPTANCE CRITERIA:
- Milestone 0 kill event recorded with full state capture.
- Reassessment ADR committed.
- No "week 9" without an explicit reassessment decision in the ADR.

§4 is explicit: 8+ weeks is not "calibrate and push forward." Don't let agent deference
("Cline says it's almost done") or personal momentum override this. The kill trigger
exists precisely because solo mode can't absorb a late-stage surprise — pushing past
the trigger moves the surprise from week 8 (where it's still recoverable) to week 16
(where it isn't).
```

### 11.12 Review-pass prompts — the gap-hunting that found real issues across v3.x

The pattern across v3 → v3.1 → v3.2 → v3.3 → v3.4 was: each review pass found 1–3 real issues
(spec contradictions, arithmetic errors, edge-case gaps) plus several false positives. The false
positives are not waste — they're the cost of catching the real ones. Don't skip the review because
the last one was "mostly clean." This is the prompt for that review pass.

```
TASK: Review pass — gap-hunt SDLC-AGRD v<N> for spec contradictions and edge cases
AGENT: Claude web (conversational) for the gap-hunt itself; tech lead for triage; Cline
for applying fixes.
PRECONDITION: §11.3 shared context block delivered. The document under review is v<N>.

GOAL: find real contradictions, ambiguities, and edge cases in v<N>. NOT to confirm the
document is good — confirmation bias produces no findings, which is itself a finding (that
the review was not real).

STEPS:
1. (Claude web) Read the full document end-to-end. Read every cross-reference (every
   "§X.Y" citation). Open the cited section and verify the citation.
2. (Claude web) For each cross-reference, verify:
   - Does the cited section say what the citing section claims it says?
   - Does the cited section's rule apply in the citing section's context?
   - Is the cited section's terminology consistent with the citing section's?
3. (Claude web) For each rule with a numeric parameter (depth levels, week counts, lap
   counts, multipliers like 1.5×), verify:
   - Does the arithmetic actually work for blueprints admitted at different laps?
   - Does the arithmetic work for edge cases: first admitted (Milestone 0 cohort), last
     admitted (final widen step), fully-widened rings (no more widen candidates)?
   - Does the arithmetic reproduce the intent stated in the prose, or does it produce a
     different result? (v3.2's floor schedule was the canonical example of arithmetic
     not reproducing stated intent.)
4. (Claude web) For each "this document supersedes" claim, verify:
   - Does this document actually fix what it claims to fix?
   - Does it preserve what it claims to preserve?
   - Does it introduce new problems while fixing old ones? (v3.1's Phase structure
     was the canonical example — fixed v3's one-shot Phase gap but reintroduced Radial
     Incremental at smaller scale.)
5. (Claude web) For each "what this module does NOT promise" or "honest caveat" callout,
   verify:
   - Is the caveat actually a limit, or is it an unverified claim disguised as honesty?
   - Does the rest of the document respect the caveat, or does some other section
     quietly assume the caveat doesn't apply?
6. (Claude web) Output a numbered list of findings. For each:
   - Severity: real contradiction (blocks correctness) | ambiguity (invites
     misinterpretation) | edge case (breaks at boundary) | false positive (reviewer
     misread).
   - Citation: section numbers + quoted text.
   - Proposed fix: concrete, not "rewrite this section."
7. (Tech lead) Triage. For each finding:
   - Fix in next revision (real contradiction, ambiguity, edge case — accept the fix).
   - Defer to lap data (gap that's data-dependent by design, like §9's six unknowns).
   - Reject with rationale (false positive, or real but out of scope for this revision).
8. (Cline, after tech-lead decision) Apply accepted fixes. Produce v<N+1>. Update worklog.

ACCEPTANCE CRITERIA:
- Review report produced with numbered findings (zero findings is a red flag — re-run
  with a different angle, don't accept "the document is clean").
- Triage decisions recorded for every finding (accept/defer/reject + rationale).
- Fixes applied in a new versioned document, NOT in-place on v<N>. The version chain
  (v3 → v3.1 → v3.2 → v3.3 → v3.4 → ...) is the audit trail; in-place edits break it.

The pattern is: each review pass finds 1–3 real issues. If a pass finds zero, the pass
wasn't real. Re-run with a different framing — read the document backwards, read it from
the perspective of a freshly-admitted lap-3 blueprint, read it assuming §5's throughput
formula is wrong by 2×. Different framings catch different gaps.
```

### 11.13 Worklog discipline and cloud-local hand-off

Every agent appends to `/home/z/my-project/worklog.md` after completing its task. Never overwrite.
This is the only mechanism a solo tech lead has for reconstructing what happened across many agent
invocations — without it, two agents working in sequence will silently undo each other's assumptions.

```
TASK: Worklog entry — append, never overwrite
AGENT: Every agent, every task.

RULE: /home/z/my-project/worklog.md is append-only. Every agent appends a section after
completing its task. No agent overwrites prior sections. No agent edits another agent's
prior section — if a prior section is wrong, append a correction section citing the
original.

TEMPLATE (append after the last existing section, separated by a `---` line):
---
Task ID: <e.g., 2-a, where 2 is the lap and a is the agent sequence within that lap>
Agent: <agent name — Cline / Jules / Claude web / etc.>
Task: <one-line summary, concrete>

Work Log:
- <step 1, concrete>
- <step 2, concrete>
- ...

Stage Summary:
- <key results — depth reached, files modified, ADRs drafted>
- <decisions made — by the agent within its scope, or surfaced to tech lead>
- <artifacts produced — paths>
- <blockers surfaced — with proposed next steps>

Hand-off notes (if switching agents):
- Next agent should read: <files, with paths>
- Next agent should know: <context not in files — e.g., "OD-02 still unresolved, lap-1
  widen constraint active">
- Next agent should NOT redo: <what's already done — e.g., "ESPOKE-05 wireframe is frozen
  under Cooldown 0 ADR-gate; do not propose changes without an ADR">

If you are an agent and you cannot find the worklog, ASK before proceeding — its absence
is a red flag, not a permission to skip logging. The worklog is the only thing that lets
a solo tech lead reconstruct what happened across many agent invocations; an agent that
skips logging has effectively done work that doesn't exist for the next agent.
```

Hand-off between local and cloud agents (the highest-risk transition in solo mode):

```
TASK: Hand-off — local agent → cloud agent (or vice versa)
AGENT: Tech lead initiates; both agents consume.

WHEN TO USE: a task started locally needs to continue on a cloud agent, or vice versa.
Common scenarios:
- Cline starts a widen-step dependency analysis, hands off to Jules for the structured
  graph query against INDEX.md §5.2 (Jules is better at this — fresh clone, async).
- Jules returns a candidate blueprint recommendation; tech lead hands off to Cline for
  the actual admission commit (Cline has full repo context, editor integration).
- Cline surfaces an interface-freeze violation; tech lead hands off to Claude web for
  ADR drafting (Claude is better at structured prose with alternatives-considered).
- Claude web returns an ADR; tech lead hands off to Cline for implementation.

STEPS:
1. (Source agent) Produce hand-off bundle in worklog:
   - Current state: depth reached, files modified (with paths), worklog entry ID for
     this task.
   - Specific task definition for target agent: scope, acceptance criteria, kill trigger.
   - Files target agent needs to read (with paths, not "the spec").
   - Files target agent should NOT touch (frozen interfaces, dependents outside scope,
     files being modified by other in-flight tasks).
   - ODs/ADRs in play (status, not just ID).
   - §11.3 shared context block (re-deliver; don't assume the target agent has it).
2. (Tech lead) Review the bundle. Add anything the source agent missed. Approve hand-off.
3. (Target agent) Receive the bundle. Read the worklog entries for every prior task in
   the same Task ID lineage (e.g., for Task 2-b, read 2-a). Acknowledge the kill trigger
   and the out-of-scope files list explicitly.
4. (Target agent) Execute. Append worklog entry on completion (citing the hand-off bundle
   in the entry).
5. (Source agent or tech lead, after target agent returns) Verify the target agent's
   output against the hand-off bundle. Specifically:
   - Did the target agent touch only the in-scope files?
   - Did the target agent respect the frozen interfaces?
   - Did the target agent hit the kill trigger and stop, or push through?
   - Is the worklog entry complete (all template fields filled)?
6. (Verifier) Surface any divergence. Update worklog with verification result.

ACCEPTANCE CRITERIA:
- Hand-off bundle produced BEFORE target agent starts (not after, not "in the same
  message").
- Target agent's output verified against the bundle (not just "looks good").
- Divergences surfaced, not silently merged.

Cloud agents (Jules, Devin, Copilot Workspace) have less context than local ones — they
run against a fresh clone with no conversation history and no worklog awareness. The
hand-off bundle is not optional politeness — it is the contract that keeps cloud-agent
work inside the §2.1 interface-freeze discipline. Skip it and you will find out at
integration time that Jules edited a frozen interface "because it looked cleaner," which
is exactly the kind of rework Spiral Deepening exists to prevent.
```

### 11.14 What this module deliberately does NOT promise

In the same style as §§6, 9's "honest caveat" callouts, this subsection states what §11 does not
promise. Each item below is a limit, not an aspiration.

- **These prompts are not autonomous agents.** Every prompt here assumes a tech lead reviewing the
  output. None of them are "run Jules and merge without looking." Solo mode multiplies AI
  implementation throughput; it does not substitute for the review capacity one person provides.
  The bottleneck is still attention, not typing speed. Prompts that pretend otherwise reintroduce
  exactly the silent-drift problem §6 (lint as second reviewer) exists to catch.

- **These prompts are not stable.** SDLC-AGRD v3.4 itself is explicit (§10) that gap-hunting has
  diminishing returns and the next iteration needs lap data. The prompts in this module are
  calibrated to v3.4's rules — when v3.5 lands (after lap 1 data, per §10), the prompts need
  re-calibration too. Treat §11 as v3.4-specific, not as a permanent operating manual. The
  §11.12 review-pass prompt applies to §11 itself: after v3.5 lands, gap-hunt §11 alongside the
  rest of the document.

- **These prompts do not eliminate the worklog discipline.** §11.13's append-only worklog rule is
  the only thing that lets a solo tech lead reconstruct what happened across many agent
  invocations. Prompts don't replace it; they assume it. An agent that drives a task well but
  skips the worklog entry has effectively done work that doesn't exist for the next agent, which
  is functionally equivalent to not doing the work at all.

- **These prompts do not resolve §9's unknowns.** §9 lists six explicit unknowns that are
  data-dependent by design: Milestone 0 duration, CORE-02 build time, blueprint-fidelity drift
  check hardness, deepening-vs-build throughput gap, lap count to full coverage, and INDEX.md §5.2
  coverage extension trigger. No prompt here resolves them — they wait for lap data, the same as
  the rest of v3.4. An agent that "resolves" one of these mid-task has smuggled an assumption,
  not produced a result.

- **Cloud-agent prompts assume agent behavior that may drift.** Jules, Devin, Copilot Workspace,
  Claude web, and Codex all update their behavior over time. A prompt that works today may not
  work in three months — the agent may become more cautious (refusing tasks it would have done),
  more aggressive (editing files it would have left alone), or differently scoped (handling a
  different task surface). The tech lead is responsible for re-validating prompts against the
  current agent behavior, not assuming the prompt is stable across agent versions. The §11.2
  matrix is a snapshot, not a permanent truth.

- **These prompts do not replace the SDLC document itself.** §11 is operational, not normative.
  The rules live in §§2–9; §11 just encodes them for agents. If §11 and §§2–9 ever appear to
  conflict, §§2–9 win — fix §11. An agent that appeals to §11 to justify violating §§2–9 has
  misread §11's role.

This module is operational, not aspirational. It says what to do with v3.4 right now, not what to
do forever. The day v3.5 lands, this module needs the same gap-hunt pass (§11.12) as the rest of
the document — and the day lap 1 produces real data, several prompts here will need rewriting
because the assumptions they encode (especially around throughput, kill-trigger units, and
deepening pace) will be replaced by measured numbers. That's not a flaw; that's the §5 calibration
discipline working as designed, applied to the prompts themselves.
