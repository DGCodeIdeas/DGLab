# License Inventory and Proposed Dispositions (Decision Gate)

**Status:** **DECIDED (2026-10-13)** — ratification recorded in §0 below. Pre-decision status was PROPOSED; **no license declaration was changed by this document itself.** Alignment edits are executed as a separate follow-up change (§0.3).
**Date:** 2026-10-13 (inventory); decision same day
**Baseline:** `main` @ `a604241` (this document's commit parent)
**Prepared for:** SAAI remediation plan, Phase 0 (licensing decision) — PR 1 of the remediation sequence
**Audience:** tech lead (decision authority). Not legal advice; see §7.

---

> **⚠️ Blind-Spot Awareness:** This inventory's claims are **mechanically derived, not exhaustively verified**. It enumerates license declarations and third-party trees discoverable from the tracked file set at the baseline commit. It does **not** scan Git history, untracked local files, or transitive dependency trees, and it cannot detect an unlicensed third-party file that omits both a LICENSE file and any license metadata. A third-party component whose headers were stripped will not appear here. **The number of findings found is not the number of findings that exist.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

---

## 0. Decision record (ratified 2026-10-13 by the tech lead)

**D-1 — Project license:** **Apache-2.0** (Option A of §5). The root `LICENSE` file already carries the operative Apache-2.0 grant, so no retroactive rights conflict is created for prior public commits of original code. Alignment obligations follow in §0.3.

**D-2 — `skills/` disposition:** **Relocate to a separate repository** (owner-side move to a private/personal workspace first), then a follow-up DGLab PR removes the tree from `main`. No DGLab-side deletion occurs before the relocation is confirmed, so no sole copy is destroyed.

**Standing consequences of D-1/D-2, recorded explicitly:**

1. **History retention caveat.** Removing `skills/` from the tree at HEAD does **not** retract material already contained in public history. Buckets M3 (Z.ai non-commercial, 5 skills) and M5/M4 (unknown provenance, 51 skills) remain present in historical commits unless a separate, explicitly-approved Git-history action is taken. Per the remediation plan, history rewriting is its own decision and is **not** approved by D-2; this caveat is registered until the owner decides. Recommended interim disposition: annotate the register with a finding whose closure condition is "history exposure for M3/M4/M5 either eliminated by approved history action or explicitly accepted with rationale."
2. **Attribution follows the material.** The relocated skills repository must carry the M1/M2/N1 license files (MIT/Apache texts with their copyright notices — "Zara Zhang", "op7418 (歸藏)", "z-ai-web-dev-sdk Skills") with the tree. Relocation does not relicense anything; DGLab's Apache-2.0 choice does not extend to any of it.
3. **`THIRD-PARTY-NOTICES.md`** is no longer needed in DGLab after the removal PR (the licensed material exits the tree), *provided* the archive tree is left untouched. No new notices are required for `archive/Legacy` (self-authored, Apache-2.0 file retained as provenance per its `ARCHIVED.md`).
4. **`download/` posture.** The SPEC/report documents show no restrictive markings (§3) and are treated as project-original under D-1.

**§0.3 — Alignment change surface (to execute after this PR merges, as the follow-up "license alignment" change; 21 edits, 0 file moves):**

- `README.md:467` — "MIT — see LICENSE" → "Apache-2.0 — see LICENSE"
- root `composer.json:5` — `"proprietary"` → `"Apache-2.0"`
- 19 × `packages/**/composer.json` — `"MIT"` → `"Apache-2.0"`
- `LICENSE` — **unchanged** (already correct)
- No `skills/` file is touched by this surface; the removal PR is separate (D-2, gated on owner relocation)

## 1. Method and evidence base

All counts and classifications below were produced by mechanical inspection of the tracked file set at the baseline commit (no reliance on prior audit prose). Reproduction commands are listed in Appendix B.

Scope covered:
- Every tracked file named `LICENSE*`, `COPYING*`, `NOTICE*` (26 files total; 24 under `skills/`, plus root `LICENSE` and `archive/Legacy/LICENSE`).
- The `license` field of all 20 tracked `composer.json` files (root + 19 packages).
- The `license:` key in the YAML frontmatter of every `skills/*/SKILL.md`.
- Prose license declarations in `README.md` and `CHANGELOG.md`.
- Source-code attribution sweep of `packages/**`, `app/**`, `orchestrator/**`, `scripts/**`, `anvil/**` for third-party copyright/permission headers.

Known limitations (each is a scope boundary of this PR, not a finding):
1. Shallow clone in the review environment → no full-history scan. History-based secret/license scanning is Phase 1 work.
2. Frontmatter sweep read the first 1500 characters of each `SKILL.md`; one contradiction (`skills/charts`) was found only on follow-up targeted grep (frontmatter at line 28). Deeper metadata inside skill bodies was not exhaustively parsed.
3. `composer.lock` is not tracked; dependency-license analysis therefore covers **declared constraints only**, not a resolved set.
4. This document cannot establish **ownership** of the unlabeled `skills/` material (bucket U in §4). Only the tech lead can.

---

## 2. The declaration conflict matrix (project's own license)

Five declarations describe the license of DGLab itself. They are mutually inconsistent.

| # | Source | Declares | Exact location |
|---|---|---|---|
| D1 | `LICENSE` (root) | **Apache-2.0** (full text, 11,357 bytes) | `/LICENSE` |
| D2 | `README.md` | **MIT** | `README.md:467` — "MIT — see LICENSE" |
| D3 | Root `composer.json` | **proprietary** | `composer.json:5` — `"license": "proprietary"` |
| D4 | 19 package manifests | **MIT** | `license` field of every `packages/**/composer.json` (19/19, verified) |
| D5 | GitHub repo metadata | **Apache-2.0** | derived from D1 by GitHub; API `spdx_id: Apache-2.0`

Additionally inside the archived tree (provenance-only, flagged not fixed):

| # | Source | Declares |
|---|---|---|
| D6 | `archive/Legacy/LICENSE` vs `archive/Legacy/composer.json` | file is **Apache-2.0**; manifest says **MIT** (self-contradictory within the archive) |

Observations for the decision:

- D1 is the operative instrument: a LICENSE file at the repository root of a **public** repository grants recipients its terms (Apache-2.0) regardless of the other four declarations. Any remediation that instead chooses MIT or proprietary **must account for rights already conveyed by the published Apache-2.0 grant for prior commits**; this is a one-way door for history, a forward-only choice for future releases.
- Apache-2.0 and MIT are both permissive and, for **project-original code**, a switch between them is a metadata + NOTICE exercise. The substantive differences are the express patent grant + patent-retaliation termination (Apache-2.0) and shorter text (MIT).
- "Proprietary" (D3) is not currently true of anything else in the repo and is incompatible with the tree's visibility; choosing it implies repository-visibility action, which is outside this PR's scope.

---

## 3. Project-original code: third-party content check (clean, with two footnotes)

Swept for bundled third-party source, copied headers, or attribution obligations:

| Area | Result |
|---|---|
| `packages/**` (19 packages, src+tests) | **No third-party copyright or permission headers found.** Mentions of "Monolog" in `core/logger` docblocks are convention citations, not code imports. |
| `app/`, `orchestrator/`, `scripts/`, `tests/` | Clean. |
| `anvil/**` | No vendored sources. Install scripts **download** third-party runtimes at deploy time (Tengine — BSD-2-Clause, Caddy — Apache-2.0, FrankenPHP — MIT). Downloading at install time places license obligations on the deployed host, not on this repository; no repo-side action required, recorded for completeness. |
| Composer requirements (root + packages) | PSR interface packages (`psr/*` — MIT) and dev tools (PHPUnit, PHPStan — MIT) are **not vendored** (`vendor/` and `composer.lock` are gitignored). Standard distribution: no license texts needed in-tree. |
| `download/SPEC-001-*.md` and A3 report set | No restrictive-header markers found (no "Confidential"/"Internal only"/"Do not distribute"). If any of these were intended to be non-public, that is a separate takedown question, not a license-field question. |
| `archive/**` | Retained per plan; contains its own self-contradictory metadata (D6) — documented here, deliberately not edited (archive is read-only provenance per its own `ARCHIVED.md`). |

**Conclusion:** everything the project calls its own framework code appears to be original. The license conflict in §2 is therefore resolvable by declaration choice alone for the code — **but not for `skills/`, which is the actual legal exposure (§4).**

---

## 4. `skills/` — third-party tree inventory (the real decision driver)

69 skill directories, ~1,076 tracked files, ~60.1 MB — of which `skills/design` alone is ~54.0 MB (the source of the repo's "HTML 90.2%" linguist skew). Referenced by **zero** project documents, workflows, or code (verified by grep of `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `DEPENDENCIES.md`, `Architecture/INDEX.md`, `.github/`). It is not part of the README's declared Repository Structure.

Bucketing by **observed license artifacts** (file text + frontmatter), not by directory name:

| Bucket | Members | Evidence | Redistribution under a permissive root license? |
|---|---|---|---|
| **M1 — MIT (12)** | `ASR`, `LLM`, `TTS`, `VLM`, `image-edit`, `image-generation`, `image-understand`, `podcast-generate`, `video-generation`, `video-understand`, `web-reader`, `web-search` | MIT `LICENSE.txt` present; copyright notice "Copyright (c) 2025 z-ai-web-dev-sdk Skills" (and 2026 variants) | **Compatible** — requires preserving each LICENSE.txt with its notice (currently satisfied in-tree) |
| **M2 — Apache-2.0 (2)** | `skill-creator` (full Apache text in LICENSE.txt), `seo-content-writer` (frontmatter only, no file) | frontmatter `license: Apache-2.0` | **Compatible** — `seo-content-writer` has a bare claim and no license text; provenance unverifiable from the repo alone |
| **M3 — Z.ai non-commercial (5)** | `charts`, `docx`, `pdf`, `pptx`, `xlsx` | `LICENSE.txt`: "Copyright (c) 2026 Z.ai… personal, educational, and non-commercial use only"; frontmatter `license: Proprietary. LICENSE.txt has complete terms` | **NOT compatible** with distributing the repo under MIT/Apache or any terms that grant commercial use. `pptx` is worse: its frontmatter points at a `LICENSE.txt` **that does not exist** — proprietary terms claimed, terms absent |
| **M4 — "Internal Tool" (1)** | `gift-evaluator` | frontmatter only; no license file; provenance unknown | **Indeterminate** — "Internal Tool" contradicts public redistribution unless authorship is the repo owner |
| **M5 — no license metadata (50)** | all remaining skills (incl. `gaokao-*`, `aminer-*`, `blog-writer`, `finance`, `coding-agent`, `ui-ux-pro-max`, …) and the `skills/design` router itself | no LICENSE file, no frontmatter license | **Unknown provenance** — default assumption must be *not ours to license*; owner can override per-item if authored by the tech lead |
| **N1 — nested template licenses (7)** | `skills/design/design-templates/ppt/guizang-ppt` (MIT, "op7418 (歸藏)"), 6× `html-ppt-zhangzara-*` (MIT, "Zara Zhang") | each carries its own MIT LICENSE + attribution files | **Compatible if kept as-is**; the parent `skills/design` router (M5) sits atop licensed MIT material it did not author |

Legal-relevant reading of this table, stated plainly:

1. **If the project adopts any permissive license (Apache-2.0 or MIT) and retains `skills/`, bucket M3 is a distribution-rights violation today** — and publishing under any of the current declarations (or keeping the existing Apache `LICENSE`) grants recipients rights over Z.ai material that the Z.ai terms do not convey. Buckets M4/M5 carry the same risk in "unknown" form.
2. **If the project becomes "proprietary,"** the same buckets are still mislabeled: the root Apache `LICENSE` (D1) controls what recipients actually receive; a public repo cannot be retroactively made proprietary without addressing the standing grant for existing commits.
3. `skills/` content is (by inspection) a personal AI-assistant workspace: academic-search wrappers, Chinese-market college-application skills, podcast/resume tooling. Nothing in the project's architecture, build, CI, or docs consumes it. Whatever the license decision, **its presence in DGLab is unexplained** — the repository's own README structure section omits it entirely.

---

## 5. Decision options and their exact edit surfaces

The owner's decision needed: **what license does DGLab itself have, and does `skills/` stay?** Options, with the complete change surface each implies (to be executed in **later** PRs, never bundled):

### Option A — Apache-2.0 (project's current effective state; SAAI-recommended default)

| Change | Files |
|---|---|
| Align D2–D4 to Apache-2.0 | `README.md:467` ("MIT" → "Apache-2.0"); root `composer.json:5`; 19 package `composer.json` files |
| No LICENSE edit needed | `LICENSE` already Apache-2.0 (11,357 bytes) |
| Retain `LICENSE.txt` files of M1/M2/N1 in place (attribution) | already satisfied |
| `skills/` disposition | see §6 matrix — M3/M4/M5 must exit the tree or receive written permission |
| Recommended addition | `NOTICE` file is **not required** by Apache-2.0 §4 for non-derivative bundling, but a `THIRD-PARTY-NOTICES.md` listing buckets M1/M2/N1 is cheap and forecloses future archaeology |

### Option B — MIT

| Change | Files |
|---|---|
| Replace root `LICENSE` with MIT text; align D2, D3, D4 | `LICENSE`; `README.md:467`; root + 19 `composer.json` |
| Rights-already-granted caveat | recipients of the public repo hold an Apache-2.0 grant for prior commits; a switch forward does not retroactively retract it |
| `skills/` disposition | identical constraints to Option A (M3/M4/M5) |
| Patent posture | forfeits Apache's express patent grant — a policy choice for a solo-authored project, low practical risk, but a real difference |

### Option C — Proprietary / closed

| Change | Files |
|---|---|
| Make visibility consistent with intent | repository must become private (or the Apache grant stands); this is a GitHub-settings decision, not a file diff |
| Align D1–D4 | replace `LICENSE`; fix README, root + 19 manifests |
| `skills/` disposition | even privately, M3's terms restrict *use* — private redistribution to self is lower-risk but the ownership question for M5/M4 still deserves a recorded answer |
| History caveat | forks/clones/prior visitors may retain permissive-granted copies of everything up to the cutover |

Under all options, **D6 (`archive/Legacy` self-contradiction) is intentionally left as-is** — archive is read-only provenance; annotate in this register instead of editing.

---

## 6. Proposed dispositions (superseded by §0 for M1–N1: RATIFIED via D-2)

| Bucket | Proposed action | Rationale |
|---|---|---|
| M1, M2, N1 (MIT/Apache third-party) | **Relocate with `skills/`** (preferred) or retain with attribution + `THIRD-PARTY-NOTICES.md` | Compatible, but the tree's presence in DGLab is unexplained even where legal |
| M3 (Z.ai non-commercial, 5 skills) | **Remove from the repository** unless written permission from Z.ai is obtained first | Distribution under permissive terms is not lawfully conveyable; `pptx` additionally claims terms that are absent |
| M4, M5 (unknown provenance, 51 dirs) | **Remove from DGLab** by default; individually re-admitted only if the owner records authorship | Unknown provenance must be presumed non-redistributable |
| Whole tree | If removal is chosen: one docs-clean PR (file deletions + `.gitignore` annotation + worklog entry), separate from any Git-history rewrite decision | Keeps functional changes and cleanup separated per remediation plan; history rewriting stays a distinct, explicitly-approved decision |
| Registration | Record the outcome as new findings in the register (closure conditions = the §5 edit surfaces executed + CI green), and — if a license ADR is wanted — propose `ADR-022` for the record, since "explicit license policy" is exactly ADR-shaped | Turns the decision into the same evidence-backed lifecycle every other finding follows |

---

## 7. What this PR does NOT do

- Does not modify `LICENSE`, README's license line, or **any** `composer.json` license field (root or package).
- Does not add, move, or delete any `skills/` file, any license text, or any attribution.
- Does not rewrite Git history and does not scan it (Phase 1 owns that).
- Does not reconcile `archive/Legacy`'s internal contradiction (D6) beyond recording it.
- Does not constitute legal advice; the buckets here are mechanical classifications of what the tree *declares*, and "compatible" means "textually compatible with permissive redistribution," which is a licensing-engineering judgment, not counsel's.

## 8. Acceptance criteria for the Phase 0 gate

1. Owner answers the two decision questions (project license; `skills/` disposition) — recorded in the PR conversation and/or as an ADR per §6.
2. Every bucket in §4 has a written disposition (retain / relocate / remove / permission-pending) — no bucket may remain "unaddressed" after this PR merges.
3. The follow-up alignment PR's file list matches §5 exactly (22 declaration sites: `LICENSE` (Option B/C only), `README.md:467`, root + 19 `composer.json`).
4. Any `skills/` removal PR lands with zero inbound-reference breakage (grep-verified; currently zero references exist, so this should be trivially satisfiable) and does **not** bundle history rewriting.
5. New findings opened from this inventory are registered with evidence pointers into this document.

**Status (2026-10-13):** criteria 1 and 2 **met** — decisions recorded in §0; all buckets carry dispositions (§6). Criterion 3 is defined by §0.3 (21 edits; `LICENSE` unchanged under Option A). Criteria 4–5 remain open and gate the follow-up PRs.

---

## Appendix A — exact evidence locations

- `README.md:465-467` — "## License / MIT — see LICENSE"
- `LICENSE` — full Apache-2.0 text
- `composer.json:5` — `"license": "proprietary"`
- 19× `packages/**/composer.json` — `"license": "MIT"` (bridge/vanguard, core/{config,container,crypto,dbal,error-handler,event-dispatcher,filesystem,http-message,kernel,logger,middleware,router}, hub/{config,identity}, spoke/external/canvas, spoke/internal/{codex,lms,showcase})
- `skills/{ASR,LLM,TTS,VLM,image-edit,image-generation,image-understand,podcast-generate,video-generation,video-understand,web-reader,web-search}/LICENSE.txt` — MIT
- `skills/pdf/LICENSE.txt` (and `charts`, `docx`, `xlsx`) — Z.ai non-commercial text; `skills/pptx/SKILL.md:7` — claims "LICENSE.txt has complete terms" with **no such file present**
- `skills/design/design-templates/ppt/{guizang-ppt,html-ppt-zhangzara-*}/LICENSE` — third-party MIT with named authors
- `archive/Legacy/LICENSE` (Apache-2.0) vs `archive/Legacy/composer.json` (`"license": "MIT"`)
- `anvil/config/versions.env` — version floors only; no secrets (checked)
- `git ls-files | grep -iE 'env$|\.pem$'` — tracked cert/key pair handled separately in Phase 1 (not duplicated here)

## Appendix B — reproduction commands (read-only)

```bash
git ls-files | grep -icE 'LICENSE|COPYING|NOTICE'            # 26
git ls-files | grep -iE 'LICENSE|COPYING|NOTICE' | grep -vc '^skills/'   # 2 (root + archive/Legacy)
grep -h '"license"' composer.json packages/*/*/composer.json packages/*/*/*/composer.json | sort | uniq -c
for f in skills/*/LICENSE.txt; do head -c 400 "$f" | grep -q 'non-commercial' && echo "$f Z.ai-NC"; done
grep -n '^license:' skills/*/SKILL.md | sort -t: -k3 | uniq -c -f2
du -sk skills/design skills/ | sort -rn
grep -rn 'skills/' README.md CHANGELOG.md CONTRIBUTING.md DEPENDENCIES.md Architecture/INDEX.md .github/  # empty
grep -rln 'Copyright' packages --include='*.php'            # empty for license-header matches
```
