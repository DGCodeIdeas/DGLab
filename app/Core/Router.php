<?php

/**
 * DGLab HTTP Router
 *
 * A high-performance regex-based router supporting RESTful methods,
 * route groups, named routes, and middleware pipelines.
 *
 * Architecture Decisions:
 * - Regex-based matching is faster than iterative string comparison
 * - Compiled route cache for production environments
 * - Middleware pipeline follows PSR-15 style
 * - Named routes enable URL generation without hardcoding
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

use DGLab\Core\Exceptions\RouteNotFoundException;

/**
 * Class Router
 *
 * Handles HTTP request routing with support for:
 * - Static and dynamic routes
 * - Parameter extraction with type casting
 * - Route groups with shared attributes
 * - Named routes for URL generation
 * - Middleware pipeline
 */
class Router
{
    /**
     * Registered routes organized by HTTP method
     *
     * @var array<string, array<Route>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'HEAD' => [],
    ];

    /**
     * Named routes for URL generation
     *
     * @var array<string, Route>
     */
    private array $namedRoutes = [];

    /**
     * Route group stack
     *
     * @var array<array>
     */
    private array $groupStack = [];


    /**
     * Global middleware stack
     *
     * @var array<string>
     */
    private array $globalMiddleware = [];

    /**
     * Current route being processed
     */
    private ?Route $currentRoute = null;

    /**
     * Register a GET route
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function get(string $pattern, callable|array|string $handler): Route
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * Register a POST route
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function post(string $pattern, callable|array|string $handler): Route
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * Register a PUT route
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function put(string $pattern, callable|array|string $handler): Route
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    /**
     * Register a PATCH route
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function patch(string $pattern, callable|array|string $handler): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    /**
     * Register a DELETE route
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function delete(string $pattern, callable|array|string $handler): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register a route for multiple methods
     *
     * @param array<string> $methods The HTTP methods
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    public function match(array $methods, string $pattern, callable|array|string $handler): Route
    {
        $route = null;

        foreach ($methods as $method) {
            $route = $this->addRoute($method, $pattern, $handler);
        }

        return $route;
    }

    /**
     * Register a route for all methods
     *
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return void
     */
    public function any(string $pattern, callable|array|string $handler): void
    {
        foreach (array_keys($this->routes) as $method) {
            $this->addRoute($method, $pattern, $handler);
        }
    }

    /**
     * Create a route group
     *
     * Groups allow shared prefixes, middleware, and namespaces.
     *
     * Example:
     * $router->group(['prefix' => 'api', 'middleware' => ['auth']], function($router) {
     *     $router->get('/users', [UserController::class, 'index']);
     * });
     *
     * @param array $attributes Group attributes
     * @param callable $callback Group definition callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Merge with parent group attributes
        if (!empty($this->groupStack)) {
            $parent = end($this->groupStack);
            $attributes = $this->mergeGroupAttributes($parent, $attributes);
        }

        $this->groupStack[] = $attributes;

        // Execute callback with this router
        $callback($this);

        // Pop the group
        array_pop($this->groupStack);
    }

    /**
     * Add a route to the registry
     *
     * @param string $method The HTTP method
     * @param string $pattern The URL pattern
     * @param callable|array|string $handler The route handler
     * @return Route The created route
     */
    private function addRoute(string $method, string $pattern, callable|array|string $handler): Route
    {
        // Apply group attributes
        $pattern = $this->applyGroupPrefix($pattern);
        $middleware = $this->getGroupMiddleware();

        // Create the route
        $route = new Route($method, $pattern, $handler);

        // Apply middleware from group
        foreach ($middleware as $m) {
            $route->middleware($m);
        }

        // Store the route
        $this->routes[$method][] = $route;

        return $route;
    }

    /**
     * Apply group prefix to pattern
     */
    private function applyGroupPrefix(string $pattern): string
    {
        if (empty($this->groupStack)) {
            return '/' . ltrim($pattern, '/');
        }

        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return $prefix . '/' . ltrim($pattern, '/');
    }

