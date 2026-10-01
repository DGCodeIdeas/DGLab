# ADR-021: Tier-Stratified Build Order with Two-DAG Governance Model

**Status:** Accepted (amended 2026-10-01 — two amendments: (1) original ADR ratified single-DAG model → two-DAG governance model; (2) edge dimension refinement — separated edge_type from requiredness, gate→gates list, multigraph semantics)
**Date:** 2026-09-30 (original); 2026-10-01 (amendment 1: two-DAG model); 2026-10-01 (amendment 2: edge dimensions)
**Author:** DGCI (architecture lead)
**Supersedes:** `Architecture/INDEX.md §5.3` (11-step global build sequence — superseded by per-tier derived build orders) and `Architecture/INDEX.md §5.2` (monolithic Mermaid — superseded by per-tier declared + verified DAGs)
**Companion:**
- `Architecture/Core/CORE-VERIFIED-DAG.md` — 13-edge verified implementation DAG (Composer + source imports + filesystem evidence)
- `Architecture/Core/CORE-DECLARED-DAG.md` — 45-edge declared architecture DAG (blueprint Upward/Downward + ADR + SPEC intent)
- `Architecture/Core/CORE-CAPABILITY-DAG.md` — capability delivery DAG (CAPABILITY-typed edges)
- `Architecture/Core/CORE-BUILD-ORDER.md` — generated build order (derived from verified DAG + SDLC admission state; do not edit manually)
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

The `ELQ-DECISIONS-RATIFY-6.5` baseline regeneration (`Task 70`) confirmed: 13 Core implementations (not 11), PHP ^8.4 (not 8.3 as README claims), 21 ADRs (not 20), 102 blueprints, 20 implemented packages. README and INDEX are stale.

The deeper structural problem: a single global build sequence cannot express that rings have fundamentally different natures, and a single DAG cannot distinguish architectural intent from implementation reality. The original ADR-021 (2026-09-30) ratified the 13-edge verified DAG as "authoritative" and the 45-edge declared view as "intent, not authoritative for admission." SAAI and Z.ai independently identified this as a false choice — both DAGs are authoritative, for different questions.

This amendment establishes the **two-DAG governance model** that fixes these defects at the root.

## Decision

**Adopt a tier-stratified build order with two-DAG governance.** Each tier owns two authoritative DAGs with different scopes: a **Declared Architecture DAG** (architectural intent) and a **Verified Implementation DAG** (repository reality). Build orders are generated artifacts derived from both DAGs plus SDLC admission state — not independently authored. The SDLC's admission rule uses an `Eligible(X)` formula that gates admission on dependency closure + capability prerequisites + architecture gate + SDLC admission.

### 1. The Five Tiers

| Tier | Rings | Packages | Notes |
|---|---|---|---|
| **Runtime** | RUNTIME-01..04 | Anvil v3 (Caddy+Tengine+FrankenPHP), systemd timers, Queue Worker (relocated from HUB-10), Chronos (relocated from HUB-25) | The substrate layer. RUNTIME-01 depth 1 = `anvilctl verify all` passes. |
| **Core** | CORE-01..20 | All 20 Core blueprints | Pure libraries. Contract-coupled, not class-coupled (verified: only C18 Kernel imports sibling Core namespaces in src/). |
| **Hub** | HUB-01..32 | 31 existing + **HUB-32 AI Inference Hub** (ratified below) | HUB-10 and HUB-25 relocate to Runtime tier. |
| **Applications** | ESPOKE-01..19 + ISPOKE-01..27 + BRIDGE-01 | 19 ESPOKEs (incl. **ESPOKE-19 Eloq** ratified below) + 27 ISPOKEs + 1 Bridge | Per-application DAGs (each ESPOKE owns its own manifest). Bridge routes through Integration DAG, not tier-DAG family. |
| **Deploy + Tooling** | DEPLOY-00..04 + CORE-13 + CORE-20 | 7 packages | Already close to tier-local DAG per SAAI audit. |

### 2. Four Edge Types + Requiredness Dimension

