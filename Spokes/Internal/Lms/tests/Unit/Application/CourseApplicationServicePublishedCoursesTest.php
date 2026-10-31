<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Internal\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Internal\Lms\Domain\Entity\Course;
use SovereignStack\Internal\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface};
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle};

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
        //
        // Fixture titles are deliberately NON-alphabetical ('Zebra', 'Apple',
        // 'Mango') and inserted in that order. This makes the test sensitive
        // to alphabetical-reordering transformations: if the service sorted
        // the result by title, the order would become [Apple, Mango, Zebra]
        // and assertSame would fail because $expected[0] (Zebra) is a
        // different object reference than $result[0] would be (Apple).
        //
        // Note on assertSame semantics for arrays: PHPUnit's assertSame on
        // arrays checks same keys in same order with === for each value.
        // For objects, === checks reference identity. So this assertion
        // verifies: (a) same count, (b) same object references in same
        // position. It does NOT strictly verify array reference identity
        // (two distinct array instances with the same === elements would
        // also pass). For this use case, that is acceptable — the test
        // goal is to catch projection, filtering, cloning, and reordering,
        // all of which would either change the count, break === on
        // elements, or change the order.
        $c1 = Course::create(
            title: CourseTitle::fromString('Zebra Course'),
            slug: CourseSlug::fromString('zebra-course'),
        );
        $c1->publish();
        $c2 = Course::create(
            title: CourseTitle::fromString('Apple Course'),
            slug: CourseSlug::fromString('apple-course'),
        );
        $c2->publish();
        $c3 = Course::create(
            title: CourseTitle::fromString('Mango Course'),
            slug: CourseSlug::fromString('mango-course'),
        );
        $c3->publish();

        // Intentional insertion order: [Zebra, Apple, Mango] — NOT alphabetical.
        // A service that sorts by title would return [Apple, Mango, Zebra].
        $expected = [$c1, $c2, $c3];
        $this->courses->method('findPublished')->willReturn($expected);

        $result = $this->service->listPublishedCourses();

        // Verifies: same count, same object references in same order.
        // Catches: filtering (count change), cloning (=== failure on
        // cloned objects), alphabetical/any reordering (position mismatch).
        self::assertSame($expected, $result, 'service must return repository result without projection, filtering, cloning, or reordering');
        self::assertCount(3, $result);

        // Additional position assertions to make the ordering check explicit
        // and produce a clearer failure message if reordering occurs.
        self::assertSame($c1, $result[0], 'first element must be Zebra Course (repository insertion order preserved)');
        self::assertSame($c2, $result[1], 'second element must be Apple Course (repository insertion order preserved)');
        self::assertSame($c3, $result[2], 'third element must be Mango Course (repository insertion order preserved)');
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
