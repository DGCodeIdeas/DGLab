<?php

declare(strict_types=1);

namespace App\Controller\Lms;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Internal\Lms\Application\Service\ProgressApplicationService;
use SovereignStack\Internal\Lms\Domain\Repository\ProgressRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus, UserId as LmsUserId};

final class ProgressController
{
    public function __construct(
        private readonly ProgressApplicationService $progressService,
        private readonly ProgressRepositoryInterface $progressRepo,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function record(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $data = json_decode((string)$request->getBody(), true) ?? [];
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new RecordProgressCommand(
            enrollmentId: $data['enrollmentId'] ?? '',
            moduleId: $data['moduleId'] ?? '',
            newStatus: \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleStatus::from($data['status'] ?? ''),
        );

        $this->progressService->recordProgress($cmd, $actor);

        return $this->jsonResponse(200, ['status' => 'recorded']);
    }

    public function get(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $enrollmentId = $request->getAttribute('id');
        $moduleId = $request->getAttribute('moduleId');
        $actor = $this->getAuthenticatedUser($request);

        $progress = $this->progressService->getProgress(
            new \SovereignStack\Internal\Lms\Domain\ValueObject\EnrollmentId($enrollmentId),
            new \SovereignStack\Internal\Lms\Domain\ValueObject\ModuleId($moduleId),
            $this->getAuthenticatedUser($request)
        );

        if ($progress === null) {
            return $this->jsonResponse(404, ['error' => 'Progress not found']);
        }

        return $this->jsonResponse(200, [
            'enrollmentId' => $enrollmentId,
            'moduleId' => (string)$progress->moduleId(),
            'status' => $progress->status()->value,
            'startedAt' => $progress->startedAt()->format('c'),
            'completedAt' => $progress->completedAt()?->format('c'),
        ]);
    }

    public function list(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $enrollmentId = $request->getAttribute('id');
        $actor = $this->getAuthenticatedUser($request);

        $progressList = $this->progressService->listProgressForEnrollment(
            new \SovereignStack\Internal\Lms\Domain\ValueObject\EnrollmentId($enrollmentId),
            $this->getAuthenticatedUser($request)
        );

        return $this->jsonResponse(200, [
            'progress' => array_map(fn($p) => [
                'moduleId' => (string)$p->moduleId(),
                'status' => $p->status()->value,
                'startedAt' => $p->startedAt()->format('c'),
                'completedAt' => $p->completedAt()?->format('c'),
            ], $progressList),
        ]);
    }

    private function getAuthenticatedUser(\Psr\Http\Message\ServerRequestInterface $request): \SovereignStack\Hub\Identity\Domain\ValueObject\UserId
    {
        $userId = $request->getAttribute('userId');
        if ($userId === null) {
            throw new \RuntimeException('Unauthenticated', 401);
        }
        return new \SovereignStack\Hub\Identity\Domain\ValueObject\UserId($userId);
    }

    private function jsonResponse(int $status, array $data, int $httpStatus = 200): \Psr\Http\Message\ResponseInterface
    {
        $response = new \SovereignStack\Core\Http\Response($status);
        $stream = (new \SovereignStack\Core\Http\StreamFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
}