<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\ViewEngineInterface;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Services\Superpowers\Runtime\CleanupManager;

class View
{
    private string $viewPath;
    private string $layoutPath;
    private array $shared = [];
    private array $sections = [];
    private ?string $fragmentMode = null;
    private ?string $currentSection = null;
    private array $engines = [];
    private array $hooks = [];

    public function __construct()
    {
        $basePath = Application::getInstance()->getBasePath();
        $this->viewPath = $basePath . '/resources/views';
        $this->layoutPath = $basePath . '/resources/views/layouts';
        $this->registerEngine('super.php', new SuperpowersEngine($this));
    }

    public function registerEngine(string $extension, ViewEngineInterface $engine): void
    {
        $this->engines[$extension] = $engine;
    }

    public function getEngine(string $extension = 'super.php'): ?ViewEngineInterface
    {
        return $this->engines[$extension] ?? null;
    }

    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    public function setFragmentMode(?string $sectionName): void
    {
        $this->fragmentMode = $sectionName;
    }

    public function render(string $template, array $data = [], ?string $layout = 'shell'): string
    {
        $data = array_merge($this->shared, $data);
        [$viewFile, $engine] = $this->resolveView($template);

        $content = $engine->render($viewFile, $data);

        if ($this->fragmentMode !== null) {
            return $this->yield($this->fragmentMode);
        }

        if ($layout !== null) {
            if (!$this->hasSection('content')) {
                $this->sections['content'] = $content;
            }
            $content = $this->renderLayout($layout, $data);
        }

        CleanupManager::getInstance()->cleanup();
        $this->trigger('cleanup');

        return $content;
    }

    public function resolveView(string $template, string $directory = null): array
    {
        if ($directory === null) {
            $directory = $this->viewPath;
        }

        $normalizedTemplate = str_replace('.', '/', $this->normalizePath($template));
        $basePath = $directory . '/' . $normalizedTemplate;
        $ext = 'super.php';

        $candidates = [
            $basePath . '.' . $ext,
            $this->viewPath . '/' . $normalizedTemplate . '.' . $ext,
            $this->layoutPath . '/' . $normalizedTemplate . '.' . $ext,
            $this->viewPath . '/components/' . $normalizedTemplate . '.' . $ext,
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                return [$file, $this->engines[$ext]];
            }
        }

        throw new \RuntimeException("View not found: {$template} at {$basePath}.{$ext}");
    }

    private function renderLayout(string $layout, array $data): string
    {
        [$layoutFile, $engine] = $this->resolveView($layout, $this->layoutPath);
        return $engine->render($layoutFile, $data);
    }

    public function partial(string $name, array $data = []): void
    {
        [$partialFile, $engine] = $this->resolveView('partials/' . $name, $this->viewPath);
        echo $engine->render($partialFile, array_merge($this->shared, $data));
    }

    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \RuntimeException('No section started');
        }
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    public function yield(string $name, ?string $default = ''): string
    {
        return $this->sections[$name] ?? ($default ?? '');
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('..', '', $path);
        return trim($path, '/');
    }

    public function asset(string $path): string
    {
        try {
            $assetService = Application::getInstance()->get(\DGLab\Services\AssetService::class);
            return $assetService->getAssetUrl($path);
        } catch (\Exception $e) {
            return '/assets/' . ltrim($path, '/');
        }
    }

    public function route(string $name, array $parameters = []): string
    {
        $router = Application::getInstance()->get(Router::class);
        return $router->url($name, $parameters);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config($key, $default);
    }

    public function on(string $event, callable $callback): void
    {
        $this->hooks[$event][] = $callback;
    }

    public function trigger(string $event, ...$args): void
    {
        foreach ($this->hooks[$event] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
