# CORE-BUILD-ORDER — Generated Core-tier Topological Build Waves

<!-- GENERATED ARTIFACT — Do not edit manually. -->
<!-- Generated from: CORE-VERIFIED-DAG + CORE-DECLARED-DAG + SDLC admission state -->
<!-- Reproducible by: scripts/generate-build-order.py (future) -->
<!-- Authority: Implementation reality + SDLC admission. Not authoritative for architectural intent. -->

**Source of truth:** Verified edges from `CORE-VERIFIED-DAG.md` (13 edges: 7 verified-in-code + 6 verified-in-composer).
**Declared view:** `CORE-DECLARED-DAG.md` (45 edges — includes future/assembled-system relationships).
**Status:** GENERATED — derived from verified DAG + SDLC admission state.
**Date:** 2026-09-30 (original); 2026-10-01 (regenerated after two-DAG model ratification)

---

## §0. Wave-computation rules (per task Step 5)

| Rule | Source |
|---|---|
| Wave membership is computed from **VERIFIED** edges only (verified-in-code OR verified-in-composer). | Task Step 5 |
| **OPTIONAL edges do not gate admission** — a package can be built even if its OPTIONAL deps aren't ready. | Task Step 5 |
| **RUNTIME edges gate depth-2 admission** — a package can be unit-tested without RUNTIME deps satisfied, but cannot claim depth 2 until they are (worker substrate, MySQL/Redis for production correctness). | Task Step 5 |
| **INTEGRATION edges gate depth-2 admission only for end-to-end tests** — a package can claim depth 2 in unit-test context without the integration target; end-to-end (e.g., real MySQL query execution) is a separate depth-3 / depth-4 concern. | Task Step 5 |
| Wave 0 = leaves (no incoming verified edges — can be built immediately). | Task Step 5 |
| Wave N = packages whose only verified incoming deps are in Wave 0..N-1. | Task Step 5 |

### §0.1 What counts as a "VERIFIED edge" for wave computation

Per Step 2 verification:
- **VERIFIED-IN-CODE**: consumer's `src/*.php` actually imports `use SovereignStack\Core\<Provider>\*`. (7 edges — all into C18.)
- **VERIFIED-IN-COMPOSER**: consumer's `composer.json` declares `require: sovereign-stack/core-<provider>: "*"`. (6 additional edges.)
- **DECLARED-BUT-UNVERIFIED**: blueprint declares upward, but neither composer.json nor src/ uses it. (4 edges for unimplemented packages + 27 declared-optional edges.) — does NOT gate wave membership, but DOES gate depth-2 admission.

**Wave computation uses 13 verified edges:**

