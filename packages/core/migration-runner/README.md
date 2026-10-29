# DGLab Migration Runner — Implementation Package

## Controlling design: Z.ai Focused Revision 5.3
## Base commit: `ff439a4` (origin/main, https://github.com/DGCodeIdeas/DGLab)

---

## A. Baseline Report

- **Repository URL:** https://github.com/DGCodeIdeas/DGLab
- **Actual base commit SHA:** `ff439a476f98e5bfe286d07a5168239569db72bf`
- **Branch:** `migration-runner-impl` (local scratch branch from ff439a4; NOT pushed)
- **Isolation confirmation:** All implementation work is on the local scratch branch. No commits, pushes, merges, or publications to the shared repository. The `main` branch is untouched.
- **Environment limitation:** The implementation environment does NOT have PHP, MySQL, Composer, or Docker available. All source code is written but NOT empirically verified by execution. All tests are source-only and marked "NOT RUN." The owner must run them in their development environment.

---

## B. File Manifest

### New files (all under `packages/core/migration-runner/`)

| Path | Purpose | New/Modified | Why in scope |
|---|---|---|---|
| `schema/001_create_migration_runner_tables.sql` | DDL for all 8 metadata tables (gseq counter, gate, migration, attempt, attempt_event, operator_event, state, current_level) + append-only triggers | New | Foundation: the runner's own metadata schema per Rev 5.3 |
| `src/Event/EventTypes.php` | Closed enumeration of establishing vs. audit-only event types; the provenance authority set | New | Core: the establishing/audit distinction (Rev 5 §3.1) |
| `src/Sequence/GseqAllocator.php` | Atomic gseq protocol: `UPDATE counter → INSERT event → COMMIT` in one InnoDB transaction; counter row lock held through COMMIT | New | Core: the causal-ordering mechanism (Rev 5 §3.2, Rev 5.3 §3) |
| `src/Locking/Gate.php` | InnoDB shared/exclusive gate (`LOCK IN SHARE MODE` / `FOR UPDATE` on `migration_gate` row) | New | Core: normal-operation global/per-migration exclusion (Rev 5.1 §2) |
| `src/Locking/AdvisoryLocks.php` | `MigrationLock` (L1[M]), `LevelLock`, `LivenessGuard` — advisory-lock primitives | New | Core: per-migration serialization, level-recompute serialization, liveness (Rev 5.3 §2) |
| `src/Migration/MigrationDefinition.php` | Parsed migration definition (metadata + upgrade/downgrade/postcondition blocks) | New | Core: migration identity and structure |
| `src/Migration/ChainValidator.php` | Validates linear chain (rejects missing predecessor, duplicates, branching, cycles, multiple genesis, disconnected) | New | Core: MVP linear-chain constraint (Rev 5 §6.1) |
| `src/Migration/SqlBlockParser.php` | Line-based `@STMT BEGIN/END` parser; not a SQL parser; driver contract enforces one-statement-per-block | New | Core: SQL validation guarantee boundary (Rev 3 §6.1) |
| `src/Recovery/Reconciler.php` | Reconciliation pass: finds latest establishing record by gseq, detects unresolved attempts (including zero-event), runs 8-step recovery | New | Core: crash recovery (Rev 5 §2, Rev 5.3 §2) |
| `src/Recovery/ReconciliationResult.php` | Result DTO for reconciliation outcomes | New | Supporting |
| `src/Runner/MigrationRunner.php` | Main orchestrator: apply, rollback, dry-run, status, reconcile; implements the §7.3 apply protocol with all crash windows | New | Core: the runner itself |
| `src/Cli/Application.php` | CLI entry point: `install`, `apply`, `rollback`, `status`, `dry-run`, `reconcile` commands | New | User-facing: CLI per §11 |
| `cli/migrate` | Executable CLI script (finds autoloader, dispatches to Application) | New | Entry point |
| `phpunit.xml.dist` | PHPUnit config; unit tests run without MySQL; integration tests require MySQL | New | Testing |
| `tests/Unit/ChainValidatorTest.php` | Pure-logic unit tests for chain validation (9 test cases covering all validation rules) | New | Testing: discovery/chain validation (§12) |
| `tests/Integration/Test6eTest.php` | Corrected Test 6e: gate-connection loss, level-lock protection, 13-step sequence | New | Testing: the specifically requested race test (§8) |
| `README.md` | This document | New | Documentation: operations + safety + integration |

