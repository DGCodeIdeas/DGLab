<?php

namespace DGLab\Core;

/**
 * Simple File-based Cache
 */
class Cache
{
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (!$content) return $default;

            $data = @unserialize($content);
            if ($data === false) return $default;

            if ($data['expires'] > time()) {
                return $data['value'];
            }
            $this->forget($key);
        }
        return $default;
    }

    public function set(string $key, mixed $value, int $seconds = 3600): void
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $seconds
        ];
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    public function forget(string $key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    protected function getFilePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }
}
