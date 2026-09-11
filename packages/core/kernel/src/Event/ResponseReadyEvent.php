<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Event;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\EventDispatcher\Event;
use SovereignStack\Core\Kernel\KernelInterface;

/**
 * Dispatched after the middleware pipeline returns a response, before
 * handle() returns it to the caller.
 *
 * Listeners can use this event for:
 *   - Response transformation (adding security headers, compression)
 *   - Access logging (status code, response time, content length)
 *   - Caching (storing the response for future requests)
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel\Event
 */
final class ResponseReadyEvent extends Event
{
    public function __construct(
        public readonly KernelInterface $kernel,
        public readonly ServerRequestInterface $request,
        public ResponseInterface $response,
    ) {
    }

    /**
     * Replace the response (e.g. after adding headers).
     *
     * @param ResponseInterface $response
     */
    public function withResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
