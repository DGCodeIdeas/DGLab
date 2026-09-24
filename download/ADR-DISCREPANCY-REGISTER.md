# ADR / Repository Discrepancy Register — M0 (Protected Baseline)

**Purpose:** Per SPEC-001 §39 Phase 0 deliverable: *"documented ADR/repository discrepancies."* This register lists every drift between (a) the ADRs and README documentation, and (b) the executable repository state at HEAD `a4a3402`.

**Generated:** 2026-09-24
**Verified against:** DGLab HEAD `a4a3402` (the same commit the SPEC was verified against)

---

## Governance Principle

Per SPEC-001 §37:

> **Executable repository state is authoritative wherever implementation status can be determined automatically.**

When ADRs or README documentation disagree with executable repository state (file existence, directory counts, code structure), the **documentation is wrong**, not the code. Each discrepancy below is a documentation defect to be corrected.

---

## Discrepancy #1 — ADR-013 claims PostgreSQL driver is "shipped but disabled"; no such driver file exists

**Severity:** High (datastore architecture claims vs. actual code)

**Claimed in:** `Architecture/ADRs/ADR-013-mysql-primary-datastore.md`

> "PostgreSQL is **relegated behind the CORE-19 driver abstraction**: the PostgreSQL driver remains implemented in the DBAL but is **disabled by default**..."
>
> "The **PostgreSQL driver is shipped but disabled** (`enabled: false` in the default `core.database.drivers` config)..."

**Actual repository state:**

```text
$ ls packages/core/dbal/src/Driver/
MysqlDriver.php
SqliteDriver.php
```

No `PgsqlDriver.php`, no `PostgresDriver.php`, no `PostgreSQLDriver.php` exists anywhere in `packages/core/dbal/`. Verified via `find packages/core/dbal -iname "*Pgsql*" -o -iname "*Postgres*"` → zero hits.

**Impact:**

A contractor reading ADR-013 may attempt to "enable" the PostgreSQL driver via config (e.g., setting `core.database.drivers.pgsql.enabled: true`). There is nothing to enable — no driver class file exists.

**Corrective action:**

One of:

1. **Implement the driver.** Create `packages/core/dbal/src/Driver/PgsqlDriver.php` implementing `DriverInterface`, with `enabled: false` in the default config. ADR-013 then becomes accurate.
2. **Correct ADR-013.** Replace "the PostgreSQL driver is shipped but disabled" with "the PostgreSQL driver is referenced as a future capability; no implementation currently exists. Re-promotion to a PostgreSQL-primary datastore would require implementing the driver and issuing a new ADR."
3. **Re-open the ADR decision.** If PostgreSQL support is no longer planned, ADR-013 should be amended to drop the "shipped but disabled" language entirely.

**SPEC-001 already encodes option (2).** SPEC-001 §19 reads: *"ADR-013 references a PostgreSQL driver as shipped-but-disabled, but the current DBAL driver directory does not contain such a driver. This discrepancy MUST be documented as repository/ADR drift."* This register is that documentation.

---

## Discrepancy #2 — README claims "19 ADRs"; actual canonical count is 20

**Severity:** Low (count drift, easily fixed)

**Claimed in:** `README.md` line 12 and line 64:

> Line 12: "- **19 Architecture Decision Records** — every major decision documented with alternatives and trade-offs"
> Line 64: "│   ├── ADRs/                  # 19 Architecture Decision Records"

**Actual repository state:**

```text
$ ls Architecture/ADRs/ADR-*.md | wc -l
20
```

Files present (verified by listing): ADR-001 through ADR-020 (sequential, no gaps). 20 canonical ADR files.

**Corrective action:**

Update README.md lines 12 and 64 from "19" to "20". This is a one-line edit × 2 occurrences.

---

## Discrepancy #3 — README claims "105 component blueprints"; actual canonical count is 102

**Severity:** Low (count drift)

**Claimed in:** `README.md` line 14 (and line 66 area):

> "- **105 component blueprints** — full implementation specs for Core, Hub, Bridge, Spoke, and Deploy tiers"

**Actual repository state (canonical, in `Architecture/`):**

