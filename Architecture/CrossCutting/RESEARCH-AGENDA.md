# Research Agenda


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This CrossCutting document records **research questions, not decisions**. Research tracks listed here are open investigations, not architectural requirements, not ADRs, and not implementation commitments. Every track is marked **Research — Not an Implementation Requirement** until and unless its findings are formally adopted via the project's ADR/OD governance process. A research track being listed here does not imply that DGLab has accepted any of its premises, hypotheses, or proposed design properties. **A governance rule is only as good as its enforcement — verify that any future claim based on this agenda is actually checked against the adopted decision, not against the research question itself.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

**Purpose.** This file records research questions that DGLab wants to investigate but has **not** yet adopted as architectural requirements, design decisions, or implementation commitments. It is the **only** canonical location for cross-cutting research tracks; research topics must not be silently inserted into ADRs, blueprints, or frozen-contract amendments.

> Each track below lists its research questions, working hypotheses, desired deliverables, and a status line. The status line MUST read **Research — Not an Implementation Requirement** until a track's findings are formally adopted through the ADR or OD governance process. When a track is adopted, the implementing ADR cites this agenda entry and the entry's status is updated to *Adopted — see ADR-XXX*.

> This agenda is distinct from `Architecture/OPEN-DECISIONS.md`. Open Decisions record forks awaiting a decision; this agenda records questions whose framing is not yet mature enough to even pose as a fork. The two files must not silently overwrite one another.

**Origin.** Created 2026-10-13 in response to SAAI synchronization directive ("SAAI Review: Z.ai Resynchronization and Verified Project Status") authorizing a new cross-cutting research document distinct from decision-fork and shortcoming-tracking artefacts.

---

## Track 1 — Platform Integrity, Copyright, Ownership, and Fair Dispute Resolution

**Status:** Research — Not an Implementation Requirement.

### Research questions

How can DGLab protect creators and legitimate users without reproducing the structural failures of existing platforms? Specifically:

- How does a platform detect and respond to **abuse, impersonation, fraud, and coordinated manipulation** without producing false positives at scale, and without concentrating enforcement in unaccountable private tribunals?
- How are **copyright ownership, licensing, attribution, provenance, and derivative works** represented, asserted, and disputed inside a multi-tenant PHP framework where Hub-tier services compose capabilities on behalf of many independent tenant applications?
- What is the right shape for **takedown requests, counterclaims, appeals, and fraudulent-complaint handling** when the platform itself does not own the content but hosts the substrate on which content is built?
- What does **transparent moderation and reviewable enforcement** look like in a Sovereign Stack context where audit trails (per `BLIND-SPOT-DOCTRINE.md` and the existing AuditRecord contract in `FROZEN-CONTRACTS.md`) are already first-class?
- How are **creator ownership, portability, interoperability, and platform independence** preserved when the same framework supplies identity (`HUB-04`), billing (`HUB-22`), media (`HUB-03`), and tenancy (`HUB-21`) to every Spoke?
- Which **commercial incentives** reward engagement at the expense of users and creators, and which of those incentives does DGLab explicitly refuse to adopt?

### Working hypotheses

1. **Enforcement is not authority.** The ability to enforce a rule is conceptually distinct from the moral and procedural authority to decide that the rule is fair. A platform that can block, remove, or ban has not thereby earned the right to define what counts as legitimate speech, original work, or fair use. This is the guiding hypothesis of the track and is **not** a verbatim restatement of any existing doctrine (see §"Related doctrines and contracts" below).
2. **Provenance is the substrate of ownership.** A framework that already maintains hash-chained audit provenance (per `AuditRecord` in `FROZEN-CONTRACTS.md`) is structurally well-positioned to express content provenance — but provenance is necessary, not sufficient. Provenance records *who did what when*; it does not by itself answer *who should be allowed to do what*.
3. **Moderation transparency is a property, not a policy.** Transparent moderation is a design property of the system (reviewable, contestable, auditable, time-bounded) rather than a paragraph in a terms-of-service document. This hypothesis is structurally similar to the Exitability hypothesis in Track 4 and is investigated jointly in Track 5.
4. **Fraudulent complaints are a first-class failure mode.** A dispute-resolution system that handles only legitimate complaints is incomplete; it must also handle malicious, automated, or coordinated fraudulent complaints without granting them the same standing as genuine ones.

### Desired deliverables

- A **threat model** covering abuse, impersonation, fraud, coordinated manipulation, and fraudulent complaints, scoped to a multi-tenant framework with Hub-mediated capabilities.
- A **stakeholder analysis** distinguishing platform operator, tenant application, creator, end user, and downstream re-user — and the rights and obligations of each.
- A **licensing and attribution model** that composes with the existing `AuditRecord` provenance schema rather than replacing it.
- A **dispute-handling principles document** covering takedown, counterclaim, appeal, and standing — explicitly addressing the case where the platform is the substrate, not the publisher.
- A **research-to-ADR migration note** specifying which findings, if any, mature into ADRs and which remain research outcomes.

### Related doctrines and contracts

- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — its governance principle ("an audit is a starting point, not a complete inventory") and its standard header ("a governance rule is only as good as its enforcement — verify that the rule is actually checked") are **related but not identical** to this track's guiding hypothesis. The doctrine addresses audit completeness and rule-enforcement quality; this track addresses the moral and procedural authority to define rules. The two ideas are complementary, not duplicative.
- `Architecture/FROZEN-CONTRACTS.md` — the `AuditRecord` schema (seq, tenant_id, request_id, fiber_id, actor_id, operation, target, before_hash, after_hash, prev_hash, entry_hash, created_at) is a candidate substrate for content provenance. The schema is **frozen**; any research outcome that proposes to extend it must do so via ADR, not via silent amendment.
- `Architecture/ADRs/ADR-013-mysql-primary-datastore.md` — constrains where provenance and dispute records can live.
- `Architecture/ADRs/ADR-008-argon2id-password-hashing.md`, `ADR-003-es256-jwt-signing.md`, `ADR-012-post-quantum-jwt-agility.md` — constrain identity and signature surface; relevant to impersonation prevention.

### Out of scope for this track

- Specific implementation of a content-moderation queue.
- Specific selection of a licensing regime (e.g., CC-BY, custom).
- A new Hub blueprint for content moderation. Any Hub blueprint proposal emerges from this track as a research **outcome**, not a starting premise.

---

## Track 2 — Avoiding the YouTube Model: Ownership, Governance, Incentives, and Lock-In

**Status:** Research — Not an Implementation Requirement.

### Research questions

Which ownership structures, governance mechanisms, and commercial incentives produce platform lock-in and centralized control, and which demonstrably avoid it?

- **Creator control and content portability.** What does it mean for a creator to "control" their content when the substrate is shared? What export formats, metadata conventions, and provenance carriers preserve content across platform migrations?
- **Transparent discovery and recommendation mechanisms.** What is the relationship between opaque recommendation algorithms and centralized control? Can a Sovereign Stack tenant run a transparent recommender without performance collapse, or is transparency necessarily partial?
- **Interoperable formats and documented APIs.** Which interop formats are genuinely portable (open spec, multiple implementations, no royalty) versus nominally open (spec published, single implementation, de-facto lock-in)?
- **Fair moderation appeals.** Under what conditions is an appeal fair —- visible, time-bounded, decided by a body that does not also act as accuser, and reversible when wrong?
- **Sustainable funding without exploitative engagement incentives.** Which funding models (subscription, transactional, public-goods, patronage, federated cost-sharing) are compatible with a framework that does not own the end-user relationship?
- **The ability to leave without unreasonable loss of content, data, or community.** What must the system hold in escrow on behalf of a departing creator, and what is the inverse — what is legitimately the platform's to retain?

### Working hypotheses

1. **Decentralization alone does not solve abuse, copyright, or governance problems.** A federated system can reproduce every failure mode of a centralized system if the federation boundaries do not align with accountability boundaries. This hypothesis is recorded in SAAI's original brief and is adopted here as a working assumption pending evidence.
2. **Lock-in is measurable, not merely declared.** The cost of leaving a platform is a function of: data export completeness, format openness, community graph portability, switching effort, and contractual penalties. A platform that promises "no lock-in" without making these dimensions measurable is making a non-claim.
3. **Engagement-maximizing incentives and creator-welfare incentives are not the same objective function.** A platform funded by engagement will, in expectation, optimize against creators' long-term interests even if every individual decision is defensible. This is a structural claim, not a claim about the morals of any specific operator.
4. **Recommendation transparency and recommendation quality are partially tradeable.** A fully transparent recommender may be gameable; a fully opaque recommender is unaccountable. The research question is the shape of the Pareto frontier, not a binary choice.

### Desired deliverables

- A **lock-in measurability framework** — concrete dimensions and metrics for the cost of leaving, applicable to any Sovereign Stack tenant.
- A **governance-incentive pattern catalog** — naming and analyzing ownership structures (cooperative, foundation-owned, tenant-governed, open-council) and their incentive alignment properties.
- A **federation accountability analysis** — under what conditions federated moderation does and does not reproduce centralized failures.
- A **funding-model compatibility matrix** — which funding models compose with which tenant business models without creating perverse incentives.

### Related doctrines and contracts

- `Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md` — its §1 principles (defence in depth, fail-safe, bounded resources, idempotency, atomicity at every boundary) constrain how transparent moderation and appeals can be implemented.
- `Architecture/ADRs/ADR-001-polyrepo-vs-monorepo.md` — establishes the polyrepo boundary; relevant to interop-format decisions.
- `Architecture/ADRs/ADR-020-stable-and-bleeding-edge-release-channels.md` — relevant to the migration-of-creators-across-versions question.

### Out of scope for this track

- A specific recommender implementation.
- A specific funding recommendation for DGLab itself.
- A federation protocol proposal. Any federation design emerges as a research outcome.

---

## Track 3 — Half Exceptions

**Status:** Research — Not an Implementation Requirement.

### Research questions

The term "Half Exceptions" originated in a discussion about personal preferences and is **not an established software-engineering term**. SAAI's directive is explicit: *"Do not assume 'Half Exceptions' is an established software-engineering term. Research the underlying concept before introducing a pattern, abstraction, or ADR."* The track therefore begins from terminology research, not from design.

