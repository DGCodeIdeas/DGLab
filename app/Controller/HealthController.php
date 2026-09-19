<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;

/**
 * Health check controller.
 *
 * Returns a 200 response with a JSON body indicating the service is healthy.
 * This endpoint is used by:
 *   - Tengine's active upstream health check (check_http_send "GET /health")
 *   - Kubernetes liveness/readiness probes (future)
 *   - Monitoring systems (future)
 *
 * The route /health is registered with a Vanguard contract in public/index.php
 * so it passes the default-deny contract lookup. The DefaultDtoTransformer
 * is used (pass-through) since health checks don't need DTO transformation.
 *
 * Depth-2 scope: returns a simple JSON status. When HUB-01 feature flags
 * land, this can be extended to report feature flag status, database
 * connectivity, cache hit rate, etc.
 *
 * @package App\Controller
 */
final class HealthController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = new Stream('php://temp', 'r+');
        $body->write('{"status":"ok"}');
        $body->rewind();
        return new Response(
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: ['Content-Type' => 'application/json'],
            body: $body,
        );
    }
}