| Tier | Path | Count |
|---|---|---|
| Core | `Architecture/Core/` | 20 |
| Hub | `Architecture/Hub/` | 31 |
| Spoke/Internal | `Architecture/Spoke/Internal/` | 27 |
| Spoke/External | `Architecture/Spoke/External/` | 18 |
| Spoke/Bridge | `Architecture/Spoke/Bridge/` | 1 |
| Deploy | `Architecture/Deploy/` | 5 |
| **TOTAL canonical** | | **102** |

The archive predecessor at `archive/Arc/Blueprints/` contains 82 (20+30+15+15+1+1) — the archive is the read-only historical superset and does not reflect the canonical current state.

**Corrective action:**

Update README.md line 14 from "105" to "102". (Note: INDEX.md is correct — INDEX.md already states the canonical counts of 20 Core + 31 Hub + 27 ISPOKE + 18 ESPOKE + 1 Bridge + 5 Deploy = 102.)

---

## Discrepancy #4 — README MUWV status is self-contradictory

**Severity:** High (release-versioning state drift; this is the most visible drift problem)

README simultaneously claims three mutually-incompatible MUWV states:

### Sub-finding 4a: "Post-MUWV" vs "Pre-MUWV" in the same file

- **Line 109:** `"1" = post-MUWV (walking skeleton complete — flipped 2026-09-18)`
- **Line 115:** `"**Current version:** v1.2.0.0+<sha> — **post-MUWV**, Milestone 1 (segment 2), lap 0, patch 0. The MUWV flip was authorized on 2026-09-18 after all 8 Milestone 0 blueprints shipped and the full Pulse trace was verified end-to-end.`
- **Line 200:** `"**Active development. Pre-MUWV.** Milestone 0 (the walking skeleton) is in progress — 5 of 8 blueprints shipped. The MUWV flip requires all 8 + a real HTTP request through the full Rim."`

Lines 109 + 115 say post-MUWV + flipped + all 8 blueprints shipped + verified end-to-end.
Line 200 says pre-MUWV + in progress + 5 of 8 blueprints shipped + flip NOT authorized.

These cannot both be true. Exactly one is the current state.

### Sub-finding 4b: "5 of 8" vs "8 of 8" Milestone 0 blueprints

- **Line 94:** `"| 3 | CORE-18 (Kernel) | ✅ Complete — **5 of 8 Milestone 0 blueprints shipped** |"`
- **Line 95:** `"| 4 | HUB-01, BRIDGE-01, ISPOKE-09, ESPOKE-01 | ✅ Complete — **all 8 Milestone 0 blueprints shipped** |"`

Line 94 says 5 of 8 shipped (3 of 8 remaining). Line 95 says all 8 shipped (0 remaining). The lines are adjacent in the same table.

### Sub-finding 4c: "503 placeholder" vs. real implementation

- **Line 218:** `"...The integration test KernelHelloWorldIntegrationTest::testHelloWorldRoundTrip proves the architectural round-trip works, but public/index.php is still a 503 placeholder — the flip is NOT yet authorized."`

But `public/index.php` is verified at 274 lines, contains a working Kernel boot + Vanguard wiring + 2 routes + 2 controller bindings + frankenphp_handle_request loop. It is NOT a 503 placeholder. (Also, the integration test the README references is named `HelloWorldTest::testHelloWorldRoundTrip`, not `KernelHelloWorldIntegrationTest::testHelloWorldRoundTrip` — class name drift.)

### Sub-finding 4d: Milestone 0 component table contradicts line 95

README lines 211–214:
```
| HUB-01 (Hub Config & Flags) | ⬜ Not started | — |
| BRIDGE-01 (Vanguard) | ⬜ Not started | — |
| ISPOKE-09 (Codex) | ⬜ Not started | — |
| ESPOKE-01 (Canvas) | ⬜ Not started | — |
```

But line 95 says these same 4 components are "✅ Complete — all 8 Milestone 0 blueprints shipped". Both cannot be true.

**Verified actual state:**

- `packages/hub/config/` exists (HUB-01 implemented) ✅
- `packages/bridge/vanguard/` exists (BRIDGE-01 implemented) ✅
- `packages/spoke/internal/codex/` exists (ISPOKE-09 implemented) ✅
- `packages/spoke/external/canvas/` exists (ESPOKE-01 implemented) ✅

All 4 packages have `composer.json` + `src/` + `tests/` directories (verified by baseline generator).

**Corrective action:**

Pick one canonical MUWV state and remove the contradictions. Given the 4 packages exist with source + tests, the "post-MUWV + all 8 shipped" claim appears to be the accurate one. The "pre-MUWV + 5 of 8 + 503 placeholder + Not started" claims are stale historical text.

