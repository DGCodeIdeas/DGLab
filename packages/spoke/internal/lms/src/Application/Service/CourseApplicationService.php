<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Application\Service;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Lms\Application\Command\CreateCourseCommand;
use SovereignStack\Spoke\Lms\Application\CourseApplicationInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\CourseId;

final class CourseApplicationService implements CourseApplicationInterface
{
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly IdentityInterface $identity,
    ) {}

    public function createCourse(CreateCourseCommand $cmd): CourseId
    {
        $this->requireAnyRole($cmd->createdBy, ['lms:admin', 'lms:instructor', 'platform:admin']);
        $course = Course::create(
            title: $cmd->title, slug: $cmd->slug, description: $cmd->description
        );
        $this->courses->save($course);
        return $course->id();
    }

    public function publishCourse(CourseId $id, UserId $publishedBy): void
    {
        $this->requireAnyRole($publishedBy, ['lms:admin', 'lms:instructor', 'platform:admin']);
        $course = $this->courses->get($id)
            ?? throw new \SovereignStack\Spoke\Lms\Domain\Exception\CourseNotFoundException($id);
        $course->publish();
        $this->courses->save($course);
    }

    private function requireAnyRole(UserId $userId, array $roles): void
    {
        $roleIds = array_map(fn($r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException('Not authorized', 403);
        }
    }
}
