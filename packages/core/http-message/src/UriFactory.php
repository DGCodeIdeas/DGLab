<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 UriFactory — creates Uri instances.
 */
final class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
