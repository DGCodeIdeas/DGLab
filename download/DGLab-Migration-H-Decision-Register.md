# DGLab Old-to-New Architecture Migration Map
## Deliverable H — Decision Register (unresolved questions)

**Format:** Question · Evidence · Options · Recommendation · Consequences · Owner decision required before implementation?

---

### D-H1 — Does the walking skeleton become the LMS Product seed?
- **Evidence:** `app/ApplicationFactory.php` wires only Core+Vanguard, serves `/`, `/health`, `/hello`; imports zero LMS code; `app/Bridge|Spokes` are empty `.gitkeep` trees; LMS spoke has no runtime wiring; handoff: "Products/LMS is the deployable app".
- **Options:** (a) skeleton → `Products/LMS/` as seed (routes/controllers/entrypoint owned by the product; LMS composition added later on top); (b) write a fresh LMS composition; skeleton moves to a neutral shared location (would force an `App/` tier).
- **Recommendation:** (a).
- **Consequences:** (a) = fastest path, product owns its entrypoint, `App/` stays unjustified (per brief); (b) = duplicate skeleton work + risks `App/` creep that the brief warns against.
- **Owner decision required:** YES (defines Stage 6 scope).

### D-H2 — Release model after package dissolution
- **Evidence:** release.yml + Loom implement ADR-018/019 per-package 4-segment SemVer against `packages/<tier>/<short>/composer.json`; after Stage 9 those manifests no longer exist; monorepo-release job already exists (MUWV.Milestone.Lap.Patch tags).
- **Options:** (a) single monorepo version only (drop per-package jobs; ADR amendment needed); (b) keep per-package versions sourced from `component-inventory.yaml` (loom taught to read inventory); (c) freeze release automation until after migration.
- **Recommendation:** (c) during migration (Stage 9 disables per-package jobs explicitly), then (a) as the steady state unless per-package release needs re-emerge.
- **Consequences:** (a)/(c) lose per-package SemVer granularity (irrelevant pre-alpha); (b) adds inventory coupling to release tooling.
- **Owner decision required:** YES (before Stage 9 unfreezes release).

### D-H3 — Test layout
- **Evidence:** 177 tests co-located in packages; 2 root integration tests; 1 orphaned app/tests suite with no runner; brief target tree has `Testsuite/`.
- **Options:** (a) component tests stay co-located; `Testsuite/` holds cross-component only (recommended); (b) centralize all tests under `Testsuite/`.
- **Recommendation:** (a) — co-location preserves "required test ownership" enforceability per component (inventory `test_path`), avoids a 177-file big-bang move.
- **Consequences:** (b) would separate tests from components, weakening the inventory test-ownership rule and enlarging the diff.
- **Owner decision required:** YES (defines Stage 8).

### D-H4 — Database layout + migration-runner home
- **Evidence:** 9 SQL migrations in 3 packages, two naming conventions; PR #370 (unmerged, based on ff439a4) implements the Rev 5.3 runner at `packages/core/migration-runner` with CLI + tests; brief: "migration runner is a separate workstream; include its proposed ownership".
- **Options:** (a) `Database/Migrations/<Owner>/` + runner at `Database/Runner/` with CLI entry `Cli/migrate` (recommended); (b) migrations stay per-component; runner at `Core/Database/Runner`; (c) defer all Database/ decisions until PR #370 merges.
- **Recommendation:** (a); PR #370 rebases onto the migrated layout when the owner merges it.
- **Consequences:** (a) centralizes schema assets with clear ownership fields; runner becomes product-agnostic tooling.
- **Owner decision required:** YES (Stage 3/5/7 scope).

### D-H5 — NeoSPX partial component
- **Evidence:** lexer-only (1015L, 27 tests), zero dependents, absent from root composer.json, test bootstrap hardcodes `/home/dgci/www/DGLab`; EBNF spec + ADR-005 rename note ("ADR-023" pending); handoff: NeoSPX not default before runtime + conformance.
- **Options:** (a) carry as `Core/NeoSpx` as-is (add to composer + CI; fix bootstrap path) (recommended); (b) park in archive until parser/compiler work resumes; (c) finish the runtime first (out of migration scope).
- **Recommendation:** (a).
- **Consequences:** (a) preserves investment + keeps the eventual-primary-template path visible; (b) removes it from CI surface but loses test coverage.
- **Owner decision required:** YES (also fixes casing `NeoSpx` vs `NeoSPX` in namespace).

