<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Lms\Application\Command\AddModuleCommand;
use SovereignStack\Spoke\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus, Module};
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle, ModuleId};

/**
 * Tests for CourseApplicationService module operations (Increment 2 of LMS MVP).
 *
 * Per SAAI Increment 2 requirements:
 *   - Modules can only be added/removed while course is in Draft.
 *   - Authorization in the application layer.
 *   - Transactions for multi-record operations.
 *   - Transaction rollback on persistence failure.
 */
final class CourseApplicationServiceModuleTest extends TestCase
{
    private CourseRepositoryInterface $courses;
    private ModuleRepositoryInterface $modules;
    private IdentityInterface $identity;
    private ConnectionInterface $connection;
    private CourseApplicationService $service;
    private UserId $adminId;

    protected function setUp(): void
    {
        $this->courses = $this->createMock(CourseRepositoryInterface::class);
        $this->modules = $this->createMock(ModuleRepositoryInterface::class);
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->connection = $this->createMock(ConnectionInterface::class);

        $this->service = new CourseApplicationService(
            $this->courses,
            $this->modules,
            $this->identity,
            $this->connection,
        );

        $this->adminId = UserId::generate();

        // Default: identity always authorizes
        $this->identity->method('hasAnyRole')->willReturn(true);
    }

    // --- addModule: Draft course (success) ---

    public function testAddModuleToDraftCourseSucceeds(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
        );

        $this->courses->method('get')->willReturn($course);
        $this->modules->method('findByCourse')->willReturn([]);
        $this->modules->method('get')->willReturn(null);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $cmd = new AddModuleCommand(
            courseId: $course->id(),
            addedBy: $this->adminId,
            title: 'Introduction',
            content: 'Welcome to the course!',
        );

        $moduleId = $this->service->addModule($cmd);

        self::assertFalse($moduleId->equals(ModuleId::generate())); // Not empty
    }

    // --- addModule: Published course (rejected) ---

    public function testAddModuleToPublishedCourseThrows(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Published Course'),
            slug: CourseSlug::fromString('published-course'),
        );
        $course->publish();

        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add modules to published course');

        $cmd = new AddModuleCommand(
            courseId: $course->id(),
            addedBy: $this->adminId,
            title: 'Should Fail',
        );

        $this->service->addModule($cmd);
    }

    // --- addModule: Archived course (rejected) ---

    public function testAddModuleToArchivedCourseThrows(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Archived Course'),
            slug: CourseSlug::fromString('archived-course'),
        );
        $course->publish();
        $course->archive();

        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add modules to archived course');

        $cmd = new AddModuleCommand(
            courseId: $course->id(),
            addedBy: $this->adminId,
            title: 'Should Fail',
        );

        $this->service->addModule($cmd);
    }

    // --- addModule: Transaction rollback on persistence failure ---

    public function testAddModuleRollsBackOnPersistenceFailure(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
        );

        $this->courses->method('get')->willReturn($course);
        $this->modules->method('findByCourse')->willReturn([]);
        $this->modules->method('get')->willReturn(null);

        // Module save throws
        $this->modules->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $cmd = new AddModuleCommand(
            courseId: $course->id(),
            addedBy: $this->adminId,
            title: 'Module',
        );

        $this->service->addModule($cmd);
    }

    // --- addModule: Authorization enforcement ---

    public function testAddModuleRejectsUnauthorizedUser(): void
    {
        $learnerId = UserId::generate();

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);

        $this->service = new CourseApplicationService(
            $this->courses,
            $this->modules,
            $this->identity,
            $this->connection,
        );

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized');

        $cmd = new AddModuleCommand(
            courseId: CourseId::generate(),
            addedBy: $learnerId,
            title: 'Unauthorized',
        );

        $this->service->addModule($cmd);
    }

    // --- removeModule: Draft course (success) ---

    public function testRemoveModuleFromDraftCourseSucceeds(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
        );

        $moduleId = ModuleId::generate();
        $module = Module::restoreFromPersistence(
            id: $moduleId,
            courseId: $course->id(),
            title: 'To Remove',
            sortOrder: 0,
            content: null,
            createdAt: new \DateTimeImmutable(),
        );

        // Need to add the module to the course first so removeModule finds it
        $course->addModule($moduleId, 'To Remove', 0);

        $this->modules->method('get')->willReturn($module);
        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->modules->expects($this->once())->method('delete')->with($moduleId);

        $this->service->removeModule($moduleId, $this->adminId);
    }

    // --- removeModule: Published course (rejected) ---

    public function testRemoveModuleFromPublishedCourseThrows(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Published Course'),
            slug: CourseSlug::fromString('published-course'),
        );
        $course->publish();

        $moduleId = ModuleId::generate();
        $module = Module::restoreFromPersistence(
            id: $moduleId,
            courseId: $course->id(),
            title: 'Existing Module',
            sortOrder: 0,
            content: null,
            createdAt: new \DateTimeImmutable(),
        );

        $this->modules->method('get')->willReturn($module);
        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove modules from published course');

        $this->service->removeModule($moduleId, $this->adminId);
    }

    // --- publishCourse: Uses transaction ---

    public function testPublishCourseUsesTransaction(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('To Publish'),
            slug: CourseSlug::fromString('to-publish'),
        );

        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->service->publishCourse($course->id(), $this->adminId);

        self::assertSame(CourseStatus::Published, $course->status());
    }

    // --- publishCourse: Transaction rollback on failure ---

    public function testPublishCourseRollsBackOnFailure(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('To Publish'),
            slug: CourseSlug::fromString('to-publish'),
        );

        $this->courses->method('get')->willReturn($course);
        $this->courses->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->service->publishCourse($course->id(), $this->adminId);
    }
}