- **Partial exceptions.** What is the right shape for a general preference or rule that has bounded exceptions under specific circumstances? When is an exception a first-class construct versus an ordinary conditional?
- **Partially negotiable preferences.** When some preferences in a system are flexible and others are non-negotiable, how is the boundary between them expressed, enforced, and made visible to operators and users?
- **Software analogies.** What existing patterns map onto these ideas? Candidate analogies include: configuration defaults with override hierarchies; authorization policies with bounded exception grants; architectural invariants versus soft constraints; fault tolerance with bounded degradation; feature flags with explicit scope, expiry, and reviewability.
- **Distinguishing primitives.** Can a clean taxonomy be developed that distinguishes: invariants (must hold), hard constraints (cannot be violated without ADR), defaults (overridable without ceremony), preferences (advisory), and explicit bounded exceptions (overridable only with authorization, scope, expiry, and audit)?
- **Exception lifecycle.** For any exception mechanism, what are the rules for: scope (what the exception applies to), authorization (who can grant it), expiry (when it ends), reviewability (who can see it was granted), and auditability (what record of it survives)?

### Working hypotheses

1. **"Half Exceptions" is not a term of art, but the underlying distinction is real.** Many systems conflate "rule" with "preference" and treat "exception" as an ad-hoc escape hatch. A cleaner taxonomy may improve reasoning, but the taxonomy must be earned by evidence (existing patterns, failure modes), not invented by analogy to a catchy phrase.
2. **Bounded exceptions and partially-negotiable preferences are structurally similar but not identical.** A bounded exception is a *rule-level* construct (the rule is in force, except when authorized otherwise). A partially-negotiable preference is a *meta-level* construct (the question of whether the preference is in force is itself negotiable). Conflating them produces category errors.
3. **Authorization, expiry, and audit are non-optional for any exception mechanism.** An exception mechanism that lacks any of these three properties degrades silently into an unconstrained override, which is structurally indistinguishable from no rule at all.

### Desired deliverables

- A **terminology note** establishing whether "Half Exceptions" maps onto any existing SE concept, or whether the underlying distinction requires a new (or differently-named) construct.
- A **primitive taxonomy** distinguishing invariants, hard constraints, defaults, preferences, and bounded exceptions, with criteria for classifying any given rule.
- A **software-analogy catalog** mapping the taxonomy onto existing patterns (configuration overrides, authz policies, feature flags, fault tolerance) with explicit notes on where the analogy holds and where it breaks.
- A **lifecycle specification template** for any future bounded-exception construct: scope, authorization, expiry, reviewability, auditability.

### Related doctrines and contracts

- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — the doctrine's standard header ("a governance rule is only as good as its enforcement") is directly relevant: a bounded exception is a rule-shape whose enforcement quality must itself be checkable.
- `Architecture/FROZEN-CONTRACTS.md` — frozen contracts are invariants (the strongest category). Any taxonomy that emerges from this track must classify frozen contracts as a non-negotiable primitive.
- `Architecture/ADRs/ADR-004-tier-enforcement-dag.md` — relevant to the question of how rule enforcement is layered across tiers.
- `Architecture/SDLC/SDLC-03-InterfaceFreeze.md` — the existing interface-freeze mechanism is a worked example of a hard constraint with bounded (ADR-gated) exceptions.

### Out of scope for this track

- A new exception-handling abstraction in code. No `BoundedException` class, trait, or interface is to be introduced on the basis of this track until the taxonomy and terminology note are reviewed.
- An ADR. The track produces research outputs; adoption as architecture is a separate decision.

---

## Track 4 — The Cancel-Anytime Paradox

**Status:** Research — Not an Implementation Requirement.

### Original statement (preserve verbatim)

> "Cancel anytime" is the most repeated promise in the history of commerce. It is also the one most rarely acted upon.

**Recorded as:** Research proposition, not an established empirical or statistical finding. The proposition is the *prompt* for the investigation below; it is not cited here as a fact.

### Research questions

What is the difference between **offering** someone the freedom to leave and **designing** a system in which leaving is genuinely practical?

- **Cancellation friction and dark patterns.** What patterns of UI, defaults, timeouts, and confirmation sequences operate as cancellation friction without being framed as such in policy language?
- **Data, integration, and community lock-in.** Beyond contractual lock-in, what forms of structural lock-in operate via data that cannot be exported, integrations that cannot be re-pointed, and communities that cannot migrate together?
- **Switching costs and asymmetric commitment.** When one party commits to a multi-year build and the other commits to a monthly cancellation right, the relationship is asymmetric in ways the nominal freedom to cancel does not capture. What is the full inventory of these asymmetries?
- **Data export, interoperability, and account closure.** What does a *usable* data export look like — not merely a compliant one? What is the boundary between cancellation (stop billing, stop service), account deletion (erase identity), content removal (erase artefacts), and data portability (carry artefacts elsewhere)?
- **Commercial incentives that undermine nominal exit rights.** Under what funding and growth-model assumptions does "easy cancellation" become a threat to the operator's revenue model, and what design responses preserve both operator solvency and user exitability?
- **Penalties and loss-of-work on exit.** Can a user leave without unreasonable penalties or loss of their work? What is "unreasonable" and who decides?

