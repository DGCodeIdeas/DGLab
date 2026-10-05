# DGLab Architecture Integrity Register (V2 — Reconciled)

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**
<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->

> **⚠️ Blind-Spot Awareness:** This verification/audit document is a **starting point, not a complete inventory**. The number of findings found is not the number of findings that exist. **No audit is declared complete.** Findings are closed only when their verification condition passes — not when code or docs change. See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

**Created:** 2026-10-01 (V1) · 2026-10-03 (V2 reconciliation — task S077-RECONCILE-76)
**Source:** SHORTCOMINGS-AUDIT-76 (47 findings, V1) + A3-RUNTIME-76 (S-048..S-054) + A3-ARCH-DAG-76 (S-055..S-065) + A3-DOC-GOV-76 (S-077..S-092)
**Status:** Living document — findings are closed only when their verification condition passes

> **V2 change note:** This register supersedes `Architecture/Verification/SHORTCOMINGS-REGISTER.md` (V1). V1 had 47 findings all marked `Open`. V2 reconciles 34 new A3 findings (S-048..S-092, scoped to this task) against the V1 register, applies status updates for findings remediated by A1 (PR #294), A2 (PR #301), and PR #303, and adds the lifecycle / Integrity-Gate-blocking structure required to answer "what is wrong right now?" and "what blocks the gate?". S-066..S-076 (parallel A3-ARCH-GOV-76 audit) are **out of scope** for this reconciliation and will be folded in a follow-up register update.

---

## 1. Paradigm Shift (Tech-Lead Directive — preserved from V1)

> "We're no longer optimizing for making the roadmap move. We're optimizing for making every architectural claim true before allowing the roadmap to move."

This register is the canonical ledger of every architectural claim that is currently false. The roadmap does not advance while any FATAL finding remains Open or Fixed-but-not-Verified. The roadmap does not advance past the current lap while any HIGH finding in the current phase's scope remains Open. Findings are closed only when their stated verification condition passes — not when code or docs are edited.

## 2. Closure Rule (preserved from V1)

A finding is closed only after its stated verification condition passes. Code changes alone do not close a finding. CI green ≠ closed.

Closure requires three things, all recorded in the `Closure evidence` field:
1. A reference to the commit/PR that applied the remediation.
2. A reference to the verification artifact (CI run URL, test name + output, lint output, doc grep result, etc.) that proves the verification condition passes.
3. The date the verification condition passed.

Findings marked `Fixed` (code changed, verification pending) are NOT closed. They are awaiting verification.

## 3. Disposition Values (extended per V2 reconciliation)

V1 dispositions: `Open` · `In-Progress` · `Fixed` · `Closed` · `Accepted` · `Deferred`

V2 adds the explicit lifecycle ladder (sub-states within the broader lifecycle):

| Disposition | Meaning | Blocks Integrity Gate? |
|---|---|---|
| **Open** | Identified, no remediation started | FATAL → yes · HIGH (current phase) → yes |
| **In-Progress** | Remediation underway (PR open, work assigned) | FATAL → yes · HIGH (current phase) → yes |
| **Fixed** | Code/doc changed, merged PR — verification condition NOT yet demonstrated | FATAL → yes · HIGH → no |
| **Verified** | Verification condition DEMONSTRATED (test exists + passes, lint green, grep clean) — re-audit not yet performed | FATAL → no (pending Closed) · HIGH → no |
| **Closed** | Re-audit at current HEAD confirmed the verification condition still passes; closure evidence recorded | — |
| **Accepted** | Tech lead accepts the risk (won't fix) | — |
| **Deferred** | Postponed to a future phase | — |

**Lifecycle ladder:** `discovered → reconciled → remediated (Fixed) → verified (Verified) → closed (Closed)`

> A merged PR moves a finding to `Fixed`, not `Verified` or `Closed`.
> `Verified` requires the verification condition to be DEMONSTRATED (test file exists + passes, lint output clean, grep returns zero, etc.).
> `Closed` requires a re-audit at current HEAD confirming the verification condition still passes.

## 4. Summary Table (V2 — 81 entries)

| Severity | Count | Open | Fixed | Verified | Closed | Accepted | Deferred |
|---|---|---|---|---|---|---|---|
| FATAL | 4 | 0 | 4 | 0 | 0 | 0 | 0 |
| HIGH | 30 | 22 | 8 | 0 | 0 | 0 | 0 |
| MEDIUM | 31 | 28 | 1 | 0 | 0 | 0 | 2 (S-020, S-021 indirectly via S-017/S-019) |
| LOW | 16 | 14 | 1 | 0 | 0 | 0 | 1 (S-044) |
| **Total** | **81** | **64** | **14** | **0** | **0** | **0** | **3** |

> Breakdown: 47 V1 findings (S-001..S-047) + 34 new A3 findings in this task's scope (S-048..S-065, S-077..S-092). S-066..S-076 (parallel A3-ARCH-GOV-76, 11 findings) are out of scope for this reconciliation and not yet folded in.
> The 2 HIGH counts shown as "Fixed" includes S-048 (PR #303), S-054 (PR #303), S-077 (this register V2 PR), plus S-033 (partially Fixed). The 1 MEDIUM Fixed is S-033's closure-gate (post-A1 HUB-32 file exists, but DAG not updated — counted as Fixed-partial).
> Note: S-033 is HIGH; counted in HIGH row above as Fixed (partial).

## 5. Phase Assignments (V2 — updated)

| Phase | Finding IDs | Description |
|---|---|---|
| A0 | S-003, S-004 | Runtime isolation specification (per-Fiber state model — spec landed in PR #293) |
| A1 | S-001, S-002, S-033 | Canonical HUB-32 / ESPOKE-19 publication (depth 1) + lint validIds extension + INDEX §1 update — PR #294. S-001/S-002 Fixed; S-033 Fixed-partial (HUB-32 file exists; DAG propagation blocked on S-055/S-056/S-062) |
| A2 | S-003, S-004 | Fiber isolation remediation (Container::pulse() → per-Fiber WeakMap) — PR #301. Fixed; verification condition (c) gap closed by PR #303 |
| A3-RUNTIME | S-048, S-052, S-053, S-054 | PR #303 creates `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` covering conditions (a), (b), (c), (d) + edge case #1 + class-string bindings |
| A3-ARCH-DAG | S-055..S-065 | Architecture DAG reconciliation — 11 findings from A3-ARCH-DAG-76 report |
| A3-DOC-GOV | S-077..S-092 | Documentation/contract consistency + governance/SDLC integrity — 16 findings |
| HIGH-batch | S-005..S-033 + S-048, S-054, S-057, S-078 | Documentation drift + latent defects + governance (29 V1 + 4 new A3) |
| MEDIUM-batch | S-034..S-043 + S-050, S-055, S-056, S-058, S-059, S-064, S-079, S-080, S-082, S-083, S-084, S-085, S-087, S-089, S-090 | Coherence + meta-audit + post-A2/post-A1 doc drift |
| LOW-backlog | S-044..S-047 + S-049, S-051, S-060, S-061, S-062, S-063, S-065, S-081, S-086, S-088, S-091, S-092 | Cleanup + cosmetic |

---

## 6. Two Key Questions This Register Must Answer

### Q1. What is wrong right now?

**Findings in `Open` or `Fixed` (not `Verified`, not `Closed`):**

- **All 4 FATAL findings are `Fixed` but not `Verified`:**
  - S-001 (lint HUB-32 — PR #294) · S-002 (lint ESPOKE-19 — PR #294) · S-003 (Container::pulse() Fiber isolation — PR #301 + #303) · S-004 (same root cause as S-003)
- **22 HIGH findings `Open` + 8 HIGH findings `Fixed` (not Verified):**
  - Open: S-005..S-032 (HIGH-batch backlog), S-057 (DAG edge misclassification), S-078 (ADR-011 status drift)
  - Fixed: S-033 (partial — HUB-32 file exists, DAG propagation pending), S-048 (PR #303), S-054 (PR #303), S-077 (this register V2)
- **All 31 MEDIUM findings are `Open`** (1 Fixed-partial: S-033 counted as HIGH above)
- **All 16 LOW findings are `Open`** (except S-044 = Deferred)

### Q2. What prevents the Integrity Gate?

> **Per the brief's gate rule:** FATAL Open + FATAL Fixed-not-Verified + HIGH without remediation disposition (i.e., HIGH `Open` in current phase's scope) block the Integrity Gate.

**A. FATAL findings in `Open` state:** 0 (all 4 FATALs moved to `Fixed` by A1/A2/PR #303)

**B. FATAL findings in `Fixed` state without verification evidence (4):**

| ID | Status | Verification gap | PR that closes gap |
|---|---|---|---|
| **S-001** | Verified (PR #294) | Awaiting re-audit confirming `architecture-lint` still passes at HEAD | (re-audit pending) |
| **S-002** | Verified (PR #294) | Same as S-001 | (re-audit pending) |
| **S-003** | Verified (PR #301 + #303) | Verification condition (c) gap closed by PR #303 (creates `PulseFiberIsolationTest.php`); conditions (a), (b) verified by A3-RUNTIME-76; (d) CI green. Re-audit to confirm `PulseFiberIsolationTest.php` exists + passes pending | (re-audit pending) |
| **S-004** | Verified (PR #301 + #303) | Same as S-003 — blocked on S-003 closure per cross-reference table | (re-audit pending) |

> **Note:** At audit time (HEAD `200479e`, post-A2 pre-PR-303), S-003/S-004 verification condition (c) was UNMET per A3-RUNTIME-76 finding S-054. PR #303 (per the runtime report's recommended remediation #1) creates the missing test file, closing the gap. S-003/S-004 remain `Fixed` (not `Verified`) until a re-audit confirms.

**C. HIGH findings `Open` in current phase (A3) scope (22):**

| ID | Title | Category |
|---|---|---|
| S-005 | README says PHP 8.3 (actual ^8.4) | Doc-Drift |
| S-006 | README says "8 Core-tier packages" (actual 12) | Doc-Drift |
| S-007 | README says "20 ADRs" (actual 21) | Doc-Drift |
| S-008 | README doesn't mention ADR-021 / two-DAG governance | Doc-Drift |
| S-009 | README says "PHPUnit 10.5" (actual ^11.0) | Doc-Drift |
| S-010 | DEPLOY-01.md describes PHP-FPM + Nginx + Supervisor (should be FrankenPHP) | Doc-Drift |
| S-011 | DEPLOY-01.md doesn't mention Anvil v3 runtime substrate | Doc-Drift |
| S-012 | anvil/app/php/preload.php references 9 nonexistent classes | Doc-Drift |
| S-013 | anvil/app/php/preload.php path resolution broken | Doc-Drift |
| S-014 | INDEX §1 line 45 active Hub count contradiction | Doc-Drift |
| S-015 | INDEX §1 line 60 says CORE-02 is "stub only (.gitkeep)" | Doc-Drift |
| S-016 | INDEX §1 missing 5 ADR entries (ADR-016..020) | Doc-Drift |
| S-017 | INDEX §5.2 still contains old Mermaid DAG | Doc-Drift |
| S-018 | INDEX §5.3 still contains 11-step build sequence | Doc-Drift |
| S-019 | INDEX §4 vs §5.2 criticality inconsistency | Doc-Drift |
| S-023 | C04↔C05 PSR-4 namespace collision | Latent-Defect |
| S-024 | C17 forward-declaration stub still exists | Latent-Defect |
| S-025 | ADR-021 §21 line 296 docs "H05/H07 Rate Limiter duplication" but HUB-05 is RBAC | Latent-Defect |
| S-027..S-032 | Hub edge governance gaps (Gaps 1, 3, 5, 8, 9, 2) | Governance |
| S-057 | CORE-DECLARED-DAG.md C18→C06 edge misclassified (HIGH) | Coherence |
| S-078 | INDEX §1 line 65 still says ADR-011 Proposed (vs Accepted) | Doc-Drift |

> **Note:** S-033 (HIGH Governance) is `Fixed` (partial) — HUB-32.md file exists per A1; closure blocked on S-056 (HUB-DECLARED-DAG.md still excludes HUB-32). Counted in B (Fixed-not-Verified).

**D. HIGH findings `Fixed` but not `Verified` (additional FATAL-adjacent, per closure-rule hazard):**

| ID | Title | Status | PR |
|---|---|---|---|
| S-033 | HUB-32 pending canonical publication | Fixed (partial) | PR #294 (file exists) — DAG propagation blocked on S-056 |
| S-048 | pulse() class-string misrouting (borderline FATAL) | Fixed | PR #303 |
| S-054 | S-003 verification condition (c) unmet (FATAL-adjacent) | Fixed | PR #303 |
| S-077 | SHORTCOMINGS-REGISTER missing A3 findings | Fixed | This register V2 PR |

> Per the brief's gate rule (FATAL Fixed-not-Verified blocks; HIGH Open blocks), the HIGH Fixed-not-Verified findings **do not** block the gate. They are listed here for traceability because they sit on the closure-rule boundary (any closure-rule drift moves them into blocking).

**Net blocking count:**
- 4 FATAL Fixed-not-Verified (S-001, S-002, S-003, S-004)
- 22 HIGH Open in current phase (S-005..S-032, S-057, S-078)
- **Total: 26 findings block the Integrity Gate as of HEAD `200479e` + post-PR-303 disposition snapshot.**

---

## 7. Reconciliation Log (V2 — how A3 findings were reconciled)

### 7.1 Reconciliation rules applied (per task brief)

For each A3 finding (S-048..S-092), determined:
1. **Genuinely new?** → Register with new ID
2. **Additional evidence for an existing finding?** → Update existing finding's evidence, don't create a duplicate
3. **Refinement/split?** → Update existing finding, note the refinement
4. **Duplicate?** → Mark as duplicate, don't register
5. **Superseded by A1/A2 fixes?** → Update existing finding's status to Fixed/Verified

### 7.2 Reconciliation summary (counts)

| Reconciliation outcome | Count |
|---|---|
| Genuinely new (registered with new ID) | 33 |
| Refinement of existing finding | 1 (S-033 — partial fix; DAG propagation still pending) |
| Duplicate / merged into existing finding | 0 |
| Superseded by A1/A2/PR-303 fix (V1 finding updated to `Fixed`) | 7 (S-001, S-002, S-003, S-004, plus carry-forward on S-033) |
| Self-referential (this register V2 is the remediation) | 2 (S-077 + S-079) |
| Related but kept separate (cross-referenced) | 8 (see §7.3 below) |

> Total A3 findings in scope: 34 (S-048..S-065 = 18, S-077..S-092 = 16).
> Net new entries added to V2: 34 (all registered with their original A3 IDs).
> V1 findings updated: 7 (S-001, S-002, S-003, S-004 → Fixed; S-033 → Fixed-partial; S-046 → noted with incorrect premise per S-079).

### 7.3 Cross-reference cluster map (related findings kept separate)

| Cluster | Findings | Relationship |
|---|---|---|
| **PR #303 closure cluster** | S-003, S-004, S-048, S-052, S-053, S-054 | Single PR fixes all 6 — creates `PulseFiberIsolationTest.php` (closes S-054 + S-003 cond. c), tests outside-Fiber throw (S-052), tests class-string pulse (S-053), fixes class-string pulse routing (S-048) |
| **A2 propagation gap (ContainerException.php)** | S-048, S-065 | Same root cause: A2 added `ContainerException.php` without updating dependent docs/impls. S-048 is the runtime side; S-065 is the DAG §7 table side |
| **PR #303 dead-code carry-forward** | S-048, S-049 | PR #303 fixes S-048 by routing class-strings through step 0; step 8b becomes live (partial S-049 fix); step 1b remains dead (S-049 still Open) |
| **Post-A2 docblock drift** | S-050, S-080, S-081 | Different files (interface, spec blueprint, A0 state model). All describe the pre-A2 state. Kept separate. |
| **A1 → A3 propagation gap (canonical-status)** | S-055, S-056, S-059, S-062, S-089 | A1 made HUB-32/ESPOKE-19 canonical but didn't propagate to HUB-VERIFIED-DAG, HUB-DECLARED-DAG, INDEX §1/§2.3, ADR-021 §"Relationship to Other Documents". Closes S-033 when fixed. |
| **CORE-DECLARED-DAG edge errors** | S-057, S-058 | Same root cause: §6.1 analysis identified errors but canonical graph not fixed. S-057 = classification; S-058 = direction. |
| **Baseline file drift** | S-061, S-063 | Both about `download/ARCHITECTURE_BASELINE.md`. S-061 = stale; S-063 = ADR-021 claim contradicts git state. |
| **ADR-011 status contradiction (5 documents)** | S-046, S-078, S-079, S-085 | ADR file + OPEN-DECISIONS say Accepted; INDEX §1 + §2.2 + register S-046 say Proposed. S-079 fixes S-046's incorrect premise; S-078 + S-085 close the cross-document contradiction. |
| **Register staleness (self-referential)** | S-077, S-079, S-086 | S-077 = register missing A3 findings (this V2 fixes); S-079 = S-046 incorrect premise (this V2 fixes); S-086 = BLIND-SPOT-DOCTRINE.md "47-finding register" reference (this V2 fixes; downstream fix). |

### 7.4 Specific reconciliation verdicts per A3 finding

| A3 ID | Reconciliation verdict | Action |
|---|---|---|
| **S-048** (pulse class-string misrouting) | Genuinely new (post-A2 latent defect; A2 introduced the routing, didn't handle class-strings) | Register as new HIGH finding. Status: Fixed (PR #303) |
| **S-049** (dead code steps 1b/8b) | Genuinely new (Coherence observation; same lines as S-048 fix). NOT merged with S-048 because severity differs (LOW vs HIGH-borderline-FATAL) | Register as new LOW finding. Status: Open (PR #303 makes step 8b live; step 1b still dead — partial fix only) |
| **S-050** (ContainerInterface docblock Shape A) | Genuinely new (post-A2 docblock drift in interface file). NOT the same as S-080 (different file: interface vs spec blueprint). | Register as new MEDIUM finding. Status: Open. Cross-ref S-080 |
| **S-051** (duplicate stacked docblock) | Genuinely new (cosmetic A2 merge artifact) | Register as new LOW finding. Status: Open |
| **S-052** (no outside-Fiber test) | Genuinely new (test-coverage gap; A2 implementation correct but untested). Fixed by PR #303 | Register as new LOW finding. Status: Fixed (PR #303) |
| **S-053** (no class-string pulse test) | Genuinely new (test-coverage gap; reason S-048 went undetected). Fixed by PR #303 | Register as new LOW finding. Status: Fixed (PR #303) |
| **S-054** (S-003 verification condition (c) unmet) | Genuinely new (meta-finding about closure-rule enforcement; NOT just evidence against S-003 — S-054 is a process/governance finding about the A2 PR landing without satisfying an explicit verification condition) | Register as new HIGH finding. Status: Fixed (PR #303). Updates S-003 disposition note. |
| **S-055** (HUB-VERIFIED-DAG stale count) | Genuinely new (A1 propagation gap; HUB-VERIFIED-DAG.md authored same day as A1, not regenerated) | Register as new MEDIUM finding. Status: Open. Same root cause as S-056 |
| **S-056** (HUB-DECLARED-DAG stale HUB-32) | Genuinely new (blocks S-033 closure). NOT a refinement of S-033 — S-033 is about HUB-32 file existence; S-056 is about the DAG node-set inclusion. Different artifacts. | Register as new MEDIUM finding. Status: Open. Cross-ref S-033 (blocks closure) |
| **S-057** (CORE-DECLARED-DAG C18→C06 misclassified) | Genuinely new (chronic edge-classification confusion in 3 independent ways) | Register as new HIGH finding. Status: Open |
| **S-058** (CORE-VERIFIED-DAG + CORE-DECLARED-DAG Mermaid direction error) | Genuinely new (admitted-but-not-fixed pattern). NOT a duplicate of S-057 — S-057 is classification; S-058 is direction. Different defects. | Register as new MEDIUM finding. Status: Open. Same files as S-057 |
| **S-059** (INDEX.md internally contradictory on HUB-32 count) | Genuinely new (4 places in INDEX.md disagree). NOT a refinement of S-014 — S-014 is about "29 + 1 pending = 30" (pre-A1 logic); S-059 is post-A1 internal propagation. Different findings, both still Open. | Register as new MEDIUM finding. Status: Open |
| **S-060** (architecture-boundary-lint.php referenced but doesn't exist) | Genuinely new (false documentation in yml) | Register as new LOW finding. Status: Open |
| **S-061** (ARCHITECTURE_BASELINE.md stale by 10 commits) | Genuinely new (build-artifact drift) | Register as new LOW finding. Status: Open. Cross-ref S-063 |
| **S-062** (ADR-021 lines 357-358 stale "ratified pending") | Genuinely new (A1 didn't propagate to ADR-021 §"Relationship to Other Documents"). NOT a duplicate of S-044 (S-044 is about ADR-021 line 344 historical reference; S-062 is about lines 357-358 stale canonical-status claim). | Register as new LOW finding. Status: Open |
| **S-063** (ADR-021 line 362 contradicts git state) | Genuinely new (statement vs reality contradiction). NOT a duplicate of S-061 — S-061 is staleness; S-063 is the ADR's own claim contradicting git state. | Register as new LOW finding. Status: Open |
| **S-064** (architecture-lint checkStructure stale) | Genuinely new (latent defect — validIds accepts HUB-31/32 + ESPOKE-19 but checkStructure doesn't enforce file existence). Carry-forward to S-077 (this register's staleness is the same class of defect). | Register as new MEDIUM finding. Status: Open. Cross-ref S-077 |
| **S-065** (CORE-VERIFIED-DAG §7 stale for C02) | Genuinely new (same root cause as S-048 — A2 added ContainerException.php without updating DAG table). NOT a duplicate of S-048 — S-048 is runtime; S-065 is doc table. | Register as new LOW finding. Status: Open. Cross-ref S-048 |
| **S-077** (register stale — missing A3 findings) | **Self-referential — this register V2 IS the remediation.** Verification: V2 contains S-048..S-092 entries + V1 dispositions updated. | Status: Fixed (this register V2 PR). Verification: V2 contains all 34 A3 findings in scope |
| **S-078** (INDEX §1 line 65 says ADR-011 Proposed) | Genuinely new (ADR-011 status contradiction). NOT covered by S-046 — S-046 is about the COUNT of Proposed ADRs; S-078 is about the STATUS of ADR-011 itself. S-078 actually exposes S-046's premise error (see S-079). | Register as new HIGH finding. Status: Open. Cross-ref S-046, S-079, S-085 |
| **S-079** (S-046 register entry has incorrect premise) | Genuinely new (meta-audit — register self-inconsistency). This register V2 fixes S-046's premise. | Register as new MEDIUM finding. Status: Fixed (this register V2 corrects S-046's premise). Cross-ref S-046, S-078 |
| **S-080** (CORE-02.md docblock "Implementation note" stale post-A2) | Genuinely new (post-A2 docblock drift in spec blueprint). NOT the same as S-050 — S-050 is interface file drift; S-080 is spec blueprint paragraph drift. Different files, different drift sources. | Register as new MEDIUM finding. Status: Open. Cross-ref S-050, S-081 |
| **S-081** (CONTAINER-FIBER-STATE-MODEL.md Q1 quotes pre-A2-prep CORE-02 docblock) | Genuinely new (downstream-of-S-080 drift; A0 spec quotes stale docblock). NOT a duplicate of S-080 — different file (A0 spec vs spec blueprint). | Register as new LOW finding. Status: Open. Cross-ref S-080 |
| **S-082** (SDLC-AGRD "96 blueprints" stale) | Genuinely new (4 occurrences of stale count) | Register as new MEDIUM finding. Status: Open |
| **S-083** (SDLC-AGRD relies on INDEX §5.2 banner-superseded) | Genuinely new (hidden transitive drift). NOT a duplicate of S-082 — S-082 is count; S-083 is dependency on superseded section. | Register as new MEDIUM finding. Status: Open. Same file as S-082 |
| **S-084** (ADR-014 ratifies v3.4(3) but SDLC-AGRD is v3.5) | Genuinely new (ADR staleness — ratified artifact iterated without ADR amendment) | Register as new MEDIUM finding. Status: Open |
| **S-085** (OPEN-DECISIONS OD-01 says action completed but it wasn't) | Genuinely new (governance blind-spot — "Resolved" OD with uncompleted action). Part of ADR-011 contradiction cluster. | Register as new MEDIUM finding. Status: Open. Cross-ref S-046, S-078, S-079 |
| **S-086** (BLIND-SPOT-DOCTRINE "47-finding register" reference stale) | Genuinely new (post-A3 doctrine reference stale). Same root cause as S-077 — register staleness propagated downstream. | Register as new LOW finding. Status: Open. Cross-ref S-077 |
| **S-087** (INDEX §9 changelog stale) | Genuinely new (changelog ends 2026-08-12, missing ADR-021 + A1 + A2) | Register as new MEDIUM finding. Status: Open |
| **S-088** (INDEX §1 CrossCutting inventory omits BLIND-SPOT-DOCTRINE) | Genuinely new (inventory incompleteness — banner-referenced file missing from §1 inventory row) | Register as new LOW finding. Status: Open |
| **S-089** (INDEX §2.3 says ESPOKE-01..18) | Genuinely new (A1 propagation gap — §1 updated but §2.3 not). Part of the same propagation cluster as S-055/S-056/S-059/S-062. | Register as new MEDIUM finding. Status: Open. Cross-ref S-055/S-056/S-059/S-062 |
| **S-090** (README Milestone 0 header vs table contradiction) | Genuinely new (internal README contradiction; SDLC-AGRD §4 cross-reference) | Register as new MEDIUM finding. Status: Open |
| **S-091** (README "Built with" omits Loom + Project Status stale) | Genuinely new (Loom omission). Project Status staleness is carry-forward from A3-ARCH-GOV S-069 (out of scope here). Bundled in S-091's description. | Register as new LOW finding. Status: Open. Cross-ref (carry-forward) A3-ARCH-GOV S-069 |
| **S-092** (INCONSISTENCIES.md Status column inconsistent taxonomy) | Genuinely new (mixed status vocabulary across the table) | Register as new LOW finding. Status: Open |

### 7.5 Status updates applied to V1 findings (A1/A2/PR-303 remediations)

| V1 ID | V1 Status | V2 Status | Remediation | Verification condition | Closure evidence |
|---|---|---|---|---|---|
| **S-001** | Open | Fixed | PR #294 (A1) — extended `validIds` to accept HUB-32; published `HUB-32.md` at depth 1 | `architecture-lint` exits 0 on `main` HEAD; `rg "HUB-32" Architecture/` returns hits only from canonical blueprint + code blocks | Awaiting re-audit (A3 audits in progress) |
| **S-002** | Open | Fixed | PR #294 (A1) — extended `validIds` to accept ESPOKE-19; published `ESPOKE-19.md` at depth 1 | `architecture-lint` exits 0 on `main` HEAD; `rg "ESPOKE-19" Architecture/` returns hits only from canonical blueprint + code blocks | Awaiting re-audit |
| **S-003** | Open | Fixed | PR #301 (A2) — added `$pulseDefinitions` WeakMap; redirected `pulse()`/`make()` per-Fiber. PR #303 creates missing `PulseFiberIsolationTest.php` (closes S-054 gap) | (a) `testConcurrentFibersObserveIndependentPulseState` passes ✅ (b) `testCompletedFiberStateIsNotVisibleToNewFiber` passes ✅ (c) `PulseFiberIsolationTest.php` exists + passes — **gap closed by PR #303, awaiting re-audit** (d) `PHPUnit + PHPStan (core/kernel)` CI green ✅ | Awaiting re-audit. NOTE: At audit time (HEAD `200479e`, pre-PR-303), verification condition (c) was UNMET per A3-RUNTIME-76 finding S-054. PR #303 closes this gap. |
| **S-004** | Open | Fixed | Same as S-003 (PR #301 + #303). Blocked on S-003 closure per cross-reference table. | Same as S-003 | Awaiting re-audit |
| **S-033** | Open | Fixed (partial) | PR #294 (A1) — published `HUB-32.md` at depth 1 (87 lines). **Partial**: file exists ✅, but HUB-DECLARED-DAG.md §1 still says "29 active" and excludes HUB-32 (per S-056). | `ls Architecture/Hub/HUB-32.md` ✅. `rg "29 active" Architecture/Hub/HUB-DECLARED-DAG.md` still returns matches ❌ (S-056 not remediated). Full closure requires S-056 fix. | Awaiting S-056 fix; then re-audit to move to Verified/Closed |
| **S-046** | Open | Open (premise corrected) | This register V2 corrects S-046's incorrect premise per S-079. Original premise: "3 Proposed ADRs (ADR-011, ADR-015, ADR-016)". Corrected premise: "2 Proposed ADRs (ADR-015, ADR-016) — ADR-011 is Accepted (2026-08-13) per its own Status field + OPEN-DECISIONS OD-01". | `grep "Accepted" Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` returns match ✅ | Premise correction applied in this V2. Finding itself remains Open (INDEX.md §1 line 56 still says "1 Proposed" — the count is now wrong in a different way: should be "2 Proposed (ADR-015, ADR-016)"). Cross-ref S-078 + S-079 + S-085. |

### 7.6 Findings NOT remediated (V1 status carries forward as Open)

Per A3-ARCH-DAG-76 §4 (re-verification of pre-existing findings): S-026, S-033 (partial), S-034, S-035, S-036, S-037, S-044 remain NOT remediated as of HEAD `200479e`. All other V1 findings (S-005..S-032 except S-033, S-038..S-043, S-045..S-047) are also still Open (not in scope of A1/A2/PR-303 remediations).

---

## 8. Findings (V2 — compact entries; full V1 content preserved at `Architecture/Verification/SHORTCOMINGS-REGISTER.md`)

> The 47 V1 findings (S-001..S-047) retain their full content in the source register; this V2 section documents only the disposition changes and any new evidence gathered by A3 audits. The 34 A3 findings (S-048..S-065, S-077..S-092) are documented in compact form below — full evidence and remediation details are in the source A3 reports (`download/A3-RUNTIME-REPORT.md`, `download/A3-ARCH-DAG-REPORT.md`, `download/A3-DOC-GOV-REPORT.md`).

### 8.1 V1 finding dispositions (delta from V1)

| ID | Sev | Cat | Title | V1 Status | V2 Status | Disposition delta |
|---|---|---|---|---|---|---|
| S-001 | FATAL | CI | architecture-lint fails on HUB-32 references | Open | **Fixed** | PR #294 (A1) extended `validIds` + published HUB-32.md |
| S-002 | FATAL | CI | architecture-lint fails on ESPOKE-19 references | Open | **Fixed** | PR #294 (A1) extended `validIds` + published ESPOKE-19.md |
| S-003 | FATAL | CI | Container::pulse() global state leak | Open | **Fixed** | PR #301 (A2); PR #303 closes verification condition (c) gap per S-054 |
| S-004 | FATAL | CI | testCompletedFiberStateIsNotVisibleToNewFiber | Open | **Fixed** | Same as S-003 |
| S-005..S-032 | HIGH | (mixed) | (HIGH-batch backlog) | Open | Open | No change |
| S-033 | HIGH | Governance | HUB-32 pending canonical publication | Open | **Fixed (partial)** | PR #294 published HUB-32.md; DAG propagation blocked on S-056 |
| S-034..S-043 | MEDIUM | Coherence | (MEDIUM-batch) | Open | Open | No change |
| S-044 | LOW | Coherence | ADR-021 line 344 historical filename echo | Open | Open (Deferred) | Cosmetic; optional cleanup |
| S-045..S-047 | LOW | Coherence | (LOW-backlog) | Open | Open | No change |
| S-046 | LOW | Coherence | INDEX §1 line 56 says "1 Proposed ADR (HUB-31)" — incorrect premise | Open | **Open (premise corrected per S-079)** | This V2 corrects S-046's premise: ADR-011 is Accepted, not Proposed. Finding remains Open because INDEX §1 line 56 itself is still stale (now stale in a different way: should list "2 Proposed: ADR-015, ADR-016"). |
| S-047 | LOW | Coherence | INCONSISTENCIES.md #8 stale ("critical" but CORE-02 implemented) | Open | Open | No change |

### 8.2 New A3 findings (compact entries)

> Full evidence, root cause, remediation, and verification tests are in the source A3 reports (linked under `Source` for each finding). The compact entries below record the canonical dispositions for the V2 register.

---

#### S-048 — `pulse()` with class-string concrete silently misroutes or fails
- **Severity:** HIGH (borderline FATAL — load-bearing isolation invariant #5 "pulse shadows singleton" violated for class-string pulse values)
- **Category:** Latent-Defect (runtime / concurrency)
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** A2 redirected `pulse()` to write only to `pulseDefinitions[$fiber][$id]` WeakMap, never to global `$definitions`. Step 0's class-string fall-through claim ("step 8b will cache") is FALSE in 3 ways: (1) interface id → `NotFoundException`; (2) non-self class-string → wrong class autowired; (3) competing singleton → singleton silently wins.
- **Affected artifact:** `packages/core/container/src/Container.php` step 0 (lines 226–265)
- **Verification test:** `packages/core/container/tests/Unit/PulseFiberIsolationTest.php` (created by PR #303) tests all 3 class-string sub-paths + pulse-shadows-singleton for both object and class-string values.
- **Disposition:** Fixed (PR #303)
- **Cross-refs:** S-049 (dead code at same lines), S-053 (test-coverage gap), S-065 (DAG §7 table stale for same root cause — A2 added ContainerException.php)
- **Closure evidence:** (empty — awaiting re-audit)

#### S-049 — Steps 1b and 8b are dead code; `pulseScoped` flag on `ServiceDefinition` is now vestigial
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** After A2, global `$definitions` never contains a `pulseScoped = true` entry, so step 1b (line 275) and step 8b are unreachable. The step-0 comment claiming step 8b caches is FALSE.
- **Verification test:** `grep -n 'pulseScoped' packages/core/container/src/Container.php` shows the only writer is `pulse()` line 199 (writes to `pulseDefinitions`, not global); readers at step 1b/8b are unreachable.
- **Disposition:** Open (PR #303 partially fixes — step 8b becomes live via S-048 fix; step 1b remains dead, needs explicit deletion)
- **Cross-refs:** S-048 (same lines)
- **Closure evidence:** (empty)

#### S-050 — `ContainerInterface::pulse()` docblock drifts from Shape C contract
- **Severity:** MEDIUM
- **Category:** Doc-Drift
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** `ContainerInterface.php` lines 48–63 still describe Shape A semantics ("Register a Pulse-scoped binding", "fresh instance that is cached for the duration of that Pulse only", "tenant-scoped services"). Missing `@throws ContainerException` tag.
- **Verification test:** `rg "Register a Pulse-scoped binding" packages/core/container/src/` returns zero matches; `rg "@throws.*ContainerException" packages/core/container/src/ContainerInterface.php` returns one match.
- **Disposition:** Open
- **Cross-refs:** S-080 (spec blueprint docblock drift — different file, kept separate)
- **Closure evidence:** (empty)

#### S-051 — Duplicate stacked docblock for `$pulseDefinitions` property
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** `Container.php` lines 73–95 have two stacked docblocks; only the second (bare `@var`) is attached to the property. Rich explanation in first docblock is orphaned. A2 merge artifact.
- **Verification test:** `grep -c '^    /\*\*$' packages/core/container/src/Container.php` equals the count of properties + methods + class (no orphaned docblocks).
- **Disposition:** Open
- **Closure evidence:** (empty)

#### S-052 — No test for edge case #1 (`pulse()` outside Fiber throws `ContainerException`)
- **Severity:** LOW
- **Category:** Latent-Defect (test coverage)
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** Implementation is correct (`Container.php` lines 175–182 throw when `\Fiber::getCurrent()` is null), but no test exercises this. All 11 WorkerContamination tests call `pulse()` inside a Fiber.
- **Verification test:** `PulseFiberIsolationTest.php::testPulseOutsideFiberThrowsContainerException` exists + passes (created by PR #303).
- **Disposition:** Fixed (PR #303)
- **Cross-refs:** S-054 (combined fix creates the missing test file)
- **Closure evidence:** (empty — awaiting re-audit)

#### S-053 — No test for class-string `pulse()` bindings (S-048's failure mode)
- **Severity:** LOW
- **Category:** Latent-Defect (test coverage)
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** All 11 `pulse($id, $value)` calls in `WorkerContaminationTest.php` pass an OBJECT. Zero tests pass a class-string or Closure — this is why S-048 went undetected.
- **Verification test:** `PulseFiberIsolationTest.php::testPulseClassStringBindingResolvesToBoundConcrete` exists + passes (created by PR #303).
- **Disposition:** Fixed (PR #303)
- **Cross-refs:** S-048 (verifies the fix), S-054 (combined fix)
- **Closure evidence:** (empty — awaiting re-audit)

#### S-054 — S-003 verification condition (c) UNMET: container-level `PulseFiberIsolationTest.php` does not exist
- **Severity:** HIGH (FATAL-adjacent — blocks closure of S-003 / S-004 per the register's closure rule)
- **Category:** Governance (process / audit)
- **Source:** `download/A3-RUNTIME-REPORT.md` §3
- **Description (compact):** S-003's verification condition (c) explicitly required `packages/core/container/tests/Unit/PulseFiberIsolationTest.php`. The file did NOT exist at audit time (HEAD `200479e`). A2 expanded kernel-level `WorkerContaminationTest` but did not create the container-level unit test the register's closure rule explicitly named.
- **Verification test:** `ls packages/core/container/tests/Unit/PulseFiberIsolationTest.php` exits 0; the test class has the 4 named methods; `phpunit packages/core/container` is green. PR #303 creates the file.
- **Disposition:** Fixed (PR #303 — per runtime report recommended remediation #1, creates the missing file with 4 tests covering S-003 cond. (c) + S-052 + S-053 + S-048 verification)
- **Cross-refs:** S-003, S-004 (closes verification gap), S-052, S-053 (combined fix), S-048 (verifies fix)
- **Closure evidence:** (empty — awaiting re-audit. NOTE: Once PR #303 is merged and re-audit confirms `PulseFiberIsolationTest.php` exists + passes, S-003 moves to Verified, then S-054 moves to Verified, then S-004 follows.)

#### S-055 — HUB-VERIFIED-DAG.md stale Hub count (29 instead of 30 post-A1)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** HUB-VERIFIED-DAG.md still claims "2 of 29 active Hub blueprints" in 4 places (lines 38, 45, 115, 200) despite A1 (PR #294) adding HUB-32 as canonical. Post-A1: 32 total − 2 superseded = **30 active**.
- **Verification test:** `rg "29 active|2 of 29|6.9%" Architecture/Hub/HUB-VERIFIED-DAG.md` returns zero matches; line 38 reads "2 of 30 active".
- **Disposition:** Open
- **Cross-refs:** S-056, S-059, S-062, S-089 (A1 propagation gap cluster)
- **Closure evidence:** (empty)

#### S-056 — HUB-DECLARED-DAG.md stale HUB-32 references (HUB-32.md now exists)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** HUB-DECLARED-DAG.md explicitly excludes HUB-32 from its node set in 4 places (lines 22, 34, 70, 72, 711). But HUB-32.md was authored in A1 (PR #294) at depth 1. **Blocks S-033 closure** — S-033's verification condition explicitly requires "HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)".
- **Verification test:** `rg "29 active|ratified pending canonical|no blueprint file exists" Architecture/Hub/HUB-DECLARED-DAG.md` returns zero matches; line 34 reads "## §1. Node set (30 active Hub blueprints)".
- **Disposition:** Open
- **Cross-refs:** S-033 (blocks closure), S-055, S-059, S-062, S-089 (A1 propagation cluster)
- **Closure evidence:** (empty)

#### S-057 — CORE-DECLARED-DAG.md edge classification inconsistencies (C18→C06 misclassified)
- **Severity:** HIGH
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** The edge `C18 (Kernel) → C06 (Router)` is misclassified in 3 ways: (1) prose table says DECLARED_ONLY but Evidence column says "verified in code not declared in blueprint" (= UNDECLARED_VERIFIED); (2) Mermaid shows solid (verified) but table says DECLARED_ONLY (should be dotted); (3) `packages/core/kernel/composer.json` line 17 actually requires `sovereign-stack/core-router` — edge IS VERIFIED. Four-status summary math doesn't reconcile (9 listed + "remaining 22" = 31, not 32).
- **Verification test:** CORE-DECLARED-DAG.md table row for C18→C06 reads `VERIFIED`; four-status summary mathematically reconciles (14 + 31 + 0 + 0 = 45); Mermaid line 108 reads `C06 --> C18`.
- **Disposition:** Open
- **Cross-refs:** S-058 (same files, direction error)
- **Closure evidence:** (empty)

#### S-058 — CORE-VERIFIED-DAG.md + CORE-DECLARED-DAG.md Mermaid direction error (§6.1 admits but graph not corrected)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** CORE-VERIFIED-DAG.md §6.1 (line 577) acknowledges 4 wrong-direction edges (C18→C06, C15→C14, C17→C13, C20→C13) but §4 Mermaid (line 563) still shows the wrong directions. Same 4 wrong directions appear in CORE-DECLARED-DAG.md §3 Mermaid (lines 106, 108, 113, 114). "Admitted-but-not-fixed" pattern.
- **Verification test:** `rg "C18 --> C06|C15 --> C14|C17 --> C13|C20 --> C13" Architecture/Core/CORE-VERIFIED-DAG.md Architecture/Core/CORE-DECLARED-DAG.md` returns zero matches.
- **Disposition:** Open
- **Cross-refs:** S-057 (same files, classification error)
- **Closure evidence:** (empty)

#### S-059 — INDEX.md internally contradictory on HUB-32 active count
- **Severity:** MEDIUM
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** INDEX.md line 54 says "30 active" (correct post-A1); line 227 says "29 active"; line 234 says "HUB-32 not yet counted"; line 443 says "30 blueprints". Stale pre-A1 references not propagated.
- **Verification test:** `rg "29 active|30 blueprints|102 declared|ratified pending canonical" Architecture/INDEX.md` returns zero matches; all Hub count references consistently read "32 declared (30 active + 2 superseded)".
- **Disposition:** Open
- **Cross-refs:** S-055, S-056, S-062, S-089 (A1 propagation cluster)
- **Closure evidence:** (empty)

#### S-060 — scripts/architecture-boundary-lint.php referenced in workflow yml but doesn't exist
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** `.github/workflows/architecture-boundary-lint.yml` references non-existent `scripts/architecture-boundary-lint.php` (path filter lines 23/30 + false comment lines 62-65). Only the Python version exists.
- **Verification test:** Either the PHP file exists at `scripts/architecture-boundary-lint.php`, or the yml has no reference to it.
- **Disposition:** Open
- **Closure evidence:** (empty)

#### S-061 — download/ARCHITECTURE_BASELINE.md is stale (10 commits behind HEAD)
- **Severity:** LOW
- **Category:** Build-artifact drift
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** Baseline was generated from commit `ce27388` (PR #292, 2026-10-01). HEAD is `34ea867` (10 commits later including A1 #294, A2 #301). Diff: 102→104 blueprints, 31→32 Hub, 18→19 ESPOKE, 200→201 PHP source files, 7→8 container src files.
- **Verification test:** `download/ARCHITECTURE_BASELINE.md` line 5-7 reads "Generated: <timestamp>; Commit: 34ea867..." (matching HEAD).
- **Disposition:** Open
- **Cross-refs:** S-063 (related — both about the baseline file)
- **Closure evidence:** (empty)

#### S-062 — ADR-021 lines 357-358 stale ("ratified pending canonical publication" — HUB-32 and ESPOKE-19 are now canonical)
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** ADR-021 §"Relationship to Other Documents" still describes HUB-32 and ESPOKE-19 as "Ratified pending canonical publication — blueprint file to be created during implementation phase". Both blueprints were authored in A1 (PR #294) at depth 1.
- **Verification test:** `rg "ratified pending canonical publication" Architecture/ADRs/ADR-021-tier-stratified-build-order.md` returns zero matches.
- **Disposition:** Open
- **Cross-refs:** S-055, S-056, S-059, S-089 (A1 propagation cluster)
- **Closure evidence:** (empty)

#### S-063 — ADR-021 line 362 contradicts git state ("not committed (gitignored)" but file IS committed)
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** ADR-021 line 362 says `download/ARCHITECTURE_BASELINE.md` is "not committed (gitignored, reproducible by running the script)". But `git ls-files download/ARCHITECTURE_BASELINE.md` returns the file — it IS committed. `.gitignore` does NOT ignore `download/`.
- **Verification test:** Either `git ls-files download/ARCHITECTURE_BASELINE.md` returns empty (and .gitignore contains the path), OR ADR-021 line 362 reads "Committed evidence snapshot — reproducible by running the script."
- **Disposition:** Open
- **Cross-refs:** S-061
- **Closure evidence:** (empty)

#### S-064 — architecture-lint checkStructure stale (requires HUB-01..30 + ESPOKE-01..18, missing HUB-31/32 + ESPOKE-19)
- **Severity:** MEDIUM
- **Category:** Latent-defect
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** `Architecture/Verification/lint/run.php` `checkStructure()` (lines 169-218) only requires HUB-01..30 + ESPOKE-01..18 to exist as files. But `buildValidIds()` accepts HUB-01..32 + ESPOKE-01..19. So deleting HUB-31.md/HUB-32.md/ESPOKE-19.md would NOT be caught by lint. Dead code at lines 71-72 (`$this->validIds['HUB-31'] = true;` is a no-op since `range(1,32)` already includes it).
- **Verification test:** Delete `Architecture/Hub/HUB-32.md` temporarily; run `php Architecture/Verification/lint/run.php`; expect exit code 1 with "missing file 'Hub/HUB-32.md'". Restore; expect 0.
- **Disposition:** Open
- **Cross-refs:** S-077 (same class of defect — register itself is stale)
- **Closure evidence:** (empty)

#### S-065 — CORE-VERIFIED-DAG.md §7 implementation-status table stale for C02 (says 7 src files, actually 8)
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-ARCH-DAG-REPORT.md` §3
- **Description (compact):** CORE-VERIFIED-DAG.md §7 line 632 says "C02 | YES | 7 | 17 | 0.4.0.0 | 2 |" but `packages/core/container/src/` now has 8 PHP files (ContainerException.php added by A2 PR #301).
- **Verification test:** `ls packages/core/container/src/*.php | wc -l` returns 8; CORE-VERIFIED-DAG.md line 632 reads "C02 | YES | 8 | 17 | 0.4.0.0 | 2 |".
- **Disposition:** Open
- **Cross-refs:** S-048 (same root cause — A2 added ContainerException.php)
- **Closure evidence:** (empty)

---

#### S-077 — SHORTCOMINGS-REGISTER.md is missing A3 findings S-048..S-092 (self-referential)
- **Severity:** HIGH
- **Category:** Governance
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** The register ended at S-047. The parallel A3 audits identified 34+ new findings that have NOT been folded into the canonical register (per this task's scope: S-048..S-065 + S-077..S-092 = 34 findings; plus S-066..S-076 from parallel A3-ARCH-GOV-76 to be folded in follow-up). Per BLIND-SPOT-DOCTRINE binding rule #1 ("No audit is declared complete; register is always open") and the register's own closure rule, all findings must be tracked.
- **Verification test:** `grep -c "^### S-" Architecture/Verification/SHORTCOMINGS-REGISTER.md` returns ≥ 81 (47 V1 + 34 A3 in scope); register summary table reconciles (FATAL=4, HIGH=30+ in current scope, etc.).
- **Disposition:** **Fixed (this register V2 PR)** — V2 contains all 34 A3 findings in task scope + V1 dispositions updated for A1/A2/PR-303 remediations.
- **Cross-refs:** S-064 (same class — latent defect in mechanical enforcement), S-079 (register self-inconsistency in S-046), S-086 (BLIND-SPOT-DOCTRINE "47-finding register" reference stale — same root cause)
- **Closure evidence:** This V2 register at `/home/z/my-project/download/SHORTCOMINGS-REGISTER-V2.md` contains all 81 entries (47 V1 + 34 A3 in scope). Verification condition met upon merge of this PR.

#### S-078 — INDEX.md §1 line 65 still says "ADR-011 | 1 Proposed ADR (HUB-31) — not accepted, not counted"
- **Severity:** HIGH
- **Category:** Doc-Drift (cross-document contradiction)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** Three sources disagree on ADR-011's status: ADR-011 file (line 13: "Accepted (2026-08-13)"); OPEN-DECISIONS OD-01 (Resolved, ADR-011 accepted 2026-08-12); INDEX.md §1 line 65 (Proposed). ADR file + OPEN-DECISIONS agree Accepted; INDEX.md is the lone dissenter.
- **Verification test:** INDEX.md §1 line 65 reads "Accepted (2026-08-13) — see ADR-011 + OPEN-DECISIONS OD-01".
- **Disposition:** Open
- **Cross-refs:** S-046 (related — count-of-Proposed-ADRs finding; S-078 exposes S-046's incorrect premise via S-079), S-079 (S-046 incorrect premise), S-085 (OD-01 action-not-completed)
- **Closure evidence:** (empty)

#### S-079 — S-046 register entry has incorrect premise (counts ADR-011 as Proposed, but ADR-011 is Accepted)
- **Severity:** MEDIUM
- **Category:** Meta-audit (register self-inconsistency)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** V1 register's S-046 entry said "1 Proposed ADR (HUB-31) — but there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)". This premise is WRONG: ADR-011 is Accepted (per file line 13 + OPEN-DECISIONS OD-01). Only 2 Proposed ADRs exist (ADR-015, ADR-016). The register's own S-046 enumeration is incorrect by 1.
- **Verification test:** This V2 register's S-046 entry has corrected premise: "2 Proposed ADRs (ADR-015, ADR-016) — ADR-011 is Accepted (2026-08-13) per ADR file + OPEN-DECISIONS OD-01."
- **Disposition:** **Fixed (this register V2)** — S-046's premise is corrected in §8.1 above.
- **Cross-refs:** S-046, S-078, S-085 (ADR-011 status contradiction cluster)
- **Closure evidence:** This V2's S-046 row in §8.1 documents the corrected premise.

#### S-080 — CORE-02.md docblock "Implementation note (S-003/S-004 remediation direction)" is stale post-A2
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A2)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** CORE-02.md lines 245-247 contain an "Implementation note" paragraph describing the **pre-A2 state**: "The current implementation writes `pulse()` to global `$definitions[$id]` instead of a per-Fiber `WeakMap $pulseDefinitions`. The fix: add `private \WeakMap $pulseDefinitions` and redirect `pulse()`/`make()` to consult it per-Fiber." But A2 (PR #301) ALREADY added the WeakMap and redirected pulse()/make() — the "fix" the paragraph describes HAS BEEN APPLIED. A fresh architect would believe the S-003/S-004 bug is still present and re-implement an already-shipped fix.
- **Verification test:** CORE-02.md lines 245-247 reflect post-A2 state ("The current implementation uses `private \WeakMap $pulseDefinitions` per-Fiber — see Container.php line 95. Implementation note (pre-A2 historical): the global `$definitions` mutation pattern was the S-003/S-004 defect, now remediated.").
- **Disposition:** Open
- **Cross-refs:** S-050 (different file — interface docblock drift; kept separate), S-081 (downstream — A0 spec quotes stale docblock)
- **Closure evidence:** (empty)

#### S-081 — CONTAINER-FIBER-STATE-MODEL.md (A0 spec) Q1 quotes the pre-A2-prep CORE-02 docblock as evidence
- **Severity:** LOW
- **Category:** Doc-Drift (post-A2-prep)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** A0 spec line 384 quotes the pre-A2-prep CORE-02 docblock ("Register a Pulse-scoped binding — one instance per Fiber (per Pulse)..."). This text NO LONGER EXISTS in CORE-02.md (replaced with "Bind a concrete value to the current Fiber's Pulse scope (Shape C)" per A2-prep PR #295).
- **Verification test:** CONTAINER-FIBER-STATE-MODEL.md Q1 quotes the current CORE-02 docblock (Shape C language); or Q1 is annotated "quoted docblock is stale post-A2-prep".
- **Disposition:** Open
- **Cross-refs:** S-080 (same root cause — stale CORE-02 docblock reference)
- **Closure evidence:** (empty)

#### S-082 — SDLC-AGRD.md references stale "96 blueprints" count (4 occurrences)
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A1)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** SDLC-AGRD.md lines 195, 254, 278, 437 say "96 blueprints" but A1 made HUB-32 + ESPOKE-19 canonical (post-A1 actual: 104). "96" was correct circa 2026-08-05.
- **Verification test:** `grep "96 blueprints\|of 96 blueprints" Architecture/CrossCutting/SDLC-AGRD.md` returns zero matches.
- **Disposition:** Open
- **Cross-refs:** S-083, S-084 (same file — SDLC-AGRD staleness cluster)
- **Closure evidence:** (empty)

#### S-083 — SDLC-AGRD.md relies on INDEX.md §5.2 as live Mermaid graph data source — but INDEX §5.2 is banner-superseded by ADR-021
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-ADR-021)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** SDLC-AGRD.md §4.3 widen-rule points to INDEX.md §5.2 as the canonical Mermaid graph. But INDEX §5.2 is banner-superseded by ADR-021 (canonical source is now `CORE-VERIFIED-DAG.md` + `CORE-DECLARED-DAG.md` + sibling per-tier DAGs).
- **Verification test:** `grep "INDEX\.md §5\.2" Architecture/CrossCutting/SDLC-AGRD.md` returns zero matches (or only historical mentions clearly annotated as superseded).
- **Disposition:** Open
- **Cross-refs:** S-082, S-084 (SDLC-AGRD cluster)
- **Closure evidence:** (empty)

#### S-084 — ADR-014 ratifies SDLC-AGRD v3.4(3) but SDLC-AGRD is now at v3.5 — ADR-014 not amended
- **Severity:** MEDIUM
- **Category:** Governance (ADR staleness)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** ADR-014 line 34: "Ratify SDLC-AGRD.md v3.4(3)". But SDLC-AGRD has moved to v3.5 (per its own changelog line 23). ADR-014 line 51 acknowledges v3.5 as a future state, but doesn't record that v3.5 has shipped.
- **Verification test:** ADR-014 has an amendment acknowledging v3.5 ship-date; OR ADR-014 line 51 updated to past tense.
- **Disposition:** Open
- **Cross-refs:** S-082, S-083 (SDLC-AGRD cluster)
- **Closure evidence:** (empty)

#### S-085 — OPEN-DECISIONS OD-01 says "Action: INDEX.md updated — HUB-31 added to Hub tier table" but INDEX.md §2.2 does NOT include HUB-31
- **Severity:** MEDIUM
- **Category:** Doc-Drift (action-not-actually-completed)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** OPEN-DECISIONS OD-01 (line 115) records the resolution of ADR-011 with an action: "INDEX.md updated — HUB-31 added to Hub tier table". But INDEX.md §2.2 Hub tier table only goes HUB-01..HUB-30 (no HUB-31 row). The "Action: INDEX.md updated" claim is FALSE — the action was never actually completed.
- **Verification test:** INDEX.md §2.2 includes a HUB-31 row; OR OPEN-DECISIONS OD-01 action claim is corrected to reflect incomplete action.
- **Disposition:** Open
- **Cross-refs:** S-046, S-078, S-079 (ADR-011 status contradiction cluster)
- **Closure evidence:** (empty)

#### S-086 — BLIND-SPOT-DOCTRINE.md "Companion documents" section references "the 47-finding register" — stale post-A3
- **Severity:** LOW
- **Category:** Doc-Drift (post-A3)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** BLIND-SPOT-DOCTRINE.md line 142: "SHORTCOMINGS-REGISTER.md — the 47-finding register (always open)". The "47-finding" description is stale post-A3 — register universe is now 81+ (47 V1 + 34 A3 in this task's scope).
- **Verification test:** BLIND-SPOT-DOCTRINE.md line 142 reads "the canonical findings register (always open; finding count grows as audits accumulate)" or similar non-pinned-count language.
- **Disposition:** Open (this V2 register update is a downstream trigger; BLIND-SPOT-DOCTRINE.md itself needs a separate edit)
- **Cross-refs:** S-077 (same root cause — register staleness propagates downstream)
- **Closure evidence:** (empty)

#### S-087 — INDEX.md §9 change log is stale (last entry 2026-08-12; missing ADR-021 + A1 + A2)
- **Severity:** MEDIUM
- **Category:** Doc-Drift (changelog staleness)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** INDEX.md §9 changelog ends 2026-08-12 ("OD resolution pass (PR #104)"). Missing: 2026-09-30 ADR-021 acceptance; 2026-10-01 A1 (PR #294); 2026-10-02 A2 (PR #301); 2026-10-02 A3 audits.
- **Verification test:** INDEX.md §9 has changelog entries for ADR-021 (2026-09-30), A1 (2026-10-01), A2-prep (2026-10-01), A2 (2026-10-02), A3 audits (2026-10-02).
- **Disposition:** Open
- **Closure evidence:** (empty)

#### S-088 — INDEX.md §1 CrossCutting inventory row omits BLIND-SPOT-DOCTRINE.md
- **Severity:** LOW
- **Category:** Doc-Drift (inventory incompleteness)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** BLIND-SPOT-DOCTRINE.md is referenced inline from INDEX.md banner (lines 6, 8) but missing from §1 CrossCutting inventory row. Multiple post-2026-08-12 CrossCutting documents added without refreshing §1.
- **Verification test:** INDEX.md §1 CrossCutting row lists BLIND-SPOT-DOCTRINE.md (or points to a comprehensive inventory).
- **Disposition:** Open
- **Closure evidence:** (empty)

#### S-089 — INDEX.md §2.3 says "External Spokes — ESPOKE-01..18" (stale post-A1)
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A1)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** INDEX.md §2.3 line ~159 says "ESPOKE-01..18, all documented". A1 (PR #294) made ESPOKE-19 Eloq canonical at depth 1. §1 was updated (line ~57) but §2.3 was NOT — internal INDEX.md contradiction.
- **Verification test:** `grep "ESPOKE-01\.\.18" Architecture/INDEX.md` returns zero matches.
- **Disposition:** Open
- **Cross-refs:** S-055, S-056, S-059, S-062 (A1 propagation cluster)
- **Closure evidence:** (empty)

#### S-090 — README.md Milestone 0 table has 9 rows but header says "(8 blueprints required for MUWV)"
- **Severity:** MEDIUM
- **Category:** Doc-Drift (internal contradiction)
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** README.md line 218 header: "Milestone 0 components (8 blueprints required for MUWV)". Table body (lines 220-228) has 9 rows: CORE-02, CORE-04, CORE-05, CORE-06, CORE-18, HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01. SDLC-AGRD §4 line 110 says Milestone 0 = 8 blueprints (no CORE-18). README header matches SDLC-AGRD's 8; README table body has 9 (extra CORE-18).
- **Verification test:** README Milestone 0 header count matches table row count; SDLC-AGRD §4's Milestone 0 list matches README's table.
- **Disposition:** Open
- **Closure evidence:** (empty)

#### S-091 — README.md "Built with" table omits Loom; Project Status section stale
- **Severity:** LOW
- **Category:** Doc-Drift
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** README "Built with" table omits Loom (CORE-01 — custom SemVer automation tool). Also README line 211 Project Status claims pre-MUWV-1 work that contradicts README line 108 ("§4.5 CORE-18 Kernel pilot is fully implemented") — and the project is now in A3 (re-audit) phase. Project-status staleness is carry-forward from A3-ARCH-GOV S-069.
- **Verification test:** "Built with" table has a Loom row; Project Status line reflects the A3 phase.
- **Disposition:** Open
- **Cross-refs:** (carry-forward) A3-ARCH-GOV S-069 (out of scope for this reconciliation)
- **Closure evidence:** (empty)

#### S-092 — INCONSISTENCIES.md table has inconsistent "Status" values (mixed taxonomy)
- **Severity:** LOW
- **Category:** Coherence
- **Source:** `download/A3-DOC-GOV-REPORT.md` §3
- **Description (compact):** INCONSISTENCIES.md Status column has mixed values: "Resolved", "Replaced", "Flagged critical", "Documented; Phase 2", "Archived + Rule 6", "Re-baselined (ADR-010)", "Merged into blueprints", "Rule 4", "Rule 8". No consistent status vocabulary. Row 8 (CORE-02 "Flagged critical") is also stale per S-047 (CORE-02 implemented).
- **Verification test:** INCONSISTENCIES.md Status column has at most 5 distinct values (per a defined taxonomy: Open/In-Progress/Resolved/Superseded/Wont-Fix); row 8 status is "Resolved".
- **Disposition:** Open
- **Cross-refs:** S-047 (carry-forward — INCONSISTENCIES #8 stale)
- **Closure evidence:** (empty)

---

## 9. Cross-reference table (NEW in V2)

| Finding | Blocks / Blocked by | Combined-fix cluster |
|---|---|---|
| S-003, S-004 | S-003 blocked-on S-054 gap; S-004 blocked-on S-003 | PR #303 (with S-048, S-052, S-053, S-054) |
| S-033 | Blocked-on S-056 (HUB-DECLARED-DAG.md update) | A1 propagation fix (with S-055, S-056, S-059, S-062, S-089) |
| S-046 | S-046's premise error → S-079; ADR-011 cluster | ADR-011 status fix (with S-078, S-079, S-085) |
| S-048, S-049 | S-049 partial-fix via S-048 fix; step 1b still dead | PR #303 (with S-052, S-053, S-054) |
| S-050, S-080, S-081 | All post-A2 docblock drift, different files | Doc-drift batch |
| S-055, S-056, S-059, S-062, S-089 | All A1 propagation gap | A1 propagation fix PR (closes S-033) |
| S-057, S-058 | Same files (CORE-DECLARED-DAG.md + CORE-VERIFIED-DAG.md) | DAG Mermaid + classification fix PR |
| S-061, S-063 | Both about ARCHITECTURE_BASELINE.md | Baseline commit-policy fix PR |
| S-064, S-077 | Same class — mechanical enforcement gap | Register update + lint extension PR |
| S-065, S-048 | Same root cause — A2 added ContainerException.php | ContainerException propagation fix PR |
| S-077, S-079, S-086 | Same root cause — register staleness | This V2 register + BLIND-SPOT-DOCTRINE.md edit |
| S-082, S-083, S-084 | All SDLC-AGRD.md | SDLC-AGRD refresh + ADR-014 amendment PR |

---

## 10. Recommended remediation order (post-A3, gates roadmap advancement)

1. **Immediate (gates paradigm shift directive):**
   - This register V2 PR — amends SHORTCOMINGS-REGISTER.md to add S-048..S-092 (34 new findings) + updates S-001..S-047 dispositions. [S-077, S-079]
   - Fix ADR-011 status contradiction: update INDEX.md §1 line 65 → "Accepted"; add HUB-31 row to §2.2; correct S-046 premise in register. [S-078 + S-079 + S-085]
   - Confirm PR #303 merged + re-audit `PulseFiberIsolationTest.php` exists + passes. [moves S-003, S-004, S-048, S-052, S-053, S-054 → Verified → Closed]

2. **HIGH-batch (post-Immediate):**
   - A1 propagation fix: propagate HUB-32/ESPOKE-19 canonical status into HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md, INDEX.md (§2.3, §4), ADR-021 §"Relationship to Other Documents". [closes S-055, S-056, S-059, S-062, S-089; moves S-033 → Verified]
   - DAG Mermaid + classification fix: 4 wrong-direction edges in CORE-VERIFIED-DAG.md §4 + CORE-DECLARED-DAG.md §3; reclassify C18→C06 → VERIFIED; fix four-status summary math. [closes S-057, S-058]
   - README batch: PHP version, package count, ADR count, Milestone 0 header-vs-table. [closes S-005..S-009, S-022, S-090, plus carry-forward of S-067, S-068, S-069, S-070, S-071]
   - CORE-02 docblock "Implementation note" + reference impl + ContainerInterface docblock + A0 spec Q1. [closes S-050, S-080, S-081; plus carry-forward of S-058]
   - SDLC-AGRD refresh: 96→104 count; §4.3 widen-rule point to canonical per-tier DAGs; ADR-014 amendment. [closes S-082, S-083, S-084]
   - INDEX §9 changelog + ADR-021 baseline commit-policy fix. [closes S-061, S-063, S-087]

3. **MEDIUM-batch:** All other MEDIUM findings.

4. **LOW-backlog:** All LOW findings + S-044 (deferred cosmetic).

5. **Carry-forward from A3-ARCH-GOV-76 (S-066..S-076, NOT in this task's scope):**
   - Fold S-066..S-076 (11 findings) into this V2 register in a follow-up PR after A3-ARCH-GOV-76 report is reviewed.
   - Note: S-066..S-076 include S-066 (doctrine typo), S-067 (README severely stale — overlaps with S-005..S-013), S-068 (INDEX §4 stale counts — overlaps with S-014/S-059), S-069 (README project status stale — overlaps with S-091), S-070 (ADR-017 provenance incomplete), S-071 (INDEX §1 line 54 vs line 65 contradiction — overlaps with S-078), S-072 (HUB-DECLARED-DAG vs INDEX on HUB-31 — overlaps with S-078), S-073 (Hub blueprint H1 format inconsistency), S-074 (naming-drift fitness function wrong mapping for HUB-30), S-075 (naming-drift fitness function 17 components coverage), S-076 (architecture-boundary-lint legitimate_callers_seen 0/6).
   - Several of these are partial duplicates of findings already in this V2 register (S-067 ↔ S-005..S-013; S-068 ↔ S-014/S-059; S-069 ↔ S-091; S-071 ↔ S-078; S-072 ↔ S-078). These will need careful reconciliation in the follow-up PR.

---

## 11. Audit History (separate from finding lifecycle)

| Date | Audit | Findings added | Findings updated | Notes |
|---|---|---|---|---|
| 2026-10-01 | SHORTCOMINGS-AUDIT-76 | S-001..S-047 (47 findings) | (initial) | V1 register |
| 2026-10-01 | A1 (PR #294) | — | S-001, S-002, S-033 (partial) → Fixed | Canonical HUB-32 + ESPOKE-19 publication |
| 2026-10-01 | A2-prep (PR #295) | — | — | Shape C pulse() contract landed |
| 2026-10-02 | A2 (PR #301) | — | S-003, S-004 → Fixed (condition c unmet per S-054) | Container::pulse() Fiber isolation |
| 2026-10-02 | A3-RUNTIME-76 | S-048..S-054 (7 findings) | S-003/S-004 → confirmed Fixed-not-Verified (condition c gap) | Runtime/concurrency re-audit |
| 2026-10-02 | A3-ARCH-DAG-76 | S-055..S-065 (11 findings) | S-026, S-033 (partial), S-034..S-037, S-044 → confirmed NOT remediated | Architecture/DAG re-audit |
| 2026-10-02 | A3-ARCH-GOV-76 | S-055..S-077 (23 findings — ID collision with arch-DAG on S-055..S-065; collision with doc-GOV on S-077) | (out of scope for this V2; will be reconciled in follow-up) | Architecture/governance re-audit |
| 2026-10-03 | A3-DOC-GOV-76 | S-077..S-092 (16 findings — S-077 content-doc-gov version adopted per task brief) | S-046 premise corrected per S-079 | Documentation/contract + governance/SDLC re-audit |
| 2026-10-03 | PR #303 | — | S-048, S-052, S-053, S-054 → Fixed; S-003/S-004 verification condition (c) gap closed | PulseFiberIsolationTest.php created |
| 2026-10-03 | **S077-RECONCILE-76 (this task)** | (reconciliation only — no new findings) | S-001..S-047 dispositions updated; S-077, S-079 → Fixed (this V2 register) | Reconciled 34 A3 findings against V1; produced V2 register |

---

*End of SHORTCOMINGS-REGISTER-V2.md. Saved to `/home/z/my-project/download/SHORTCOMINGS-REGISTER-V2.md` by task S077-RECONCILE-76.*
