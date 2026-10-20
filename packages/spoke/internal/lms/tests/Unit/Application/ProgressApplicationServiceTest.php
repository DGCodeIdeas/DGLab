<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Spoke\Lms\Application\Service\ProgressApplicationService;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus, Enrollment, Module, Progress};
use SovereignStack\Spoke\Lms\Domain\Exception\{CannotRevertCompletedModuleException, EnrollmentNotFoundException};
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, EnrollmentRepositoryInterface, ModuleRepositoryInterface, ProgressRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle, EnrollmentId, ModuleId, ModuleStatus};

/**
 * Tests for ProgressApplicationService (Increment 3 PR-A2).
 *
 * Per SAAI design review 2026-10-20 (Issues A–E):
 *   - Issue A: lms_progress is canonical; service coordinates domain + persistence
 *   - Issue B: actor identity from trusted context (UserId $actor param, NOT in command)
 *   - Issue C: full validation chain — enrollment exists + Active, course Published,
 *     module belongs to course, state transition valid, caller authorized
 *   - Issue D: check-then-insert/update; idempotent same-status; forbidden
 *     completed→in_progress; transaction-wrapped
 *   - Issue E: mock tests verify orchestration, NOT real-DB behavior
 *
 * Mock-based, follows the pattern established by EnrollmentApplicationServiceTest.
 */
final class ProgressApplicationServiceTest extends TestCase
{
    private ProgressRepositoryInterface $progress;
    private EnrollmentRepositoryInterface $enrollments;
    private CourseRepositoryInterface $courses;
    private ModuleRepositoryInterface $modules;
    private IdentityInterface $identity;
    private ConnectionInterface $connection;
    private ProgressApplicationService $service;
    private UserId $learnerId;
    private UserId $adminId;

    protected function setUp(): void
    {
        $this->progress    = $this->createMock(ProgressRepositoryInterface::class);
        $this->enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $this->courses     = $this->createMock(CourseRepositoryInterface::class);
        $this->modules     = $this->createMock(ModuleRepositoryInterface::class);
        $this->identity    = $this->createMock(IdentityInterface::class);
        $this->connection  = $this->createMock(ConnectionInterface::class);

        $this->service = new ProgressApplicationService(
            $this->progress,
            $this->enrollments,
            $this->courses,
            $this->modules,
            $this->identity,
            $this->connection,
        );

        $this->learnerId = new UserId('test-learner-id-1234567890123');
        $this->adminId   = new UserId('test-admin-id-12345678901234');

        // Default: identity authorizes
        $this->identity->method('hasAnyRole')->willReturn(true);
    }

    // --- recordProgress: happy path ---

