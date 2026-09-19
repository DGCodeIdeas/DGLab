<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

final class RouteResult
{
    /**
     * @param Route                $route      The matched Route.
     * @param array<string,string> $parameters URL-decoded route parameters.
     * @param string               $method     Matched HTTP method (uppercase).
     */
    public function __construct(
        public readonly Route $route,
        public readonly array $parameters,
        public readonly string $method,
    ) {}
}

/**
 * PHP 8.0+ attribute for declaring a route on a controller method. Repeatable
 * (TARGET_METHOD | IS_REPEATABLE) so a single method may map multiple paths.
 * AttributeRouteLoader reads these via ReflectionAttribute::newInstance().
 */
