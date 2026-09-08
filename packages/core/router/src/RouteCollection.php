<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

/**
 * Ordered, name-indexed set of Route objects.
 *
 * Enforces route-name uniqueness. Provides getByMethod() for the matcher
 * to iterate only the routes matching the request's HTTP method.
 */
final class RouteCollection
{
    /** @var array<string, Route> Indexed by route name (non-empty only). */
    private array $byName = [];

    /** @var array<string, list<Route>> Indexed by uppercase HTTP method. */
    private array $byMethod = [];

    /**
     * Add a route to the collection.
     *
     * @throws DuplicateRouteNameException If $route->name is non-empty and already registered.
     */
    public function add(Route $route): void
    {
        if ($route->name !== '' && isset($this->byName[$route->name])) {
            throw new Exception\DuplicateRouteNameException(
                \sprintf('Duplicate route name "%s".', $route->name)
            );
        }

        if ($route->name !== '') {
            $this->byName[$route->name] = $route;
        }

        foreach ($route->methods as $method) {
            $this->byMethod[\strtoupper($method)][] = $route;
        }
    }

    /**
     * Get all routes for a given HTTP method.
     *
     * @param string $method Uppercase HTTP method (GET, POST, etc.).
     * @return list<Route>
     */
    public function getByMethod(string $method): array
    {
        return $this->byMethod[\strtoupper($method)] ?? [];
    }

    /**
     * Get a route by name (for URL generation).
     *
     * @throws Exception\RouteNotFoundException If $name is not registered.
     */
    public function getByName(string $name): Route
    {
        if (!isset($this->byName[$name])) {
            throw new Exception\RouteNotFoundException(
                \sprintf('No route registered with name "%s".', $name)
            );
        }
        return $this->byName[$name];
    }

    /**
     * Get all registered routes.
     *
     * @return list<Route>
     */
    public function all(): array
    {
        return \array_values($this->byName);
    }

    /**
     * Check if a route name is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }
}
