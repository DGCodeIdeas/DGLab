<?php

namespace DGLab\Core;

/**
 * Route class
 *
 * Represents a single route with its pattern, handler, and metadata.
 */
class Route
{
    private string $method;
    private string $pattern;
    private mixed $handler;
    private array $middleware = [];
    private ?string $name = null;

    public function __construct(string $method, string $pattern, mixed $handler)
    {
        $this->method = $method;
        $this->pattern = $pattern;
        $this->handler = $handler;
    }

    /**
     * Add middleware to this route
     */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    /**
     * Name this route
     */
    public function name(string $name): self
    {
        $this->name = $name;

        if (Application::getInstance()->has(Router::class)) {
            Application::getInstance()->get(Router::class)->name($name, $this);
        }

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