Specifically:
1. Update lines 200–218 to either remove the "Pre-MUWV" section entirely OR mark it clearly as "Historical — superseded by the 2026-09-18 MUWV flip".
2. Update the Milestone 0 component table (lines 211–214) to mark all 4 as "✅ Shipped" instead of "⬜ Not started".
3. Remove the "public/index.php is still a 503 placeholder" text — it is demonstrably false (274 lines of working code).
4. Either remove line 94's "5 of 8" or remove line 95's "all 8" — pick the accurate one.

---

## Discrepancy #5 — Hub/config docblocks say "When CORE-19 (DBAL) lands"; CORE-19 is partially implemented

**Severity:** Low (docblock staleness — not user-facing, but creates contractor confusion)

**Claimed in:** 6 docblock occurrences in `packages/hub/config/src/`:

| File | Line | Text |
|---|---|---|
| `ConfigOverrideRepositoryInterface.php` | (header) | "When CORE-19 (DBAL) lands, replaced with a DBAL-backed implementation that" |
| `FeatureFlagRepositoryInterface.php` | (header) | "When CORE-19 (DBAL) lands, replaced with a DBAL-backed implementation that" |
| `InMemoryConfigOverrideRepository.php` | (header) | "In-memory ConfigOverrideRepository for depth-2 (pre-DBAL) usage." |
| `InMemoryFeatureFlagRepository.php` | (header) | "In-memory FeatureFlagRepository for depth-2 (pre-DBAL) usage." |
| `InMemoryFeatureFlagRepository.php` | (header) | "When CORE-19 lands, replace with a DBAL-backed implementation — the" |
| `FeatureFlagManager.php` | (header) | "at depth 2; DBAL-backed when CORE-19 lands" |
| `FeatureManagerInterface.php` | (header) | "stub at depth 2; DBAL-backed when CORE-19 lands" |

**Actual repository state:**

CORE-19 (DBAL) is **partially implemented** at `packages/core/dbal/src/`:
- `Connection.php` + `ConnectionInterface.php`
- `DatabaseException.php`
- `Driver/` (MysqlDriver, SqliteDriver)
- `DriverInterface.php`
- `QueryBuilder.php` + `QueryBuilderInterface.php`
- `TenantContext.php`
- `Transaction.php`
- `TypeMapper.php`

11 source files. The DBAL is no longer "not yet landed" — it has basic infrastructure.

**Corrective action:**

Update the 7 docblock occurrences to reflect that CORE-19 has landed at depth 1 (basic infrastructure: Connection, QueryBuilder, Transaction, TenantContext). The "replace InMemory with DBAL-backed" recommendation remains valid as a future Hub depth-3+ task, but the framing should be "When CORE-19 reaches depth 3+ and is consumed by Hub" rather than "When CORE-19 lands" (it has already landed).

---

## Discrepancy #6 — Hub/config `composer.json` has zero DBAL dependency; SPEC-001 §1 PlantUML implies Hub→DBAL is current

**Severity:** Informational (forward-looking vs current-state framing)

**Claimed in:** SPEC-001 §1 PlantUML diagram shows arrow `HUBAPP → DBAL`.

**Actual repository state:**

```text
$ cat packages/hub/config/composer.json | grep require
"require": {
    "php": "^8.4",
    "ext-json": "*",
    "ext-hash": "*",
    "psr/container": "^2.0",
    "sovereign-stack/core-container": "^0.1",
    "sovereign-stack/core-config": "^0.1"
}
```

Zero `sovereign-stack/core-dbal` dependency. Hub/config does not depend on DBAL today.

**Note:** SPEC-001 §1 already includes a target-state caption noting this:

> "This represents **target-state logical ownership**. Some dependencies shown (notably `HUBAPP → DBAL`) are not yet exercised by current Hub code — the existing Hub/config package uses in-memory repository implementations and has zero DBAL dependency in its `composer.json`. The `HUBAPP → DBAL` arrow will become accurate once Hub business capabilities at depth 3+ require persistence."

**No corrective action needed** — the SPEC already documents this. Listed here for completeness as part of the M0 discrepancy audit.

---

## Discrepancy #7 — Architecture-lint CI workflow disclaims coverage it doesn't provide

