<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RouteAttribute
{
    /**
     * @param string               $path        Path pattern, e.g. "/users/{id}".
     * @param list<string>         $methods     HTTP methods, e.g. ["GET", "HEAD"].
     * @param string               $name        Route name for generateUrl(); empty = anonymous.
     * @param list<class-string>   $middleware  Route-local middleware class-strings.
     * @param array<string,string> $constraints Placeholder → regex subpattern, e.g. ["id" => "\d+"].
     */
    public function __construct(
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly string $name = '',
        public readonly array $middleware = [],
        public readonly array $constraints = [],
    ) {}
}