**Edge types** describe HOW the dependency works (mechanics + delivery semantics). **Requiredness** describes WHETHER it's mandatory (gating decision). These are orthogonal dimensions — an edge can be COMPILE+REQUIRED, COMPILE+OPTIONAL, RUNTIME+REQUIRED, etc.

**Edge types** (exactly one per edge):

| Edge type | Meaning | Example |
|---|---|---|
| **COMPILE** | Consumer requires target for construction/compilation/package assembly | `C10 → C09` (Logger requires Config via composer) |
| **RUNTIME** | Consumer requires target while executing its runtime behavior | `C18 → RUNTIME-01` (Kernel needs FrankenPHP worker) |
| **INTEGRATION** | Consumer depends on an external/system boundary being available and correctly wired | `C19 → MySQL`, `C14 → S3` |
| **CAPABILITY** | Consumer requires a capability supplied by the target, without necessarily having a direct implementation dependency | `C19 → HUB-04` (DBAL enables Identity) |

**Requiredness** (independent of edge_type):

| Value | Meaning |
|---|---|
| **REQUIRED** | Missing target prevents the consumer from satisfying the relevant architectural gate |
| **OPTIONAL** | Consumer remains valid without the target; target provides an optional enhancement/path |

**This separation is critical.** `DECLARED_ONLY` (verification status) is NOT the same as `OPTIONAL` (requiredness). The edge `C18 → C17` is DECLARED_ONLY (C17 not yet implemented) but REQUIRED (production gate requires C17). Conflating these would cause a future generator to infer `verified=false → OPTIONAL → doesn't gate` — missing the C17 production gate requirement entirely.

### 3. Two-DAG Governance Model (LOCKED)

**Each tier owns two authoritative DAGs with different scopes:**

| DAG | Authority | Source | Drives |
|---|---|---|---|
| **DECLARED DAG** (e.g., `CORE-DECLARED-DAG.md`) | Architectural intent ("should be") | Blueprints, ADRs, SPECs, declared `Upward` dependencies, capability contracts | Capability planning, architecture gates, identifying missing implementation work |
| **VERIFIED DAG** (e.g., `CORE-VERIFIED-DAG.md`) | Repository reality ("is proven") | Composer manifests + PHP namespace imports + filesystem/package structure + generated implementation metadata + verified integration tests | Build eligibility, package-level ordering, SDLC admission |

**Neither DAG overrides the other.** They answer different questions. The naming makes the authority boundary obvious — no future engineer asks "which one is the real DAG."

```
                 ARCHITECTURE
                      │
             ┌────────┴────────┐
             ▼                 ▼
       DECLARED DAG       VERIFIED DAG
       "should be"         "is proven"
             │                 │
             └────────┬────────┘
                      ▼
                SDLC ADMISSION
                      │
             ┌────────┴────────┐
             ▼                 ▼
       Capability Gates    Implementation Gates
             │                 │
             └────────┬────────┘
                      ▼
               Build Eligibility
```

### 4. Four Edge Status Categories (LOCKED)

Each edge's status is determined by comparing the declared and verified DAGs:

| Status | Declared | Verified | Meaning | Action |
|---|---|---|---|---|
| `VERIFIED` | ✓ | ✓ | Implemented architectural dependency | None — healthy |
| `DECLARED_ONLY` | ✓ | ✗ | Architectural work still required | Implement to close the gap |
| `UNDECLARED_VERIFIED` | ✗ | ✓ | Architectural drift — investigate | Declare the edge or remove the dependency |
| `INVALID` | ✗ | ✗ | No dependency | Prevents noise accumulation |

**Example:** `CORE-18 → CORE-17` is `DECLARED_ONLY` — the blueprint declares the dependency but C17 is not implemented. The kernel's `Stub/ProviderRegistryInterface.php` is the evidence of the declared-but-unverified edge.

### 5. Machine-Readable Edge Metadata Schema (LOCKED)

Each edge is a structured record with 8 fields across 4 dimensions:

