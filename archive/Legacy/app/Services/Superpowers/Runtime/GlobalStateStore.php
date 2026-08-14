<?php

namespace DGLab\Services\Superpowers\Runtime;

class GlobalStateStore implements GlobalStateStoreInterface
{
    private const SESSION_KEY = '_superpowers_global_state';
    private array $state = [];

    public function __construct()
    {
        $this->load();
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->isSerializable($value)) {
            throw new \InvalidArgumentException("Value for key '{$key}' is not serializable.");
        }
        $this->state[$key] = $value;
        $this->save();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->state) ? $this->state[$key] : $default;
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

    public function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $this->state;
        }
    }

    private function isSerializable(mixed $value): bool
    {
        if (is_scalar($value) || is_null($value)) {
            return true;
        }
        if (is_resource($value) || $value instanceof \Closure) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (!$this->isSerializable($v)) {
                    return false;
                }
            }
            return true;
        }
        return true;
    }
}
