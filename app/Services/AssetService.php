<?php

namespace DGLab\Services;

use ScssPhp\ScssPhp\Compiler;
use MatthiasMullie\Minify;
use DGLab\Core\Application;

class AssetService extends BaseService
{
    private string $cachePath;
    private string $scssPath;
    private string $jsPath;

    public function __construct()
    {
        parent::__construct();
        $app = Application::getInstance();
        $this->cachePath = $app->getBasePath() . '/storage/cache/assets';
        $this->scssPath = $app->getBasePath() . '/resources/scss';
        $this->jsPath = $app->getBasePath() . '/resources/js';

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function getId(): string
    {
        return 'asset-service';
    }

    public function getName(): string
    {
        return 'Asset Service';
    }

    public function getDescription(): string
    {
        return 'Handles compilation and minification of SCSS and JS assets.';
    }

    public function getIcon(): string
    {
        return 'fas fa-file-code';
    }

    public function getInputSchema(): array
    {
        return [];
    }

    public function validate(array $input): array
    {
        return $input;
    }

    public function process(array $input, ?callable $progressCallback = null): array
    {
        return [];
    }

    public function supportsChunking(): bool
    {
        return false;
    }

    public function estimateTime(array $input): int
    {
        return 1;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getAssetUrl(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_BASENAME);

        $sourcePath = ($extension === 'scss' || $extension === 'css')
            ? $this->scssPath . '/' . $path
            : $this->jsPath . '/' . $path;

        if (!file_exists($sourcePath)) {
            return '/assets/' . $path;
        }

        // Use file modification time for better performance
        $mtime = filemtime($sourcePath);
        $hash = substr(md5((string)$mtime), 0, 8);
        $type = ($extension === 'scss' || $extension === 'css') ? 'css' : 'js';

        $urlPath = ($dirname !== '.') ? str_replace('/', '.', $dirname) . '.' : '';

        return "/assets/{$type}/{$urlPath}{$filename}.{$hash}.{$type}";
    }

    public function serveAsset(string $type, string $file): void
    {
        // Strictly validate the file format to prevent path traversal
        // Allow dots in the filename for subdirectories and multiple extensions
        if (!preg_match('/^([a-zA-Z0-9._-]+)\.([a-f0-9]{8})\.(css|js)$/', $file, $matches)) {
            header("HTTP/1.0 400 Bad Request");
            echo "Invalid asset format";
            return;
        }

        $fullFilename = $matches[1];
        $hash = $matches[2];
        $ext = $matches[3];

        if (!in_array($type, ['css', 'js'], true)) {
            header("HTTP/1.0 400 Bad Request");
            echo "Invalid asset type";
            return;
        }

        // Reconstruct the path from dots
        $parts = explode('.', $fullFilename);
        $filename = array_pop($parts);
        $path = implode('/', $parts);

        $sourceFile = ($type === 'css') ? "{$filename}.scss" : "{$filename}.js";
        $sourcePath = ($type === 'css') ? $this->scssPath . '/' : $this->jsPath . '/';
        if ($path !== '') {
            $sourcePath .= $path . '/';
        }
        $sourcePath .= $sourceFile;

        // Fallback for .css files in scss folder
        if ($type === 'css' && !file_exists($sourcePath)) {
            $sourcePath = str_replace('.scss', '.css', $sourcePath);
        }

        if (!file_exists($sourcePath)) {
            header("HTTP/1.0 404 Not Found");
            echo "Source file not found";
            return;
        }

        $cacheName = str_replace('/', '.', ($path !== '' ? $path . '/' : '') . $filename);
        $cacheFile = "{$this->cachePath}/{$cacheName}.{$hash}.{$ext}";

        if (!file_exists($cacheFile)) {
            $this->compile($sourcePath, $cacheFile, $type);
        }

        $this->output($cacheFile, $type);
    }

    private function compile(string $sourcePath, string $cacheFile, string $type): void
    {
        try {
            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0755, true);
            }

            if ($type === 'css') {
                $compiler = new Compiler();
                $compiler->setImportPaths(dirname($sourcePath));
                $scss = file_get_contents($sourcePath);
                $css = $compiler->compileString($scss)->getCss();

                $minifier = new Minify\CSS($css);
                file_put_contents($cacheFile, $minifier->minify());
            } else {
                $minifier = new Minify\JS($sourcePath);
                file_put_contents($cacheFile, $minifier->minify());
            }
        } catch (\Exception $e) {
            header("HTTP/1.0 500 Internal Server Error");
            echo "Compilation failed";
            exit;
        }
    }

    private function output(string $file, string $type): void
    {
        $mimeType = ($type === 'css') ? 'text/css' : 'application/javascript';
        header("Content-Type: {$mimeType}");
        header("Cache-Control: public, max-age=31536000, immutable");
        header("ETag: " . md5_file($file));

        readfile($file);
        exit;
    }
}
