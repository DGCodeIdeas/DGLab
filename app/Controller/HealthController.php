<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;

/**
 * Three-tier health check controller per SPEC-001 §45 (M4 — Production Release Gate).
 *
 * Health endpoints are separated by purpose:
 *
 *   GET /health/live         — Is the process alive? No DB, no deps. For LB liveness probes.
 *   GET /health/ready        — Is this instance ready for traffic? May check DB. For LB readiness.
 *   GET /health/dependencies — What dependencies are healthy? Operator-only, sanitized.
 *   GET /health              — Compatibility path (returns {"status":"ok"}). Migrates to /health/ready.
 *
 * Per SPEC §29: liveness MUST NOT depend on external services (prevents restart storms).
 * Readiness MAY check required dependencies. Dependencies detail is operator-restricted.
 *
 * @package App\Controller
 */
final class HealthController
{
    /**
     * Liveness — is the FrankenPHP worker process alive?
     * No DB, no cache, no external dependency check.
     * Returns 200 if the process can respond at all.
     */
    public function live(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse(200, ['status' => 'alive']);
    }

    /**
     * Readiness — is this instance ready to receive application traffic?
     * Checks: Kernel booted, router frozen, middleware pipeline ready.
     * When DBAL is wired, this MAY check database connectivity.
     * For MVP (depth 2): returns 200 if the process is alive and bootstrapped.
     */
    public function ready(ServerRequestInterface $request): ResponseInterface
    {
        // Depth 2: same as liveness for now — no DB connection to check yet.
        // When CORE-19 DBAL is wired into ApplicationFactory, this will check
        // the database connection and return 503 if unreachable.
        return $this->jsonResponse(200, ['status' => 'ready']);
    }

    /**
     * Dependencies — what dependencies are healthy?
     * Operator/internal access only. Returns sanitized dependency status.
     * Per SPEC §29: detailed dependency information MUST be protected from
     * uncontrolled public exposure.
     *
     * Depth 2: returns empty dependencies (no external services wired yet).
     * Future: database, cache, filesystem, queue status.
     */
    public function dependencies(ServerRequestInterface $request): ResponseInterface
    {
        // Depth 2: no external dependencies wired into the app yet.
        // When they land, this returns: {"database":"healthy","cache":"healthy",...}
        return $this->jsonResponse(200, ['status' => 'ok', 'dependencies' => []]);
    }

    /**
     * Compatibility path — the original /health endpoint.
     * Returns {"status":"ok"} for existing Tengine health checks.
     * Migrates to /health/ready behavior when all consumers update.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse(200, ['status' => 'ok']);
    }

    private function jsonResponse(int $statusCode, array $body): ResponseInterface
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write(json_encode($body, JSON_THROW_ON_ERROR));
        $stream->rewind();
        return new Response(
            statusCode: $statusCode,
            reasonPhrase: $statusCode === 200 ? 'OK' : 'Service Unavailable',
            headers: ['Content-Type' => 'application/json'],
            body: $stream,
        );
    }
}
