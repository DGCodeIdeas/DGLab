<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

final class RouteCompiler
{
    private const PLACEHOLDER_REGEX = '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/';

    /** @throws InvalidRoutePatternException On unclosed brace, duplicate placeholder, or path-traversal. */
    public function compile(Route $route): CompiledRoute
    {
        $path = $route->path;

        // Reject path-traversal patterns at compile time.
        if (\str_contains($path, '/../') || \str_contains($path, '/./')) {
            throw new InvalidRoutePatternException(\sprintf(
                'Route path "%s" contains a path-traversal sequence.',
                $path,
            ));
        }

        $placeholderNames = [];
        $constraints = $route->constraints;

        $regex = \preg_replace_callback(
            self::PLACEHOLDER_REGEX,
            static function (array $m) use (&$placeholderNames, $constraints, $path): string {
                $name = $m[1];
                $inline = $m[2] ?? null;

                if (isset($placeholderNames[$name])) {
                    throw new InvalidRoutePatternException(\sprintf(
                        'Duplicate placeholder "{%s}" in route path "%s".',
                        $name,
                        $path,
                    ));
                }
                $placeholderNames[$name] = true;

                $subpattern = $constraints[$name] ?? $inline ?? '[^/]+';
                return '(?P<' . $name . '>' . $subpattern . ')';
            },
            $path,
        );

        if ($regex === null) {
            throw new InvalidRoutePatternException(\sprintf(
                'PCRE error while compiling route path "%s": %s',
                $path,
                \preg_last_error_msg(),
            ));
        }

        // Anchor: without ^...$, /users/123/extra would match /users/{id}.
        $regex = '#^' . $regex . '$#u';

        return new CompiledRoute(
            route: $route,
            regex: $regex,
            placeholderNames: \array_keys($placeholderNames),
        );
    }
}

/**
 * Immutable: a Route plus its compiled regex and ordered placeholder names.
 */
