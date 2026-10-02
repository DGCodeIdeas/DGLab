<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Thrown by {@see ContainerInterface::pulse()} when called outside any
 * Fiber context.
 *
 * Under the Shape C contract (ratified by ADR-021 Amendment 2 + tech-lead
 * decision 2026-10-01), `pulse()` is a request-time, Fiber-local value
 * binding operation — it requires a current Fiber to scope the binding to.
 * Calling `pulse()` from the main context (boot, before any Fiber is
 * started) has no Pulse to bind to and is therefore a contract violation.
 *
 * Boot-time registration MUST use {@see Container::bind()} or
 * {@see Container::singleton()} instead.
 *
 * @see \SovereignStack\Core\Container\Container::pulse() The mutation method that throws this.
 */
final class ContainerException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface
{
}
