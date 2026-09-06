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
    /** @param mixed $uri */
    public function createRequest(string $method, $uri): RequestInterface
    {
        if ($uri instanceof UriInterface) {
            return new Request($method, $uri);
        }
        // String or Stringable — convert to string for the Request constructor.
        return new Request($method, is_string($uri) ? $uri : (string) $uri);
    }
}
