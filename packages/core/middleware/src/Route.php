<?php
declare(strict_types=1);

/**
 * Stub Route for CORE-06 (not yet implemented).
 */

namespace SovereignStack\Core\Router;

final class Route
{
    /**
     * @param list<string> $methods
     * @param list<class-string> $middleware
     * @param array<string, string> $constraints
     */
    public function __construct(
        public readonly string $path,
        public readonly array $methods,
        public readonly string $name,
        public readonly string $controllerClass,
        public readonly string $controllerMethod,
        public readonly array $middleware = [],
        public readonly array $constraints = [],
    ) {}
}
