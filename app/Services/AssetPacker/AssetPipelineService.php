<?php

namespace DGLab\Services\AssetPacker;

use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ServiceInterface;
use DGLab\Core\Exceptions\ValidationException;
use DGLab\Core\Application;
use MatthiasMullie\Minify;

/**
 * AssetPipelineService
 *
 * Primary interface for the PHP Asset Pipeline.
 * Handles ESM-first asset processing, optional minification, and cache-busting.
 */
class AssetPipelineService extends BaseService implements ServiceInterface
{
    private DependencyResolverInterface $resolver;

    public function __construct(?DependencyResolverInterface $resolver = null)
    {
        parent::__construct();
        $this->resolver = $resolver ?? new DependencyResolver();
    }

    public function getId(): string
    {
        return 'pipeline';
    }

    public function getName(): string
    {
        return 'PHP Asset Pipeline';
    }

    public function getDescription(): string
    {
        return 'Processes JavaScript and CSS assets entirely in PHP.';
    }

    public function getIcon(): string
    {
        return 'bi-lightning-charge';
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
        $this->reportProgress($progressCallback, 10, "Initializing pipeline");

        $entryKey = $input['entry'];
        $config = config('assets.pipeline');
        $entryFile = $config['entries'][$entryKey] ?? $entryKey;

        $basePath = Application::getInstance()->getBasePath();
        $jsPath = $basePath . DIRECTORY_SEPARATOR . ltrim($entryFile, DIRECTORY_SEPARATOR);

        if (!file_exists($jsPath)) {
            throw new \RuntimeException("Entry file not found: {$entryFile}");
        }

        $mode = $config['mode'] ?? 'esm';

        if ($mode === 'bundle') {
            return $this->processBundle($entryKey, $jsPath, $config, $progressCallback);
        }

        return $this->processEsm($entryKey, $jsPath, $config, $progressCallback);
    }

    private function processEsm(string $entryKey, string $entryPath, array $config, ?callable $progressCallback): array
    {
        $this->reportProgress($progressCallback, 30, 'Resolving ESM dependencies');
        $dependencies = $this->resolver->resolve($entryPath);

        $this->reportProgress($progressCallback, 60, 'Processing individual files');
        $basePath = Application::getInstance()->getBasePath();
        $assetsPath = $basePath . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "assets";
        $outputPath = $assetsPath . DIRECTORY_SEPARATOR . "js";

        foreach ($dependencies as $path) {
            $content = file_get_contents($path);
            $relPath = $this->getRelativePath($path);

            if ($config['optimization']['minify'] ?? true) {
                $minifier = new Minify\JS();
                $minifier->add($content);
                $content = $minifier->minify();
            }

            $target = $outputPath . DIRECTORY_SEPARATOR . $relPath;
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
            file_put_contents($target, $content);
        }

        $this->updateManifest("$entryKey.js", "js/" . $this->getRelativePath($entryPath));

        $this->reportProgress($progressCallback, 100, 'ESM Build complete');

        return [
            'success' => true,
            'entry' => $entryKey,
            'mode' => 'esm',
            'count' => count($dependencies),
        ];
    }

    private function processBundle(string $entryKey, string $entryPath, array $config, ?callable $progressCallback): array
    {
        $this->reportProgress($progressCallback, 30, 'Resolving dependencies for bundle');
        $dependencies = $this->resolver->resolve($entryPath);

        $this->reportProgress($progressCallback, 50, 'Bundling files');
        $bundleResult = $this->bundle($dependencies);
        $bundleContent = $bundleResult['content'];
        $sourceMap = $bundleResult['map'];

        if ($config['optimization']['minify'] ?? true) {
            $this->reportProgress($progressCallback, 70, 'Minifying');
            $minifier = new Minify\JS();
            $minifier->add($bundleContent);
            $bundleContent = $minifier->minify();
        }

        $hash = substr(md5($bundleContent), 0, 12);
        $outputFilename = "$entryKey.$hash.js";
        $mapFilename = "$outputFilename.map";

        $bundleContent .= "\n//# sourceMappingURL=$mapFilename";
        $basePath = Application::getInstance()->getBasePath();
        $outputPath = $basePath . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "js";

        if (!is_dir($outputPath)) mkdir($outputPath, 0755, true);
        $this->cleanupOldVersions($outputPath, $entryKey);

        file_put_contents($outputPath . DIRECTORY_SEPARATOR . $outputFilename, $bundleContent);
        file_put_contents($outputPath . DIRECTORY_SEPARATOR . $mapFilename, json_encode($sourceMap));

        $this->updateManifest("$entryKey.js", "js/$outputFilename");
        $this->reportProgress($progressCallback, 100, 'Bundle Build complete');

        return [
            'success' => true,
            'entry' => $entryKey,
            'mode' => 'bundle',
            'output' => $outputFilename,
            'hash' => $hash,
        ];
    }

    private function bundle(array $dependencies): array
    {
        $content = "/** DGLab Legacy Bundle **/\n(function() {\n  const modules = {};\n  const cache = {};\n  function require(id) {\n    if (cache[id]) return cache[id].exports;\n    const module = cache[id] = { exports: {} };\n    modules[id](module, module.exports, require);\n    return module.exports;\n  }\n";

        foreach ($dependencies as $path) {
            $id = $this->getModuleId($path);
            $fileContent = $this->transformModule(file_get_contents($path));
            $content .= "  modules['$id'] = function(module, exports, require) {\n$fileContent\n  };\n";
        }

        $entryPath = end($dependencies);
        $entryId = $this->getModuleId($entryPath);
        $content .= "  require('$entryId');\n})();";

        return ['content' => $content, 'map' => ['version' => 3, 'mappings' => '']];
    }

    private function transformModule(string $content): string
    {
        $content = preg_replace('/import\s+.*?\s+from\s+[\'"](.+?)[\'"]/', 'require("")', $content);
        $content = preg_replace('/export\s+const\s+(\w+)\s*=/', 'exports.$1 =', $content);
        $content = preg_replace('/export\s+default\s+/', 'module.exports = ', $content);
        return $content;
    }

    private function getModuleId(string $path): string
    {
        $basePath = Application::getInstance()->getBasePath();
        return str_replace([$basePath . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ["", "/"], $path);
    }

    private function getRelativePath(string $path): string
    {
        $basePath = Application::getInstance()->getBasePath();
        $resourcesPath = $basePath . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR;
        return str_replace($resourcesPath, "", $path);
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
        return 2;
    }

    public function getConfig(): array
    {
        return config('assets.pipeline');
    }
}
