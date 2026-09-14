<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;

/**
 * Hello World controller — the Milestone 0 success criterion endpoint.
 *
 * Returns a 200 response with body "Hello World". This is the canonical
 * "walking skeleton" controller that proves the full Pulse round-trip works:
 * ServerRequest → Vanguard → Kernel → middleware → router → controller → Response.
 *
 * This class lives in the App\Controller namespace (application code), NOT in
 * a test fixtures namespace. The previous version lived in
 * SovereignStack\Core\Kernel\Tests\Fixtures\ — that was a P0 finding from
 * the code review: production code must not depend on test fixtures.
 *
 * @package App\Controller
 */
final class HelloController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = new Stream('php://temp', 'r+');
        $body->write('Hello World');
        $body->rewind();
        return new Response(200, 'OK', [], $body);
    }
}
