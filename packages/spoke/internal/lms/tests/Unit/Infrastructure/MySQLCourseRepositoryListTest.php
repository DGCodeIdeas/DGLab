<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Connection;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseSlug, CourseTitle};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\MySQLCourseRepository;

/**
 * Integration test for MySQLCourseRepository::listAll().
 *
 * Tests the new listAll() method added for the LMS vertical slice.
 * Requires a real MySQL instance with the lms_courses table installed.
 *
 * @group integration
 * @group mysql-required
 * @package SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure
 */
final class MySQLCourseRepositoryListTest extends TestCase
{
    private ?Connection $connection = null;
    private ?MySQLCourseRepository $repo = null;

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $name = getenv('TEST_DB_NAME') ?: 'dglab_test';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $pass = getenv('TEST_DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $this->connection = new Connection($dsn, $user, $pass);
        } catch (\Throwable $e) {
            $this->markTestSkipped("MySQL not available: " . $e->getMessage());
        }

        $this->repo = new MySQLCourseRepository($this->connection);

        // Install lms_courses table if not present.
        $this->connection->exec("CREATE TABLE IF NOT EXISTS lms_courses (
            id CHAR(26) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Clean any prior data.
        $this->connection->exec("DELETE FROM lms_courses");
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->exec("DELETE FROM lms_courses");
        }
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testListAllReturnsEmptyArrayWhenNoCourses(): void
    {
        $result = $this->repo->listAll();
        $this->assertSame([], $result, 'listAll on empty table returns empty array');
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testListAllReturnsAllCoursesOrderedByCreationDateDesc(): void
    {
        $course1 = Course::create(
            CourseTitle::fromString('First Course'),
            CourseSlug::fromString('first-course'),
            'Oldest course'
        );
        $this->repo->save($course1);

        // Small delay to ensure different timestamps.
        usleep(1100000); // 1.1s

        $course2 = Course::create(
            CourseTitle::fromString('Second Course'),
            CourseSlug::fromString('second-course'),
            'Newer course'
        );
        $this->repo->save($course2);

        $result = $this->repo->listAll();

        $this->assertCount(2, $result, 'two courses saved and retrieved');
        $this->assertSame('Second Course', (string) $result[0]->title(), 'newest course first');
        $this->assertSame('First Course', (string) $result[1]->title(), 'older course second');
    }

    /**
     * @group integration
     * @group mysql-required
     */
    public function testListAllHydratesAllFields(): void
    {
        $course = Course::create(
            CourseTitle::fromString('Hydration Test'),
            CourseSlug::fromString('hydration-test'),
            'Testing field hydration'
        );
        $this->repo->save($course);

        $result = $this->repo->listAll();
        $this->assertCount(1, $result);

        $hydrated = $result[0];
        $this->assertSame((string) $course->id(), (string) $hydrated->id());
        $this->assertSame('Hydration Test', (string) $hydrated->title());
        $this->assertSame('hydration-test', (string) $hydrated->slug());
        $this->assertSame('Testing field hydration', $hydrated->description());
        $this->assertSame('draft', $hydrated->status()->value);
    }
}
