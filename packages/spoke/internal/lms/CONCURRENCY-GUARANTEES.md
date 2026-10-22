# Progress Concurrency: Test Guarantees

Scope: `ProgressApplicationService::recordProgress()` and `MySQLProgressRepository`.

## Concurrency strategy (implemented)

1. `recordProgress()` validates inputs, then opens a transaction.
2. Inside the transaction it reads the existing row with `SELECT ... FOR UPDATE`
   (`ProgressRepositoryInterface::getForUpdate()`), locking the row.
3. The forward-only state machine (`Progress::transition()`) runs against the
   locked (fresh) status, never a stale read.
4. Persistence uses a plain `INSERT` for new rows; on a duplicate-key race
   (MySQL 1062 / SQLSTATE 23000) the row is re-read under lock and the
   transition is re-applied via `save()` (atomic `INSERT ... ON DUPLICATE KEY
   UPDATE`).
5. Any non-duplicate DB error or validation failure rolls back the transaction.

## Guarantees established by UNIT tests (mocks)

| Guarantee | Test(s) | Notes |
|---|---|---|
| `getForUpdate()` (not `get()`) is used inside the transaction | `ProgressApplicationServiceTest` recordProgress tests | Mock asserts orchestration only |
| `beginTransaction`/`commit`/`rollBack` called correctly | all recordProgress tests | Mock asserts call counts |
| NotStarted is a no-op only when no row exists | `testRecordProgressNotStartedIsNoOpWhenNoExistingRecord` | |
| Existing InProgress/Completed reject NotStarted | `testRecordProgressNotStartedWithExisting*Throws` | |
| Forbidden transitions throw under locked read | `testRecordProgressThrowsOnRevertingCompletedModule` | |
| Duplicate-key race path re-reads under lock, transitions, saves | `testRecordProgressHandlesDuplicateKeyRaceByReReadingUnderLock` | Mock simulates PDOException 1062 |
| Non-duplicate PDOException propagates + rollback | `testRecordProgressRethrowsNonDuplicatePDOExceptionAndRollsBack` | |
| Duplicate race with missing reloaded row rethrows | `testRecordProgressDuplicateRaceRethrowsWhenReloadedMissing` | |
| `getForUpdate()` SQL contains `FOR UPDATE` | `MySQLProgressRepositoryTest::testGetForUpdateSqlContainsForUpdate` | |
| `insert()` is plain INSERT (no upsert) | `MySQLProgressRepositoryTest::testInsert*` | |
| `save()` uses `ON DUPLICATE KEY UPDATE` | `MySQLProgressRepositoryTest::testSaveSqlContainsOnDuplicateKeyUpdate` | |

## Guarantees that REQUIRE real MySQL integration tests

These cannot be proven with mocks and are NOT covered by the unit suite:

- `SELECT ... FOR UPDATE` actually acquires a row-level lock and serializes
  concurrent transactions (lock contention, deadlock behavior).
- Concurrent `INSERT` of the same composite PK raises MySQL error 1062
  (SQLSTATE 23000) as assumed by the race handler.
- `ON DUPLICATE KEY UPDATE` performs an atomic upsert under the composite PK.
- Transaction isolation level (default REPEATABLE READ) and its interaction
  with `FOR UPDATE`.
- Foreign-key constraints (`fk_lms_progress_enrollment`, `fk_lms_progress_module`)
  and cascade deletes.
- `TIMESTAMP(6)` precision round-tripping and NULL `started_at` rejection at
  the DB layer (schema allows NULL; service never creates such rows).
- End-to-end concurrent request behavior (HTTP layer not yet implemented).

Until a real-MySQL integration suite exists, treat the concurrency guarantees
above as design intent verified by orchestration-level unit tests only.
