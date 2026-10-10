<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Service;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Lms\Application\Command\AddModuleCommand;
use SovereignStack\Spoke\Lms\Application\Command\CreateCourseCommand;
use SovereignStack\Spoke\Lms\Application\CourseApplicationInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\Exception\CourseNotFoundException;
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

/**
 * Application service for course and module lifecycle operations.
 *
 * Per SAAI Increment 2:
 *   - Authorization is enforced in the application layer, not the repository.
 *   - Multi-record operations use transactions (ConnectionInterface).
 *   - Modules can only be added/removed while the course is in Draft.
 *   - Publishing persists the course state consistently.
 *
 * Single source of truth for course lifecycle — no parallel service.
 */
final class CourseApplicationService implements CourseApplicationInterface
{
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly ModuleRepositoryInterface $modules,
        private readonly IdentityInterface $identity,
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function createCourse(CreateCourseCommand $cmd): CourseId
    {
        $this->requireAnyRole($cmd->createdBy, ['lms:admin', 'lms:instructor', 'platform:admin']);
        $course = Course::create(
            title: $cmd->title,
            slug: $cmd->slug,
            description: $cmd->description,
        );
        $this->courses->save($course);

        return $course->id();
    }

    public function publishCourse(CourseId $id, UserId $publishedBy): void
    {
        $this->requireAnyRole($publishedBy, ['lms:admin', 'lms:instructor', 'platform:admin']);
        $course = $this->courses->get($id)
            ?? throw new CourseNotFoundException($id);

        $this->connection->beginTransaction();
        try {
            $course->publish();
            $this->courses->save($course);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function addModule(AddModuleCommand $cmd): ModuleId
    {
        $this->requireAnyRole($cmd->addedBy, ['lms:admin', 'lms:instructor', 'platform:admin']);

        $course = $this->courses->get($cmd->courseId)
            ?? throw new CourseNotFoundException($cmd->courseId);

        // Determine the next sort order
        $existingModules = $this->modules->findByCourse($cmd->courseId);
        $nextSortOrder = count($existingModules);

        $moduleId = ModuleId::generate();

        $this->connection->beginTransaction();
        try {
            // Course::addModule() enforces Draft-only (throws if Published/Archived)
            $course->addModule(
                id: $moduleId,
                title: $cmd->title,
                sortOrder: $nextSortOrder,
                content: $cmd->content,
            );
            $this->courses->save($course);

            // Persist the module itself
            $module = \SovereignStack\Spoke\Lms\Domain\Entity\Module::restoreFromPersistence(
                id: $moduleId,
                courseId: $cmd->courseId,
                title: $cmd->title,
                sortOrder: $nextSortOrder,
                content: $cmd->content,
                createdAt: new \DateTimeImmutable(),
            );
            $this->modules->save($module);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $moduleId;
    }

    public function removeModule(ModuleId $moduleId, UserId $removedBy): void
    {
        $this->requireAnyRole($removedBy, ['lms:admin', 'lms:instructor', 'platform:admin']);

        $module = $this->modules->get($moduleId)
            ?? throw new \RuntimeException("Module not found: {$moduleId}");

        $course = $this->courses->get($module->courseId())
            ?? throw new CourseNotFoundException($module->courseId());

        $this->connection->beginTransaction();
        try {
            // Course::removeModule() enforces Draft-only (throws if Published/Archived)
            $course->removeModule($moduleId);
            $this->courses->save($course);
            $this->modules->delete($moduleId);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function requireAnyRole(UserId $userId, array $roles): void
    {
        $roleIds = array_map(fn ($r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException('Not authorized', 403);
        }
    }
}
