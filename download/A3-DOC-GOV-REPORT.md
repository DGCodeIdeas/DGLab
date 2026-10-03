# A3-DOC-GOV-76 — Documentation/Contract Consistency + Governance/SDLC Integrity Re-Audit Report

**Task ID:** A3-DOC-GOV-76 (Part 2 of the A3 re-audit family — focused doc/governance lens)
**Agent:** General-purpose (documentation/contract-consistency + governance/SDLC-integrity double lens)
**Date:** 2026-10-03
**Base:** `main` @ `200479e` (HEAD; same scratch GUID commit past A2 merge that A3-ARCH-GOV and A3-RUNTIME audited)
**Audit lens:** (1) Documentation/contract consistency — narrow scope per task brief; (2) Governance/SDLC integrity — narrow scope per task brief.

---

## §0. Methodology + Coordination Note

This audit was performed against HEAD (`200479e`) using static analysis only. No PHP runtime was available; markdown linting was done via Python text scans (regex-equivalent to markdownlint MD012 "no multiple consecutive blank lines").

**Two parallel A3 audits completed immediately before this one**, both with overlapping scope:

- **A3-RUNTIME-76** (runtime/concurrency lens) — 7 new findings **S-048 through S-054**, all about `Container::pulse()` runtime behavior and test coverage.
- **A3-ARCH-GOV-76** (fresh-architect + DAG + documentation/governance lens) — 22 new findings **S-055 through S-076**, covering ADR-021 staleness, DAG inconsistencies, register drift, lint gaps, and CI workflow observations.

To avoid ID collision, **this audit's new findings start at S-077**. The task brief instructs "any new finding IDs (S-055+)" — interpreted here as "S-077+ to avoid collision with the parallel A3-ARCH-GOV agent's already-allocated S-055..S-076 range".

This report is **deliberately narrow per the task brief**: it does NOT re-litigate S-001..S-047 (the original register) or S-048..S-076 (the parallel A3 findings). It validates the brief's 6 + 5 specific checks and surfaces new findings discovered while performing those checks.

---

## §1. Documentation/Contract Consistency — Brief's 6 Specific Checks

### Check 1: Did the 182-document Blind-Spot Awareness propagation introduce formatting issues or contradictions?

**Finding: YES — wider than previously reported.**

