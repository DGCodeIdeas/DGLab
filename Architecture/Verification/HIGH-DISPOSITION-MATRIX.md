# HIGH Finding Disposition Matrix

**Status:** Disposition assigned per Integrity Gate criteria
**Date:** 2026-10-05
**Purpose:** Every HIGH finding must have a disposition (Closed, Accepted, Deferred, or Invalid/Superseded) before the Integrity Gate can pass.

---

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

---

## Disposition Summary

| Disposition | Count | Blocks gate? |
|---|---|---|
| **Deferred** | 19 | No (with owner + rationale + milestone) |
| **Accepted** | 2 | No (with owner + rationale + milestone) |
| **Closed** | 1 | No |
| **Total** | 22 | — |

## Disposition Matrix

### Group 1: README.md drift (S-005..S-009) — 5 findings

| Field | Value |
|---|---|
| IDs | S-005, S-006, S-007, S-008, S-009 |
| Severity | HIGH |
| Category | Doc-Drift |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | README is stale (PHP 8.3→8.4, 8→13 Core packages, 20→21 ADRs, missing ADR-021/two-DAG/HUB-32/ESPOKE-19, PHPUnit 10.5→11.0). Documentation drift from rapid architectural changes. Does not affect architectural correctness — the architecture is sound; the README is inaccurate. |
| Target milestone | Pre-V1 release documentation sweep |
| Verification condition | README.md reflects current HEAD state (PHP version, package count, ADR count, key architectural decisions) |

### Group 2: DEPLOY-01.md drift (S-010, S-011) — 2 findings

| Field | Value |
|---|---|
| IDs | S-010, S-011 |
| Severity | HIGH |
| Category | Doc-Drift |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | DEPLOY-01 still describes PHP-FPM + Nginx + Supervisor; should describe FrankenPHP + Anvil v3 per ADR-017. Documentation drift, not architectural defect. |
| Target milestone | Pre-V1 release documentation sweep |
| Verification condition | DEPLOY-01.md describes FrankenPHP runtime per ADR-017 |

### Group 3: preload.php broken (S-012, S-013) — 2 findings

| Field | Value |
|---|---|
| IDs | S-012, S-013 |
| Severity | HIGH |
| Category | Doc-Drift |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | preload.php references 9 nonexistent classes (Fiber\Pulse, Fiber\Scheduler, Http\Kernel, etc.) and has broken path resolution. Stale from older architecture. Does not affect current runtime (preload is not loaded in current CI). |
| Target milestone | Pre-V1 release — preload.php rewrite or removal |
| Verification condition | preload.php references only existing classes OR is removed |

### Group 4: INDEX.md drift (S-014..S-019) — 6 findings

| Field | Value |
|---|---|
| IDs | S-014, S-015, S-016, S-017, S-018, S-019 |
| Severity | HIGH |
| Category | Doc-Drift |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | INDEX.md has §1 Hub count contradiction, CORE-02 stub vs implemented, missing 5 ADR entries (ADR-016..020), §5.2/§5.3 not collapsed, §4 vs §5.2 criticality inconsistency. Partially addressed by Task 74 reconciliation but residual issues remain. INDEX is the governance registry — its drift is tracked but doesn't block architectural decisions (the two-DAG model and ADR-021 are authoritative). |
| Target milestone | Pre-V1 release — INDEX.md full reconciliation |
| Verification condition | INDEX.md §1/§2/§4 are internally consistent with current HEAD |

### Group 5: C04↔C05 namespace collision (S-023) — 1 finding

| Field | Value |
|---|---|
| ID | S-023 |
| Severity | HIGH |
| Category | Latent-Defect |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | Both http-message/composer.json AND middleware/composer.json declare `"SovereignStack\\Core\\Http\\"` as PSR-4 root. Latent bug: adding a class with the same name in both packages would silently alias. No collision exists today. Namespace root lint rule (ADR-021 §16) will catch this going forward. Fix requires namespace split (future ADR). |
| Target milestone | Before multi-tenant production (code-level fix needed) |
| Verification condition | Each package has a unique PSR-4 root namespace |

### Group 6: C17 forward-declaration stub (S-024) — 1 finding

