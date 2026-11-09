<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Migration\DoctrineMigrationRunner;
use SovereignStack\Core\Database\Migration\MigrationRunnerInterface;

final class DoctrineMigrationRunnerTest extends TestCase
{
    private string $migrationsPath;

    protected function setUp(): void
    {
        // Core/Database/tests/Unit/Migration/ → 5 levels up to repo root
        $this->migrationsPath = __DIR__ . '/../../../../../Spokes/Internal/Lms/migrations';
    }

    public function testImplementsInterface(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $this->assertInstanceOf(MigrationRunnerInterface::class, $runner);
    }

    public function testStatusReturnsAllMigrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $status = $runner->status();

        $this->assertNotEmpty($status);
        $this->assertCount(9, $status);

        foreach ($status as $m) {
            $this->assertArrayHasKey('version', $m);
            $this->assertArrayHasKey('description', $m);
            $this->assertArrayHasKey('executed', $m);
            $this->assertArrayHasKey('executedAt', $m);
        }
    }

    public function testFreshInstallAppliesAllMigrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $applied = $runner->migrate();

        $this->assertCount(9, $applied);
    }

    public function testIdempotentRerun(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $applied = $runner->migrate();

        $this->assertEmpty($applied, 'Re-run should apply no new migrations');
    }

    public function testRollbackThrows(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback is not supported');

        $runner->rollback();
    }
}
