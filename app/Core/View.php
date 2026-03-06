<?php

/**
 * DGLab View Component
 *
 * Simple PHP-based templating system with layout support.
 * Can be extended to use Twig or other template engines.
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

/**
 * Class View
 *
 * Provides template rendering with:
 * - Layout support
 * - Partial includes
 * - Data escaping
 * - Section blocks
 */
class View
{
    /**
     * Views directory
     */
    private string $viewPath;

    /**
     * Layout directory
     */
    private string $layoutPath;

    /**
     * Shared data across all views
     */
    private array $shared = [];

    /**
     * Section content
     */
    private array $sections = [];

    /**
     * Current section being captured
     */
    private ?string $currentSection = null;


    /**
     * Constructor
     */
    public function __construct()
    {
        $basePath = Application::getInstance()->getBasePath();
        $this->viewPath = $basePath . '/resources/views';
        $this->layoutPath = $basePath . '/resources/views/layouts';
    }

    /**
     * Render a view template
     */
    public function render(string $template, array $data = [], ?string $layout = 'master'): string
    {
        // Merge shared data
        $data = array_merge($this->shared, $data);

        // Extract data for view
        extract($data);

        // Start output buffering
        ob_start();

        // Include the view file
        $viewFile = $this->viewPath . '/' . $this->normalizePath($template) . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        include $viewFile;

        // Get content
        $content = ob_get_clean();

        // Wrap in layout if specified
        if ($layout !== null) {
            $this->sections['content'] = $content;
            $content = $this->renderLayout($layout, $data);
        }

        return $content;
    }

    /**
     * Render a layout
     */
    private function renderLayout(string $layout, array $data): string
    {
        extract($data);

        ob_start();

        $layoutFile = $this->layoutPath . '/' . $this->normalizePath($layout) . '.php';

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        include $layoutFile;

        return ob_get_clean();
    }

    /**
     * Include a partial view
     */
    public function partial(string $name, array $data = []): void
    {
        extract($data);

        $partialFile = $this->viewPath . '/partials/' . $this->normalizePath($name) . '.php';

        if (!file_exists($partialFile)) {
            throw new \RuntimeException("Partial not found: {$name}");
        }

        include $partialFile;
    }

    /**
     * Start a section
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End a section
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \RuntimeException('No section started');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Yield a section
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Check if section exists
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Share data across all views
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Escape HTML entities
     */
    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Normalize path (prevent directory traversal)
     */
    private function normalizePath(string $path): string
    {
        // Remove any .. to prevent directory traversal
        $path = str_replace('..', '', $path);

        // Remove leading/trailing slashes
        return trim($path, '/');
    }

    /**
     * Get asset URL with cache busting
     */
    public function asset(string $path): string
    {
        try {
            $assetService = Application::getInstance()->get(\DGLab\Services\AssetService::class);
            return $assetService->getAssetUrl($path);
        } catch (\Exception $e) {
            return '/assets/' . ltrim($path, '/');
        }
    }

    /**
     * Generate URL for named route
     */
    public function route(string $name, array $parameters = []): string
    {
        $router = Application::getInstance()->get(Router::class);

        return $router->url($name, $parameters);
    }

    /**
     * Get config value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config($key, $default);
    }

    /**
     * Get old input value
     */
    public function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }

    /**
     * Get error for field
     */
    public function error(string $key): ?string
    {
        return $_SESSION['errors'][$key] ?? null;
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($_SESSION['errors']);
    }

    /**
     * Get all errors
     */
    public function errors(): array
    {
        return $_SESSION['errors'] ?? [];
    }

    /**
     * Get CSRF token
     */
    public function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Get CSRF field HTML
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->e($this->csrfToken()) . '">';
    }
}
