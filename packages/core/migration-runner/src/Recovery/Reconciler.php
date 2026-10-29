<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Recovery;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\DatabaseException;
use SovereignStack\Core\MigrationRunner\Event\EventTypes;
use SovereignStack\Core\MigrationRunner\Sequence\GseqAllocator;

/**
 * The reconciliation pass: runs on every runner start before accepting commands.
 *
 * Controlling design: Z.ai Focused Revision 5.3 §2 (carrying Rev 4 §2.3-§2.6
 * and Rev 5 §2).
 *
 * The pass:
 *   1. For each migration M (under L1[M] in lex order), find the latest
 *      establishing record L (max gseq among establishing event types).
 *   2. Independently scan for unresolved attempts (including zero-event
 *      attempts per Rev 5.3 §2.2).
 *   3. If an unresolved attempt exists, hand off to the 8-step recovery
 *      algorithm (Rev 5.3 §2.3).
 *   4. If L exists, compare migration_state.established_by_gseq with L.gseq:
 *      - Match → no-op (idempotent).
 *      - Stale (state's gseq < L's gseq) → update state, insert auto_reconciled audit.
 *      - Inconsistent reference → quarantine.
 *      - Integrity anomaly (state's gseq > L's gseq) → quarantine.
 *
 * The pass is idempotent: a second pass finds every migration in the "match"
 * branch and writes nothing (Rev 5 §2.5).
 *
 * @package SovereignStack\Core\MigrationRunner\Recovery
 */
