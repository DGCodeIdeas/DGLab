# A3-ARCH-DAG-76 — Architecture/DAG Integrity + Repository/Build/Artifact Consistency Re-Audit

**Task ID:** A3-ARCH-DAG-76
**Agent:** General-purpose (Phase A3 architecture/DAG re-audit)
**Scope:** Architecture/Core + Architecture/Hub DAG files; lint validIds; `scripts/generate-architecture-baseline-v2.py` + `download/ARCHITECTURE_BASELINE.md`; `.github/workflows/architecture-boundary-lint.yml`; stale filename references in `Architecture/`.
**HEAD at audit:** `34ea867` (2026-10-02T20:44:09Z), branch `main`.
**Audit-only:** No code or architecture files were modified.

---

## §0. Executive summary

The two-DAG governance model ratified by ADR-021 (Amendment 1, PR #287) is **structurally sound** — the verified and declared DAGs exist, the four edge-status categories are documented, the lint `validIds` map covers CORE-01..20 / HUB-01..32 / ISPOKE-01..27 / ESPOKE-01..19, and the architecture-boundary-lint script passes with 0 violations and 19/19 regression tests green. The architecture-lint (PHP) workflow also passes per the A1 worklog.

**However**, the DAG files are **stale relative to recent governance changes**:

1. **HUB-32 (ratified canonical in A1, PR #294) is not reflected in the Hub DAGs.** HUB-VERIFIED-DAG.md and HUB-DECLARED-DAG.md still claim "29 active Hub blueprints" and "no HUB-32 blueprint file exists", despite HUB-32.md being authored 2026-10-01 at depth 1. This blocks S-033 closure (its verification condition explicitly requires "HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)").

2. **The old filename `CORE-DEPENDENCY-DAG.md` is still referenced in 4 Core DAG files + 2 Hub DAG files** (8 references total, plus 4 historical mentions in ADR-021 + register/audit). S-034 through S-037 (and S-044) remain **Open with empty closure evidence** — they were never remediated despite being registered as MEDIUM/LOW batch fixes.

3. **CORE-DECLARED-DAG.md has internal edge-classification inconsistencies** around the `C18 → C06` edge: the prose table classifies it as `DECLARED_ONLY` but the evidence column says "verified in code but not declared in blueprint" (which is `UNDECLARED_VERIFIED` by definition). The Mermaid graph shows it as solid (verified style), contradicting the table. Actual `packages/core/kernel/composer.json` requires `sovereign-stack/core-router`, so the edge IS verified-in-composer — it should be reclassified as `VERIFIED`. The "remaining 22 edges" math also doesn't sum to 32.

4. **The Mermaid direction error for `C18 → C06` (acknowledged in CORE-VERIFIED-DAG.md §6.1 as wrong)** has not been corrected in either DAG's own Mermaid graph. The DAG files document the error in their analysis sections but ship the wrong direction in their authoritative graph.

5. **`download/ARCHITECTURE_BASELINE.md` is stale by 10 commits** (generated from `ce27388` PR #292; HEAD is `34ea867`). It misses HUB-32 (PR #294), ESPOKE-19 (PR #294), ContainerException.php (PR #301 A2), and 7 other commits. Diff: 102 vs 104 blueprints, 31 vs 32 Hub, 18 vs 19 ESPOKE, 200 vs 201 PHP source files.

6. **ADR-021 contradicts itself on baseline commit policy** (line 362 says "not committed (gitignored)" but the file IS committed — `git ls-files` confirms).

7. **The architecture-boundary-lint workflow references a non-existent PHP file** (`scripts/architecture-boundary-lint.php`).

8. **The architecture-lint structural check is stale**: it requires Hub files HUB-01..30 and ESPOKE files ESPOKE-01..18, but validIds accepts HUB-01..32 and ESPOKE-01..19. So if HUB-31.md, HUB-32.md, or ESPOKE-19.md were deleted, the lint would not catch it.

---

## §1. Architecture/DAG integrity findings

### §1.1 CORE-VERIFIED-DAG.md (667 lines)

| Check | Result |
|---|---|
| Does it reference the old filename `CORE-DEPENDENCY-DAG.md`? | **YES** — 2 stale references (line 18 historical rename mention, OK; line 666 footer "End of CORE-DEPENDENCY-DAG.md", STALE per S-035) |
| Is the §4 Mermaid graph direction-correct? | **NO** — line 563 has `C18 --> C06` but §6.1 (line 577) admits this should be `C06 --> C18`. The file documents the error in its own analysis but ships the wrong direction in its authoritative Mermaid (new finding S-058). |
| Is the §7 implementation-status table accurate? | **NO** — line 632 says C02 has 7 src files, but `packages/core/container/src/` now has 8 (ContainerException.php added by A2 PR #301). New finding S-065. |
| Has the "Generated, do not edit manually" header? | N/A (this is the verified DAG, not a build order — header not expected). |

### §1.2 CORE-DECLARED-DAG.md (150 lines)

| Check | Result |
|---|---|
| Edge counts correct? | **NO** — multiple inconsistencies (new finding S-057). |
| `C18 → C06` edge classification correct? | **NO** — line 50 marks DECLARED_ONLY with evidence "verified in code but not declared in blueprint" (= UNDECLARED_VERIFIED by definition, contradicting summary's UNDECLARED_VERIFIED=0 claim). Mermaid line 108 shows it as solid (verified style). Actual `packages/core/kernel/composer.json` requires `sovereign-stack/core-router` → edge IS verified-in-composer → should be VERIFIED. |
| Mermaid direction for `C18 → C06` correct? | **NO** — Mermaid line 108 shows `C18 --> C06` (wrong direction per the convention "X --> Y means Y consumes X"). Should be `C06 --> C18` (Kernel consumes Router). Same error as CORE-VERIFIED-DAG.md (S-058). |
| Four-status summary math (line 56-64)? | **Doesn't reconcile** — claims VERIFIED=13, DECLARED_ONLY=32, UNDECLARED_VERIFIED=0, INVALID=0, total=45. But: (a) 9 explicitly listed declared-only edges + "remaining 22 edges" line (54) = 31, not 32; (b) Mermaid shows 14 solid (verified) edges, not 13 (the C18→C06 discrepancy); (c) UNDECLARED_VERIFIED=0 but the C18→C06 row's evidence column literally says "verified in code but not declared in blueprint" — that's UNDECLARED_VERIFIED by definition. |
| References `CORE-DEPENDENCY-DAG.md`? | No (this file was authored post-rename). |
| Has "Generated" header? | No (this is the declared DAG — hand-maintained per line 149). |

### §1.3 CORE-BUILD-ORDER.md (335 lines)

| Check | Result |
|---|---|
| Marked as "Generated, do not edit manually"? | **YES** — line 12 has `<!-- GENERATED ARTIFACT — Do not edit manually. -->` header. ✅ |
| References old filename `CORE-DEPENDENCY-DAG.md`? | **YES** — 3 stale references (line 288, line 328, line 334 footer). STALE per S-037. |
| Generator script listed (`scripts/generate-build-order.py (future)`)? | **YES** — line 14 says "Reproducible by: scripts/generate-build-order.py (future)" — but the script doesn't exist yet. Acceptable (the file IS marked as future). |
| Implementation-status counts stale? | Not checked line-by-line; line 288 mentions "12 implemented Core packages" but actually 13 implemented (C14 added later, plus C01 orchestrator). |

### §1.4 HUB-DECLARED-DAG.md (716 lines)

| Check | Result |
|---|---|
| References HUB-32 correctly (post-A1 canonical)? | **NO** — multiple stale references (new finding S-056): line 22 ("29 active Hub blueprints"), line 34 ("§1. Node set (29 active Hub blueprints)"), line 70 ("Total active Hub nodes: 29"), line 72 ("HUB-32 ... no blueprint file exists; the inventory tracks it but it appears as a future addition. Excluded from this DAG until the file lands"), line 711 ("Until then, this DAG remains at 150 edges and 29 active Hub nodes. The next plausible growth event is the publication of `Architecture/Hub/HUB-32.md`"). All STALE post-A1. |
| Edge counts (76 Hub→Hub + 74 Hub→Core = 150)? | Internally consistent (Mermaid shows 76 + 74 edges). |
| References old filename `CORE-DEPENDENCY-DAG.md`? | **YES** — 2 stale references (line 420 Mermaid subgraph label, line 668 Mermaid legend). STALE per S-034. |
| Internal self-correction text (lines 161-200)? | Yes — the file contains a stream-of-consciousness recounting of the ↓-only edge decomposition, ending with "let me just leave the count as 76 with the table being authoritative". This is unusual documentation hygiene for a ratified governance artifact; it should be cleaned up. |
| Decomposition numbers in §2.1 reconcile? | The §2.1 table lists 76 rows (verified by spot-check). |

### §1.5 HUB-VERIFIED-DAG.md (222 lines)

| Check | Result |
|---|---|
| Consistent with actual `packages/hub/` implementations? | **YES** — 10 verified edges (2 HUB-01 → CORE-02/CORE-10; 8 HUB-04 → CORE-02/03/04/09/10/16/18/19) match the actual composer.json files at `packages/hub/config/composer.json` (lines 12-13) and `packages/hub/identity/composer.json` (lines 15-22). ✅ |
| Hub count correct (post-A1)? | **NO** — multiple stale counts (new finding S-055): line 38 ("2 of 29 active Hub blueprints" — should be "2 of 30"), line 45 (lists 27 unimplemented, missing HUB-32 — should be 28), line 115 ("2 of 29 implemented — 6.9% depth" — should be "2 of 30 implemented — 6.7% depth"), line 200 ("29 (all active Hub blueprints)" — should be "30"). All STALE post-A1. |
| References old filename `CORE-DEPENDENCY-DAG.md`? | **YES** — 2 stale references (line 120 Mermaid subgraph label, line 156 Mermaid legend). STALE per S-034. |

### §1.6 DAG files referencing IDs not in lint validIds

| Check | Result |
|---|---|
| Do DAGs reference IDs not in validIds? | No — DAGs use `C01`..`C20` in Mermaid blocks (which the lint strips as fenced code, line 89 of run.php: `preg_replace('/```.*?```/s', '', $text)`). In prose tables they use `CORE-01`..`CORE-20`, `HUB-01`..`HUB-32` — all valid per `validIds` ranges. ✅ |
| Does CORE-DECLARED-DAG.md reference IDs not in validIds? | Spot-checked: all CORE-01..20, no spurious IDs. ✅ |
| Does HUB-DECLARED-DAG.md reference IDs not in validIds? | Spot-checked: HUB-01..31, CORE-01..20 — all valid. ✅ (HUB-32 is mentioned but as "excluded from this DAG" prose — not as a node ID.) |

---

## §2. Repository/build/artifact consistency findings

### §2.1 `scripts/generate-architecture-baseline-v2.py` (432 lines)

| Check | Result |
|---|---|
| Does the script run? | **YES** — `python3 scripts/generate-architecture-baseline-v2.py --output /tmp/baseline_test.md` succeeded; printed "Baseline written to: /tmp/baseline_test.md; Commit: 34ea867; Branch: main; Total implemented packages: 20; Total PHP source files: 201; Total PHP test files: 88; Total blueprints (declared): 104; Total ADRs: 21". ✅ |
| Output is reproducible? | **YES** — re-running produces identical output modulo the `**Generated:**` UTC timestamp line (line 243). |
| Stale references inside the script? | Yes — `check_index_discrepancies()` (lines 138-156) hard-codes "30 blueprints" → "should be 31" (line 154-155) as the expected INDEX.md §5.3 stale-count pattern, but post-A1 the correct count is 32, not 31. So this discrepancy-checker is itself stale — it would miss the actual stale count "30 blueprints" → "should be 32" in current INDEX.md. |

### §2.2 `download/ARCHITECTURE_BASELINE.md` vs HEAD

| Check | Result |
|---|---|
| File committed to git? | **YES** — `git ls-files download/ARCHITECTURE_BASELINE.md` returns the file. (Contradicts ADR-021 line 362's claim that it's "not committed (gitignored)". New finding S-063.) |
| Generated from which commit? | `ce27388` (PR #292, "Hub DAG Phase 2 — HUB-DECLARED-DAG + HUB-VERIFIED-DAG + HUB-04 update", committed 2026-10-01T09:17:18+01:00). |
| HEAD at audit? | `34ea867` (2026-10-02T20:44:09Z). |
| Stale by how many commits? | **10 commits** between `ce27388` and HEAD (`f34bdc5` PR #293, `08b0ce6` PR #294, `9336fe6` PR #295, `a80a62f` PR #296, `70de0a5` PR #297, `f282280` PR #298, `6ae3e13` PR #299, `2ecfb03` PR #301, `200479e` mystery, `34ea867` HEAD). |
| Diff summary | (a) **Total blueprints: 102 → 104** (HUB-32 + ESPOKE-19 added by PR #294); (b) **Hub count: 31 → 32** (HUB-32 added); (c) **Spoke/External: 18 → 19** (ESPOKE-19 added); (d) **Total PHP source files: 200 → 201** (ContainerException.php added by PR #301 A2); (e) **Container src files: 7 → 8** (same). |
| Conclusion | **STALE** — new finding S-061. The baseline is a generated snapshot, so staleness is somewhat by design, but the committed file is now 10 commits behind and no longer reflects the A1/A2 work that the A3 audit is verifying. |

### §2.3 Stale baseline generators (S-026 confirmation)

| File | mtime | Status |
|---|---|---|
| `scripts/generate-architecture-baseline-v2.py` | 2026-10-01 | Authoritative per ADR-021 line 361. ✅ |
| `scripts/generate-architecture-baseline.py` | 2026-09-24 | **Still present**. No deprecation header (first 5 lines say "M0 — Architecture Baseline Generator (Python equivalent)"). S-026 NOT remediated. |
| `scripts/generate-architecture-baseline.php` | 2026-09-24 | **Still present**. No deprecation header (first 5 lines say "M0 — Architecture Baseline Generator"). S-026 NOT remediated. |

### §2.4 `.github/workflows/architecture-boundary-lint.yml`

| Check | Result |
|---|---|
| Workflow passes locally? | **YES** — `python3 scripts/architecture-boundary-lint.py` returns 0 violations on 198 files scanned / 317 imports scanned. 19/19 regression tests pass in `scripts/test_architecture_boundary_lint.py`. ✅ |
| Path-filter references `scripts/architecture-boundary-lint.php` (lines 23, 30)? | **YES** — but the file does NOT exist in `scripts/`. Only the `.py` version exists. New finding S-060. The path filter is a harmless no-op, but the comment block at lines 62-65 explicitly claims "a PHP equivalent of this checker exists at scripts/architecture-boundary-lint.php for contractor environments where PHP 8.4 is the primary runtime" — FALSE. |
| Comment block at lines 51-59 says "Runs 13 tests"? | **STALE** — actually runs 19 tests (the test file has grown from 13 to 19 since this comment was written). Cosmetic but stale. |
| `legitimate_callers_seen: 0 / 6 expected` in output? | This is **correct behavior** — the script only populates `legitimate_callers_seen` when a service-locator violation IS detected on a file in `LEGITIMATE_RESOLVE_CALLERS`. If no violations are detected (the happy path), the list is empty. The A3-RUNTIME-76 report misinterpreted this as a stale list, but it is not. |

### §2.5 `.github/workflows/architecture-lint.yml` (the OTHER lint workflow, PHP-based)

| Check | Result |
|---|---|
| Workflow passes per worklog? | **YES** — A1 worklog confirms "architecture-lint: ✅ success (FIRST TIME PASSING since ADR-021 was merged!)" after PR #294. ✅ |
| PHP version in yml? | PHP 8.3 (line 24). Root `composer.json` requires `php ^8.4`. The lint script itself uses only basic PHP features that work in 8.3, so the version mismatch is harmless — but worth noting. |
| Lint script `Architecture/Verification/lint/run.php` checkStructure stale? | **YES** — lines 175-177 iterate `range(1, 30)` for Hub structural check (only requires HUB-01..HUB-30 to exist). Lines 182-184 iterate `range(1, 18)` for ESPOKE (only requires ESPOKE-01..ESPOKE-18). But `validIds` (line 60) accepts `range(1, 32)` for HUB and `range(1, 19)` for ESPOKE. So the lint would NOT catch deletion of HUB-31.md, HUB-32.md, or ESPOKE-19.md. New finding S-064. |

### §2.6 Other Architecture/ files referencing stale paths/filenames

| File | Stale reference | Severity |
|---|---|---|
| `Architecture/Core/CORE-CAPABILITY-DAG.md` | 3 references to `CORE-DEPENDENCY-DAG.md` (lines 427, 431, 481) — STALE per S-036. |
| `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` | Line 353 historical mention of "renamed from `CORE-DEPENDENCY-DAG.md`" — technically OK per S-044 (historical mention, optional remediation). Line 357 says "HUB-32 ... Ratified pending canonical publication" — STALE post-A1 (HUB-32.md exists at depth 1). New finding S-062. Line 358 says "ESPOKE-19 ... Ratified pending canonical publication" — STALE post-A1 (ESPOKE-19.md exists at depth 1). New finding S-062. Line 362 says "download/ARCHITECTURE_BASELINE.md | Generated artifact — evidence snapshot, not committed (gitignored, reproducible by running the script)" — FALSE (file IS committed per `git ls-files`). New finding S-063. |
| `Architecture/INDEX.md` | Line 14 ("Last verified against main: 2026-10-01") — STALE (HEAD is 2026-10-02). Line 227 ("Hub \| 31 declared (29 active + 2 superseded)") — STALE (should be "32 declared (30 active + 2 superseded)"). Line 234 ("reducing active Hub count to 29. HUB-32 AI Inference Hub ratified pending canonical publication (not yet counted in active inventory)") — STALE. Line 443 ("Hub tier (30 blueprints)") — STALE. Line 54 ("active Hub count = 32 − 2 superseded = 30") — CORRECT post-A1. Internal contradiction → new finding S-059. |
| `Architecture/CrossCutting/*` and `Architecture/Spoke/*` | Spot-checked; no stale references found. |

---

## §3. New findings (S-055 through S-065)

### S-055: HUB-VERIFIED-DAG.md stale Hub count (29 instead of 30 post-A1)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** HUB-VERIFIED-DAG.md still claims "2 of 29 active Hub blueprints" in 4 places (lines 38, 45, 115, 200) despite A1 (PR #294, 2026-10-01) adding HUB-32 as canonical at depth 1. After A1: 32 total Hub files − 2 superseded (HUB-10, HUB-25) = **30 active**, of which 2 are implemented (HUB-01, HUB-04) and 28 are greenfield. The DAG was authored 2026-10-01 in PR #292 (pre-A1) and was correct at that time, but is now stale.
- **Evidence:** `Architecture/Hub/HUB-VERIFIED-DAG.md` lines 38, 45, 115, 200, 217.
- **Affected artifact:** `Architecture/Hub/HUB-VERIFIED-DAG.md`
- **Contract violated:** ADR-021 §13 (HUB-32 ratified canonical); S-033 verification condition ("HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)") implies the sister HUB-VERIFIED-DAG.md must also be updated.
- **Root cause:** HUB-VERIFIED-DAG.md was authored in PR #292 (Hub DAG Phase 2) on 2026-10-01 — the same day A1 (PR #294) added HUB-32. The DAG file was not regenerated after A1 landed.
- **Remediation:** Find-replace "29 active" → "30 active" in HUB-VERIFIED-DAG.md (4 occurrences); update line 45's enumeration to include HUB-32; update line 115's percentage "6.9% depth" → "6.7% depth" (2/30 vs 2/29).
- **Verification test:** `rg "29 active|2 of 29|6.9%" Architecture/Hub/HUB-VERIFIED-DAG.md` returns zero matches; line 38 reads "2 of 30 active"; line 115 reads "6.7% depth".
- **Disposition:** Open
- **Target phase:** MEDIUM-batch (bundle with S-056)

### S-056: HUB-DECLARED-DAG.md stale HUB-32 references (HUB-32.md now exists)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** HUB-DECLARED-DAG.md explicitly excludes HUB-32 from its node set in 4 places (lines 22, 34, 70, 72, 711) on the basis that "HUB-32 ... no blueprint file exists; the inventory tracks it but it appears as a future addition. Excluded from this DAG until the file lands." But HUB-32.md was authored in A1 (PR #294, 2026-10-01) at depth 1. The DAG should now include HUB-32 in its node set (30 active) and add HUB-32's declared edges (currently unknown — HUB-32.md doesn't enumerate formal Upward/Downward sections per A1's minimal depth-1 blueprint).
- **Evidence:** `Architecture/Hub/HUB-DECLARED-DAG.md` lines 22, 34, 70, 72, 711.
- **Affected artifact:** `Architecture/Hub/HUB-DECLARED-DAG.md`
- **Contract violated:** S-033 verification condition explicitly requires "HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)".
- **Root cause:** Same as S-055 — DAG was authored in PR #292, HUB-32 landed in PR #294 on the same day, DAG not regenerated.
- **Remediation:** (a) Add HUB-32 row to §1 node set table; (b) update line 22 "29 active" → "30 active"; (c) update line 34 heading "## §1. Node set (29 active Hub blueprints)" → "30 active Hub blueprints"; (d) update line 70 "Total active Hub nodes: 29" → "30"; (e) delete line 72 (HUB-32 exclusion rationale — no longer applicable); (f) update line 711 to remove "The next plausible growth event is the publication of `Architecture/Hub/HUB-32.md`" (it has been published); (g) optionally add HUB-32's declared edges (none enumerated in A1's minimal depth-1 blueprint — HUB-32.md needs Upward/Downward sections to derive declared edges; this is a follow-up to A1's depth-1 publication).
- **Verification test:** `rg "29 active|ratified pending canonical|no blueprint file exists" Architecture/Hub/HUB-DECLARED-DAG.md` returns zero matches; line 34 reads "## §1. Node set (30 active Hub blueprints)"; HUB-32 row appears in §1 table.
- **Disposition:** Open
- **Target phase:** MEDIUM-batch (bundle with S-055; closes S-033 when complete)

### S-057: CORE-DECLARED-DAG.md edge classification inconsistencies (C18→C06 misclassified)
- **Severity:** HIGH
- **Category:** Coherence
- **Description:** The edge `C18 (Kernel) → C06 (Router)` is misclassified in multiple ways in CORE-DECLARED-DAG.md:
  1. **Line 50 prose table classification vs evidence column contradiction:** Table row classifies as `DECLARED_ONLY`, but the Evidence column literally says "Kernel→Router (verified in code but not declared in blueprint)". By the four-status definition, "verified in code but not declared in blueprint" is `UNDECLARED_VERIFIED`, not `DECLARED_ONLY`. The line 56-64 summary says `UNDECLARED_VERIFIED = 0`, directly contradicting the line 50 evidence.
  2. **Table classification vs Mermaid classification contradiction:** The Mermaid graph (line 108) shows `C18 --> C06` as solid (`-->` is the verified style per line 120 classDef). But the prose table classifies it as DECLARED_ONLY (which should be dotted `-.->` per line 121 classDef). Internal inconsistency between the table and the Mermaid.
  3. **Actual composer.json evidence:** `packages/core/kernel/composer.json` line 17 requires `"sovereign-stack/core-router": "*"`. This is verified-in-composer evidence — the edge IS VERIFIED, not DECLARED_ONLY.
  4. **Edge direction labeling confusion:** The Evidence column says "Kernel→Router" (i.e., Kernel depends on Router, so the edge direction per the §0 convention "X --> Y means Y consumes X" should be `C06 --> C18` — Router consumed by Kernel). But the Source/Target columns list `C18 | C06`, which under the same convention means `C06 consumes C18` (Router depends on Kernel) — opposite direction. The Evidence column and the Source/Target columns contradict each other on direction.
- **Description of secondary math inconsistency:** The line 41 declared-only table says "(remaining 22 edges — full list in blueprint Upward/Downward sections)". 9 explicitly listed + 22 = 31 declared-only, but the line 61 summary says DECLARED_ONLY = 32. Off-by-one in the math.
- **Evidence:**
  - `Architecture/Core/CORE-DECLARED-DAG.md` line 50 (table row), line 56-64 (summary), line 108 (Mermaid), line 54 (remaining 22 edges)
  - `packages/core/kernel/composer.json` line 17: `"sovereign-stack/core-router": "*"`
  - `Architecture/Core/CORE-VERIFIED-DAG.md` line 563 (Mermaid shows same `C18 --> C06` — also wrong direction per its own §6.1 analysis)
- **Affected artifact:** `Architecture/Core/CORE-DECLARED-DAG.md` (table + Mermaid + summary); `Architecture/Core/CORE-VERIFIED-DAG.md` (Mermaid)
- **Contract violated:** ADR-021 §11 (two-DAG governance model — VERIFIED/DECLARED_ONLY/UNDECLARED_VERIFIED/INVALID four-status categorization must be applied consistently); the four-status summary math must reconcile.
- **Root cause:** C18→C06 has been a chronic confusion point — see CORE-VERIFIED-DAG.md §6.1 line 577 ("this single inverted edge was the one SAAI's prior audit caught ('missed C18 → C06') but the auditor did not catch that the direction is also wrong"). The declared DAG was authored with the same direction error and an inconsistent status classification.
- **Remediation:** (a) Reclassify C18→C06 from `DECLARED_ONLY` to `VERIFIED` (composer.json evidence: kernel requires sovereign-stack/core-router); (b) update four-status summary: VERIFIED=14 (was 13), DECLARED_ONLY=31 (was 32), UNDECLARED_VERIFIED=0, INVALID=0, total=45; (c) fix "remaining 22 edges" → "remaining 22 edges" is now wrong (since the table has 9 listed edges + 22 remaining = 31, not 32) → either change to "remaining 23 edges" or add the missing 23rd edge explicitly; (d) fix the Mermaid direction for C18→C06 — currently `C18 --> C06` (Router consumes Kernel, wrong); should be `C06 --> C18` (Kernel consumes Router, correct per §0 convention); (e) apply the same direction fix to CORE-VERIFIED-DAG.md line 563 Mermaid.
- **Verification test:** (1) `packages/core/kernel/composer.json` requires `sovereign-stack/core-router` confirmed; (2) CORE-DECLARED-DAG.md table row for C18→C06 reads `VERIFIED` (not `DECLARED_ONLY`); (3) four-status summary mathematically reconciles (14 + 31 + 0 + 0 = 45); (4) Mermaid line 108 reads `C06 --> C18` (not `C18 --> C06`); (5) CORE-VERIFIED-DAG.md Mermaid line 563 reads `C06 --> C18`.
- **Disposition:** Open
- **Target phase:** HIGH-batch (the edge-classification data is the authoritative input for build-order wave computation per CORE-BUILD-ORDER.md §0; an incorrect classification would propagate into wrong wave assignment)

### S-058: CORE-VERIFIED-DAG.md + CORE-DECLARED-DAG.md Mermaid direction error (§6.1 admits but graph not corrected)
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** CORE-VERIFIED-DAG.md §6.1 (line 577) explicitly acknowledges that the edge direction `C18 --> C06` is wrong: "Should be `C06 --> C18`. Blueprint CORE-06 lists C18 in its 'Upward' section but the description ('Kernel owns the Router instance, triggers AttributeRouteLoader during boot') is the inverse — Kernel *owns* the Router, i.e., Kernel *depends on* Router." But the same file's §4 Mermaid (line 563) STILL shows `C18 --> C06` (wrong direction). The file documents the error in its own analysis section but ships the wrong direction in its authoritative graph.
  - The same wrong direction appears in CORE-DECLARED-DAG.md §3 Mermaid (line 108): `C18 --> C06`.
  - §6.5 defect tally (line 612-621) acknowledges "Edges with WRONG direction | 4 (C18→C06, C15→C14, C17→C13, C20→C13)" but the Mermaid graph was not corrected for any of the 4.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` line 563 (§4 Mermaid `C18 --> C06`), line 577 (§6.1 analysis "Should be C06 --> C18"), line 578 (§6.1 analysis "Should be C14 --> C15"), line 579 (§6.1 analysis "Should be C13 --> C17"), line 580 (§6.1 analysis "Should be C13 --> C20")
  - `Architecture/Core/CORE-DECLARED-DAG.md` line 108 (Mermaid `C18 --> C06`), line 106 (Mermaid `C15 --> C14`), line 113 (Mermaid `C17 --> C13`), line 114 (Mermaid `C20 --> C13`)
- **Affected artifact:** `Architecture/Core/CORE-VERIFIED-DAG.md` §4 Mermaid; `Architecture/Core/CORE-DECLARED-DAG.md` §3 Mermaid
- **Contract violated:** ADR-021 §11 (two-DAG governance — Mermaid graph is the canonical representation; analysis section identifying errors is not a substitute for fixing them in the canonical graph)
- **Root cause:** §6.1 was authored as a "Cross-check against INDEX.md §5.2" — the analysis identified 4 wrong-direction edges in INDEX.md §5.2, but those same 4 wrong directions were also in §4 of CORE-VERIFIED-DAG.md and §3 of CORE-DECLARED-DAG.md (copied verbatim from INDEX.md §5.2 during DAG derivation). The author caught the error in INDEX.md §5.2 but did not propagate the fix to the new DAG files.
- **Remediation:** In CORE-VERIFIED-DAG.md §4 Mermaid: change `C18 --> C06` → `C06 --> C18`; change `C15 --> C14` → `C14 --> C15`; change `C17 --> C13` → `C13 --> C17`; change `C20 --> C13` → `C13 --> C20`. Apply same fixes to CORE-DECLARED-DAG.md §3 Mermaid (lines 106, 108, 113, 114). The §6.1 analysis section can be updated to note the fixes were applied, or kept as historical analysis.
- **Verification test:** `rg "C18 --> C06|C15 --> C14|C17 --> C13|C20 --> C13" Architecture/Core/CORE-VERIFIED-DAG.md Architecture/Core/CORE-DECLARED-DAG.md` returns zero matches; the 4 inverted edges now read in the correct direction per §0 convention.
- **Disposition:** Open
- **Target phase:** MEDIUM-batch (bundle with S-057 — same root cause, same files)

### S-059: INDEX.md internally contradictory on HUB-32 active count
- **Severity:** MEDIUM
- **Category:** Coherence
- **Description:** INDEX.md has mutually contradictory statements about the active Hub count after A1:
  - **Line 54 (CORRECT post-A1):** "active Hub count = 32 − 2 superseded = 30"
  - **Line 227 (STALE pre-A1):** "Hub | 31 declared (29 active + 2 superseded) | 0 | **31 declared** (29 active per ADR-021)"
  - **Line 234 (STALE pre-A1):** "reducing active Hub count to 29. HUB-32 AI Inference Hub ratified pending canonical publication (not yet counted in active inventory). Total canonical: **102** declared blueprints"
  - **Line 443 (STALE pre-A1):** "Hub tier (30 blueprints)"
  
  Per A1: 32 total Hub files − 2 superseded (HUB-10, HUB-25) = **30 active**; 2 implemented + 28 greenfield. Total declared blueprints = 20 Core + 32 Hub + 27 ISPOKE + 19 ESPOKE + 1 BRIDGE + 5 DEPLOY = **104** (not 102).
- **Evidence:** `Architecture/INDEX.md` lines 14, 54, 227, 234, 443.
- **Affected artifact:** `Architecture/INDEX.md`
- **Contract violated:** ADR-021 §13 (HUB-32 ratified canonical); INDEX.md §2 authority (canonical ID → component map must be consistent across sections).
- **Root cause:** A1 (PR #294) updated INDEX.md line 54 to reflect HUB-32 canonical, but did not propagate the update to lines 227, 234, 443 (older sections that retain the pre-A1 counts).
- **Remediation:** (a) Update line 227 "31 declared (29 active + 2 superseded)" → "32 declared (30 active + 2 superseded)"; (b) update line 234 to remove "HUB-32 ... not yet counted in active inventory" and replace with "HUB-32 canonical at depth 1 (implementation deferred)"; update "Total canonical: 102" → "Total canonical: 104"; (c) update line 443 "Hub tier (30 blueprints)" → "Hub tier (32 blueprints)" (or "30 active blueprints"); (d) update line 14 "Last verified against main: 2026-10-01" → "2026-10-02".
- **Verification test:** `rg "29 active|30 blueprints|102 declared|ratified pending canonical" Architecture/INDEX.md` returns zero matches; all Hub count references in INDEX.md consistently read "32 declared (30 active + 2 superseded)".
- **Disposition:** Open
- **Target phase:** MEDIUM-batch

### S-060: scripts/architecture-boundary-lint.php referenced in workflow yml but doesn't exist
- **Severity:** LOW
- **Category:** Coherence
- **Description:** `.github/workflows/architecture-boundary-lint.yml` references a non-existent PHP file in two ways:
  1. **Path filter (lines 23, 30):** Lists `scripts/architecture-boundary-lint.php` as a path that triggers the workflow on push/PR. The file does NOT exist (only the Python version `scripts/architecture-boundary-lint.py` exists). The path filter is harmless (no commits will ever match a non-existent path), but it's misleading documentation.
  2. **Comment block (lines 62-65):** Says "a PHP equivalent of this checker exists at scripts/architecture-boundary-lint.php for contractor environments where PHP 8.4 is the primary runtime. The Python version is used in CI for portability; both versions produce identical output." This is FALSE — the PHP version does not exist.
- **Evidence:** `.github/workflows/architecture-boundary-lint.yml` lines 23, 30, 62-65. `ls scripts/architecture-boundary-lint*` returns only `scripts/architecture-boundary-lint.py`.
- **Affected artifact:** `.github/workflows/architecture-boundary-lint.yml`
- **Contract violated:** SPEC-001 §41 (workflow documentation should match reality)
- **Root cause:** The PHP equivalent was planned but never authored. The workflow + comment were written assuming it would land in the same PR; the PHP file never did.
- **Remediation:** Either (a) author `scripts/architecture-boundary-lint.php` matching the Python version's output, or (b) remove `scripts/architecture-boundary-lint.php` from the path filters (lines 23, 30) and remove the comment block at lines 62-65.
- **Verification test:** Either the PHP file exists at `scripts/architecture-boundary-lint.php`, or the yml has no reference to it.
- **Disposition:** Open
- **Target phase:** LOW-batch

### S-061: download/ARCHITECTURE_BASELINE.md is stale (10 commits behind HEAD)
- **Severity:** LOW
- **Category:** Build-artifact drift
- **Description:** `download/ARCHITECTURE_BASELINE.md` was generated from commit `ce27388` (PR #292, "Hub DAG Phase 2", 2026-10-01 09:17 UTC). Current HEAD is `34ea867` (2026-10-02 20:44 UTC). There are 10 commits between baseline and HEAD including:
  - PR #293 (`f34bdc5`): A0 state model + shortcomings register + audit
  - PR #294 (`08b0ce6`): A1 — canonical HUB-32 + ESPOKE-19 + lint + INDEX + HUB-33 fix
  - PR #295 (`9336fe6`): A2-prep — Shape C pulse() contract
  - PR #296 (`a80a62f`): Blind-Spot Doctrine
  - PR #297 (`70de0a5`): Blind-Spot Doctrine references undefined HUB-33 token fix
  - PR #298 (`f282280`): embed Blind-Spot Awareness in EVERY architectural document
  - PR #299 (`6ae3e13`): contributor awareness statement
  - PR #301 (`2ecfb03`): A2 — Fiber isolation for pulse()
  - `200479e` (UUID-titled commit)
  - `34ea867` (UUID-titled commit, HEAD)
  
  Diff (baseline vs fresh regeneration at HEAD):
  - Total blueprints: **102 → 104** (HUB-32 + ESPOKE-19 added by PR #294)
  - Hub count: **31 → 32** (HUB-32 added)
  - Spoke/External: **18 → 19** (ESPOKE-19 added)
  - Total PHP source files: **200 → 201** (ContainerException.php added by PR #301 A2)
  - Container src files: **7 → 8** (ContainerException.php added)
  - "Declared but not implemented" Hub: **29 → 30**
  - "Declared but not implemented" Spoke: **41 → 42**
- **Evidence:** `download/ARCHITECTURE_BASELINE.md` line 5-7 ("Generated: 2026-10-02T08:30:59Z; Commit: ce27388..."). `git rev-parse HEAD` returns `34ea867...`.
- **Affected artifact:** `download/ARCHITECTURE_BASELINE.md`
- **Contract violated:** ADR-021 line 362 (the baseline is "reproducible by running the script" — but the committed file is not the result of running the script at HEAD)
- **Root cause:** The baseline was last regenerated on 2026-10-02 08:30 UTC and committed; subsequent commits (especially A1 #294 and A2 #301) were not accompanied by a baseline regeneration. The A1/A2 PRs were docs-only / docs-heavy and didn't trigger the baseline regeneration workflow.
- **Remediation:** Re-run `python3 scripts/generate-architecture-baseline-v2.py` at HEAD `34ea867` and commit the regenerated `download/ARCHITECTURE_BASELINE.md`. (Optionally: add a CI check that regenerates the baseline and fails if the committed version differs from the freshly-generated one.)
- **Verification test:** `download/ARCHITECTURE_BASELINE.md` line 5-7 reads "Generated: <timestamp>; Commit: 34ea867..." (matching HEAD).
- **Disposition:** Open
- **Target phase:** LOW-batch (housekeeping)

### S-062: ADR-021 lines 357-358 stale ("ratified pending canonical publication" — HUB-32 and ESPOKE-19 are now canonical)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** ADR-021 §"Relationship to Other Documents" (lines 357-358) still describes HUB-32 and ESPOKE-19 as "Ratified pending canonical publication — blueprint file to be created during implementation phase". Both blueprints were authored in A1 (PR #294, 2026-10-01) at depth 1 — they exist on disk. Per the A1 worklog: "Tech lead decision: HUB-32 and ESPOKE-19 become canonical at depth 1 (implementation deferred)".
- **Evidence:**
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 357: "Architecture/Hub/HUB-32.md | **Ratified pending canonical publication** — blueprint file to be created during implementation phase."
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 358: "Architecture/Spoke/External/ESPOKE-19.md | **Ratified pending canonical publication** — blueprint file to be created during implementation phase."
  - `Architecture/Hub/HUB-32.md` exists (87 lines, depth 1, "Status: 📝 Canonical (depth 1 — interface declared, implementation deferred)")
  - `Architecture/Spoke/External/ESPOKE-19.md` exists at depth 1 (per A1 worklog: "Created ESPOKE-19.md (90 lines) — Eloq, depth 1, 14 ISPOKEs + HUB-32/HUB-04, neutral parity")
- **Affected artifact:** `Architecture/ADRs/ADR-021-tier-stratified-build-order.md`
- **Contract violated:** ADR-021 §13 (HUB-32 ratified canonical); A1's tech-lead decision (canonical identity ≠ implementation)
- **Root cause:** ADR-021 was amended in PR #287 (Amendment 1, 2026-10-01) to ratify HUB-32/ESPOKE-19 as "pending canonical publication". A1 (PR #294, same day) then published the canonical blueprints but did not update ADR-021's "Relationship to Other Documents" table.
- **Remediation:** Update ADR-021 line 357 to "Architecture/Hub/HUB-32.md | **Canonical at depth 1** (interface declared, implementation deferred) — authored 2026-10-01 per A1 (PR #294)." Same for line 358 (ESPOKE-19).
- **Verification test:** `rg "ratified pending canonical publication" Architecture/ADRs/ADR-021-tier-stratified-build-order.md` returns zero matches.
- **Disposition:** Open
- **Target phase:** LOW-batch (bundle with S-055/S-056 — same root cause: A1 didn't propagate canonical-status updates)

### S-063: ADR-021 line 362 contradicts git state ("not committed (gitignored)" but file IS committed)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** ADR-021 line 362 says: "download/ARCHITECTURE_BASELINE.md | Generated artifact — evidence snapshot, not committed (gitignored, reproducible by running the script)." But `git ls-files download/ARCHITECTURE_BASELINE.md` returns the file — it IS committed. The `.gitignore` file does NOT ignore `download/ARCHITECTURE_BASELINE.md` (or anything under `download/`). The ADR-021 statement is FALSE.
- **Evidence:**
  - `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 362
  - `git ls-files download/ARCHITECTURE_BASELINE.md` returns `download/ARCHITECTURE_BASELINE.md`
  - `.gitignore` (root) does not contain `download/` or `ARCHITECTURE_BASELINE.md`
- **Affected artifact:** `Architecture/ADRs/ADR-021-tier-stratified-build-order.md` line 362; OR `download/ARCHITECTURE_BASELINE.md` (if the intent was for the file to be gitignored, the file should be untracked and added to .gitignore)
- **Contract violated:** ADR-021 §"Relationship to Other Documents" (statements about other artifacts should match reality)
- **Root cause:** ADR-021 was authored with the intent that the baseline be a non-committed snapshot (regenerated on demand). But the baseline file was committed in PR #285 (when v2 was added) and never untracked. The ADR-021 statement was never reconciled with the actual commit state.
- **Remediation:** Either (a) `git rm --cached download/ARCHITECTURE_BASELINE.md` + add `download/ARCHITECTURE_BASELINE.md` to `.gitignore` (makes ADR-021 statement true), or (b) update ADR-021 line 362 to read "Committed evidence snapshot — reproducible by running the script. May drift between commits; regenerate before relying on the numbers."
- **Verification test:** Either `git ls-files download/ARCHITECTURE_BASELINE.md` returns empty (and .gitignore contains the path), OR ADR-021 line 362 reads "Committed evidence snapshot — reproducible by running the script."
- **Disposition:** Open
- **Target phase:** LOW-batch

### S-064: architecture-lint checkStructure stale (requires HUB-01..30 + ESPOKE-01..18, missing HUB-31/32 + ESPOKE-19)
- **Severity:** MEDIUM
- **Category:** Latent-defect
- **Description:** `Architecture/Verification/lint/run.php` `checkStructure()` (lines 169-218) iterates:
  - Line 175-177: `foreach (range(1, 30) as $n) { $expected[] = sprintf('Hub/HUB-%02d.md', $n); }` — only requires HUB-01 through HUB-30 to exist as files.
  - Lines 182-184: `foreach (range(1, 18) as $n) { $expected[] = sprintf('Spoke/External/ESPOKE-%02d.md', $n); }` — only requires ESPOKE-01 through ESPOKE-18.
  
  But `buildValidIds()` (line 56-73) accepts `range(1, 32)` for HUB (HUB-31, HUB-32 are valid references) and `range(1, 19)` for ESPOKE (ESPOKE-19 is a valid reference). So:
  - A reference to HUB-32 in a blueprint is allowed by `checkReferences` (line 60: `'HUB' => range(1, 32)`).
  - But the existence of HUB-32.md as a file is NOT enforced by `checkStructure`.
  - If a future PR deletes HUB-32.md (or HUB-31.md, or ESPOKE-19.md) while leaving HUB-31/32/ESPOKE-19 references elsewhere, the lint would NOT flag the missing file.
  
  Also: line 71-72 is dead code:
  ```php
  // Proposed HUB-31 is referenced (as "pending") but not yet counted in §4.
  $this->validIds['HUB-31'] = true;
  ```
  This line is a no-op because `range(1, 32)` already includes HUB-31. The comment is also stale — HUB-31 is now ACTIVE per ADR-011 (Real-Time Analytics), not "pending".
- **Evidence:**
  - `Architecture/Verification/lint/run.php` line 60 (`'HUB' => range(1, 32)` — accepts HUB-31, HUB-32 as valid)
  - `Architecture/Verification/lint/run.php` line 62 (`'ESPOKE' => range(1, 19)` — accepts ESPOKE-19 as valid)
  - `Architecture/Verification/lint/run.php` line 175 (`foreach (range(1, 30) as $n)` — only checks HUB-01..HUB-30 exist)
  - `Architecture/Verification/lint/run.php` line 183 (`foreach (range(1, 18) as $n)` — only checks ESPOKE-01..ESPOKE-18 exist)
  - `Architecture/Verification/lint/run.php` line 71-72 (dead code)
  - `Architecture/Hub/HUB-31.md` exists (Real-Time Analytics, ADR-011)
  - `Architecture/Hub/HUB-32.md` exists (AI Inference Hub, A1 PR #294)
  - `Architecture/Spoke/External/ESPOKE-19.md` exists (Eloq, A1 PR #294)
- **Affected artifact:** `Architecture/Verification/lint/run.php`
- **Contract violated:** ADR-021 §13 (HUB-32 canonical); ADR-011 (HUB-31 active); S-033 remediation contract ("Add HUB-32 to the architecture-lint expected structural list (line 175: extend `range(1, 30)` to include `HUB-32`)")
- **Root cause:** When A1 added HUB-32 (PR #294) and ESPOKE-19, the `validIds` map was extended (lines 60, 62) to allow references to them, but the `checkStructure()` function's expected-file list (lines 175, 183) was NOT extended. A1's worklog explicitly says "Extended lint validIds: HUB range 1-32" — but did not say "Extended lint checkStructure to require HUB-32.md file existence".
- **Remediation:** (a) Change line 175 to `foreach (range(1, 32) as $n)` (require HUB-31.md, HUB-32.md to exist); (b) change line 183 to `foreach (range(1, 19) as $n)` (require ESPOKE-19.md to exist); (c) delete dead code at lines 71-72; (d) update ADR range comment on line 60 to remove "Proposed HUB-31" wording (HUB-31 is active per ADR-011, not proposed).
- **Verification test:** Delete `Architecture/Hub/HUB-32.md` temporarily; run `php Architecture/Verification/lint/run.php`; expect exit code 1 with "missing file 'Hub/HUB-32.md'". Restore the file; expect exit code 0.
- **Disposition:** Open
- **Target phase:** MEDIUM-batch (this is a latent defect — currently harmless because all files exist, but if any of HUB-31/32/ESPOKE-19 is deleted in a future PR the lint would silently miss it)

### S-065: CORE-VERIFIED-DAG.md §7 implementation-status table stale for C02 (says 7 src files, actually 8)
- **Severity:** LOW
- **Category:** Coherence
- **Description:** CORE-VERIFIED-DAG.md §7 line 632 says "C02 | YES | 7 | 17 | 0.4.0.0 | 2 | PSR-11 conformance suite passing" — but `packages/core/container/src/` now has 8 PHP files (ContainerException.php was added by A2 PR #301 on 2026-10-02). The src-file count is stale by 1.
- **Evidence:**
  - `Architecture/Core/CORE-VERIFIED-DAG.md` line 632 (says "C02 | YES | 7 | 17 | 0.4.0.0 | 2 |")
  - `ls packages/core/container/src/*.php | wc -l` returns 8 (CircularDependencyException, CompilerPassInterface, Container, ContainerBuilderInterface, ContainerException, ContainerInterface, NotFoundException, ServiceDefinition)
  - `git log --oneline packages/core/container/src/ContainerException.php` shows it was added in commit 2ecfb03 (PR #301, "A2 — Fiber isolation for pulse() (Shape C)")
- **Affected artifact:** `Architecture/Core/CORE-VERIFIED-DAG.md` line 632
- **Contract violated:** CORE-VERIFIED-DAG authority (implementation reality — "direct inspection of each implemented package's composer.json + each implemented package's src/*.php use statements")
- **Root cause:** A2 (PR #301) added ContainerException.php to support the new `ContainerException` thrown when `pulse()` is called outside a Fiber (per Shape C contract). The CORE-VERIFIED-DAG §7 implementation-status table was not regenerated.
- **Remediation:** Update CORE-VERIFIED-DAG.md §7 line 632 src-files column for C02 from `7` to `8`.
- **Verification test:** `ls packages/core/container/src/*.php | wc -l` returns 8; CORE-VERIFIED-DAG.md line 632 reads "C02 | YES | 8 | 17 | 0.4.0.0 | 2 |".
- **Disposition:** Open
- **Target phase:** LOW-batch (cosmetic — the table is a secondary artifact; the authoritative count is `ls packages/core/container/src/`)

---

## §4. Confirmation of pre-existing findings (NOT remediated)

| ID | Description | Register status | Actual status (post-A3 audit) |
|---|---|---|---|
| S-026 | Old `generate-architecture-baseline.py` v1 + .php still exist alongside v2 | Open | **Not remediated** — both v1 files still exist, no deprecation headers added. |
| S-033 | HUB-32 pending canonical publication (no blueprint file exists) | Open | **Partially remediated** — HUB-32.md exists at depth 1 (A1 PR #294). But HUB-DECLARED-DAG.md §1 node set still says "29 active" and excludes HUB-32 (S-056). Verification condition NOT met. |
| S-034 | HUB-VERIFIED-DAG.md + HUB-DECLARED-DAG.md reference nonexistent CORE-DEPENDENCY-DAG.md | Open | **Not remediated** — 4 references still present (HUB-VERIFIED-DAG.md lines 120, 156; HUB-DECLARED-DAG.md lines 420, 668). |
| S-035 | CORE-VERIFIED-DAG.md footer says "End of CORE-DEPENDENCY-DAG.md" | Open | **Not remediated** — line 666 still says "End of CORE-DEPENDENCY-DAG.md". |
| S-036 | CORE-CAPABILITY-DAG.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences) | Open | **Not remediated** — 3 references still present (lines 427, 431, 481). |
| S-037 | CORE-BUILD-ORDER.md references nonexistent CORE-DEPENDENCY-DAG.md (3 occurrences) | Open | **Not remediated** — 3 references still present (lines 288, 328, 334). |
| S-044 | ADR-021 line 344 references "renamed from CORE-DEPENDENCY-DAG.md" (historical, but echoes old filename) | Open (cosmetic, optional fix) | **Not remediated** — line 353 still says "renamed from `CORE-DEPENDENCY-DAG.md`" (historical mention, technically OK). |

---

## §5. Findings summary table

| ID | Severity | Category | One-line description |
|---|---|---|---|
| S-055 | MEDIUM | Coherence | HUB-VERIFIED-DAG.md stale Hub count (29 instead of 30 post-A1) |
| S-056 | MEDIUM | Coherence | HUB-DECLARED-DAG.md stale HUB-32 references (HUB-32.md now exists) — blocks S-033 closure |
| S-057 | **HIGH** | Coherence | CORE-DECLARED-DAG.md C18→C06 misclassified (DECLARED_ONLY but evidence says UNDECLARED_VERIFIED; actual composer.json verifies edge); four-status math doesn't reconcile |
| S-058 | MEDIUM | Coherence | CORE-VERIFIED-DAG.md + CORE-DECLARED-DAG.md Mermaid direction errors for 4 edges (§6.1 admits but graph not fixed) |
| S-059 | MEDIUM | Coherence | INDEX.md internally contradictory on HUB-32 active count (line 54 says 30; lines 227, 234, 443 say 29/30/102) |
| S-060 | LOW | Coherence | `.github/workflows/architecture-boundary-lint.yml` references non-existent `scripts/architecture-boundary-lint.php` (path filter + false comment) |
| S-061 | LOW | Build-artifact drift | `download/ARCHITECTURE_BASELINE.md` stale by 10 commits (post-A1/A2 changes not reflected) |
| S-062 | LOW | Coherence | ADR-021 lines 357-358 stale ("ratified pending canonical publication" — HUB-32 and ESPOKE-19 are now canonical) |
| S-063 | LOW | Coherence | ADR-021 line 362 says baseline is "not committed (gitignored)" but file IS committed |
| S-064 | MEDIUM | Latent-defect | architecture-lint `checkStructure` only requires HUB-01..30 + ESPOKE-01..18 (not HUB-31/32 + ESPOKE-19); dead HUB-31 line 71-72 |
| S-065 | LOW | Coherence | CORE-VERIFIED-DAG.md §7 C02 src-file count stale (says 7, actually 8 after A2 added ContainerException.php) |

**Tally:** 11 new findings (1 HIGH, 5 MEDIUM, 5 LOW). No new FATALs.

---

## §6. Blind-spot report — what A3 discovered that was not known before A3 began

1. **The A1 → A3 propagation gap.** A1 (PR #294) was a successful PR that made HUB-32 and ESPOKE-19 canonical and updated INDEX.md line 54 — but did not propagate the canonical-status updates to (a) HUB-DECLARED-DAG.md (S-056), (b) HUB-VERIFIED-DAG.md (S-055), (c) ADR-021's "Relationship to Other Documents" table (S-062), (d) the architecture-lint `checkStructure` expected-file list (S-064). This is the single largest category of new findings. The A1 → A3 propagation gap creates a **closure-rule hazard for S-033**: S-033's verification condition explicitly requires "HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)" — this condition is NOT met, so S-033 cannot move to Closed at this A3 audit despite the A1 worklog's "✅ PASSED" claim for S-001/S-002 (which are about lint passing, not about S-033's full closure conditions).

2. **The C18→C06 edge-classification mess in CORE-DECLARED-DAG.md.** This edge has been a chronic confusion point (CORE-VERIFIED-DAG.md §6.1 admits the direction is wrong; the A3-RUNTIME-76 report didn't audit this because it was scoped to runtime/concurrency). The A3-ARCH-DAG audit found THREE independent inconsistencies around this single edge: (a) prose table classifies as DECLARED_ONLY but evidence column says "verified in code not declared in blueprint" (which is UNDECLARED_VERIFIED by definition); (b) Mermaid graph shows it as solid (verified style) but table classifies as DECLARED_ONLY; (c) actual `packages/core/kernel/composer.json` requires `sovereign-stack/core-router` → the edge IS VERIFIED in composer. The four-status summary math doesn't reconcile: "remaining 22 edges" + 9 listed = 31, not 32; VERIFIED count should be 14 (with C18→C06 reclassified), not 13. This is the highest-severity new finding (S-057) because edge classification is the authoritative input for the build-order wave computation in CORE-BUILD-ORDER.md §0.

3. **The Mermaid direction error admitted-but-not-fixed.** CORE-VERIFIED-DAG.md §6.1 contains a 4-row analysis table acknowledging that 4 edges in INDEX.md §5.2 have the wrong direction. But the same 4 wrong directions appear in CORE-VERIFIED-DAG.md's own §4 Mermaid AND in CORE-DECLARED-DAG.md's §3 Mermaid — neither was corrected. The analysis section identified the error but the canonical graph was not updated. This is S-058 — the audit found that DAG derivation can copy errors verbatim from a source document AND simultaneously document the error in the same file without reconciling.

4. **ADR-021's "not committed (gitignored)" claim is FALSE.** ADR-021 line 362 says `download/ARCHITECTURE_BASELINE.md` is "not committed (gitignored, reproducible by running the script)". The A3 audit ran `git ls-files download/ARCHITECTURE_BASELINE.md` and found the file IS committed. The `.gitignore` does NOT ignore the path. This is S-063 — a low-severity but factual inconsistency between ADR-021's stated policy and the actual repo state. Either the file should be untracked + gitignored, or ADR-021 should be updated to say "Committed evidence snapshot — reproducible by running the script."

5. **The architecture-boundary-lint.yml false claim about a PHP equivalent.** The yml's comment block at lines 62-65 says "a PHP equivalent of this checker exists at scripts/architecture-boundary-lint.php" — this is FALSE. Only the Python version exists. The path filter at lines 23 and 30 also references the non-existent PHP file (harmless as a trigger but misleading). This is S-060.

6. **The architecture-lint structural check is incomplete post-A1.** The lint `validIds` map was extended in A1 to accept HUB-31/HUB-32/ESPOKE-19 as valid references (so a blueprint mentioning HUB-32 wouldn't fail the "undefined reference" check). But the `checkStructure()` function's expected-file list was NOT extended. So if any of HUB-31.md, HUB-32.md, or ESPOKE-19.md were deleted in a future PR, the lint would NOT flag the missing file — the reference would still be "valid" (per `validIds`) and the file's absence wouldn't be enforced (per `checkStructure`). This is S-064, a latent defect.

7. **The baseline regeneration workflow gap.** The committed `download/ARCHITECTURE_BASELINE.md` was last regenerated at commit `ce27388` (PR #292). Since then, 10 commits have landed — including A1 (PR #294, +HUB-32 +ESPOKE-19) and A2 (PR #301, +ContainerException.php). The baseline wasn't regenerated in either PR. The numbers are off by: +2 blueprints, +1 Hub, +1 ESPOKE, +1 PHP source file. This is S-061. There's no CI check that regenerates the baseline and fails if the committed version differs from the freshly-generated one — this should probably be added.

8. **CORE-VERIFIED-DAG.md §7 implementation-status table is stale for C02.** After A2 added ContainerException.php, the container src/ has 8 PHP files, but the table still says 7. This is S-065 — low-severity but worth noting because the table claims "direct inspection of each implemented package's src/*.php" as its evidence base, and that direct inspection now would yield 8, not 7.

---

## §7. Recommended remediation order (sketched for next PR window)

1. **S-055 + S-056 + S-062 (combined A1-propagation fix):** Single docs PR that propagates the HUB-32 canonical status from A1 into HUB-VERIFIED-DAG.md, HUB-DECLARED-DAG.md, and ADR-021 §"Relationship to Other Documents". Also closes S-033's verification condition. Effort: ~30 minutes of find-replace + hand-derivation of HUB-32's declared edges (none enumerated in A1's minimal depth-1 blueprint — the author needs to add Upward/Downward sections to HUB-32.md first OR explicitly defer this with a "HUB-32 edges pending depth-2 enrichment" note).

2. **S-057 + S-058 (combined DAG Mermaid + classification fix):** Single PR that fixes the 4 wrong-direction edges in CORE-VERIFIED-DAG.md §4 Mermaid AND CORE-DECLARED-DAG.md §3 Mermaid, reclassifies C18→C06 from DECLARED_ONLY to VERIFIED, fixes the four-status summary math, and fixes the "remaining 22 edges" off-by-one. Should be done together because they touch the same lines in the same files. Effort: ~1 hour of careful edit + verification.

3. **S-059 (INDEX.md self-contradiction fix):** Bundle with S-055/S-056/S-062 since it's the same A1-propagation root cause. Update lines 227, 234, 443, and line 14's freshness stamp.

4. **S-034 + S-035 + S-036 + S-037 (combined stale-filename remediation):** Single docs PR that find-replaces `CORE-DEPENDENCY-DAG.md` → `CORE-VERIFIED-DAG.md` in 4 files (10 occurrences). This is a 5-minute mechanical fix that should have been done at the time of the rename in PR #287. Keep the historical mention in ADR-021 line 353 (per S-044's optional remediation).

5. **S-060 + S-064 (combined lint workflow fix):** Single PR that removes the stale `scripts/architecture-boundary-lint.php` references from the yml, removes the false comment block, AND extends `checkStructure` to require HUB-01..32 + ESPOKE-01..19. Should be done together since both touch lint/CI workflow files.

6. **S-026 (stale v1 baseline scripts):** Single PR that deletes `scripts/generate-architecture-baseline.py` (v1) and `scripts/generate-architecture-baseline.php` (legacy), updates ADR-021 line 361 to note the deletion. 5-minute mechanical fix.

7. **S-061 + S-063 (combined baseline commit-policy fix):** Single PR that either (a) untracks `download/ARCHITECTURE_BASELINE.md`, adds it to `.gitignore`, AND adds a CI check that regenerates and diffs the baseline (making ADR-021 line 362 true), or (b) updates ADR-021 line 362 to read "Committed evidence snapshot — regenerate before relying on the numbers" AND adds the same CI check.

8. **S-065 (CORE-VERIFIED-DAG.md C02 src-file count):** Single-line fix in CORE-VERIFIED-DAG.md §7 line 632 (7 → 8). Bundle with #2 above (same file).

---

## §8. Top-line conclusion

The two-DAG governance model is **structurally sound** and the architecture-lint + architecture-boundary-lint workflows **pass on HEAD `34ea867`**. The A1 (HUB-32 + ESPOKE-19 canonical) and A2 (Fiber isolation) work landed successfully. However, the A3 architecture/DAG re-audit found:

- **No new FATAL findings.** The DAGs are coherent enough for build-order wave computation and lint passes.
- **1 HIGH-severity new finding (S-057)** — CORE-DECLARED-DAG.md's `C18→C06` edge is misclassified in three independent ways and the four-status summary math doesn't reconcile. This is the highest-priority fix because edge classification is the authoritative input for build-order wave computation.
- **5 MEDIUM-severity new findings (S-055, S-056, S-058, S-059, S-064)** — mostly A1-propagation gaps (HUB-32 canonical status didn't reach HUB-VERIFIED-DAG.md / HUB-DECLARED-DAG.md / INDEX.md / lint checkStructure) plus the Mermaid direction errors that were documented-but-not-fixed.
- **5 LOW-severity new findings (S-060, S-061, S-062, S-063, S-065)** — mostly cosmetic / housekeeping.
- **Pre-existing findings S-026, S-033 (partial), S-034, S-035, S-036, S-037, S-044 remain NOT remediated.**

**S-033 closure condition is NOT met.** S-033's verification condition explicitly requires "HUB-DECLARED-DAG.md §1 node set includes HUB-32 (count = 30 active)" — the DAG still says "29 active" and excludes HUB-32. S-033 cannot move to Closed at this A3 audit. The A1 worklog's "✅ PASSED" claim for S-001/S-002 (which are about lint passing) is true, but S-033's full closure condition was not enforced mechanically — CI green ≠ all verification conditions met.

The DAG files are stable enough for A3-B Part 2 (runtime/concurrency) and future A4 (Hub/Spoke tier) work to proceed — but the S-057 + S-058 fixes should land before any build-order regeneration work begins, and the S-055 + S-056 + S-059 + S-062 fixes should land before S-033 is claimed Closed in any future audit.

---

*End of A3-ARCH-DAG-76 report. Companion report: `download/A3-RUNTIME-REPORT.md` (runtime/concurrency re-audit). Sister artifacts: `download/SHORTCOMINGS-REGISTER.md`, `download/SHORTCOMINGS-AUDIT.md`, `Architecture/Verification/SHORTCOMINGS-REGISTER.md`.*
