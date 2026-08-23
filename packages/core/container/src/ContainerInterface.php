<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Sovereign Stack dependency injection container.
 *
 * Extends the PSR-11 {@see PsrContainerInterface} with a mutation API
 * (bind/singleton/instance/addCompilerPass/compile) and a non-cached
 * factory method {@see make()} that always returns a fresh instance
 * (unless the binding is marked shared, in which case the first
 * resolution is cached and reused).
 *
 * `get()` (inherited from PSR-11) and `make()` differ in one respect:
 * `get()` throws {@see NotFoundException} when the id is unknown and
 * cannot be autowired; `make()` throws the same exception under the
 * same conditions but is permitted to construct an unregistered
 * class-string by reflection when one is supplied directly.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a binding in the container.
     *
     * @param string $id        The service identifier (typically an interface FQCN).
     * @param mixed  $concrete  The concrete resolver. Accepts: a class-string,
     *                          a {@see \Closure} receiving ($container, $parameters),
     *                          a pre-built object instance, or null (in which case
     *                          $id is used as the concrete class-string).
     * @param bool   $singleton When true, the first resolved instance is cached
     *                          and returned on subsequent {@see make()} / {@see get()} calls.
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function bind(string $id, mixed $concrete = null, bool $singleton = false): void;

    /**
     * Convenience wrapper for {@see bind()} with $singleton = true.
     *
     * **Scope:** worker-lifetime (shared across all Pulses in this worker process).
     * For per-Pulse instances, use {@see pulse()}.
     */
    public function singleton(string $id, mixed $concrete = null): void;

    /**
     * Register a Pulse-scoped binding — one instance per Fiber (per Pulse).
     *
     * When a Pulse resolves this service, it receives a fresh instance that is
     * cached for the duration of that Pulse only. A different Pulse (even in the
     * same worker, even concurrently) receives its own independent instance.
     *
     * This is the correct scope for tenant-scoped services (repositories, unit-
     * of-work, request context) under the Fiber-based cooperative runtime (OD-07).
     *
     * @param string $id       The service identifier.
     * @param mixed  $concrete The concrete resolver (same types as {@see bind()}).
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function pulse(string $id, mixed $concrete = null): void;

    /**
     * Register a pre-built object instance as a shared binding.
     *
     * The instance is stored in the resolved cache immediately; subsequent
     * {@see get()} / {@see make()} calls return the same object identity.
     *
     * @param string $id       The service identifier.
     * @param object $instance The instance to register.
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function instance(string $id, object $instance): void;

    /**
     * Resolve a service from the container.
     *
     * If $id refers to a shared binding that has already been resolved,
     * the cached instance is returned. Otherwise a new instance is
     * constructed by reflection (autowiring) using $parameters for any
     * constructor arguments that cannot be resolved from the container.
     *
     * @param string  $id         The service identifier or a class-string.
     * @param array<string,mixed> $parameters Positional-or-named constructor
     *                            overrides. Named keys match parameter names;
     *                            integer keys match parameter position.
     *
     * @return mixed The resolved service instance.
     *
     * @throws CircularDependencyException If $id is part of a resolution cycle.
     * @throws NotFoundException           If $id cannot be resolved (unknown
     *                                     class, non-instantiable class, or
     *                                     an unresolved primitive constructor
     *                                     argument with no default).
     */
    public function make(string $id, array $parameters = []): mixed;

    /**
     * Register a compiler pass to run during {@see compile()}.
     *
     * Compiler passes receive a read-only {@see ContainerBuilderInterface}
     * view of the definition table and may rewrite definitions (by calling
     * {@see bind()} on the underlying container) before the container is
     * frozen.
     *
     * @throws \LogicException If the container has already been compiled.
     */
    public function addCompilerPass(CompilerPassInterface $pass): void;

    /**
     * Freeze the container.
     *
     * Runs all registered compiler passes in registration order, then
     * sets the `$compiled` flag. After this returns, any call to
     * {@see bind()}, {@see singleton()}, {@see pulse()}, {@see instance()}, or
     * {@see addCompilerPass()} throws {@see \LogicException}.
     *
     * Idempotent: calling compile() on an already-compiled container
     * returns immediately without re-running passes.
     */
    public function compile(): void;
}