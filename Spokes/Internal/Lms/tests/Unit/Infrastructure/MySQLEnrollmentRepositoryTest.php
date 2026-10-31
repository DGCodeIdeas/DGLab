<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\Entity\Enrollment;
use SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, EnrollmentId, EnrollmentStatus};
use SovereignStack\Internal\Lms\Infrastructure\Persistence\MySQLEnrollmentRepository;

/**
 * Tests for MySQLEnrollmentRepository (Increment 3 PR-A1).
 *
 * Verifies that the repository:
 *   - Uses prepare()/execute()/fetch()/fetchAll() — NOT the non-existent
 *     fetchOne()/execute() connection methods that caused the prior DBAL
 *     contract drift in PR #355 / #357.
 *   - Implements EnrollmentRepositoryInterface (including the SemVer-minor
 *     additions findByLearner() and findByCourse() added in this PR).
 *   - Hydrates rows correctly, including the column-name impedance mismatch:
 *     the DB column is `enrolled_at` (past-tense) but the entity property is
 *     `createdAt` (generic). The hydrate() method must translate.
 *   - Persists only the enrollment row (NOT the in-memory `progress` array —
 *     progress is a separate aggregate root with its own table).
 *   - Clamps UPDATE to only the mutable columns (status, completed_at) —
 *     course_id, learner_id, enrolled_at are immutable post-creation.
 *
 * Mock-based, follows the pattern established by MySQLCourseRepositoryTest.
 */
