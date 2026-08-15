<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Read-only view over the container's definition table, used by
 * {@see CompilerPassInterface} implementations to inspect the
 * service graph during compilation.
 *
 * The same interface is implemented by {@see Container}; passes
 * receive the container itself and may call back into its mutation
 * API to register additional bindings.
 */
interface ContainerBuilderInterface
{
    /**
     * @return array<string, ServiceDefinition> All registered definitions, keyed by id.
     */
    public function getDefinitions(): array;

    /**
     * @param string $id The service identifier.
     *
     * @throws NotFoundException If no definition is registered for $id.
     */
    public function getDefinition(string $id): ServiceDefinition;

    /**
     * @param string $id The service identifier.
     */
    public function hasDefinition(string $id): bool;
}