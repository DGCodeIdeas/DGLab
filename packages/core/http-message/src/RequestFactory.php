<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 RequestFactory — creates Request instances.
 */
final class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri) || $uri instanceof UriInterface) {
            return new Request($method, $uri);
        }
        throw new \InvalidArgumentException(
            'URI must be a string or UriInterface; got ' . get_debug_type($uri)
        );
    }
}
