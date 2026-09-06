<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 * PSR-17 RequestFactory — creates Request instances.
 */
final class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri)) {
            return new Request($method, $uri);
        }
        return new Request($method, $uri);
    }
}
