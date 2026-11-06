<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Migration;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use SovereignStack\Core\Database\ConnectionInterface;

/**
 * DoctrineMigrationRunner
 *
 * Temporary adapter implementing MigrationRunnerInterface using Doctrine
 * DBAL as the connection layer. This adapter executes raw SQL migration
 * files (.sql) directly — it does NOT use Doctrine Migrations' PHP-class
 * system, because the 9 existing migration files are plain SQL DDL.
 *
 * ## Guarantees this adapter does NOT provide (native runner must)
 * - Failure-injection safety (two-connection lock model)
 * - Level lock / gseq log for concurrent migration detection
 * - Uncertain-DDL recovery (if DDL partially applies, state is uncertain)
 * - Integrity checksums on migration content
 * - Transactional rollback for DDL (MySQL DDL implicitly commits)
 *
 * ## Provider selection
 * Configured via `migration.runner = doctrine|native`. Validated at
 * startup. No automatic cross-provider fallback.
 *
 * @see MigrationRunnerInterface
 */
final class DoctrineMigrationRunner implements MigrationRunnerInterface
{
    private const TRACKING_TABLE = 'dglab_migrations';

    private DbalConnection $dbal;

    /** @var array<string, string> version => file path */
    private array $migrations = [];

    public function __construct(
        private readonly string $migrationsPath,
        private readonly ?ConnectionInterface $connection = null,
    ) {
        $this->dbal = $this->createDbalConnection();
        $this->discoverMigrations();
        $this->ensureTrackingTable();
    }

    private function createDbalConnection(): DbalConnection
    {
        // Use the project's own connection if available, otherwise create
        // a DBAL connection from environment variables.
        $params = [
            'dbname'   => getenv('DB_NAME') ?? 'dglab_test',
            'user'     => getenv('DB_USER') ?? 'dglab_test',
            'password' => getenv('DB_PASS') ?? 'CHANGE_ME_STRONG_PASSWORD',
            'host'     => getenv('DB_HOST') ?? '127.0.0.1',
            'port'     => (int) (getenv('DB_PORT') ?? 3306),
            'driver'   => 'pdo_mysql',
            'charset'  => 'utf8mb4',
        ];

        return DriverManager::getConnection($params);
    }

    private function discoverMigrations(): void
    {
        if (!is_dir($this->migrationsPath)) {
            return;
        }

        $files = glob($this->migrationsPath . '/*.sql') ?? [];
        sort($files);

        foreach ($files as $file) {
            $basename = basename($file, '.sql');
            // Extract version: "001_create_users_table" → "001"
            $version = preg_replace('/^(\d+).*/', '$1', $basename) ?? $basename;
            $this->migrations[$version] = $file;
        }
    }

    private function ensureTrackingTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::TRACKING_TABLE . " (
            version VARCHAR(255) NOT NULL PRIMARY KEY,
            description VARCHAR(500) NOT NULL DEFAULT '',
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            execution_time_ms INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->dbal->executeStatement($sql);
    }

    public function status(): array
    {
        $applied = $this->getAppliedVersions();
        $result = [];

        foreach ($this->migrations as $version => $file) {
            $executed = isset($applied[$version]);
            $result[] = [
                'version'     => $version,
                'description' => basename($file),
                'executed'    => $executed,
                'executedAt'  => $executed ? new \DateTimeImmutable($applied[$version]) : null,
            ];
        }

        return $result;
    }

    public function migrate(?string $targetVersion = null): array
    {
        $applied = [];
        $pending = $this->getPendingVersions($targetVersion);

        foreach ($pending as $version) {
            $file = $this->migrations[$version];
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration file: $file");
            }

            $start = microtime(true);

            try {
                // MySQL DDL implicitly commits — we do NOT wrap in a
                // transaction. Claiming "transactional rollback" would
                // misrepresent MySQL's behavior.
                $this->dbal->executeStatement($sql);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Migration $version failed: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }

            $elapsed = (int) ((microtime(true) - $start) * 1000);

            $this->dbal->executeStatement(
                "INSERT INTO " . self::TRACKING_TABLE . " (version, description, executed_at, execution_time_ms) VALUES (?, ?, NOW(), ?)",
                [$version, basename($file), $elapsed]
            );

            $applied[] = $version;
        }

        return $applied;
    }

    public function rollback(?string $targetVersion = null): array
    {
        // Rollback is NOT supported for raw SQL DDL migrations.
        // MySQL DDL implicitly commits and cannot be transactionally
        // rolled back. Inverse operations would need to be explicitly
        // defined per migration, which is out of scope for this adapter.
        throw new \RuntimeException(
            'Rollback is not supported by DoctrineMigrationRunner for raw SQL DDL migrations. '
            . 'MySQL DDL implicitly commits and cannot be transactionally rolled back. '
            . 'Manual intervention is required.'
        );
    }

    /** @return array<string, string> version => executed_at */
    private function getAppliedVersions(): array
    {
        try {
            $rows = $this->dbal->fetchAllAssociative(
                "SELECT version, executed_at FROM " . self::TRACKING_TABLE
            );
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row['version']] = $row['executed_at'];
        }

        return $result;
    }

    /** @return array<int, string> */
    private function getPendingVersions(?string $targetVersion): array
    {
        $applied = $this->getAppliedVersions();
        $pending = [];

        foreach (array_keys($this->migrations) as $version) {
            if (isset($applied[$version])) {
                continue;
            }

            if ($targetVersion !== null && $version > $targetVersion) {
                break;
            }

            $pending[] = $version;
        }

        return $pending;
    }
}