| # | Edge | Type | Verification |
|---|------|------|---|
| 1 | C02 → C18 | COMPILE | src/ imports Container\ContainerInterface |
| 2 | C03 → C18 | COMPILE | src/ imports EventDispatcher\Event + EventDispatcherInterface |
| 3 | C04 → C05 | COMPILE | composer.json requires core-http-message |
| 4 | C04 → C06 | COMPILE | composer.json requires core-http-message |
| 5 | C04 → C18 | COMPILE | composer.json requires core-http-message (NOT-DECLARED-BUT-USED — kernel blueprint doesn't list C04 upward but composer requires it) |
| 6 | C05 → C06 | COMPILE | composer.json requires core-router |
| 7 | C05 → C18 | COMPILE | src/ imports Http\MiddlewarePipeline*, MiddlewareResolver*, FinalRequestHandler (via shared Core\Http namespace with C04) |
| 8 | C06 → C18 | COMPILE | src/ imports Router\RouterInterface |
| 9 | C08 → C18 | COMPILE | src/ imports ErrorHandler\ErrorHandlerInterface |
| 10 | C09 → C08 | COMPILE | composer.json requires core-logger |
| 11 | C09 → C18 | COMPILE | src/ imports Logger\LoggerInterface as DgLoggerInterface |
| 12 | C10 → C09 | COMPILE | composer.json requires core-config |
| 13 | C10 → C18 | COMPILE | src/ imports Config\ConfigInterface |

---

## §1. Wave computation (Kahn's algorithm)

### §1.1 In-degree computation (per package, using 13 verified edges)

| Package | In-degree | Incoming verified edges from |
|---|---|---|
| C01 | 0 | (no incoming edges) |
| C02 | 0 | (no incoming edges) |
| C03 | 0 | (no incoming edges) |
| C04 | 0 | (no incoming edges) |
| C05 | 1 | C04 |
| C06 | 2 | C04, C05 |
| C07 | 0 | (no incoming edges) |
| C08 | 1 | C09 |
| C09 | 1 | C10 |
| C10 | 0 | (no incoming edges) |
| C11 | 0 | (no incoming edges — blueprint-declared C07→C11 is not verified since C11 not implemented) |
| C12 | 0 | (no incoming edges — same reason) |
| C13 | 0 | (no incoming edges) |
| C14 | 0 | (no incoming edges) |
| C15 | 0 | (no incoming edges — blueprint-declared C14→C15 is optional and C15 not implemented) |
| C16 | 0 | (no incoming edges) |
| C17 | 0 | (no incoming edges — C17 not implemented; blueprint-declared C02→C17/C10→C17/C09→C17 not verified) |
| C18 | 8 | C02, C03, C04, C05, C06, C08, C09, C10 |
| C19 | 0 | (no incoming edges) |
| C20 | 0 | (no incoming edges — blueprint-declared C13→C20/C17→C20 not verified since both unimplemented) |

### §1.2 Topological waves (Kahn's algorithm)

```
In-degrees:
  C01=0 C02=0 C03=0 C04=0 C05=1 C06=2 C07=0 C08=1 C09=1 C10=0
  C11=0 C12=0 C13=0 C14=0 C15=0 C16=0 C17=0 C18=8 C19=0 C20=0

WAVE 0 (in-degree 0 — pick all leaves):
  C01, C02, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20  (15 packages)

  After removing Wave 0, decrement neighbors:
    C05: 1 - 1 (from C04 removed) = 0  → ready for Wave 1
    C06: 2 - 1 (from C04 removed) = 1   → still blocked (C05 not removed yet)
    C08: 1 - 0 = 1                     → still blocked (C09 not removed yet)
    C09: 1 - 1 (from C10 removed) = 0  → ready for Wave 1
    C18: 8 - 4 (C02, C03, C04, C10 removed) = 4  → still blocked (C05, C06, C08, C09 not removed)

WAVE 1 (in-degree 0 after Wave 0 removal):
  C05, C09  (2 packages)

  After removing Wave 1:
    C06: 1 - 1 (from C05 removed) = 0  → ready for Wave 2
    C08: 1 - 1 (from C09 removed) = 0  → ready for Wave 2
    C18: 4 - 2 (C05, C09 removed) = 2 → still blocked (C06, C08 not removed)

WAVE 2 (in-degree 0 after Wave 1 removal):
  C06, C08  (2 packages)

  After removing Wave 2:
    C18: 2 - 2 (C06, C08 removed) = 0 → ready for Wave 3

WAVE 3 (in-degree 0 after Wave 2 removal):
  C18  (1 package)

  All 20 packages accounted for: 15 + 2 + 2 + 1 = 20. ✓
```

---

## §2. The four waves

### Wave 0 — Leaves (15 packages, build immediately, in parallel)

**Members:** C01, C02, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20

**Rationale:** None of these 15 packages has a verified-in-code or verified-in-composer incoming edge from another Core package. They can all be built today, in parallel, without coordination.

**Subdivision by implementation status (for parallel-team planning):**

| Status | Packages | Recommended action |
|---|---|---|
| Already at depth 2 (shipped) | C01, C02, C03, C04, C10, C14, C16, C19 | Hold at depth 2; gate the depth-3 → depth-4 promotion on ADR-021 ratification |
| Not yet implemented | C07, C11, C12, C13, C15, C17, C20 | Begin implementation in parallel |

**Internal Wave-0 ordering recommendation for not-yet-implemented packages** (per blueprint-declared future edges, not currently verified):

Within Wave 0, the blueprint declares two **internal sequential chains** that should be respected even though they don't (yet) appear in verified code:

1. **SuperPHP chain** (C07 → C11 → C12): declared COMPILE edges C07→C11, C11→C12. Build in this order. The chain is a "free island" — nothing outside SuperPHP feeds into it (per `INDEX-VERIFY-3` finding); it can proceed entirely in parallel with the rest of Wave 0.
2. **CLI/Forge chain** (C13 → C20 with C17 also feeding C20): declared COMPILE edges C13→C20, C17→C20. Build order: C13 and C17 in parallel; C20 after both.

**Optional pre-reqs inside Wave 0 (do NOT block build, but enable features):**
- C02 is OPTIONAL upward for C03, C05, C08, C10, C14 (all in Wave 0). Without C02, these packages still build (they use PSR contracts in src/) but their boot-time integration into the application root is deferred.
- C10 is OPTIONAL upward for C16 (soft, APP_KEY loading). Without C10, C16 falls back to direct `addKey()` in tests.

### Wave 1 — First dependents (2 packages)

**Members:** C05 (Middleware), C09 (PSR-3 Logger)

**Rationale:** Both packages have exactly one verified-in-composer incoming edge from Wave 0:
- C05 ← C04 (Wave 0): middleware composer.json requires `sovereign-stack/core-http-message`
- C09 ← C10 (Wave 0): logger composer.json requires `sovereign-stack/core-config`

**Pre-existing-implementation note:** C05 and C09 are ALREADY at depth 2 (per `ARCHITECTURE_BASELINE.md` — both shipped v0.4.0). The wave computation confirms they were built in the correct topological order. The wave label is "computed from current verified edges", not "still needs to be built".

### Wave 2 — Second dependents (2 packages)

**Members:** C06 (Router), C08 (Error Handler)

**Rationale:**
- C06 has two incoming verified edges: from C04 (Wave 0, composer) and C05 (Wave 1, composer). Both predecessors are resolved by Wave 2's start.
- C08 has one incoming verified edge: from C09 (Wave 1, composer — error-handler composer.json requires `sovereign-stack/core-logger`). C09 is resolved at end of Wave 1.

**Pre-existing-implementation note:** C06 (v0.4.0) and C08 (v0.4.0) are ALREADY at depth 2. The wave computation confirms the order.

### Wave 3 — The sink (1 package)

**Members:** C18 (Kernel)

**Rationale:** C18 has 8 incoming verified edges (7 verified-in-code + 1 verified-in-composer for C04), all of which are now resolved. The kernel cannot ship at depth 2 until all 8 hard upward deps (C02, C03, C04, C05, C06, C08, C09, C10, plus C17 which is currently forward-declared via stub) are themselves at depth 2. **C17 is the missing prerequisite** — the kernel currently runs with a local stub (`Stub/ProviderRegistryInterface` + `Stub/EmptyProviderRegistry`), but to legitimately claim depth 2 the kernel must consume the real C17 implementation, not the stub.

**Pre-existing-implementation note:** C18 is ALREADY shipped at v0.4.0 with the stub, but its **depth-2 claim is CONDITIONAL** — until C17 lands, the kernel's boot-phase `registerAll()` + `bootAll()` calls are no-ops. The kernel's depth-2 badge in `ARCHITECTURE_BASELINE.md` should carry an asterisk: "depth 2 with stub C17; full depth 2 requires C17 implementation".

### Wave-3+ (no further waves)

C18 is the sink — no Core package depends on C18. Wave 3 is the terminal wave.

---

## §3. Wave summary table

| Wave | Packages | Count | Notes |
|---|---|---|---|
| 0 | C01, C02, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20 | 15 | All leaves; parallelizable; 8 already at depth 2; 7 not yet implemented |
| 1 | C05, C09 | 2 | Both already at depth 2 |
| 2 | C06, C08 | 2 | Both already at depth 2 |
| 3 | C18 | 1 | Already shipped with stub C17; full depth 2 requires C17 implementation |
| **Total** | | **20** | All packages accounted for |

---

## §4. Comparison vs `INDEX.md §5.3` (11-step global build sequence)

INDEX.md §5.3 has 11 steps. Steps 1-7 are Core-tier work; Step 8 is Hub tier; Step 9 is Bridge; Steps 10-11 are Spokes. The following analysis focuses on Steps 1-7 (the Core portion).

### §4.1 INDEX.md §5.3's 11-step sequence (verbatim)

| Step | Work items | Entry criteria | Exit criteria |
|---|---|---|---|
| 1 | CORE-02 (DI Container) | None — foundational leaf | Container compiles, autowires, detects cycles, passes PSR-11 suite |
| 2 | CORE-10, CORE-09, CORE-08 — parallelizable | Step 1 lands | All three pass CI; Config loads .env + JSON; Logging writes structured JSON; Error Handler converts all PHP errors to exceptions |
| 3 | CORE-18 (Kernel) | Steps 1-2 land | Kernel boots, runs boot-phase events via CORE-03, terminates cleanly |
| 4 | CORE-04 → CORE-05 → CORE-06 — sequential | Step 3 lands | A "Hello World" request completes the full pipeline |
| 5 | CORE-19, CORE-15, CORE-14, CORE-16 — parallelizable | Step 1 lands (Kernel not required) | Each passes CI; DBAL targets MySQL 8; Cache is PSR-6/16 over Redis 7; Filesystem abstracts local + S3; Encryption is AES-256-GCM + Argon2id |
| 6 | CORE-07 → CORE-11 → CORE-12 (SuperPHP) — sequential | None | SuperPHP renders a template with variables and conditionals |
| 7 | CORE-13, CORE-17, CORE-20 — parallelizable | Steps 1-3 land | CLI runs commands; providers register bindings; Forge scaffolds a new Hub service |
| 8 | Hub tier (30 blueprints — should be 31 per INDEX-VERIFY-3) | Steps 1-7 land | All 30 pass CI; HUB-15 reports all Hub services healthy |
| 9 | BRIDGE-01 (Vanguard) | Step 8 lands | Default-deny enforced; DTO transformation works; zero-exposure test passes |
| 10 | Internal Spokes 01-15, then 16-25 | Step 9 lands | All 25 pass CI; admin panel operational |
| 11 | External Spokes 01-15 | Step 9 lands | All 15 pass CI; public CMS serves traffic through the Bridge |

### §4.2 Top 3 discrepancies between computed waves and INDEX.md §5.3

#### Discrepancy #1 — §5.3 Step 2 falsely parallelizes C10 + C09 + C08; the actual verified order is C10 (Wave 0) → C09 (Wave 1) → C08 (Wave 2)

| §5.3 claim | Actual verified DAG |
|---|---|
| Step 2: "CORE-10, CORE-09, CORE-08 — parallelizable" | C10 is a leaf (Wave 0). C09 composer-requires C10 (verified-in-composer edge C10→C09). C08 composer-requires C09 (verified-in-composer edge C09→C08). So they MUST be built sequentially: C10 first, then C09, then C08. |
| Entry criterion: "Step 1 lands" | Step 1 (C02) is NOT a verified incoming edge for any of C10/C09/C08 — they're all leaves in the verified DAG (C10 is a leaf; C09's only verified incoming is C10; C08's only verified incoming is C09). The Step 1 entry criterion is over-cautious. |

**Impact:** §5.3's "2 weeks" estimate for Step 2 assumed three packages built in parallel. The verified DAG shows they form a sequential chain (C10 → C09 → C08) — they cannot be parallelized without breaking the composer-dep chain. Actual effort may be closer to 3 weeks if each takes ~1 week. This also means §5.3's "parallelizable" label (which ADR-014 retired but §5.3 retains — per INDEX-VERIFY-3 finding) is actively misleading in this step.

#### Discrepancy #2 — §5.3 Step 3 puts CORE-18 (Kernel) at Step 3, but the verified DAG shows Kernel is the sink (Wave 3) requiring 8 hard upward deps, only 3 of which (C02, C09, C08) are listed in Steps 1-2

| §5.3 claim | Actual verified DAG |
|---|---|
| Step 3: "CORE-18 (Kernel)" with entry criterion "Steps 1-2 land" | Kernel is in Wave 3. Its 8 verified-incoming COMPILE edges come from C02 (Wave 0), C03 (Wave 0), C04 (Wave 0), C05 (Wave 1), C06 (Wave 2), C08 (Wave 2), C09 (Wave 1), C10 (Wave 0). |
| Exit criterion: "Kernel boots, runs boot-phase events via CORE-03, terminates cleanly" | Kernel boots need C17 (Service Providers) at depth 2 — which §5.3 lists in Step 7, AFTER the kernel (Step 3). So the kernel is shipped at Step 3 with a stub C17, but its full depth-2 claim requires Step 7 to land first. |

**Impact:** §5.3 ships the kernel (Step 3) before C04/C05/C06 (Step 4), but the verified DAG shows C04, C05, C06 are upstream of C18 (kernel composer-requires core-http-message, kernel src-imports from core-middleware, kernel src-imports from core-router). So §5.3's Step 3 cannot actually compile in reality — the kernel package composer.json requires `sovereign-stack/core-http-message`, `sovereign-stack/core-middleware`, `sovereign-stack/core-router` which are listed in Step 4. The §5.3 ordering is **inverted** relative to the verified DAG. In practice, the DGLab team must have actually built C04/C05/C06 before or alongside C18 (which is consistent with their all-shipped-at-v0.4.0 status), but the §5.3 narrative is wrong.

#### Discrepancy #3 — §5.3 Step 5 puts CORE-15 (Cache) parallel with CORE-14, CORE-16, CORE-19, but the verified DAG shows C15 has NO incoming verified edges (it's a Wave 0 leaf), and C14→C15 is OPTIONAL (C15's blueprint declares "blocked on C14 only for hypothetical FileAdapter" — the core ArrayAdapter/RedisAdapter don't need C14)

| §5.3 claim | Actual verified DAG |
|---|---|
| Step 5: "CORE-19, CORE-15, CORE-14, CORE-16 — parallelizable" with entry criterion "Step 1 lands (Kernel not required)" | All four are Wave 0 leaves. They can be built in parallel from day 1, not gated on Step 1. |
| Implies C15 must wait for C14 (since they're grouped) | C15's blueprint explicitly says "core ArrayAdapter and RedisAdapter need no other Core-tier component" — C14 is only needed for a hypothetical future FileAdapter. So C15 can be built immediately, in parallel with C14 (or before it, if FileAdapter isn't in scope). |

**Impact:** §5.3's grouping of C14+C15+C16+C19 in Step 5 is correct that they're parallelizable, but the entry criterion "Step 1 lands" is over-cautious — none of them have verified incoming edges from C02 (they're all leaves). They can start in Wave 0, in parallel with the entire rest of the Core tier.

### §4.4 Additional discrepancies (not in the top 3, but worth flagging)

#### #4 — §5.3 Step 1 (CORE-02 alone) artificially serializes the Container

§5.3 Step 1 puts C02 alone with "1 week" estimate. The verified DAG shows C02 is one of 15 Wave-0 leaves — it can be built in parallel with C01, C03, C04, C07, C10, C11, C12, C13, C14, C15, C16, C17, C19, C20. Isolating C02 in Step 1 delays the start of all other Wave-0 work by 1 week. (In practice, the team built them in parallel; the §5.3 narrative is wrong.)

#### #5 — §5.3 Step 6 (SuperPHP chain as sequential) is correct in principle but unverifiable in current code

§5.3 Step 6 says "CORE-07 → CORE-11 → CORE-12 (SuperPHP) — sequential". The verified DAG has NO verified edges between C07, C11, C12 (none are implemented). The blueprint declares C07→C11 and C11→C12 as COMPILE, which would put C07 in Wave 0, C11 in Wave 1, C12 in Wave 2 *if those edges were verified*. Since they're not yet implemented, all three currently sit in Wave 0 (no verified incoming). When C07 lands, C11 should move to Wave 1; when C11 lands, C12 should move to Wave 2. The §5.3 ordering is *correct as future intent* but not enforced by current code.

#### #6 — §5.3 Step 7 puts CORE-13, CORE-17, CORE-20 parallel, but the verified DAG shows no edges between them (all Wave 0) — however, the blueprint declares C13→C20 and C17→C20 as hard COMPILE edges (unverified because unimplemented). Once C13 and C17 land, C20 should move from Wave 0 to Wave 1.

The §5.3 ordering is correct as future intent. The current code-state doesn't enforce it.

#### #7 — §5.3 Step 8 says "Hub tier (30 blueprints)" but should be 31 (per INDEX-VERIFY-3) — and now should be 32 (per ELQ-DECISIONS-RATIFY-6.5 — HUB-32 ratified today)

The §5.3 narrative is stale: it has the pre-HUB-31 count of 30, not the post-HUB-31 count of 31, and definitely not the post-HUB-32 count of 32. The wave-computation in this document doesn't include Hub-tier work (it stops at Core tier), but the tech lead should update §5.3 Step 8 to reflect 32 Hub blueprints.

### §4.5 Defect tally for INDEX.md §5.3 (Core-tier portion, Steps 1-7)

| Defect class | Count | Severity |
|---|---|---|
| Steps that artificially serialize parallel work | 2 (Step 1: C02 alone; Step 5 entry criterion "Step 1 lands") | LOW (cosmetic — actual practice already parallelizes) |
| Steps that claim parallelizability but the packages are actually sequential | 1 (Step 2: C10/C09/C08 are a sequential chain via composer deps, not parallelizable) | MEDIUM (would have led to broken composer install if literally followed) |
| Steps in WRONG order relative to the verified DAG | 1 (Step 3 kernel before Step 4 HTTP chain — but kernel composer-requires the HTTP chain) | HIGH (this would have broken composer install if literally followed) |
| Stale counts | 1 (Step 8 "30 blueprints" should be 32) | LOW (cosmetic) |
| Total Core-tier steps affected | 4 of 7 | — |

---

## §5. Honest Gaps

1. **Wave 0 is enormous (15 of 20 packages).** This is a real finding: 15 of 20 Core packages have NO verified incoming edges from other Core packages. This is because:
   - The PSR contracts (PSR-3, PSR-7, PSR-11, PSR-14, PSR-15, PSR-16) decouple consumers from concrete providers — most packages use the PSR interface, not the Core namespace.
   - The composer.json declarations are stronger than the blueprint's "Upward" listings, but even composer.json doesn't capture cross-package imports for 8 of 12 implemented packages (only C18, and partially C08/C09/C06/C05, actually import from sibling Core packages).
   - The Core tier is "loosely coupled at the contract level" by design — which means topological waves collapse to a small number of distinct levels. This is **good architecture** but it makes the wave-based build order less informative than expected.
2. **C17 is the single most critical unimplemented Core package.** It is declared hard upward by C18 (kernel) and C20 (forge), and forward-declared by a stub in the kernel. Until C17 lands, the kernel runs with a no-op stub — meaning boot-phase service provider registration doesn't actually happen. This is a **silent production gap**: the kernel's depth-2 badge is technically correct (unit tests pass) but the production behavior is degraded.
3. **The 28 OPTIONAL edges don't gate wave membership, but they DO gate depth-2 admission for full features.** Example: C16 (Encryption) is at depth 2 today, but its `KeyRegistry` reads `APP_KEY` from C10 (Config) at runtime — without C10 wired into the container, `KeyRegistry` falls back to direct `addKey()` calls (test-only path). So C16's depth-2 badge is "depth 2 in unit tests; depth 2 in production requires C10 integration". This nuance is not captured by the wave numbering.
4. **The SuperPHP chain (C07 → C11 → C12) is a "free island" in the DAG.** Nothing outside SuperPHP feeds into the chain, and the chain feeds only into C18 (for boot-time bootstrap of the template engine) and C20 (for `superphp:compile` warm-up). Once C07 lands, C11 follows immediately; once C11 lands, C12 follows immediately. This is the cleanest parallel-track work in the entire Core tier — three packages, three sequential mini-waves, no dependencies on anything else.
5. **The composer.json vs src/ asymmetry is significant.** Of the 12 implemented Core packages, only 1 (C18 Kernel) actually imports from sibling Core namespaces in src/. The other 11 use only PSR contracts in src/. This means the "verified-in-code" edge count (7) is much smaller than the "verified-in-composer" edge count (6) which is much smaller than the "declared-in-blueprint" edge count (45). The DAG in §4 of `CORE-DEPENDENCY-DAG.md` shows all 45; the wave computation uses only the 13 verified-in-code-or-composer. **The 32 unverified-but-declared edges are real architectural intent** — they describe the assembled-application state, not the package-isolation state. The tech lead should decide whether the ADR-021 build order uses the 13-edge strict-verified DAG (gives 4 waves) or the 45-edge blueprint-declared DAG (would give more waves with deeper sequencing).
6. **C15 (Cache) and C17 (Service Providers) are the two Wave-0 unimplemented packages with the most downstream impact.** C15's downstream consumers include H02, H04, H07 (Rate Limiter), plus core-tier C06/C09/C18 (caching compiled routes, handler-rotation state, provider manifest). C17's downstream consumers include every Hub and Spoke service provider. Until both land, the Hub tier cannot reach its full depth-2 readiness (several Hubs are blocked on C15 specifically — H02, H04 sessions, H07 rate limiter).
7. **C20 (Dev CLI/Forge) is the only Core package whose `forge:make:hub` and `forge:make:spoke` commands are needed for Hub/Spoke tier development.** Until C20 lands, every new Hub/Spoke package must be hand-scaffolded. C20's hard upward deps (C13 + C17) are also unimplemented — so the entire "developer ergonomics" track (C13 → C17 → C20) is blocked.

---

## §6. Recommended build order for the next 3 sprints (derived from waves)

### Sprint 1 — Wave 0 unimplemented + critical-path stub closure

**Focus:** Land the 7 unimplemented Wave-0 packages in parallel, with priority on C17 (blocks C18's full depth-2) and C15 (blocks H02/H04/H07 Hub-tier work).

| Priority | Package | Rationale |
|---|---|---|
| 🔴 Critical | C17 (Service Providers) | C18 runs with stub today; full depth-2 requires real C17 |
| 🔴 Critical | C15 (Cache) | Blocks H02 Hub Cache (which blocks H04 sessions + H07 rate limiter) |
| 🟡 High | C13 (CLI Engine) | Hard upward of C20; needed for all Hub-tier management commands |
| 🟡 High | C20 (Dev CLI/Forge) | Hard upward of C13+C17; needed for Hub/Spoke scaffolding (every new Hub package starts here) |
| 🟢 Medium | C07 → C11 → C12 (SuperPHP chain) | Sequential mini-waves inside Wave 0; only blocks H12 (Newsletter) + H26 (UI Elements); can run as a side-track |
| 🟢 Medium | C15 already covered above | — |

### Sprint 2 — Wave 0 polish + Wave 1/2 hardening

| Priority | Package | Rationale |
|---|---|---|
| 🟢 Hold | C01, C02, C03, C04, C10, C14, C16, C19 | Already at depth 2 — gate depth-3 promotion on ADR-021 |
| 🟢 Hold | C05, C09 (Wave 1), C06, C08 (Wave 2) | Already at depth 2 — gate depth-3 promotion on ADR-021 |
| 🟢 Hold | C18 (Wave 3) | Already shipped with stub — gate full depth-2 on C17 landing |

### Sprint 3 — Wave 3 full depth-2 claim + Hub tier parallel start

Once C17 lands (Sprint 1), C18 can replace its Stub with the real C17 implementation and claim full depth-2. With all 20 Core packages at depth 2, the Hub tier can start its Tier-A Hubs (per `CORE-CAPABILITY-DAG.md` §7): H16 (Hub Weaver) immediately, then H03, H08, H19, H20, H31 in parallel. H32 (AI Inference Hub) can start as soon as its blueprint is authored (all its Core deps C02/C09/C10/C16/C18/C19 are at depth 2).

---

## §7. ADR-021 recommendations

1. **Replace INDEX.md §5.3's 11-step global sequence with per-tier wave computation.** The 4-wave Core computation in this document should be the Core-tier portion of ADR-021; the Hub-tier wave computation should be a separate document (`HUB-BUILD-ORDER.md`, future task); the Bridge, Spoke, and Deploy tiers each get their own wave document.
2. **Drop the "parallelizable" labels entirely** — per ADR-014 they're retired, and per the wave computation, ALL packages within a wave are by definition parallelizable. The "parallelizable" label was a workaround for a single monolithic sequence; with per-tier waves, the parallelism is implicit.
3. **Update §5.3 Step 8's "30 blueprints" count to 32** (post-HUB-31 + post-HUB-32 ratification). Note that HUB-32's blueprint doesn't exist yet — it should be authored as part of the un-deferred ELQ implementation phase.
4. **Update INDEX.md §5.2's Core-edge block to use the 45-edge declared DAG** from `CORE-DEPENDENCY-DAG.md` §4 (corrected direction for C18→C06, C15→C14, C17→C13, C20→C13; removed C16→C15 [Hub-tier edge]; added 11 missing edges including C04→C18, C04→C06, C09→C08, C10→C09, C17→C18, etc.).
5. **Add a per-tier "depth-2 conditional" annotation** to packages whose depth-2 badge is conditional on a stub (C18 conditional on C17 real implementation; C16 conditional on C10 wiring; etc.). The current `ARCHITECTURE_BASELINE.md` doesn't surface these conditions.
6. **Track the C04↔C05 namespace collision** (`SovereignStack\Core\Http\` shared between two packages) as an ADR-021 consideration — splitting the namespace is a breaking change requiring coordinated minor-version bumps across C04, C05, and all consumers (C18, future HUB-08, BRIDGE-01).

---

*End of CORE-BUILD-ORDER.md. See sibling documents `CORE-DEPENDENCY-DAG.md` (typed-edge DAG) and `CORE-CAPABILITY-DAG.md` (capability edges).*
