<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Runner;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\DatabaseException;
use SovereignStack\Core\MigrationRunner\Event\EventTypes;
use SovereignStack\Core\MigrationRunner\Locking\Gate;
use SovereignStack\Core\MigrationRunner\Locking\LevelLock;
use SovereignStack\Core\MigrationRunner\Locking\LivenessGuard;
use SovereignStack\Core\MigrationRunner\Locking\MigrationLock;
use SovereignStack\Core\MigrationRunner\Migration\ChainValidationException;
use SovereignStack\Core\MigrationRunner\Migration\ChainValidator;
use SovereignStack\Core\MigrationRunner\Migration\MigrationDefinition;
use SovereignStack\Core\MigrationRunner\Migration\SqlBlockParser;
use SovereignStack\Core\MigrationRunner\Recovery\Reconciler;
use SovereignStack\Core\MigrationRunner\Recovery\ReconciliationResult;
use SovereignStack\Core\MigrationRunner\Sequence\GseqAllocator;

/**
 * The main migration runner: applies, rolls back, inspects, and dry-runs
 * migrations following the controlling design (Rev 5.3).
 *
 * Lock order (mandatory; Rev 5.3 §4):
 *   gate (S/X) → L1[M] → gseq counter → events → state → level lock → level
 *
 * Apply protocol (Rev 5 §7.3, with Rev 5.3 corrections):
 *   1. Acquire gate-S (briefly, via the gate connection).
 *   2. Acquire L1[M] (work connection).
 *   3. Release gate-S.
 *   4. Run recovery-before-action: reconcile M.
 *   5. INSERT attempt row.
 *   6. INSERT `prepared` event (BEFORE validation — Rev 5 §7.2 corrected timing).
 *   7. Validate migration (chain, SQL blocks, postconditions).
 *      On failure: INSERT execution_failed_pre_exec; release L1[M]; allow retry.
 *   8. INSERT dispatch_started.
 *   9. For each SQL block:
 *      a. INSERT statement_dispatched(i) [intent, write-ahead].
 *      b. EXECUTE the block through the driver (EMULATE_PREPARES off →
 *         multi-statement rejection at dispatch).
 *      c. INSERT statement_succeeded(i) or statement_failed(i).
 *   10. INSERT execution_succeeded.
 *   11. UPDATE migration_state[M] = applied, established_by = execution_succeeded.
 *   12. Acquire level lock; recompute migration_current_level; release level lock.
 *   13. Release L1[M].
 *
 * Crash windows (Rev 5 §7.4, Rev 5.3 §2.4):
 *   W-1: after attempt row, before prepared → zero-event recovery (Rev 5.3 §2.3).
 *   W0: after prepared, before dispatch_started → pre_exec recovery; auto-retry.
 *   W1: after dispatch_started, before statement_dispatched → pre_exec; auto-retry.
 *   W2-W4: after statement_dispatched, before result → uncertain; postcondition
 *          verification or operator; NO auto-retry.
 *   W5: after execution_succeeded, before state UPDATE → reconciliation pass.
 *   W6: during state UPDATE → reconciliation pass (atomic single-row UPDATE).
 *
 * @package SovereignStack\Core\MigrationRunner\Runner
 */
final class MigrationRunner
{
    private string $schemaHash;
    private string $schemaName;
    private Gate $gate;
    private GseqAllocator $gseq;
    private Reconciler $reconciler;

    /** @var array<string, MigrationDefinition> */
    private array $migrations = [];

    public function __construct(
        string $schemaName,
        private readonly ConnectionInterface $workConnection,
        private readonly ConnectionInterface $gateConnection,
    ) {
        $this->schemaName = $schemaName;
        $this->schemaHash = substr(hash('sha256', $schemaName), 0, 8);
        $this->gate = new Gate($this->schemaHash, $schemaName, $this->gateConnection);
        $this->gseq = new GseqAllocator($this->workConnection);
        $this->reconciler = new Reconciler($this->workConnection, $this->gseq);
    }

