<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Assert;
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
        Assert::assertInstanceOf(MigrationRunnerInterface::class, $runner);
    }

    public function testStatusReturnsAllMigrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $status = $runner->status();

        Assert::assertNotEmpty($status);
        Assert::assertCount(9, $status);

        foreach ($status as $m) {
            Assert::assertArrayHasKey('version', $m);
            Assert::assertArrayHasKey('description', $m);
            Assert::assertArrayHasKey('executed', $m);
            Assert::assertArrayHasKey('executedAt', $m);
        }
    }

    public function testFreshInstallAppliesAllMigrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $applied = $runner->migrate();

        Assert::assertCount(9, $applied);
    }

    public function testIdempotentRerun(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);
        $applied = $runner->migrate();

        Assert::assertEmpty($applied, 'Re-run should apply no new migrations');
    }

    public function testRollbackThrows(): void
    {
        $runner = new DoctrineMigrationRunner($this->migrationsPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback is not supported');

        $runner->rollback();
    }
}