```yaml
source: CORE-18          # consuming component
target: CORE-17          # consumed component
edge_type: RUNTIME       # COMPILE | RUNTIME | INTEGRATION | CAPABILITY (exactly one)
requiredness: REQUIRED    # REQUIRED | OPTIONAL (independent of edge_type)
declared: true            # bool — architecture declares this edge
verified: false           # bool — repository evidence confirms this edge
status: DECLARED_ONLY    # DERIVED from (declared, verified): VERIFIED | DECLARED_ONLY | UNDECLARED_VERIFIED | INVALID
gates:                   # LIST — one or more gate values this edge affects
  - RUNTIME
  - PRODUCTION
evidence:                # evidence supporting the edge's current state (declaration evidence if verified=false, verification evidence if verified=true)
  - packages/core/kernel/src/Stub/ProviderRegistryInterface.php
```

**Four dimensions:**
- `edge_type` — HOW the dependency works (mechanics)
- `requiredness` — WHETHER it's mandatory (gating decision)
- `declared` + `verified` → `status` (derived) — evidence state
- `gates` — WHAT readiness gates it affects (consequences; list, not scalar — one edge can affect multiple gates)
- `evidence` — WHY (proof sources supporting the current state)

**Evidence definition:** `evidence` is **evidence supporting the edge's current state**, not necessarily verification evidence. For `DECLARED_ONLY` edges, evidence is the architectural declaration (blueprint file path). For `VERIFIED` edges, evidence is the code proof (composer.json, use statement, test). For `UNDECLARED_VERIFIED`, evidence proves the repository relationship that architecture hasn't declared.

This makes the DAG **machine-generated and machine-verified** — not hand-maintained. Drift between declared and verified becomes automatically detectable. Future tooling (`scripts/generate-verified-dag.py`, `scripts/generate-declared-dag.py`, `scripts/compare-dags.py`) will produce and compare these records automatically.

### 6. Three-Axis Status Model (replaces single "depth" number)

The current SDLC depth scale (1-6) conflates three orthogonal concepts. This ADR establishes a three-axis model:

| Axis | What it measures | Evidence | Example |
|---|---|---|---|
| `implementation_depth` | Does the code exist + pass PHPUnit/PHPStan? | Source files + test results | C18: depth 2 (code exists, tests pass) |
| `integration_completeness` | Is the assembled-system wiring complete? | Verified DAG edges + stub analysis | C18: PARTIALLY WIRED (C17 stub = no-op boot) |
| `production_gate` | What's required for production readiness? | Declared DAG edges not yet verified | C18: C17 required (provider system must exist) |

This applies beyond Core — Hub and Spoke packages will have the same pattern (unit tests pass while assembled-system capabilities remain incomplete).

**C18 example (the clearest case):**
- `implementation_depth`: 2 (Kernel code exists, 15 PHPUnit tests pass)
- `integration_completeness`: PARTIALLY WIRED (`EmptyProviderRegistry` no-op stub for C17)
- `production_gate`: C17 required (provider system must exist before boot is real)

### 7. The `Eligible(X)` Admission Rule

```
Eligible(X) =
    every REQUIRED incoming edge has its required gates satisfied
        (REQUIRED edges gate admission; OPTIONAL edges do not)
    AND required CAPABILITY dependencies are delivered
        (REQUIRED CAPABILITY edges into X have targets delivered)
    AND architecture gate passed
        (interfaces frozen + fitness functions pass + lifecycle/resource guarantees)
    AND SDLC admission granted
        (capacity, findings, throughput calibration, cooldown status)
```

**Key principle:** `edge_type` tells us HOW to evaluate the dependency. `requiredness` determines WHETHER it gates. `gates` tells us WHAT's affected. These are cleanly separated — `edge_type` never itself determines whether an edge gates admission; `requiredness` does.

### 8. Six-Criteria Capability Gate