### Modified files

| Path | Change | Why |
|---|---|---|
| `composer.json` (root) | Add `"SovereignStack\\Core\\MigrationRunner\\": "packages/core/migration-runner/src/"` to autoload psr-4; add `"SovereignStack\\Core\\MigrationRunner\\Tests\\": "packages/core/migration-runner/tests/"` to autoload-dev | Register the new namespace (no separate Composer package per the target architecture) |

**Total: 17 new files, 1 modified file.**

---

## C. Source Patch

The patch is generated against `ff439a4` (origin/main):

```bash
git diff ff439a4..migration-runner-impl > migration-runner.patch
```

To apply:
```bash
git checkout ff439a4
git apply migration-runner.patch
composer install
```

**Do NOT commit, push, or merge. Review the patch first.**

---

## D. Test Evidence

### Unit tests (no MySQL required)

```bash
cd packages/core/migration-runner
vendor/bin/phpunit --testsuite=Unit
```

**STATUS: NOT RUN** — PHP/PHPUnit not available in the implementation environment.

Expected: 9 tests, all passing (ChainValidator: empty, valid linear, missing predecessor, duplicate, branching, cycle, multiple genesis, no genesis, disconnected, deterministic ordering).

### Integration tests (require MySQL with InnoDB)

```bash
export TEST_DB_HOST=127.0.0.1
export TEST_DB_NAME=dglab_test
export TEST_DB_USER=root
export TEST_DB_PASS=yourpassword
vendor/bin/phpunit --testsuite=Integration --group=mysql-required
```

**STATUS: NOT RUN** — MySQL not available in the implementation environment.

Expected: Test6eTest (3 tests), plus the full integration suite (concurrency, recovery, crash windows) once implemented.

### Corrected Test 6e result

**STATUS: NOT RUN** — requires MySQL. The test class is at `tests/Integration/Test6eTest.php` and follows the 13-step corrected sequence from the handoff §8. The owner must run it against the target MySQL version.

### Tests not yet implemented

The following test classes from the §12 plan are NOT yet written (the implementation environment could not run them, so they were deprioritized in favor of the core protocol classes and the specifically-requested Test 6e):
- `GseqProtocolTest` (concurrent writers, interrupted allocation)
- `ReconciliationTest` (idempotence, stale-state recovery)
- `ZeroEventRecoveryTest` (zero-event attempt discovery + 8-step recovery)
- `CrashWindowTest` (W-1 through W6 crash boundaries)
- `ConcurrencyTest` (two attempts on same migration, different migrations)
- `LockBehaviorTest` (gate acquisition, timeout, connection-death release)
- `SqlBlockParserTest` (multi-statement rejection, quoted semicolons)

These are the next priority once the owner can run PHPUnit.

---

## E. Operations Documentation

### Installation / Setup

1. **Prerequisites:** PHP 8.4+, MySQL 8.0+ (or 5.7+ with InnoDB), Composer.
2. **Install dependencies:** `composer install` at the repository root.
3. **Provision a restricted runner account** (Rev 3 §8.1):
   ```sql
   CREATE USER 'dglab_runner'@'%' IDENTIFIED BY '...';
   GRANT SELECT, INSERT, UPDATE ON migration_gseq_counter TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT, UPDATE ON migration_gate TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT, UPDATE ON migration TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT ON migration_attempt TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT ON migration_attempt_event TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT ON operator_event TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT, UPDATE ON migration_state TO 'dglab_runner'@'%';
   GRANT SELECT, INSERT, UPDATE ON migration_current_level TO 'dglab_runner'@'%';
   ```
