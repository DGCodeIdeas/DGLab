<?php

namespace DGLab\Services\Superpowers\Runtime;

interface GlobalStateStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function all(): array;
    public function forget(string $key): void;
    public function clear(): void;
    public function save(): void;
}
