<?php

namespace DGLab\Services\Superpowers\Runtime;

use DGLab\Core\Application;
use DGLab\Services\Encryption\EncryptionService;

/**
 * Class StateContainer
 *
 * Manages component-local state and tracks variables.
 */
class StateContainer
{
    private array $data = [];
    private array $tracked = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->tracked[$key] = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function merge(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Export encrypted state.
     */
    public function export(): string
    {
        $payload = json_encode($this->data);
        $encryption = Application::getInstance()->get(EncryptionService::class);
        return $encryption->encrypt($payload);
    }

    /**
     * Import encrypted state.
     */
    public function import(string $encrypted): void
    {
        $encryption = Application::getInstance()->get(EncryptionService::class);
        $payload = $encryption->decrypt($encrypted);
        $this->data = json_decode($payload, true) ?: [];
    }
}
