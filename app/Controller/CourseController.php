<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;
use SovereignStack\Spoke\Lms\Domain\Entity\Course;
use SovereignStack\Spoke\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseSlug, CourseTitle};

/**
 * Course Catalog controller — the LMS vertical slice entrypoint.
 *
 * Exposes two HTTP endpoints:
 *   GET  /courses       — list all courses (JSON array)
 *   POST /courses       — create a course (JSON body → JSON response)
 *
 * This is the smallest demonstrable LMS workflow: create a course, then
 * see it in the list. It reuses the existing Course entity +
 * MySQLCourseRepository; no new abstractions are introduced.
 *
 * The create endpoint bypasses CourseApplicationService (which requires
 * an IdentityInterface for authorization) to keep the slice minimal.
 * A follow-up will wire the full service layer with auth.
 *
 * @package App\Controller
 */
final class CourseController
{
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
    ) {}

    /**
     * GET /courses — list all courses as JSON.
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $all = $this->courses->listAll();

        $data = array_map(
            static fn(Course $c): array => [
                'id'          => (string) $c->id(),
                'title'       => (string) $c->title(),
                'slug'        => (string) $c->slug(),
                'description' => $c->description(),
                'status'      => $c->status()->value,
                'createdAt'   => $c->createdAt()->format(\DateTimeInterface::ATOM),
            ],
            $all,
        );

        return $this->jsonResponse(200, $data);
    }

    /**
     * POST /courses — create a course from JSON body.
     *
     * Expected JSON: {"title": "...", "slug": "...", "description": "..."}
     * Returns: 201 with the created course, or 422 on invalid input.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true, 2, JSON_THROW_ON_ERROR);

        if (!is_array($body)) {
            return $this->jsonResponse(422, ['error' => 'Request body must be a JSON object']);
        }

        try {
            $title = CourseTitle::fromString((string) ($body['title'] ?? ''));
            $slug  = CourseSlug::fromString((string) ($body['slug'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(422, ['error' => $e->getMessage()]);
        }

        $description = isset($body['description']) ? (string) $body['description'] : null;

        $course = Course::create($title, $slug, $description);
        $this->courses->save($course);

        return $this->jsonResponse(201, [
            'id'          => (string) $course->id(),
            'title'       => (string) $course->title(),
            'slug'        => (string) $course->slug(),
            'description' => $course->description(),
            'status'      => $course->status()->value,
            'createdAt'   => $course->createdAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Build a JSON PSR-7 Response.
     *
     * @param int $status HTTP status code.
     * @param array<string, mixed>|list<array<string, mixed>> $data
     */
    private function jsonResponse(int $status, array $data): ResponseInterface
    {
        $body = new Stream('php://temp', 'r+');
        $body->write(json_encode($data, JSON_THROW_ON_ERROR));
        $body->rewind();

        return new Response(
            status: $status,
            reason: $status === 201 ? 'Created' : 'OK',
            headers: ['Content-Type' => 'application/json'],
            body: $body,
        );
    }
}
