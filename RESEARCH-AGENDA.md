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

**Four formulations to investigate (per Prompt 1, FRESH-SESSION-PROMPTS.md):**

| # | Formulation | Origin | Status |
|---|-------------|--------|--------|
| 1 | "Partial exceptions to general preferences/rules" — a rule is in force except under bounded conditions | Owner-originated (V1) | Working hypothesis |
| 2 | "Partially negotiable preferences" — the question of whether a preference is in force is itself negotiable | Owner-originated (V1) | Working hypothesis |
| 3 | "Half of a general exception" — an exception that applies to only part of a rule's scope (e.g., exception for 50% of users) | Agent interpretation (Z.ai) | Agent interpretation — not owner-confirmed |
| 4 | "Exception that is half-committed" — an exception that is provisionally granted and subject to full ratification | Agent interpretation (Kilo) | Agent interpretation — not owner-confirmed |

**Owner Decision Required:** Confirm whether formulations 3 and 4 are valid interpretations or whether only 1 and 2 represent the owner's intent.

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

The four tracks above share a common question. **Three variants of this question have been reported** (per AGENTS.md §79–82, Handoff §110–115). They are **not interchangeable** and must be presented side-by-side:

**Variant A (owner-attributed — SAAI briefing §862–865):**
> "The difference between a right that exists on paper and a right that a person can exercise in practice."

**Variant B (agent-reported — AGENTS.md §79, Handoff §111):**
> "How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?"

**Variant C (agent-reported — AGENTS.md §81, Handoff §112):**
> "A procedural formulation about identifying shared concepts and potential DGLab implications without prematurely creating abstractions."

| Dimension | Variant A | Variant B | Variant C |
|-----------|-----------|-----------|-----------|
| **Type** | Substantive thesis (diagnosis) | Research question (design) | Research method (investigation) |
| **Attributed to owner?** | Yes (SAAI briefing) | No (agent-reported) | No (agent-reported) |
| **Focus** | Gap between formal rights and practice | Fair exercise of rights in design | How to investigate without premature abstraction |
| **Risk if used alone** | Doesn't guide methodology | May conflate authority and cost | Doesn't pose a substantive question |

**Reconciliation recommendation (for owner review — NOT a decision):** Use Variant B as the primary research question, Variant A as the substantive thesis that motivates it, and Variant C as the method statement. This preserves all three without conflating their distinct roles.

**Owner Decision Required:** Which variant (or combination) to use as the canonical connecting-theme question. Do not attribute a wording to the owner unless the original record verifies that attribution.

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

## SuperPHP Naming Investigation

**Status:** Research — Not an Implementation Requirement. **No rename is approved.**

### Background

NeoSPX is currently the planned template-language capability (formerly SuperPHP) encompassing `CORE-07` (Lexer), `CORE-11` (Parser), and `CORE-12` (Compiler), per `Architecture/INDEX.md` §1 (lines 109, 113, 114 — all three 📝 Not started). It is referenced in `ADR-005-superphp-vs-blade-twig.md` (historical: ratified SuperPHP over Blade/Twig; identity superseded by NeoSPX pending ADR-023) and in `OPEN-DECISIONS.md` §OD-09 (DGLab SCSS framework authoring model).

SAAI's directive (2026-10-13) records five candidate replacement names: **Forge, Foundry, Nexus, SPX, Sovereign**. The directive is explicit: *"No rename is approved. Preserve the current name until its responsibilities and architectural boundaries are documented and the proposed alternatives have been evaluated for collisions."*

### Why a separate section instead of an OD entry

SAAI's directive required that, before editing `OPEN-DECISIONS.md`, the exact scope and title of OD-09 be inspected and confirmed as the correct home for the naming question. OD-09 is titled *"DGLab SCSS framework: scope, inspirations, SuperPHP authoring model"* and is scoped to the **SCSS framework's authoring model** (Tailwind vs. custom, four-layer model, `<dg:style>` block support in CORE-12). SuperPHP appears in OD-09 only as the *authoring layer for SCSS components* — not as a naming decision. **OD-09 is therefore not the correct home for the naming question.** Recording the candidates in this research agenda (rather than creating a new OD entry) preserves the distinction: the naming question is still in research, not yet a decision-fork awaiting resolution. When the research matures into a fork, a new OD entry (candidate: OD-13) should be opened and this section should reference it.

### Candidate name collision audit (performed 2026-10-13 against `Architecture/INDEX.md` §1 and §2.3, `Architecture/CrossCutting/*.md`, and `Architecture/ADRs/*.md`)

