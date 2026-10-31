<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-17 ResponseFactory — creates Response instances.
 */
final class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, reasonPhrase: $reasonPhrase);
    }
}
