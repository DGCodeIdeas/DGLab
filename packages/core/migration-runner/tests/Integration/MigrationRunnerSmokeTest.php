<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Connection;
use SovereignStack\Core\MigrationRunner\Locking\Gate;
use SovereignStack\Core\MigrationRunner\Locking\MigrationLock;
use SovereignStack\Core\MigrationRunner\Recovery\ReconciliationResult;
use SovereignStack\Core\MigrationRunner\Runner\MigrationRunner;
use SovereignStack\Core\MigrationRunner\Sequence\GseqAllocator;

/**
 * End-to-end smoke test for the migration runner.
 *
 * Validates the critical-path behaviors the owner listed:
 *   - schema installation (installSchema against real MySQL)
 *   - SQL parsing + chain validation (discoverMigrations)
 *   - event sequencing (gseq counter initializes + monotonic allocation)
 *   - locking (gate S/X; L1[M] advisory locks acquire + release)
 *   - reconciliation (reconcileAll on empty state — no-op outcomes)
 *
 * This test does NOT exercise the full apply/rollback protocol — that is
 * Test6eTest's job (the corrected 13-step gate-connection-loss scenario).
 * This smoke test proves the infrastructure compiles, wires, and connects.
 *
 * @group integration
 * @group mysql-required
 * @package SovereignStack\Core\MigrationRunner\Tests\Integration
 */
