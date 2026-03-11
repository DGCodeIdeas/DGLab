<?php

namespace DGLab\Services\AssetPacker;

use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ServiceInterface;
use DGLab\Core\Exceptions\ValidationException;
use DGLab\Core\Application;

/**
 * WebpackService
 *
 * Primary interface for the PHP Asset Bundler.
 * Handles dependency resolution, and eventually bundling and minification.
 */
class WebpackService extends BaseService implements ServiceInterface
{
    private DependencyResolverInterface $resolver;

    public function __construct(?DependencyResolverInterface $resolver = null)
    {
        parent::__construct();
        $this->resolver = $resolver ?? new DependencyResolver();
    }

    public function getId(): string
    {
        return 'webpack';
    }

    public function getName(): string
    {
        return 'PHP Asset Bundler';
    }

    public function getDescription(): string
    {
        return 'Resolves and bundles JavaScript dependencies entirely in PHP.';
    }

    public function getIcon(): string
    {
        return 'bi-box-seam';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entry' => ['type' => 'string'],
            ],
            'required' => ['entry'],
        ];
    }

    public function validate(array $input): array
    {
        return $this->validateAgainstSchema($input, $this->getInputSchema());
    }

    public function process(array $input, ?callable $progressCallback = null): array
    {
        $this->reportProgress($progressCallback, 10, 'Initializing resolution');

        $entry = $input['entry'];
        $basePath = Application::getInstance()->getBasePath();
        $resourcesPath = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js';
        $jsPath = $resourcesPath . DIRECTORY_SEPARATOR . ltrim($entry, DIRECTORY_SEPARATOR);

        if (!str_ends_with($jsPath, '.js')) {
            $jsPath .= '.js';
        }

        if (!file_exists($jsPath)) {
            throw new \RuntimeException("Entry file not found: {$entry}");
        }

        $this->reportProgress($progressCallback, 30, 'Resolving dependencies');

        try {
            $dependencies = $this->resolver->resolve($jsPath);
        } catch (\Exception $e) {
            throw new \RuntimeException("Dependency resolution failed: " . $e->getMessage());
        }

        $this->reportProgress($progressCallback, 100, 'Resolution complete');

        // For Phase One, we just return the list of dependencies.
        // Relative paths from project root for readability.
        $relativeDependencies = array_map(function ($path) use ($basePath) {
            $prefix = $basePath . DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
            return $path;
        }, $dependencies);

        return [
            'success' => true,
            'entry' => $entry,
            'dependencies' => $relativeDependencies,
            'count' => count($relativeDependencies),
        ];
    }

    public function supportsChunking(): bool
    {
        return false;
    }

    public function estimateTime(array $input): int
    {
        return 2; // Usually very fast
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
