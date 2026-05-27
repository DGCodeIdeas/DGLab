<?php

namespace DGLab\Services\Superpowers\Runtime;

/**
 * Class CleanupManager
 *
 * Tracks and executes cleanup blocks.
 */
class CleanupManager
{
    private static ?CleanupManager $instance = null;
    private array $callbacks = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function cleanup(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
        $this->callbacks = [];
    }
}