A tier is "at gate" when ALL of:
1. Required dependency closure exists (REQUIRED edges satisfied)
2. Required capability closure exists (REQUIRED CAPABILITY edges delivered)
3. Interfaces frozen (export-allow-list enforced)
4. Architecture fitness passes (ring-boundary, contamination, freeze tests)
5. Lifecycle/resource guarantees pass (worker recycling, signal handling — for runtime-touching tiers)
6. Required application path is executable (for Application tier: `anvilctl verify all` against staging)

**Tier population ≠ Required production closure.** The "required closure" is a SUBSET of the tier — the packages on the path to a production-deployable application — not the entire tier population. A tier is "at gate" when the required production closure satisfies the six criteria, not when ALL packages in the tier are complete. A tier can be at-gate with 12 of 20 Core blueprints at depth 2 if those 12 constitute the required production closure for the application path being deployed.

### 8.5. Multigraph Semantics (LOCKED)

The DAG is a **multigraph**: the same source/target pair may have multiple edges with different `edge_type` values. For example:

```
CORE-10 ──COMPILE──────► CORE-09   (Logger's composer.json requires Config)
CORE-10 ──CAPABILITY───► CORE-09   (Logger needs Config's values to function)
```

Each edge independently carries `requiredness`, `evidence` state, and `gates` impact. This is preferable to creating an overloaded edge whose semantics become difficult for generators to reason about.

**Generator edge identity:** `source + target + edge_type`. The generator's edge identity is NOT `source + target` — otherwise it could overwrite the COMPILE edge when it discovers a CAPABILITY edge between the same components.

### 9. Core DAGs (Two Views, Both Authoritative)

Per `CORE-DAG-RECONCILIATION-8` + `Task 70` baseline evidence:

**CORE-VERIFIED-DAG** (13 edges, repository evidence):
| Wave | Count | Packages |
|---|---|---|
| 0 | 15 | C01, C02, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20 |
| 1 | 2 | C05, C09 |
| 2 | 2 | C06, C08 |
| 3 | 1 | C18 |

**CORE-DECLARED-DAG** (45 edges, blueprint intent):
Documented in `Architecture/Core/CORE-DECLARED-DAG.md`. Includes future/assembled-system edges not yet observable in PHP code (e.g., `C18 → C17` declared but C17 not yet implemented).

**Core is contract-coupled, not class-coupled.** Verified: only C18 Kernel imports sibling Core namespaces in src/. The other 11 implemented packages use only PSR contracts. This collapses the verified wave computation to 4 waves for 20 packages (15 sit in Wave 0).

### 10. Build Order as Generated Artifact (LOCKED)

> **Build-order documents are generated from the current architecture and repository evidence. Do not edit manually.**

Build order = f(Declared DAG, Verified DAG, Capability requirements, Current implementation state, SDLC admission). The `CORE-BUILD-ORDER.md` file carries a "Generated, do not edit manually" header. Future tooling will regenerate it automatically.

### 11. INDEX Authority Evolution (LOCKED)

**INDEX owns:** identity and governance (IDs, names, tier membership, numbering, canonical status, ADR relationships, governance rules).

**INDEX does NOT own:** repository-derived facts (actual Composer dependencies, actual namespace imports, actual implementation status, actual topological ordering, actual test state). Those are generated.

> INDEX is the canonical registry of architectural identity and governance, while dependency graphs are generated authoritative views of declared intent and verified implementation state.

### 12. Tier-Local DAG Contract (LOCKED)

Every tier gets the same two-DAG treatment:

```
Core     → CORE-DECLARED-DAG + CORE-VERIFIED-DAG
Hub      → HUB-DECLARED-DAG + HUB-VERIFIED-DAG
Bridge   → BRIDGE-DECLARED-DAG + BRIDGE-VERIFIED-DAG
Spoke    → APP-DECLARED-DAG + APP-VERIFIED-DAG (per-application)
Deploy   → DEPLOY-DECLARED-DAG + DEPLOY-VERIFIED-DAG
Runtime  → RUNTIME-DECLARED-DAG + RUNTIME-VERIFIED-DAG
```

The global graph becomes a **derived integration view**, not another independently maintained graph.

