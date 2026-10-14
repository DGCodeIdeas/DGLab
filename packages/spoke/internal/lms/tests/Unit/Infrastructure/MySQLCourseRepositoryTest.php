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

    // --- findPublished: regression coverage for Increment 3 ---

    public function testFindPublishedUsesPrepareNotQuery(): void
    {
        // findPublished must use prepare() + bound values, NOT ConnectionInterface::query().
        // ConnectionInterface::query() takes a raw SQL string with no parameter binding
        // and is therefore unsafe for any query that includes user-controlled limit/offset.
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        // query() must NEVER be called for findPublished — it has no parameter binding.
        $this->connection->expects($this->never())->method('query');

        $result = $this->repository->findPublished();

        self::assertSame([], $result);
    }

    public function testFindPublishedBindsStatusLimitOffset(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        // Three bindValue calls expected: status (string), limit (int), offset (int).
        $stmt->expects($this->exactly(3))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value, $type) use (&$bindings) {
                $bindings[$param] = ['value' => $value, 'type' => $type];
                return true;
            });
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $this->repository->findPublished(limit: 10, offset: 5);

        // Assert each binding has the correct value AND the correct PDO type.
        // The status MUST be the string 'published' (CourseStatus::Published->value).
        // The limit and offset MUST be bound as PDO::PARAM_INT, not PARAM_STR —
        // otherwise PDO's emulation mode would quote them and MySQL would reject
        // them as strings in LIMIT/OFFSET positions.
        self::assertSame('published', $bindings[':status']['value']);
        self::assertSame(\PDO::PARAM_STR, $bindings[':status']['type']);

        self::assertSame(10, $bindings[':limit']['value']);
        self::assertSame(\PDO::PARAM_INT, $bindings[':limit']['type']);

        self::assertSame(5, $bindings[':offset']['value']);
        self::assertSame(\PDO::PARAM_INT, $bindings[':offset']['type']);
    }

    public function testFindPublishedClampsLimitAbove100(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturnCallback(function ($param, $value) use (&$limitBinding) {
            if ($param === ':limit') { $limitBinding = $value; }
            return true;
        });
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $this->repository->findPublished(limit: 5000);

        // Limit MUST be clamped to 100 to prevent pathological result sets.
        self::assertSame(100, $limitBinding);
    }

    public function testFindPublishedClampsLimitBelow1(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturnCallback(function ($param, $value) use (&$limitBinding) {
            if ($param === ':limit') { $limitBinding = $value; }
            return true;
        });
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $this->repository->findPublished(limit: 0);
        self::assertSame(1, $limitBinding, 'limit=0 must clamp to 1');

        $this->repository->findPublished(limit: -50);
        self::assertSame(1, $limitBinding, 'limit=-50 must clamp to 1');
    }

    public function testFindPublishedClampsNegativeOffsetTo0(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturnCallback(function ($param, $value) use (&$offsetBinding) {
            if ($param === ':offset') { $offsetBinding = $value; }
            return true;
        });
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->method('prepare')->willReturn($stmt);

        $this->repository->findPublished(offset: -10);
        self::assertSame(0, $offsetBinding, 'negative offset must clamp to 0');
    }

    public function testFindPublishedReturnsEmptyArrayWhenNoCourses(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]); // No rows

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findPublished();

        self::assertSame([], $result);
    }

    public function testFindPublishedReturnsHydratedCoursesOrderedByUpdatedAtDesc(): void
    {
        $id1 = CourseId::generate();
        $id2 = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        // The repository hydrates whatever rows the DB returns; the SQL ORDER BY
        // is what enforces ordering at runtime. The test here verifies hydration
        // preserves the row order returned by fetchAll (the DB's job is to order).
        $stmt->method('fetchAll')->willReturn([
            [
                'id'          => (string) $id1,
                'title'       => 'Newer Published',
                'slug'        => 'newer-published',
                'description' => null,
                'status'      => 'published',
                'created_at'  => '2026-01-01 00:00:00',
                'updated_at'  => '2026-10-13 12:00:00',
            ],
            [
                'id'          => (string) $id2,
                'title'       => 'Older Published',
                'slug'        => 'older-published',
                'description' => 'Older course',
                'status'      => 'published',
                'created_at'  => '2026-01-01 00:00:00',
                'updated_at'  => '2026-09-01 00:00:00',
            ],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findPublished();

        self::assertCount(2, $result);
        self::assertInstanceOf(Course::class, $result[0]);
        self::assertInstanceOf(Course::class, $result[1]);
        self::assertSame('Newer Published', (string) $result[0]->title());
        self::assertSame('Older Published', (string) $result[1]->title());
        self::assertSame(CourseStatus::Published, $result[0]->status());
        self::assertSame(CourseStatus::Published, $result[1]->status());
    }

    public function testFindPublishedOnlySelectsRowsWhereStatusEqualsPublished(): void
    {
        // The SQL string passed to prepare() MUST filter on status = 'published'.
        // The repository MUST NOT rely on post-hydration filtering (that would
        // pull Draft and Archived rows out of the DB and discard them in PHP,
        // leaking unpublished content into memory and into the response if a
        // caller forgets to filter).
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->repository->findPublished();

        // The SQL MUST contain "status = :status" or equivalent predicate.
        // :status is bound to 'published' (verified in testFindPublishedBindsStatusLimitOffset).
        self::assertStringContainsStringIgnoringCase("status", $capturedSql);
        self::assertStringContainsString(":status", $capturedSql);
    }
}
