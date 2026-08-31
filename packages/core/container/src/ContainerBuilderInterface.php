<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Builder view over the container's definition table, used by
 * {@see CompilerPassInterface} implementations during compilation.
 *
 * This interface extends the read-only inspection methods (getDefinitions /
 * getDefinition / hasDefinition) WITH the mutation methods (bind / singleton /
 * instance). Passes are expected to inspect the existing graph and then mutate
 * it by calling bind()/singleton()/instance() directly on the builder — no
 * instanceof check or cast is required.
 *
 * The same interface is implemented by {@see Container}; passes receive the
 * container itself. After {@see ContainerInterface::compile()} returns, the
 * container's `assertNotCompiled()` guard rejects further mutation, so passes
 * can only mutate during the compile() pass — never after.
 *
 * Design note: an earlier draft of this interface declared it "read-only" and
 * required passes to perform an `instanceof ContainerInterface` cast to mutate.
 * That was internally contradictory (the blueprint's own compiler-pass
 * contract expected mutation) and forced every non-trivial pass to do an
 * unsafe cast. The mutable contract here is honest about what passes do.
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

    /**
     * Register or replace a binding. See {@see ContainerInterface::bind()} for
     * the full contract; this method has identical semantics and is exposed on
     * the builder so compiler passes can rewrite the definition graph before
     * the container is frozen.
     *
     * @param string $id        The service identifier.
     * @param mixed  $concrete  A class-string, a Closure, an object instance, or null.
     * @param bool   $singleton When true, the first resolved instance is cached.
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function bind(string $id, mixed $concrete = null, bool $singleton = false): void;

    /**
     * Convenience wrapper for {@see bind()} with $singleton = true.
     */
    public function singleton(string $id, mixed $concrete = null): void;

    /**
     * Register a pre-built object instance as a shared binding.
     *
     * @param string $id       The service identifier.
     * @param object $instance The instance to register.
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function instance(string $id, object $instance): void;
}
