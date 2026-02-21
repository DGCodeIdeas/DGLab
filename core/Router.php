<?php

namespace DGLab\Core;

use Closure;
use Exception;

class Router
{
    /**
     * The registered routes.
     *
     * @var array
     */
    protected array $routes = [];

    /**
     * The current route group attributes.
     *
     * @var array
     */
    protected array $groupStack = [];

    /**
     * Register a GET route.
     *
     * @param string $path
     * @param mixed $handler
     * @return void
     */
    public function get(string $path, mixed $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $path
     * @param mixed $handler
     * @return void
     */
    public function post(string $path, mixed $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param string $path
     * @param mixed $handler
     * @return void
     */
    public function put(string $path, mixed $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path
     * @param mixed $handler
     * @return void
     */
    public function delete(string $path, mixed $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Add a route to the collection.
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @return void
     */
    protected function addRoute(string $method, string $path, mixed $handler): void
    {
        $path = $this->applyGroupAttributes($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'regex' => $this->compileRegex($path)
        ];
    }

    /**
     * Create a route group.
     *
     * @param array $attributes
     * @param Closure $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Apply current group attributes to a path.
     *
     * @param string $path
     * @return string
     */
    protected function applyGroupAttributes(string $path): string
    {
        if (empty($this->groupStack)) {
            return '/' . ltrim($path, '/');
        }

        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return $prefix . '/' . ltrim($path, '/');
    }

    /**
     * Compile a path to a regex.
     *
     * @param string $path
     * @return string
     */
    protected function compileRegex(string $path): string
    {
        // Convert {id} to (?P<id>[^/]+)
        // We can support specific patterns mentioned in ARCHITECTURE.json
        $patterns = [
            '{id}' => '(?P<id>\d+)',
            '{slug}' => '(?P<slug>[a-z0-9\-]+)',
            '{any}' => '(?P<any>.*)',
            '{file}' => '(?P<file>[a-zA-Z0-9\-\_\.]+\.[a-zA-Z0-9]+)'
        ];

        $regex = str_replace(array_keys($patterns), array_values($patterns), $path);

        // Handle generic {param}
        $regex = preg_replace('/\{([a-zA-Z0-9\_]+)\}/', '(?P<\1>[^/]+)', $regex);

        return '#^' . $regex . '$#s';
    }

    /**
     * Resolve the route for the given request.
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function resolve(Request $request): array
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['regex'], $path, $matches)) {
                // Extract parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $route['handler'],
                    'params' => $params
                ];
            }
        }

        throw new Exception("Route not found: $method $path", 404);
    }
}