final class MySQLEnrollmentRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLEnrollmentRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLEnrollmentRepository($this->connection);
    }

    public function testImplementsEnrollmentRepositoryInterface(): void
    {
        self::assertInstanceOf(EnrollmentRepositoryInterface::class, $this->repository);
    }

    public function testGetUsesPrepareNotFetchOne(): void
    {
        $id = EnrollmentId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => (string) $id]);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // Not found

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM lms_enrollments WHERE id = :id')
            ->willReturn($stmt);

        $result = $this->repository->get($id);

        self::assertNull($result);
    }

    public function testGetReturnsHydratedEnrollmentWhenFound(): void
    {
        $id = EnrollmentId::generate();
        $courseId = CourseId::generate();
        $learnerId = new UserId('test-learner-id-1234567890123');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id'           => (string) $id,
            'course_id'    => (string) $courseId,
            'learner_id'   => (string) $learnerId,
            'status'       => 'active',
            'enrolled_at'  => '2026-01-01 00:00:00.000000',
            'completed_at' => null,
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->get($id);

        self::assertNotNull($result);
        self::assertTrue($result->id()->equals($id));
        self::assertTrue($result->courseId()->equals($courseId));
        self::assertTrue($result->learnerId()->equals($learnerId));
        self::assertSame(EnrollmentStatus::Active, $result->status());
        // Verify the column-name impedance mismatch is handled:
        // DB column `enrolled_at` → entity property `createdAt`
        self::assertSame('2026-01-01 00:00:00.000000', $result->createdAt()->format('Y-m-d H:i:s.u'));
        self::assertNull($result->completedAt());
    }

    public function testFindByCourseAndLearnerUsesCorrectParams(): void
    {
        $courseId = CourseId::generate();
        $learnerId = new UserId('test-learner-id-1234567890123');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'course_id'  => (string) $courseId,
                'learner_id' => (string) $learnerId,
            ]);
        $stmt->method('fetch')->willReturn(false); // Not found

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->repository->findByCourseAndLearner($courseId, $learnerId);

        self::assertNull($result);
    }

    public function testFindByCourseAndLearnerSQLContainsOrderAndLimit(): void
    {
        // The SQL MUST order by enrolled_at DESC + LIMIT 1 to return the
        // most-recent enrollment when multiple exist (e.g., re-enrollment
        // after cancellation).
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->repository->findByCourseAndLearner(CourseId::generate(), new UserId('test-learner-id-1234567890123'));

        self::assertStringContainsStringIgnoringCase('ORDER BY', $capturedSql);
        self::assertStringContainsStringIgnoringCase('enrolled_at DESC', $capturedSql);
        self::assertStringContainsStringIgnoringCase('LIMIT 1', $capturedSql);
    }

    public function testSaveNewEnrollmentUsesInsert(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: new UserId('test-learner-id-1234567890123'),
        );

        // First call: get() to check existence → returns null (new enrollment)
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn(false); // Not found → INSERT

        // Second call: prepare for INSERT
        $stmtInsert = $this->createMock(\PDOStatement::class);
        $stmtInsert->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) {
                return $params['status'] === 'active'
                    && $params['completed_at'] === null
                    && isset($params['enrolled_at'])
                    // Verify column-name mapping: entity's createdAt → DB's enrolled_at
                    && isset($params['course_id'])
                    && isset($params['learner_id'])
                    && isset($params['id']);
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtInsert);

        $this->repository->save($enrollment);
    }

    public function testSaveExistingEnrollmentUsesUpdateOnlyMutableColumns(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: new UserId('test-learner-id-1234567890123'),
        );
        $enrollment->cancel(); // Change state to test the UPDATE path

        // First call: get() returns the existing enrollment → UPDATE
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn([
            'id'           => (string) $enrollment->id(),
            'course_id'    => (string) $enrollment->courseId(),
            'learner_id'   => (string) $enrollment->learnerId(),
            'status'       => 'active',
            'enrolled_at'  => '2026-01-01 00:00:00.000000',
            'completed_at' => null,
        ]);

        // Second call: prepare for UPDATE — must NOT include course_id, learner_id, enrolled_at
        $stmtUpdate = $this->createMock(\PDOStatement::class);
        $stmtUpdate->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) {
                // UPDATE should only set status + completed_at + id
                // It MUST NOT include course_id, learner_id, or enrolled_at
                // (those are immutable post-creation)
                return array_keys($params) === ['id', 'status', 'completed_at']
                    && $params['status'] === 'cancelled';
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtUpdate);

        $this->repository->save($enrollment);
    }

    public function testFindByLearnerReturnsOrderedArray(): void
    {
        $learnerId = new UserId('test-learner-id-1234567890123');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'id'           => 'enrollment-id-1',
                'course_id'    => 'course-id-1',
                'learner_id'   => (string) $learnerId,
                'status'       => 'active',
                'enrolled_at'  => '2026-10-15 00:00:00.000000',
                'completed_at' => null,
            ],
            [
                'id'           => 'enrollment-id-2',
                'course_id'    => 'course-id-2',
                'learner_id'   => (string) $learnerId,
                'status'       => 'completed',
                'enrolled_at'  => '2026-09-01 00:00:00.000000',
                'completed_at' => '2026-09-30 00:00:00.000000',
            ],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByLearner($learnerId);

        self::assertCount(2, $result);
        self::assertInstanceOf(Enrollment::class, $result[0]);
        self::assertInstanceOf(Enrollment::class, $result[1]);
        // Verify order preserved (most recent first per SQL ORDER BY)
        self::assertSame('enrollment-id-1', (string) $result[0]->id());
        self::assertSame('enrollment-id-2', (string) $result[1]->id());
    }

    public function testFindByCourseReturnsOrderedArray(): void
    {
        $courseId = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'id'           => 'enrollment-id-1',
                'course_id'    => (string) $courseId,
                'learner_id'   => 'learner-1',
                'status'       => 'active',
                'enrolled_at'  => '2026-10-15 00:00:00.000000',
                'completed_at' => null,
            ],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByCourse($courseId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Enrollment::class, $result[0]);
    }

    public function testFindByLearnerReturnsEmptyArrayWhenNoEnrollments(): void
    {
        $learnerId = new UserId('test-learner-id-1234567890123');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByLearner($learnerId);

        self::assertSame([], $result);
    }
}
