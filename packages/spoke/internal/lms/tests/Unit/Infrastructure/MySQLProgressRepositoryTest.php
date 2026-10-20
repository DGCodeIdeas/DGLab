<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\Repository\ProgressRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\MySQLProgressRepository;

/**
 * Tests for MySQLProgressRepository (Increment 3 PR-A2, revised per SAAI review).
 *
 * Revised per SAAI Findings 3 + 4:
 *   - Finding 3: hydrate() throws on NULL started_at (no manufactured timestamp)
 *   - Finding 4: save() uses atomic MySQL upsert (INSERT ... ON DUPLICATE KEY UPDATE)
 */
final class MySQLProgressRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLProgressRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLProgressRepository($this->connection);
    }

    public function testImplementsProgressRepositoryInterface(): void
    {
        self::assertInstanceOf(ProgressRepositoryInterface::class, $this->repository);
    }

    public function testGetUsesPrepareWithCompositeKey(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with(['enrollment_id' => (string) $enrollmentId, 'module_id' => (string) $moduleId]);
        $stmt->method('fetch')->willReturn(false);

        $this->connection->expects($this->once())->method('prepare')->willReturn($stmt);

        self::assertNull($this->repository->get($enrollmentId, $moduleId));
    }

    public function testGetReturnsHydratedProgressWhenFound(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $moduleId,
            'status'        => 'in_progress',
            'started_at'    => '2026-10-20 12:00:00.000000',
            'completed_at'  => null,
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->get($enrollmentId, $moduleId);

        self::assertNotNull($result);
        self::assertSame(ModuleStatus::InProgress, $result->status());
        self::assertNull($result->completedAt());
    }

    // Per SAAI Finding 3: hydrate() must throw on NULL started_at
    public function testGetThrowsOnNullStartedAt(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $moduleId,
            'status'        => 'not_started',
            'started_at'    => null, // NULL — data inconsistency
            'completed_at'  => null,
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data inconsistency: lms_progress row has NULL started_at');

        $this->repository->get($enrollmentId, $moduleId);
    }

    public function testFindByEnrollmentReturnsOrderedArray(): void
    {
        $enrollmentId = EnrollmentId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['enrollment_id' => (string) $enrollmentId, 'module_id' => 'mod-1', 'status' => 'completed', 'started_at' => '2026-10-20 10:00:00.000000', 'completed_at' => '2026-10-20 11:00:00.000000'],
            ['enrollment_id' => (string) $enrollmentId, 'module_id' => 'mod-2', 'status' => 'in_progress', 'started_at' => '2026-10-20 12:00:00.000000', 'completed_at' => null],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByEnrollment($enrollmentId);

        self::assertCount(2, $result);
        self::assertSame(ModuleStatus::Completed, $result[0]->status());
        self::assertSame(ModuleStatus::InProgress, $result[1]->status());
    }

    public function testFindByEnrollmentReturnsEmptyArrayWhenNoProgress(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $this->connection->method('prepare')->willReturn($stmt);

        self::assertSame([], $this->repository->findByEnrollment(EnrollmentId::generate()));
    }

    // Per SAAI Finding 4: save() uses atomic upsert (single prepare, no check-then-insert/update)
    public function testSaveUsesAtomicUpsert(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();
        $progress = new Progress($moduleId, ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with(self::callback(function ($params) use ($enrollmentId, $moduleId) {
                return $params['enrollment_id'] === (string) $enrollmentId
                    && $params['module_id'] === (string) $moduleId
                    && $params['status'] === 'in_progress';
            }));

        // Only ONE prepare call (not two like the prior check-then-insert pattern)
        $this->connection->expects($this->once())->method('prepare')
            ->willReturn($stmt);

        $this->repository->save($progress, $enrollmentId);
    }

    // Per SAAI Finding 4: verify the SQL contains ON DUPLICATE KEY UPDATE
    public function testSaveSqlContainsOnDuplicateKeyUpdate(): void
    {
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->connection->expects($this->once())->method('prepare')
            ->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->repository->save(
            new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null),
            EnrollmentId::generate(),
        );

        self::assertStringContainsStringIgnoringCase('ON DUPLICATE KEY UPDATE', $capturedSql);
    }

    public function testDeleteUsesPrepareWithCompositeKey(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with(['enrollment_id' => (string) $enrollmentId, 'module_id' => (string) $moduleId]);

        $this->connection->expects($this->once())->method('prepare')->willReturn($stmt);
        $this->connection->expects($this->never())->method('exec');

        $this->repository->delete($enrollmentId, $moduleId);
    }
}
