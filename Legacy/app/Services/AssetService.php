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
    private string $fontsPath;

    public function __construct()
    {
        parent::__construct();
        $app = Application::getInstance();
        $this->cachePath = $app->getBasePath() . '/storage/cache/assets';
        $this->scssPath = realpath($app->getBasePath() . '/resources/scss');
        $this->jsPath = realpath($app->getBasePath() . '/resources/js');
        $this->fontsPath = realpath($app->getBasePath() . '/resources/fonts/webfonts');

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
        return 'bi bi-file-earmark-code';
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
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        if (in_array($extension, ['woff2', 'ttf', 'woff', 'otf'])) {
            $relativeFontPath = (str_starts_with($path, 'webfonts/')) ? substr($path, 9) : $path;
            $sourcePath = $this->fontsPath . '/' . $relativeFontPath;
            if (file_exists($sourcePath)) {
                $hash = substr(md5_file($sourcePath), 0, 8);
                return "/assets/webfonts/{$filenameWithoutExt}.{$hash}.{$extension}";
            }
        }

        $sourcePath = ($extension === 'scss' || $extension === 'css')
            ? $this->scssPath . '/' . $path
            : $this->jsPath . '/' . $path;

        if (!file_exists($sourcePath)) {
            // Check for .scss fallback if .css was requested
            if ($extension === 'css' && file_exists(str_replace('.css', '.scss', $sourcePath))) {
                $sourcePath = str_replace('.css', '.scss', $sourcePath);
            } else {
                // Check if it exists in public/assets
                $publicPath = Application::getInstance()->getBasePath() . '/public/assets/' . $path;
                if (file_exists($publicPath)) {
                    $mtime = filemtime($publicPath);
                    $hash = substr(md5((string)$mtime), 0, 8);
                    return '/assets/' . $path . '?v=' . $hash;
                }
                return '/assets/' . $path;
            }
        }

        // Use content hash for JS to ensure build-time stability
        $hash = ($extension === 'js')
            ? substr(md5_file($sourcePath), 0, 8)
            : substr(md5((string)filemtime($sourcePath)), 0, 8);
        $type = ($extension === 'scss' || $extension === 'css') ? 'css' : 'js';

        $urlPath = ($dirname !== '.') ? str_replace('/', '.', $dirname) . '.' : '';

        return "/assets/{$type}/{$urlPath}{$filenameWithoutExt}.{$hash}.{$type}";
    }

    public function serveAsset(string $type, string $file): void
    {
        if ($type === 'webfonts') {
            if (preg_match('/^([a-zA-Z0-9._-]+)\.([a-f0-9]{8})\.(woff2|ttf|woff|otf)$/', $file, $matches)) {
                $filename = $matches[1];
                $ext = $matches[3];
                $sourcePath = $this->fontsPath . '/' . $filename . '.' . $ext;
                if (file_exists($sourcePath)) {
                    $this->output($sourcePath, 'webfonts');
                    return;
                }
            }

            if (preg_match('/^([a-zA-Z0-9._-]+)\.(woff2|ttf|woff|otf)$/', $file, $matches)) {
                $filename = $matches[1];
                $ext = $matches[2];
                $sourcePath = $this->fontsPath . '/' . $filename . '.' . $ext;
                if (file_exists($sourcePath)) {
                    $this->output($sourcePath, 'webfonts');
                    return;
                }
            }

            header("HTTP/1.0 404 Not Found");
            echo "Font not found";
            return;
        }

        if (!preg_match('/^([a-zA-Z0-9._-]+)\.([a-f0-9]{8})\.(css|js|js\.map)$/', $file, $matches)) {
            $subPath = ($type === 'css' ? 'css/' : 'js/') . $file;
            $publicPath = Application::getInstance()->getBasePath() . '/public/assets/' . $subPath;
            if (file_exists($publicPath)) {
                $this->output($publicPath, $type);
                return;
            }

            if (preg_match('/^([a-zA-Z0-9._-]+)\.(css|js)$/', $file, $matches)) {
                $fullFilename = $matches[1];
                $ext = $matches[2];

                $parts = explode('.', $fullFilename);
                $filename = array_pop($parts);
                $path = implode('/', $parts);

                $sourceFile = ($type === 'css') ? "{$filename}.scss" : "{$filename}.js";
                $sourcePath = ($type === 'css') ? $this->scssPath . '/' : $this->jsPath . '/';
                if ($path !== '') {
                    $sourcePath .= $path . '/';
                }
                $sourcePath .= $sourceFile;

                if ($type === 'css' && !file_exists($sourcePath)) {
                    $sourcePath = str_replace('.scss', '.css', $sourcePath);
                }

                if (file_exists($sourcePath)) {
                    $hash = substr(md5((string)filemtime($sourcePath)), 0, 8);
                    $cacheName = str_replace('/', '.', ($path !== '' ? $path . '/' : '') . $filename);
                    $cacheFile = "{$this->cachePath}/{$cacheName}.{$hash}.{$ext}";

                    if (!file_exists($cacheFile)) {
                        $this->compile($sourcePath, $cacheFile, $type);
                    }

                    $this->output($cacheFile, $type);
                    return;
                }
            }

            header("HTTP/1.0 400 Bad Request");
            echo "Invalid asset format";
            return;
        }

        $fullFilename = $matches[1];
        $hash = $matches[2];
        $ext = $matches[3];

        if ($ext === 'js.map') {
            $type = 'js';
        }

        if (!in_array($type, ['css', 'js'], true)) {
            header("HTTP/1.0 400 Bad Request");
            echo "Invalid asset type";
            return;
        }

        $parts = explode('.', $fullFilename);
        $filename = array_pop($parts);
        $path = implode('/', $parts);

        $sourceFile = ($type === 'css') ? "{$filename}.scss" : "{$filename}.js";
        $sourcePath = ($type === 'css') ? $this->scssPath . '/' : $this->jsPath . '/';
        if ($path !== '') {
            $sourcePath .= $path . '/';
        }
        $sourcePath .= $sourceFile;

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

    protected function compile(string $sourcePath, string $cacheFile, string $type): void
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
            return;
        }
    }

    private function output(string $file, string $type): void
    {
        if (str_ends_with($file, '.map')) {
            $mimeType = 'application/json';
        } elseif ($type === 'webfonts') {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $mimeTypes = [
                'woff2' => 'font/woff2',
                'woff' => 'font/woff',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf'
            ];
            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        } else {
            $mimeType = ($type === 'css') ? 'text/css' : 'application/javascript';
        }
        header("Content-Type: {$mimeType}");
        header("Cache-Control: public, max-age=31536000, immutable");
        header("ETag: " . md5_file($file));

        readfile($file);
        return;
    }
}