    /**
     * Get middleware from current group stack
     */
    private function getGroupMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        return $middleware;
    }

    /**
     * Merge parent and child group attributes
     */
    private function mergeGroupAttributes(array $parent, array $child): array
    {
        // Merge middleware
        if (isset($parent['middleware']) && isset($child['middleware'])) {
            $child['middleware'] = array_merge(
                (array) $parent['middleware'],
                (array) $child['middleware']
            );
        } elseif (isset($parent['middleware'])) {
            $child['middleware'] = $parent['middleware'];
        }

        // Merge prefix
        if (isset($parent['prefix']) && isset($child['prefix'])) {
            $child['prefix'] = trim($parent['prefix'], '/') . '/' . trim($child['prefix'], '/');
        } elseif (isset($parent['prefix'])) {
            $child['prefix'] = $parent['prefix'];
        }

        // Merge namespace
        if (isset($parent['namespace']) && isset($child['namespace'])) {
            $child['namespace'] = trim($parent['namespace'], '\\') . '\\' . trim($child['namespace'], '\\');
        } elseif (isset($parent['namespace'])) {
            $child['namespace'] = $parent['namespace'];
        }

        return $child;
    }

    /**
     * Dispatch a request to the appropriate handler
     *
     * @param Request $request The incoming request
     * @return mixed The handler response
     * @throws RouteNotFoundException If no matching route
     */
    public function dispatch(Request $request): mixed
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Find matching route
        $route = $this->matchRoute($method, $path);

        if ($route === null) {
            throw new RouteNotFoundException("No route found for {$method} {$path}");
        }

        $this->currentRoute = $route;

        // Extract parameters
        $params = $this->extractParameters($route, $path);

        // Merge with request parameters
        $request = $request->withRouteParams($params);

        // Emit RouteMatched event
        event(new \DGLab\Events\Routing\RouteMatched($route, $request));

        // Execute middleware pipeline
        $response = $this->runMiddleware($route, $request);

        // Emit RequestHandled event if response is a Response object
        if ($response instanceof \DGLab\Core\Response) {
            event(new \DGLab\Events\Routing\RequestHandled($request, $response));
        }

        return $response;
    }

    /**
     * Match a route for the given method and path
     */
    private function matchRoute(string $method, string $path): ?Route
    {
        $path = '/' . trim($path, '/');

        // Check method exists
        if (!isset($this->routes[$method])) {
            return null;
        }

        // Try each route
        foreach ($this->routes[$method] as $route) {
            if ($this->routeMatches($route, $path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Check if a route matches the given path
     */
    private function routeMatches(Route $route, string $path): bool
    {
        $pattern = $this->compileRoutePattern($route->getPattern());

        return (bool) preg_match($pattern, $path, $matches);
    }

    /**
     * Compile a route pattern to regex
     */
    private function compileRoutePattern(string $pattern): string
    {
        // Convert route parameters to regex
        // {param} -> (?P<param>[^/]+)
        // {param:\d+} -> (?P<param>\d+)
        // {param?} -> (?P<param>[^/]+)?

        $pattern = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}(\?)?/',
            function ($matches) {
                $name = $matches[1];
                $regex = $matches[2] ?? '[^/]+';
                $optional = isset($matches[3]) && $matches[3] === '?';

                if ($optional) {
                    return '(?P<' . $name . '>' . $regex . ')?';
                }

                return '(?P<' . $name . '>' . $regex . ')';
            },
            $pattern
        );

        // Handle wildcards
        $pattern = str_replace('*', '.*', $pattern);

        // Ensure leading slash
        $pattern = '/' . ltrim($pattern, '/');

        return '#^' . $pattern . '$#';
    }

    /**
     * Extract parameters from matched route
     */
    private function extractParameters(Route $route, string $path): array
    {
        $pattern = $this->compileRoutePattern($route->getPattern());

        preg_match($pattern, $path, $matches);

        // Filter named captures only
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $this->castParameter($value);
            }
        }

        return $params;
    }

    /**
     * Cast parameter to appropriate type
     */
    private function castParameter(string $value): mixed
    {
        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        // Float
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        // Boolean strings
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        return $value;
    }

    /**
     * Run middleware pipeline
     */
    private function runMiddleware(Route $route, Request $request): mixed
    {
        $middleware = array_merge(
            $this->globalMiddleware,
            $route->getMiddleware()
        );

        // Start with the handler
        $next = function ($request) use ($route) {
            return $this->executeHandler($route->getHandler(), $request);
        };

        // Wrap with middleware (in reverse order)
        foreach (array_reverse($middleware) as $m) {
            $next = function ($request) use ($m, $next) {
                return $this->executeMiddleware($m, $request, $next);
            };
        }

        return $next($request);
    }

    /**
     * Execute a single middleware
     */
    private function executeMiddleware(string $middleware, Request $request, callable $next): mixed
    {
        $instance = Application::getInstance()->get($middleware);

        return $instance->handle($request, $next);
    }

    /**
     * Execute the route handler
     */
    private function executeHandler(callable|array|string $handler, Request $request): mixed
    {
        // Controller action
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            $instance = is_string($controller)
                ? Application::getInstance()->get($controller)
                : $controller;

            return Application::getInstance()->call([$instance, $method], ['request' => $request]);
        }

        // Callable
        return Application::getInstance()->call($handler, ['request' => $request]);
    }

    /**
     * Register a named route
     *
     * @param string $name The route name
     * @param Route $route The route to name
     * @return void
     */
    public function name(string $name, Route $route): void
    {
        // Remove from any existing name
        foreach ($this->namedRoutes as $n => $r) {
            if ($r === $route) {
                unset($this->namedRoutes[$n]);
            }
        }
        $this->namedRoutes[$name] = $route;
    }

    /**
     * Get a named route
     */
    public function getNamedRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for a named route
     *
     * @param string $name The route name
     * @param array $parameters Route parameters
     * @return string The generated URL
     * @throws \RuntimeException If route not found
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getNamedRoute($name);

        if (!$route) {
            throw new \RuntimeException("Route '{$name}' not found");
        }

        $pattern = $route->getPattern();

        // Replace parameters
        $url = preg_replace_callback(
            '/\{(\w+)(?::[^}]+)?\}(\?)?/',
            function ($matches) use (&$parameters) {
                $name = $matches[1];
                $optional = isset($matches[2]) && $matches[2] === '?';

                if (isset($parameters[$name])) {
                    $value = $parameters[$name];
                    unset($parameters[$name]);
                    return urlencode($value);
                }

                if ($optional) {
                    return '';
                }

                throw new \RuntimeException("Missing required parameter '{$name}'");
            },
            $pattern
        );

        // Add remaining parameters as query string
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Add global middleware
     *
     * @param string|array $middleware Middleware class name(s)
     * @return $this
     */
    public function middleware(string|array $middleware): self
    {
        $this->globalMiddleware = array_merge(
            $this->globalMiddleware,
            (array) $middleware
        );

        return $this;
    }

    /**
     * Get the current route
     */
    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clear all routes (for testing)
     */
    public function clear(): void
    {
        $this->routes = array_fill_keys(array_keys($this->routes), []);
        $this->namedRoutes = [];
        $this->globalMiddleware = [];
        $this->groupStack = [];
        $this->currentRoute = null;
    }
}