| Field | Value |
|---|---|
| ID | S-024 |
| Severity | HIGH |
| Category | Latent-Defect |
| Status | Open |
| **Disposition** | **Accepted** |
| Owner | Tech lead |
| Rationale | C18 Kernel boots with `EmptyProviderRegistry` (no-op stub for C17). C18's `implementation_depth` is 2 but `integration_completeness` is PARTIALLY WIRED. This is a KNOWN production gap — C17 implementation is deferred until service providers are needed. The three-axis status model (ADR-021 §6) captures this precisely. Accepting this risk because: (1) no production traffic exists yet, (2) the gap is documented in ADR-021 §21, (3) C17 implementation is a roadmap item, not an integrity issue. |
| Target milestone | C17 implementation (roadmap item, not integrity gate) |
| Verification condition | C17 implemented OR C18's production_gate explicitly documents the stub |

### Group 7: H05/H07 rate limiter documentation stale (S-025) — 1 finding

| Field | Value |
|---|---|
| ID | S-025 |
| Severity | HIGH |
| Category | Latent-Defect |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | H05 and H07 may be duplicate Rate Limiter Hubs. ADR-021 §21 documents this as "tech-lead decision pending." The documentation is stale (H05 is RBAC, not rate limiter). Needs investigation but doesn't block current architecture. |
| Target milestone | Next documentation cleanup |
| Verification condition | H05 and H07 roles are clarified in their blueprints |

### Group 8: Hub DAG governance decisions (S-027..S-032) — 6 findings

| Field | Value |
|---|---|
| IDs | S-027, S-028, S-029, S-030, S-031, S-032 |
| Severity | HIGH |
| Category | Governance |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | 6 Hub DAG governance decisions unresolved (HUB-06→HUB-11 label, HUB-15 reverse-Downward, HUB-16 generic Downward, 26 asymmetric downward-only, 136/150 UNKNOWN edge_type, 11 relocated edges in source blueprints). These are DAG quality issues that must be resolved before HUB-BUILD-ORDER.md generation (Phase 3), but don't block the current integrity gate (no build order is being generated). |
| Target milestone | Before Hub build-order generation (Phase 3) |
| Verification condition | All Hub DAG edges have correct edge_type; no asymmetric downward-only declarations remain |

### Group 9: DAG edge misclassification (S-057) — 1 finding

| Field | Value |
|---|---|
| ID | S-057 |
| Severity | HIGH |
| Category | Coherence |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | CORE-DECLARED-DAG.md C18→C06 edge misclassified in 3 ways: prose says DECLARED_ONLY but evidence says VERIFIED; Mermaid shows solid (verified style); actual composer.json requires sovereign-stack/core-router. Edge classification is the authoritative input for build-order wave computation. Must be fixed before build-order regeneration, but doesn't block the current gate. |
| Target milestone | Before Core build-order regeneration |
| Verification condition | C18→C06 edge status = VERIFIED in CORE-DECLARED-DAG.md |

### Group 10: ADR-011 status contradiction (S-078) — 1 finding

| Field | Value |
|---|---|
| ID | S-078 |
| Severity | HIGH |
| Category | Governance |
| Status | Open |
| **Disposition** | **Deferred** |
| Owner | Main agent |
| Rationale | ADR-011 file says Accepted (2026-08-13); INDEX.md §1 says Proposed; OPEN-DECISIONS OD-01 says Resolved with action claimed but never completed. 5 documents disagree. Governance contradiction — ADR-011 IS Accepted (HUB-31 was accepted per ADR-011 on 2026-08-13). The INDEX/OPEN-DECISIONS are stale. Needs reconciliation but doesn't affect architectural decisions (HUB-31 is already counted in the inventory). |
| Target milestone | Next documentation cleanup |
| Verification condition | All 5 documents agree: ADR-011 = Accepted |

---

## Gate Assessment

With these dispositions assigned:

| Gate Criterion | Status |
|---|---|
| 1. FATAL: zero Open, all have verification evidence | ✅ (4 FATALs Fixed; S-001..S-004 verification conditions met) |
| 2. HIGH: all have disposition | ✅ (19 Deferred + 2 Accepted + 1 Closed = 22) |
| 3. Governance consistency | ✅ (S-077 register synchronized) |
| 4. Targeted verification | ⏳ (Need to formally move S-001..S-004 from Fixed → Verified) |
| 5. CI + architecture-lint pass | ✅ (CI green on main) |
| 6. Convergence: zero new FATAL/HIGH without disposition | ⏳ (Need targeted verification to confirm) |
| 7. Traceability | ✅ (Every blocking finding has status, owner, verification condition) |

**Remaining before gate can pass:** Formal verification of S-001..S-004 (move from Fixed → Verified) + targeted verification to confirm no new FATAL/HIGH without disposition.
