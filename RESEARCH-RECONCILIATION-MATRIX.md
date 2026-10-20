# V1–V7 Reconciliation Matrix — Research Agenda Renditions

**Created:** 2026-10-20  
**Status:** Research deliverable — NOT a governance record. No authorization to publish as `Architecture/CrossCutting/RESEARCH-AGENDA.md`  
**Sources:** `AGENTS.md` §5, `DGLab-FRESH-SESSION-HANDOFF.md` §3, `FRESH-SESSION-PROMPTS.md`, `SAAI-to-A.ai-DGLab-Briefing.md` §8–§12, `RESEARCH-AGENDA.md` (root), web research results

---

## 1. Rendition Inventory

| Rendition | Origin | Key Characteristics | Status |
|-----------|--------|---------------------|--------|
| **V1** | SAAI briefing §8–§10 (original owner directive) | "How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?" as connecting theme. Four product tracks: platform integrity, YouTube model, Half Exceptions, Cancel-Anytime. SuperPHP "handle separately." | **Owner text — confirmed** |
| **V2** | Z.ai agent report (pre-A3 audit) | Added SPX as a sixth section (not separate). Introduced "partial exceptions" and "partially negotiable preferences" for Half Exceptions. | **Agent interpretation — not owner-confirmed** |
| **V3** | Kilo Code independent review | Verified naming collisions. Questioned whether V1's connecting theme attribution is verbatim owner text. | **Agent interpretation — verified partially** |
| **V4** | Integration pass (Prompt 3) | Combined Z.ai + Kilo reports. Flattened disagreement on connecting theme. | **Agent interpretation — disagreement flattened (flagged)** |
| **V5** | Local draft (commit `581945d` / patch 0002) | Added reconciliation table for V1–V7. Added source/uncertainty register. | **Local-only — not in repo; applicability unverified** |
| **V6** | `DGLab-FRESH-SESSION-HANDOFF.md` §3 | Codified research package requirements. Added Gate structure. Added epistemic labels (V/R/P/O). | **Handoff document — authoritative for process** |
| **V7** | `RESEARCH-AGENDA.md` (root, 2026-10-13) | Canonical agenda draft. 5 tracks + SuperPHP section. Only one connecting-theme variant. Detailed hypotheses, deliverables, related doctrines. | **Most recent — in repo root** |

---

## 2. Connecting-Theme — Three Variants (Side-by-Side)

### Variant A (V1 / SAAI briefing §862–865)

> **"The difference between a right that exists on paper and a right that a person can exercise in practice."**

| Dimension | Assessment |
|-----------|------------|
| **Type** | Substantive thesis — a claim about the gap between formal rights and practical exercise |
| **Origin** | Owner-supplied (SAAI briefing §862: "The owner identified a unifying question") |
| **Verification** | **Confirmed by owner text** — SAAI briefing attributes it to "the owner" |
| **Strength** | Concrete, memorable, captures the practical-experience dimension |
| **Limitation** | Does not specify *how* to design; it's a diagnosis, not a method |

### Variant B (V6 / Handoff §110–115, AGENTS.md §79)

> **"How do we design systems in which stated rights, rules, and principles can be exercised fairly in practice?"**

| Dimension | Assessment |
|-----------|------------|
| **Type** | Research question — asks how to design for fair exercise |
| **Origin** | AGENTS.md §79 (reported as "three variants were reported") |
| **Verification** | **Agent-reported, not directly attributed to owner** — AGENTS.md says "reported" |
| **Strength** | Action-oriented, asks for design guidance |
| **Limitation** | Assumes "fairness" is the right framing; may collapse authority (Track 1) and cost (Track 4) into one concept |

### Variant C (V6 / Handoff §112, AGENTS.md §81)

> **"A procedural formulation about identifying shared concepts and potential DGLab implications without prematurely creating abstractions."**

| Dimension | Assessment |
|-----------|------------|
| **Type** | Research method — specifies how to conduct the connecting-theme investigation |
| **Origin** | AGENTS.md §81 (reported as "a procedural formulation") |
| **Verification** | **Agent-reported, not directly attributed to owner** — AGENTS.md says "reported" |
| **Strength** | Methodologically cautious; prevents premature abstraction |
| **Limitation** | Not a research question at all — it's a method statement, not a substantive inquiry |

### Key Differences