### Working hypotheses

1. **Exitability is a design property, not a policy promise.** A potential architectural principle: *Exitability should be evaluated as a design property, not merely promised in policy language.* This principle is structurally parallel to the moderation-transparency hypothesis in Track 1 and is investigated jointly in Track 5.
2. **Cancellation, deletion, removal, and portability are four distinct operations.** Conflating them — either in policy or in implementation — produces both UX failures (the user wanted to cancel billing but the system deleted their work) and accountability failures (the user wanted to erase their identity but the system retained their content for "operational continuity").
3. **Nominal exit rights and practical exitability diverge systematically under engagement-funded business models.** This is a structural claim about incentives, not a claim about the morals of any specific operator. The research question is the *shape* of the divergence, not its existence.

### Desired deliverables

- An **exitability measurement framework** — concrete dimensions (export completeness, format openness, switching effort, community migration, contractual penalty, retention policy) with measurable criteria for each. This framework is a candidate research output that composes with the lock-in measurability framework in Track 2.
- A **four-operations separation analysis** — cancellation, account deletion, content removal, and data portability as distinct operations, each with its own contract, audit trail, and reversal path.
- A **dark-pattern inventory** adapted from existing public-interest dark-pattern taxonomies, scoped to the patterns that operate on exit flows specifically.
- A **design-property checklist** for evaluating whether a Sovereign Stack tenant application has *practical* exitability, not merely *promised* exitability.

### Related doctrines and contracts

- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — the doctrine's standard header ("a governance rule is only as good as its enforcement") applies directly to exit policy: an exit policy is only as good as the practical exitability it produces.
- `Architecture/ADRs/ADR-009-ulid-over-uuid.md` — ULIDs are used as opaque identifiers throughout the audit trail; relevant to the question of whether identity can be cleanly erased versus merely deactivated.
- `Architecture/ADRs/ADR-013-mysql-primary-datastore.md` — constrains where deletion and retention policies are physically implemented.
- `Architecture/CrossCutting/MEMORY-GOVERNANCE.md` and `MEMORY.md` — relevant to retention policy and the right-to-be-forgotten surface.

### Out of scope for this track

- A specific cancellation UI proposal.
- A specific retention schedule. Both emerge as research outcomes, not premises.
- A new ADR amending existing identity, billing, or audit contracts.

---

## Track 5 — Connecting Principles and Potential Architectural Implications

**Status:** Research — Not an Implementation Requirement.

### Research questions

The four tracks above share a common question:

> **How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?**

This track investigates the shared implications without prematurely creating abstractions. Specifically:

- What **provenance, permissions, auditability, portability, governance boundaries, and transparent exception handling** concepts recur across Tracks 1–4?
- Where do the tracks' working hypotheses compose (e.g., moderation-transparency in Track 1 and exitability in Track 4 both treat "design property, not policy promise" as a candidate principle)?
- Where do the tracks' working hypotheses *diverge* (e.g., Track 1's emphasis on authority vs. Track 4's emphasis on practical cost)?
- Which existing DGLab doctrines (BLIND-SPOT, NUCLEAR-GRADE, MEMORY-GOVERNANCE) are *natural anchors* for shared principles, and which would require amendment to accommodate them?
- What are the **potential architectural implications for DGLab** — without proposing specific ADRs, contracts, or blueprints — of treating "stated-rights-exercisable-in-practice" as a first-class design dimension?

### Working hypotheses

1. **Design-property framing recurs.** Tracks 1, 2, and 4 each independently produce a hypothesis of the form *"X should be evaluated as a design property, not merely promised in policy language."* This recurrence is itself evidence that the framing may be a candidate cross-cutting principle, but the principle must be earned by evidence across the tracks, not stipulated.
2. **Provenance is the common substrate.** The existing `AuditRecord` provenance schema (frozen in `FROZEN-CONTRACTS.md`) recurs as a candidate substrate in Track 1 (copyright), Track 2 (lock-in measurement), and Track 4 (exit audit). This may indicate that provenance is the load-bearing primitive across the research agenda — but it may also indicate that the agenda is over-indexing on the one substrate it already has.
3. **Authority and cost are distinct axes.** Track 1's hypothesis ("enforcement is not authority") and Track 4's hypothesis ("policy is not practical exitability") address *different* failures: the first addresses who has the right to decide a rule; the second addresses whether the rule's promised effect actually materializes. A shared framework must respect this distinction rather than collapse the two into a single "fairness" principle.

### Desired deliverables

- A **connecting-principles note** identifying which hypotheses compose across tracks, which diverge, and which compose *only under specific scope conditions*.
- A **doctrinal-impact analysis** specifying which existing doctrines are natural anchors and which would require amendment — without proposing the amendments themselves.
- A **research-to-architecture migration protocol** specifying how findings from Tracks 1–4 (and this track) graduate from research to ADRs, blueprints, or frozen-contract amendments. The protocol must preserve the three-state distinction: research ≠ decision ≠ implementation.

