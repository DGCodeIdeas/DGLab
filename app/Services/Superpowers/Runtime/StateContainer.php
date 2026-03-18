<?php

namespace DGLab\Services\Superpowers\Runtime;

/**
 * Class StateContainer
 *
 * Manages component-local state and tracks variables.
 */
class StateContainer
{
    /**
     * @var array
     */
    private array $data = [];

    /**
     * @var array
     */
    private array $tracked = [];

    /**
     * Set a state value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->tracked[$key] = true;
    }

    /**
     * Get a state value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Merge multiple values into state.
     */
    public function merge(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get all tracked state.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Check if a key is tracked.
     */
    public function isTracked(string $key): bool
    {
        return isset($this->tracked[$key]);
    }
}
