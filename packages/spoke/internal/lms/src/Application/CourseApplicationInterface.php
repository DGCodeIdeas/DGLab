<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Application\Command\AddModuleCommand;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

interface CourseApplicationInterface
{
    public function createCourse(CreateCourseCommand $cmd): CourseId;

    public function publishCourse(CourseId $id, UserId $publishedBy): void;

    /**
     * Add a module to a draft course.
     *
     * Per SAAI Increment 2: modules can only be added while the course is in Draft.
     * Throws if the course is Published or Archived.
     */
    public function addModule(AddModuleCommand $cmd): ModuleId;

    /**
     * Remove a module from a draft course.
     *
     * Per SAAI Increment 2: module removal is draft-only.
     * Throws if the course is Published or Archived.
     */
    public function removeModule(ModuleId $moduleId, UserId $removedBy): void;
}
