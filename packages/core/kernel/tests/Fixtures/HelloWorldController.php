<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;

/**
 * Test fixture controller for the "Hello World" integration test.
 *
 * Returns a 200 response with body "Hello World".
 */
final class HelloWorldController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello World');
        $stream->rewind();

        return new Response(200, body: $stream);
    }
}
