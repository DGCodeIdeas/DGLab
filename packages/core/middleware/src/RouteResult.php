<?php
declare(strict_types=1);

/**
 * Stub RouteResult for CORE-06 (not yet implemented).
 * See RouterInterface.php for the rationale.
 */

namespace SovereignStack\Core\Router;

final class RouteResult
{
    public function __construct(
        public readonly Route $route,
        public readonly array $parameters,
        public readonly string $method,
    ) {}
}