| Candidate | Collision status | Evidence |
|---|---|---|
| **Forge** | ❌ **COLLISION** | `CORE-20` is canonically named *"Sovereign Forge"* (Developer CLI Toolchain), namespace `SovereignStack\Forge`, per `INDEX.md` §1 line 122. Also referenced in `CrossCutting/DGLAB-AS-OS.md` line 244 as `bin/forge` and line 255 as "gcc + make" build toolchain equivalent. The historical collision was resolved 2026-08-12 in `OPEN-DECISIONS.md` §OD-05 by assigning `Forge` to CORE-20 (the other three contenders — ISPOKE-02, ISPOKE-11, ESPOKE-12 — were renamed to A1 Atlas, B1 Penumbra, C1 Pulsar). Reusing `Forge` for SuperPHP would re-open this resolved collision and create a 4-way ambiguity with the developer CLI toolchain. |
| **Foundry** | ✅ **No collisions found.** No matches for `\bFoundry\b` across `Architecture/` or `docs/`. Lexically clean. Caveat: not yet audited against PHP-ecosystem package names on Packagist; this is a future research item if Foundry becomes a serious candidate. |
| **Nexus** | ❌ **COLLISION (2-way)** | `HUB-17` is *"Sovereign Webhook Nexus"* and `HUB-21` is *"Sovereign Nexus (Tenancy)"* per `INDEX.md` §2.3 lines 146, 149. Two existing Hub-tier components already use `Nexus` as their noun. Reusing it for the template engine would create cross-tier ambiguity. |
| **SPX** | ✅ **No internal collisions found.** No matches for `\bSPX\b` across `Architecture/` or `docs/`. Caveat: `SPX` is also an acronym used in the broader PHP ecosystem (Simple PHp Aggregate eXtension — a profiling extension), which is a *lexical* rather than *architectural* collision risk but worth noting if SPX becomes a serious candidate. |
| **Sovereign** | ❌ **MASSIVE COLLISION (Hub-tier naming prefix).** Every Hub-tier component is prefixed with `Sovereign` — `Sovereign Identity` (HUB-04), `Sovereign Cache` (HUB-02), `Sovereign Auditor` (HUB-06), `Sovereign Ledger` (HUB-22), `Sovereign Nexus` (HUB-21), `Sovereign Forge` (CORE-20), and 26+ more. The word `Sovereign` is also the project's own naming-convention prefix (per `INDEX.md` §1, §2.3, and the `SovereignStack\` namespace). Renaming SuperPHP to `Sovereign` would create total namespace confusion with the Hub tier. |

### Working hypothesis

Of the five candidates SAAI recorded, only **Foundry** and **SPX** are lexically clean inside the project. **Forge**, **Nexus**, and **Sovereign** all collide with existing canonical component names. This audit does not recommend either Foundry or SPX — it only establishes that the other three carry collision risk that should rule them out unless SAAI explicitly accepts the collision and the cost of resolving it (renaming the colliding component or accepting the ambiguity).

### Research questions for the naming investigation

- What are SuperPHP's *actual* responsibilities and architectural boundaries? Until these are documented, a rename is premature — naming should follow function, not lead it.
- Are there external collisions (Packagist, npm, GitHub org names, PHP-FIG proposals) for any of the candidates?
- What is the cost of renaming the 100+ references to "SuperPHP" across `Architecture/`, `ADR-005`, `OPEN-DECISIONS.md` §OD-09, and the future-implementation blueprints `CORE-07`, `CORE-11`, `CORE-12`?
- Should the template engine be named for its *function* (template, view, render) or for its *character* (forge, foundry, etc.)? This is a naming-philosophy question, not a collision question.

### Deliverables

- A **responsibilities and boundaries document** for SuperPHP, covering Lexer / Parser / Compiler responsibilities and the seams between them.
- An **external collision audit** for Foundry and SPX against Packagist, npm, and the broader PHP ecosystem.
- A **rename-cost estimate** for the existing `Architecture/` references.
- A **naming-philosophy note** distinguishing function-names from character-names, with SAAI's directive as input.

### Related doctrines and contracts

- `Architecture/ADRs/ADR-005-superphp-vs-blade-twig.md` — the existing ADR that ratified SuperPHP over Blade/Twig. Any rename requires amending this ADR.
- `Architecture/OPEN-DECISIONS.md` §OD-09 — the SCSS-framework authoring-model OD that mentions SuperPHP. Verified 2026-10-13: OD-09 is scoped to SCSS framework, not to naming.
- `Architecture/INDEX.md` §1, §2.3 — the canonical component-name registry and the collision target for any candidate.
- `Architecture/CrossCutting/DGLAB-AS-OS.md` — uses `bin/forge` and "Sovereign Forge" terminology; relevant to the Forge collision.
- `Architecture/CrossCutting/GLOSSARY.md` — defines "Sovereign Forge" as the codename for CORE-20.

### Out of scope

- Any rename of SuperPHP. **Not authorized.**
- Any amendment to ADR-005. **Not authorized.**
- Any new blueprint, namespace, or package rename. **Not authorized.**

---

## Governance of this agenda

- **Adding a new track** requires SAAI or tech-lead approval. A new track must follow the section structure above (research questions, working hypotheses, deliverables, related doctrines, out-of-scope, status line).
- **Promoting a track to ADR** requires that the track's deliverables be produced, reviewed, and cited in a new or amended ADR. The promotion must update the track's status line to *Adopted — see ADR-XXX* and must NOT delete the track from this agenda (research provenance is preserved).
- **Closing a track** requires SAAI or tech-lead approval and a closing note explaining why the research is no longer pursued. The track is retained in the agenda with a *Closed — reason* status line.
- **No silent merge with `OPEN-DECISIONS.md`.** A track's research question may mature into a fork recorded in `OPEN-DECISIONS.md`; when it does, the OD entry cites this agenda and the agenda entry's status is updated. The two files remain distinct.
- **No silent merge with `Verification/SHORTCOMINGS-REGISTER.md`.** A research track is not a shortcoming; a shortcoming is not a research track.
