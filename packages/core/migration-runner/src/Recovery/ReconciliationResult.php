<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Recovery;

/**
 * Result of reconciling a single migration.
 *
 * @package SovereignStack\Core\MigrationRunner\Recovery
 */
final class ReconciliationResult
{
    public const string NO_OP = 'no_op';
    public const string RECONCILED = 'reconciled';
    public const string RECOVERED = 'recovered';
    public const string QUARANTINED = 'quarantined';
    public const string UNCERTAIN = 'uncertain';

    private function __construct(
        public readonly string $migrationId,
        public readonly string $outcome,
        public readonly ?int $attemptId,
        public readonly ?string $newState,
        public readonly ?int $establishingGseq,
        public readonly string $message,
    ) {}

    public static function noOp(string $migrationId, string $message): self
    {
        return new self($migrationId, self::NO_OP, null, null, null, $message);
    }

    public static function reconciled(string $migrationId, string $newState, int $gseq): self
    {
        return new self($migrationId, self::RECONCILED, null, $newState, $gseq, "state reconciled to {$newState} (gseq={$gseq})");
    }

    public static function recovered(string $migrationId, int $attemptId, string $message): self
    {
        return new self($migrationId, self::RECOVERED, $attemptId, null, null, $message);
    }

    public static function quarantined(string $migrationId, string $message): self
    {
        return new self($migrationId, self::QUARANTINED, null, 'quarantined', null, $message);
    }

    public static function uncertain(string $migrationId, int $attemptId, string $message): self
    {
        return new self($migrationId, self::UNCERTAIN, $attemptId, null, null, $message);
    }
}
