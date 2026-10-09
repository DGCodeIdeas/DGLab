<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus};
use SovereignStack\Spoke\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\MySQLCourseRepository;

/**
 * Regression test for DBAL contract drift (Increment 1 of LMS MVP).
 *
 * Verifies that MySQLCourseRepository uses the ACTUAL ConnectionInterface API
 * (prepare/execute) — NOT the non-existent fetchOne()/execute() methods that
 * caused the contract drift.
 *
 * Per SAAI directive: "Do not expand the public DBAL interface merely to
 * accommodate an LMS repository that uses nonexistent methods."
 */
final class MySQLCourseRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLCourseRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLCourseRepository($this->connection);
    }

    public function testImplementsCourseRepositoryInterface(): void
    {
        self::assertInstanceOf(CourseRepositoryInterface::class, $this->repository);
    }

    public function testGetUsesPrepareNotFetchOne(): void
    {
        $id = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => (string) $id]);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false); // No course found

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM lms_courses WHERE id = :id')
            ->willReturn($stmt);

        // fetchOne() does not exist on ConnectionInterface — if this test
        // passes, the repository is using prepare() correctly.
        $result = $this->repository->get($id);

        self::assertNull($result);
    }

    public function testGetBySlugUsesPrepareNotFetchOne(): void
    {
        $slug = CourseSlug::fromString('test-course');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['slug' => 'test-course']);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM lms_courses WHERE slug = :slug')
            ->willReturn($stmt);

        $result = $this->repository->getBySlug($slug);

        self::assertNull($result);
    }

    public function testSaveNewCourseUsesPrepareForInsert(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
            description: 'Test description',
        );

        // First call: get() to check existence → returns null (new course)
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn(false); // Not found → insert

        // Second call: prepare for INSERT
        $stmtInsert = $this->createMock(\PDOStatement::class);
        $stmtInsert->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) {
                return $params['title'] === 'Test Course'
                    && $params['slug'] === 'test-course'
                    && $params['status'] === 'draft';
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtInsert);

        $this->repository->save($course);
    }

    public function testSaveExistingCourseUsesPrepareForUpdate(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Updated Title'),
            slug: CourseSlug::fromString('test-course'),
        );
        $course->publish(); // Change state so it's different from default

        // First call: get() to check existence → returns the course (exists)
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn([
            'id'          => (string) $course->id(),
            'title'       => 'Old Title',
            'slug'        => 'test-course',
            'description' => null,
            'status'      => 'draft',
            'created_at'  => '2026-01-01 00:00:00',
            'updated_at'  => '2026-01-01 00:00:00',
        ]);

        // Second call: prepare for UPDATE
        $stmtUpdate = $this->createMock(\PDOStatement::class);
        $stmtUpdate->expects($this->once())
            ->method('execute')
            ->with(self::callback(function ($params) {
                return $params['status'] === 'published'; // Verify the updated state
            }));

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtUpdate);

        $this->repository->save($course);
    }

    public function testDeleteUsesPrepareNotExecute(): void
    {
        $id = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id' => (string) $id]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM lms_courses WHERE id = :id')
            ->willReturn($stmt);

        // execute() with params does not exist on ConnectionInterface —
        // if this test passes, the repository is using prepare() correctly.
        $this->connection->expects($this->never())
            ->method('exec'); // exec() takes only SQL string, no params

        $this->repository->delete($id);
    }

    public function testGetReturnsHydratedCourseWhenFound(): void
    {
        $id = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id'          => (string) $id,
            'title'       => 'Test Course',
            'slug'        => 'test-course',
            'description' => 'A test',
            'status'      => 'published',
            'created_at'  => '2026-01-01 00:00:00',
            'updated_at'  => '2026-01-02 00:00:00',
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->get($id);

        self::assertNotNull($result);
        self::assertSame('Test Course', (string) $result->title());
        self::assertSame('test-course', (string) $result->slug());
        self::assertSame(CourseStatus::Published, $result->status());
    }
}
