<?php

namespace DGLab\Core\Contracts;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function set(string $id, mixed $service): void;
    public function singleton(string $id, mixed $service = null): void;
    public function has(string $id): bool;
}
