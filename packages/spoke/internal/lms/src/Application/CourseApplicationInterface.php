<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Application;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\ValueObject\CourseId;

interface CourseApplicationInterface
{
    public function createCourse(CreateCourseCommand $cmd): CourseId;
    public function publishCourse(CourseId $id, UserId $publishedBy): void;
}