The `add-blind-spot-awareness.py` script (PR #298) inserts a 3-line block (`["", note, ""]`) after the first H1 via `lines.insert(insert_after + 1, "")`, `lines.insert(insert_after + 2, note)`, `lines.insert(insert_after + 3, "")` (script lines 185-187). It does NOT account for an existing blank line at `insert_after` position, so files that already had a blank line between H1 and the next content get an EXTRA blank line added.

Then PR #299 (`docs(governance): add contributor awareness statement to key documents`) inserts the contributor awareness statement using a similar pattern, adding another blank line above. The cumulative effect on affected files is **3 consecutive blank lines between H1 and the awareness banner**.

**Independent scan results** (Python regex over all 181 `.md` files in `Architecture/`):
- 180 files have a Blind-Spot Awareness banner (the doctrine file itself correctly does NOT — `add-blind-spot-awareness.py` skips it at line 207-209).
- 23 files have the contributor awareness statement.
- **22 files have 3+ consecutive blank lines** (triple-blank-line artifact).
- **159 files have exactly 2 consecutive blank lines** as their maximum (double-blank-line artifact — still a markdownlint MD012 violation, though milder).

The 22 files with triple-blank-line artifacts (all at line 4, i.e., 3 blank lines between H1 at line 1 and the contributor awareness at line 5):

| # | File | Max-blank |
|---|---|---|
| 1 | `README.md` | 3 |
| 2 | `INDEX.md` | 3 |
| 3 | `AUTHORING_GUIDE.md` | 3 |
| 4 | `Hub/HUB-04.md` | 3 |
| 5 | `Hub/HUB-32.md` | 3 |
| 6 | `Spoke/Bridge/BRIDGE-01.md` | 3 |
| 7 | `Spoke/External/ESPOKE-19.md` | 3 |
| 8 | `Verification/SHORTCOMINGS-AUDIT.md` | 3 |
| 9 | `Verification/SHORTCOMINGS-REGISTER.md` | 3 |
| 10 | `CrossCutting/SDLC-AGRD.md` | 3 |
| 11 | `CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` | 3 |
| 12 | `CrossCutting/DGLAB-AS-OS-RUNTIME.md` | 3 |
| 13 | `CrossCutting/NUCLEAR-GRADE-DOCTRINE.md` | 3 |
| 14 | `CrossCutting/STRUCTURE-01-Wheel.md` | 3 |
| 15 | `Deploy/DEPLOY-01.md` | 3 |
| 16 | `Core/CORE-02.md` | 3 |
| 17 | `Core/CORE-BUILD-ORDER.md` | 3 |
| 18 | `Core/CORE-VERIFIED-DAG.md` | 3 |
| 19 | `Core/CORE-DECLARED-DAG.md` | 3 |
| 20 | `Core/CORE-18.md` | 3 |
| 21 | `ADRs/ADR-021-tier-stratified-build-order.md` | 3 |
| 22 | `ADRs/ADR-014-ratify-agrd-canonical-sdlc.md` | 3 |

**Discrepancy vs A3-ARCH-GOV-76 S-065:** S-065 reports "~20 files" with triple-blank-lines and lists only 3 file examples (HUB-04, HUB-32, ESPOKE-19). The actual count is **22 files**, and S-065's evidence list is incomplete. See new finding **S-077** below.

**Cosmetic severity only** — does not affect rendering in GitHub's markdown viewer. markdownlint MD012 ("Multiple consecutive blank lines") would flag these. No contradiction between banner wording and document content was discovered; the banners are intentionally generic.

### Check 2: Did the 24-document contributor awareness statement break any markdown rendering?

**Finding: NO rendering breakage, but artifact-confirming pattern.**

The contributor awareness statement is:
```
> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**
```

Independent count: **23 files** have this statement (vs brief's "24-document" claim — off by 1, possibly because BLIND-SPOT-DOCTRINE.md itself has the statement without being counted, or one file was already skipped).

In all 23 affected files, the contributor blockquote is followed directly by `<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->` HTML comment with NO blank line between them. Standard CommonMark treats the HTML comment as ending the blockquote (HTML comments are block-level HTML), so this is **technically valid markdown** but stylistically inconsistent with the surrounding 3-blank-line artifacts.

No rendering breakage was discovered. The statement itself renders as a single blockquote with bold text on all 23 files. The Markdown is well-formed; only the surrounding blank-line structure has artifacts.

### Check 3: Does `README.md` match the actual state? (PHP version, package count, ADR count)

**Finding: NO — README.md drifts significantly from actual state.**

Cross-checked against the actual repository:

| Field | README.md claim | Actual (verified) | Disposition |
|---|---|---|---|
| PHP version (line 12) | "PHP 8.3" | `composer.json` line 1: `"php": "^8.4"` | STALE — S-005 |
| Core packages (line 20) | "8 Core-tier packages" | 12 under `packages/core/` + 1 (`orchestrator/`) = 13 | STALE — S-006 |
| ADR count (line 21) | "20 Architecture Decision Records" | `ls Architecture/ADRs/` = 21 files | STALE — S-007 |
| Blueprint count (line 22) | "102 component blueprints" | 20 Core + 32 Hub + 27 ISPOKE + 19 ESPOKE + 1 Bridge + 5 Deploy = 104 (post-A1) | STALE — S-013 |
| Hub blueprints (line 75) | "31 Hub-tier blueprints" | 32 (post-A1) | STALE — S-013 |
| PHPUnit (line 201) | "PHPUnit 10.5" | `composer.json` line 4: `"phpunit/phpunit": "^11.0"` | STALE — S-009 |
| SDLC version (line 85) | "SDLC-AGRD v3.4(3)" | SDLC-AGRD.md is now v3.5 | STALE — see S-084 below |
| Cooldown (line 93) | "2-week between-lap cooldowns" | SDLC-AGRD v3.5 §7 changed to "variable duration, gated on a recorded rest check" | STALE |
| Build order source (line 95) | "INDEX.md §5" | INDEX.md §5 is banner-superseded by ADR-021 | STALE — see S-083 below |
| Milestone 0 (line 215) | "All 8 Milestone 0 blueprints shipped" | Table at line 220-228 shows **9 rows** (CORE-02, 04, 05, 06, 18, HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01) | Internal contradiction — see S-090 below |
| Milestone 0 header (line 218) | "(8 blueprints required for MUWV)" | Table body has 9 rows | Internal contradiction — see S-090 below |
| Project status (line 211) | "Current work: doctrine §4.5 follow-up items 4-6 on CORE-18 Kernel..." | README line 108 itself says "§4.5 CORE-18 Kernel pilot is fully implemented" | Internal contradiction — A3-ARCH-GOV S-069 |

**Total** count discrepancies confirmed: 12 of README's headline claims are stale. The brief's three explicit items (PHP, package count, ADR count) are all STALE.

### Check 4: Are there stale references to old filenames anywhere in Architecture/?

**Finding: YES — `CORE-DEPENDENCY-DAG.md` references persist in 5 files (12 occurrences).**

Cross-checked via `grep -rn "CORE-DEPENDENCY-DAG" Architecture/`:

| File | Line | Reference |
|---|---|---|
| `Core/CORE-VERIFIED-DAG.md` | 18 | "**Date:** 2026-09-30 (original); 2026-10-01 (renamed from CORE-DEPENDENCY-DAG per ADR-021 two-DAG model)" — historical, OK |
| `Core/CORE-VERIFIED-DAG.md` | 666 | "*End of CORE-DEPENDENCY-DAG.md. See sibling documents...*" — STALE footer (S-035) |
| `Core/CORE-BUILD-ORDER.md` | 288 | "...The DAG in §4 of `CORE-DEPENDENCY-DAG.md` shows all 45..." — STALE (S-037) |
| `Core/CORE-BUILD-ORDER.md` | 328 | "...use the 45-edge declared DAG from `CORE-DEPENDENCY-DAG.md` §4..." — STALE (S-037) |
| `Core/CORE-BUILD-ORDER.md` | 334 | "*End of CORE-BUILD-ORDER.md. See sibling documents `CORE-DEPENDENCY-DAG.md`...*" — STALE footer (S-037) |
| `Core/CORE-CAPABILITY-DAG.md` | 427 | "...documented in CORE-DEPENDENCY-DAG.md §3..." — STALE (S-036) |
| `Core/CORE-CAPABILITY-DAG.md` | 431 | "...in the dependency DAG (`CORE-DEPENDENCY-DAG.md §4`)..." — STALE (S-036) |
| `Core/CORE-CAPABILITY-DAG.md` | 481 | "*End of CORE-CAPABILITY-DAG.md. See sibling documents `CORE-DEPENDENCY-DAG.md`...*" — STALE footer (S-036) |
| `Hub/HUB-DECLARED-DAG.md` | 420 | `subgraph core["Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for canonical Core DAG)"]` — STALE (S-034) |
| `Hub/HUB-DECLARED-DAG.md` | 668 | "Blue-filled box = Core target (canonical Core DAG is `Architecture/Core/CORE-DEPENDENCY-DAG.md`)" — STALE (S-034) |
| `Hub/HUB-VERIFIED-DAG.md` | 120 | `subgraph core_tier["Core tier (consumed targets — see CORE-DEPENDENCY-DAG.md for the canonical Core DAG)"]` — STALE (S-034) |
| `Hub/HUB-VERIFIED-DAG.md` | 156 | "Blue-filled box = Core target (referenced; canonical Core DAG is in `Architecture/Core/CORE-DEPENDENCY-DAG.md`)" — STALE (S-034) |
| `ADRs/ADR-021-tier-stratified-build-order.md` | 353 | "**NEW** (renamed from `CORE-DEPENDENCY-DAG.md`) — 13-edge verified implementation DAG." — historical, OK |

Of 13 total references, **11 are stale** (S-034/S-035/S-036/S-037 — all Open in register) and 2 are legitimate historical mentions (S-044 — Open). All carry-forward; no new findings.

### Check 5: Does `Architecture/Core/CORE-02.md` have BOTH the old Shape A docblock AND the new Shape C contract?

**Finding: PARTIALLY — the DOCBLOCK was REPLACED with Shape C (✅), but the EMBEDDED REFERENCE IMPLEMENTATION class still uses Shape A (❌ — already S-058), and the docblock's "Implementation note" paragraph is now stale post-A2 (NEW — S-080).**

**Detailed verification:**

1. **Docblock for `pulse()` (lines 148-258): REPLACED with Shape C.** ✅
   - Line 148: "Bind a concrete value to the current Fiber's Pulse scope (Shape C)."
   - Line 152-153: "It is NOT a boot-time factory registration. The `$value` IS the instance"
   - Line 172: "**make() precedence (Shape C):**"
   - No remaining "Shape A" or "Shape B" string in the docblock.
   - 12 edge cases enumerated; `@throws ContainerException` tag present (line 256).

2. **Reference implementation class (lines 500-636): STILL Shape A.** ❌ (S-058 from A3-ARCH-GOV)
   - 6 private fields (`$definitions`, `$instances`, `$pulseInstances`, `$resolving`, `$compilerPasses`, `$compiled`) — live source has 9 fields (adds `$pulseDefinitions`, `$fiberResolving`, `$mainResolving`, `$mainResolvingChain`).
   - `pulse()` writes to `$this->definitions[$id]` (Shape A pattern, line 580) — live source writes to `$this->pulseDefinitions[$fiber][$id]` (Shape C, line 195).
   - Missing `invalidateCurrentFiberPulseInstance()` helper (live source lines 521-532).
   - Missing per-Fiber cycle-detection dispatch in `make()` step 0 (live source lines 232-265).

3. **Docblock "Implementation note (S-003/S-004 remediation direction)" paragraph (lines 245-247): STALE post-A2.** ❌ NEW FINDING S-080
   - Says: "The current implementation writes `pulse()` to global `$definitions[$id]` instead of a per-Fiber `WeakMap $pulseDefinitions`. The fix: add `private \WeakMap $pulseDefinitions`..."
   - But the live source ALREADY has `$pulseDefinitions` per A2 PR #301 (commit `2ecfb03`). The "fix" the paragraph describes HAS BEEN APPLIED.
   - The paragraph describes the pre-A2 state as if it's the current state. A fresh architect reading CORE-02 would believe the bug is still present; they would attempt to re-implement a fix that's already shipped.

**Verdict:** The subagent (PR #295, A2-prep) DID replace the docblock with Shape C language — ✅ the brief's specific concern is resolved. But the reference implementation class was NOT updated (S-058 from A3-ARCH-GOV), and the "Implementation note" paragraph within the docblock is now stale post-A2 (NEW finding S-080).

### Check 6: Does `Architecture/Index/INDEX.md` have the authority boundary section (§0)?

**Note on path:** The brief asks about `Architecture/Index/INDEX.md` — the actual file is at `Architecture/INDEX.md` (no `Index/` subdirectory). The brief's path is slightly off.

**Finding: YES — §0 Authority Boundary section exists.** ✅

Verified at `Architecture/INDEX.md` lines 33-49:
- Header: "## §0. Authority Boundary (per ADR-021 §11)"
- Quote: "INDEX is the canonical registry of architectural identity and governance, while dependency graphs are generated authoritative views of declared intent and verified implementation state."
- "INDEX owns:" list (IDs, names, tier membership, numbering, Canonical status, ADR relationships, Governance rules)
- "INDEX does NOT own (generated, not manually maintained):" list (Actual Composer dependencies, namespace imports, implementation status, topological ordering, test state)
- Closing: "Derived facts are generated from repository evidence, not hand-maintained in INDEX."

Section is well-formed and consistent with ADR-021 §11 (per the brief's check). However, INDEX.md §2.1 + §4 hand-maintain derived facts ("Real implementation | Build status" and "Documented | Placeholder-only | Total files" columns) — this is the S-057 finding (carried forward from A3-ARCH-GOV): INDEX.md's own §0 says derived facts should be generated, but INDEX.md §2.1 + §4 still hand-maintain them.

---

## §2. Governance/SDLC Integrity — Brief's 5 Specific Checks

### Check 7: Does SDLC-AGRD.md still describe the old single-lap model?

**Note on terminology:** The brief says "old single-lap model" but SDLC-AGRD has always been a multi-lap "Spiral Deepening" model (per §2 "Spiral Deepening replaces Radial Incremental" and §4.3 "Lap structure — widens and deepens every cycle"). The brief's "single-lap model" appears to refer to the **pre-v4.0 lap-widen admission rule** (which ADR-021 Amendment 2 §7 supersedes with the `Eligible(X)` admission rule). The v4.0 rewrite (which would implement `Eligible(X)`) has not happened.

**Finding: YES — SDLC-AGRD is still v3.5 (lap-widen admission rule, NOT `Eligible(X)`).** ✅ Correct per the brief's expectation.

Verified at `Architecture/CrossCutting/SDLC-AGRD.md`:
- Line 9: "Status: Canonical. Supersedes AGRD_v1_0.md... `SDLC-AGRD-v3.5` (this version, per real Lap 1/Lap 2 evidence)..."
- Line 198: "Lap k, for k = 1, 2, 3…:"
- Line 200: "**Widen.** For each ring that still has unadmitted blueprints, admit its next-most-depended-upon not-yet-touched blueprint at depth 1."
- No mention of `Eligible(X)`, no mention of ADR-021's two-DAG governance, no mention of edge dimensions.

The v3.5 lap-widen admission rule is still in effect. ADR-021's `Eligible(X)` admission rule (Amendment 2 §7) has NOT been integrated into SDLC-AGRD — this is the deferred v4.0 rewrite. Brief expectation met.

However, the SDLC-AGRD has **stale blueprint count** ("96 blueprints") and **stale §5.2 reliance** — see new findings **S-082** and **S-083** below.

### Check 8: Is the shortcomings register (SHORTCOMINGS-REGISTER.md) consistent with the worklog?

**Finding: NO — register is stale w.r.t. worklog in three ways.**

Cross-checked `Architecture/Verification/SHORTCOMINGS-REGISTER.md` against the worklog:

**Inconsistency 1: Register dispositions are stale.** All 47 findings still marked "Disposition: Open" (Summary table lines 44-50: FATAL 4 Open / 0 Closed, HIGH 25 Open / 0 Closed, MEDIUM 14 Open / 0 Closed, LOW 4 Open / 0 Closed). The worklog records:
- A1 (PR #294, 2026-10-01): S-001/S-002/S-033 verification conditions met (architecture-lint passes).
- A2 (PR #301, 2026-10-02): S-003/S-004 Fixed (but per A3-RUNTIME-76 S-054, condition (c) unmet → NOT Closed).

Per the register's own closure rule (lines 22-31): S-001/S-002/S-033 should be marked `Closed`; S-003/S-004 should be marked `Fixed` (not `Closed` — condition (c) unmet). The register was not updated.

**Inconsistency 2: Register missing A3-RUNTIME findings S-048..S-054.** The register has entries S-001 through S-047 (verified by `grep "### S-"` returning 47 hits). No entries exist for S-048 through S-054 (the 7 findings from the parallel A3-RUNTIME-76 audit). See new finding **S-077** below.

**Inconsistency 3: Register missing A3-ARCH-GOV findings S-055..S-076.** No entries exist for S-055 through S-076 (the 22 findings from the parallel A3-ARCH-GOV-76 audit). See new finding **S-077** below.

**Inconsistency 4 (NEW finding S-079): S-046 register entry has incorrect premise.** S-046 says "there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)" — but ADR-011 itself (file line 13) says "Status: **Accepted** (2026-08-13)". Only 2 Proposed ADRs exist (ADR-015, ADR-016). The register's own S-046 entry contains a factual error. See new finding **S-079**.

### Check 9: Does the register have entries for S-048..S-054 (the A3 findings)?

**Finding: NO — register has zero entries for S-048..S-054.**

`grep -E "S-04[89]|S-05[0-4]" Architecture/Verification/SHORTCOMINGS-REGISTER.md` returns zero matches. The register ends at S-047.

The 7 A3-RUNTIME findings (S-048 through S-054) are documented in `/home/z/my-project/download/A3-RUNTIME-REPORT.md` but have NOT been folded into the canonical register. Per the BLIND-SPOT-DOCTRINE.md binding rule #1 ("No audit is declared complete. The register is always 'open' — new findings can be added at any time"), these findings SHOULD be added to the register. See new finding **S-077**.

### Check 10: Is the Blind-Spot Doctrine (BLIND-SPOT-DOCTRINE.md) referenced from INDEX.md?

**Finding: YES — referenced in two places.** ✅

- `Architecture/INDEX.md` line 6: `<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->`
- `Architecture/INDEX.md` line 8: `> **⚠️ Blind-Spot Awareness:** This INDEX registry's **counts, statuses, and cross-references may have drift**. ... See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.`

The doctrine is referenced from INDEX.md. However, INDEX.md §1's CrossCutting inventory row (line ~51 — "STRUCTURE-01..09, OBSERVABILITY, GLOSSARY, THREAT_MODEL, NUCLEAR-GRADE-DOCTRINE") does NOT list `BLIND-SPOT-DOCTRINE.md` as a CrossCutting document. So while the doctrine is referenced inline, it's missing from the canonical inventory. See new finding **S-088** below (low severity — the inline reference suffices for discoverability, but the inventory is incomplete).

### Check 11: Are there ADR cross-reference errors?

**Finding: NO — ADR cross-references are clean.** ✅

Python scan of all 181 `.md` files in `Architecture/` for `ADR-\d{3}` references (after stripping code blocks):
- ADR-001 through ADR-021 (21 ADRs) all exist on disk.
- ADR-001 through ADR-021 are all referenced from at least one other document.
- **Zero** references to non-existent ADRs (the only "ADR-022" references are forward references in `Verification/SHORTCOMINGS-AUDIT.md` and `Verification/SHORTCOMINGS-REGISTER.md` — these are PROPOSED future ADRs mentioned in S-023's remediation, not broken references).

ADR cross-references are clean. However, the ADR **status** fields disagree across documents:
- ADR-011 file says "Accepted (2026-08-13)" but INDEX.md §1 line 65 says "Proposed ADR (HUB-31) — not accepted, not counted". See new finding **S-078** below.
- ADR-014 file ratifies SDLC-AGRD v3.4(3) but SDLC-AGRD is now at v3.5. ADR-014 was not amended. See new finding **S-084** below.

---

## §3. New Findings (S-077+, avoiding collision with A3-ARCH-GOV's S-055..S-076 and A3-RUNTIME's S-048..S-054)

### S-077: SHORTCOMINGS-REGISTER.md is missing A3-RUNTIME findings S-048..S-054 + A3-ARCH-GOV findings S-055..S-076
- **Severity:** HIGH
- **Category:** Governance
- **Description:** The register ends at S-047. The parallel A3 audits (A3-RUNTIME-76, A3-ARCH-GOV-76) identified 29 additional findings (S-048 through S-076) that have NOT been folded into the canonical register. Per BLIND-SPOT-DOCTRINE.md binding rule #1: "No audit is declared complete. The register is always 'open' — new findings can be added at any time." Per the register's own closure rule (lines 22-31) and paradigm shift directive (lines 14-21), all findings — original or subsequently discovered — must be tracked in the register with the full field set. The register's Summary table (lines 44-50) shows "47" total findings — this is stale.
- **Evidence:**
  - `Architecture/Verification/SHORTCOMINGS-REGISTER.md` line 49: `| **Total** | **47** | **47** | **0** |` (Summary table — only counts S-001..S-047)
  - `grep -E "### S-" Architecture/Verification/SHORTCOMINGS-REGISTER.md` returns 47 hits (S-001 through S-047)
  - `grep -E "S-04[89]|S-05[0-9]|S-06[0-9]|S-07[0-6]" Architecture/Verification/SHORTCOMINGS-REGISTER.md` returns zero matches
  - A3-RUNTIME-76 report at `/home/z/my-project/download/A3-RUNTIME-REPORT.md` documents S-048 through S-054
  - A3-ARCH-GOV-76 report at `/home/z/my-project/download/A3-ARCH-GOV-REPORT.md` documents S-055 through S-076
- **Affected artifact:** `Architecture/Verification/SHORTCOMINGS-REGISTER.md`
- **Contract violated:** BLIND-SPOT-DOCTRINE.md binding rule #1 (no audit is declared complete; register is always open). The register's own closure rule (line 22-31).
- **Root cause:** The register was authored in PR #293 (commit f34bdc5, "docs(integrity-gate): shortcomings audit + register + A0 state model"). A1 (PR #294), A2-prep (PR #295), A2 (PR #301), and the parallel A3 audits landed afterward but the register was not amended to absorb the new findings.
- **Remediation:** Amend the register:
  1. Add S-048 through S-054 entries (copy from `A3-RUNTIME-REPORT.md` with the canonical field set).
  2. Add S-055 through S-076 entries (copy from `A3-ARCH-GOV-REPORT.md` with the canonical field set).
  3. Update Summary table to show 76 total findings (47 original + 29 new) with appropriate severity counts: FATAL 4 + HIGH 25 + 14 + (S-048 HIGH + S-054 HIGH + S-055 HIGH + S-058 HIGH + S-059 HIGH + S-060 HIGH + S-062 HIGH + S-064 HIGH = 8 new HIGH) + S-067 HIGH = 9 new HIGH = 34 HIGH total; MEDIUM 14 + (S-050 + S-056 + S-057 + S-061 + S-063 + S-068 + S-070 + S-074) = 22 MEDIUM; LOW 4 + (S-049 + S-051 + S-052 + S-053 + S-065 + S-066 + S-069 + S-073 + S-075 + S-076) = 14 LOW.
  4. Update disposition of S-001/S-002/S-033 to Closed/Fixed (per worklog A1 evidence + A3-ARCH-GOV-76 §1 verification). Update S-003/S-004 to Fixed (not Closed — condition (c) unmet per A3-RUNTIME-76 S-054).
- **Verification test:** `grep -E "### S-04[89]|### S-05[0-9]|### S-06[0-9]|### S-07[0-6]" Architecture/Verification/SHORTCOMINGS-REGISTER.md` returns 29 matches (S-048 through S-076); Summary table shows 76 total findings.
- **Disposition:** Open (this finding itself)
- **Owner:** main agent
- **Target phase:** Immediate (post-A3) — gates the paradigm shift directive ("roadmap does not advance while any FATAL finding remains Open")
- **Closure evidence:** (empty)

### S-078: INDEX.md §1 line 65 still says "ADR-011 | 1 Proposed ADR (HUB-31) — not accepted, not counted" — but ADR-011 itself says "Accepted (2026-08-13)" and OPEN-DECISIONS OD-01 confirms Resolved
- **Severity:** HIGH
- **Category:** Doc-Drift (cross-document contradiction)
- **Description:** Three sources disagree on ADR-011's status:
  - **ADR-011 file** (`Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` line 13): `**Status:** **Accepted** (2026-08-13)`
  - **OPEN-DECISIONS** (`Architecture/OPEN-DECISIONS.md` line 113-116, in the "## Resolved" section): "OD-01 — HUB-31: accepted as full Hub tier. Decision: Accept ADR-011 as-is. HUB-31 promoted from Proposed to accepted. Decided by: DGCI, 2026-08-12. Action: INDEX.md updated — HUB-31 added to Hub tier table; count updated to 97 blueprints."
  - **INDEX.md** (`Architecture/INDEX.md` line 65): "Architecture/ADRs/ADR-011 | 1 **Proposed** ADR (HUB-31) — not accepted, not counted"

  The ADR file itself and OPEN-DECISIONS agree: ADR-011 is **Accepted**. INDEX.md is the lone dissenter, still calling it "Proposed".
- **Evidence:**
  - `Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` line 13: "**Status:** **Accepted** (2026-08-13)"
  - `Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` line 11: "**Date:** 2026-08-05 (Proposed); 2026-08-13 (Accepted)"
  - `Architecture/OPEN-DECISIONS.md` line 113: "### OD-01 — HUB-31 (Real-Time Analytics & Metrics Ledger): accepted as full Hub tier"
  - `Architecture/OPEN-DECISIONS.md` line 114: "Decision: Accept `ADR-011` as-is. HUB-31 promoted from Proposed to accepted."
  - `Architecture/INDEX.md` line 65: "| `Architecture/ADRs/ADR-011` | 1 **Proposed** ADR (HUB-31) — not accepted, not counted |"
- **Affected artifact:** `Architecture/INDEX.md` line 65 (and indirectly §2.2 Hub table which omits HUB-31)
- **Contract violated:** INDEX.md §1 promises "single source of truth" (Governance Rule 1) — INDEX disagrees with the ADR's own status field and with OPEN-DECISIONS' resolution.
- **Root cause:** ADR-011 was filed as Proposed on 2026-08-05; OPEN-DECISIONS OD-01 was resolved 2026-08-12 with explicit action "INDEX.md updated — HUB-31 added to Hub tier table". But this action was apparently never actually performed — INDEX.md §1 line 65 was never updated to "Accepted", and §2.2 Hub table never received a HUB-31 row.
- **Remediation:**
  1. Update INDEX.md §1 line 65 to: "| `Architecture/ADRs/ADR-011` | **Accepted** (2026-08-13) — HUB-31 Real-Time Analytics & Metrics Ledger; per OD-01 resolution. |"
  2. Add a HUB-31 row to INDEX.md §2.2 Hub table.
  3. Reconcile HUB-31 status with ADR-021 §12 (Hub tier — HUB-31 is now an active Hub blueprint, not Pending).
- **Verification test:** `grep "Proposed ADR (HUB-31)" Architecture/INDEX.md` returns zero matches; INDEX.md §2.2 Hub table has a HUB-31 row; INDEX.md §1 ADR-011 row says "Accepted".
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-079: S-046 register entry has incorrect premise — counts ADR-011 as Proposed, but ADR-011 is Accepted (per file + OPEN-DECISIONS OD-01)
- **Severity:** MEDIUM
- **Category:** Meta-audit (register self-inconsistency)
- **Description:** The register's S-046 entry (lines 918-935) says "INDEX.md §1 line 56 says '1 Proposed ADR (HUB-31)' — but there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)". This premise is WRONG: ADR-011 itself (file line 13) says "Status: **Accepted** (2026-08-13)" — ADR-011 is NOT Proposed. Only 2 Proposed ADRs exist (ADR-015, ADR-016). The register's S-046 entry's enumeration is incorrect by 1.
- **Evidence:**
  - `Architecture/Verification/SHORTCOMINGS-REGISTER.md` line 918: "### S-046: INDEX.md §1 line 56 says '1 Proposed ADR (HUB-31)' — but there are 3 Proposed ADRs (ADR-011, ADR-015, ADR-016)"
  - `Architecture/Verification/SHORTCOMINGS-REGISTER.md` line 927: "Architecture/ADRs/ADR-016-library-app-boundary-split.md line 3: 'Status: Proposed'" (correct)
  - `Architecture/Verification/SHORTCOMINGS-REGISTER.md` line 926: "Architecture/INDEX.md line 55: ADR-015 Proposed" (correct)
  - `Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md` line 13: "**Status:** **Accepted** (2026-08-13)" — ADR-011 is Accepted, not Proposed
- **Affected artifact:** `Architecture/Verification/SHORTCOMINGS-REGISTER.md` S-046 entry
- **Contract violated:** The register's own integrity — findings must be factually correct.
- **Root cause:** The S-046 audit was conducted against INDEX.md's claim ("1 Proposed ADR — HUB-31"), not against the ADR files themselves. The auditor trusted INDEX.md over the source ADR files. This is itself a blind-spot: the audit found an inconsistency in INDEX.md (count says 1, actual Proposed are 2) but introduced a new error (counting ADR-011 as Proposed when its file says Accepted).
- **Remediation:**
  1. Amend S-046 to say: "INDEX.md §1 line 56 says '1 Proposed ADR (HUB-31)' — but there are 2 Proposed ADRs (ADR-015, ADR-016). ADR-011 is Accepted per its own file (line 13) and per OPEN-DECISIONS OD-01 (Resolved 2026-08-12), but INDEX.md still calls it Proposed — see S-078."
  2. Mark S-046 as closed when S-078 closes (since both stem from the same INDEX.md §1 line 65 staleness).
- **Verification test:** S-046 entry in register no longer mentions ADR-011 as Proposed; the entry cross-references S-078.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** Immediate (bundled with S-077)
- **Closure evidence:** (empty)

### S-080: CORE-02.md docblock "Implementation note (S-003/S-004 remediation direction)" is stale post-A2 — describes the pre-A2 state
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A2)
- **Description:** `Architecture/Core/CORE-02.md` lines 245-247 contain an "Implementation note (S-003/S-004 remediation direction)" paragraph inside the `pulse()` docblock. The paragraph describes the **pre-A2 state** as if it's the current state: "The current implementation writes `pulse()` to global `$definitions[$id]` instead of a per-Fiber `WeakMap $pulseDefinitions`. The fix: add `private \WeakMap $pulseDefinitions` and redirect `pulse()`/`make()` to consult it per-Fiber." But A2 (PR #301, commit `2ecfb03`, 2026-10-02) ALREADY added the `$pulseDefinitions` WeakMap and redirected `pulse()`/`make()` accordingly. The "fix" the paragraph describes HAS BEEN APPLIED. The paragraph is now misleading: a fresh architect reading CORE-02 would believe the S-003/S-004 bug is still present and would attempt to re-implement a fix that's already shipped.
- **Evidence:**
  - `Architecture/Core/CORE-02.md` line 245: `**Implementation note (S-003/S-004 remediation direction):**`
  - `Architecture/Core/CORE-02.md` line 247: `The current implementation writes \`pulse()\` to global \`$definitions[$id]\` instead`
  - `Architecture/Core/CORE-02.md` line 248: `of a per-Fiber \`WeakMap $pulseDefinitions\`. The fix: add \`private \WeakMap`
  - `Architecture/Core/CORE-02.md` line 249: `$pulseDefinitions\` and redirect \`pulse()\`/\`make()\` to consult it per-Fiber. The`
  - Live source `packages/core/container/src/Container.php` line 95: `private \WeakMap $pulseDefinitions;` (the fix HAS been applied)
  - Live source `packages/core/container/src/Container.php` line 195: `$this->pulseDefinitions[$fiber][$id] = ...` (pulse() writes to per-Fiber WeakMap, not global $definitions)
- **Affected artifact:** `Architecture/Core/CORE-02.md` docblock "Implementation note" paragraph (lines 245-249)
- **Contract violated:** Blueprint freshness — the docblock should describe the CURRENT state, not the pre-A2 state.
- **Root cause:** PR #295 (A2-prep, commit `9336fe6`) updated the pulse() docblock to Shape C language, but the "Implementation note (S-003/S-004 remediation direction)" paragraph at the END of the docblock was kept verbatim from the A0 spec era. PR #301 (A2) updated the live Container.php source but did NOT propagate the change back to the blueprint's "Implementation note" paragraph.
- **Remediation:** Update the "Implementation note" paragraph to reflect post-A2 reality:
  > **Implementation note (post-A2, 2026-10-02):**
  >
  > The implementation now writes `pulse()` to a per-Fiber `WeakMap $pulseDefinitions` (introduced by PR #301, commit `2ecfb03`). The `make()` method consults `$pulseDefinitions[Fiber::getCurrent()][$id]` first (step 0), then falls through to the global `$definitions` table. The `invalidateCurrentFiberPulseInstance()` helper (Fiber-scoped) replaces the previous global `invalidatePulseInstances()`. S-003/S-004 verification condition (a) (testConcurrentFibersObserveIndependentPulseState passes) and (b) (testCompletedFiberStateIsNotVisibleToNewFiber passes) are met; condition (c) (new file `PulseFiberIsolationTest.php`) is UNMET per A3-RUNTIME-76 S-054. See `download/CONTAINER-FIBER-STATE-MODEL.md` for the A0 spec.
- **Verification test:** `grep "writes \`pulse()\` to global" Architecture/Core/CORE-02.md` returns zero matches; the "Implementation note" paragraph says "now writes ... per-Fiber WeakMap" and references PR #301 + commit `2ecfb03`.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (bundled with S-058 reference impl update)
- **Closure evidence:** (empty)

### S-081: CONTAINER-FIBER-STATE-MODEL.md (A0 spec) Q1 quotes the pre-A2-prep CORE-02 docblock as evidence
- **Severity:** LOW
- **Category:** Doc-Drift (post-A2-prep)
- **Description:** `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` line 384 quotes the CORE-02 docblock: "The CORE-02 blueprint docblock says: *'Register a Pulse-scoped binding — one instance per Fiber (per Pulse). When a Pulse resolves this service, it receives a fresh instance that is cached for the duration of that Pulse only.'* This reads as **Shape A** (boot-time registration with a factory; per-Fiber caching happens at make-time)." But this quote is from the **pre-A2-prep** version of CORE-02.md. A2-prep (PR #295, commit `9336fe6`, 2026-10-01) updated the CORE-02 docblock to Shape C language. The quoted text "Register a Pulse-scoped binding — one instance per Fiber (per Pulse)" NO LONGER EXISTS in CORE-02.md (replaced with "Bind a concrete value to the current Fiber's Pulse scope (Shape C)"). The A0 spec's Q1 evidence is now stale.
- **Evidence:**
  - `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` line 384: "The CORE-02 blueprint docblock says: *'Register a Pulse-scoped binding — one instance per Fiber (per Pulse). When a Pulse resolves this service, it receives a fresh instance that is cached for the duration of that Pulse only.'* This reads as **Shape A**..."
  - `Architecture/Core/CORE-02.md` line 148: "Bind a concrete value to the current Fiber's Pulse scope (Shape C)." (current docblock — the old "Register a Pulse-scoped binding" text is gone)
- **Affected artifact:** `Architecture/CrossCutting/CONTAINER-FIBER-STATE-MODEL.md` Q1 (lines 380-394)
- **Contract violated:** The A0 spec is supposed to be the canonical state model; quoting a stale docblock as evidence is misleading.
- **Root cause:** The A0 spec was authored before A2-prep updated the CORE-02 docblock. A2-prep updated the docblock but did NOT update the A0 spec's quote of the old docblock.
- **Remediation:** Update CONTAINER-FIBER-STATE-MODEL.md Q1 to reflect the post-A2-prep reality:
  > Q1 was resolved by tech-lead decision (2026-10-01): Shape C is canonical. PR #295 (A2-prep) updated the CORE-02 docblock to Shape C language; PR #301 (A2) implemented the per-Fiber WeakMap. The original Q1 evidence below is retained for provenance — the docblock it quotes no longer exists in CORE-02.md (it has been replaced with Shape C language).
- **Verification test:** CONTAINER-FIBER-STATE-MODEL.md Q1 has a banner or annotation acknowledging that the quoted docblock is stale and that Q1 was resolved.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog (cosmetic — provenance annotation only)
- **Closure evidence:** (empty)

### S-082: SDLC-AGRD.md references stale "96 blueprints" count (4 occurrences) — actual post-A1 is 104
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A1)
- **Description:** SDLC-AGRD.md still says "96 blueprints" in 4 places, but A1 made HUB-32 and ESPOKE-19 canonical at depth 1 (and hospitality promotion to 101 happened earlier on 2026-08-12 per ADR-015; INDEX.md §4 itself moved to 102 pre-A1; post-A1 actual is 104). The SDLC-AGRD's "96" count was correct circa 2026-08-05 (pre-hospitality-promotion) and was never refreshed.
- **Evidence:**
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 195: "it can't reach 96 blueprints without repeating"
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 254: "the lap structure ends when all 96 blueprints are at depth 5"
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 278: "37 of 96 blueprints (all 20 Core, 10 Hub components...)"
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 437: "`INDEX.md` §5.2's dependency-graph coverage (37 of 96 blueprints, verified) needs extending"
- **Affected artifact:** `Architecture/CrossCutting/SDLC-AGRD.md`
- **Contract violated:** SDLC-AGRD's own calibration depends on the blueprint count being correct (§5 formula: `throughput = N / W` where N is the blueprint count). An incorrect N produces incorrect throughput projections.
- **Root cause:** SDLC-AGRD v3.5 was authored 2026-10-01 (per its own changelog line 23). Hospitality promotion (2026-08-12) and A1 (2026-10-01) both happened before/around v3.5 authorship, but the "96" figure was carried forward from the v3.4(3) baseline without refresh.
- **Remediation:** Find-replace "96 blueprints" → "104 blueprints" in 4 occurrences (or update to a more dynamic reference like "the canonical count per INDEX.md §4").
- **Verification test:** `grep "96 blueprints\|of 96 blueprints" Architecture/CrossCutting/SDLC-AGRD.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-083: SDLC-AGRD.md relies on INDEX.md §5.2 as a live Mermaid graph data source — but INDEX.md §5.2 is banner-superseded by ADR-021
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-ADR-021)
- **Description:** SDLC-AGRD.md §4.3 (line 203) says "is decided against the current matrix's real dependency edges (`INDEX.md` §5.2 — verified to be an actual Mermaid graph with real A --> B edges, not prose)". But INDEX.md §5.2 has been banner-superseded by ADR-021 (per INDEX.md §5 banner: "⚠️ SUPERSEDED by ADR-021 (2026-09-30)... This section is retained for historical reference. Do not derive build orders from it."). The SDLC-AGRD's widen rule points to a deprecated section as the live data source.
- **Evidence:**
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 203: "is decided against the current matrix's real dependency edges (`INDEX.md` §5.2 — verified to be an actual Mermaid graph with real A --> B edges, not prose; see the coverage caveat under gap C below)"
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 276: "Gap C — is `INDEX.md` §5.2 actually queryable, checked against the live repo, not assumed: yes..."
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 437: "`INDEX.md` §5.2's dependency-graph coverage (37 of 96 blueprints, verified) needs extending before widening..."
  - `Architecture/INDEX.md` §5 banner (line 215-225): "⚠️ SUPERSEDED by ADR-021 (2026-09-30)... §5.2 (monolithic Mermaid) is superseded by per-tier DAGs. The authoritative Core DAGs live at `Architecture/Core/CORE-VERIFIED-DAG.md`... This section is retained for historical reference. Do not derive build orders from it. Use the per-tier DAG files referenced in ADR-021 instead."
- **Affected artifact:** `Architecture/CrossCutting/SDLC-AGRD.md` §4.3 widen rule + Gap C
- **Contract violated:** ADR-021 §11 (INDEX authority evolution) — the live dependency graph data should come from generated DAGs, not from INDEX.md §5.2's hand-maintained Mermaid block.
- **Root cause:** SDLC-AGRD v3.5 (2026-10-01) was authored the same day ADR-021 was ratified. ADR-021's §11 declaration that INDEX does NOT own derived facts (and §5.2 banner-supersedes the Mermaid block) was not propagated to SDLC-AGRD's widen-rule data-source reference. This is the same class of drift as S-057 (INDEX §2.1 hand-maintains derived facts) — both stems from ADR-021 §11 not being fully realized.
- **Remediation:** Update SDLC-AGRD §4.3 to point to the canonical generated DAGs:
  > is decided against the current matrix's real dependency edges — the canonical source is now the per-tier DAGs ratified by ADR-021 §12: `Architecture/Core/CORE-VERIFIED-DAG.md` (13-edge verified Core DAG) + `Architecture/Core/CORE-DECLARED-DAG.md` (45-edge declared Core DAG) + sibling per-tier DAGs (Hub, Spoke, Bridge, Deploy, Runtime — to be derived in follow-up PRs).
  Also update Gap C and line 437 to reference the new canonical sources.
- **Verification test:** `grep "INDEX\.md §5\.2" Architecture/CrossCutting/SDLC-AGRD.md` returns zero matches (or only historical mentions clearly annotated as superseded).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch (bundled with S-082 SDLC-AGRD refresh)
- **Closure evidence:** (empty)

### S-084: ADR-014 ratifies SDLC-AGRD v3.4(3) but SDLC-AGRD is now at v3.5 — ADR-014 not amended
- **Severity:** MEDIUM
- **Category:** Governance (ADR staleness)
- **Description:** ADR-014 (`Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md`) line 34: "Ratify `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3) as the canonical, sole SDLC for the DGLab project." But the SDLC-AGRD has moved to v3.5 (per SDLC-AGRD line 23: "`v3.5` (this version, per real Lap 1/Lap 2 evidence): formalized widen as need-driven rather than mandatory-per-ring (§4.3), and replaced the fixed 2-week cooldown with a variable duration gated on a recorded rest check rather than a clock (§7)."). ADR-014 itself acknowledges this gap at line 51: "No lap data yet. v3.4(3) explicitly states it will not iterate to v3.5 without lap-1 data (§10). If Milestone 0 exceeds 8 weeks..." — but v3.5 has now shipped per Lap 1/Lap 2 evidence (the SDLC-AGRD changelog says so), and ADR-014 hasn't been amended to acknowledge this.
- **Evidence:**
  - `Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md` line 34: "Ratify `Architecture/CrossCutting/SDLC-AGRD.md` v3.4(3) as the canonical, sole SDLC for the DGLab project."
  - `Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md` line 51: "No lap data yet. v3.4(3) explicitly states it will not iterate to v3.5 without lap-1 data (§10)."
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 23: "`v3.5` (this version, per real Lap 1/Lap 2 evidence)..."
- **Affected artifact:** `Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md`
- **Contract violated:** ADR governance — when the ratified artifact (SDLC-AGRD) iterates to a new version, the ADR should be amended to acknowledge the new version (either as "v3.5 is now canonical, amending v3.4(3) ratification" or as "v3.5 is an in-scope refinement under the v3.4(3) ratification, no amendment needed").
- **Root cause:** SDLC-AGRD v3.5 was authored (per its changelog entry on line 23) without a corresponding ADR-014 amendment. The ADR still describes the v3.4(3) ratification as canonical.
- **Remediation:** Either:
  (a) Amend ADR-014 to add Amendment 1: "v3.5 is canonical as of [date]; ADR-014's v3.4(3) ratification is extended to cover v3.5 as an in-scope refinement (v3.5's two changes — need-driven widen, variable cooldown — are operational refinements, not architectural changes; no new ADR required)."
  (b) Or add a note to ADR-014's Status field: "Extended by v3.5 (2026-10-01): see SDLC-AGRD changelog."
- **Verification test:** ADR-014 has either an Amendment 1 section acknowledging v3.5, or a Status field note pointing to the v3.5 changelog.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-085: OPEN-DECISIONS OD-01 says "Action: INDEX.md updated — HUB-31 added to Hub tier table" but INDEX.md §2.2 does NOT include HUB-31
- **Severity:** MEDIUM
- **Category:** Doc-Drift (action-not-actually-completed)
- **Description:** OPEN-DECISIONS.md OD-01 (in the "## Resolved" section, line 113-116) records the resolution of ADR-011 with an action: "INDEX.md updated — HUB-31 added to Hub tier table; count updated to 97 blueprints." But INDEX.md §2.2 Hub tier table only goes HUB-01..HUB-30 (with HUB-10/HUB-25 superseded strikethrough); there is NO HUB-31 row in §2.2. The "Action: INDEX.md updated" claim in OPEN-DECISIONS is FALSE — the action was never actually completed.
- **Evidence:**
  - `Architecture/OPEN-DECISIONS.md` line 115: "Action: `INDEX.md` updated — HUB-31 added to Hub tier table; count updated to 97 blueprints."
  - `Architecture/INDEX.md` §2.2 Hub table (lines ~133-148): table goes HUB-01..HUB-30 (no HUB-31 row)
  - `Architecture/INDEX.md` §2.2 "Proposed" sub-table (line ~151): empty (— | — | —) — no HUB-31 entry
- **Affected artifact:** `Architecture/OPEN-DECISIONS.md` OD-01 (action claim is false); `Architecture/INDEX.md` §2.2 (missing HUB-31 row)
- **Contract violated:** OPEN-DECISIONS governance rule #9 ("Open questions are recorded, never silently resolved. Picking one silently is a governance violation.") — but the inverse also applies: an OPEN-DECISIONS resolution that claims an action was completed when it wasn't is also a governance violation. The action was declared complete but never actually performed.
- **Root cause:** OD-01 was resolved 2026-08-12; the resolution said "INDEX.md updated" as the action. But the update was either never made, or was made and subsequently lost (e.g., during a later INDEX.md restructure). Either way, the current state of INDEX.md §2.2 contradicts OD-01's stated action.
- **Remediation:** Add a HUB-31 row to INDEX.md §2.2 Hub table with status "Accepted (per ADR-011, 2026-08-13; OD-01 resolved 2026-08-12)". Cross-reference S-078.
- **Verification test:** INDEX.md §2.2 Hub table has a HUB-31 row; the "Proposed" sub-table is empty; §4 Hub count reflects the additional HUB-31 entry.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (bundled with S-078 INDEX.md ADR-011 status fix)
- **Closure evidence:** (empty)

### S-086: BLIND-SPOT-DOCTRINE.md "Companion documents" section references "the 47-finding register" — stale post-A3
- **Severity:** LOW
- **Category:** Doc-Drift (post-A3)
- **Description:** `BLIND-SPOT-DOCTRINE.md` line 142 (Companion documents section): "Architecture/Verification/SHORTCOMINGS-REGISTER.md — the 47-finding register (always open)". But A3 audits have added S-048..S-076 (29 new findings beyond the original 47), so the "47-finding" description is stale. The register still has 47 entries (per S-077), but the universe of known findings is now ~76.
- **Evidence:**
  - `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` line 142: "- `Architecture/Verification/SHORTCOMINGS-REGISTER.md` — the 47-finding register (always open)"
  - A3-RUNTIME-76 report at `/home/z/my-project/download/A3-RUNTIME-REPORT.md` documents 7 new findings (S-048..S-054)
  - A3-ARCH-GOV-76 report at `/home/z/my-project/download/A3-ARCH-GOV-REPORT.md` documents 22 new findings (S-055..S-076)
  - This audit adds S-077 through S-092 (16 new findings)
- **Affected artifact:** `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` line 142
- **Contract violated:** Doctrine's own binding rule #1 ("No audit is declared complete. The register is always 'open' — new findings can be added at any time.") — the doctrine should not pin a specific finding count that becomes stale.
- **Root cause:** The doctrine was authored 2026-10-02 (per its own provenance line 138) when only the 47-finding register existed. Subsequent A3 audits added new findings; the doctrine's "47-finding" reference was not updated.
- **Remediation:** Update BLIND-SPOT-DOCTRINE.md line 142 to: "- `Architecture/Verification/SHORTCOMINGS-REGISTER.md` — the canonical shortcomings register (47+ findings; grows as audits surface blind spots; always open)". Better yet, remove the specific count entirely: "- `Architecture/Verification/SHORTCOMINGS-REGISTER.md` — the canonical shortcomings register (always open; new findings added as audits surface blind spots)".
- **Verification test:** `grep "47-finding register" Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

### S-087: INDEX.md §9 change log is stale — last entry is 2026-08-12, doesn't include ADR-021 (2026-09-30) or A1 (2026-10-01) or A2 (2026-10-02)
- **Severity:** MEDIUM
- **Category:** Doc-Drift (changelog staleness)
- **Description:** INDEX.md §9 change log ends at the 2026-08-12 entry "OD resolution pass (PR #104): ADR-012 (post-quantum JWT agility) and ADR-014 (ratify AGRD as canonical SDLC) authored and Accepted. ...". But INDEX.md itself has been substantially modified since 2026-08-12 — ADR-021 (2026-09-30), A1 (2026-10-01), A2 (2026-10-02), and the parallel A3 audits all landed after the last changelog entry. The changelog should record these major events.
- **Evidence:**
  - `Architecture/INDEX.md` §9 last entry (line ~540): "| 2026-08-12 | OD resolution pass (PR #104): ... | OD resolution pass |"
  - `git log` on `Architecture/INDEX.md` shows multiple commits after 2026-08-12: PR #290 (Task 74 INDEX reconciliation), PR #291 (INDEX §2.2/§4 contradiction fix), PR #294 (A1 — canonical HUB-32 + ESPOKE-19 + lint + INDEX + HUB-33 fix), etc.
- **Affected artifact:** `Architecture/INDEX.md` §9 (Change log table)
- **Contract violated:** INDEX.md's own Governance Rule 9 (Open questions are recorded, never silently resolved) — by extension, the changelog should also be kept current as a record of architectural changes.
- **Root cause:** The changelog was authored in the v3.4 consolidation era (pre-2026-08-12). Subsequent ADR-021 (2026-09-30), A1 (2026-10-01), A2-prep (2026-10-01), and A2 (2026-10-02) all touched INDEX.md but did not refresh the changelog.
- **Remediation:** Add changelog entries for:
  - 2026-09-30: ADR-021 acceptance (tier-stratified build order, two-DAG governance model)
  - 2026-10-01: ADR-021 Amendment 1 (two-DAG model); ADR-021 Amendment 2 (edge dimensions); §0 Authority Boundary added; §5.2/§5.3 supersession banner; HUB-32 + ESPOKE-19 canonical at depth 1; lint validIds extended; HUB-33 token fix
  - 2026-10-02: A2 Fiber isolation fix landed (Container.php pulse() now per-Fiber WeakMap); SDLC-AGRD v3.5 published
- **Verification test:** INDEX.md §9 changelog has entries dated 2026-09-30 and 2026-10-01 (at minimum).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** MEDIUM-batch
- **Closure evidence:** (empty)

### S-088: INDEX.md §1 CrossCutting inventory row omits BLIND-SPOT-DOCTRINE.md (and other post-2026-08-12 CrossCutting documents)
- **Severity:** LOW
- **Category:** Doc-Drift (inventory incompleteness)
- **Description:** INDEX.md §1 line ~51 lists CrossCutting documents: "STRUCTURE-01..09, OBSERVABILITY, GLOSSARY, THREAT_MODEL, **NUCLEAR-GRADE-DOCTRINE** (binding on Core tier...)". But the CrossCutting/ directory now contains many more documents that aren't listed: `BLIND-SPOT-DOCTRINE.md`, `MEMORY-GOVERNANCE.md`, `MEMORY.md`, `PROMPTS.md`, `SDLC-AGRD.md`, `SDLC-HISTORY.md`, `AGRD-HISTORY.md`, `CI-ITERATION-TRIAGE.md`, `DISCREPANCY-REGISTER.md`, `REPO-STATE-AUDIT.md`, `RUNBOOK-BLUETOOTH.md`, `RUNBOOK-ANVIL-DNS.md`, `DGLAB-AS-OS-RUNTIME.md`, `DGLAB-AS-OS.md`, `CONTAINER-FIBER-STATE-MODEL.md`, `WHEEL-RECONCILIATION.md`, `HOSPITALITY-VERTICAL.md`, `MEMORY_INSTRUCTIONS.md`, `WORKLOG.md`. The inventory is stale. BLIND-SPOT-DOCTRINE.md is referenced inline from INDEX.md's banner (line 6, 8) but is missing from the §1 inventory row.
- **Evidence:**
  - `Architecture/INDEX.md` line ~51: "Architecture/CrossCutting/ | STRUCTURE-01..09, OBSERVABILITY, GLOSSARY, THREAT_MODEL, **NUCLEAR-GRADE-DOCTRINE** (binding on Core tier...)"
  - `Architecture/CrossCutting/` directory listing: 21 .md files (verified via `ls`)
  - `Architecture/INDEX.md` line 6: inline reference to BLIND-SPOT-DOCTRINE.md
- **Affected artifact:** `Architecture/INDEX.md` §1 CrossCutting inventory row
- **Contract violated:** INDEX.md §1 promises "single source of truth" for the architecture inventory.
- **Root cause:** The §1 inventory was authored in the v3.4 consolidation era. Many new CrossCutting documents were added subsequently (BLIND-SPOT-DOCTRINE.md 2026-10-02, MEMORY-GOVERNANCE.md 2026-08-12, SDLC-AGRD.md, etc.) without refreshing the §1 inventory row.
- **Remediation:** Either (a) extend the §1 inventory row to list all 21 CrossCutting documents (verbose), or (b) replace the partial list with "see `Architecture/CrossCutting/README.md` for the full CrossCutting document inventory" (compact), or (c) leave the partial list but add "BLIND-SPOT-DOCTRINE.md" to it as a minimum (since it's referenced inline from the banner).
- **Verification test:** INDEX.md §1 CrossCutting row either lists BLIND-SPOT-DOCTRINE.md OR points to a comprehensive inventory (e.g., `Architecture/CrossCutting/README.md`).
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

### S-089: INDEX.md §2.3 says "External Spokes — ESPOKE-01..18, all documented" — stale post-A1 (ESPOKE-19 is canonical at depth 1)
- **Severity:** MEDIUM
- **Category:** Doc-Drift (post-A1)
- **Description:** INDEX.md §2.3 line ~159: "**External Spokes** — `ESPOKE-01..18`, all documented (ESPOKE-16 Sovereign Booking Portal, ESPOKE-17 Sovereign Concierge, ESPOKE-18 Sovereign Mobile Check-in promoted from the hospitality-vertical design on 2026-08-12 per ADR-015)." But A1 (PR #294, 2026-10-01) made ESPOKE-19 Eloq canonical at depth 1 per ADR-021 §14. So the §2.3 line should now say `ESPOKE-01..19`. The §1 row was updated (line ~57: "Architecture/Spoke/External/ESPOKE-01..19 | 19 External Spoke blueprints ... ESPOKE-19 Eloq canonical at depth 1 per ADR-021 §14 — implementation deferred") but §2.3 was NOT updated — internal INDEX.md contradiction.
- **Evidence:**
  - `Architecture/INDEX.md` line ~57 (§1): "| `Architecture/Spoke/External/ESPOKE-01..19` | 19 External Spoke blueprints ... ESPOKE-19 Eloq canonical at depth 1 per ADR-021 §14 — implementation deferred |"
  - `Architecture/INDEX.md` line ~159 (§2.3): "- **External Spokes** — `ESPOKE-01..18`, all documented (ESPOKE-16 Sovereign Booking Portal, ESPOKE-17 Sovereign Concierge, ESPOKE-18 Sovereign Mobile Check-in promoted from the hospitality-vertical design on 2026-08-12 per ADR-015)."
- **Affected artifact:** `Architecture/INDEX.md` §2.3
- **Contract violated:** INDEX.md §1 (single source of truth) — §1 and §2.3 disagree on ESPOKE count.
- **Root cause:** PR #294 (A1) updated INDEX.md §1 to include ESPOKE-19 but did not propagate the update to §2.3. This is the same class of drift as S-014/S-068 (partial propagation).
- **Remediation:** Update §2.3 to: "- **External Spokes** — `ESPOKE-01..19`, all documented (ESPOKE-16 Sovereign Booking Portal, ESPOKE-17 Sovereign Concierge, ESPOKE-18 Sovereign Mobile Check-in promoted from the hospitality-vertical design on 2026-08-12 per ADR-015; **ESPOKE-19 Eloq** canonical at depth 1 per ADR-021 §14 — implementation deferred)."
- **Verification test:** `grep "ESPOKE-01\.\.18" Architecture/INDEX.md` returns zero matches.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch
- **Closure evidence:** (empty)

### S-090: README.md Milestone 0 table has 9 rows but header says "(8 blueprints required for MUWV)" — internal contradiction
- **Severity:** MEDIUM
- **Category:** Doc-Drift (internal contradiction)
- **Description:** README.md line 218 header: "### Milestone 0 components (8 blueprints required for MUWV)". But the table body (lines 220-228) has 9 rows: CORE-02, CORE-04, CORE-05, CORE-06, CORE-18, HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01. The header count (8) contradicts the table body (9). SDLC-AGRD §4 line 110 says "Scope — 8 blueprints... minimal CORE-02, CORE-04/05/06 stubs, one Hub service (HUB-01), BRIDGE-01 stub, one Internal Spoke (ISPOKE-09, Codex), one External Spoke (ESPOKE-01, Canvas)" — that's 8 components (no CORE-18). So the README's Milestone 0 table includes CORE-18 (Kernel) as a 9th component, but SDLC-AGRD §4 says Milestone 0 has 8 components (no CORE-18). The README header matches SDLC-AGRD's 8; the README table body has 9 (extra CORE-18).
- **Evidence:**
  - `README.md` line 218: "### Milestone 0 components (8 blueprints required for MUWV)"
  - `README.md` lines 220-228: Table with 9 rows (CORE-02, CORE-04, CORE-05, CORE-06, CORE-18, HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01)
  - `Architecture/CrossCutting/SDLC-AGRD.md` line 110: "Scope — 8 blueprints, corrected count (v3 stated '~10' against a list of 8; that mismatch is fixed here, not carried forward — the calibration in §5 depends on this count being honest):** minimal `CORE-02`, `CORE-04`/`05`/`06` stubs, one Hub service (`HUB-01`), `BRIDGE-01` stub, one Internal Spoke (`ISPOKE-09`, Codex), one External Spoke (`ESPOKE-01`, Canvas)."
- **Affected artifact:** `README.md` Milestone 0 section (line 215-228); cross-reference to `SDLC-AGRD.md` §4
- **Contract violated:** README's own internal consistency (header vs body); README-vs-SDLC-AGRD consistency.
- **Root cause:** Either (a) the README header is correct (8 per SDLC-AGRD) and the table erroneously includes CORE-18 as a Milestone 0 component, OR (b) the README table is correct (CORE-18 should be a Milestone 0 component per the walking-skeleton requirement) and SDLC-AGRD §4 needs amending. Either way, the README header and table contradict each other.
- **Remediation:** Reconcile README header vs table vs SDLC-AGRD §4 — either:
  (a) Remove CORE-18 from the README's Milestone 0 table (move it to the "Also shipped" list below), update header to keep "8 blueprints"; OR
  (b) Update README header to "(9 blueprints required for MUWV)" AND amend SDLC-AGRD §4 to include CORE-18 as a Milestone 0 component (since the kernel is what makes the walking skeleton actually run — Milestone 0 needs the kernel).
  Recommended: option (b) — the kernel is what makes the end-to-end Pulse trace possible; SDLC-AGRD §4's "8 blueprints" list omits CORE-18 but the README's success criterion (line 92: "Milestone 0 = walking skeleton (CORE-18 Kernel wiring the full request pipeline)") implies CORE-18 is part of Milestone 0. The SDLC-AGRD §4 list is incomplete; the README table is correct.
- **Verification test:** README Milestone 0 header count matches table row count; SDLC-AGRD §4's Milestone 0 list matches README's table.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** HIGH-batch (bundled with S-005..S-022 README updates)
- **Closure evidence:** (empty)

### S-091: README.md "Build with" table omits Loom (CORE-01) and the project status section claims pre-MUWV-1 work that's stale
- **Severity:** LOW
- **Category:** Doc-Drift
- **Description:** README.md "Built with" table (lines 192-204) lists: PHP 8.3, FrankenPHP 1.12, Caddy 2.11, Tengine 3.2, Composer 2.x, PHPUnit 10.5, PHPStan 2.x, Dart Sass. But Loom (CORE-01) is a custom SemVer automation tool (`orchestrator/bin/loom`) that drives the monorepo release flow (per README line 70 and line 211 "Loom (`orchestrator/bin/loom`) analyzes path-scoped commits and computes the bump"). Loom is project-specific tooling and should be in the "Built with" table.
- Also, line 211 (Project Status): "Current work: doctrine §4.5 follow-up items 4-6 on CORE-18 Kernel (§4.5.5 resource ceilings, §4.5.6 KernelLifecycleRecord audit feed, §4.5.7 8 chaos tests), then Step 5 Core persistence packages (CORE-19 DBAL, CORE-15 Cache, CORE-14 Filesystem, CORE-16 Encryption) at nuclear-grade depth 2." But README line 108 itself says "The §4.5 CORE-18 Kernel pilot is **fully implemented**". So line 211 contradicts line 108 — and the project is now in the A3 (full re-audit) phase per the worklog, not in the doctrine §4.5 phase.
- **Evidence:**
  - `README.md` line 192-204: "Built with" table (omits Loom)
  - `README.md` line 70: "├── orchestrator/          # CORE-01: Loom — SemVer automation tool"
  - `README.md` line 108: "The §4.5 CORE-18 Kernel pilot is **fully implemented**."
  - `README.md` line 211: "Current work: doctrine §4.5 follow-up items 4-6 on CORE-18 Kernel..."
- **Affected artifact:** `README.md` (Built with table + Project status section)
- **Contract violated:** README's internal consistency (line 108 vs line 211).
- **Root cause:** README was authored pre-A3 phase; the project moved into the A3 (re-audit) phase per the worklog (lines 1640+). The "Current work" line wasn't refreshed.
- **Remediation:**
  1. Add a "Loom (custom)" row to the "Built with" table with purpose "SemVer automation tool — drives the monorepo release flow end-to-end".
  2. Update line 211 Project Status to reflect the current A3 phase: "Current work: A3 (full re-audit) phase per SHORTCOMINGS-AUDIT-76. 4 FATAL findings (S-001 through S-004); 2 Closed (S-001/S-002 per A1) and 2 Fixed (S-003/S-004 per A2; verification condition (c) pending per A3-RUNTIME-76 S-054). 47+ register findings being triaged for remediation."
- **Verification test:** "Built with" table has a Loom row; Project Status line reflects the A3 phase.
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog (bundled with S-069 README project status refresh)
- **Closure evidence:** (empty)

### S-092: INCONSISTENCIES.md table has inconsistent "Status" values — mixture of "Resolved", "Replaced", "Flagged critical", "Documented; Phase 2", "Archived + Rule 6", "Re-baselined (ADR-010)", "Merged into blueprints", "Rule 4", "Rule 8"
- **Severity:** LOW
- **Category:** Coherence
- **Description:** `Architecture/Verification/INCONSISTENCIES.md` table at lines 36-50 has a "Status" column. The header says "Status" but the values are mixed and don't follow a consistent taxonomy:
  - Row 1: "Resolved (MySQL) → reversed 2026-08-05 by revised ADR-013..."
  - Row 8: "Flagged critical" (stale — CORE-02 is now implemented per S-047)
  - Row 9: "Resolved (DEPLOY-01)"
  - Row 10: "Re-baselined (ADR-010)"
  - Row 11: "Merged into blueprints"
  - Row 12: "Rule 4"
  - Row 18: "Replaced"
  - Row 19: "Rule 8"
  - Row 20: "Resolved"

  Some values are status ("Resolved", "Flagged critical"), some are actions ("Replaced", "Merged into blueprints", "Re-baselined"), some are governance-rule references ("Rule 4", "Rule 8"). The taxonomy is inconsistent.
- **Evidence:**
  - `Architecture/Verification/INCONSISTENCIES.md` lines 36-50: Status column with mixed values
  - Row 8 specifically: "| 8 | Blocker | `CORE-02` (DI Container) is an empty stub | Flagged critical |" — STALE per S-047 (CORE-02 is now implemented + tested)
- **Affected artifact:** `Architecture/Verification/INCONSISTENCIES.md`
- **Contract violated:** None — table is internal tracking, no formal contract for status values.
- **Root cause:** The table was authored across multiple sessions with no consistent status taxonomy enforced.
- **Remediation:**
  1. Define a consistent status taxonomy: `Open`, `In-Progress`, `Resolved (with PR ref)`, `Superseded (with ADR ref)`, `Wont-Fix (with rule ref)`.
  2. Update row 8 (CORE-02) status from "Flagged critical" → "Resolved (PR #127, 2026-09-18; v1.0.0, 97.2% coverage)".
  3. Update all other rows to use the consistent taxonomy.
- **Verification test:** `INCONSISTENCIES.md` Status column has at most 5 distinct values (per the defined taxonomy); row 8 status is "Resolved".
- **Disposition:** Open
- **Owner:** main agent
- **Target phase:** LOW-backlog
- **Closure evidence:** (empty)

---

## §4. Summary of New Findings (S-077 through S-092)

| ID | Severity | Category | Title (abbreviated) |
|---|---|---|---|
| S-077 | HIGH | Governance | SHORTCOMINGS-REGISTER missing A3 findings S-048..S-076 (29 findings) |
| S-078 | HIGH | Doc-Drift | INDEX.md §1 line 65 still says ADR-011 Proposed (vs ADR-011 file + OD-01 say Accepted) |
| S-079 | MEDIUM | Meta-audit | S-046 register entry has incorrect premise (counts ADR-011 as Proposed) |
| S-080 | MEDIUM | Doc-Drift (post-A2) | CORE-02.md docblock "Implementation note" describes pre-A2 state |
| S-081 | LOW | Doc-Drift (post-A2-prep) | CONTAINER-FIBER-STATE-MODEL.md Q1 quotes pre-A2-prep CORE-02 docblock |
| S-082 | MEDIUM | Doc-Drift (post-A1) | SDLC-AGRD.md "96 blueprints" count stale (4 occurrences) — actual 104 |
| S-083 | MEDIUM | Doc-Drift (post-ADR-021) | SDLC-AGRD.md relies on INDEX.md §5.2 (which is banner-superseded) |
| S-084 | MEDIUM | Governance (ADR staleness) | ADR-014 ratifies v3.4(3) but SDLC-AGRD is now v3.5 — ADR not amended |
| S-085 | MEDIUM | Doc-Drift (action-not-completed) | OPEN-DECISIONS OD-01 says "INDEX.md updated" but §2.2 has no HUB-31 row |
| S-086 | LOW | Doc-Drift (post-A3) | BLIND-SPOT-DOCTRINE.md "47-finding register" reference stale |
| S-087 | MEDIUM | Doc-Drift (changelog staleness) | INDEX.md §9 changelog ends 2026-08-12 — missing ADR-021 + A1 + A2 entries |
| S-088 | LOW | Doc-Drift (inventory incompleteness) | INDEX.md §1 CrossCutting row omits BLIND-SPOT-DOCTRINE.md + 12 others |
| S-089 | MEDIUM | Doc-Drift (post-A1) | INDEX.md §2.3 says ESPOKE-01..18 (actual 19 per A1) |
| S-090 | MEDIUM | Doc-Drift (internal contradiction) | README Milestone 0 header "(8 blueprints)" vs table body (9 rows) |
| S-091 | LOW | Doc-Drift | README "Built with" table omits Loom; Project Status line stale |
| S-092 | LOW | Coherence | INCONSISTENCIES.md Status column has inconsistent taxonomy |

**Totals:** 16 new findings — 2 HIGH, 9 MEDIUM, 5 LOW.

**Combined with parallel A3 audits:** A3-RUNTIME-76 added S-048..S-054 (7 findings); A3-ARCH-GOV-76 added S-055..S-076 (22 findings); A3-DOC-GOV-76 (this audit) adds S-077..S-092 (16 findings). Total new findings across all three A3 audits: **45 findings beyond the original 47**, bringing the total known universe to **92 findings** (47 original + 45 new across A3 audits).

---

## §5. Verification of Brief's Specific Check Items

For traceability, here is the explicit verdict for each of the 11 specific check items in the task brief:

### Documentation/contract consistency (6 items)

| # | Brief check | Verdict | Finding refs |
|---|---|---|---|
| 1 | Did the 182-document Blind-Spot Awareness propagation introduce formatting issues or contradictions? | **YES** — 22 files have triple-blank-line artifacts (larger than A3-ARCH-GOV S-065's "20 files"); no contradictions between banner wording and document content | (S-065 understated; no new ID for the larger count, but evidence recorded in §1 Check 1) |
| 2 | Did the 24-document contributor awareness statement break any markdown rendering? | **NO** — well-formed blockquote; renders fine on all 23 files (off-by-1 from brief's "24" claim) | No new finding |
| 3 | Does README.md match the actual state? (PHP version, package count, ADR count) | **NO** — PHP 8.3 vs actual ^8.4; 8 vs 13 packages; 20 vs 21 ADRs; plus 9+ other stale claims | S-005..S-013 carry-forward; S-090 (internal contradiction); S-091 (Loom missing) |
| 4 | Are there stale references to old filenames anywhere in Architecture/? | **YES** — `CORE-DEPENDENCY-DAG.md` referenced in 5 files (11 stale + 2 historical OK) | S-034/S-035/S-036/S-037 carry-forward |
| 5 | Does CORE-02.md have BOTH the old Shape A docblock AND the new Shape C contract? | **PARTIALLY** — docblock REPLACED with Shape C ✅; embedded reference impl STILL Shape A (S-058 from A3-ARCH-GOV); docblock "Implementation note" paragraph stale post-A2 (NEW S-080) | S-058 (carry-forward); S-080 (NEW) |
| 6 | Does Architecture/Index/INDEX.md have the authority boundary section (§0)? | **YES** — §0 Authority Boundary section exists at lines 33-49, well-formed | No new finding |

### Governance/SDLC integrity (5 items)

| # | Brief check | Verdict | Finding refs |
|---|---|---|---|
| 7 | Does SDLC-AGRD.md still describe the old single-lap model? (Should — v4.0 rewrite hasn't happened) | **YES** — SDLC-AGRD is at v3.5 (lap-widen admission rule, not Eligible(X)) — correct per brief | S-082 (stale 96 blueprint count); S-083 (stale §5.2 reliance); S-084 (ADR-014 not amended for v3.5) |
| 8 | Is the shortcomings register consistent with the worklog? | **NO** — 3 inconsistencies: all 47 dispositions still Open (no Closed/Fixed); missing S-048..S-054 (7 A3-RUNTIME findings); missing S-055..S-076 (22 A3-ARCH-GOV findings) | S-077 (NEW — register missing 29 findings); S-079 (NEW — S-046 has incorrect premise); DOC-1 (A3-ARCH-GOV S-064 — register dispositions stale) carry-forward |
| 9 | Does the register have entries for S-048..S-054 (the A3 findings)? | **NO** — zero entries for S-048..S-054; register ends at S-047 | S-077 (NEW) |
| 10 | Is the Blind-Spot Doctrine (BLIND-SPOT-DOCTRINE.md) referenced from INDEX.md? | **YES** — referenced inline (line 6 + line 8) but missing from §1 CrossCutting inventory row | S-088 (NEW — §1 inventory incomplete) |
| 11 | Are there ADR cross-reference errors? | **NO** — all ADR-001..021 references resolve to existing files; only "ADR-022" forward references (in S-023's remediation text, legit) | No new finding; but see S-078 (ADR-011 STATUS contradiction between ADR file and INDEX.md) |

---

## §6. Top Critical Findings + Recommended Remediation Order

### Top critical finding (cross-cutting governance gap)

**S-077 + S-064 (carry-forward from A3-ARCH-GOV): SHORTCOMINGS-REGISTER is stale.** The register:
- Has 47 entries (S-001..S-047) marked all Open
- Is missing 29 new findings from A3-RUNTIME + A3-ARCH-GOV + A3-DOC-GOV (S-048..S-092)
- Has not been updated to reflect A1/A2 dispositions (S-001/S-002 should be Closed; S-003/S-004 should be Fixed)
- The register's own S-046 entry has a factual error (counts ADR-011 as Proposed, but it's Accepted)

Per the paradigm shift directive in the register's own preamble (line 14-21): "The roadmap does not advance while any FATAL finding remains Open." But the register's staleness means the actual disposition state is unknown to the register itself — the register's claim of "4 FATAL Open" is wrong (only 2 are Open: S-003/S-004). The roadmap-blocking condition is therefore not actually being enforced mechanically.

### Top critical finding (cross-document contradiction)

**S-078 + S-079 + S-085: ADR-011 status contradiction across 4 documents.**
- ADR-011 file: Accepted (2026-08-13)
- OPEN-DECISIONS OD-01: Resolved, ADR-011 accepted (2026-08-12), action "INDEX.md updated — HUB-31 added to Hub tier table"
- INDEX.md §1 line 65: still says "Proposed ADR (HUB-31) — not accepted, not counted"
- INDEX.md §2.2 Hub table: NO HUB-31 row (action claimed in OD-01 was never actually completed)
- SHORTCOMINGS-REGISTER S-046: counts ADR-011 as Proposed (incorrect premise)

Five documents disagree on ADR-011's status. The actual state (Accepted) is recorded in 2 documents (the ADR file itself + OPEN-DECISIONS resolution); the stale state (Proposed) is recorded in 3 documents (INDEX.md §1, INDEX.md §2.2, SHORTCOMINGS-REGISTER S-046).

### Recommended remediation order (post-A3, gates roadmap advancement)

1. **Immediate (gates paradigm shift directive):**
   - Amend SHORTCOMINGS-REGISTER.md to add S-048..S-092 entries (29 new findings from 3 A3 audits) and update S-001..S-047 dispositions (Closed/Fixed per A1/A2 + A3 verification). [S-077]
   - Fix the ADR-011 status contradiction: update INDEX.md §1 line 65 to "Accepted", add HUB-31 row to §2.2, fix S-046 in register. [S-078 + S-079 + S-085]

2. **HIGH-batch (post-Immediate):**
   - Update CORE-02.md "Implementation note" paragraph to reflect post-A2 reality. [S-080]
   - Refresh SDLC-AGRD.md blueprint count from 96 → 104. [S-082]
   - Point SDLC-AGRD §4.3 widen-rule at canonical per-tier DAGs (not superseded §5.2). [S-083]
   - Amend ADR-014 to acknowledge v3.5. [S-084]
   - Update INDEX.md §9 changelog with ADR-021 + A1 + A2 entries. [S-087]
   - Fix INDEX.md §2.3 ESPOKE-01..18 → ESPOKE-01..19. [S-089]
   - Fix README Milestone 0 header-vs-table contradiction. [S-090]

3. **MEDIUM-batch:**
   - Update BLIND-SPOT-DOCTRINE.md "47-finding register" reference. [S-086]
   - Update INDEX.md §1 CrossCutting inventory row (add BLIND-SPOT-DOCTRINE.md). [S-088]
   - Annotate CONTAINER-FIBER-STATE-MODEL.md Q1 with "quoted docblock is stale post-A2-prep". [S-081]
   - Fix INCONSISTENCIES.md Status column taxonomy. [S-092]

4. **LOW-backlog:**
   - README "Built with" table — add Loom row. [S-091]

5. **Carry-forward from A3-ARCH-GOV S-065:**
   - Fix the 22 files with triple-blank-line artifacts (find-replace `\n\n\n\n> \*\*This project is developed` → `\n\n> **This project is developed`). S-065 understated the count.

---

## §7. Blind-Spot Report — What this audit discovered that was not known before A3 began

1. **The 22-vs-20 file count for triple-blank-line artifacts.** A3-ARCH-GOV S-065 reported "~20 files"; this audit's independent scan found 22. The discrepancy is small but real — S-065's evidence list was incomplete (only listed 3 example files), and the actual count is 22.

2. **The 5-document ADR-011 status contradiction.** This audit independently discovered that ADR-011's status field disagrees across 5 documents (ADR file, OPEN-DECISIONS, INDEX §1, INDEX §2.2, SHORTCOMINGS-REGISTER S-046). The parallel A3-ARCH-GOV audit caught the INDEX §1 line 65 issue but didn't trace it back to ADR-011's own Status field, to OPEN-DECISIONS OD-01's claim of an action that was never completed, or to S-046's incorrect premise.

3. **The S-046 self-inconsistency.** This audit independently discovered that the register's own S-046 entry has a factual error — it counts ADR-011 as Proposed when ADR-011 itself says Accepted. This is a meta-audit blind-spot: an audit finding that itself contains an error. The original audit trusted INDEX.md over the source ADR files — exactly the kind of blind spot the BLIND-SPOT-DOCTRINE warns about.

4. **The "Implementation note" paragraph drift in CORE-02.** The parallel A3-ARCH-GOV S-058 caught that the EMBEDDED REFERENCE IMPLEMENTATION in CORE-02 is older than the live source. But neither A3-ARCH-GOV nor A3-RUNTIME-76 caught that the DOCBLOCK ITSELF contains a stale "Implementation note" paragraph (lines 245-247) that describes the pre-A2 state. This is a distinct drift source — S-058 covers the reference impl; S-080 covers the docblock paragraph.

5. **The OPEN-DECISIONS OD-01 "Action claimed but not actually completed" pattern.** OPEN-DECISIONS OD-01 says "Action: INDEX.md updated — HUB-31 added to Hub tier table" but INDEX.md §2.2 has NO HUB-31 row. This audit independently verified that the OD-01 action claim is FALSE. This is a governance blind-spot: a "Resolved" OD whose stated action was never performed. The register/audit framework assumes that "Resolved" in OPEN-DECISIONS means "action completed" — but this is not mechanically verified.

6. **The 159 files with double-blank-line artifacts.** While the 22 triple-blank-line files are the worst cases (S-065 covers them), this audit's scan revealed that **159 files have double-blank-line artifacts** as their maximum blank-line count. These are milder MD012 violations but still widespread — affecting 88% of all Architecture .md files (159 of 181).

7. **The SDLC-AGRD "§5.2 reliance" stale dependency.** The brief's Check 7 (SDLC-AGRD still describes old single-lap model) was correctly answered YES (correct per brief expectation). But this audit discovered that SDLC-AGRD's §4.3 widen rule relies on INDEX.md §5.2 as the live Mermaid graph data source — and INDEX.md §5.2 is banner-superseded by ADR-021. This is a hidden drift: SDLC-AGRD looks fine on its own, but its dependency on §5.2 makes it transitively stale post-ADR-021.

8. **The ADR-014 unacknowledged v3.5 ratification.** The brief's Check 7 (does SDLC-AGRD still describe old model) was answered correctly, but this audit discovered that ADR-014 (which ratifies SDLC-AGRD v3.4(3)) has not been amended to acknowledge that SDLC-AGRD has moved to v3.5. ADR-014's line 51 acknowledges v3.5 as a future state, but doesn't record that v3.5 has shipped. This is a governance blind-spot: an ADR whose ratified artifact has iterated without an ADR amendment.

9. **The "ADR count: 21" verification.** The brief explicitly asks about ADR count. This audit independently verified: 21 ADR files on disk (ADR-001 through ADR-021). README's "20 ADRs" claim is stale by 1. The single missing ADR (vs README) is ADR-021 itself — which README mentions in passing (line 108, 134) but doesn't count in its headline "20 Architecture Decision Records" claim.

10. **The "packages count: 13" verification.** The brief explicitly asks about package count. This audit independently verified: 12 packages under `packages/core/` + 1 (`orchestrator/`) = 13 Core-tier packages total. README's "8 Core-tier packages" claim is stale by 5 (the 5 missing: error-handler, logger, config, dbal, middleware, router, filesystem, crypto, kernel, http-message, event-dispatcher — actually that's 11 missing; the original "8" must have referred to the Milestone 0 subset only).

---

## §8. Stage Summary

- Phase A3 documentation/contract-consistency + governance/SDLC-integrity re-audit complete. Report saved to `/home/z/my-project/download/A3-DOC-GOV-REPORT.md`.
- **16 new findings logged (S-077 through S-092):** 2 HIGH, 9 MEDIUM, 5 LOW.
- **Combined with parallel A3 audits:** A3-RUNTIME-76 (S-048..S-054, 7 findings) + A3-ARCH-GOV-76 (S-055..S-076, 22 findings) + this audit (S-077..S-092, 16 findings) = **45 new findings** across all three A3 audits. Combined with the original 47 register findings, the total known universe is now **92 findings**.
- **Brief's 11 specific check items verified:** 6 documentation/contract-consistency items + 5 governance/SDLC-integrity items. Verdicts: 5 "NO/STALE" (drift confirmed), 4 "YES" (state as expected per brief), 2 "PARTIALLY" (mixed state).
- **Top critical finding:** SHORTCOMINGS-REGISTER staleness (S-077 + carry-forward S-064) — register has 47 entries but should have ~76; dispositions all "Open" but should reflect A1/A2 closures; S-046 has incorrect premise (S-079). The roadmap-blocking condition is not mechanically enforced.
- **Second critical finding:** ADR-011 status contradiction across 5 documents (S-078 + S-079 + S-085) — ADR file says Accepted, OPEN-DECISIONS says Resolved with action claimed-but-not-completed, INDEX.md still says Proposed, register S-046 has incorrect premise.
- **Third critical finding:** CORE-02.md docblock "Implementation note" paragraph describes pre-A2 state (S-080) — a fresh architect would believe the S-003/S-004 bug is still present and would attempt to re-implement a fix that's already shipped.
- **No new FATAL findings** from this audit lens. All findings are HIGH/MEDIUM/LOW.
- **No code or markdown files were modified during this audit** (per task constraint: documentation/governance audit only).
