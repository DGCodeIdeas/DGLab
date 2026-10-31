<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Thrown by {@see ContainerInterface::pulse()} when invoked outside a Fiber
 * context. Per the Shape C contract (CORE-02): pulse() is a request-time,
 * Fiber-local value-binding operation — it requires a current Fiber to scope
 * the binding to. Calling pulse() from the main (non-Fiber) context has no
 * sensible scope to bind to and is a programming error.
 *
 * The PSR-11 {@see \Psr\Container\ContainerExceptionInterface} is implemented
 * so callers that catch the PSR-11 base exception still intercept it.
 */
final class ContainerException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface
{
}
