<?php

declare(strict_types=1);

namespace App\Controller\Lms;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Application\Command\{EnrollCommand, CancelEnrollmentCommand, ReEnrollCommand};
use SovereignStack\Internal\Lms\Application\Service\EnrollmentApplicationService;
use SovereignStack\Internal\Lms\Domain\Repository\EnrollmentRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, CourseId, UserId};

final class EnrollmentController
{
    public function __construct(
        private readonly EnrollmentApplicationService $enrollmentService,
        private readonly EnrollmentRepositoryInterface $enrollmentRepo,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function enroll(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $data = json_decode((string)$request->getBody(), true) ?? [];
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new \SovereignStack\Internal\Lms\Application\Command\EnrollCommand(
            courseId: $data['courseId'] ?? '',
            learnerId: $actor,
        );

        $enrollmentId = $this->enrollmentService->enroll($cmd);

        return $this->jsonResponse(201, [
            'enrollmentId' => (string)$enrollmentId,
            'status' => 'active',
        ], 201);
    }

    public function cancel(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $id = $request->getAttribute('id');
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new \SovereignStack\Internal\Lms\Application\Command\CancelEnrollmentCommand(
            enrollmentId: $id,
            cancelledBy: $actor,
        );

        $this->enrollmentService->cancelEnrollment($cmd);

        return $this->jsonResponse(200, ['status' => 'cancelled']);
    }

    public function reenroll(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $id = $request->getAttribute('id');
        $actor = $this->getAuthenticatedUser($request);

        $cmd = new \SovereignStack\Internal\Lms\Application\Command\ReEnrollCommand(
            enrollmentId: $id,
            learnerId: $actor,
        );

        $newEnrollmentId = $this->enrollmentService->reEnroll($cmd->enrollmentId, $cmd->learnerId);

        return $this->jsonResponse(201, [
            'enrollmentId' => (string)$newEnrollmentId,
            'status' => 'active',
        ], 201);
    }

    private function getAuthenticatedUser(\Psr\Http\Message\ServerRequestInterface $request): \SovereignStack\Hub\Identity\Domain\ValueObject\UserId
    {
        $userId = $request->getAttribute('userId');
        if ($userId === null) {
            throw new \RuntimeException('Unauthenticated', 401);
        }
        return $userId;
    }

    private function jsonResponse(int $status, array $data, int $httpStatus = 200): ResponseInterface
    {
        $response = new \SovereignStack\Core\Http\Response($status);
        $stream = (new \SovereignStack\Core\Http\StreamFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
}