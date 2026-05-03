<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\ResponseFactoryInterface;
use DGLab\Services\Auth\AuthManager;

abstract class Controller
{
    protected Request $request;
    protected Response $response;
    protected Application $app;
    protected array $middleware = [];

    public function __construct()
    {
        $this->app = Application::getInstance();
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }
    public function getRequest(): Request
    {
        return $this->request;
    }
    public function setResponse(Response $response): self
    {
        $this->response = $response;
        return $this;
    }
    public function getResponse(): Response
    {
        return $this->response;
    }

    protected function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->app->get(ResponseFactoryInterface::class);
    }

    protected function json(array $data, int $status = 200, array $headers = []): Response
    {
        return $this->getResponseFactory()->json($data, $status, $headers);
    }
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $view = $this->app->get(View::class);

        $fragment = $this->request->getHeader('X-Superpowers-Fragment');
        if ($fragment) {
            $view->setFragmentMode($fragment === 'true' ? 'content' : $fragment);
        }

        return $this->getResponseFactory()->create($view->render($template, $data), $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return $this->getResponseFactory()->redirect($url, $status);
    }
    protected function redirectToRoute(string $name, array $parameters = [], int $status = 302): Response
    {
        $router = $this->app->get(Router::class);
        return $this->redirect($router->url($name, $parameters), $status);
    }

    protected function noContent(): Response
    {
        return Response::noContent();
    }
    protected function validate(array $rules): array
    {
        $validator = new Validator($this->request);
        return $validator->validate($rules);
    }

    protected function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    protected function auth(): AuthManager
    {
        return $this->app->get(AuthManager::class);
    }
    protected function user()
    {
        return $this->auth()->user();
    }
    protected function isAuthenticated(): bool
    {
        return $this->auth()->check();
    }
    protected function can(string $ability, array $arguments = []): bool
    {
        return $this->auth()->can($ability, $arguments);
    }

    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required', 401);
        }
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }
    protected function getFlash(?string $type = null): ?string
    {
        if ($type === null) {
            $flash = $_SESSION['flash'] ?? [];
            unset($_SESSION['flash']);
            return $flash;
        }
        $message = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $message;
    }

    protected function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }
    protected function flashInput(array $input): void
    {
        $_SESSION['old'] = $input;
    }
    protected function deleteOldInput(): void
    {
        unset($_SESSION['old']);
    }

    protected function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    protected function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->csrfToken()) . '">';
    }

    protected function abort(int $code, string $message = ''): never
    {
        throw new \RuntimeException($message, $code);
    }
}