final class MigrationRunnerSmokeTest extends TestCase
{
    private ?Connection $workConn = null;
    private ?Connection $gateConn = null;
    private string $schemaName = 'dglab_smoke';

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $name = getenv('TEST_DB_NAME') ?: 'dglab_test';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $pass = getenv('TEST_DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $this->workConn = new Connection($dsn, $user, $pass);
            $this->gateConn = new Connection($dsn, $user, $pass);
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                "MySQL not available for integration test: " . $e->getMessage()
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->workConn !== null) {
            // Clean up smoke-test artifacts (idempotent — tables may or may not exist).
            $this->dropSafely("migration_attempt_event");
            $this->dropSafely("migration_attempt");
            $this->dropSafely("migration_state");
            $this->dropSafely("migration");
            $this->dropSafely("migration_gate");
            $this->dropSafely("migration_gseq_counter");
            $this->dropSafely("operator_event");
            $this->dropSafely("smoke_test_users");
            $this->workConn = null;
        }
        $this->gateConn = null;
    }

    private function dropSafely(string $table): void
    {
        try {
            $this->workConn->exec("DROP TABLE IF EXISTS `{$table}`");
        } catch (\Throwable) {
            // Best-effort cleanup.
        }
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testInstallSchemaCreatesAllExpectedTables(): void
    {
        $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
        $runner->installSchema();

        $stmt = $this->workConn->query("SHOW TABLES");
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $tableNames = array_map('strval', $rows);

        $this->assertContains('migration_gseq_counter', $tableNames);
        $this->assertContains('migration_gate', $tableNames);
        $this->assertContains('migration', $tableNames);
        $this->assertContains('migration_attempt', $tableNames);
        $this->assertContains('migration_attempt_event', $tableNames);
        $this->assertContains('migration_state', $tableNames);
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testGseqCounterInitializesToZero(): void
    {
        $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
        $runner->installSchema();

        $gseq = new GseqAllocator($this->workConn);
        $gseq->ensureCounterInitialized();

        $this->assertSame(0, $gseq->getCurrentValue(), 'fresh gseq counter should be 0');
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testGateAcquiresSharedAndExclusiveModes(): void
    {
        $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
        $runner->installSchema();

        $schemaHash = substr(hash('sha256', $this->schemaName), 0, 8);
        $gate = new Gate($schemaHash, $this->schemaName, $this->gateConn);
        $gate->ensureRowExists();

        // Shared mode (per-migration operations).
        $gate->acquireShared();
        $this->assertTrue($gate->isHeld(), 'gate should be held after acquireShared');
        $this->assertSame(Gate::MODE_SHARED, $gate->getMode());
        $gate->release();
        $this->assertFalse($gate->isHeld(), 'gate released after release()');

        // Exclusive mode (global reconciliation sweep).
        $gate->acquireExclusive();
        $this->assertTrue($gate->isHeld());
        $this->assertSame(Gate::MODE_EXCLUSIVE, $gate->getMode());
        $gate->release();
        $this->assertFalse($gate->isHeld());
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testMigrationLockAcquiresAndReleasesL1(): void
    {
        $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
        $runner->installSchema();

        $schemaHash = substr(hash('sha256', $this->schemaName), 0, 8);
        $lock = new MigrationLock($schemaHash, 'smoke_test_migration', $this->workConn);

        $this->assertTrue($lock->acquire(), 'L1[M] advisory lock should acquire on empty state');
        $this->assertTrue($lock->isHeld());

        $lock->release();  // void — no return value to assert
        $this->assertFalse($lock->isHeld(), 'L1[M] released');

        // Re-acquire after release (no leak).
        $this->assertTrue($lock->acquire(), 'L1[M] should re-acquire after release');
        $this->assertTrue($lock->isHeld());
        $lock->release();
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testDiscoverMigrationsParsesAndValidatesChain(): void
    {
        $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
        $runner->installSchema();

        $tmpDir = sys_get_temp_dir() . '/mig_smoke_' . uniqid();
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . '/001_create_users_table.sql', <<<'SQL'
-- @META
migration_id: 001_create_users_table
predecessor: null
description: Create users table
-- @END

-- @STMT BEGIN upgrade
CREATE TABLE smoke_test_users (
    id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
);
-- @STMT END
SQL);

        file_put_contents($tmpDir . '/002_add_email_index.sql', <<<'SQL'
-- @META
migration_id: 002_add_email_index
predecessor: 001_create_users_table
description: Add unique email index
-- @END

-- @STMT BEGIN upgrade
CREATE UNIQUE INDEX idx_smoke_users_email ON smoke_test_users (email);
-- @STMT END
SQL);

        try {
            // Should not throw — chain is valid linear (001 genesis → 002).
            $runner->discoverMigrations($tmpDir);
            $this->assertTrue(true, 'discoverMigrations parsed + validated the chain without throwing');
        } finally {
            array_map('unlink', glob($tmpDir . '/*.sql') ?: []);
            rmdir($tmpDir);
        }
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testReconcileAllOnEmptyStateReturnsNoOpResults(): void
    {
        $tmpDir = sys_get_temp_dir() . '/mig_reconcile_' . uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir . '/001.sql', <<<'SQL'
-- @META
migration_id: 001
predecessor: null
description: genesis
-- @END

-- @STMT BEGIN upgrade
SELECT 1;
-- @STMT END
SQL);
        file_put_contents($tmpDir . '/002.sql', <<<'SQL'
-- @META
migration_id: 002
predecessor: 001
description: second
-- @END

-- @STMT BEGIN upgrade
SELECT 1;
-- @STMT END
SQL);

        try {
            $runner = new MigrationRunner($this->schemaName, $this->workConn, $this->gateConn);
            $runner->installSchema();
            $runner->discoverMigrations($tmpDir);

            // Reconciliation on empty state — no attempts, no events, no state.
            // Should return results for both migrations, each NO_OP.
            $results = $runner->reconcileAll();

            $this->assertCount(2, $results, 'reconcileAll should return a result per migration');
            $this->assertArrayHasKey('001', $results);
            $this->assertArrayHasKey('002', $results);

            // Both should be NO_OP (no state to reconcile).
            $this->assertSame(
                ReconciliationResult::NO_OP,
                $results['001']->outcome,
                'reconcile on empty state should be NO_OP, got: ' . $results['001']->outcome
            );
            $this->assertSame(
                ReconciliationResult::NO_OP,
                $results['002']->outcome,
                'reconcile on empty state should be NO_OP, got: ' . $results['002']->outcome
            );
        } finally {
            array_map('unlink', glob($tmpDir . '/*.sql') ?: []);
            rmdir($tmpDir);
        }
    }
}