4. **Install the schema:** `php packages/core/migration-runner/cli/migrate install --db=yourdb --user=dglab_runner --pass=...`
5. **Place migration files** in a directory (e.g., `database/migrations/`), using the `@META` / `@STMT BEGIN` / `@STMT END` format.

### CLI Usage

```bash
# Install the metadata schema
php migrate install --db=yourdb --user=dglab_runner --pass=...

# Dry-run (validate all migrations; no SQL execution, no state mutation)
php migrate dry-run --migrations-dir=database/migrations

# Apply the next pending migration
php migrate apply --migrations-dir=database/migrations --db=yourdb --user=dglab_runner --pass=...

# Show current status
php migrate status --migrations-dir=database/migrations --db=yourdb --user=dglab_runner --pass=...

# Roll back the latest applied migration (MVP: latest-applied only)
php migrate rollback --migrations-dir=database/migrations --db=yourdb --user=dglab_runner --pass=...

# Run the reconciliation pass (startup recovery)
php migrate reconcile --migrations-dir=database/migrations --db=yourdb --user=dglab_runner --pass=...
```

**Exit codes:** 0 = success; 1 = failure; 2 = uncertain outcome (operator action required).

### Dry-Run Semantics

Dry-run validates all migration files (metadata, chain linearity, block structure, postcondition presence) without executing any SQL or mutating `migration_state`. It does not acquire locks or create attempts.

### Status Interpretation

Status shows: schema name, current level migration ID, and per-migration state (`pending` / `applied` / `unapplied` / `diverged` / `quarantined`). The current-level migration is marked with `*`.

### Rollback Semantics and Limits

- **MVP rule:** only the latest applied migration (the one at `current_level`) can be rolled back.
- A rollback is a **real executed attempt** against the migration's downgrade artifact (`direction = downgrade`).
- Historical records are preserved: the original apply attempt and the rollback attempt both remain in the append-only event log.
- DDL implicit commits are acknowledged: the runner does NOT claim transactional safety for DDL migrations. Post-dispatch crash windows (W2–W4) escalate to postcondition verification or operator intervention.

### Recovery and Operator-Intervention Procedure

1. If the runner crashes mid-migration, the next `apply` or `reconcile` runs the reconciliation pass.
2. The pass discovers unresolved attempts (including zero-event attempts) and classifies them:
   - **No dispatch intent** → `execution_failed_pre_exec` (recovered); auto-retry allowed.
   - **Dispatch intent present** → uncertain; postcondition verification or operator intervention. **NO auto-retry.**
