<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;

final class MiddlewareResolver implements MiddlewareResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(MiddlewareInterface|string|callable $entry): MiddlewareInterface
    {
        if ($entry instanceof MiddlewareInterface) {
            return $entry;
        }

        if (\is_string($entry)) {
            // Lazy: the container only constructs the middleware the first time
            // the request actually reaches that layer. A DB-backed session
            // middleware is never instantiated for static-asset requests.
            $resolved = $this->container->get($entry);
            if (! $resolved instanceof MiddlewareInterface) {
                throw new \TypeError(\sprintf(
                    'Class "%s" resolved via container does not implement %s.',
                    $entry,
                    MiddlewareInterface::class,
                ));
            }
            return $resolved;
        }

        if (\is_callable($entry)) {
            return new CallableMiddlewareAdapter($entry);
        }

        // Unreachable given the union type, but defensive: phpstan level 9
        // cannot prove the union is exhaustive at the call site.
        throw new \TypeError(\sprintf(
            'Middleware must be %s, callable, or class-string; got %s.',
            MiddlewareInterface::class,
            \get_debug_type($entry),
        ));
    }
}

/**
 * Adapts a callable to MiddlewareInterface.
 *
 * The callable signature is:
 *     callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface
 */