### Related doctrines and contracts

- All four preceding tracks' "Related doctrines and contracts" sections, by reference.
- `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` — its governance principle ("an audit is a starting point, not a complete inventory") is the methodological anchor: this track must not prematurely close what Tracks 1–4 leave open.
- `Architecture/SDLC/SDLC-03-InterfaceFreeze.md` and `Architecture/FROZEN-CONTRACTS.md` — establish the project's existing rule for how research outcomes become binding architecture (ADR-gated, never silent amendment).

### Out of scope for this track

- A new cross-cutting principle ("Design Property Doctrine" or similar). No such doctrine is to be proposed on the basis of this track until the connecting-principles note is reviewed and SAAI / the tech lead approve promotion.
- Any new ADR. The track produces research outputs only.

---

## SuperPHP Naming Investigation — Extracted to Standalone Report

**Status:** Research — Not an Implementation Requirement. **No rename is approved.**

The SuperPHP naming investigation has been **extracted to a standalone research report** at [`RESEARCH-SuperPHP-NAMING.md`](./RESEARCH-SuperPHP-NAMING.md) per the handoff requirement (Kilo analysis 2026-10-20: "Extract SuperPHP naming investigation into standalone report"). The agenda proper records the five product-research tracks + connecting-principles track; the naming investigation is referenced from here but maintained separately so it can be reviewed, referenced, and resolved independently.

**Summary of findings** (full content in the standalone report):

- **Five candidates recorded** by SAAI's 2026-10-13 directive: Forge, Foundry, Nexus, SPX, Sovereign.
- **Collision audit result**: Only **Foundry** and **SPX** are lexically clean inside the project. **Forge** (❌ CORE-20 "Sovereign Forge"), **Nexus** (❌ HUB-17 + HUB-21), and **Sovereign** (❌ universal Hub-tier prefix + project namespace) all collide with existing canonical component names.
- **Placement divergence**: V5 added SuperPHP to the agenda; V6 said "Handle SuperPHP separately" (removed it); V7 re-included it as a 6th section. V8 (this rendition) resolves the divergence by extracting to a standalone file — satisfying V6's "handle separately" instruction while preserving V7's research content. See Appendix D for the full placement-divergence history.

**Authorization state**: No rename is approved. ADR-005 is not amended. New OD entry (candidate: OD-13) to be opened when the research matures into a decision-fork.

---

## Governance of this agenda

- **Adding a new track** requires SAAI or tech-lead approval. A new track must follow the section structure above (research questions, working hypotheses, deliverables, related doctrines, out-of-scope, status line).
- **Promoting a track to ADR** requires that the track's deliverables be produced, reviewed, and cited in a new or amended ADR. The promotion must update the track's status line to *Adopted — see ADR-XXX* and must NOT delete the track from this agenda (research provenance is preserved).
- **Closing a track** requires SAAI or tech-lead approval and a closing note explaining why the research is no longer pursued. The track is retained in the agenda with a *Closed — reason* status line.
- **No silent merge with `OPEN-DECISIONS.md`.** A track's research question may mature into a fork recorded in `OPEN-DECISIONS.md`; when it does, the OD entry cites this agenda and the agenda entry's status is updated. The two files remain distinct.
- **No silent merge with `Verification/SHORTCOMINGS-REGISTER.md`.** A research track is not a shortcoming; a shortcoming is not a research track.

---

## Appendix A — V1–V7 Reconciliation Matrix

**Purpose.** This appendix reconstructs the seven renditions of the research agenda (V1–V7) that existed in conversation prior to this file's repository creation. Per Kilo's analysis (2026-10-20) and A.ai Compact Sync #4 §5.5, the handoff requirements explicitly call for a reconciliation matrix showing what changed and why across renditions, so any reader can see the agenda's evolution rather than treating any single rendition as canonical.

**Source.** Reconstructed from A.ai Compact Sync #4 §5.5 (which cites line numbers in the original ChatGPT session transcript). Z.ai does not have direct access to `AGENTS.md`, `DGLab-FRESH-SESSION-HANDOFF.md`, or `SAAI-to-A.ai-DGLab-Briefing.md` referenced by Kilo; the line-number citations below are as reported by A.ai and not independently verified by Z.ai.

**Authorization note.** The matrix below documents what was written in chat, NOT what was authorized. No rendition was authorized for repository publication until PR #365 (owner authorization 2026-10-20: "push latest to PR so i can review"). V8 (this rendition) is the first to address all gaps identified by A.ai §5.5 and Kilo's analysis.

### A.1 Rendition-by-rendition evolution

