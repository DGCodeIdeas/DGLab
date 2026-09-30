# ADR-021: Tier-Stratified Build Order with Typed-Edge DAGs

**Status:** Accepted  
**Date:** 2026-09-30  
**Author:** DGCI (architecture lead)  
**Supersedes:** `Architecture/INDEX.md §5.3` (11-step global build sequence — superseded by per-tier derived build orders)  
**Companion:** `Architecture/Core/CORE-DEPENDENCY-DAG.md`, `Architecture/Core/CORE-CAPABILITY-DAG.md`, `Architecture/Core/CORE-BUILD-ORDER.md` — authoritative Core DAGs derived from actual blueprint + code inspection  
**Related:** ADR-014 (SDLC-AGRD canonical), ADR-017 (Fiber-based cooperative runtime), ADR-005 (SuperPHP over Blade/Twig)

---

## Context

DGLab's `INDEX.md §5` currently combines six concerns in a single monolithic Mermaid block (Core DAG + "selected critical" Hub DAG + Bridge deps + Spoke deps + Deploy deps + 11-step global build sequence). Verified defects per `INDEX-VERIFY-3` worklog audit:

1. **The "selected critical" Hub subset is inconsistent with §4's own criticality table** — 4 of §4's 10 Critical Hubs (HUB-05, HUB-09, HUB-10, HUB-21) are missing from §5.2; 4 "High" Hubs are in the DAG instead. The label is misleading — "selected" yes, "critical" no.
2. **§5.3 has an 11-step global build sequence with stale "parallelizable" labels** that ADR-014 retired but the table still uses.
3. **§5.3 Step 8 says "30 blueprints"** but should be 31 (HUB-31 accepted 2026-08-13 per ADR-011).
4. **Three contradictory statements about CORE-02** within INDEX.md (stub-only at §1, fully-implemented at §2.1, build-blocking-top-priority at §2.1 critical-path correction).
5. **§5.2 missed one Core edge**: `C18 → C06` (Kernel → Router) — the architectural reason Step 4 is a sequential chain.
6. **Freshness stamp says 2026-08-12** but §9 changelog records edits through 2026-09-24.

The `CORE-DAG-RECONCILIATION-8` audit verified against actual blueprint + code inspection that **4 of 7 Core-tier steps in §5.3 are wrong**: Step 2 false-parallelism (C10→C09→C08 is sequential per composer.json); Step 3 inverted order (C18 is the sink at Wave 3, not Step 3); Step 5 over-cautious entry criterion (15 of 20 packages can start immediately).

The deeper structural problem: a single global build sequence cannot express that rings have fundamentally different natures. A depth-2 badge means one thing for CORE-02 Container (PHPUnit happy path, real), another for HUB-04 Identity (PHPUnit + MySQL round-trip, real), and a third for BRIDGE-01 Vanguard (needs FrankenPHP serving HTTP to be real). The single depth scale silently degraded to the weakest interpretation, and the runtime substrate became invisible to the SDLC's admission rule.

This ADR ratifies the tier-stratified model that fixes these defects at the root.

## Decision

**Adopt a tier-stratified build order with typed-edge DAGs.** Each tier owns its own complete internal DAG (dependency + capability) and a derived build order. Cross-tier dependencies live in a separate Integration DAG. The SDLC's admission rule uses an `Eligible(X)` formula that gates admission on dependency closure + capability prerequisites + architecture gate + SDLC admission, replacing the hand-numbered 11-step global sequence.

### 1. The Five Tiers

| Tier | Rings | Packages | Notes |
|---|---|---|---|
| **Runtime** | RUNTIME-01..04 | Anvil v3 (Caddy+Tengine+FrankenPHP), systemd timers, Queue Worker (relocated from HUB-10), Chronos (relocated from HUB-25) | The substrate layer. RUNTIME-01 depth 1 = `anvilctl verify all` passes. |
| **Core** | CORE-01..20 | All 20 Core blueprints | Pure libraries. Contract-coupled, not class-coupled (verified: only C18 Kernel imports sibling Core namespaces in src/). |
| **Hub** | HUB-01..32 | 31 existing + **HUB-32 AI Inference Hub** (ratified below) | HUB-10 and HUB-25 relocate to Runtime tier. |
| **Applications** | ESPOKE-01..19 + ISPOKE-01..27 + BRIDGE-01 | 19 ESPOKEs (incl. **ESPOKE-19 Eloq** ratified below) + 27 ISPOKEs + 1 Bridge | Per-application DAGs (each ESPOKE owns its own manifest). Bridge routes through Integration DAG, not tier-DAG family. |
| **Deploy + Tooling** | DEPLOY-00..04 + CORE-13 + CORE-20 | 7 packages | Already close to tier-local DAG per SAAI audit. |

