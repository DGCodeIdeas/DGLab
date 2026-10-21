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
            $this->identity, $this->connection);
        $this->learnerId = new UserId('test-learner-id-1234567890123');
        $this->identity->method('hasAnyRole')->willReturn(true);
    }

    // --- recordProgress: happy path ---
    public function testRecordProgressWithValidActiveEnrollmentSucceeds(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn(null);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->progress->expects($this->once())->method('save');
        $this->service->recordProgress(
            new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }

    // --- Finding 1: require Published ---
    public function testRecordProgressRejectsDraftCourse(): void
    {
        $course = Course::create(CourseTitle::fromString('D'), CourseSlug::fromString('d-'.bin2hex(random_bytes(3))));
        $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on draft course');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressRejectsArchivedCourse(): void
    {
        $course = $this->pub(); $course->archive(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on archived course');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressRejectsModuleNotInCourse(): void
    {
        $course = $this->pub(); $other = $this->pub(); $module = $this->mod($other->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong to enrollment');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressThrowsForUnknownEnrollment(): void
    {
        $this->enrollments->method('get')->willReturn(null);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(EnrollmentNotFoundException::class);
        $this->service->recordProgress(new RecordProgressCommand(EnrollmentId::generate(), ModuleId::generate(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressRejectsCancelledEnrollment(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id()); $enr->cancel();
        $this->enrollments->method('get')->willReturn($enr);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record progress on cancelled enrollment');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressRejectsUnauthorizedCaller(): void
    {
        $course = $this->pub(); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService($this->progress, $this->enrollments, $this->courses, $this->modules, $this->identity, $this->connection);
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to record progress');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), ModuleId::generate(), ModuleStatus::InProgress), new UserId('other-user-id-123456789012345'));
    }

    // --- SAAI Finding 1: NotStarted bypass fix ---
    public function testRecordProgressNotStartedIsNoOpWhenNoExistingRecord(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn(null); // No existing record
        // Transaction begins + commits (empty) but NO save
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->progress->expects($this->never())->method('save');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::NotStarted), $this->learnerId);
    }

    // SAAI Finding 1: NotStarted with existing InProgress record must throw (regression)
    public function testRecordProgressNotStartedWithExistingInProgressThrows(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $existing = new Progress($module->id(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn($existing); // Existing InProgress
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack'); // Rolls back on exception
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot revert in_progress module to not_started');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::NotStarted), $this->learnerId);
    }

    // SAAI Finding 1: NotStarted with existing Completed record must throw (regression)
    public function testRecordProgressNotStartedWithExistingCompletedThrows(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $existing = new Progress($module->id(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn($existing); // Existing Completed
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(CannotRevertCompletedModuleException::class);
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::NotStarted), $this->learnerId);
    }

    // --- Idempotent same-status (inside transaction with locked row) ---
    public function testRecordProgressIdempotentSameStatusStillSaves(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $existing = new Progress($module->id(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn($existing);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->progress->expects($this->once())->method('save');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }

    // --- Full transition matrix (Progress::transition) ---
    public function testTransitionNotStartedToInProgressSucceeds(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::NotStarted, new \DateTimeImmutable(), null);
        $p->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::InProgress, $p->status());
    }
    public function testTransitionNotStartedToCompletedSucceeds(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::NotStarted, new \DateTimeImmutable(), null);
        $p->transition(ModuleStatus::Completed, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::Completed, $p->status());
        self::assertNotNull($p->completedAt());
    }
    public function testTransitionInProgressToCompletedSucceeds(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $p->transition(ModuleStatus::Completed, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::Completed, $p->status());
    }
    public function testTransitionSameStatusIsIdempotent(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $p->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
        self::assertSame(ModuleStatus::InProgress, $p->status());
    }
    public function testTransitionCompletedToInProgressThrows(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->expectException(CannotRevertCompletedModuleException::class);
        $p->transition(ModuleStatus::InProgress, new \DateTimeImmutable());
    }
    public function testTransitionCompletedToNotStartedThrows(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->expectException(CannotRevertCompletedModuleException::class);
        $p->transition(ModuleStatus::NotStarted, new \DateTimeImmutable());
    }
    public function testTransitionInProgressToNotStartedThrows(): void
    {
        $p = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot revert in_progress module to not_started');
        $p->transition(ModuleStatus::NotStarted, new \DateTimeImmutable());
    }

    // --- Forbidden regression via service (inside transaction with locked row) ---
    public function testRecordProgressThrowsOnRevertingCompletedModule(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $existing = new Progress($module->id(), ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn($existing);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(CannotRevertCompletedModuleException::class);
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }
    public function testRecordProgressRollsBackOnFailure(): void
    {
        $course = $this->pub(); $module = $this->mod($course->id()); $enr = $this->enr($course->id());
        $this->enrollments->method('get')->willReturn($enr);
        $this->courses->method('get')->willReturn($course);
        $this->modules->method('get')->willReturn($module);
        $this->progress->method('getForUpdate')->willReturn(null);
        $this->progress->method('save')->willThrowException(new \RuntimeException('DB error'));
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');
        $this->service->recordProgress(new RecordProgressCommand($enr->id(), $module->id(), ModuleStatus::InProgress), $this->learnerId);
    }

    // --- getProgress (uses get(), not getForUpdate()) ---
    public function testGetProgressReturnsProgressForSelf(): void
    {
        $enr = $this->enr(CourseId::generate()); $mId = ModuleId::generate();
        $p = new Progress($mId, ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->enrollments->method('get')->willReturn($enr);
        $this->progress->method('get')->willReturn($p);
        $r = $this->service->getProgress($enr->id(), $mId, $this->learnerId);
        self::assertNotNull($r);
        self::assertSame(ModuleStatus::InProgress, $r->status());
    }
    public function testGetProgressReturnsNullForUnauthorized(): void
    {
        $enr = $this->enr(CourseId::generate());
        $this->enrollments->method('get')->willReturn($enr);
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService($this->progress, $this->enrollments, $this->courses, $this->modules, $this->identity, $this->connection);
        self::assertNull($this->service->getProgress($enr->id(), ModuleId::generate(), new UserId('other-user-id-123456789012345')));
    }
    public function testGetProgressReturnsNullForNotFound(): void
    {
        $this->enrollments->method('get')->willReturn(null);
        self::assertNull($this->service->getProgress(EnrollmentId::generate(), ModuleId::generate(), $this->learnerId));
    }

    // --- listProgressForEnrollment ---
    public function testListProgressForEnrollmentSelfSucceeds(): void
    {
        $enr = $this->enr(CourseId::generate());
        $p = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $this->enrollments->method('get')->willReturn($enr);
        $this->progress->method('findByEnrollment')->willReturn([$p]);
        $r = $this->service->listProgressForEnrollment($enr->id(), $this->learnerId);
        self::assertCount(1, $r);
    }
    public function testListProgressForEnrollmentRejectsNonSelfNonAdmin(): void
    {
        $enr = $this->enr(CourseId::generate());
        $this->enrollments->method('get')->willReturn($enr);
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);
        $this->service = new ProgressApplicationService($this->progress, $this->enrollments, $this->courses, $this->modules, $this->identity, $this->connection);
        $this->progress->expects($this->never())->method('findByEnrollment');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to list progress');
        $this->service->listProgressForEnrollment($enr->id(), new UserId('other-user-id-123456789012345'));
    }

    // --- Helpers ---
    private function pub(): Course
    {
        $c = Course::create(CourseTitle::fromString('T'), CourseSlug::fromString('t-'.bin2hex(random_bytes(4))));
        $c->publish(); return $c;
    }
    private function mod(CourseId $cid): Module
    {
        return Module::restoreFromPersistence(ModuleId::generate(), $cid, 'M', 0, null, new \DateTimeImmutable());
    }
    private function enr(CourseId $cid): Enrollment
    {
        return Enrollment::create(courseId: $cid, learnerId: $this->learnerId);
    }
}
