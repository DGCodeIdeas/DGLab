<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\RequestHandlerInterface;
use DGLab\Core\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Pipeline implements RequestHandlerInterface
{
    protected array $middleware = [];
    protected $handler;
    protected int $index = 0;

    public function send(ServerRequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(callable $handler): ResponseInterface
    {
        $this->handler = $handler;
        $this->index = 0;
        return $this->handle($this->request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middleware)) {
            return ($this->handler)($request);
        }

        $middleware = $this->middleware[$this->index++];

        if (is_string($middleware)) {
            $middleware = Application::getInstance()->get($middleware);
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        // Handle legacy closure middleware
        return $middleware->handle($request, function($req) {
            return $this->handle($req);
        });
    }
}