### 2. The Five Edge Types

Per the second-AI proposal refined through SAAI's consumer-side composition critique:

| Edge type | Meaning | Example |
|---|---|---|
| **COMPILE** | Must exist at build time (composer require) | `C10 → C09` (Logger requires Config) |
| **RUNTIME** | Must exist at runtime (worker, request loop, scheduler) | `C18 → RUNTIME-01` (Kernel needs FrankenPHP worker) |
| **INTEGRATION** | Must exist for external service calls | `C19 → MySQL`, `C14 → S3` |
| **CAPABILITY** | Must exist for capability delivery (delivery pressure, not composer pressure) | `C19 → HUB-04` (DBAL enables Identity) |
| **OPTIONAL** | Nice to have; doesn't gate admission | (declared-but-unverified edges per CORE-DAG-RECONCILIATION-8) |

**CONSENT edges are NOT used.** Per SAAI's critique (APP-MODEL-REFINEMENT-5): consumer-side composition policy lives in each ESPOKE's Application Manifest as governance metadata, not in the dependency DAG. An ISPOKE's `reusable: false` flag is lint-enforced (no other ESPOKE may declare it in their composition policy without an ADR promotion).

### 3. The `Eligible(X)` Admission Rule

```
Eligible(X) =
    dependency closure satisfied
        (all COMPILE/RUNTIME/INTEGRATION-typed edges into X have targets at depth ≥2)
    AND capability prerequisites satisfied
        (all CAPABILITY-typed edges into X have targets delivered)
    AND architecture gate passed
        (interfaces frozen + fitness functions pass + lifecycle/resource guarantees)
    AND SDLC admission granted
        (capacity, findings, throughput calibration, cooldown status)
```

The SDLC's lap model becomes: "from the Eligible set, pick the next unit based on critical path, application need, security, maturity, findings, throughput." No more hand-numbered steps.

### 4. The Six-Criteria Capability Gate

A tier is "at gate" when ALL of:
1. Required dependency closure exists (typed edges satisfied)
2. Required capability closure exists (CAPABILITY edges satisfied)
3. Interfaces frozen (export-allow-list enforced)
4. Architecture fitness passes (ring-boundary, contamination, freeze tests)
5. Lifecycle/resource guarantees pass (worker recycling, signal handling — for runtime-touching tiers)
6. Required application path is executable (for Application tier: `anvilctl verify all` against staging)

**Not "all N complete."** A tier can be at-gate with 12 of 20 Core blueprints at depth 2 if those 12 satisfy the application path requirement.

### 5. Core Build Order (Authoritative)

Per `CORE-DAG-RECONCILIATION-8`, derived from the 13-edge strict-verified DAG (honest current-state view; the 45-edge blueprint-declared view is documented in `CORE-DEPENDENCY-DAG.md §4` as architectural intent, not authoritative for admission):

| Wave | Count | Packages |
|---|---|---|
| 0 | 15 | C01, C02, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20 |
| 1 | 2 | C05, C09 |
| 2 | 2 | C06, C08 |
| 3 | 1 | C18 |

**Core is contract-coupled, not class-coupled.** Verified: only C18 Kernel imports sibling Core namespaces in src/. The other 11 implemented packages use only PSR contracts. This collapses the topological wave computation to 4 waves for 20 packages (15 sit in Wave 0).

### 6. HUB-32 AI Inference Hub (Immediate Ratification)

