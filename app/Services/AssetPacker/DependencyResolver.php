<?php

namespace DGLab\Services\AssetPacker;

use RuntimeException;
use DGLab\Core\Application;

class DependencyResolver implements DependencyResolverInterface
{
    private array $resolved = [];
    private array $visiting = [];
    private string $basePath;
    private array $vendorMap = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (Application::getInstance()->getBasePath() . '/resources/js');
        $this->loadVendorMap();
    }

    private function loadVendorMap(): void
    {
        $path = Application::getInstance()->getBasePath() . '/config/vendor_map.php';
        if (file_exists($path)) {
            $this->vendorMap = include $path;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $entryPath): array
    {
        $this->resolved = [];
        $this->visiting = [];

        $absolutePath = $this->normalizePath($entryPath);
        $this->traverse($absolutePath);

        return $this->resolved;
    }

    private function traverse(string $path): void
    {
        if (in_array($path, $this->resolved)) {
            return;
        }

        if (isset($this->visiting[$path])) {
            throw new RuntimeException("Circular dependency detected: " . $path);
        }

        // If it's a vendor path from the map, don't traverse into it
        $basePath = Application::getInstance()->getBasePath();
        if (strpos($path, $basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'vendor') === 0) {
            $this->resolved[] = $path;
            return;
        }

        if (!file_exists($path)) {
             // In ESM mode, we allow unresolved bare imports as the browser handles them via import map
             return;
        }

        $this->visiting[$path] = true;

        $content = file_get_contents($path);
        $dependencies = $this->extractDependencies($content);

        foreach ($dependencies as $dependency) {
            $normalizedDep = $this->normalizePath($dependency, dirname($path));
            if ($normalizedDep) {
                $this->traverse($normalizedDep);
            }
        }

        unset($this->visiting[$path]);
        $this->resolved[] = $path;
    }

    private function extractDependencies(string $content): array
    {
        $dependencies = [];
        $pattern = '/\/\/[^\n]*|\/\*.*?\*\/|' .
            '(?P<if>\bimport\s+(?:[^"\']+\s+from\s+)(?P<q1>[\'"])(?P<p1>[^\'"]+)(?P=q1))|' .
            '(?P<ef>\bexport\s+(?:[^"\']+\s+from\s+)(?P<q5>[\'"])(?P<p5>[^\'"]+)(?P=q5))|' .
            '(?P<di>\bimport\(\s*(?P<q3>[\'"])(?P<p3>[^\'"]+)(?P=q3)\s*\))|' .
            '(?P<re>\brequire\(\s*(?P<q2>[\'"])(?P<p2>[^\'"]+)(?P=q2)\s*\))|' .
            '(?P<io>\bimport\s+(?P<q4>[\'"])(?P<p4>[^\'"]+)(?P=q4))/ms';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match['p1'])) $dependencies[] = $match['p1'];
                elseif (!empty($match['p5'])) $dependencies[] = $match['p5'];
                elseif (!empty($match['p2'])) $dependencies[] = $match['p2'];
                elseif (!empty($match['p3'])) $dependencies[] = $match['p3'];
                elseif (!empty($match['p4'])) $dependencies[] = $match['p4'];
            }
        }

        return array_unique($dependencies);
    }

    private function normalizePath(string $path, ?string $currentDir = null): ?string
    {
        // Check vendor map first
        if (isset($this->vendorMap[$path])) {
            $p = $this->vendorMap[$path];
            return Application::getInstance()->getBasePath() . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $p);
        }

        if (str_starts_with($path, '@/')) {
            $path = $this->basePath . DIRECTORY_SEPARATOR . substr($path, 2);
        } elseif (str_starts_with($path, './') || str_starts_with($path, '../')) {
            $dir = $currentDir ?? $this->basePath;
            $path = $dir . DIRECTORY_SEPARATOR . $path;
        } elseif (!str_starts_with($path, DIRECTORY_SEPARATOR) && !preg_match('/^[a-zA-Z]:\\\\/', $path)) {
            $path = $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $safe = [];
        foreach ($parts as $part) {
            if ($part === '' && empty($safe)) { $safe[] = ''; continue; }
            if ($part === '.' || $part === '') continue;
            if ($part === '..') { array_pop($safe); continue; }
            $safe[] = $part;
        }
        $normalized = implode(DIRECTORY_SEPARATOR, $safe);

        if (str_starts_with($path, DIRECTORY_SEPARATOR) && !str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            $normalized = DIRECTORY_SEPARATOR . $normalized;
        }

        if (!str_contains(basename($normalized), '.')) {
            $normalized .= '.js';
        }

        return $normalized;
    }
}
