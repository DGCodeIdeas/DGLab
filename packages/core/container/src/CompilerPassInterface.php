<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * A compiler pass runs during {@see ContainerInterface::compile()} and
 * receives a read-only view of the definition table. Passes may rewrite
 * the graph by calling `bind()` / `singleton()` / `instance()` on the
 * builder (which is the container itself).
 *
 * Passes are run in registration order.
 */
interface CompilerPassInterface
{
    /**
     * @param ContainerBuilderInterface $builder Read-only view of the
     *     definition table. The same instance is also a
     *     {@see ContainerInterface}; passes may cast or assert to mutate.
     */
    public function process(ContainerBuilderInterface $builder): void;
}