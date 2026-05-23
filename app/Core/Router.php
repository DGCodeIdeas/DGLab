<?php

namespace DGLab\Core;

use DGLab\Core\Exceptions\RouteNotFoundException;
use DGLab\Services\AssetService;
use DGLab\Core\Contracts\ResponseFactoryInterface;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    private array $namedRoutes = [];
    private array $globalMiddleware = [];
    private array $groupStack = [];
    private ?Route $currentRoute = null;

    public function get(string $pattern, $handler, ?string $name = null): Route
    {
        return $this->addRoute('GET', $pattern, $handler, $name);
    }

    public function post(string $pattern, $handler, ?string $name = null): Route
    {
        return $this->addRoute('POST', $pattern, $handler, $name);
    }

    public function put(string $pattern, $handler, ?string $name = null): Route
    {
        return $this->addRoute('PUT', $pattern, $handler, $name);
    }

    public function patch(string $pattern, $handler, ?string $name = null): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler, $name);
    }

    public function delete(string $pattern, $handler, ?string $name = null): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler, $name);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function addRoute(string $method, string $pattern, $handler, ?string $name = null): Route
    {
        $pattern = $this->applyGroupAttributes($pattern);
        $middleware = $this->getGroupMiddleware();

        $route = new Route($method, $pattern, $handler);
        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        $this->routes[$method][] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        return $route;
    }

    private function applyGroupAttributes(string $pattern): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return $prefix . '/' . trim($pattern, '/');
    }

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

    public function dispatch(Request $request): mixed
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        if (str_starts_with($path, "/assets/")) {
            $parts = explode("/", trim($path, "/"));
            if (count($parts) >= 3) {
                $type = $parts[1];
                $file = implode("/", array_slice($parts, 2));
                Application::getInstance()->get(AssetService::class)->serveAsset($type, $file);
                return Application::getInstance()->get(ResponseFactoryInterface::class)->create("", 200);
            }
        }
        // Find matching route
        $route = $this->matchRoute($method, $path);

        if ($route === null && $method === 'HEAD') {
            $route = $this->matchRoute('GET', $path);
        }

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

    private function matchRoute(string $method, string $path): ?Route
    {
        $path = '/' . trim($path, '/');

        if (!isset($this->routes[$method])) {
            if ($method === 'HEAD' && isset($this->routes['GET'])) {
                $method = 'GET';
            } else {
                return null;
            }
        }

        foreach ($this->routes[$method] as $route) {
            if ($this->routeMatches($route, $path)) {
                return $route;
            }
        }

        return null;
    }

    private function routeMatches(Route $route, string $path): bool
    {
        $pattern = $this->compileRoutePattern($route->getPattern());

        return (bool) preg_match($pattern, $path, $matches);
    }

    private function compileRoutePattern(string $pattern): string
    {
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

        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/' . ltrim($pattern, '/');

        return '#^' . $pattern . '$#';
    }

    private function extractParameters(Route $route, string $path): array
    {
        $pattern = $this->compileRoutePattern($route->getPattern());

        preg_match($pattern, $path, $matches);

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $this->castParameter($value);
            }
        }

        return $params;
    }

    private function castParameter(string $value): mixed
    {
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        return $value;
    }

    private function runMiddleware(Route $route, Request $request): mixed
    {
        $middleware = array_merge(
            $this->globalMiddleware,
            $route->getMiddleware()
        );

        $next = function ($request) use ($route) {
            return $this->executeHandler($route->getHandler(), $request);
        };

        foreach (array_reverse($middleware) as $m) {
            $next = function ($request) use ($m, $next) {
                return $this->executeMiddleware($m, $request, $next);
            };
        }

        return $next($request);
    }

    private function executeMiddleware(string $middleware, Request $request, callable $next): mixed
    {
        $instance = Application::getInstance()->get($middleware);

        return $instance->handle($request, $next);
    }

    private function executeHandler(callable|array|string $handler, Request $request): mixed
    {
        $args = array_merge($request->routeParams(), ['request' => $request]);

        if (is_array($handler)) {
            [$controller, $method] = $handler;
            $instance = is_string($controller)
                ? Application::getInstance()->get($controller)
                : $controller;

            if ($instance instanceof \DGLab\Core\Controller) {
                $instance->setRequest($request);
            }

            return Application::getInstance()->call([$instance, $method], $args);
        }

        return Application::getInstance()->call($handler, $args);
    }

    public function name(string $name, Route $route): void
    {
        foreach ($this->namedRoutes as $n => $r) {
            if ($r === $route) {
                unset($this->namedRoutes[$n]);
            }
        }
        $this->namedRoutes[$name] = $route;
    }

    public function getNamedRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getNamedRoute($name);

        if (!$route) {
            throw new \RuntimeException("Route '{$name}' not found");
        }

        $pattern = $route->getPattern();

        $url = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}(\?)?/',
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

        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    public function middleware(string|array $middleware): self
    {
        $this->globalMiddleware = array_merge(
            $this->globalMiddleware,
            (array) $middleware
        );

        return $this;
    }

    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function clear(): void
    {
        $this->routes = array_fill_keys(array_keys($this->routes), []);
        $this->namedRoutes = [];
        $this->globalMiddleware = [];
        $this->groupStack = [];
        $this->currentRoute = null;
    }
}
