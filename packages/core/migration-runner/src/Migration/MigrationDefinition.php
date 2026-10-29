<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Migration;

/**
 * A parsed migration definition: metadata + upgrade/downgrade SQL blocks.
 *
 * Migration file format (Rev 5 §6.3, Rev 3 §5 — @STMT block format):
 *
 *   -- @META
 *   migration_id: 2024_01_01_000001_create_users_table
 *   predecessor: null
 *   description: Create users table
 *   -- @END
 *
 *   -- @STMT BEGIN upgrade
 *   CREATE TABLE users (...);
 *   -- @STMT END
 *
 *   -- @STMT BEGIN downgrade
 *   DROP TABLE users;
 *   -- @STMT END
 *
 *   -- @STMT BEGIN postconditions
 *   -- table_exists: users
 *   -- @STMT END
 *
 * @package SovereignStack\Core\MigrationRunner\Migration
 */
final class MigrationDefinition
{
    public string $migrationId;
    public ?string $predecessorId;
    public string $description;
    public int $chainPosition = 0;

    /** @var array<int, string> Upgrade SQL blocks (one statement per block, per the driver contract). */
    public array $upgradeBlocks = [];

    /** @var array<int, string> Downgrade SQL blocks. */
    public array $downgradeBlocks = [];

    /** @var array<string, mixed> Postcondition assertions (table_exists, column_exists, expect_empty, etc.). */
    public array $postconditions = [];

    public string $filePath;

    public function __construct(
        string $migrationId,
        ?string $predecessorId,
        string $description,
        string $filePath,
    ) {
        $this->migrationId = $migrationId;
        $this->predecessorId = $predecessorId;
        $this->description = $description;
        $this->filePath = $filePath;
    }

    public function isGenesis(): bool
    {
        return $this->predecessorId === null;
    }

    /**
     * Is this migration a DDL-policy migration (contains DDL statements)?
     * For the MVP, all migrations are assumed DDL-policy (implicit commits
     * acknowledged per Rev 3 §3). The crash-window protocol (Rev 5 §7.4)
     * escalates post-dispatch crashes to postcondition verification or operator.
     */
    public function isDdlPolicy(): bool
    {
        return true; // MVP: all migrations are DDL-policy.
    }
}
