<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Repository;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug};

interface CourseRepositoryInterface
{
    public function get(CourseId $id): ?Course;
    public function getBySlug(CourseSlug $slug): ?Course;
    public function save(Course $course): void;
    public function delete(CourseId $id): void;
}
