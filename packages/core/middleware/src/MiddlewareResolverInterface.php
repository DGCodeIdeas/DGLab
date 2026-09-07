<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareResolverInterface
{
    /**
     * @param mixed $entry MiddlewareInterface instance, callable, or class-string.
     *
     * @throws \TypeError  If $entry is a class-string whose container-resolved
     *                     value does not implement MiddlewareInterface.
     * @throws \Psr\Container\NotFoundExceptionInterface If $entry is a
     *                     class-string not registered in the container.
     */
    public function resolve(MiddlewareInterface|string|callable $entry): MiddlewareInterface;
}

/**
 * The terminal request handler invoked when the middleware chain is exhausted.
 *
 * Implementations typically delegate to CORE-06 (Attribute-Based Router) and
 * the matched controller. May also short-circuit to a 404 / 405 response.
 */