### 13. HUB-32 AI Inference Hub (Immediate Ratification — ratified pending canonical publication)

**ISPOKE-E3 from the ELQ analysis is immediately promoted to HUB-32**, bypassing the deferred-promotion rule. LLM invocation is judged as foundational as Identity (HUB-04) or Audit (HUB-06). The Hub ring grows from 31 to 32 packages. All ELQ ISPOKEs that consumed E3 now consume HUB-32.

**Status: ratified pending canonical publication.** The HUB-32 blueprint file does not yet exist in `Architecture/Hub/`. INDEX.md still says 31 Hubs. This is intentional — the decision is ratified; the canonical blueprint is deferred to the implementation phase.

### 14. ESPOKE-19 Eloq (New Application — ratified pending canonical publication)

**Eloq is ratified as ESPOKE-19**, a new External Spoke. Consumes HUB-32 + HUB-04, composes 14 ISPOKEs from the ELQ analysis. Content policy: **neutral parity** — the 22 document-type × 10 paraphrase-style taxonomy ships intact including NSFW doc-types; per-doc `content_filter_setting` toggle preserved as user choice; `BLOCK_NONE` Gemini safety setting available.

**Status: ratified pending canonical publication.** The ESPOKE-19 blueprint file does not yet exist. INDEX.md still says 18 ESPOKEs.

### 15. ISPOKE Contract Lint Rule (E11/E12 Resolution — LOCKED)

**An ISPOKE with `reusable: true` MAY NOT transitively depend on an ISPOKE with `reusable: false`** without making the dependency explicitly `OPTIONAL`. This is an architecture lint invariant, not just a documentation note.

### 16. Namespace Root Lint Rule (LOCKED)

> **A namespace root belongs to exactly one package unless an explicit namespace-partition contract exists.**