final class Reconciler
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly GseqAllocator $gseq,
    ) {}

    /**
     * Run the full reconciliation pass for a single migration M.
     *
     * Precondition: L1[M] is held by the caller.
     *
     * @param string $migrationId
     * @return ReconciliationResult
     */
    public function reconcileMigration(string $migrationId): ReconciliationResult
    {
        // Step 1: Read migration_state[M]; insert pending if absent.
        $state = $this->readState($migrationId);
        if ($state === null) {
            $this->insertPendingState($migrationId);
            $state = ['current_state' => 'pending', 'established_by_gseq' => null];
        }

        // Step 2: Independent unresolved-attempt scan (Rev 5.3 §2.2).
        $unresolved = $this->findUnresolvedAttempt($migrationId);
        if ($unresolved !== null) {
            // Hand off to the recovery algorithm.
            return $this->recoverUnresolvedAttempt($unresolved, $migrationId);
        }

        // Step 3: Find L = max-gseq establishing record for M.
        $L = $this->findLatestEstablishingRecord($migrationId);

        // Step 4: No establishing record and state is pending → no-op.
        if ($L === null) {
            return ReconciliationResult::noOp($migrationId, 'no establishing record; pending');
        }

        $stateGseq = $state['established_by_gseq'] !== null ? (int) $state['established_by_gseq'] : null;

        // Step 5: Match → idempotent no-op.
        if ($stateGseq !== null && $stateGseq === $L['gseq']) {
            return ReconciliationResult::noOp($migrationId, 'state matches latest establishing record');
        }

        // Step 6: Inconsistent reference (established_by points to non-existent record).
        // The gseq in state is higher than any known establishing record's gseq
        // → the state claims an authority that doesn't exist → integrity anomaly.
        if ($stateGseq !== null && $stateGseq > $L['gseq']) {
            $this->quarantine($migrationId, 'integrity anomaly: state gseq > max establishing gseq');
            return ReconciliationResult::quarantined($migrationId, 'integrity anomaly');
        }

        // Step 7: Stale state (state's gseq < L's gseq, or state has no gseq).
        // Update state to reference L.
        $impliedState = EventTypes::impliedState(
            $L['event_type'],
            $L['direction'] ?? 'upgrade',
        );

        if ($impliedState === null) {
            // L is an operator event (manual_completion, manual_cleanup, etc.).
            $impliedState = EventTypes::impliedState($L['event_type']);
        }

        $this->updateStateToEstablishingRecord($migrationId, $impliedState, $L);

        // Insert auto_reconciled audit event (non-establishing; Rev 5 §3.1).
        $this->gseq->allocateAndInsertOperatorEvent(
            $migrationId,
            EventTypes::AUTO_RECONCILED,
            (string) $L['gseq'],
            ['reason' => 'stale state reconciled', 'new_gseq' => $L['gseq']],
        );

        return ReconciliationResult::reconciled($migrationId, $impliedState, $L['gseq']);
    }

    /**
     * Find an unresolved attempt for migration M.
     *
     * An attempt is unresolved if:
     *   - It has zero events (Rev 5.3 §2.2 — crash window W-1), OR
     *   - Its latest event is non-terminal (not in TERMINAL_TYPES).
     *
     * @return array{attempt_id: int, latest_event: string|null}|null
     */
    public function findUnresolvedAttempt(string $migrationId): ?array
    {
        // Find attempts for M that have zero events.
        $stmt = $this->connection->prepare(
            'SELECT a.attempt_id FROM migration_attempt a '
            . 'LEFT JOIN migration_attempt_event e ON e.attempt_id = a.attempt_id '
            . 'WHERE a.migration_id = ? AND e.event_id IS NULL '
            . 'ORDER BY a.attempt_id ASC LIMIT 1'
        );
        $stmt->execute([$migrationId]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return ['attempt_id' => (int) $row['attempt_id'], 'latest_event' => null];
        }

        // Find attempts whose latest event is non-terminal.
        $stmt = $this->connection->prepare(
            'SELECT a.attempt_id, (
                SELECT e.event_type FROM migration_attempt_event e
                WHERE e.attempt_id = a.attempt_id
                ORDER BY e.event_id DESC LIMIT 1
            ) AS latest_event
            FROM migration_attempt a
            WHERE a.migration_id = ?
            ORDER BY a.attempt_id ASC'
        );
        $stmt->execute([$migrationId]);

        while ($row = $stmt->fetch()) {
            $latestEvent = $row['latest_event'];
            if ($latestEvent === null || !EventTypes::isTerminal($latestEvent)) {
                return ['attempt_id' => (int) $row['attempt_id'], 'latest_event' => $latestEvent];
            }
        }

        return null;
    }

    /**
     * The 8-step recovery algorithm for zero-event and pre-dispatch attempts
     * (Rev 5.3 §2.3, Rev 5 §2.3).
     *
     * Steps:
     *   1. (L1[M] already held by caller.)
     *   2. Load the attempt and all its events.
     *   3. Determine whether any statement-dispatch intent exists.
     *   4. If no dispatch intent, establish that no SQL was dispatched.
     *   5. Record a durable recovered pre-dispatch outcome (execution_failed_pre_exec).
     *   6. Do NOT update migration_state (pre_exec is non-establishing per Rev 5 §4.3).
     *   7. Idempotent: if the event already exists (recovery was interrupted
     *      and restarted), the INSERT is a no-op.
     *   8. The migration is eligible for retry.
     *
     * If dispatch intent EXISTS (Rev 5.3 §2.5), escalate to postcondition
     * verification or operator intervention. Do NOT auto-retry.
     */
    public function recoverUnresolvedAttempt(array $unresolved, string $migrationId): ReconciliationResult
    {
        $attemptId = $unresolved['attempt_id'];

        // Step 3: Check for dispatch intent events.
        $hasDispatchIntent = $this->hasDispatchIntent($attemptId);

        if ($hasDispatchIntent) {
            // Rev 5.3 §2.5: SQL may have been dispatched. Escalate.
            // Mark the migration as diverged (uncertain outcome).
            $this->quarantine($migrationId, 'uncertain post-dispatch outcome; operator intervention required');
            return ReconciliationResult::uncertain($migrationId, $attemptId, 'post-dispatch uncertainty; operator required');
        }

        // Step 4: No dispatch intent → no SQL could have been dispatched
        // (structural proof: statement_dispatched is written BEFORE execution
        // per Rev 5 §7.2; its absence proves no SQL was dispatched).

        // Step 5: Record execution_failed_pre_exec (recovered).
        // Step 7: Idempotent — check if it already exists.
        if (!$this->hasPreExecEvent($attemptId)) {
            $this->gseq->allocateAndInsertAttemptEvent(
                $attemptId,
                EventTypes::EXECUTION_FAILED_PRE_EXEC,
                null,
                ['recovered' => true, 'reason' => 'no_dispatch_intent_found'],
            );
        }

        // Step 6: Do NOT update migration_state. pre_exec is non-establishing
        // (Rev 5 §4.3). The state row remains at its previous value.

        return ReconciliationResult::recovered($migrationId, $attemptId, 'pre-dispatch recovery; eligible for retry');
    }

    /**
     * Check whether the attempt has any statement_dispatched event.
     */
    private function hasDispatchIntent(int $attemptId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) AS cnt FROM migration_attempt_event '
            . 'WHERE attempt_id = ? AND event_type = ?'
        );
        $stmt->execute([$attemptId, EventTypes::STATEMENT_DISPATCHED]);
        $row = $stmt->fetch();
        return (int) $row['cnt'] > 0;
    }

    /**
     * Check whether the attempt already has an execution_failed_pre_exec event
     * (idempotence check for recovery interruption).
     */
    private function hasPreExecEvent(int $attemptId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) AS cnt FROM migration_attempt_event '
            . 'WHERE attempt_id = ? AND event_type = ?'
        );
        $stmt->execute([$attemptId, EventTypes::EXECUTION_FAILED_PRE_EXEC]);
        $row = $stmt->fetch();
        return (int) $row['cnt'] > 0;
    }

    /**
     * Find the latest establishing record for migration M.
     * Returns the record with the maximum gseq among all establishing
     * event types (from both migration_attempt_event and operator_event).
     *
     * @return array{gseq: int, event_type: string, source_table: string, id: int, direction: ?string}|null
     */
    private function findLatestEstablishingRecord(string $migrationId): ?array
    {
        // Query attempt events that are establishing.
        $stmt = $this->connection->prepare(
            'SELECT e.gseq, e.event_type, e.event_id AS id, a.direction
             FROM migration_attempt_event e
             JOIN migration_attempt a ON a.attempt_id = e.attempt_id
             WHERE a.migration_id = ? AND e.event_type IN ('
            . implode(',', array_fill(0, count(EventTypes::ESTABLISHING_TYPES), '?'))
            . ')
             ORDER BY e.gseq DESC LIMIT 1'
        );
        $params = array_merge([$migrationId], EventTypes::ESTABLISHING_TYPES);
        $stmt->execute($params);
        $attemptRow = $stmt->fetch();

        // Query operator events that are establishing.
        $stmt2 = $this->connection->prepare(
            'SELECT gseq, decision_type AS event_type, operator_event_id AS id
             FROM operator_event
             WHERE migration_id = ? AND decision_type IN ('
            . implode(',', array_fill(0, count(EventTypes::ESTABLISHING_TYPES), '?'))
            . ')
             ORDER BY gseq DESC LIMIT 1'
        );
        $stmt2->execute($params);
        $operatorRow = $stmt2->fetch();

        // Compare the two candidates by gseq.
        $best = null;
        if ($attemptRow !== false) {
            $best = [
                'gseq' => (int) $attemptRow['gseq'],
                'event_type' => $attemptRow['event_type'],
                'source_table' => 'attempt_event',
                'id' => (int) $attemptRow['event_id'],
                'direction' => $attemptRow['direction'],
            ];
        }
        if ($operatorRow !== false) {
            $opGseq = (int) $operatorRow['gseq'];
            if ($best === null || $opGseq > $best['gseq']) {
                $best = [
                    'gseq' => $opGseq,
                    'event_type' => $operatorRow['event_type'],
                    'source_table' => 'operator_event',
                    'id' => (int) $operatorRow['operator_event_id'],
                    'direction' => null,
                ];
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readState(string $migrationId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT current_state, established_by_type, established_by_id, established_by_gseq, last_attempt_id
             FROM migration_state WHERE migration_id = ?'
        );
        $stmt->execute([$migrationId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    private function insertPendingState(string $migrationId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO migration_state (migration_id, current_state) VALUES (?, "pending")'
        );
        $stmt->execute([$migrationId]);
    }

    private function updateStateToEstablishingRecord(string $migrationId, string $newState, array $L): void
    {
        $establishedByType = $L['source_table'] === 'attempt_event' ? 'attempt_event' : 'operator_event';
        $stmt = $this->connection->prepare(
            'UPDATE migration_state
             SET current_state = ?, established_by_type = ?, established_by_id = ?, established_by_gseq = ?
             WHERE migration_id = ?'
        );
        $stmt->execute([
            $newState,
            $establishedByType,
            $L['id'],
            $L['gseq'],
            $migrationId,
        ]);
    }

    private function quarantine(string $migrationId, string $reason): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE migration_state SET current_state = "quarantined" WHERE migration_id = ?'
        );
        $stmt->execute([$migrationId]);

        $this->gseq->allocateAndInsertOperatorEvent(
            $migrationId,
            EventTypes::AUTO_QUARANTINED_INTEGRITY,
            null,
            ['reason' => $reason],
        );
    }
}
