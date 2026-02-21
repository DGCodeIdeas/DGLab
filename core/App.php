<?php

namespace DGLab\Core;

use Exception;
use Throwable;

class App
{
    /**
     * The application instance.
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * The dependency injection container.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The configuration instance.
     *
     * @var Config
     */
    protected Config $config;

    /**
     * The router instance.
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Create a new application instance.
     *
     * @param string $basePath
     */
    protected function __construct(string $basePath)
    {
        $this->container = new Container();
        $this->container->singleton(App::class, fn() => $this);
        $this->container->singleton(Container::class, fn() => $this->container);

        $this->config = new Config($basePath . DIRECTORY_SEPARATOR . 'config');
        $this->container->singleton(Config::class, fn() => $this->config);

        $this->router = new Router();
        $this->container->singleton(Router::class, fn() => $this->router);

        static::$instance = $this;
    }

    /**
     * Get the application instance.
     *
     * @param string|null $basePath
     * @return static
     */
    public static function getInstance(?string $basePath = null): static
    {
        if (is_null(static::$instance)) {
            if (is_null($basePath)) {
                $basePath = dirname(__DIR__);
            }
            static::$instance = new static($basePath);
        }

        return static::$instance;
    }

    /**
     * Resolve a dependency from the container.
     *
     * @param string $abstract
     * @return mixed
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    /**
     * Register application routes.
     *
     * @param callable $callback
     * @return void
     */
    public function routes(callable $callback): void
    {
        $callback($this->router);
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->resolve($request);
            $response = $this->dispatch($route['handler'], $route['params']);
        } catch (Throwable $e) {
            $response = $this->handleException($e);
        }

        return $response;
    }

    /**
     * Dispatch to the controller handler.
     *
     * @param mixed $handler
     * @param array $params
     * @return Response
     * @throws Exception
     */
    protected function dispatch(mixed $handler, array $params): Response
    {
        if ($handler instanceof \Closure) {
            $content = call_user_func_array($handler, $params);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$controllerName, $method] = explode('@', $handler);

            // Add namespace if not present
            if (!str_starts_with($controllerName, 'DGLab\\')) {
                $controllerName = "DGLab\\App\\Controllers\\$controllerName";
            }

            $controller = $this->container->make($controllerName);

            if (!method_exists($controller, $method)) {
                throw new Exception("Method $method not found on controller $controllerName");
            }

            $content = call_user_func_array([$controller, $method], $params);
        } else {
            throw new Exception("Invalid handler type");
        }

        if ($content instanceof Response) {
            return $content;
        }

        return new Response($content);
    }

    /**
     * Handle an exception and return a response.
     *
     * @param Throwable $e
     * @return Response
     */
    protected function handleException(Throwable $e): Response
    {
        $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

        $debug = $this->config->get('app.debug', false);

        if ($debug) {
            $content = "<h1>Exception: " . $e->getMessage() . "</h1>";
            $content .= "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            $content = "<h1>Something went wrong</h1>";
        }

        return new Response($content, $status);
    }

    /**
     * Terminate the application.
     *
     * @param Response $response
     * @return void
     */
    public function terminate(Response $response): void
    {
        $response->send();
    }
}