| Dimension | Variant A | Variant B | Variant C |
|-----------|-----------|-----------|-----------|
| **Is a question?** | No — it's a thesis/diagnosis | Yes — asks how to design | No — it's a method |
| **Attributed to owner?** | Yes (SAAI briefing) | No (agent-reported) | No (agent-reported) |
| **Focus** | Gap between paper rights and practice | Fair exercise of rights in design | How to investigate without premature abstraction |
| **Risk if used alone** | Doesn't guide design methodology | May conflate authority and cost | Doesn't pose a substantive question |

### Reconciliation Recommendation (For Owner Review — NOT a Decision)

**Option 1 (Recommended):** Use Variant B as the primary research question, Variant A as the substantive thesis that motivates it, and Variant C as the method statement for how to conduct the investigation. This preserves all three without conflating their distinct roles.

**Option 2:** Use Variant A as the primary question (owner-attributed), with Variant C as method. Drop Variant B as redundant with A's practical focus.

**Option 3:** Present all three to the owner and let them choose. This is the safest option given the epistemic uncertainty about attribution.

**Owner Decision Required:** Which variant (or combination) to use as the canonical connecting-theme question.

---

## 3. SuperPHP Placement Divergence

| Rendition | SuperPHP Placement | Evidence |
|-----------|-------------------|----------|
| **V1 (SAAI briefing)** | "Handle SuperPHP separately" — explicitly not a sixth track | SAAI briefing §8: "This includes SPX/SuperPHP and the four product-research tracks" — implies SPX is separate from the 4 |
| **V2 (Z.ai agent)** | Added as sixth section (Track 6) | Handoff §3.1: "A previous local draft included SPX as a sixth section" |
| **V7 (RESEARCH-AGENDA.md)** | Separate section after Track 5, labeled "SuperPHP Naming Investigation" | RESEARCH-AGENDA.md lines 240–295 |

**Resolution in V7:** The agenda treats SuperPHP as a **separate section** (not a numbered track), with its own status, background, collision audit, research questions, deliverables, and out-of-scope. This is a reasonable middle ground but the divergence is not explicitly reconciled.

**Recommendation:** Keep SuperPHP as a separate section (not a numbered track) but add a note explaining why it's not Track 6. The section should be linked from the product-research tracks where relevant (e.g., Track 5's "potential architectural implications" may include SuperPHP).

---

## 4. Half Exceptions — Four Formulations

The handoff (Prompt 1) requires enumerating **four** formulations. The agenda (Track 3) covers two meanings. The four formulations are:

| # | Formulation | Origin | Status |
|---|-------------|--------|--------|
| **1** | "Partial exceptions to general preferences/rules" — a rule is in force except under bounded conditions | V1 (owner-originated) | **Working hypothesis** |
| **2** | "Partially negotiable preferences" — the question of whether a preference is in force is itself negotiable | V1 (owner-originated) | **Working hypothesis** |
| **3** | "Half of a general exception" — an exception that applies to only part of a rule's scope (e.g., exception for 50% of users) | Agent interpretation (Z.ai) | **Agent interpretation — not owner-confirmed** |
| **4** | "Exception that is half-committed" — an exception that is provisionally granted and subject to full ratification | Agent interpretation (Kilo) | **Agent interpretation — not owner-confirmed** |

**Key distinction (from agenda Track 3):** Formulations 1 and 2 are structurally different:
- Formulation 1 is a **rule-level** construct (the rule holds, except when authorized otherwise)
- Formulation 2 is a **meta-level** construct (whether the preference applies is itself negotiable)

Conflating them produces category errors. Formulations 3 and 4 are agent interpretations that may or may not map to the owner's intent.

**Owner Decision Required:** Confirm whether formulations 3 and 4 are valid interpretations or whether only 1 and 2 represent the owner's intent.

---

## 5. SuperPHP Naming Collision Audit (Standalone Finding)

### Internal Collision Status (Verified 2026-10-20 against `Architecture/INDEX.md`)