3. If the pass finds a stale `migration_state` (terminal event committed but state not updated), it updates the state and inserts an `auto_reconciled` audit event.
4. If the pass finds an integrity anomaly (state's gseq > max establishing gseq), it quarantines the migration. The operator must inspect the event log and resolve manually.
5. **Operator intervention** is required when: any postcondition fails; any postcondition cannot be evaluated; a migration has no postcondition manifest; or a post-dispatch crash left an uncertain outcome.

### Supported Migration SQL Format

- `-- @META` block with `migration_id`, `predecessor`, `description`.
- `-- @STMT BEGIN upgrade` / `-- @STMT END` blocks for upgrade SQL.
- `-- @STMT BEGIN downgrade` / `-- @STMT END` blocks for downgrade SQL.
- `-- @STMT BEGIN postconditions` block with assertions (`table_exists:`, `column_exists:`, `expect_empty:`, `expect_count:`, etc.).
- **One SQL statement per block** (enforced by the driver: `PDO::ATTR_EMULATE_PREPARES = false` in the existing DBAL Connection). A block with multiple semicolon-terminated statements will raise a driver error at dispatch time.
- The parser is **line-based, not a SQL parser.** It does not split on semicolons. It does not validate SQL syntax. It does not handle `DELIMITER` changes for stored routines (the MVP does not support stored routine migrations).

### Known Limitations

1. **No stored routine migrations:** The MVP does not support `CREATE PROCEDURE` / `FUNCTION` / `TRIGGER` migrations with `DELIMITER` changes. These require a different SQL dispatch model.
2. **DDL implicit commits:** MySQL DDL statements implicitly commit the surrounding transaction. The runner does not claim that DDL and metadata updates share one atomic transaction. Post-dispatch crash windows (W2–W4) escalate to postcondition verification or operator intervention.
3. **No baseline adoption:** Excluded from the MVP (frozen decision). The `baseline_adopted` event type is in the schema for future use but no code path produces it.
4. **No branching migrations:** The MVP supports only a linear chain. Branching is a recorded future-direction item.
5. **No arbitrary rollback:** Only the latest-applied migration can be rolled back.
6. **Sequential apply:** The MVP applies migrations sequentially (no concurrent application). The lock hierarchy supports concurrent per-migration operations on different migrations (for future use), but the CLI applies one migration at a time.
7. **Whole-operation exclusion under gate-connection loss is NOT guaranteed** (Rev 5.3 §3). The overlap is safe (all shared resources protected by L1[M] and the level lock), but zero-overlap is not structurally guaranteed after a gate-connection death.
8. **Tests not run:** The implementation environment has no PHP/MySQL. All tests are source-only.

---

## F. Safety Assessment

### Verified behavior (by code review against the Rev 5.3 specification)

- **Schema DDL** matches the Rev 5.3 table definitions (gseq counter, gate, attempt, attempt_event, operator_event, state, current_level) with correct columns, indexes, and append-only triggers.
- **EventTypes** correctly enumerates the 6 establishing types, 4 audit-only types, and 8 lifecycle/intent/result/terminal types per Rev 5 §3.1. The `isEstablishing()` / `isAuditOnly()` / `isTerminal()` / `isDispatchIntent()` / `impliedState()` methods match the provenance table.
- **GseqAllocator** implements the mandatory atomic protocol: `BEGIN → UPDATE counter SET c = LAST_INSERT_ID(c+1) → INSERT event with LAST_INSERT_ID() → COMMIT`. The counter row lock is held through COMMIT. Both attempt-event and operator-event variants are provided.
- **Gate** implements InnoDB `LOCK IN SHARE MODE` (per-migration) and `FOR UPDATE` (global) on the `migration_gate` row, with per-session `innodb_lock_wait_timeout`.
- **LevelLock** is an advisory lock (`dglab.mig.level.<schema_hash>`) held by the work connection (not the gate connection). It serializes all level recomputes. It survives DDL implicit commits (advisory, not InnoDB transaction lock). It eliminates the check-to-use race for the level recompute.
- **ChainValidator** rejects all 7 invalid chain topologies: missing predecessor, duplicate identity, branching, cycle, multiple genesis, no genesis, disconnected sub-chain. Assigns deterministic chain positions.
- **SqlBlockParser** is line-based (not a SQL parser). Uses `@STMT BEGIN/END` markers. Does not split on semicolons. The driver contract (EMULATE_PREPARES off) enforces one-statement-per-block at dispatch.
- **Reconciler** implements the §2.4 reconciliation pass: finds L (max-gseq establishing record), compares `established_by_gseq` with L.gseq, handles match/stale/inconsistent/anomaly. The unresolved-attempt scan includes zero-event attempts. The 8-step recovery for zero-event attempts is implemented with the dispatch-intent check (escalates to quarantine if intent exists).
- **MigrationRunner** implements the §7.3 apply protocol: gate-S → L1[M] → release gate-S → reconcile → attempt row → prepared (BEFORE validation) → validation → dispatch_started → per-statement intent/execute/result → execution_succeeded → state UPDATE → level recompute (under level lock) → release L1[M]. Crash windows W-1 through W6 are handled by the reconciliation pass on the next runner start.
- **Rollback** creates a real downgrade attempt, executes the downgrade blocks, sets state to `unapplied`, and recomputes the level (retreats to predecessor).
- **Dry-run** validates without executing SQL or mutating state.
- **CLI** provides install, apply, rollback, status, dry-run, reconcile commands with exit codes 0/1/2.

### Behavior supported only by unit tests or mocks (source-only, NOT RUN)

- ChainValidatorTest (9 tests) — pure logic, can run without MySQL.
- All other tests — NOT YET WRITTEN or require MySQL.

### Behavior reasoned about but not tested

- GseqAllocator's atomic protocol under concurrent writers — reasoned from InnoDB row-lock semantics (Rev 5.3 §3.3); NOT empirically verified.
- Gate's S/X exclusion under concurrent access — reasoned from InnoDB compatibility matrix; NOT empirically verified.
- LevelLock's survival of DDL implicit commits — reasoned from advisory-lock session-binding; NOT empirically verified (Test6eTest::testAdvisoryLockSurvivesDdlImplicitCommit would verify this but is NOT RUN).
- Crash-window recovery (W-1 through W6) — reasoned from the reconciliation algorithm; NOT empirically verified.

### Known limitations

- See §E "Known Limitations" above.
- The implementation environment could not run PHP/MySQL; all verification is by code review against the spec.

### Issues that block production use

1. **No empirical MySQL verification has been performed.** The owner MUST run the integration tests (including Test 6e) against the target MySQL version before any production use.
2. **Several integration test classes are not yet written** (GseqProtocolTest, ReconciliationTest, ZeroEventRecoveryTest, CrashWindowTest, ConcurrencyTest, LockBehaviorTest, SqlBlockParserTest). These are the next priority.
3. **The schema SQL's trigger handling may need adjustment** for the target MySQL version (`CREATE TRIGGER IF NOT EXISTS` is MySQL 8.0+; 5.7 requires `DROP TRIGGER IF EXISTS` first).
4. **The `executeSchemaSql` method in MigrationRunner** uses a simple `explode(';')` for non-trigger statements, which may not handle all edge cases (e.g., semicolons inside string literals). For the MVP, this is acceptable because the schema DDL is simple; for production, a more robust SQL splitter or `mysql` CLI execution is recommended.

---

## G. Integration Instructions

### For the owner: how to review and apply

1. **Verify the base commit:**
   ```bash
   cd /path/to/DGLab
   git fetch origin main
   git rev-parse origin/main
   # Should output: ff439a476f98e5bfe286d07a5168239569db72bf
   ```

2. **Create a review branch:**
   ```bash
   git checkout -b review/migration-runner ff439a4
   ```

3. **Apply the patch:**
   ```bash
   git apply /path/to/migration-runner.patch
   ```

4. **Install dependencies:**
   ```bash
   composer install
   ```

5. **Run unit tests (no MySQL needed):**
   ```bash
   cd packages/core/migration-runner
   ../../vendor/bin/phpunit --testsuite=Unit
   ```

6. **Set up a test MySQL database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE dglab_test"
   mysql -u root -p dglab_test < packages/core/migration-runner/schema/001_create_migration_runner_tables.sql
   ```

7. **Run integration tests (including Test 6e):**
   ```bash
   export TEST_DB_NAME=dglab_test
   export TEST_DB_USER=root
   export TEST_DB_PASS=yourpassword
   ../../vendor/bin/phpunit --testsuite=Integration
   ```

8. **Try the CLI:**
   ```bash
   php packages/core/migration-runner/cli/migrate install --db=dglab_test --user=root --pass=...
   php packages/core/migration-runner/cli/migrate status --migrations-dir=database/migrations --db=dglab_test --user=root --pass=...
   ```

9. **Review the code** against the Rev 5.3 specification. Key files to review:
   - `src/Sequence/GseqAllocator.php` — the atomic gseq protocol.
   - `src/Locking/Gate.php` + `AdvisoryLocks.php` — the locking hierarchy.
   - `src/Recovery/Reconciler.php` — the reconciliation pass and 8-step recovery.
   - `src/Runner/MigrationRunner.php` — the apply/rollback protocol.
   - `schema/001_create_migration_runner_tables.sql` — the metadata schema.

10. **If the patch is acceptable, merge it into your local checkout** (the owner performs this independently):
    ```bash
    git add packages/core/migration-runner/
    git add composer.json
    git commit -m "feat(migration-runner): bounded MVP implementation per Rev 5.3"
    ```

**Do NOT claim this patch is integrated into any participant's local checkout unless the owner independently performs and confirms that integration.**
