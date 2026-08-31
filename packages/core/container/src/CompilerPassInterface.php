<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * A compiler pass runs during {@see ContainerInterface::compile()} and
 * receives a {@see ContainerBuilderInterface} view of the definition table.
 *
 * Passes may inspect the existing graph via getDefinitions() / getDefinition() /
 * hasDefinition(), and may rewrite it by calling bind() / singleton() /
 * instance() directly on the builder. No cast or instanceof check is required
 * — the builder interface is intentionally mutable to match what passes do.
 *
 * Passes are run in registration order. After compile() returns, the
 * container's assertNotCompiled() guard rejects further mutation, so passes
 * can only mutate during the compile() pass itself.
 */
interface CompilerPassInterface
{
    /**
     * @param ContainerBuilderInterface $builder Mutable view of the definition
     *     table. The same instance is also a {@see ContainerInterface}; passes
     *     that need read access to resolved services (rare) may cast, but
     *     mutation is available directly via the builder interface.
     */
    public function process(ContainerBuilderInterface $builder): void;
}
