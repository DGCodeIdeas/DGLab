<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Tests\Unit\Domain;
use PHPUnit\Framework\TestCase;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseTitle, CourseSlug};

final class CourseTest extends TestCase
{
    public function testCreateCourse(): void
    {
        $course = Course::create(
            title: CourseTitle::fromString('Introduction to PHP'),
            slug: CourseSlug::fromString('intro-to-php'),
            description: 'A comprehensive PHP course',
        );
        self::assertSame('Introduction to PHP', (string)$course->title());
        self::assertSame('intro-to-php', (string)$course->slug());
        self::assertSame(CourseStatus::Draft, $course->status());
    }

    public function testPublishTransitionsToPublished(): void
    {
        $course = $this->createCourse();
        $course->publish();
        self::assertSame(CourseStatus::Published, $course->status());
    }

    public function testPublishIsIdempotent(): void
    {
        $course = $this->createCourse();
        $course->publish();
        $updatedAt = $course->updatedAt();
        $course->publish();
        self::assertSame(CourseStatus::Published, $course->status());
        self::assertSame($updatedAt, $course->updatedAt());
    }

    private function createCourse(): Course
    {
        return Course::create(
            title: CourseTitle::fromString('Test Course'),
            slug: CourseSlug::fromString('test-course'),
        );
    }
}