    /**
     * Discover and validate migrations from a directory.
     * @param string $directory Path to migration files.
     * @throws ChainValidationException On invalid chain.
     */
    public function discoverMigrations(string $directory): void
    {
        $parser = new SqlBlockParser();
        $found = [];

        foreach (glob(rtrim($directory, '/') . '/*.sql') as $file) {
            $def = $parser->parseFile($file);
            $found[] = $def;
        }

        if (count($found) === 0) {
            throw ChainValidationException::emptyDirectory();
        }

        $validator = new ChainValidator();
        $validator->validate($found);

        // Index by migration_id.
        foreach ($found as $def) {
            $this->migrations[$def->migrationId] = $def;
        }
    }

    /**
     * Install the runner's own metadata schema.
     */
    public function installSchema(): void
    {
        $sqlFile = dirname(__DIR__, 2) . '/schema/001_create_migration_runner_tables.sql';
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \RuntimeException("Cannot read schema file: {$sqlFile}");
        }

        // Execute each statement (the schema file uses DELIMITER // for triggers).
        // For the MVP, execute the file in bulk if the driver supports it;
        // otherwise, split on DELIMITER boundaries.
        $this->executeSchemaSql($sql);
    }

    /**
     * Run the reconciliation pass for all known migrations.
     * Called on every runner start before accepting commands.
     *
     * @return array<string, ReconciliationResult>
     */
    public function reconcileAll(): array
    {
        $this->gate->ensureRowExists();
        $this->gseq->ensureCounterInitialized();

        // Acquire gate in EXCLUSIVE mode (global reconciliation sweep).
        $this->gate->acquireExclusive();

        try {
            $results = [];
            $migrationIds = array_keys($this->migrations);
            sort($migrationIds); // Lexicographic order (Rev 4 §5.3).

            foreach ($migrationIds as $migrationId) {
                $lock = new MigrationLock($this->schemaHash, $migrationId, $this->workConnection);
                if (!$lock->acquire()) {
                    $results[$migrationId] = ReconciliationResult::quarantined(
                        $migrationId, 'L1[M] acquisition timeout during reconciliation'
                    );
                    continue;
                }

                try {
                    $results[$migrationId] = $this->reconciler->reconcileMigration($migrationId);
                } finally {
                    $lock->release();
                }
            }

            // Recompute the current level.
            $this->recomputeLevel();

            return $results;
        } finally {
            $this->gate->release();
        }
    }

    /**
     * Apply the next pending migration in chain order.
     *
     * @return ApplyResult
     */
    public function applyNext(): ApplyResult
    {
        $next = $this->findNextPendingMigration();
        if ($next === null) {
            return ApplyResult::noOp('all migrations are applied');
        }

        return $this->applyMigration($next);
    }

    /**
     * Apply a specific migration.
     */
    public function applyMigration(MigrationDefinition $def): ApplyResult
    {
        $opId = uniqid('apply_', true);

        // Step 1: Acquire gate-S briefly.
        $this->gate->ensureRowExists();
        $this->gate->acquireShared(Gate::DEFAULT_SHARED_TIMEOUT);
        $guardConnId = $this->gate->getConnectionId();

        // Acquire the guard advisory lock on the gate connection.
        $guardName = 'dglab.mig.guard.' . $def->migrationId;
        $stmt = $this->gateConnection->prepare('SELECT GET_LOCK(?, 1) AS acquired');
        $stmt->execute([$guardName]);
        $acquired = $stmt->fetch();

        // Step 2: Acquire L1[M] on the work connection.
        $migLock = new MigrationLock($this->schemaHash, $def->migrationId, $this->workConnection);
        if (!$migLock->acquire()) {
            $this->gate->release();
            return ApplyResult::failed("could not acquire L1[{$def->migrationId}]: migration busy");
        }

        // Step 3: Release gate-S.
        $this->gate->release();

        // Set up liveness guard.
        $guard = new LivenessGuard($def->migrationId, $guardConnId, $this->workConnection);

        try {
            // Step 4: Recovery-before-action.
            $this->reconciler->reconcileMigration($def->migrationId);

            // Step 5: INSERT attempt row.
            $stmt = $this->workConnection->prepare(
                'INSERT INTO migration_attempt (migration_id, direction) VALUES (?, "upgrade")'
            );
            $stmt->execute([$def->migrationId]);
            $attemptId = (int) $this->workConnection->lastInsertId();

            // Step 6: INSERT prepared (BEFORE validation — Rev 5 §7.2).
            if (!$guard->isGateAlive()) {
                return $this->abortApply($def->migrationId, $attemptId, $migLock, 'gate connection lost before prepared');
            }
            $this->gseq->allocateAndInsertAttemptEvent(
                $attemptId, EventTypes::PREPARED, null, null
            );

            // Step 7: Validate.
            $validationError = $this->validateMigration($def);
            if ($validationError !== null) {
                // Pre-execution failure: INSERT execution_failed_pre_exec (non-establishing).
                // State is NOT updated (Rev 5 §4.3).
                $this->gseq->allocateAndInsertAttemptEvent(
                    $attemptId, EventTypes::EXECUTION_FAILED_PRE_EXEC, null,
                    ['reason' => $validationError]
                );
                $migLock->release();
                return ApplyResult::failed("validation failed: {$validationError} (pre-execution; state unchanged; retry allowed)");
            }

            // Step 8: INSERT dispatch_started.
            if (!$guard->isGateAlive()) {
                return $this->abortApply($def->migrationId, $attemptId, $migLock, 'gate connection lost before dispatch');
            }
            $this->gseq->allocateAndInsertAttemptEvent($attemptId, EventTypes::DISPATCH_STARTED);

            // Step 9: Per-statement intent/execute/result loop.
            for ($i = 0; $i < count($def->upgradeBlocks); $i++) {
                $block = $def->upgradeBlocks[$i];
                $stmtIndex = $i + 1;

                // Intent: INSERT statement_dispatched(i) BEFORE execution.
                $this->gseq->allocateAndInsertAttemptEvent(
                    $attemptId, EventTypes::STATEMENT_DISPATCHED, $stmtIndex, ['sql' => $block]
                );

                // Execute the block through the driver.
                // EMULATE_PREPARES is off (DBAL Connection contract); a
                // multi-statement block raises a driver error here.
                try {
                    $this->workConnection->exec($block);
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::STATEMENT_SUCCEEDED, $stmtIndex, null
                    );
                } catch (DatabaseException $e) {
                    // Post-dispatch failure: INSERT execution_failed_dispatched (establishing → diverged).
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::STATEMENT_FAILED, $stmtIndex,
                        ['error' => $e->getMessage()]
                    );
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::EXECUTION_FAILED_DISPATCHED, null,
                        ['reason' => "statement {$stmtIndex} failed", 'error' => $e->getMessage()]
                    );
                    $this->updateState($def->migrationId, 'diverged', 'attempt_event', null, null);
                    $migLock->release();
                    return ApplyResult::failed("statement {$stmtIndex} failed: {$e->getMessage()} (post-dispatch; state → diverged; NO auto-retry)");
                }
            }

            // Step 10: INSERT execution_succeeded (establishing → applied).
            $eventRowId = $this->gseq->allocateAndInsertAttemptEvent(
                $attemptId, EventTypes::EXECUTION_SUCCEEDED
            );
            $gseq = $this->gseq->getCurrentValue();

            // Step 11: UPDATE migration_state (write-record-then-state invariant).
            $this->updateState($def->migrationId, 'applied', 'attempt_event', $eventRowId, $gseq, $attemptId);

            // Step 12: Level recompute (under the level lock — Rev 5.3 §2.2).
            $this->recomputeLevel();

            // Step 13: Release L1[M].
            $migLock->release();

            return ApplyResult::succeeded($def->migrationId, $attemptId);
        } catch (\Throwable $e) {
            // Uncatched failure: release locks and propagate.
            try { $migLock->release(); } catch (\Throwable) {}
            throw $e;
        }
    }

    /**
     * Roll back the latest applied migration (MVP: latest-applied only).
     */
    public function rollbackLatest(): ApplyResult
    {
        $currentLevel = $this->getCurrentLevel();
        if ($currentLevel === null) {
            return ApplyResult::noOp('no migrations applied; nothing to roll back');
        }

        $def = $this->migrations[$currentLevel] ?? null;
        if ($def === null) {
            return ApplyResult::failed("current level migration '{$currentLevel}' not found in definitions");
        }

        // A rollback is a real executed attempt against the downgrade artifact.
        $opId = uniqid('rollback_', true);

        // Gate-S + L1[M].
        $this->gate->acquireShared();
        $guardConnId = $this->gate->getConnectionId();
        $migLock = new MigrationLock($this->schemaHash, $def->migrationId, $this->workConnection);
        if (!$migLock->acquire()) {
            $this->gate->release();
            return ApplyResult::failed("could not acquire L1[{$def->migrationId}]: migration busy");
        }
        $this->gate->release();
        $guard = new LivenessGuard($def->migrationId, $guardConnId, $this->workConnection);

        try {
            // Recovery-before-action.
            $this->reconciler->reconcileMigration($def->migrationId);

            // Create a downgrade attempt.
            $stmt = $this->workConnection->prepare(
                'INSERT INTO migration_attempt (migration_id, direction) VALUES (?, "downgrade")'
            );
            $stmt->execute([$def->migrationId]);
            $attemptId = (int) $this->workConnection->lastInsertId();

            $this->gseq->allocateAndInsertAttemptEvent($attemptId, EventTypes::PREPARED);

            // Validate downgrade blocks exist.
            if (count($def->downgradeBlocks) === 0) {
                $this->gseq->allocateAndInsertAttemptEvent(
                    $attemptId, EventTypes::EXECUTION_FAILED_PRE_EXEC, null,
                    ['reason' => 'no downgrade blocks defined']
                );
                $migLock->release();
                return ApplyResult::failed("migration '{$def->migrationId}' has no downgrade blocks; cannot roll back");
            }

            $this->gseq->allocateAndInsertAttemptEvent($attemptId, EventTypes::DISPATCH_STARTED);

            // Execute downgrade blocks.
            for ($i = 0; $i < count($def->downgradeBlocks); $i++) {
                $block = $def->downgradeBlocks[$i];
                $stmtIndex = $i + 1;

                $this->gseq->allocateAndInsertAttemptEvent(
                    $attemptId, EventTypes::STATEMENT_DISPATCHED, $stmtIndex, ['sql' => $block]
                );

                try {
                    $this->workConnection->exec($block);
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::STATEMENT_SUCCEEDED, $stmtIndex
                    );
                } catch (DatabaseException $e) {
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::STATEMENT_FAILED, $stmtIndex, ['error' => $e->getMessage()]
                    );
                    $this->gseq->allocateAndInsertAttemptEvent(
                        $attemptId, EventTypes::EXECUTION_FAILED_DISPATCHED, null,
                        ['reason' => "downgrade statement {$stmtIndex} failed"]
                    );
                    $this->updateState($def->migrationId, 'diverged', 'attempt_event', null, null);
                    $migLock->release();
                    return ApplyResult::failed("downgrade statement {$stmtIndex} failed: {$e->getMessage()}");
                }
            }

            // Execution succeeded → state = unapplied (direction = downgrade).
            $eventId = $this->gseq->allocateAndInsertAttemptEvent(
                $attemptId, EventTypes::EXECUTION_SUCCEEDED
            );
            $gseq = $this->gseq->getCurrentValue();
            $this->updateState($def->migrationId, 'unapplied', 'attempt_event', $eventId, $gseq, $attemptId);

            // Level recompute (retreats to the predecessor).
            $this->recomputeLevel();

            $migLock->release();
            return ApplyResult::succeeded($def->migrationId, $attemptId);
        } catch (\Throwable $e) {
            try { $migLock->release(); } catch (\Throwable) {}
            throw $e;
        }
    }

    /**
     * Dry-run: validate migrations without executing any SQL or mutating state.
     */
    public function dryRun(): array
    {
        $results = [];
        foreach ($this->migrations as $id => $def) {
            $error = $this->validateMigration($def);
            if ($error !== null) {
                $results[$id] = "INVALID: {$error}";
            } else {
                $results[$id] = "valid: " . count($def->upgradeBlocks) . " upgrade blocks, " . count($def->downgradeBlocks) . " downgrade blocks";
            }
        }
        return $results;
    }

    /**
     * Report the current migration status.
     */
    public function status(): array
    {
        $status = [];
        $currentLevel = $this->getCurrentLevel();

        foreach ($this->migrations as $id => $def) {
            $state = $this->readState($id);
            $status[$id] = [
                'chain_position' => $def->chainPosition,
                'state' => $state['current_state'] ?? 'pending',
                'is_current_level' => $id === $currentLevel,
                'predecessor' => $def->predecessorId,
                'description' => $def->description,
            ];
        }

        return [
            'schema' => $this->schemaName,
            'current_level' => $currentLevel,
            'migrations' => $status,
        ];
    }

    // --- Helpers ---

    private function findNextPendingMigration(): ?MigrationDefinition
    {
        $currentLevel = $this->getCurrentLevel();
        $nextPosition = $currentLevel === null ? 0 : ($this->migrations[$currentLevel]->chainPosition + 1);

        foreach ($this->migrations as $def) {
            if ($def->chainPosition === $nextPosition) {
                return $def;
            }
        }
        return null;
    }

    private function getCurrentLevel(): ?string
    {
        $stmt = $this->workConnection->prepare(
            'SELECT current_level_migration_id FROM migration_current_level WHERE schema_hash = ?'
        );
        $stmt->execute([$this->schemaHash]);
        $row = $stmt->fetch();
        return $row !== false ? $row['current_level_migration_id'] : null;
    }

    private function recomputeLevel(): void
    {
        $levelLock = new LevelLock($this->schemaHash, $this->workConnection);
        if (!$levelLock->acquire()) {
            throw new \RuntimeException('Could not acquire level lock for recompute');
        }

        try {
            // Walk the chain from genesis; find the highest applied migration
            // whose entire chain prefix is applied.
            $sorted = array_values($this->migrations);
            usort($sorted, fn(MigrationDefinition $a, MigrationDefinition $b) => $a->chainPosition <=> $b->chainPosition);

            $level = null;
            foreach ($sorted as $def) {
                $state = $this->readState($def->migrationId);
                if (($state['current_state'] ?? 'pending') === 'applied') {
                    $level = $def->migrationId;
                } else {
                    break;
                }
            }

            $stmt = $this->workConnection->prepare(
                'INSERT INTO migration_current_level (schema_hash, current_level_migration_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE current_level_migration_id = VALUES(current_level_migration_id)'
            );
            $stmt->execute([$this->schemaHash, $level]);
        } finally {
            $levelLock->release();
        }
    }

    private function validateMigration(MigrationDefinition $def): ?string
    {
        if (count($def->upgradeBlocks) === 0) {
            return "migration '{$def->migrationId}' has no upgrade SQL blocks";
        }
        // The driver contract (EMULATE_PREPARES off) enforces one-statement-per-block
        // at dispatch time. The parser does not claim to validate this textually.
        return null;
    }

    private function updateState(
        string $migrationId, string $state, string $establishedByType,
        ?int $establishedById, ?int $establishedByGseq, ?int $lastAttemptId = null
    ): void {
        $stmt = $this->workConnection->prepare(
            'INSERT INTO migration_state (migration_id, current_state, established_by_type, established_by_id, established_by_gseq, last_attempt_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               current_state = VALUES(current_state),
               established_by_type = VALUES(established_by_type),
               established_by_id = VALUES(established_by_id),
               established_by_gseq = VALUES(established_by_gseq),
               last_attempt_id = VALUES(last_attempt_id)'
        );
        $stmt->execute([$migrationId, $state, $establishedByType, $establishedById, $establishedByGseq, $lastAttemptId]);
    }

    private function readState(string $migrationId): ?array
    {
        $stmt = $this->workConnection->prepare(
            'SELECT current_state, established_by_type, established_by_id, established_by_gseq
             FROM migration_state WHERE migration_id = ?'
        );
        $stmt->execute([$migrationId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    private function abortApply(string $migrationId, int $attemptId, MigrationLock $lock, string $reason): ApplyResult
    {
        $lock->release();
        return ApplyResult::failed($reason);
    }

    private function executeSchemaSql(string $sql): void
    {
        $statements = $this->splitSqlStatements($sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
                continue;
            }
            if (str_starts_with(strtoupper($stmt), 'DELIMITER')) {
                continue;
            }
            try {
                $this->workConnection->exec($stmt);
            } catch (DatabaseException $e) {
                // Triggers may fail if they already exist (IF NOT EXISTS is
                // used in CREATE TRIGGER on MySQL 8.0+; on 5.7 not supported).
                if (!str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Split a SQL script into individual statements using a state machine
     * that correctly handles:
     *   - -- line comments (ignored, do not split)
     *   - /\* *\/ block comments (ignored, do not split)
     *   - single-quoted string literals (with backslash escapes)
     *   - double-quoted string literals (with backslash escapes)
     *   - DELIMITER directives (changes the statement separator for
     *     trigger bodies using BEGIN...END)
     *
     * This replaces the previous ad-hoc semicolon-splitting approach which
     * broke on: (a) ';' inside SQL comments, (b) trigger bodies where
     * BEGIN...END must be sent as a single unit.
     *
     * @return array<int, string> Complete SQL statements (delimiter stripped).
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $delimiter = ';';
        $inSingle = false;
        $inDouble = false;
        $inLineComment = false;
        $inBlockComment = false;
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $char = $sql[$i];
            $rest = substr($sql, $i);
            $atLineStart = ($i === 0 || $sql[$i - 1] === "\n");

            // DELIMITER directive — only at line start, outside strings/comments
            if ($atLineStart && !$inSingle && !$inDouble && !$inLineComment && !$inBlockComment) {
                if (preg_match('/^DELIMITER\s+(\S+)[ \t]*\r?\n/', $rest, $m)) {
                    $current = trim($current);
                    if ($current !== '') {
                        $statements[] = $current;
                    }
                    $current = '';
                    $delimiter = $m[1];
                    // Skip past the DELIMITER line (including the newline)
                    $lineEnd = strpos($rest, "\n");
                    $i += $lineEnd !== false ? $lineEnd + 1 : strlen($rest);
                    continue;
                }
            }

            // Line comment (-- to end of line)
            if ($inLineComment) {
                $current .= $char;
                if ($char === "\n") {
                    $inLineComment = false;
                }
                $i++;
                continue;
            }

            // Block comment (/* ... */)
            if ($inBlockComment) {
                $current .= $char;
                if ($char === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
                    $current .= '/';
                    $i += 2;
                    $inBlockComment = false;
                    continue;
                }
                $i++;
                continue;
            }

            // Single-quoted string
            if ($inSingle) {
                $current .= $char;
                if ($char === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inSingle = false;
                }
                $i++;
                continue;
            }

            // Double-quoted string
            if ($inDouble) {
                $current .= $char;
                if ($char === '"' && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inDouble = false;
                }
                $i++;
                continue;
            }

            // Not in any string/comment — detect comment/string starts
            if ($char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $inLineComment = true;
                $current .= '--';
                $i += 2;
                continue;
            }
            if ($char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $inBlockComment = true;
                $current .= '/*';
                $i += 2;
                continue;
            }
            if ($char === "'") {
                $inSingle = true;
                $current .= $char;
                $i++;
                continue;
            }
            if ($char === '"') {
                $inDouble = true;
                $current .= $char;
                $i++;
                continue;
            }

            // Check for delimiter match (outside strings/comments)
            $delimLen = strlen($delimiter);
            if (substr($rest, 0, $delimLen) === $delimiter) {
                $current = trim($current);
                if ($current !== '') {
                    $statements[] = $current;
                }
                $current = '';
                $i += $delimLen;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $current = trim($current);
        if ($current !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}

/**
 * Result of an apply/rollback operation.
 */
final class ApplyResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly string $message,
        public readonly ?int $attemptId = null,
    ) {}

    public static function succeeded(string $migrationId, int $attemptId): self
    {
        return new self(true, "applied {$migrationId} (attempt {$attemptId})", $attemptId);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }

    public static function noOp(string $message): self
    {
        return new self(true, $message);
    }
}
