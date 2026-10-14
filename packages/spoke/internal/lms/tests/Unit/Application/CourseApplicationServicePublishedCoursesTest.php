<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Spoke\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle};

/**
 * Tests for CourseApplicationService::listPublishedCourses (Increment 3).
 *
 * Per SAAI Increment 3 directive: published-course discovery is a PUBLIC
 * operation. No authorization check. Anyone (including unauthenticated
 * visitors) may discover what courses have been published.
 *
 * These tests enforce that contract:
 *   - The method delegates to CourseRepositoryInterface::findPublished().
 *   - The method does NOT consult IdentityInterface (no hasAnyRole, hasRole,
 *     getUserById, authenticate calls).
 *   - The method does NOT open a DB transaction (it is a read-only query;
 *     wrapping a SELECT in beginTransaction/commit would be wasteful and
 *     could starve the connection pool under load).
 *   - The method passes limit and offset through to the repository unchanged.
 *   - The method returns the array returned by the repository without
 *     transformation (no projection, no filtering, no reordering).
 *
 * If a future change introduces authorization on this method, these tests
 * MUST fail — that change requires a separate, owner-approved scope change
 * (and likely a separate method such as listVisibleCoursesFor(UserId)).
 */
final class CourseApplicationServicePublishedCoursesTest extends TestCase
{
    private CourseRepositoryInterface $courses;
    private ModuleRepositoryInterface $modules;
    private IdentityInterface $identity;
    private ConnectionInterface $connection;
    private CourseApplicationService $service;

    protected function setUp(): void
    {
        $this->courses   = $this->createMock(CourseRepositoryInterface::class);
        $this->modules   = $this->createMock(ModuleRepositoryInterface::class);
        $this->identity  = $this->createMock(IdentityInterface::class);
        $this->connection = $this->createMock(ConnectionInterface::class);

        $this->service = new CourseApplicationService(
            $this->courses,
            $this->modules,
            $this->identity,
            $this->connection,
        );
    }

    public function testListPublishedCoursesDelegatesToRepositoryFindPublished(): void
    {
        // The repository is the only collaborator consulted. Identity and
        // connection are NOT touched for this read-only public operation.
        $course = Course::create(
            title: CourseTitle::fromString('Public Course'),
            slug: CourseSlug::fromString('public-course'),
        );
        $course->publish();

        $this->courses->expects($this->once())
            ->method('findPublished')
            ->with(20, 0)
            ->willReturn([$course]);

        $result = $this->service->listPublishedCourses();

        self::assertSame([$course], $result);
    }

    public function testListPublishedCoursesPassesThroughLimitAndOffset(): void
    {
        // The application service MUST NOT clamp, round, or transform limit/offset
        // — the repository already clamps. Double-clamping would be redundant.
        $this->courses->expects($this->once())
            ->method('findPublished')
            ->with(15, 30)
            ->willReturn([]);

        $this->service->listPublishedCourses(limit: 15, offset: 30);
    }

    public function testListPublishedCoursesDoesNotConsultIdentity(): void
    {
        // The public-discovery contract requires NO authorization check.
        // IdentityInterface is in the constructor (used by createCourse,
        // publishCourse, addModule, removeModule) but listPublishedCourses
        // MUST NOT call any of its methods.
        $this->identity->expects($this->never())->method('hasAnyRole');
        $this->identity->expects($this->never())->method('hasRole');
        $this->identity->expects($this->never())->method('getUserById');
        $this->identity->expects($this->never())->method('authenticate');

        $this->courses->method('findPublished')->willReturn([]);

        $this->service->listPublishedCourses();
    }

    public function testListPublishedCoursesDoesNotOpenTransaction(): void
    {
        // Read-only SELECT queries do not require transactions. Wrapping a
        // SELECT in beginTransaction/commit would consume a transaction ID
        // and could starve the connection pool under heavy read load.
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->courses->method('findPublished')->willReturn([]);

        $this->service->listPublishedCourses();
    }

    public function testListPublishedCoursesReturnsRepositoryResultUntransformed(): void
    {
        // The application service MUST NOT project, filter, reorder, or
        // otherwise transform the repository's result. If the repository
        // returns 3 Course objects in a specific order, the application
        // service returns exactly those 3 Course objects in that order.
        $c1 = Course::create(
            title: CourseTitle::fromString('Course 1'),
            slug: CourseSlug::fromString('course-1'),
        );
        $c1->publish();
        $c2 = Course::create(
            title: CourseTitle::fromString('Course 2'),
            slug: CourseSlug::fromString('course-2'),
        );
        $c2->publish();
        $c3 = Course::create(
            title: CourseTitle::fromString('Course 3'),
            slug: CourseSlug::fromString('course-3'),
        );
        $c3->publish();

        $expected = [$c1, $c2, $c3];
        $this->courses->method('findPublished')->willReturn($expected);

        $result = $this->service->listPublishedCourses();

        self::assertSame($expected, $result, 'must return the exact array reference');
        self::assertCount(3, $result);
    }

    public function testListPublishedCoursesReturnsEmptyArrayWhenNoCoursesPublished(): void
    {
        // An empty result is a normal, non-error outcome. The application
        // service must NOT throw, must NOT log, must NOT return null — just
        // an empty array.
        $this->courses->method('findPublished')->willReturn([]);

        $result = $this->service->listPublishedCourses();

        self::assertSame([], $result);
    }
}