**ISPOKE-E3 from the ELQ analysis is immediately promoted to HUB-32**, bypassing the deferred-promotion rule (APP-MODEL-REFINEMENT-5 extension #2 "wait for second consumer"). LLM invocation is judged as foundational as Identity (HUB-04) or Audit (HUB-06). The Hub ring grows from 31 to 32 packages. All ELQ ISPOKEs that consumed E3 (E4 Paraphrase, E5 Grammar, E11 Content Classification, E12 Generation) now consume HUB-32.

The hierarchical failover pattern from ELQ's `server-api.cjs:39-296` (Gemini SDK → OpenAI-compatible fetch → Pollinations zero-key → rule-based local fallback, with model cascade) is the reference design for HUB-32's implementation when code is admitted.

### 7. ESPOKE-19 Eloq (New Application)

**Eloq is ratified as ESPOKE-19**, a new External Spoke (not a rename of an existing planned app). The 19th ESPOKE consumes HUB-32 (AI Inference) + HUB-04 (Identity) and composes 14 ISPOKEs from the ELQ analysis (post E3→HUB-32 promotion): E1, E2, E4, E5, E6, E7, E8, E9, E10, E11, E12, E13, E14, E15. Content policy: **neutral parity** — the 22 document-type × 10 paraphrase-style taxonomy ships intact including NSFW doc-types; per-doc `content_filter_setting` toggle preserved as user choice; `BLOCK_NONE` Gemini safety setting available. DGLab is policy-neutral at the platform level.

### 8. ISPOKE Contract Lint Rule (E11/E12 Resolution)

**An ISPOKE with `reusable: true` MAY NOT transitively depend on an ISPOKE with `reusable: false`** without making the dependency optional (no-op when no config provided). This resolves the ISPOKE-E11 (Manuscript Exporter, `reusable: true`) → ISPOKE-E12 (Censorship & Redaction Engine, `reusable: false`) hidden dependency surfaced by `ESPOKE-CONSUMER-MAP-7`. E11's redaction-aware feature must be made optional (no-op when no `CensorshipConfig` is provided) to unblock 3 cross-ESPOKE consumers (Billing, Beacon, Forge).

### 9. ISPOKE-E4 Paraphrase Engine (Deferred)

**ISPOKE-E4 (Paraphrase Engine) is deferred** until the Eloq ESPOKE-19 ships or new content-creation ESPOKEs are admitted. Per `ESPOKE-CONSUMER-MAP-7`: 0 YES consumers in the existing 18 ESPOKEs. The 22 doc-types are heavily weighted toward fiction/academic/legal registers that no existing ESPOKE produces. The weakest-fit ISPOKE.

### 10. ISPOKE-E15 Theme Manager (HUB-26 Absorption Target)

**ISPOKE-E15 (Theme Manager) is ratified as a HUB-26 absorption target**, not a new HUB-33. Per `ESPOKE-CONSUMER-MAP-7`: E15 crosses the 50% Hub-promotion threshold (61.1% YES, 11 of 18 ESPOKEs). The right move is to absorb E15's 91 LOC pure-library implementation into HUB-26 UI Elements when HUB-26 ships, not create a 33rd Hub.

### 11. Hub-Promotion Reassessments

Per `ESPOKE-CONSUMER-MAP-7`:
- **ISPOKE-E13 (Privacy & Audit Ledger)**: PARTIAL SPLIT reaffirmed — mechanism → HUB-06 Auditor (already shipped); slim policy-label ISPOKE stays at 11.1% YES.
- **ISPOKE-E8 (BYOK Vault)**: demoted from DEFER Hub candidate to ISPOKE (5.6% YES, only ESPOKE-17 Concierge consumes).
- **ISPOKE-E9 (Remote Backup Orchestrator)**: demoted from DEFER Hub candidate to ISPOKE (11.1% YES, 2 consumers). S3 target delegated to HUB-11 Cloud Storage.

### 12. HUB-10 and HUB-25 Relocation to Runtime Tier

**HUB-10 (Queue Worker) and HUB-25 (Chronos TaskRunner) relocate from Hub tier to Runtime tier** as RUNTIME-03 (Worker) and RUNTIME-04 (Scheduler). Their primary purpose is to BE the long-running process substrate, not to consume Hub capabilities — they are runtime-tier packages masquerading as Hub-tier. The Hub ring drops from 32 to 30 packages (31 existing + HUB-32 ratified − HUB-10 relocated − HUB-25 relocated = 30). HUB-10 and HUB-25 blueprint files are marked SUPERSEDED with redirect pointers to RUNTIME-03 and RUNTIME-04.

### 13. Known Latent Defects (Documented, Not Fixed in This ADR)

Per `CORE-DAG-RECONCILIATION-8`:
- **C04↔C05 namespace collision** — Both `http-message/composer.json` and `middleware/composer.json` declare `"SovereignStack\\Core\\Http\\": "src/"` as the PSR-4 root. Latent bug: adding a class to one package with the same name as a class in the other would silently alias. Remediation plan: namespace split (`SovereignStack\Core\Http\Message\*` vs `SovereignStack\Core\Http\Middleware\*`) in a future ADR.
- **C18 forward-declaration stub for C17** — `kernel/src/Stub/ProviderRegistryInterface.php` + `EmptyProviderRegistry.php` are local-to-kernel placeholders for the not-yet-implemented CORE-17. C18 boots with `EmptyProviderRegistry` (no-op) — boot-phase `registerAll()`/`bootAll()` calls are silent no-ops until C17 lands. **C18's depth-2 badge is conditional.** The depth scale should be amended to express "depth 2 with stubs" vs "depth 2 fully wired" in a future ADR.
- **H05/H07 Rate Limiter duplication** — H05's blueprint Upward reads "HUB-04, CORE-19, HUB-02" but CORE-15's Downward says "HUB-07 (Rate Limiter)". H05 and H07 may be duplicate Rate Limiter Hubs. The Capability DAG includes H07 but not H05. Tech-lead decision pending.

## Consequences

### Positive

1. **Runtime substrate becomes visible to the SDLC.** The missing layer (Anvil v3, systemd timers) is now a first-class tier with depth requirements. Depth-2 claims for runtime-touching packages are no longer fiction.
2. **Build orders derived, not hand-numbered.** Topological waves calculated from verified edges eliminate the arbitrary sequencing that put SuperPHP at "Step 6" despite having no Kernel dependency.
3. **Per-tier depth calibration via Capability DAG.** CAPABILITY-typed edges capture tier-specific verification requirements (e.g., "end-to-end HTTP round-trip verified" as a CAPABILITY edge from BRIDGE-01 to RUNTIME-01) without forcing per-tier depth scales.
4. **Application-model clarity.** ESPOKE/ISPOKE many-to-many with consumer-side composition policy; `reusable` flag lint-enforced; Application Manifest as first-class artifact.
5. **HUB-32 unblocks 4 ELQ ISPOKEs** (E4, E5, E11, E12) and future AI-consuming ESPOKEs.

### Negative

1. **Documentation explosion.** Each tier gets its own DAG + capability DAG + build order + (for Applications) per-app manifests. Total documentation surface grows significantly. Mitigated by derivation from blueprints (not hand-maintained) and the `generate-architecture-baseline.py` rewrite planned for the next PR.
2. **HUB-10 and HUB-25 relocation** breaks any external references to those IDs. The blueprints are marked SUPERSEDED with redirect pointers, but downstream consumers (including this ADR's references) must update.
3. **Core DAG has two views** (13-edge strict-verified vs 45-edge blueprint-declared). The strict-verified view is authoritative for admission, but the declared view must be maintained as architectural intent. This dual-view maintenance is overhead.
4. **Known latent defects (§13) are documented but not fixed.** The C04↔C05 namespace collision and C17 forward-declaration stub are tracked for future ADRs.

### Neutral / Gated

1. **SDLC-AGRD v4.0 rewrite** — this ADR ratifies the tier-stratified model; the SDLC document itself must be rewritten from v3.5 (single-lap model) to v4.0 (per-tier laps + Eligible(X) admission). Tracked as a separate PR.
2. **`generate-architecture-baseline.py` rewrite** — the baseline generator must report per-tier depth, cross-tier admission-gate status, and runtime substrate readiness (Tier A depth). Also addresses the stale-blueprint-source / no-require-edges / PHP-Python-twin issues identified in `SDLC-AUDIT-1`. Tracked as a separate PR.
3. **Cross-tier admission-gate fitness function** — new FF in `scripts/fitness/` that checks "does every depth-N claim in tier X satisfy its admission gate?" Catches the runtime-fiction problem structurally going forward. Tracked as a separate PR.
4. **Codex/LMS/Showcase planning** — per `ESPOKE-CONSUMER-MAP-7`, the original ELQ analysis assumed consumers that don't exist in the 18-ESPOKE catalog. Tech-lead decision pending: are Codex/LMS/Showcase planned as new ESPOKEs? Their addition would significantly boost YES counts for E4, E5, E6, E7.

## Rejected Alternatives

| Alternative | Why Rejected |
|---|---|
| **Single global build sequence (status quo)** | Verified defective: 4 of 7 Core-tier steps wrong; "selected critical" Hub subset inconsistent with §4; 3 contradictory CORE-02 statuses; missed C18→C06 edge. |
| **Tier-stratified laps (keep unified lap structure, make each lap tier-scoped)** | Forces synchronization that doesn't match reality (Tier A work doesn't fit in a "lap" — it's ops work, not code). Per-tier separation is cleaner. |
| **Bilateral CONSENT edges (my original proposal)** | Per SAAI's critique: ownership was theater; consent is governance metadata, not a dependency; consumer-side composition policy is sufficient. |
| **Per-tier depth scales (my original proposal)** | Subsumed by the Capability DAG. CAPABILITY-typed edges capture tier-specific verification requirements more generally than per-tier depth scales. |
| **45-edge blueprint-declared DAG as authoritative** | Not honest about current state. The strict-verified 13-edge DAG (4 waves) is authoritative for admission; the 45-edge declared view is documented as architectural intent. |
| **Immediate OS-metaphor Phase-0 packages** (pulse/scheduler/tracer) | Months of work. Only worth it if the OS metaphor is a real product differentiator. Deferred per `SDLC-AUDIT-1` §F.1. |

## Relationship to Other Documents

| Document | Relationship |
|---|---|
| `INDEX.md §5.3` | **SUPERSEDED** by this ADR. The 11-step global build sequence is no longer authoritative. |
| `INDEX.md §5.2` | **SUPERSEDED** by per-tier DAGs. The monolithic Mermaid block is replaced by `Architecture/Core/CORE-DEPENDENCY-DAG.md` (and future Hub/Spoke/Deploy equivalents). |
| `Architecture/Core/CORE-DEPENDENCY-DAG.md` | **NEW** — authoritative Core dependency DAG (typed edges, 13-edge strict-verified view authoritative, 45-edge declared view documented). |
| `Architecture/Core/CORE-CAPABILITY-DAG.md` | **NEW** — authoritative Core capability DAG (CAPABILITY-typed edges to Hub consumers). |
| `Architecture/Core/CORE-BUILD-ORDER.md` | **NEW** — authoritative Core build order (4 topological waves derived from the strict-verified DAG). |
| `Architecture/Hub/HUB-32.md` | **NEW** — AI Inference Hub stub (depth 1, implementation deferred). |
| `Architecture/Spoke/External/ESPOKE-19.md` | **NEW** — Eloq External Spoke stub (depth 1, implementation deferred). |
| `Architecture/Hub/HUB-10.md` | **SUPERSEDED** — relocated to Runtime tier as RUNTIME-03. Blueprint marked SUPERSEDED with redirect. |
| `Architecture/Hub/HUB-25.md` | **SUPERSEDED** — relocated to Runtime tier as RUNTIME-04. Blueprint marked SUPERSEDED with redirect. |
| `ADR-014` (SDLC-AGRD canonical) | Companion. SDLC v3.5 → v4.0 rewrite will implement this ADR's Eligible(X) admission rule. |
| `ADR-017` (Fiber-based cooperative runtime) | Compatible. The cooperative scheduler remains conceptual (deferred per SDLC-AUDIT-1 §F.1); the request-lifecycle Kernel (C18) is the runtime-touching Core package. |
| `ADR-005` (SuperPHP over Blade/Twig) | Compatible. CORE-07/11/12 remain in Core tier as pure compiler chain; not blocking anything currently admitted. |
| `download/ELQ-ANALYSIS.md` | Reference — the 14 ISPOKE decomposition (post E3→HUB-32) and Decisions Ratified section. |
| `download/ELQ-CONSUMER-MAP.md` | Reference — the 10×18 consumer matrix and Hub-promotion reassessments. |

## Provenance

Ratifies the tier-stratified build order model developed through the conversation arc documented in `/home/z/my-project/worklog.md` entries SDLC-AUDIT-1 through ELQ-DECISIONS-RATIFY-6.5. The Core DAG was derived from actual blueprint + code inspection (CORE-DAG-RECONCILIATION-8); the consumer matrix was derived from reading all 18 ESPOKE blueprints (ESPOKE-CONSUMER-MAP-7); the ELQ ISPOKE decomposition was derived from cloning and analyzing the ELQ repository (ELQ-ANALYSIS-6). Tech-lead decisions ratified 2026-09-30: (1) license granted (owns ELQ); (2) Eloq is new ESPOKE-19; (3) LLM to Hub (HUB-32 immediate); (4) neutral parity content policy; (5) consumer analysis requested (executed in ESPOKE-CONSUMER-MAP-7).
