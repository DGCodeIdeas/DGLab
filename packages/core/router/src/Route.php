<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

final class Route
{
    /**
     * @param string                $path             Path pattern with {placeholder} syntax.
     * @param list<string>          $methods          HTTP methods (canonical uppercase).
     * @param string                $name             Unique route name (empty = anonymous).
     * @param class-string          $controllerClass  Fully-qualified controller class name.
     * @param string                $controllerMethod Controller method name.
     * @param list<class-string>    $middleware       Route-local middleware class-strings.
     * @param array<string,string>  $constraints      Placeholder → regex subpattern.
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

/**
 * Result of a successful Router::match() call. Parameters are URL-decoded exactly
 * once. Controllers receive this value as the request attribute '__route_match'
 * (set by FinalRequestHandler in CORE-05).
 */