| Version | Location (per A.ai §5.5) | Tracks | Key change from prior version |
|---|---|---|---|
| **V1** | lines 28847–28937 | 3 | Initial: Platform Integrity + Copyright + Half Exceptions. Raises Half Exceptions as unknown term; asks owner where the term came from. |
| **V2** | lines 28943–28976 | 3 | Half Exceptions only — adds "brother context" (term originated from owner's brother) and a "design principle" hypothesis. Adds explicit caveat. |
| **V3** | lines 29011–29075 | 3 | Owner answers "Both" → two interpretations of Half Exceptions formalized (partial exceptions + partially negotiable preferences). |
| **V4** | lines 29081–29130 | **4** | **Cancel-Anytime added** (quote supplied by owner: *"Cancel anytime is the most repeated promise in the history of commerce. It is also the one most rarely acted upon."*). |
| **V5** | lines 29136–29297 | 4 (+SuperPHP) | SAAI→Z.ai sync brief — formalizes as 7 numbered sections. Adds "Record this statement verbatim" instruction. Adds SuperPHP naming as a track. |
| **V6** | lines 29298–29586 | **4 + 1** | **"Handle SuperPHP separately"** — SuperPHP leaves the research agenda. Adds "Required outcome" column. Adds binding rule: every track marked "Research — Not an Implementation Requirement." Adds BLIND-SPOT-DOCTRINE.md as anchor (rather than duplicating it). |
| **V7** | lines 31829–31940 | **5** | Final agenda draft written for `RESEARCH-AGENDA.md` (commit `581945d`). Five prose tracks. Re-includes SuperPHP as a 6th section (diverges from V6's "handle separately" instruction). |

### A.2 Track count evolution

| Version | Platform Integrity | YouTube Model | Half Exceptions | Cancel-Anytime | Connecting Principles | SuperPHP |
|---|---|---|---|---|---|---|
| V1 | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| V2 | (implicit) | (implicit) | ✓ | ✗ | ✗ | ✗ |
| V3 | (implicit) | (implicit) | ✓ | ✗ | ✗ | ✗ |
| V4 | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| V5 | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ (in-agenda) |
| V6 | ✓ | ✓ | ✓ | ✓ | ✓ (connecting-theme track) | ✗ (removed — "handle separately") |
| V7 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (re-included — diverges from V6) |
| **V8 (this rendition)** | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ (extracted to standalone `RESEARCH-SuperPHP-NAMING.md`) |

### A.3 What's confirmed by owner text vs. agent interpretation vs. unresolved

| Element | Source | Status |
|---|---|---|
| Cancel-Anytime verbatim quote ("Cancel anytime is the most repeated promise...") | Owner-supplied in V4 | **Confirmed by owner text** |
| "Half Exceptions" term originated from owner's brother | Owner-supplied in V2 | **Confirmed by owner text** |
| Owner answered "Both" (re: Half Exceptions interpretations) | Owner-supplied in V3 | **Confirmed by owner text** |
| Five SuperPHP candidate names (Forge, Foundry, Nexus, SPX, Sovereign) | SAAI's directive (V5) | **SAAI interpretation of owner intent** — not directly owner-quoted |
| "Forge = preferred initial candidate" | SAAI's V5 brief | **SAAI interpretation** — later reversed by Z.ai's collision audit (V6/V7) |
| "Handle SuperPHP separately" (V6) | SAAI's V6 brief | **SAAI directive** — owner did not explicitly say this; SAAI's interpretation of owner intent |
| Connecting-theme Variant 3 attribution to owner | V7 track 5 | **UNRESOLVED** — Kilo's analysis warns: "Do not attribute a wording to the owner unless the original record verifies that attribution." Z.ai could not independently verify this attribution from the conversation history available; it may have been SAAI's interpretation of owner intent rather than direct owner text. See Appendix B for the three variants side-by-side. |
| BLIND-SPOT-DOCTRINE.md principle as anchor | V6 | **SAAI directive** — Z.ai verified the doctrine does NOT contain SAAI's hypothesis verbatim (corrected in V7/V8: cited as related, not as source) |

### A.4 Items requiring owner review before V8 can be considered canonical

1. **Connecting-theme variant selection** — owner must confirm which of the three variants (Appendix B) represents their actual intent. Until confirmed, the agenda uses Variant 1 as primary with Variants 2 & 3 documented side-by-side.
2. **Half Exceptions formulation** — owner must confirm which of the four formulations (Appendix C) is canonical. Until confirmed, the agenda tracks all four.
3. **SuperPHP placement** — owner must confirm V8's extraction (standalone file) is acceptable, OR request re-inclusion in the agenda proper (reverting to V7's structure), OR confirm V6's "handle separately" instruction (V8's approach).
4. **V8 adoption** — owner must explicitly adopt V8 (or request a V9 that resolves remaining items) before this file is merged from PR #365 to `main`.

---

## Appendix B — Three Connecting-Theme Variants Side-by-Side

**Purpose.** Per Kilo's analysis: "Present them side-by-side, explain differences, and propose a reconciliation for owner review." V7 contained only Variant 1; Variants 2 and 3 were absent. This appendix restores all three for owner review.

**Source.** Variants 1 and 2 are quoted from A.ai Compact Sync #4 §5.5 (which cites V5 and V6 of the original ChatGPT session). Variant 3 is quoted from V7 track 5 (the file you are reading). All three are presented verbatim; commentary is separate from the quoted text.

### B.1 The three variants