**Severity:** Medium (CI gates may be assumed stronger than they are)

**Claimed in:** `.github/workflows/architecture-lint.yml` self-comment:

> "Note: as of this workflow's introduction, run.php performs exactly 3 checks (reference existence, misattribution phrases, structural completeness) — see Architecture/CrossCutting/REPO-STATE-AUDIT.md §5. Do not assume broader coverage (Pulse consistency, naming drift, Soft-Freeze, blueprint-fidelity) is enforced here yet; those are cooldown-expansion targets per SDLC-AGRD.md §6, not current scope."

**Actual repository state:**

The disclaimer is accurate — the lint does perform only those 3 checks. However, contractors may assume "architecture-lint passing" means "ring boundaries enforced." It does not. Ring-boundary import checking (SPEC-001 §40, M01) is a future addition.

**Corrective action:**

This is a documentation-honesty positive — the disclaimer is correct. The corrective action is M1 (Enforceable Architecture) per SPEC-001 §40, which adds the AST/import-based ring-boundary checker as a separate workflow (`architecture-boundary-lint.yml`). Once that exists, contractors should treat `architecture-boundary-lint.yml` as the ring-boundary gate and `architecture-lint.yml` as the documentation/reference-structure gate.

---

## Summary Table

| # | Discrepancy | Severity | Documentation corrective | Code/CI corrective |
|---|---|---|---|---|
| 1 | ADR-013 PostgreSQL driver "shipped but disabled" — no driver file | High | Update ADR-013 OR implement the driver | Implement `PgsqlDriver.php` OR explicitly drop the claim |
| 2 | README "19 ADRs" — actual 20 | Low | Update README lines 12 + 64 from 19 → 20 | — |
| 3 | README "105 blueprints" — actual 102 | Low | Update README line 14 from 105 → 102 | — |
| 4 | README MUWV status self-contradictory | High | Update README lines 94, 95, 200, 211–214, 218 to pick one canonical state | — |
| 5 | Hub/config docblocks say "When CORE-19 lands" — CORE-19 has landed | Low | Update 7 docblocks across Hub/config | — |
| 6 | SPEC §1 PlantUML implies Hub→DBAL current | Info | (Already documented in SPEC §1 caption) | — |
| 7 | Architecture-lint CI disclaims coverage gaps | Medium | (Already documented in workflow self-comment) | M1: add `architecture-boundary-lint.yml` per SPEC §40 |

---

## M0 Exit Criteria Status (per SPEC-001 Milestone M0)

- ✅ **reproducible test baseline** — produced by `scripts/generate-architecture-baseline.{py,php}` at `download/ARCHITECTURE_BASELINE.md`
- ✅ **current architecture manifest** — embedded in baseline (package counts + blueprint counts + ADR list + frozen contracts + worker recycling values)
- ✅ **current deployment baseline** — captured in baseline (worker recycling config + Caddyfile.blue + systemd unit values)
- ✅ **documented ADR/repository discrepancies** — this register (7 discrepancies documented above)

M0 exit criteria are met pending contractor merge of the documentation corrections for discrepancies #1–#5. Discrepancies #6 and #7 are already documented in their respective source files and require no separate corrective action.

---

## Reproducibility

This register is reproducible from repository state. To regenerate:

```bash
python3 /home/z/my-project/scripts/generate-architecture-baseline.py
# Baseline output: /home/z/my-project/download/ARCHITECTURE_BASELINE.md
```

The discrepancies above were verified manually against:
- `Architecture/ADRs/ADR-013-mysql-primary-datastore.md` (ADR text)
- `packages/core/dbal/src/Driver/` directory listing (driver file existence)
- `README.md` line numbers 12, 14, 64, 94, 95, 109, 115, 117, 200, 211–214, 218 (drift points)
- `packages/hub/config/composer.json` `require` block (DBAL dependency)
- `packages/hub/config/src/*.php` docblocks (7 stale "When CORE-19 lands" occurrences)
- `Architecture/Core/`, `Architecture/Hub/`, `Architecture/Spoke/Internal/`, `Architecture/Spoke/External/`, `Architecture/Spoke/Bridge/`, `Architecture/Deploy/` directory listings (canonical blueprint counts)
- `.github/workflows/architecture-lint.yml` self-comment (CI disclaimer)

---

*End of ADR / Repository Discrepancy Register — M0 deliverable per SPEC-001 §39.*
