<?php

declare(strict_types=1);

namespace App\Controller\Lms;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SovereignStack\Internal\Lms\Application\Service\CourseApplicationService;
use SovereignStack\Internal\Lms\Domain\Repository\ModuleRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, ModuleId};

final class ModuleController
{
    public function __construct(
        private readonly CourseApplicationService $courseService,
        private readonly ModuleRepositoryInterface $moduleRepo,
    ) {}

    public function list(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $courseId = $request->getAttribute('id');

        $modules = $this->moduleRepo->findByCourse(new \SovereignStack\Internal\Lms\Domain\ValueObject\CourseId($courseId));

        return $this->jsonResponse(200, [
            'modules' => array_map(fn($m) => [
                'id' => (string)$m->id(),
                'title' => (string)$m->title(),
                'sortOrder' => $m->sortOrder(),
                'content' => $m->content(),
            ], $modules),
        ]);
    }

    private function jsonResponse(int $status, array $data, int $httpStatus = 200): \Psr\Http\Message\ResponseInterface
    {
        $response = new \SovereignStack\Core\Http\Response($status);
        $stream = (new \SovereignStack\Core\Http\StreamFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
}