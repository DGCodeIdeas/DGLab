<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Module;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\MySQLModuleRepository;

/**
 * Tests for MySQLModuleRepository (Increment 2 of LMS MVP).
 *
 * Verifies the repository uses the actual ConnectionInterface API
 * (prepare/execute) and correctly hydrates Module entities.
 */
final class MySQLModuleRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLModuleRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLModuleRepository($this->connection);
    }

    public function testGetReturnsNullWhenNotFound(): void
    {
        $id = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->connection->method('prepare')->willReturn($stmt);

        self::assertNull($this->repository->get($id));
    }

    public function testGetReturnsHydratedModule(): void
    {
        $id = ModuleId::generate();
        $courseId = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id'         => (string) $id,
            'course_id'  => (string) $courseId,
            'title'      => 'Introduction',
            'sort_order' => 0,
            'content'    => 'Welcome to the course!',
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->get($id);

        self::assertNotNull($result);
        self::assertSame('Introduction', $result->title());
        self::assertSame(0, $result->sortOrder());
        self::assertSame('Welcome to the course!', $result->content());
        self::assertTrue($result->id()->equals($id));
        self::assertTrue($result->courseId()->equals($courseId));
    }

    public function testFindByCourseReturnsOrderedModules(): void
    {
        $courseId = CourseId::generate();
        $id1 = ModuleId::generate();
        $id2 = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'id' => (string) $id1, 'course_id' => (string) $courseId,
                'title' => 'Module 1', 'sort_order' => 0,
                'content' => 'Content 1', 'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => (string) $id2, 'course_id' => (string) $courseId,
                'title' => 'Module 2', 'sort_order' => 1,
                'content' => 'Content 2', 'created_at' => '2026-01-02 00:00:00',
            ],
        ]);

        $this->connection->method('prepare')->willReturn($stmt);

        $result = $this->repository->findByCourse($courseId);

        self::assertCount(2, $result);
        self::assertSame('Module 1', $result[0]->title());
        self::assertSame(0, $result[0]->sortOrder());
        self::assertSame('Module 2', $result[1]->title());
        self::assertSame(1, $result[1]->sortOrder());
    }

    public function testSaveNewModuleUsesPrepareForInsert(): void
    {
        $module = Module::restoreFromPersistence(
            id: ModuleId::generate(),
            courseId: CourseId::generate(),
            title: 'New Module',
            sortOrder: 0,
            content: 'Some content',
            createdAt: new \DateTimeImmutable(),
        );

        // First call: get() → returns null (new module)
        $stmtGet = $this->createMock(\PDOStatement::class);
        $stmtGet->method('execute')->willReturn(true);
        $stmtGet->method('fetch')->willReturn(false);

        // Second call: prepare for INSERT
        $stmtInsert = $this->createMock(\PDOStatement::class);
        $stmtInsert->expects($this->once())->method('execute');

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtGet, $stmtInsert);

        $this->repository->save($module);
    }

    public function testDeleteUsesPrepare(): void
    {
        $id = ModuleId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['id' => (string) $id]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM lms_modules WHERE id = :id')
            ->willReturn($stmt);

        $this->repository->delete($id);
    }

    public function testDeleteByCourseUsesPrepare(): void
    {
        $courseId = CourseId::generate();

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['course_id' => (string) $courseId]);

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM lms_modules WHERE course_id = :course_id')
            ->willReturn($stmt);

        $this->repository->deleteByCourse($courseId);
    }
}
