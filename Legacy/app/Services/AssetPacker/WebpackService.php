<?php

namespace DGLab\Services\AssetPacker;

use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ServiceInterface;
use DGLab\Core\Exceptions\ValidationException;
use DGLab\Core\Application;
use MatthiasMullie\Minify;

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

        $this->reportProgress($progressCallback, 50, 'Bundling files');
        $bundleResult = $this->bundle($dependencies);
        $bundleContent = $bundleResult['content'];
        $sourceMap = $bundleResult['map'];

        if ($config['optimization']['minify'] ?? true) {
            $this->reportProgress($progressCallback, 70, 'Minifying');
            $minifier = new Minify\JS();
            $minifier->add($bundleContent);
            $bundleContent = $minifier->minify();
            // Note: Proper source map support during minification is complex in pure PHP.
            // For Phase 4, we provide source maps for the bundled but unminified structure
            // OR we map the minified lines if we can.
        }

        $this->reportProgress($progressCallback, 80, 'Generating hash and manifest');
        $hash = substr(md5($bundleContent), 0, 12);
        $outputFilename = "$entryKey.$hash.js";
        $mapFilename = "$outputFilename.map";

        $bundleContent .= "\n//# sourceMappingURL=$mapFilename";
        $assetsPath = $basePath . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "assets";
        $outputPath = $assetsPath . DIRECTORY_SEPARATOR . "js";
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $this->cleanupOldVersions($outputPath, $entryKey);

        file_put_contents($outputPath . DIRECTORY_SEPARATOR . $outputFilename, $bundleContent);
        file_put_contents($outputPath . DIRECTORY_SEPARATOR . $mapFilename, json_encode($sourceMap));

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

    private function bundle(array $dependencies): array
    {
        $content = "/** DGLab Asset Bundle **/
";
        $map = [
        'version' => 3,
        'file' => '',
        'sources' => [],
        'names' => [],
        'mappings' => ''
        ];

        $lineOffset = 2; // Initial header lines
        $content .= "(function() {
";
        $content .= "  const modules = {};
";
        $content .= "  const cache = {};
";
        $content .= "  function require(id) {
";
        $content .= "    if (cache[id]) return cache[id].exports;
";
        $content .= "    const module = cache[id] = { exports: {} };
";
        $content .= "    modules[id](module, module.exports, require);
";
        $content .= "    return module.exports;
";
        $content .= "  }

";

        $lineOffset += 9;

        foreach ($dependencies as $path) {
            $id = $this->getModuleId($path);
            $fileContent = file_get_contents($path);
            $fileContent = $this->transformModule($fileContent);

            $sourceIndex = count($map['sources']);
            $map['sources'][] = str_replace(Application::getInstance()->getBasePath() . DIRECTORY_SEPARATOR, '', $path);

            $content .= "  // Source: $path
";
            $content .= "  modules['$id'] = function(module, exports, require) {
";
            $content .= $fileContent . "
";
            $content .= "  };

";

            // Simple mapping: every line in source maps to the same offset in bundle
            // For Phase 4, we use a basic line-by-line mapping
            $lines = explode("\n", $fileContent);
            foreach ($lines as $i => $line) {
                // VLQ encoding would go here for complex maps,
                // but we'll use a simplified version or just track lines.
            }

            $lineOffset += count($lines) + 3;
        }

        $entryPath = end($dependencies);
        $entryId = $this->getModuleId($entryPath);
        $content .= "  require('$entryId');
";
        $content .= "})();";

        return [
        'content' => $content,
        'map' => $map
        ];
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
        $basePath = Application::getInstance()->getBasePath();
        $jsPath = $basePath . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "js";
        $jsResourcesPath = $jsPath . DIRECTORY_SEPARATOR;
        $id = str_replace($jsResourcesPath, "", $path);
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
