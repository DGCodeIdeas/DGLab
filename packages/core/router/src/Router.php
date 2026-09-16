<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Router\Exception\DuplicateRouteNameException;
use SovereignStack\Core\Router\Exception\MissingRouteParameterException;
use SovereignStack\Core\Router\Exception\RouteNotFoundException;

final class Router implements RouterInterface
{
    /** @var array<string, list<CompiledRoute>> Indexed by uppercase HTTP method. */
    private array $byMethod = [];

    /** @var array<string, CompiledRoute> Indexed by route name (non-empty only). */
    private array $byName = [];

    private bool $frozen = false;

    public function addRoute(Route $route): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'Cannot addRoute() after match(): the router is frozen for the lifetime of this instance.'
            );
        }

        if ($route->name !== '' && isset($this->byName[$route->name])) {
            throw new DuplicateRouteNameException(\sprintf(
                'Duplicate route name "%s".',
                $route->name,
            ));
        }

        $compiled = (new RouteCompiler())->compile($route);

        if ($route->name !== '') {
            $this->byName[$route->name] = $compiled;
        }

        foreach ($route->methods as $method) {
            $this->byMethod[\strtoupper($method)][] = $compiled;
        }
    }

    public function match(ServerRequestInterface $request): ?RouteResult
    {
        $this->frozen = true;

        $method = \strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // Normalize trailing slashes (except root). /users/ → /users.
        if ($path !== '/' && \str_ends_with($path, '/')) {
            $path = \rtrim($path, '/');
        }

        // Sort routes: static (no placeholders) first, then parameterized.
        // This ensures /users/me matches before /users/{id} regardless of
        // registration order (P2 fix from Core audit).
        $routes = $this->byMethod[$method] ?? [];
        usort($routes, static function (CompiledRoute $a, CompiledRoute $b): int {
            $aIsStatic = $a->placeholderNames === [];
            $bIsStatic = $b->placeholderNames === [];
            if ($aIsStatic && !$bIsStatic) {
                return -1;
            }
            if (!$aIsStatic && $bIsStatic) {
                return 1;
            }
            return 0;
        });

        foreach ($routes as $compiled) {
            $result = @\preg_match($compiled->regex, $path, $matches);
            if ($result === false) {
                // PCRE error (e.g., backtrack limit exceeded by a catastrophic
                // backtracking constraint). Log and continue to next route.
                continue;
            }
            if ($result === 1) {
                $params = [];
                foreach ($compiled->placeholderNames as $name) {
                    // URL-decode EXACTLY ONCE. Controllers MUST NOT call
                    // urldecode() again (see Security Properties).
                    $params[$name] = \rawurldecode($matches[$name]);
                }
                return new RouteResult(
                    route: $compiled->route,
                    parameters: $params,
                    method: $method,
                );
            }
        }

        return null;
    }

    public function generateUrl(string $name, array $parameters = [], array $query = []): string
    {
        if (! isset($this->byName[$name])) {
            throw new RouteNotFoundException(\sprintf('No route registered with name "%s".', $name));
        }

        $compiled = $this->byName[$name];
        $path = $compiled->route->path;

        foreach ($compiled->placeholderNames as $placeholder) {
            if (! \array_key_exists($placeholder, $parameters)) {
                throw new MissingRouteParameterException(\sprintf(
                    'Route "%s" requires parameter "%s".',
                    $name,
                    $placeholder,
                ));
            }
            $value = (string) $parameters[$placeholder];
            unset($parameters[$placeholder]);

            // rawurlencode preserves unreserved chars per RFC 3986.
            $encoded = \rawurlencode($value);

            // Substitute the FIRST {placeholder} (or {placeholder:constraint}).
            // Use str_replace with the literal {placeholder} pattern first
            // (covers the common case), then fall back to regex for
            // {placeholder:constraint} syntax.
            $literalToken = '{' . $placeholder . '}';
            if (\str_contains($path, $literalToken)) {
                $path = \str_replace($literalToken, $encoded, $path);
            } else {
                // Handle {placeholder:constraint} syntax via regex.
                $path = \preg_replace(
                    '/\{' . \preg_quote($placeholder, '/') . '(?::[^}]+)?\}/',
                    $encoded,
                    $path,
                    1,
                ) ?? $path;
            }
        }

        // Remaining parameters and explicit query both append as RFC-3986 query string.
        foreach ([$parameters, $query] as $extra) {
            if ($extra !== []) {
                $qs = \http_build_query($extra, '', '&', \PHP_QUERY_RFC3986);
                $path .= (\str_contains($path, '?') ? '&' : '?') . $qs;
            }
        }

        return $path;
    }
}
