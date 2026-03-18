<?php

/**
 * DGLab View Component
 *
 * Extensible templating system with support for multiple engines.
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

use DGLab\Core\Contracts\ViewEngineInterface;
use DGLab\Services\Superpowers\SuperpowersEngine;

/**
 * Class View
 *
 * Provides template rendering with support for multiple engines:
 * - Layout support
 * - Partial includes
 * - Data escaping
 * - Section blocks
 * - Support for .php and .super.php extensions
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
     * Registered view engines
     *
     * @var array<string, ViewEngineInterface>
     */
    private array $engines = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $basePath = Application::getInstance()->getBasePath();
        $this->viewPath = $basePath . '/resources/views';
        $this->layoutPath = $basePath . '/resources/views/layouts';

        // Register default engines
        $this->registerEngine('php', new PhpEngine());
        $this->registerEngine('super.php', new SuperpowersEngine());
    }

    /**
     * Register a view engine for a given extension.
     */
    public function registerEngine(string $extension, ViewEngineInterface $engine): void
    {
        $this->engines[$extension] = $engine;
    }

    /**
     * Render a view template
     */
    public function render(string $template, array $data = [], ?string $layout = 'master'): string
    {
        // Merge shared data
        $data = array_merge($this->shared, $data);

        // Find the view file and appropriate engine
        [$viewFile, $engine] = $this->resolveView($template);

        // Render the view using the engine
        $content = $engine->render($viewFile, $data);

        // Wrap in layout if specified
        if ($layout !== null) {
            $this->sections['content'] = $content;
            $content = $this->renderLayout($layout, $data);
        }

        return $content;
    }

    /**
     * Resolve a view template to its file path and rendering engine.
     *
     * @param string $template
     * @return array{0: string, 1: ViewEngineInterface}
     */
    private function resolveView(string $template, string $directory = null): array
    {
        if ($directory === null) {
            $directory = $this->viewPath;
        }

        $basePath = $directory . '/' . $this->normalizePath($template);

        // Check for engines in order (prioritize super.php)
        $extensions = array_keys($this->engines);

        // Ensure super.php is checked first if registered
        usort($extensions, function($a, $b) {
            if ($a === 'super.php') return -1;
            if ($b === 'super.php') return 1;
            return 0;
        });

        foreach ($extensions as $ext) {
            $file = $basePath . '.' . $ext;
            if (file_exists($file)) {
                return [$file, $this->engines[$ext]];
            }
        }

        throw new \RuntimeException("View not found: {$template}");
    }

    /**
     * Render a layout
     */
    private function renderLayout(string $layout, array $data): string
    {
        [$layoutFile, $engine] = $this->resolveView($layout, $this->layoutPath);
        return $engine->render($layoutFile, $data);
    }

    /**
     * Include a partial view
     */
    public function partial(string $name, array $data = []): void
    {
        [$partialFile, $engine] = $this->resolveView('partials/' . $name, $this->viewPath);
        echo $engine->render($partialFile, array_merge($this->shared, $data));
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
