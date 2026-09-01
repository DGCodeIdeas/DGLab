<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Reference implementation of {@see ContainerInterface} and
 * {@see ContainerBuilderInterface}.
 *
 * State:
 *   - $definitions       : id -> ServiceDefinition (the binding table)
 *   - $instances         : id -> resolved object (shared-instance cache)
 *   - $pulseInstances    : WeakMap<Fiber, array<string, mixed>> (auto-evicts on Fiber GC)
 *   - $resolving         : concrete-key -> true (cycle detection set; presence == on stack)
 *   - $resolvingChain    : ordered list of [id, concrete] tuples for diagnostics
 *   - $compilerPasses    : list of passes to run during compile()
 *   - $compiled          : freeze flag; mutation methods assert false before running
 *
 * Resolution flow (see also the sequence diagram in this blueprint):
 *   make(A) ->
 *     1. if A in $instances -> return cached object
 *     2. compute concrete (definition->concrete ?? $id); if concrete already in
 *        $resolving -> throw CircularDependencyException(chain from $resolvingChain)
 *     3. push [id, concrete] onto $resolving and $resolvingChain
 *     4. try { build(concrete) } finally { pop from both }
 *     5. if shared -> cache in $instances
 *     6. return object
 *
 * Cycle detection is resolution-time, not graph-time, and keys on the *concrete*
 * class being constructed (not the binding id). This is deliberate: autowire()
 * recurses by calling make() with class-string FQCNs (e.g. `make(B::class)`),
 * not with the user's binding id. Keying on concrete catches cycles whether
 * the cycle enters via the user's bound id or via an autowired FQCN, and never
 * stack-overflows. The full chain of [id, concrete] pairs is preserved on the
 * exception via getResolutionChain() for diagnostics.
 */
