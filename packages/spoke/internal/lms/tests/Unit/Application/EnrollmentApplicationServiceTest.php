<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Lms\Application\Command\{CancelEnrollmentCommand, EnrollCommand};
use SovereignStack\Spoke\Lms\Application\Service\EnrollmentApplicationService;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus, Enrollment};
use SovereignStack\Spoke\Lms\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, EnrollmentRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle, EnrollmentId, EnrollmentStatus};

/**
 * Tests for EnrollmentApplicationService (Increment 3 PR-A1).
 *
 * Per SAAI Increment 3 PR-A1 + founder's "full backend" directive:
 *   - enroll() requires published course, authorization, transaction
 *   - cancelEnrollment() requires self OR admin role, transaction
 *   - getEnrollment() returns null for both not-found AND unauthorized
 *     (security: avoid leaking enrollment existence to unauthorized callers)
 *   - listEnrollmentsForLearner() requires self OR admin role
 *   - listEnrollmentsForCourse() requires admin role only
 *
 * Mock-based, follows the pattern established by CourseApplicationServiceModuleTest.
 */
final class EnrollmentApplicationServiceTest extends TestCase
{
    private EnrollmentRepositoryInterface $enrollments;
    private CourseRepositoryInterface $courses;
    private IdentityInterface $identity;
    private ConnectionInterface $connection;
    private EnrollmentApplicationService $service;
    private UserId $learnerId;
    private UserId $adminId;

    protected function setUp(): void
    {
        $this->enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $this->courses    = $this->createMock(CourseRepositoryInterface::class);
        $this->identity   = $this->createMock(IdentityInterface::class);
        $this->connection = $this->createMock(ConnectionInterface::class);

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $this->learnerId = new UserId('test-learner-id-1234567890123');
        $this->adminId   = new UserId('test-admin-id-12345678901234');

        // Default: identity authorizes learner with lms:learner role
        $this->identity->method('hasAnyRole')->willReturn(true);
    }

    // --- enroll() happy path ---

    public function testEnrollWithValidPublishedCourseSucceeds(): void
    {
        $course = $this->buildPublishedCourse();

        $this->courses->method('get')->willReturn($course);
        $this->enrollments->method('findByCourseAndLearner')->willReturn(null); // No existing

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->enrollments->expects($this->once())->method('save')
            ->with(self::callback(function (Enrollment $e) use ($course) {
                return $e->courseId()->equals($course->id())
                    && $e->learnerId()->equals($this->learnerId);
            }));

        $cmd = new EnrollCommand(courseId: $course->id(), learnerId: $this->learnerId);
        $enrollmentId = $this->service->enroll($cmd);

        self::assertFalse($enrollmentId->equals(EnrollmentId::generate())); // Non-empty ID
    }

    // --- enroll() rejects non-Published courses ---

    public function testEnrollRejectsDraftCourse(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Draft Course'),
            slug: CourseSlug::fromString('draft-course'),
        ); // Draft by default

        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot enroll in draft course');

