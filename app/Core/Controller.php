<?php

/**
 * DGLab Base Controller
 *
 * Abstract base class for all application controllers.
 * Provides common functionality and helper methods for request handling.
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

/**
 * Class Controller
 *
 * Base controller providing:
 * - Request/Response property injection
 * - Helper methods for common responses
 * - Middleware hook points
 * - View rendering
 * - Validation integration
 */
abstract class Controller
{
    /**
     * The current request
     */
    protected Request $request;

    /**
     * The response instance
     */
    protected Response $response;

    /**
     * Application container
     */
    protected Application $app;

    /**
     * Middleware to apply to controller actions
     */
    protected array $middleware = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->app = Application::getInstance();
    }

    /**
     * Set the request instance
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the request instance
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Set the response instance
     */
    public function setResponse(Response $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get the response instance
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Return a JSON response
     */
    protected function json(array $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }

    /**
     * Return a view response
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $view = $this->app->get(View::class);
        $content = $view->render($template, $data);

        return new Response($content, $status);
    }

    /**
     * Return a redirect response
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Redirect to a named route
     */
    protected function redirectToRoute(string $name, array $parameters = [], int $status = 302): Response
    {
        $router = $this->app->get(Router::class);
        $url = $router->url($name, $parameters);

        return $this->redirect($url, $status);
    }

    /**
     * Return a file download response
     * @deprecated Use Download::file() or DownloadManager::download()
     */
    protected function download(string $file, ?string $name = null, array $headers = []): Response
    {
        return Response::download($file, $name, $headers);
    }

    /**
     * Return a file stream response
     * @deprecated Use DownloadManager::stream()
     */
    protected function stream(string $file, ?string $name = null, array $headers = []): Response
    {
        return Response::stream($file, $name, $headers);
    }

    /**
     * Return a no-content response
     */
    protected function noContent(): Response
    {
        return Response::noContent();
    }

    /**
     * Validate request input
     */
    protected function validate(array $rules): array
    {
        $validator = new Validator($this->request);

        return $validator->validate($rules);
    }

    /**
     * Add middleware to this controller
     */
    protected function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * Get controller middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the authenticated user (if any)
     */
    protected function user(): ?array
    {
        // To be implemented with authentication system
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required', 401);
        }
    }

    /**
     * Flash a message to the session
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Get flashed messages
     */
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

    /**
     * Get input value with old data fallback
     */
    protected function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }

    /**
     * Store input in session for old() fallback
     */
    protected function flashInput(array $input): void
    {
        $_SESSION['old'] = $input;
    }

    /**
     * Clear old input
     */
    protected function clearOldInput(): void
    {
        unset($_SESSION['old']);
    }

    /**
     * Generate CSRF token
     */
    protected function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Get CSRF token field HTML
     */
    protected function csrfField(): string
    {
        $token = $this->csrfToken();

        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Abort with an error response
     */
    protected function abort(int $code, string $message = ''): never
    {
        throw new \RuntimeException($message, $code);
    }

    /**
     * Called before action execution
     * Override in child classes for setup
     */
    protected function beforeAction(string $action): void
    {
        // Override in child classes
    }

    /**
     * Called after action execution
     * Override in child classes for cleanup
     */
    protected function afterAction(string $action, mixed $result): void
    {
        // Override in child classes
    }
}