final class Container implements ContainerInterface, ContainerBuilderInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, mixed> Worker-scoped instance cache (singleton + instance). */
    private array $instances = [];

    /**
     * Pulse-scoped instance cache, keyed on the Fiber object itself.
     *
     * @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, array<string, mixed>>
     *
     * WeakMap<Fiber, array<string, mixed>> — when a Fiber is garbage-collected
     * (Pulse completes), PHP automatically evicts the entire inner array for
     * that Fiber. No manual cleanup, no scheduler coupling, no memory leak
     * over a long-running FrankenPHP worker lifetime.
     *
     * Outside any Fiber context (main context), pulse-scoped bindings behave
     * as transient — each make() call returns a fresh instance with no caching.
     * This is intentional: pulse() is defined as per-Pulse, and the main context
     * is not a Pulse.
     */
    private \WeakMap $pulseInstances;

    /** @var array<string, true> Keys are concrete class-strings currently being built. */
    private array $resolving = [];

    /** @var list<array{0: string, 1: mixed}> Ordered [id, concrete] pairs for chain reporting. */
    private array $resolvingChain = [];

    /** @var list<CompilerPassInterface> */
    private array $compilerPasses = [];

    private bool $compiled = false;

    public function __construct()
    {
        $this->pulseInstances = new \WeakMap();
    }

    public function bind(string $id, mixed $concrete = null, bool $singleton = false): void
    {
        $this->assertNotCompiled();

        $concrete ??= $id;

        $this->definitions[$id] = new ServiceDefinition(
            abstract: $id,
            concrete: $concrete,
            shared: $singleton,
            tags: [],
        );
    }

    public function singleton(string $id, mixed $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    public function pulse(string $id, mixed $concrete = null): void
    {
        $this->assertNotCompiled();

        $concrete ??= $id;

        $this->definitions[$id] = new ServiceDefinition(
            abstract: $id,
            concrete: $concrete,
            shared: false,
            pulseScoped: true,
            tags: [],
        );
    }

    public function instance(string $id, object $instance): void
    {
        $this->assertNotCompiled();

        $this->instances[$id] = $instance;
        $this->definitions[$id] = new ServiceDefinition(
            abstract: $id,
            concrete: $instance,
            shared: true,
            tags: [],
        );
    }

    public function make(string $id, array $parameters = []): mixed
    {
        // 1. Resolved-instance cache (worker-scoped).
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // 1b. Pulse-scoped cache (one instance per Fiber/Pulse).
        //     Uses WeakMap keyed on the Fiber object — auto-evicts on Fiber GC.
        //     Outside any Fiber, pulse-scoped bindings are transient (no cache).
        $definition = $this->definitions[$id] ?? null;
        if ($definition !== null && $definition->pulseScoped) {
            /** @var \Fiber<mixed, mixed, mixed, mixed>|null $fiber */
            $fiber = \Fiber::getCurrent();
            if ($fiber !== null && isset($this->pulseInstances[$fiber][$id])) {
                return $this->pulseInstances[$fiber][$id];
            }
        }

        // 2. Resolve the concrete binding (fall back to $id when unbound, supporting
        //    make(SomeClass::class) without an explicit bind() — see blueprint).
        //    PHPStan strict rule: `?->concrete ?? $id` is redundant (`?->` already
        //    handles null receiver; `??` is then equivalent). Use an explicit
        //    null check so the access is plain `->`, which PHPStan accepts.
        $concrete = $definition !== null ? $definition->concrete : $id;

        // 3. Reject unresolvable ids before pushing onto the stack. If there is no
        //    explicit definition AND $id is not a class-string, the caller asked for
        //    something that cannot be constructed — throw NotFoundException rather
        //    than returning the bare string (the old `default => $concrete` arm in
        //    build() did that, violating the PSR-11 contract).
        if ($definition === null && !(is_string($concrete) && class_exists($concrete))) {
            throw new NotFoundException(
                "No service registered for id [$id] and [$id] is not an instantiable class."
            );
        }

        // 4. Cycle detection — keyed on the concrete class being built. Autowire
        //    recurses by calling make() with class-string FQCNs (e.g. make(B::class)),
        //    so keying on concrete catches cycles entered via autowire even when the
        //    user's binding id differs from the FQCN. The chain returned on the
        //    exception preserves the binding ids for diagnostics.
        //    After the step-3 guard, $concrete is guaranteed to be either a class-string
        //    (the autowire path) or an object (Closure or pre-built instance) — never
        //    a primitive scalar. The is_object check narrows mixed to object for
        //    spl_object_hash().
        if (is_string($concrete)) {
            $resolutionKey = $concrete;
        } elseif (is_object($concrete)) {
            $resolutionKey = spl_object_hash($concrete);
        } else {
            // Unreachable after the step-3 guard: if $definition is non-null,
            // $concrete came from $definition->concrete (Closure|object|class-string
            // per ServiceDefinition docblock); if $definition is null, step 3
            // required $concrete to be a class-string. Throwing here is a defensive
            // invariant — if it ever fires, a new ServiceDefinition concrete type
            // was introduced without updating this dispatch.
            throw new \LogicException(
                'Unreachable: $concrete is neither string nor object after step-3 guard. '
                . 'Got type: ' . get_debug_type($concrete)
            );
        }

        if (isset($this->resolving[$resolutionKey])) {
            $chain = array_map(
                static fn(array $pair) => $pair[0],
                $this->resolvingChain,
            );
            $chain[] = $id;
            throw CircularDependencyException::fromChain($chain);
        }

        // 5. Push onto both the cycle-detection set and the chain.
        $this->resolving[$resolutionKey] = true;
        $this->resolvingChain[] = [$id, $concrete];

        try {
            // 6. Build by concrete type.
            $object = $this->build($concrete, $parameters);
        } finally {
            // 7. Always pop, even on exception — no state leak (Security Property #3).
            unset($this->resolving[$resolutionKey]);
            array_pop($this->resolvingChain);
        }

        // 8. Cache shared singletons (worker-scoped).
        if ($definition !== null && $definition->shared) {
            $this->instances[$id] = $object;
        }

        // 8b. Cache pulse-scoped instances (per-Fiber, via WeakMap).
        if ($definition !== null && $definition->pulseScoped) {
            /** @var \Fiber<mixed, mixed, mixed, mixed>|null $fiber */
            $fiber = \Fiber::getCurrent();
            if ($fiber !== null) {
                // Initialize the inner array on first write to this Fiber —
                // avoids "cannot assign offset to null" and the mixed-type
                // inference ambiguity on chained WeakMap[$fiber][$id] = $obj.
                if (!isset($this->pulseInstances[$fiber])) {
                    $this->pulseInstances[$fiber] = [];
                }
                $this->pulseInstances[$fiber][$id] = $object;
            }
        }

        return $object;
    }

    /**
     * PSR-11 get(): throws NotFoundException on unknown ids.
     *
     * Unlike make(), get() refuses to construct an arbitrary class-string
     * that has not been explicitly bound or registered via instance().
     * This matches the PSR-11 contract that get() must throw if the id
     * is not "known" to the container.
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException("No service registered for id [$id].");
        }

        return $this->make($id);
    }

    /**
     * PSR-11 has(): true if the id is bound, pre-instantiated, or
     * refers to an autoloadable class (the latter supports autowiring
     * of unregistered class-strings via make()).
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || array_key_exists($id, $this->definitions)
            || class_exists($id);
    }

    public function addCompilerPass(CompilerPassInterface $pass): void
    {
        $this->assertNotCompiled();
        $this->compilerPasses[] = $pass;
    }

    public function compile(): void
    {
        // Idempotent: a second call is a no-op.
        if ($this->compiled) {
            return;
        }

        foreach ($this->compilerPasses as $pass) {
            $pass->process($this);
        }

        $this->compiled = true;
    }

    // ----- ContainerBuilderInterface -----

    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function getDefinition(string $id): ServiceDefinition
    {
        if (!array_key_exists($id, $this->definitions)) {
            throw new NotFoundException("No definition registered for id [$id].");
        }

        return $this->definitions[$id];
    }

    public function hasDefinition(string $id): bool
    {
        return array_key_exists($id, $this->definitions);
    }

    // ----- Internals -----

    /**
     * Guard: mutation methods must not run after compile().
     */
    private function assertNotCompiled(): void
    {
        if ($this->compiled) {
            throw new \LogicException(
                'Cannot modify the container after it has been compiled.'
            );
        }
    }

    /**
     * Construct an instance from the concrete binding.
     *
     * Dispatches on the concrete's runtime type via a match expression:
     *   - Closure              -> invoke with ($container, $parameters)
     *   - object (non-Closure) -> return as-is (from instance() or bind($id, $obj))
     *   - class-string         -> autowire via reflection
     *   - interface-string     -> throw NotFoundException (must be bound to concrete)
     *   - any other scalar     -> return as-is (primitive bindings from explicit bind())
     *
     * Note: the scalar arm is reachable ONLY via an explicit `bind('key', $scalar)`
     * call — make() rejects unbound non-class strings before reaching build(), so
     * this default arm cannot accidentally return an unbound id as a string.
     *
     * @throws NotFoundException If a class-string concrete does not exist.
     * @param array<string, mixed> $parameters
     */
    private function build(mixed $concrete, array $parameters): mixed
    {
        return match (true) {
            $concrete instanceof \Closure => $concrete($this, $parameters),
            is_object($concrete) => $concrete,
            is_string($concrete) && class_exists($concrete) => $this->autowire($concrete, $parameters),
            is_string($concrete) && interface_exists($concrete) => throw new NotFoundException("Interface [$concrete] cannot be resolved directly; bind to a concrete implementation."),
            default => $concrete, // primitive value from explicit bind('key', $scalar)
        };
    }

    /**
     * Reflective autowiring.
     *
     * For each constructor parameter (in declaration order):
     *   1. If $parameters contains the parameter name -> use the supplied value.
     *   2. Else if the parameter has a default -> use the default.
     *   3. Else if the parameter has a class-string type hint -> recurse into make().
     *   4. Else -> throw NotFoundException (cannot resolve primitive without default).
     *
     * Union types, intersection types, and `readonly` properties on the
     * constructor are handled implicitly by ReflectionParameter: only
     * {@see \ReflectionNamedType} with isBuiltin() = false is recursed;
     * everything else falls through to the default-or-throw path.
     * @param array<string, mixed> $parameters
     */
    private function autowire(string $class, array $parameters): object
    {
        /** @var class-string $class */
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new NotFoundException(
                "Class [$class] is not instantiable (abstract, interface, or trait)."
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            // 1. Caller-supplied override (by name, then by position).
            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            $position = $parameter->getPosition();
            if (array_key_exists($position, $parameters)) {
                $arguments[] = $parameters[$position];
                continue;
            }

            // 2. Default value.
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            // 3. Class-string type hint -> recurse (cycle detection applies).
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if (class_exists($className) || interface_exists($className)) {
                    $arguments[] = $this->make($className);
                    continue;
                }
            }

            // 4. Unresolvable primitive.
            throw new NotFoundException(
                "Cannot resolve parameter [\$$name] of [$class]: no type hint, "
                . "no default value, and no caller-supplied value."
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }
}