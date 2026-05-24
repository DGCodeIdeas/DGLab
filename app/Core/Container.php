<?php

namespace DGLab\Core;

use Psr\Container\ContainerInterface;
use DGLab\Core\Exceptions\ContainerException;
use DGLab\Core\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionParameter;
use Exception;

class Container implements ContainerInterface
{
    protected array $entries = [];
    protected array $resolved = [];

    public function get(string $id): mixed
    {
        if (!isset($this->entries[$id])) {
            try {
                return $this->autowire($id);
            } catch (Exception $e) {
                throw new NotFoundException("Service not found: {$id}", 0, $e);
            }
        }

        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        $entry = $this->entries[$id];

        if ($entry instanceof \Closure) {
            $this->resolved[$id] = $entry($this);
            return $this->resolved[$id];
        }

        return $entry;
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]) || class_exists($id);
    }

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
        unset($this->resolved[$id]);
    }

    public function singleton(string $id, mixed $service = null): void
    {
        if ($service === null) {
            $this->set($id, fn($app) => new $id($app));
        } else {
            $this->set($id, $service);
        }
    }

    public function make(string $id): mixed
    {
        return $this->get($id);
    }

    protected function autowire(string $id): mixed
    {
        if (!class_exists($id)) {
            throw new NotFoundException("Class {$id} does not exist.");
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class {$id} is not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $id();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new ContainerException("Unable to resolve parameter '{$parameter->getName()}' (no type hint).");
            }

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new ContainerException("Unable to resolve parameter '{$parameter->getName()}' (builtin or non-named type).");
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $dependencies;
    }

    public function call(callable $callable, array $args = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }
        $params = $reflection->getParameters();
        $finalArgs = [];
        foreach ($params as $param) {
            $name = $param->getName();
            if (isset($args[$name])) {
                $finalArgs[] = $args[$name];
                continue;
            }
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $finalArgs[] = $this->get($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $finalArgs[] = $param->getDefaultValue();
                continue;
            }
            throw new \RuntimeException("Unable to resolve parameter: {$name}");
        }
        return call_user_func_array($callable, $finalArgs);
    }
}
