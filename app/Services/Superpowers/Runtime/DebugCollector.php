<?php

namespace DGLab\Services\Superpowers\Runtime;

class DebugCollector
{
    private static ?DebugCollector $instance = null;
    private array $components = [];
    private array $views = [];

    public static function getInstance(): DebugCollector
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function recordView(string $name, string $path, array $data, ?string $compiledPath = null): void
    {
        $this->views[] = [
            'name' => $name,
            'path' => $path,
            'source' => file_exists($path) ? file_get_contents($path) : '',
            'compiled' => ($compiledPath && file_exists($compiledPath)) ? file_get_contents($compiledPath) : '',
            'state' => $this->sanitize($data),
            'time' => microtime(true)
        ];
    }

    public function recordComponent(string $name, array $props): void
    {
        $this->components[] = [
            'name' => $name,
            'props' => $this->sanitize($props),
            'time' => microtime(true)
        ];
    }

    public function clear(): void
    {
        $this->views = [];
        $this->components = [];
        $this->events = [];
    }

    public function getMetadata(): array
    {
        return [
            'views' => $this->views,
            'components' => $this->components,
            'php' => PHP_VERSION,
            'mem' => memory_get_peak_usage(true)
        ];
    }

    private function sanitize(array $data): array
    {
        $san = [];
        foreach ($data as $k => $v) {
            if (strpos($k, '__') === 0) {
                continue;
            }
            if (is_object($v)) {
                $san[$k] = '[Obj: ' . get_class($v) . ']';
            } elseif (is_array($v)) {
                $san[$k] = $this->sanitize($v);
            } else {
                $san[$k] = $v;
            }
        }
        return $san;
    }
}
