<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Service;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Internal\Lms\Application\Command\AddModuleCommand;
use SovereignStack\Internal\Lms\Application\Command\CreateCourseCommand;
use SovereignStack\Internal\Lms\Application\CourseApplicationInterface;
use SovereignStack\Internal\Lms\Domain\Entity\Course;
use SovereignStack\Internal\Lms\Domain\Exception\CourseNotFoundException;
use SovereignStack\Internal\Lms\Domain\Repository\{CourseRepositoryInterface, ModuleRepositoryInterface};
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, ModuleId};

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
            ?? throw CourseNotFoundException::forId($id);

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

    public function updateCourse(CourseId $id, UserId $updatedBy, string $title, ?string $description, ?string $slug): void
    {
        $this->requireAnyRole($updatedBy, ['lms:admin', 'lms:instructor', 'platform:admin']);
        $course = $this->courses->get($id)
            ?? throw CourseNotFoundException::forId($id);

        $this->connection->beginTransaction();
        try {
            $course->update($title, $description, $slug);
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
            ?? throw CourseNotFoundException::forId($cmd->courseId);

        $existingModules = $this->modules->findByCourse($cmd->courseId);
        $nextSortOrder = count($existingModules);

        $moduleId = ModuleId::generate();

        $this->connection->beginTransaction();
        try {
            $course->addModule(
                id: $moduleId,
                title: $cmd->title,
                sortOrder: $nextSortOrder,
                content: $cmd->content,
            );
            $this->courses->save($course);

            $module = \SovereignStack\Internal\Lms\Domain\Entity\Module::restoreFromPersistence(
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
            ?? throw CourseNotFoundException::forId($module->courseId());

        $this->connection->beginTransaction();
        try {
            $course->removeModule($moduleId);
            $this->courses->save($course);
            $this->modules->delete($moduleId);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function listPublishedCourses(int $limit = 20, int $offset = 0): array
    {
        return $this->courses->findPublished($limit, $offset);
    }

    public function isCourseComplete(EnrollmentId $enrollmentId): bool
    {
        // TODO: Implement when progress persistence is wired
        return false;
    }

    /** @param array<string> $roles */
    private function requireAnyRole(UserId $userId, array $roles): void
    {
        $roleIds = array_map(fn (string $r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException('Not authorized', 403);
        }
    }
}