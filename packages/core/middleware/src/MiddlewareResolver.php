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
        private ?ContainerInterface $container = null,
    ) {}

    /** @param MiddlewareInterface|callable|string $entry */
    public function resolve(MiddlewareInterface|string|callable $entry): MiddlewareInterface
    {
        if ($entry instanceof MiddlewareInterface) {
            return $entry;
        }

        if (\is_string($entry) && $this->container !== null) {
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

        if (\is_string($entry)) {
            // P2 fix: previously this branch fell through to an unreachable
            // TypeError with a misleading "got string" message. Now we
            // explicitly try direct instantiation (works when the class has
            // a no-arg constructor) and throw a clear LogicException when
            // that's not possible.
            if (!class_exists($entry)) {
                throw new \LogicException(\sprintf(
                    'Cannot resolve middleware class-string "%s": class does not exist.',
                    $entry,
                ));
            }

            try {
                $resolved = new $entry();
            } catch (\Throwable $e) {
                throw new \LogicException(\sprintf(
                    'Cannot resolve middleware class-string "%s" without a container: %s. '
                    . 'Pass a ContainerInterface to MiddlewareResolver::__construct() '
                    . 'to enable lazy resolution of class-string middleware with dependencies.',
                    $entry,
                    $e->getMessage(),
                ), 0, $e);
            }

            if (! $resolved instanceof MiddlewareInterface) {
                throw new \TypeError(\sprintf(
                    'Class "%s" instantiated directly does not implement %s.',
                    $entry,
                    MiddlewareInterface::class,
                ));
            }
            return $resolved;
        }

        // At this point, $entry has been narrowed from MiddlewareInterface|string|callable
        // to just callable (MiddlewareInterface and string cases handled above).
        // No is_callable() check needed — PHPStan knows it's always callable here.
        return new CallableMiddlewareAdapter($entry);
    }
}

/**
 * Adapts a callable to MiddlewareInterface.
 *
 * The callable signature is:
 *     callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface
 */
