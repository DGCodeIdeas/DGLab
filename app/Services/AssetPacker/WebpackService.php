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

        $entryKey = $input['entry'];
        $config = config('assets.webpack');
        $entryFile = $config['entries'][$entryKey] ?? $entryKey;

        $basePath = Application::getInstance()->getBasePath();
        $jsPath = $basePath . DIRECTORY_SEPARATOR . ltrim($entryFile, DIRECTORY_SEPARATOR);

        if (!file_exists($jsPath)) {
            throw new \RuntimeException("Entry file not found: {$entryFile}");
        }

        $this->reportProgress($progressCallback, 30, 'Resolving dependencies');

        try {
            $dependencies = $this->resolver->resolve($jsPath);
        } catch (\Exception $e) {
            throw new \RuntimeException("Dependency resolution failed: " . $e->getMessage());
        }

        $this->reportProgress($progressCallback, 60, 'Bundling files');
        $bundleContent = $this->bundle($dependencies);

        $this->reportProgress($progressCallback, 80, 'Generating hash and manifest');
        $hash = substr(md5($bundleContent), 0, 12);
        $outputFilename = "$entryKey.$hash.js";
        $outputPath = $basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js';

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $this->cleanupOldVersions($outputPath, $entryKey);

        file_put_contents($outputPath . DIRECTORY_SEPARATOR . $outputFilename, $bundleContent);
        $this->updateManifest("$entryKey.js", "js/$outputFilename");

        $this->reportProgress($progressCallback, 100, 'Build complete');

        return [
            'success' => true,
            'entry' => $entryKey,
            'output' => $outputFilename,
            'hash' => $hash,
            'count' => count($dependencies),
        ];
    }

    private function bundle(array $dependencies): string
    {
        $bundle = "/** DGLab Asset Bundle - Generated " . date('Y-m-d H:i:s') . " **/
";
        $bundle .= "(function() {
";
        $bundle .= "  const modules = {};
";
        $bundle .= "  const cache = {};
";
        $bundle .= "  function require(id) {
";
        $bundle .= "    if (cache[id]) return cache[id].exports;
";
        $bundle .= "    const module = cache[id] = { exports: {} };
";
        $bundle .= "    modules[id](module, module.exports, require);
";
        $bundle .= "    return module.exports;
";
        $bundle .= "  }

";

        foreach ($dependencies as $path) {
            $id = $this->getModuleId($path);
            $content = file_get_contents($path);

            $content = $this->transformModule($content);

            $bundle .= "  modules['$id'] = function(module, exports, require) {
";
            $bundle .= $content . "
";
            $bundle .= "  };

";
        }

        $entryPath = end($dependencies);
        $entryId = $this->getModuleId($entryPath);
        $bundle .= "  require('$entryId');
";
        $bundle .= "})();";

        return $bundle;
    }

    private function transformModule(string $content): string
    {
        $content = preg_replace('/import\s+.*?\s+from\s+[\'"](.+?)[\'"]/', 'require("")', $content);
        $content = preg_replace('/export\s+const\s+(\w+)\s*=/', 'exports. =', $content);
        $content = preg_replace('/export\s+default\s+/', 'module.exports = ', $content);
        return $content;
    }

    private function getModuleId(string $path): string
    {
        $basePath = Application::getInstance()->getBasePath() . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR;
        $id = str_replace($basePath, '', $path);
        return str_replace(DIRECTORY_SEPARATOR, '/', $id);
    }

    private function updateManifest(string $key, string $value): void
    {
        $manifestPath = Application::getInstance()->getBasePath() . '/public/assets/manifest.json';
        $manifest = [];
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        }
        $manifest[$key] = $value;
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function cleanupOldVersions(string $dir, string $prefix): void
    {
        foreach (glob("$dir/$prefix.*.js") as $file) {
            @unlink($file);
        }
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