    public function testRecordProgressWithValidActiveEnrollmentSucceeds(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn(null); // No existing progress

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->progress->expects($this->once())->method('save');

        $cmd = new RecordProgressCommand(
            enrollmentId: $enrollment->id(),
            moduleId: $module->id(),
            newStatus: ModuleStatus::InProgress,
        );
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: rejects non-Active enrollment ---

    public function testRecordProgressRejectsCancelledEnrollment(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);
        $enrollment->cancel(); // Cancelled

        $this->enrollments->method('get')->willReturn($enrollment);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on cancelled enrollment');

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: rejects Archived course ---

    public function testRecordProgressRejectsArchivedCourse(): void
    {
        $course = $this->buildPublishedCourse();
        $course->archive();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on archived course');

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: rejects module not in enrollment's course ---

    public function testRecordProgressRejectsModuleNotInCourse(): void
    {
        $course = $this->buildPublishedCourse();
        $otherCourse = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($otherCourse->id()); // Module in DIFFERENT course
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong to enrollment\'s course');

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: enrollment not found ---

    public function testRecordProgressThrowsForUnknownEnrollment(): void
    {
        $this->enrollments->method('get')->willReturn(null);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(EnrollmentNotFoundException::class);

        $cmd = new RecordProgressCommand(
            EnrollmentId::generate(),
            ModuleId::generate(),
            ModuleStatus::InProgress,
        );
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: unauthorized ---

    public function testRecordProgressRejectsUnauthorizedCaller(): void
    {
        $course = $this->buildPublishedCourse();
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);
        $this->enrollments->method('get')->willReturn($enrollment);

        // Identity denies admin role
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to record progress');

        $otherUser = new UserId('other-user-id-123456789012345');
        $cmd = new RecordProgressCommand($enrollment->id(), ModuleId::generate(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $otherUser);
    }

    // --- recordProgress: idempotent same-status ---

    public function testRecordProgressIsIdempotentForSameStatus(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $existingProgress = new Progress($module->id(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn($existingProgress); // Existing InProgress

        // MUST NOT open transaction or save (idempotent no-op)
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->progress->expects($this->never())->method('save');

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: forbidden completed→in_progress ---

    public function testRecordProgressThrowsOnRevertingCompletedModule(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $completedProgress = new Progress($module->id(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn($completedProgress); // Already Completed

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(CannotRevertCompletedModuleException::class);

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- recordProgress: transaction rollback ---

    public function testRecordProgressRollsBackOnFailure(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModuleForCourse($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id(), $this->learnerId);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn(null);

        $this->progress->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $cmd = new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress);
        $this->service->recordProgress($cmd, $this->learnerId);
    }

    // --- getProgress: self ---

    public function testGetProgressReturnsProgressForSelf(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate(), $this->learnerId);
        $moduleId = ModuleId::generate();
        $progressEntity = new Progress($moduleId, ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->progress->method('get')->willReturn($progressEntity);

        $result = $this->service->getProgress($enrollment->id(), $moduleId, $this->learnerId);

        self::assertNotNull($result);
        self::assertSame(ModuleStatus::InProgress, $result->status());
    }

    // --- getProgress: unauthorized → null (don't leak existence) ---

    public function testGetProgressReturnsNullForUnauthorized(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate(), $this->learnerId);

        $this->enrollments->method('get')->willReturn($enrollment);

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );

        $otherUser = new UserId('other-user-id-123456789012345');
        $result = $this->service->getProgress($enrollment->id(), ModuleId::generate(), $otherUser);

        self::assertNull($result);
    }

    // --- getProgress: not found → null ---

    public function testGetProgressReturnsNullForNotFound(): void
    {
        $this->enrollments->method('get')->willReturn(null);

        $result = $this->service->getProgress(EnrollmentId::generate(), ModuleId::generate(), $this->learnerId);

        self::assertNull($result);
    }

    // --- listProgressForEnrollment: self ---

    public function testListProgressForEnrollmentSelfSucceeds(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate(), $this->learnerId);
        $progressEntity = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->progress->method('findByEnrollment')->willReturn([$progressEntity]);

        $result = $this->service->listProgressForEnrollment($enrollment->id(), $this->learnerId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Progress::class, $result[0]);
    }

    // --- listProgressForEnrollment: unauthorized ---

    public function testListProgressForEnrollmentRejectsNonSelfNonAdmin(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate(), $this->learnerId);
        $this->enrollments->method('get')->willReturn($enrollment);

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );

        $this->progress->expects($this->never())->method('findByEnrollment');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to list progress');

        $otherUser = new UserId('other-user-id-123456789012345');
        $this->service->listProgressForEnrollment($enrollment->id(), $otherUser);
    }

    // --- Helpers ---

    private function buildPublishedCourse(): Course
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course-' . bin2hex(random_bytes(4))),
        );
        $course->publish();
        return $course;
    }

    private function buildModuleForCourse(CourseId $courseId): Module
    {
        return Module::restoreFromPersistence(
            id: ModuleId::generate(),
            courseId: $courseId,
            title: 'Test Module',
            sortOrder: 0,
            content: null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    private function buildActiveEnrollment(CourseId $courseId, UserId $learnerId): Enrollment
    {
        return Enrollment::create(courseId: $courseId, learnerId: $learnerId);
    }
}
