<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Event;

use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\EventDispatcher\Event;
use SovereignStack\Core\Kernel\KernelInterface;

/**
 * Dispatched at the start of handle(), before the middleware pipeline runs.
 *
 * Listeners can use this event for:
 *   - Audit logging (who made the request, when)
 *   - Request enrichment (adding attributes from headers, geo-IP, etc.)
 *   - Rate limiting checks
 *   - Security scanning (SQL injection detection in query params)
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel\Event
 */
final class RequestReceivedEvent extends Event
{
    public function __construct(
        public readonly KernelInterface $kernel,
        public ServerRequestInterface $request,
    ) {
    }

    /**
     * Replace the request (e.g. after enrichment).
     *
     * @param ServerRequestInterface $request
     */
    public function withRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