| # | Variant (verbatim) | Source | Type |
|---|---|---|---|
| **1** | "How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?" | SAAI Briefing §7 (V5) → V7 Track 5 primary question | **Substantive** — asks a design question |
| **2** | "Connecting architectural principles — identify shared concepts and potential implications for DGLab without prematurely creating abstractions." | V6 table (per A.ai §5.5) | **Procedural** — describes a process (identify, don't create) |
| **3** | "The difference between a right that exists on paper and a right that a person can exercise in practice." | V7 Track 5 (attributed to owner) | **Substantive** — names a distinction |

### B.2 What's different

- **Variant 1** asks *how to design* — a forward-looking design question.
- **Variant 2** describes *how to investigate* — a process instruction (identify, don't prematurely create).
- **Variant 3** names *what the distinction is* — a definition/diagnosis of the problem.

Variants 1 and 3 are both substantive but ask different questions: V1 asks how to design; V3 names the gap the design must close. Variant 2 is procedural and tells investigators how to approach the analysis.

### B.3 Attribution caveat

Kilo's analysis warns: *"Do not attribute a wording to the owner unless the original record verifies that attribution."* Variant 3 was attributed to the owner in V7, but Z.ai could not independently verify this attribution from the conversation history available. It may have been SAAI's interpretation of owner intent rather than direct owner text. The owner should confirm or correct the attribution before V8 is adopted as canonical.

### B.4 Proposed reconciliation

Three options for the owner:

1. **Adopt Variant 1 as canonical** — V7's current primary question. Substantive, design-oriented, broadly applicable across Tracks 1–4.
2. **Adopt Variant 3 as canonical** — more specific, names the gap directly. Requires owner to confirm attribution.
3. **Keep all three** — V8's current approach. Variant 1 as Track 5's primary question; Variant 2 as Track 5's procedural instruction; Variant 3 as a documented framing. Avoids forcing a choice before owner review.

**V8 default (this rendition):** Option 3 — keep all three with side-by-side documentation. The owner can collapse to a single variant after review.

---

## Appendix C — Four Half Exceptions Formulations

**Purpose.** Per Kilo's analysis: V7 covered "two meanings" (partial exceptions + partially negotiable preferences) but did not explicitly enumerate the four formulations that appeared across V1–V7. This appendix restores all four for owner review.

**Source.** All four formulations are quoted from A.ai Compact Sync #4 §5.5 (which cites V1, V3, V6, V7 of the original ChatGPT session).

### C.1 The four formulations

| # | Formulation (verbatim) | Source | Status |
|---|---|---|---|
| **1** | "I would not assign the term a technical meaning yet." | V1 | **Agnostic** — refuses to define until evidence accumulates |
| **2** | Two interpretations: (a) partial exceptions to general preferences; (b) partially negotiable preferences. | V3 (owner answered "Both" to V1's question) | **Bifurcated** — owner explicitly endorsed both meanings |
| **3** | "both partial exceptions to general preferences and partially negotiable preferences, with possible software-design analogies." | V6 | **Synthesized** — combines both meanings + adds software analogies as a research direction |
| **4** | "Treat this as a user-originated hypothesis, not an established computer-science term." | V7 | **Discipline-flagged** — explicitly warns against assuming SE-term status |

### C.2 What's different

- **V1** refuses to define — cautious, evidence-first.
- **V3** accepts both meanings (per owner's "Both" answer) but doesn't synthesize them.
- **V6** synthesizes both meanings AND adds software-design analogies as a research direction (config defaults, authz policies, feature flags, fault tolerance).
- **V7** keeps V6's synthesis but adds an explicit discipline flag (do not assume it's an established SE term).

### C.3 Reconciliation

V8 (Track 3 of this file) adopts V7's formulation — preserves the discipline flag AND keeps V6's synthesis AND documents both meanings per V3's owner-confirmed "Both" answer. The discipline flag from V7 is preserved verbatim in Track 3's opening: *"SAAI's warning that 'Half Exceptions' is not an established software-engineering term is preserved verbatim in Track 3's opening."*

**No owner decision required** to keep this formulation; V8's Track 3 satisfies all four formulations by preserving the discipline flag, both meanings, and the software-analogy research direction. The owner may collapse to a single formulation after review if desired.

---

## Appendix D — SuperPHP Placement Divergence (V5 → V6 → V7 → V8)

**Purpose.** Per Kilo's analysis: V7 documented the SuperPHP placement divergence but did not present it as a reconciliation matrix. This appendix restores the placement history side-by-side and documents V8's resolution.

### D.1 Placement history

| Version | SuperPHP placement | Source / instruction |
|---|---|---|
| **V1–V4** | Not present | SuperPHP naming not yet in scope |
| **V5** (lines 29136–29297) | Added to agenda as a section | SAAI→Z.ai sync brief — SuperPHP enters as a research track |
| **V6** (lines 29298–29586) | **Removed from agenda** — "Handle SuperPHP separately" | SuperPHP leaves the research agenda; explicit instruction that SuperPHP is not part of the agenda proper |
| **V7** (lines 31829–31940) | **Re-included as a 6th section** | V7 draft — diverges from V6's "handle separately" instruction. Z.ai did not flag this divergence to SAAI before writing V7. |
| **V8 (this rendition)** | **Extracted to standalone file** `Architecture/CrossCutting/RESEARCH-SuperPHP-NAMING.md` | Resolves the divergence: satisfies V6's "handle separately" instruction (SuperPHP not in agenda proper) while preserving V7's research content (full collision audit + research questions + deliverables). Agenda proper cross-references the standalone file. |

### D.2 Why V8 extracts rather than re-includes

- **V6's "handle separately" instruction** is explicit and was not overturned by owner authorization between V6 and V7.
- **V7's re-inclusion** appears to have been an unauthorized divergence — Z.ai should have flagged to SAAI that V6 said "handle separately" before including SuperPHP in V7. Kilo's analysis correctly identifies this as a divergence to document, not silently enshrine.
- **V8's extraction** is the cleanest resolution: SuperPHP naming is real research worth recording, but it does not belong in the agenda proper per V6. A standalone file lets the research be referenced, reviewed, and resolved independently without conflating it with the five product-research tracks.
- **The owner can override** — if the owner prefers V7's structure (SuperPHP as a 6th section in the agenda proper), V9 can re-include it. V8's choice is reversible.

### D.3 Cross-reference

- Standalone file: [`RESEARCH-SuperPHP-NAMING.md`](./RESEARCH-SuperPHP-NAMING.md) — contains the full collision audit, research questions, deliverables, and the placement-divergence history from this appendix.
- Agenda proper reference: see "SuperPHP Naming Investigation — Extracted to Standalone Report" section above.
- Authorization state: no rename approved; ADR-005 not amended; new OD entry (candidate: OD-13) to be opened when research matures into a decision-fork.

---

## Appendix E — Authorization State and Source Provenance

**Purpose.** Kilo's analysis references `AGENTS.md §5.7` which (per Kilo) forbids creating `Architecture/CrossCutting/RESEARCH-AGENDA.md` without explicit owner authorization. This appendix documents the authorization chain for V8.

### E.1 Authorization chain

| Step | Authorization | Evidence |
|---|---|---|
| V1–V7 drafts in chat | Not authorized for repository publication — research stayed in chat | A.ai Compact Sync #4 §5.5: "Every rendition exists only in chat. None is in the repository." |
| V7 commit `581945d` (local) | SAAI's 2026-10-13 directive authorized Z.ai to create the file locally | Documented in commit message of `581945d` |
| V7 → PR #365 (owner-pushed for review) | Owner explicit authorization 2026-10-20: "I can't see it so push latest to PR so i can review" | User message in IM session web-43583218 |
| V8 (this rendition) | Owner-authorized as a follow-up commit on PR #365 addressing Kilo's analysis gaps | Kilo's analysis was relayed by owner to Z.ai; Z.ai producing V8 in response is implicit authorization |

### E.2 Source provenance for V8's content

| Content | Source |
|---|---|
| Tracks 1–5 structure | V7 (preserved from commit `581945d`) |
| Cancel-Anytime verbatim quote | V4 (owner-supplied) |
| Half Exceptions discipline flag | V7 (SAAI directive) |
| Half Exceptions both-meanings synthesis | V6 (SAAI directive) |
| SuperPHP collision audit | V7 (Z.ai's 2026-10-13 audit against `INDEX.md` §1 + §2.3) |
| V1–V7 reconciliation matrix (Appendix A) | Reconstructed from A.ai Compact Sync #4 §5.5 |
| Three connecting-theme variants (Appendix B) | Reconstructed from A.ai Compact Sync #4 §5.5 |
| Four Half Exceptions formulations (Appendix C) | Reconstructed from A.ai Compact Sync #4 §5.5 |
| SuperPHP placement divergence (Appendix D) | Reconstructed from A.ai Compact Sync #4 §5.5 + Z.ai's own commit history |
| Authorization chain (Appendix E) | Reconstructed from user messages in IM session + commit history |

### E.3 Documents Z.ai could NOT directly verify

The following documents referenced by Kilo's analysis are NOT in Z.ai's environment (they exist in the owner's `/home/dgci/www/DGLab/` clone):

- `AGENTS.md` (referenced as §5.7)
- `DGLab-FRESH-SESSION-HANDOFF.md`
- `SAAI-to-A.ai-DGLab-Briefing.md`

V8's reconstruction of the V1–V7 history is based on A.ai Compact Sync #4 §5.5 (which cites line numbers in the original ChatGPT session transcript). Z.ai cannot independently verify the line-number citations or the contents of the handoff documents. The owner should cross-check V8's appendices against the actual handoff documents before adopting V8 as canonical.

### E.4 What V8 does NOT claim

- V8 does NOT claim to be canonical. It is a draft for owner review.
- V8 does NOT claim the V1–V7 reconstruction is complete or accurate. It is best-effort based on A.ai's reporting.
- V8 does NOT resolve the connecting-theme variant question — it documents all three side-by-side for owner decision.
- V8 does NOT resolve the Half Exceptions formulation question — it preserves all four.
- V8 does NOT authorize any rename of SuperPHP. The standalone report explicitly states "No rename is approved."
