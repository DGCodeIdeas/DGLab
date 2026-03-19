<?php

namespace DGLab\Services\Superpowers\Runtime;

class SourceMapResolver
{
    public function resolve(string $compiledPath, int $compiledLine): ?int
    {
        if (!file_exists($compiledPath)) return null;
        $lines = file($compiledPath);
        for ($i = min($compiledLine - 1, count($lines) - 1); $i >= 0; $i--) {
            if (preg_match('/\/\* line:(\d+) \*\//', $lines[$i], $matches)) {
                return (int) $matches[1];
            }
        }
        return null;
    }
}