This catches the C04↔C05 collision (`SovereignStack\Core\Http\` shared root) and should become an enforceable rule in `architecture-boundary-lint.py`, not just a documented defect.

### 17. ISPOKE-E4 Paraphrase Engine (Deferred)

**ISPOKE-E4 is deferred** until the Eloq ESPOKE-19 ships or new content-creation ESPOKEs are admitted. Per `ESPOKE-CONSUMER-MAP-7`: 0 YES consumers in the existing 18 ESPOKEs.

### 18. ISPOKE-E15 Theme Manager (HUB-26 Absorption Target)

**ISPOKE-E15 is ratified as a HUB-26 absorption target**, not a new HUB-33. Per `ESPOKE-CONSUMER-MAP-7`: E15 crosses the 50% Hub-promotion threshold (61.1% YES, 11 of 18 ESPOKEs). Absorb into HUB-26 UI Elements when HUB-26 ships.

### 19. Hub-Promotion Reassessments

Per `ESPOKE-CONSUMER-MAP-7`:
- **ISPOKE-E13 (Privacy & Audit Ledger)**: PARTIAL SPLIT reaffirmed — mechanism → HUB-06 Auditor; slim policy-label ISPOKE stays.
- **ISPOKE-E8 (BYOK Vault)**: demoted to ISPOKE (5.6% YES, 1 consumer).
- **ISPOKE-E9 (Remote Backup Orchestrator)**: demoted to ISPOKE (11.1% YES, 2 consumers). S3 target delegated to HUB-11 Cloud Storage.

### 20. HUB-10 and HUB-25 Relocation to Runtime Tier

**HUB-10 (Queue Worker) and HUB-25 (Chronos TaskRunner) relocate from Hub tier to Runtime tier** as RUNTIME-03 (Worker) and RUNTIME-04 (Scheduler). Their primary purpose is to BE the long-running process substrate, not to consume Hub capabilities.

### 21. Known Latent Defects (Documented, Not Fixed in This ADR)

Per `CORE-DAG-RECONCILIATION-8`:
- **C04↔C05 namespace collision** — Both `http-message/composer.json` and `middleware/composer.json` declare `"SovereignStack\\Core\\Http\\": "src/"` as the PSR-4 root. Remediation: namespace split in a future ADR. The namespace root lint rule (§16) will catch this going forward.
- **C18 forward-declaration stub for C17** — `kernel/src/Stub/ProviderRegistryInterface.php` + `EmptyProviderRegistry.php` are placeholders. C18's `implementation_depth` is 2 but `integration_completeness` is PARTIALLY WIRED and `production_gate` requires C17. The three-axis status model (§6) captures this precisely.
- **H05/H07 Rate Limiter duplication** — H05 and H07 may be duplicate Rate Limiter Hubs. Tech-lead decision pending.

## Consequences

### Positive

1. **Two-DAG governance prevents drift.** Declared and verified DAGs answer different questions; both are authoritative. Drift between them becomes automatically detectable via the four edge status categories.
2. **Runtime substrate becomes visible to the SDLC.** The missing layer (Anvil v3, systemd timers) is now a first-class tier with depth requirements.
3. **Build orders derived, not hand-numbered.** Topological waves calculated from verified edges eliminate arbitrary sequencing.
4. **Three-axis status model** separates implementation depth from integration completeness from production gate — no more conflating "tests pass" with "production ready."
5. **Machine-readable edge metadata** enables future tooling to auto-generate DAGs, compare them, and detect drift.
6. **INDEX authority evolution** prevents manual maintenance of derived facts — "never manually maintain what the repo can derive."
7. **HUB-32 unblocks 4 ELQ ISPOKEs** and future AI-consuming ESPOKEs.

### Negative

1. **Documentation surface grows.** Each tier gets two DAGs + capability DAG + build order. Mitigated by generation from blueprints + repository evidence (not hand-maintained).
2. **HUB-10 and HUB-25 relocation** breaks external references. Blueprints are marked SUPERSEDED with redirect pointers.
3. **DAG generator tooling not yet built.** The two-DAG model is ratified but the scripts (`generate-verified-dag.py`, `generate-declared-dag.py`, `compare-dags.py`, `generate-build-order.py`) are tracked as future tasks. Until they exist, DAGs are hand-maintained markdown.
4. **Known latent defects (§21) are documented but not fixed.** The C04↔C05 namespace collision and C17 forward-declaration stub are tracked for future ADRs.

### Neutral / Gated

1. **SDLC-AGRD v4.0 rewrite** — implements the Eligible(X) admission rule + per-tier laps. Tracked as a separate PR.
2. **DAG generator scripts** — `scripts/generate-verified-dag.py` etc. Tracked as future tasks.
3. **Cross-tier admission-gate fitness function** — new FF in `scripts/fitness/`. Tracked as a separate PR.
4. **Hub/Spoke/Deploy/Runtime per-tier DAGs** — Core is first; others follow the same two-DAG methodology.

## Rejected Alternatives

| Alternative | Why Rejected |
|---|---|
| **Single global build sequence (status quo)** | Verified defective: 4 of 7 Core-tier steps wrong; "selected critical" Hub subset inconsistent; 3 contradictory CORE-02 statuses. |
| **Single authoritative DAG (original ADR-021 §5)** | False choice between 13-edge verified and 45-edge declared. Both answer different questions; both are authoritative. Forces choosing "which is the real DAG" when both are real. |
| **Tier-stratified laps** | Forces synchronization that doesn't match reality (Runtime work doesn't fit in a "lap" — it's ops work, not code). |
| **Bilateral CONSENT edges** | Per SAAI: ownership was theater; consent is governance metadata, not a dependency. Consumer-side composition policy is sufficient. |
| **Per-tier depth scales** | Subsumed by the three-axis status model + Capability DAG. |
| **45-edge declared DAG as sole authority** | Not honest about current state — would ignore the fact that most edges are not yet verified in code. |
| **13-edge verified DAG as sole authority** | Loses architectural intent — would miss `C18 → C17` (declared but not yet implemented) and other future-looking edges. |
| **Single-enum edge_type (original ADR-021 §2)** | Conflated three orthogonal dimensions: dependency mechanics (COMPILE/RUNTIME/INTEGRATION), delivery semantics (CAPABILITY), and requiredness (OPTIONAL). A DECLARED_ONLY edge would be implicitly treated as OPTIONAL, missing REQUIRED production gate dependencies like C17. |
| **Scalar `gate` field** | Forces choosing one gate when an edge can affect multiple gates (e.g., C18→C17 affects both RUNTIME and PRODUCTION). List `gates` captures the full gate impact set. |

## Relationship to Other Documents

| Document | Relationship |
|---|---|
| `INDEX.md §5.3` | **SUPERSEDED** — 11-step global build sequence replaced by per-tier generated build orders. |
| `INDEX.md §5.2` | **SUPERSEDED** — monolithic Mermaid replaced by per-tier declared + verified DAGs. |
| `Architecture/Core/CORE-VERIFIED-DAG.md` | **NEW** (renamed from `CORE-DEPENDENCY-DAG.md`) — 13-edge verified implementation DAG. |
| `Architecture/Core/CORE-DECLARED-DAG.md` | **NEW** — 45-edge declared architecture DAG. |
| `Architecture/Core/CORE-CAPABILITY-DAG.md` | Existing — capability delivery DAG (CAPABILITY-typed edges). |
| `Architecture/Core/CORE-BUILD-ORDER.md` | Existing — updated with "Generated, do not edit manually" header. |
| `Architecture/Hub/HUB-32.md` | **Ratified pending canonical publication** — blueprint file to be created during implementation phase. |
| `Architecture/Spoke/External/ESPOKE-19.md` | **Ratified pending canonical publication** — blueprint file to be created during implementation phase. |
| `Architecture/Hub/HUB-10.md` | **SUPERSEDED** — relocated to Runtime as RUNTIME-03. |
| `Architecture/Hub/HUB-25.md` | **SUPERSEDED** — relocated to Runtime as RUNTIME-04. |
| `scripts/generate-architecture-baseline-v2.py` | **NEW** — evidence-collection script for baseline generation. |
| `download/ARCHITECTURE_BASELINE.md` | Generated artifact — evidence snapshot, not committed (gitignored, reproducible by running the script). |
| ADR-014 | Companion. SDLC v3.5 → v4.0 rewrite implements Eligible(X) admission rule. |
| ADR-017 | Compatible. Request-lifecycle Kernel (C18) is the runtime-touching Core package. |
| ADR-005 | Compatible. CORE-07/11/12 remain in Core tier as pure compiler chain. |

## Provenance

**Amendment 1** (2026-10-01, PR #287): Established the two-DAG governance model per SAAI + Z.ai convergence analysis: both Declared and Verified DAGs are authoritative for different purposes; neither overrides the other. The four edge status categories, machine-readable edge metadata schema, three-axis status model, INDEX authority evolution, tier-local DAG contract, and namespace root lint rule are new ratifications.

**Amendment 2** (2026-10-01, this PR): Edge dimension refinement per SAAI precision review (7 findings). Separated `edge_type` (HOW — 4 values: COMPILE/RUNTIME/INTEGRATION/CAPABILITY) from `requiredness` (WHETHER — REQUIRED/OPTIONAL) from `gates` (WHAT — list of gate values). Renamed `kind` → `edge_type`. Changed `gate` (scalar) → `gates` (list). Updated Eligible(X) formula to reference requiredness, not edge_type. Formalized "Tier population ≠ Required production closure." Added explicit multigraph semantics (edge identity = source + target + edge_type). Refined `evidence` definition: "evidence supporting the edge's current state" (declaration evidence for DECLARED_ONLY, verification evidence for VERIFIED).

The five tiers, HUB-32/ESPOKE-19 ratification, HUB-10/HUB-25 relocation, and ISPOKE contract lint rule are carried forward from the original ADR.

Baseline evidence from `Task 70` (commit `84d68da`): 20 implemented packages, 200 PHP source files, 88 test files, 21 ADRs, 102 blueprints, PHP ^8.4 confirmed across all packages.