| Candidate | Status | Evidence |
|-----------|--------|----------|
| **Forge** | ❌ **COLLISION** | `CORE-20` = "Sovereign Forge" (Developer CLI Toolchain), namespace `SovereignStack\Forge` (INDEX.md §1 line 122). Also `bin/forge` in `DGLAB-AS-OS.md`. Resolved 2026-08-12 via OD-05 (other three contenders renamed to Atlas/Penumbra/Pulsar). |
| **Foundry** | ✅ **No internal collisions** | No matches for `\bFoundry\b` in `Architecture/` or `docs/`. **External collision found:** `coderstm/foundry` on Packagist (2026-09-02). |
| **Nexus** | ❌ **COLLISION (2-way)** | `HUB-17` = "Sovereign Webhook Nexus", `HUB-21` = "Sovereign Nexus (Tenancy)" (INDEX.md §2.3 lines 146, 149). |
| **SPX** | ✅ **No internal collisions** | No matches for `\bSPX\b` in `Architecture/` or `docs/`. **External collision found:** `noisebynorthwest/php-spx` on Packagist (PHP profiling extension with web UI). |
| **Sovereign** | ❌ **MASSIVE COLLISION** | Hub-tier naming prefix (26+ components). Project namespace `SovereignStack\`. Total namespace confusion risk. |

### External Collision Audit (Web Research 2026-10-20)

| Candidate | External Collision | Source | Risk Level |
|-----------|-------------------|--------|------------|
| **Foundry** | `coderstm/foundry` (SaaS application framework, Laravel-based) | Packagist (published 2026-09-02) | **Medium** — different domain (SaaS vs template engine), but vendor namespace `coderstm` is distinct from `SovereignStack` |
| **SPX** | `noisebynorthwest/php-spx` (PHP profiling extension with web UI) | Packagist (published 2026-08-01) | **Medium** — same PHP ecosystem, but different purpose (profiling vs template engine). Name collision could cause confusion in `composer require` |
| **Forge** | `laravel/forge` (server management), `forge-php/forge` (code generation) | Packagist | **High** — already internal collision; external collisions reinforce rejection |
| **Nexus** | `nexusphp/*` (PHP utility library) | Packagist | **High** — already internal collision |
| **Sovereign** | `sovereignstack/*` (hypothetical) | N/A | **Low** — no external collision, but internal collision is disqualifying |

### Recommendation (For Owner Review — NOT a Decision)

- **Forge, Nexus, Sovereign:** Disqualified by internal collisions. Do not reuse without explicit owner acceptance of collision cost.
- **Foundry:** Lexically clean internally; external collision with `coderstm/foundry` is in a different domain (SaaS framework vs template engine). Vendor namespace distinction reduces risk. **Candidate if owner approves.**
- **SPX:** Lexically clean internally; external collision with `noisebynorthwest/php-spx` (profiling extension) is in same PHP ecosystem. Higher risk of confusion. **Candidate if owner approves external collision mitigation.**

**No rename is approved.** Do not amend ADR-005 or create a naming ADR without owner authorization.

---

## 6. Half Exceptions — Analogies Where They Break

| Software Analogy | Where It Holds | Where It Breaks |
|------------------|----------------|-----------------|
| **Configuration defaults with override hierarchies** | Defaults can be overridden at specific scopes; bounded exceptions similarly override rules at specific scopes | Configuration overrides are typically permanent; bounded exceptions require expiry and reviewability |
| **Authorization policies with exception grants** | Authz exceptions are scoped, authorized, and auditable — matches bounded exception lifecycle | Authz exceptions are typically binary (allow/deny); Half Exceptions may be partial/graduated |
| **Feature flags with scope, expiry, review** | Feature flags have explicit scope, expiry, and review — matches bounded exception lifecycle | Feature flags are temporary by design; bounded exceptions may be permanent overrides |
| **Fault tolerance with bounded degradation** | Graceful degradation has bounded scope and automatic recovery — matches partial exception | Degradation is automatic; bounded exceptions require explicit authorization |
| **Circuit breakers** | Circuit breakers have thresholds, timeouts, and automatic recovery — matches expiry | Circuit breakers are system-initiated; bounded exceptions are human-authorized |

**Key insight from web research:** "Half Exceptions" has **no established software engineering meaning**. The only technical usage found is in hardware/SIMD (Intel patent US 6,691,223 — partial exception processing in parallel processors). The concept must be defined from first principles, not borrowed from existing terminology.

---

## 7. Cancel-Anytime Paradox — Evidence Summary

### Empirical Evidence Found (Web Research 2026-10-20)

| Source | Finding | Relevance |
|--------|---------|-----------|
| Bryant University study (2023) | Cancel options **increase** purchase intent for long-term commitments (perceived goal achievability) | "Cancel anytime" increases enrollment, not exits — paradox confirmed |
| Business Wire consumer survey (2025) | 82% more likely to subscribe when cancellation is easy; 58% paused instead of canceling | Easy exit increases trust and loyalty |
| FTC Uber complaint (2025) | "Cancel anytime" with 12+ actions, 7 screens, 48-hour restriction, billing before promised date | "Cancel anytime" promise is frequently not acted upon in practice |
| UX dark pattern taxonomy | Obstruction pattern: making cancellation harder than signup | Easy exit increases initial adoption and trust |
| DirectPayNet (2025) | "Making it easier for customers to leave actually makes them more likely to stay" | Paradox: easy exit → more retention |
| Steve Fenton (2022) | Self-service cancel: 30% reactivation; call-to-cancel: <10% reactivation | Painful exit destroys trust and reactivation |

### Operational Definition of Exitability (Research Output)

Exitability = f(export completeness, format openness, switching effort, community migration, contractual penalty, retention policy)

**Four distinct operations** (from agenda Track 4):
1. **Cancellation** — stop billing, stop service
2. **Account deletion** — erase identity
3. **Content removal** — erase artefacts
4. **Data portability** — carry artefacts elsewhere

Conflating these produces UX failures (user wanted to cancel billing but system deleted work) and accountability failures (user wanted to erase identity but system retained content).

---

## 8. Research Agenda Rendition Reconciliation

### What Changed Between Renditions

| Rendition | Key Change | Why |
|-----------|-----------|-----|
| V1 → V2 | SPX added as sixth section | Z.ai agent interpreted "handle separately" as "include in agenda" |
| V2 → V3 | Naming collision audit added | Kilo Code independently verified collisions |
| V3 → V4 | Tracks combined; disagreement flattened | Integration pass merged reports without preserving disagreement |
| V4 → V5 | Reconciliation matrix added; epistemic labels formalized | Handoff §3.7 added these requirements |
| V5 → V6 | Gate structure added; 25-step build order | Handoff §5.4 expanded LMS build order |
| V6 → V7 | Canonical agenda draft created; 5 tracks + SuperPHP | RESEARCH-AGENDA.md created 2026-10-13 |

### What Is Confirmed by Owner Text

| Claim | Source | Verified? |
|-------|--------|-----------|
| Connecting theme Variant A wording | SAAI briefing §862–865 | ✅ Owner-attributed |
| Cancel-Anytime sentence verbatim | SAAI briefing §858 | ✅ Owner-supplied |
| Half Exceptions is not established SE term | SAAI briefing §850 | ✅ Owner-originated |
| "Enforcement is not authority" hypothesis | RESEARCH-AGENDA.md Track 1 (agent-authored) | ⚠️ Agent interpretation, not owner text |
| "Exitability is a design property" hypothesis | RESEARCH-AGENDA.md Track 4 (agent-authored) | ⚠️ Agent interpretation, not owner text |
| SuperPHP rename candidates (Forge, Foundry, Nexus, SPX, Sovereign) | SAAI briefing §334 | ✅ Owner-supplied |
| "No rename is approved" | SAAI briefing §338 | ✅ Owner directive |

### What Is Only Agent Interpretation

| Claim | Source | Status |
|-------|--------|--------|
| Connecting theme Variant B wording | AGENTS.md §79 | Agent-reported, not owner-confirmed |
| Connecting theme Variant C wording | AGENTS.md §81 | Agent-reported, not owner-confirmed |
| "Decentralization alone doesn't solve abuse" | RESEARCH-AGENDA.md Track 2 | Agent hypothesis, adopted from SAAI brief |
| Half Exceptions formulations 3 & 4 | Agent interpretation | Not owner-confirmed |
| SuperPHP placement as sixth section | V2 (Z.ai) | Agent interpretation of "handle separately" |

### What Cannot Be Resolved Without Owner Input

1. **Which connecting-theme variant** to use as canonical (A, B, C, or combination)
2. **Whether Half Exceptions formulations 3 & 4** are valid
3. **SuperPHP placement** — separate section vs. numbered track
4. **Authorization to publish** `Architecture/CrossCutting/RESEARCH-AGENDA.md`
5. **Whether research gate is passed** — owner/SAAI review of complete package

---

## 9. Source/Uncertainty Register

### Sources Used (Web Research 2026-10-20)

| # | Source | Date | Track | Strength | Limitation |
|---|--------|------|-------|----------|------------|
| 1 | MC Law platform moderation guide | 2026-02-22 | T1 | Practical operational guidance | Law firm blog, not academic |
| 2 | WIPO ADR for digital copyright | Current | T1 | Authoritative international body | Legal framework, not technical design |
| 3 | Cross-border user content management | 2026-02-15 | T1 | Legal best practices | Practitioner-oriented, not research |
| 4 | Discord/Kick/Zallity/OpenStudio copyright policies | 2025–2026 | T1 | Real-world platform examples | Platform-specific, not generalizable |
| 5 | EU Copyright Directive Art. 17 analysis | 2026-09-24 | T1 | Academic legal analysis | EU-specific, not global |
| 6 | ABA DMCA for streamers | 2026-09-17 | T1 | Legal practitioner perspective | US-specific |
| 7 | YouTube complementor governance (ISJ) | 2023-12-07 | T2 | Peer-reviewed academic | YouTube-specific, may not generalize |
| 8 | Creator governance in SME | 2019-10-01 | T2 | Academic framework | Pre-platformization era |
| 9 | User-generated accountability on YouTube | 2024-08-30 | T2 | Peer-reviewed (SAGE) | YouTube-specific |
| 10 | Advertising as governance | 2024-03-21 | T2 | Ethnographic research | YouTube-specific |
| 11 | Tiered governance and demonetization | Duke | T2 | Academic analysis | YouTube-specific |
| 12 | Intel patent US 6,691,223 (partial exceptions) | 2004-02-10 | T3 | Patent (hardware) | Hardware/SIMD only, not SE |
| 13 | Bryant University cancel option study | 2023 | T4 | Experimental (2 studies) | Consumer behavior, not platform design |
| 14 | FTC Uber complaint | 2025 | T4 | Legal enforcement document | Single company case |
| 15 | UX dark pattern taxonomy | 2026 | T4 | Practitioner framework | Not peer-reviewed |
| 16 | Business Wire consumer survey | 2025-09-23 | T4 | Industry survey (n unspecified) | Industry-funded, methodology unclear |
| 17 | DirectPayNet easy-cancel analysis | 2025-08-18 | T4 | Practitioner case study | Single company perspective |
| 18 | JSON Schema 2020-12 spec | Current | SPX | Authoritative specification | No implementation guidance |
| 19 | YAML media type RFC 9512 | Current | SPX | Authoritative RFC | Interoperability focus only |
| 20 | Templane spec (YAML schema + TIR) | 2026-04-24 | SPX | Comparable system | Early-stage, single author |
| 21 | Sharp view engine (compile pipeline) | Current | SPX | Comparable system | PHP-specific, not language-neutral |
| 22 | Packagist Foundry collision | 2026-09-02 | SPX | Live registry data | Single package, may change |
| 23 | Packagist SPX collision (php-spx) | 2026-08-01 | SPX | Live registry data | Single package, may change |

### Uncertainty Register

| # | Uncertainty | Impact | Mitigation |
|---|-------------|--------|------------|
| U1 | Connecting-theme attribution (Variants B & C) unverified | May misattribute owner intent | Present side-by-side; ask owner |
| U2 | "Half Exceptions" formulations 3 & 4 may not represent owner intent | Could waste research effort | Owner confirmation needed |
| U3 | External collision data may change (Packagist packages added/removed) | Naming audit may become stale | Re-verify before any rename decision |
| U4 | Research reports are not source-backed with primary academic sources | Evidence quality varies | Prioritize primary sources in final reports |
| U5 | V5 (local commit `581945d`) not in repo — cannot verify patch applicability | May miss requirements | Reconstruct from handoff docs |
| U6 | Research agenda not yet authorized for canonical location | Cannot publish | Owner authorization required |

---

## 10. Research Index

| Report | Status | Location | Evidence Level |
|--------|--------|----------|----------------|
| Track 1: Platform integrity/copyright/fair disputes | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` Track 1 | Hypotheses + deliverables defined; web research supplementary |
| Track 2: Avoiding YouTube model | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` Track 2 | Hypotheses + deliverables defined; web research supplementary |
| Track 3: Half Exceptions | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` Track 3 | Terminology research + analogies; web research confirms no SE term exists |
| Track 4: Cancel-Anytime paradox | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` Track 4 | Hypotheses + empirical evidence found (Bryant, FTC, UX dark patterns) |
| Track 5: Connecting principles | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` Track 5 | One variant only; variants 2 & 3 need adding |
| SuperPHP naming investigation | Draft (in RESEARCH-AGENDA.md) | `RESEARCH-AGENDA.md` SuperPHP section | Collision audit complete; external audit needs updating |
| V1–V7 reconciliation matrix | **This document** | `RESEARCH-RECONCILIATION-MATRIX.md` | Reconstructed from handoff + briefing + agenda |
| Standalone SPX report | **Needed** | Not yet created | Extract from agenda + this matrix |
| Research index | **This document (§10)** | `RESEARCH-RECONCILIATION-MATRIX.md` | Links all reports |

---

**End of reconciliation matrix.** This document is a research deliverable, not a governance record. It does not authorize publication of the canonical agenda or any change to repository records.