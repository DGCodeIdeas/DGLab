<?php

namespace DGLab\Services\Superpowers\Runtime;

/**
 * Class GlobalStateStore
 *
 * Manages shared application state across SuperPHP components.
 * Persistent via Session.
 */
class GlobalStateStore
{
    private const SESSION_KEY = '_superpowers_global_state';
    private array $state = [];

    public function __construct()
    {
        $this->load();
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
        $this->save();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->state;
    }

    public function forget(string $key): void
    {
        unset($this->state[$key]);
        $this->save();
    }

    public function clear(): void
    {
        $this->state = [];
        $this->save();
    }

    private function load(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->state = $_SESSION[self::SESSION_KEY] ?? [];
        }
    }

    private function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $this->state;
        }
    }
}
