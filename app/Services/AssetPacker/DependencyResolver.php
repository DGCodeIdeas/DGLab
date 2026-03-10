<?php

namespace DGLab\Services\AssetPacker;

use RuntimeException;

class DependencyResolver implements DependencyResolverInterface
{
    private array $resolved = [];
    private array $visiting = [];
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (dirname(__DIR__, 3) . '/resources/js');
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
            throw new RuntimeException("Circular dependency detected: " . $path . " is part of a cycle.");
        }

        if (!file_exists($path)) {
            throw new RuntimeException("File not found: " . $path);
        }

        $this->visiting[$path] = true;

        $content = file_get_contents($path);
        $dependencies = $this->extractDependencies($content);

        foreach ($dependencies as $dependency) {
            $normalizedDep = $this->normalizePath($dependency, dirname($path));
            $this->traverse($normalizedDep);
        }

        unset($this->visiting[$path]);
        $this->resolved[] = $path;
    }

    private function extractDependencies(string $content): array
    {
        $dependencies = [];

        // Match comments, imports/requires, and standalone strings.
        // We order them carefully:
        // 1. Comments (to skip everything in them)
        // 2. Import from (matches import ... from path)
        // 3. Dynamic Import (matches import(path))
        // 4. Require (matches require(path))
        // 5. Import only (matches import path)
        // 6. Standalone strings (to skip them and avoid matching their content)
        $pattern = '/\/\/[^\n]*|\/\*.*?\*\/|' .
            '(?P<if>\bimport\s+(?:[^"\']+\s+from\s+)(?P<q1>[\'"])(?P<p1>[^\'"]+)(?P=q1))|' .
            '(?P<di>\bimport\(\s*(?P<q3>[\'"])(?P<p3>[^\'"]+)(?P=q3)\s*\))|' .
            '(?P<re>\brequire\(\s*(?P<q2>[\'"])(?P<p2>[^\'"]+)(?P=q2)\s*\))|' .
            '(?P<io>\bimport\s+(?P<q4>[\'"])(?P<p4>[^\'"]+)(?P=q4))|' .
            '(?P<sdq>"(?:[^"\\\\]|\\\\.)*")|' .
            '(?P<ssq>\'(?:[^\'\\\\]|\\\\.)*\')|' .
            '(?P<sbt>`(?:[^`\\\\]|\\\\.)*`)/ms';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match['p1'])) {
                    $dependencies[] = $match['p1'];
                } elseif (!empty($match['p2'])) {
                    $dependencies[] = $match['p2'];
                } elseif (!empty($match['p3'])) {
                    $dependencies[] = $match['p3'];
                } elseif (!empty($match['p4'])) {
                    $dependencies[] = $match['p4'];
                }
            }
        }

        return array_unique($dependencies);
    }

    private function normalizePath(string $path, ?string $currentDir = null): string
    {
        if (str_starts_with($path, './') || str_starts_with($path, '../')) {
            $dir = $currentDir ?? $this->basePath;
            $path = $dir . DIRECTORY_SEPARATOR . $path;
        } elseif (!str_starts_with($path, DIRECTORY_SEPARATOR) && !preg_match('/^[a-zA-Z]:\\\\/', $path)) {
            $path = $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $safe = [];
        foreach ($parts as $part) {
            if ($part === '' && empty($safe)) {
                $safe[] = '';
                continue;
            }
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop($safe);
                continue;
            }
            $safe[] = $part;
        }
        $normalized = implode(DIRECTORY_SEPARATOR, $safe);

        if (str_starts_with($path, DIRECTORY_SEPARATOR) && !str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            $normalized = DIRECTORY_SEPARATOR . $normalized;
        }

        if (!str_ends_with($normalized, '.js')) {
            $normalized .= '.js';
        }

        return $normalized;
    }
}
