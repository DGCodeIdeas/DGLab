<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Migration;

/**
 * MigrationRunnerInterface
 *
 * Contract for a migration runner. This interface is intentionally
 * provider-agnostic: it describes WHAT a runner does (status, migrate,
 * rollback) without prescribing HOW (Doctrine, native, etc.).
 *
 * A future native implementation (Revision 5.3) can sit behind this
 * interface without a redesign. The interface carries no Doctrine-
 * specific types or semantics — those live only in the adapter.
 *
 * @see DoctrineMigrationRunner (temporary adapter)
 */
interface MigrationRunnerInterface
{
    /**
     * Return the status of all known migrations.
     *
     * @return array<int, array{
     *   version: string,
     *   description: string,
     *   executed: bool,
     *   executedAt: ?\DateTimeImmutable,
     * }>
     */
    public function status(): array;

    /**
     * Apply all pending migrations up to (and including) the given
     * target version. If $targetVersion is null, apply all pending.
     *
     * @param string|null $targetVersion Optional target version.
     * @return array<int, string> List of versions that were applied.
     */
    public function migrate(?string $targetVersion = null): array;

    /**
     * Roll back to the given target version (exclusive). If $targetVersion
     * is null, roll back the last applied migration.
     *
     * NOTE: Rollback is only as safe as the inverse SQL. DDL statements
     * in MySQL implicitly commit and cannot be transactionally rolled
     * back. The adapter documents which operations are genuinely
     * reversible.
     *
     * @param string|null $targetVersion Optional target version.
     * @return array<int, string> List of versions that were rolled back.
     */
    public function rollback(?string $targetVersion = null): array;
}