### D-H6 — Codex Product boundary confirmation
- **Evidence:** brief target structure (§3) lists BOTH `Products/LMS|Codex/` and `DGLab\Spokes\Internal\Codex`. The spoke `packages/spoke/internal/codex` (ISPOKE-09) is a reusable library capability (no HTTP controllers, no deployment).
- **Confirmed arrangement (per owner revision Issue 1):**
  - `Spokes/Internal/Codex/` (ISPOKE-09) remains the reusable capability spoke.
  - `Products/Codex/` is reserved as the intended Product boundary (deployable application).
  - **No Codex application code exists today.** The Product is recorded in the inventory at `status: blueprint-only` (Deliverable D-4 PRODUCT-CODEX entry); no migration action because there is no source to move.
  - When a Codex application is built (post-migration workstream), it will compose the Codex spoke at `Products/Codex/`.
- **Recommendation:** confirmed (per owner revision; not deferred — proposed and reserved as the intended boundary).
- **Consequences:** inventory carries PRODUCT-CODEX as blueprint-only; migration stages do NOT touch `Products/Codex/` (no code to migrate); the migration does NOT invent Codex application code to fill the directory (per owner directive Issue 1).
- **Owner decision required:** NO (confirmed by owner revision).

### D-H7 — Root-doc corpus + scripts re-homing depth
- **Evidence:** 23 root .md (~800KB, incl. 367KB `document.md`, 251KB tracked `worklog.md`); 12 scripts referenced by 3 workflows.
- **Options:** (a) minimal: move research/bulk docs to `Docs/`, keep ops docs at root; scripts → `Tools/` (recommended); (b) full curated split with index regeneration; (c) leave in place.
- **Recommendation:** (a).
- **Consequences:** (a) keeps diffs small while cleaning the root; workflow path updates required in lockstep.
- **Owner decision required:** YES (Stage 7 scope).

### D-H8 — `Public/` legacy root retirement
- **Evidence:** brief says "Preserve or migrate only through a coordinated change"; after Stage 6 the web docroot is `Products/LMS/public/`; the legacy `public/` at root is empty of anything else after the move (xff.php moves too).
- **Options:** (a) delete root `public/` at Stage 10 (recommended); (b) keep a redirect stub.
- **Recommendation:** (a) — anvil references are updated in Stage 6; a root-level stub reintroduces the exposure risk the brief prohibits (config/secrets outside product docroot).
- **Owner decision required:** YES.

### D-H9 — Governance repair scope
- **Evidence:** INDEX undercounts (102 vs 104 blueprints; HUB-31/32 missing from §2.2; ESPOKE 18 vs 19); ADR-011 four-way status contradiction; changelog ends 2026-09-24; S-055..S-065 findings cataloged in A3-ARCH-DAG-REPORT.md (at repo root).
- **Options:** (a) migration only establishes component-inventory.yaml; INDEX/DAG repairs = separate bounded governance task (recommended); (b) fold repairs into Stage 1.
- **Recommendation:** (a) — keeps the restructure review-free of doc-edit noise; inventory institutionalizes corrected counts from day one.
- **Owner decision required:** YES.

### D-H10 — `App/` tier
- **Evidence:** no shared application-level infrastructure exists beyond the skeleton (which D-H1 assigns to Products/LMS); brief: "conditional, justify before proposing".
- **Recommendation:** NOT introduced. Revisit only when a second Product emerges with shared composition needs.
- **Owner decision required:** confirmatory NO.

### D-H11 — Repo hygiene items
- **Evidence:** `.phpunit.cache/test-results` committed; `worklog.md` (251KB) tracked; `.roo/mcp.json` hardcodes `/home/dgci/www/DGLab`; anvil web-UI shells to nonexistent `anvil/bin/anvilctl`.
- **Options:** untrack cache; move/untrack worklog per team convention; fix or drop .roo; fix anvilctl path.
- **Recommendation:** untrack cache (Stage 0); worklog → `Docs/Operations/` or untracked (Stage 7); leave .roo (agent-local); fix anvilctl path (Stage 6, one line).
- **Owner decision required:** YES for worklog policy; rest = low-risk fixes.

### D-H12 — Pre-existing defects carried (NOT fixed by migration)
- **Evidence:** (1) LMS re-enrollment-after-cancel violates `UNIQUE(course_id, learner_id)` (service vs migration 003 conflict); (2) LMS progress persistence missing (entity + table exist, no repo/use case); (3) `/health/{live,ready,dependencies}` registered in Vanguard contracts but unrouted; (4) crypto Encrypter/Envelope untested; (5) Vanguard JWT/rate-limit/audit stubs; (6) AuthMiddleware::stampRequestContext no-op; (7) core-filesystem orphaned.
- **Recommendation:** record all seven in the inventory/decision trail as post-migration workstreams; the migration changes locations/namespaces ONLY (one exception: routing the health endpoints may be folded into Stage 6 if the owner chooses).
- **Owner decision required:** YES only for whether health-endpoint routing rides in Stage 6 or a later PR.

---

**Explicit statement per brief §13:** none of the above are silently decided by this analysis; recommendations are recorded with rationale and consequences, and each marked whether owner input is required before implementation.

*Deliverable H complete.*
