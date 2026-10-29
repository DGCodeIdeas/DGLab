<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Event;

/**
 * Event type enumeration and the establishing/audit-only classification.
 *
 * Controlling design: Z.ai Focused Revision 5.3 (replacing Rev 4 §3.1).
 *
 * Establishing events are the ONLY types that can be referenced by
 * migration_state.established_by_type / established_by_id / established_by_gseq.
 * Audit-only events can be INSERTed by the runner but are NEVER referenced by
 * established_by — they cannot become state authorities (Rev 5.3 §3, carried
 * from Rev 4 §3.2).
 *
 * The enumeration is CLOSED: the runner refuses to write any event type not
 * listed here. Adding a type requires an owner-approved schema change to this
 * enumeration, not an implementation discretion.
 *
 * @package SovereignStack\Core\MigrationRunner\Event
 */
final class EventTypes
{
    // --- Establishing event types (can be referenced by established_by) ---

    /** Terminal: migration execution succeeded (upgrade → applied, downgrade → unapplied). */
    public const string EXECUTION_SUCCEEDED = 'execution_succeeded';

    /** Terminal: execution failed after SQL was dispatched → diverged. */
    public const string EXECUTION_FAILED_DISPATCHED = 'execution_failed_dispatched';

    /** Operator decision: manual resolution of an unresolved attempt as applied. */
    public const string MANUAL_COMPLETION = 'manual_completion';

    /** Operator decision: manual cleanup of an unresolved attempt → unapplied. */
    public const string MANUAL_CLEANUP = 'manual_cleanup';

    /** Operator decision: baseline adoption (excluded from MVP; schema retained). */
    public const string BASELINE_ADOPTED = 'baseline_adopted';

    /** Operator decision: quarantine a migration. */
    public const string SUSPEND = 'suspend';

    // --- Audit-only event types (never referenced by established_by) ---

    /** Audit: reconciliation pass updated a stale state row. */
    public const string AUTO_RECONCILED = 'auto_reconciled';

    /** Audit: recovery set state to diverged (post-dispatch, unverified). */
    public const string AUTO_DIVERGED = 'auto_diverged';

    /** Audit: integrity anomaly detected; state quarantined. */
    public const string AUTO_QUARANTINED_INTEGRITY = 'auto_quarantined_integrity';

    /** Audit: runner started in degraded integrity mode (weaker privilege model). */
    public const string DEGRADED_INTEGRITY_MODE = 'degraded_integrity_mode';

    // --- Lifecycle event types (non-establishing) ---

    /** Lifecycle: attempt registered, validation pending (Rev 5 §7.2 corrected timing). */
    public const string PREPARED = 'prepared';

    /** Lifecycle: validation succeeded, dispatch about to begin. */
    public const string DISPATCH_STARTED = 'dispatch_started';

    // --- Intent event type (write-ahead, non-establishing) ---

    /** Intent: durable record of intent to execute statement i, written BEFORE execution. */
    public const string STATEMENT_DISPATCHED = 'statement_dispatched';

    // --- Result event types (non-establishing) ---

    /** Result: statement i executed successfully. */
    public const string STATEMENT_SUCCEEDED = 'statement_succeeded';

    /** Result: statement i failed. */
    public const string STATEMENT_FAILED = 'statement_failed';

    // --- Terminal non-establishing events ---

    /** Terminal: validation failure, no SQL dispatched. Non-establishing (Rev 5 §4.3). */
    public const string EXECUTION_FAILED_PRE_EXEC = 'execution_failed_pre_exec';

    /** Terminal: attempt superseded by an operator decision. Non-establishing (paired with operator_event). */
    public const string ATTEMMENT_SUPERSEDED = 'attempt_superseded';

    /**
     * The complete set of establishing event types.
     * Only these can be referenced by migration_state.established_by_*.
     */
    public const array ESTABLISHING_TYPES = [
        self::EXECUTION_SUCCEEDED,
        self::EXECUTION_FAILED_DISPATCHED,
        self::MANUAL_COMPLETION,
        self::MANUAL_CLEANUP,
        self::BASELINE_ADOPTED,
        self::SUSPEND,
    ];

    /**
     * The complete set of audit-only event types.
     * These can be INSERTed but are never referenced by established_by.
     */
    public const array AUDIT_ONLY_TYPES = [
        self::AUTO_RECONCILED,
        self::AUTO_DIVERGED,
        self::AUTO_QUARANTINED_INTEGRITY,
        self::DEGRADED_INTEGRITY_MODE,
    ];

    /**
     * Terminal event types (attempt is no longer unresolved if its latest
     * event is one of these). Used by the unresolved-attempt scan (Rev 5 §2.2).
     */
    public const array TERMINAL_TYPES = [
        self::EXECUTION_SUCCEEDED,
        self::EXECUTION_FAILED_DISPATCHED,
        self::EXECUTION_FAILED_PRE_EXEC,
        self::ATTEMMENT_SUPERSEDED,
    ];

    /**
     * Is the given event type establishing (can be referenced by established_by)?
     */
    public static function isEstablishing(string $eventType): bool
    {
        return in_array($eventType, self::ESTABLISHING_TYPES, true);
    }

    /**
     * Is the given event type audit-only (must never be referenced by established_by)?
     */
    public static function isAuditOnly(string $eventType): bool
    {
        return in_array($eventType, self::AUDIT_ONLY_TYPES, true);
    }

    /**
     * Is the given event type terminal (closes an attempt)?
     */
    public static function isTerminal(string $eventType): bool
    {
        return in_array($eventType, self::TERMINAL_TYPES, true);
    }

    /**
     * Is the given event type a statement-dispatch intent event?
     * Used by the auto-retry rule (Rev 5 §7.5): auto-retry is permitted only
     * when no intent event exists in the attempt's log.
     */
    public static function isDispatchIntent(string $eventType): bool
    {
        return $eventType === self::STATEMENT_DISPATCHED;
    }

    /**
     * State implied by an establishing event type (Rev 4 §2.3 step 6 provenance table).
     * Returns null for non-establishing types.
     *
     * @return string|null One of: 'applied', 'unapplied', 'diverged', 'quarantined'
     */
    public static function impliedState(string $eventType, string $direction = 'upgrade'): ?string
    {
        return match ($eventType) {
            self::EXECUTION_SUCCEEDED => $direction === 'upgrade' ? 'applied' : 'unapplied',
            self::EXECUTION_FAILED_DISPATCHED => 'diverged',
            self::MANUAL_COMPLETION, self::BASELINE_ADOPTED => 'applied',
            self::MANUAL_CLEANUP => 'unapplied',
            self::SUSPEND => 'quarantined',
            default => null,
        };
    }

    /**
     * Validate that a given event type is known to the runner.
     * The runner refuses to write unknown types.
     */
    public static function isKnown(string $eventType): bool
    {
        return self::isEstablishing($eventType)
            || self::isAuditOnly($eventType)
            || in_array($eventType, [
                self::PREPARED,
                self::DISPATCH_STARTED,
                self::STATEMENT_DISPATCHED,
                self::STATEMENT_SUCCEEDED,
                self::STATEMENT_FAILED,
                self::EXECUTION_FAILED_PRE_EXEC,
                self::ATTEMMENT_SUPERSEDED,
            ], true);
    }
}
