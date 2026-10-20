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
 * Tests for ProgressApplicationService (Increment 3 PR-A2, revised per SAAI review).
 *
 * Revised per SAAI Findings 1-4:
 *   - Finding 1: require CourseStatus::Published (rejects Draft AND Archived)
 *   - Finding 2: use Progress::transition() for all state-machine validation
 *   - Finding 3: NotStarted is a no-op (no DB row created)
 *   - Finding 4: save() uses atomic upsert (service always wraps in transaction)
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

    protected function setUp(): void
    {
        $this->progress = $this->createMock(ProgressRepositoryInterface::class);
        $this->enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $this->courses = $this->createMock(CourseRepositoryInterface::class);
        $this->modules = $this->createMock(ModuleRepositoryInterface::class);
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );
        $this->learnerId = new UserId('test-learner-id-1234567890123');
        $this->identity->method('hasAnyRole')->willReturn(true);
    }

    // --- recordProgress: happy path ---
    public function testRecordProgressWithValidActiveEnrollmentSucceeds(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn(null);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->progress->expects($this->once())->method('save');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    // --- Finding 1: require Published (rejects Draft) ---
    public function testRecordProgressRejectsDraftCourse(): void
    {
        $course = Course::create(CourseTitle::fromString('Draft'), CourseSlug::fromString('draft-' . bin2hex(random_bytes(3))));
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on draft course');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    // --- Finding 1: require Published (rejects Archived) ---
    public function testRecordProgressRejectsArchivedCourse(): void
    {
        $course = $this->buildPublishedCourse();
        $course->archive();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on archived course');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    // --- Finding 1: rejects module not in course ---
    public function testRecordProgressRejectsModuleNotInCourse(): void
    {
        $course = $this->buildPublishedCourse();
        $otherCourse = $this->buildPublishedCourse();
        $module = $this->buildModule($otherCourse->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong to enrollment');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    public function testRecordProgressThrowsForUnknownEnrollment(): void
    {
        $this->enrollments->method('get')->willReturn(null);
        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(EnrollmentNotFoundException::class);

        $this->service->recordProgress(
            new RecordProgressCommand(EnrollmentId::generate(), ModuleId::generate(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    public function testRecordProgressRejectsCancelledEnrollment(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());
        $enrollment->cancel();

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on cancelled enrollment');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    public function testRecordProgressRejectsUnauthorizedCaller(): void
    {
        $course = $this->buildPublishedCourse();
        $enrollment = $this->buildActiveEnrollment($course->id());
        $this->enrollments->method('get')->willReturn($enrollment);

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to record progress');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), ModuleId::generate(), ModuleStatus::InProgress),
            new UserId('other-user-id-123456789012345'),
        );
    }

    // --- Finding 3: NotStarted is a no-op ---
    public function testRecordProgressNotStartedIsNoOp(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);

        // NotStarted must NOT open a transaction or save
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->progress->expects($this->never())->method('save');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::NotStarted),
            $this->learnerId,
        );
    }

    // --- Finding 2: idempotent same-status (transition() is no-op, but save still runs) ---
    public function testRecordProgressIdempotentSameStatusStillSaves(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());
        $existing = new Progress($module->id(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn($existing);

        // Even for idempotent transitions, the service saves (atomic upsert writes same values)
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->progress->expects($this->once())->method('save');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    // --- Finding 2: full transition matrix via Progress::transition() ---

    public function testTransitionNotStartedToInProgressSucceeds(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::NotStarted, new \DateTimeImmutable(), null);
        $progress->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::InProgress, $progress->status());
    }

    public function testTransitionNotStartedToCompletedSucceeds(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::NotStarted, new \DateTimeImmutable(), null);
        $progress->transition(ModuleStatus::Completed, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::Completed, $progress->status());
        self::assertNotNull($progress->completedAt());
    }

    public function testTransitionInProgressToCompletedSucceeds(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $progress->transition(ModuleStatus::Completed, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::Completed, $progress->status());
    }

    public function testTransitionSameStatusIsIdempotent(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $progress->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::InProgress, $progress->status());
    }

    public function testTransitionCompletedToInProgressThrows(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->expectException(CannotRevertCompletedModuleException::class);
        $progress->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
    }

    public function testTransitionCompletedToNotStartedThrows(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->expectException(CannotRevertCompletedModuleException::class);
        $progress->transition(ModuleStatus::NotStarted, new \DateTimeImmutable());
    }

    public function testTransitionInProgressToNotStartedThrows(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot revert in_progress module to not_started');
        $progress->transition(ModuleStatus::NotStarted, new \DateTimeImmutable());
    }

    // --- recordProgress: forbidden regression via service ---
    public function testRecordProgressThrowsOnRevertingCompletedModule(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());
        $completed = new Progress($module->id(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn($completed);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(CannotRevertCompletedModuleException::class);

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    public function testRecordProgressRollsBackOnFailure(): void
    {
        $course = $this->buildPublishedCourse();
        $module = $this->buildModule($course->id());
        $enrollment = $this->buildActiveEnrollment($course->id());

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('get')->willReturn(null);
        $this->progress->method('save')->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->service->recordProgress(
            new RecordProgressCommand($enrollment->id(), $module->id(), ModuleStatus::InProgress),
            $this->learnerId,
        );
    }

    // --- getProgress ---
    public function testGetProgressReturnsProgressForSelf(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate());
        $moduleId = ModuleId::generate();
        $progressEntity = new Progress($moduleId, ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->progress->method('get')->willReturn($progressEntity);

        $result = $this->service->getProgress($enrollment->id(), $moduleId, $this->learnerId);
        self::assertNotNull($result);
        self::assertSame(ModuleStatus::InProgress, $result->status());
    }

    public function testGetProgressReturnsNullForUnauthorized(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate());
        $this->enrollments->method('get')->willReturn($enrollment);

        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService(
            $this->progress, $this->enrollments, $this->courses, $this->modules,
            $this->identity, $this->connection,
        );

        $result = $this->service->getProgress($enrollment->id(), ModuleId::generate(), new UserId('other-user-id-123456789012345'));
        self::assertNull($result);
    }

    public function testGetProgressReturnsNullForNotFound(): void
    {
        $this->enrollments->method('get')->willReturn(null);
        $result = $this->service->getProgress(EnrollmentId::generate(), ModuleId::generate(), $this->learnerId);
        self::assertNull($result);
    }

    // --- listProgressForEnrollment ---
    public function testListProgressForEnrollmentSelfSucceeds(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate());
        $progressEntity = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->progress->method('findByEnrollment')->willReturn([$progressEntity]);

        $result = $this->service->listProgressForEnrollment($enrollment->id(), $this->learnerId);
        self::assertCount(1, $result);
    }

    public function testListProgressForEnrollmentRejectsNonSelfNonAdmin(): void
    {
        $enrollment = $this->buildActiveEnrollment(CourseId::generate());
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

        $this->service->listProgressForEnrollment($enrollment->id(), new UserId('other-user-id-123456789012345'));
    }

    // --- Helpers ---
    private function buildPublishedCourse(): Course
    {
        $course = Course::create(
            CourseTitle::fromString('Test Course'),
            CourseSlug::fromString('test-course-' . bin2hex(random_bytes(4))),
        );
        $course->publish();
        return $course;
    }

    private function buildModule(CourseId $courseId): Module
    {
        return Module::restoreFromPersistence(
            id: ModuleId::generate(), courseId: $courseId, title: 'Test Module',
            sortOrder: 0, content: null, createdAt: new \DateTimeImmutable(),
        );
    }

    private function buildActiveEnrollment(CourseId $courseId): Enrollment
    {
        return Enrollment::create(courseId: $courseId, learnerId: $this->learnerId);
    }
}