        $cmd = new EnrollCommand(courseId: $course->id(), learnerId: $this->learnerId);
        $this->service->enroll($cmd);
    }

    public function testEnrollRejectsArchivedCourse(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Archived Course'),
            slug: CourseSlug::fromString('archived-course'),
        );
        $course->publish();
        $course->archive();

        $this->courses->method('get')->willReturn($course);

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot enroll in archived course');

        $cmd = new EnrollCommand(courseId: $course->id(), learnerId: $this->learnerId);
        $this->service->enroll($cmd);
    }

    // --- enroll() idempotency ---

    public function testEnrollIsIdempotentForActiveEnrollment(): void
    {
        $course = $this->buildPublishedCourse();

        $existingEnrollment = Enrollment::create(
            courseId: $course->id(),
            learnerId: $this->learnerId,
        ); // Active by default

        $this->courses->method('get')->willReturn($course);
        $this->enrollments->method('findByCourseAndLearner')->willReturn($existingEnrollment);

        // MUST NOT save a new enrollment (idempotent)
        $this->enrollments->expects($this->never())->method('save');
        $this->connection->expects($this->never())->method('beginTransaction');

        $cmd = new EnrollCommand(courseId: $course->id(), learnerId: $this->learnerId);
        $result = $this->service->enroll($cmd);

        // Returns the existing enrollment's ID
        self::assertTrue($result->equals($existingEnrollment->id()));
    }

    // --- enroll() authorization ---

    public function testEnrollRejectsUnauthorizedCaller(): void
    {
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to enroll');

        $cmd = new EnrollCommand(courseId: CourseId::generate(), learnerId: $this->learnerId);
        $this->service->enroll($cmd);
    }

    // --- enroll() transaction rollback ---

    public function testEnrollRollsBackOnPersistenceFailure(): void
    {
        $course = $this->buildPublishedCourse();
        $this->courses->method('get')->willReturn($course);
        $this->enrollments->method('findByCourseAndLearner')->willReturn(null);

        $this->enrollments->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $cmd = new EnrollCommand(courseId: $course->id(), learnerId: $this->learnerId);
        $this->service->enroll($cmd);
    }

    // --- cancelEnrollment() self-cancellation ---

    public function testCancelEnrollmentSelfSucceeds(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('get')->willReturn($enrollment);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->enrollments->expects($this->once())->method('save')
            ->with(self::callback(function (Enrollment $e) {
                return $e->status() === EnrollmentStatus::Cancelled;
            }));

        $cmd = new CancelEnrollmentCommand(
            enrollmentId: $enrollment->id(),
            cancelledBy: $this->learnerId, // Self-cancel
        );
        $this->service->cancelEnrollment($cmd);
    }

    // --- cancelEnrollment() admin for other ---

    public function testCancelEnrollmentAllowsAdminToCancelForOther(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('get')->willReturn($enrollment);
        $this->identity->method('hasAnyRole')->willReturn(true); // Admin authorized

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $this->enrollments->expects($this->once())->method('save');

        $cmd = new CancelEnrollmentCommand(
            enrollmentId: $enrollment->id(),
            cancelledBy: $this->adminId, // Different from learner
        );
        $this->service->cancelEnrollment($cmd);
    }

    // --- cancelEnrollment() authorization ---

    public function testCancelEnrollmentRejectsNonSelfNonAdmin(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $otherLearner = new UserId('other-learner-id-123456789012');

        $this->enrollments->method('get')->willReturn($enrollment);

        // Identity denies admin role for the other learner
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to cancel enrollment');

        $cmd = new CancelEnrollmentCommand(
            enrollmentId: $enrollment->id(),
            cancelledBy: $otherLearner,
        );
        $this->service->cancelEnrollment($cmd);
    }

    // --- cancelEnrollment() not found ---

    public function testCancelEnrollmentThrowsForUnknownId(): void
    {
        $this->enrollments->method('get')->willReturn(null); // Not found

        $this->connection->expects($this->never())->method('beginTransaction');

        $this->expectException(EnrollmentNotFoundException::class);

        $cmd = new CancelEnrollmentCommand(
            enrollmentId: EnrollmentId::generate(),
            cancelledBy: $this->learnerId,
        );
        $this->service->cancelEnrollment($cmd);
    }

    // --- getEnrollment() self ---

    public function testGetEnrollmentReturnsEnrollmentForSelf(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('get')->willReturn($enrollment);

        $result = $this->service->getEnrollment($enrollment->id(), $this->learnerId);

        self::assertNotNull($result);
        self::assertTrue($result->id()->equals($enrollment->id()));
    }

    // --- getEnrollment() not found ---

    public function testGetEnrollmentReturnsNullForNotFound(): void
    {
        $this->enrollments->method('get')->willReturn(null);

        $result = $this->service->getEnrollment(EnrollmentId::generate(), $this->learnerId);

        self::assertNull($result);
    }

    // --- getEnrollment() unauthorized → null (don't leak existence) ---

    public function testGetEnrollmentReturnsNullForUnauthorizedCaller(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('get')->willReturn($enrollment);

        // Caller is NOT the learner AND has no admin role
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $otherUser = new UserId('other-user-id-123456789012345');
        $result = $this->service->getEnrollment($enrollment->id(), $otherUser);

        // MUST return null (not throw, not the enrollment) — avoid leaking existence
        self::assertNull($result);
    }

    // --- listEnrollmentsForLearner() self ---

    public function testListEnrollmentsForLearnerSelfSucceeds(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('findByLearner')->willReturn([$enrollment]);

        $result = $this->service->listEnrollmentsForLearner($this->learnerId, $this->learnerId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Enrollment::class, $result[0]);
    }

    // --- listEnrollmentsForLearner() admin for other ---

    public function testListEnrollmentsForLearnerAllowsAdminToQueryForOther(): void
    {
        $enrollment = Enrollment::create(
            courseId: CourseId::generate(),
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('findByLearner')->willReturn([$enrollment]);
        $this->identity->method('hasAnyRole')->willReturn(true); // Admin authorized

        $result = $this->service->listEnrollmentsForLearner($this->learnerId, $this->adminId);

        self::assertCount(1, $result);
    }

    // --- listEnrollmentsForLearner() unauthorized ---

    public function testListEnrollmentsForLearnerRejectsNonSelfNonAdmin(): void
    {
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false);

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $this->enrollments->expects($this->never())->method('findByLearner');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to list another learner');

        $this->service->listEnrollmentsForLearner($this->learnerId, $this->adminId);
    }

    // --- listEnrollmentsForCourse() admin-only ---

    public function testListEnrollmentsForCourseAllowsAdmin(): void
    {
        $courseId = CourseId::generate();
        $enrollment = Enrollment::create(
            courseId: $courseId,
            learnerId: $this->learnerId,
        );

        $this->enrollments->method('findByCourse')->willReturn([$enrollment]);
        $this->identity->method('hasAnyRole')->willReturn(true); // Admin authorized

        $result = $this->service->listEnrollmentsForCourse($courseId, $this->adminId);

        self::assertCount(1, $result);
        self::assertInstanceOf(Enrollment::class, $result[0]);
    }

    public function testListEnrollmentsForCourseRejectsLearner(): void
    {
        // Even if the caller is a learner (not admin), they cannot list
        // course enrollments — this is instructor/admin-only functionality.
        $this->identity = $this->createMock(IdentityInterface::class);
        $this->identity->method('hasAnyRole')->willReturn(false); // No admin role

        $this->service = new EnrollmentApplicationService(
            $this->enrollments,
            $this->courses,
            $this->identity,
            $this->connection,
        );

        $this->enrollments->expects($this->never())->method('findByCourse');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to list course enrollments');

        $this->service->listEnrollmentsForCourse(CourseId::generate(), $this->learnerId);
    }

    // --- Helper ---

    private function buildPublishedCourse(): Course
    {
        $course = Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
        );
        $course->publish();
        return $course;
    }
}
