<?php

namespace DGLab\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler, ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, $handler, ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    private function addRoute(string $method, string $path, $handler, ?string $name = null): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'name' => $name
        ];
    }

    public function dispatch(Request $request)
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->callHandler($route['handler'], $params);
            }
        }

        throw new \DGLab\Core\Exceptions\RouteNotFoundException("Route not found: $path");
    }

    private function callHandler($handler, array $params)
    {
        if (is_callable($handler)) {
            return $handler(...array_values($params));
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = Application::getInstance()->get($class);
            return $controller->$method($params);
        }

        return $handler;
    }
}
