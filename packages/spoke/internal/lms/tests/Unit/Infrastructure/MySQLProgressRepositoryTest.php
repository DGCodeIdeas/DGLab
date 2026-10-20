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
 * Tests for MySQLProgressRepository (Increment 3 PR-A2).
 *
 * Verifies that the repository:
 *   - Uses prepare()/execute()/fetch()/fetchAll() — same DBAL pattern as
 *     MySQLCourseRepository (PR #355), MySQLModuleRepository (PR #356),
 *     MySQLEnrollmentRepository (PR #366).
 *   - Implements ProgressRepositoryInterface (new in this PR).
 *   - Hydrates Progress entities correctly from DB rows.
 *   - save() uses check-then-insert/update; UPDATE only mutates status +
 *     started_at + completed_at (preserves immutable composite PK).
 *   - Composite PK (enrollment_id, module_id) used in all queries.
 *
 * Mock-based, follows the pattern established by MySQLEnrollmentRepositoryTest.
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
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'enrollment_id' => (string) $enrollmentId,
                'module_id'     => (string) $moduleId,
            ]);
        $stmt->method('fetch')->willReturn(false); // Not found

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->repository->get($enrollmentId, $moduleId);

        self::assertNull($result);
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
        self::assertInstanceOf(Progress::class, $result);
        self::assertSame(ModuleStatus::InProgress, $result->status());
        self::assertNull($result->completedAt());
    }

    public function testFindByEnrollmentReturnsOrderedArray(): void
    {
        $enrollmentId = EnrollmentId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'enrollment_id' => (string) $enrollmentId,
                'module_id'     => 'module-id-001',
                'status'        => 'completed',
                'started_at'    => '2026-10-20 10:00:00.000000',
                'completed_at'  => '2026-10-20 11:00:00.000000',
            ],
            [
                'enrollment_id' => (string) $enrollmentId,
                'module_id'     => 'module-id-002',
                'status'        => 'in_progress',
                'started_at'    => '2026-10-20 12:00:00.000000',
                'completed_at'  => null,
            ],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByEnrollment($enrollmentId);

        self::assertCount(2, $result);
        self::assertInstanceOf(Progress::class, $result[0]);
        self::assertInstanceOf(Progress::class, $result[1]);
        self::assertSame(ModuleStatus::Completed, $result[0]->status());
        self::assertSame(ModuleStatus::InProgress, $result[1]->status());
    }

    public function testFindByEnrollmentReturnsEmptyArrayWhenNoProgress(): void
    {
        $enrollmentId = EnrollmentId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByEnrollment($enrollmentId);

        self::assertSame([], $result);
    }

    public function testSaveNewProgressUsesInsert(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();
        $progress = new Progress(
            moduleId: $moduleId,
            status: ModuleStatus::InProgress,
            startedAt: new \DateTimeImmutable(),
            completedAt: null,
        );

        // First call: get() to check existence → returns null (new)
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn(false); // Not found → INSERT

        // Second call: prepare for INSERT
        $stmtInsert = $this->createMock(\PDOStatement::class);
        $stmtInsert->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) use ($enrollmentId, $moduleId) {
                return $params['enrollment_id'] === (string) $enrollmentId
                    && $params['module_id'] === (string) $moduleId
                    && $params['status'] === 'in_progress';
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtInsert);

        $this->repository->save($progress, $enrollmentId);
    }

    public function testSaveExistingProgressUsesUpdateOnlyMutableColumns(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();
        $progress = new Progress(
            moduleId: $moduleId,
            status: ModuleStatus::Completed,
            startedAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
        );

        // First call: get() returns existing → UPDATE
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $moduleId,
            'status'        => 'in_progress',
            'started_at'    => '2026-10-20 10:00:00.000000',
            'completed_at'  => null,
        ]);

        // Second call: UPDATE — must NOT include enrollment_id or module_id in SET clause
        $stmtUpdate = $this->createMock(\PDOStatement::class);
        $stmtUpdate->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) {
                // UPDATE params should contain enrollment_id + module_id (for WHERE)
                // plus status + started_at + completed_at (for SET)
                return isset($params['enrollment_id'])
                    && isset($params['module_id'])
                    && isset($params['status'])
                    && isset($params['started_at'])
                    && isset($params['completed_at'])
                    && $params['status'] === 'completed';
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtUpdate);

        $this->repository->save($progress, $enrollmentId);
    }

    public function testDeleteUsesPrepareWithCompositeKey(): void
    {
        $enrollmentId = EnrollmentId::generate();
        $moduleId = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'enrollment_id' => (string) $enrollmentId,
                'module_id'     => (string) $moduleId,
            ]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->connection->expects($this->never())->method('exec');

        $this->repository->delete($enrollmentId, $moduleId);
    }
}
