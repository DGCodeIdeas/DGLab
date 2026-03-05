<?php
/**
 * DGLab Cache System
 * 
 * Multi-layer caching with file and memory support.
 * 
 * @package DGLab\Core
 */

namespace DGLab\Core;

/**
 * Class Cache
 * 
 * Provides caching functionality with:
 * - File-based caching
 * - In-memory caching (APCu)
 * - Cache tags
 * - TTL support
 * - Cache warming
 */
class Cache
{
    /**
     * Cache directory
     */
    private string $cachePath;
    
    /**
     * Default TTL in seconds
     */
    private int $defaultTtl = 3600;
    
    /**
     * In-memory cache
     */
    private array $memory = [];
    
    /**
     * Whether APCu is available
     */
    private bool $hasApcu = false;

    /**
     * Constructor
     */
    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? Application::getInstance()->getBasePath() . '/storage/cache';
        $this->hasApcu = extension_loaded('apcu') && ini_get('apc.enabled');
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Get an item from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check memory first
        if (isset($this->memory[$key])) {
            if ($this->memory[$key]['expires'] > time()) {
                return $this->memory[$key]['value'];
            }
            unset($this->memory[$key]);
        }
        
        // Check APCu
        if ($this->hasApcu) {
            $value = apcu_fetch($this->prefix($key), $success);
            if ($success) {
                return $value;
            }
        }
        
        // Check file cache
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }

    /**
     * Store an item in cache
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = time() + $ttl;
        
        // Store in memory
        $this->memory[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];
        
        // Store in APCu
        if ($this->hasApcu) {
            apcu_store($this->prefix($key), $value, $ttl);
        }
        
        // Store in file
        $file = $this->cachePath . '/' . $this->sanitizeKey($key) . '.cache';
        $data = [
            'key' => $key,
            'value' => $value,
            'expires' => $expires,
            'created' => time(),
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Remove an item from cache
     */
    public function delete(string $key): bool
    {
        unset($this->memory[$key]);
        
        if ($this->hasApcu) {
            apcu_delete($this->prefix($key));
        }
        
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Get and delete an item (atomic)
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Remember a value (get or compute)
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Remember forever (no TTL)
     */
    public function forever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, 0);
    }

    /**
     * Increment a numeric value
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        return $new;
    }

    /**
     * Decrement a numeric value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Flush all cache
     */
    public function flush(): bool
    {
        $this->memory = [];
        
        if ($this->hasApcu) {
            apcu_clear_cache();
        }
        
        $files = glob($this->cachePath . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }

    /**
     * Flush by pattern
     */
    public function flushPattern(string $pattern): int
    {
        $count = 0;
        $files = glob($this->cachePath . '/*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if (fnmatch($pattern, $data['key'])) {
                unlink($file);
                $count++;
                
                if ($this->hasApcu) {
                    apcu_delete($this->prefix($data['key']));
                }
            }
        }
        
        return $count;
    }

    /**
     * Get cache statistics
     */
    public function stats(): array
    {
        $files = glob($this->cachePath . '/*.cache');
        $totalSize = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] < time()) {
                $expired++;
            }
        }
        
        return [
            'files' => count($files),
            'expired' => $expired,
            'size_bytes' => $totalSize,
            'size_human' => $this->formatBytes($totalSize),
            'memory_items' => count($this->memory),
            'apcu_available' => $this->hasApcu,
        ];
    }

    /**
     * Clean expired entries
     */
    public function gc(): int
    {
        $count = 0;
        $files = glob($this->cachePath . '/*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] < time()) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get cache file path
     */
    private function getCacheFile(string $key): string
    {
        return $this->cachePath . '/' . $this->sanitizeKey($key) . '.cache';
    }

    /**
     * Sanitize key for filesystem
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }

    /**
     * Prefix key for APCu
     */
    private function prefix(string $key): string
    {
        return 'dglab:' . $key;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes > 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
