<?php

declare(strict_types=1);

namespace App\Controller\Lms;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Internal\Lms\Application\Command\{CreateCourseCommand, AddModuleCommand, PublishCourseCommand, ArchiveCourseCommand, UpdateCourseCommand};
use SovereignStack\Internal\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Internal\Lms\Domain\Exception\CourseNotFoundException;
use SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, CourseSlug, ModuleId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;

final class CourseController
{
    public function __construct(
        private readonly CourseApplicationService $courseService,
        private readonly CourseRepositoryInterface $courseRepo,
        private readonly IdentityInterface $identity,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string)$request->getBody(), true) ?? [];
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new CreateCourseCommand(
            title: $data['title'] ?? '',
            slug: $data['slug'] ?? '',
            description: $data['description'] ?? null,
            createdBy: $actor,
        );

        $courseId = $this->courseService->createCourse($cmd);

        $course = $this->courseService->get($courseId);
        return $this->jsonResponse(201, [
            'id' => (string)$course->id(),
            'title' => (string)$course->title(),
            'slug' => (string)$course->slug(),
            'description' => $course->description(),
            'status' => $course->status()->value,
            'version' => $course->version(),
        ]);
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = min(100, max(1, (int)($params['limit'] ?? 20)));
        $offset = max(0, (int)($params['offset'] ?? 0));

        $courses = $this->courseService->listPublished($limit, $offset);

        return $this->jsonResponse(200, [
            'data' => array_map(fn($c) => [
                'id' => (string)$c->id(),
                'title' => (string)$c->title(),
                'slug' => (string)$c->slug(),
                'description' => $c->description(),
                'status' => $c->status()->value,
                'version' => $c->version(),
            ], $courses),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($courses),
            ],
        ]);
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $course = $this->courseRepo->get(new CourseId($id));

        if ($course === null) {
            return $this->jsonResponse(404, ['error' => 'Course not found']);
        }

        return $this->jsonResponse(200, [
            'id' => (string)$course->id(),
            'title' => (string)$course->title(),
            'slug' => (string)$course->slug(),
            'description' => $course->description(),
            'status' => $course->status()->value,
            'version' => $course->version(),
            'requirementsSnapshot' => $course->requirementsSnapshotArray(),
        ]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $data = json_decode((string)$request->getBody(), true) ?? [];
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new UpdateCourseCommand(
            id: $request->getAttribute('id'),
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            slug: $data['slug'] ?? null,
            updatedBy: $actor,
        );

        $this->courseService->updateCourse($cmd->id, $cmd->updatedBy, $cmd->title, $cmd->description, $cmd->slug);

        $course = $this->courseRepo->get(new CourseId($id));
        return $this->jsonResponse(200, [
            'id' => (string)$course->id(),
            'title' => (string)$course->title(),
            'slug' => (string)$course->slug(),
            'description' => $course->description(),
            'status' => $course->status()->value,
            'version' => $course->version(),
        ]);
    }

    public function publish(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new PublishCourseCommand(
            id: $id,
            publishedBy: $actor,
        );

        $this->courseService->publishCourse($cmd->id, $cmd->publishedBy);

        $course = $this->courseRepo->get(new CourseId($id));
        return $this->jsonResponse(200, [
            'id' => (string)$course->id(),
            'title' => (string)$course->title(),
            'slug' => (string)$course->slug(),
            'status' => $course->status()->value,
            'version' => $course->version(),
        ]);
    }

    public function archive(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new ArchiveCourseCommand(
            id: $id,
            archivedBy: $actor,
        );

        $this->courseService->archiveCourse($cmd->id, $cmd->archivedBy);

        $course = $this->courseRepo->get(new CourseId($id));
        return $this->jsonResponse(200, [
            'id' => (string)$course->id(),
            'status' => $course->status()->value,
        ]);
    }

    public function versions(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        // TODO: Implement version history endpoint
        return $this->jsonResponse(200, ['versions' => []]);
    }

    private function getAuthenticatedUser(ServerRequestInterface $request): UserId
    {
        $userId = $request->getAttribute('userId');
        if ($userId === null) {
            throw new \RuntimeException('Unauthenticated', 401);
        }
        return $userId;
    }

    private function jsonResponse(int $status, array $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $stream = $this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
}