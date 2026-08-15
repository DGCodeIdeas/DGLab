<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Reference implementation of {@see ContainerInterface} and
 * {@see ContainerBuilderInterface}.
 *
 * State:
 *   - $definitions   : id -> ServiceDefinition (the binding table)
 *   - $instances     : id -> resolved object (shared-instance cache)
 *   - $resolving     : id -> true (the resolution stack; presence == on stack)
 *   - $compilerPasses: list of passes to run during compile()
 *   - $compiled      : freeze flag; mutation methods assert false before running
 *
 * Resolution flow (see also the sequence diagram in this blueprint):
 *   make(A) ->
 *     1. if A in $instances -> return cached object
 *     2. if A in $resolving -> throw CircularDependencyException(chain)
 *     3. push A onto $resolving
 *     4. try { build(concrete) } finally { pop A from $resolving }
 *     5. if shared -> cache in $instances
 *     6. return object
 *
 * Cycle detection is resolution-time, not graph-time. This is deliberate:
 * a graph-time analysis would have to walk every Closure binding (which may
 * be opaque) and every constructor argument of every class. Resolution-time
 * detection via a stack + finally is O(depth) per resolution, never
 * stack-overflows, and surfaces the exact chain that closed the cycle.
 */
final class Container implements ContainerInterface, ContainerBuilderInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, string> Map from concrete class name to definition ID */
    private array $concreteToId = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /** @var list<CompilerPassInterface> */
    private array $compilerPasses = [];

    private bool $compiled = false;

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

        // Track concrete class name for cycle detection
        if (is_string($concrete) && class_exists($concrete)) {
            $this->concreteToId[$concrete] = $id;
        }
    }

    public function singleton(string $id, mixed $concrete = null): void
    {
        $this->bind($id, $concrete, true);
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

        // Track concrete class name for cycle detection
        $className = get_class($instance);
        $this->concreteToId[$className] = $id;
    }

    public function make(string $id, array $parameters = []): mixed
    {
        // 1. Resolved-instance cache.
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // 2. Cycle detection — resolution-time, never stack-overflows.
        // Use the concrete class name for cycle detection if available, otherwise use the ID.
        $definition = $this->definitions[$id] ?? null;
        $concrete = $definition->concrete ?? $id;
        $resolutionKey = (is_string($concrete) && class_exists($concrete)) ? $concrete : $id;

        if (isset($this->resolving[$resolutionKey])) {
            $chain = array_keys($this->resolving);
            $chain[] = $resolutionKey;
            throw CircularDependencyException::fromChain($chain);
        }

        // 3. Push onto the resolution stack.
        $this->resolving[$resolutionKey] = true;

        try {
            // 4. If no definition exists and the ID looks like a class name that doesn't exist, throw early.
            // This ensures make('NonExistent\\Class') throws NotFoundException instead of returning the string.
            if ($definition === null && is_string($concrete) && str_contains($concrete, '\\') && !class_exists($concrete) && !interface_exists($concrete)) {
                throw new NotFoundException("Class [$concrete] does not exist and cannot be resolved.");
            }

            // 5. Build by concrete type.
            $object = $this->build($concrete, $parameters);
        } finally {
            // 6. Always pop, even on exception — no state leak.
            unset($this->resolving[$resolutionKey]);
        }

        // 7. Cache shared singletons.
        if ($definition !== null && $definition->shared) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Get the definition ID for a concrete class name.
     * Used by compiler passes to track concrete-to-id mappings.
     */
    public function getDefinitionIdForConcrete(string $concrete): ?string
    {
        return $this->concreteToId[$concrete] ?? null;
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
     *   - Closure            -> invoke with ($container, $parameters)
     *   - object (non-Closure) -> return as-is (from instance() or bind($id, $obj))
     *   - class-string       -> autowire via reflection
     *   - any other scalar   -> return as-is (primitive bindings)
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
            is_string($concrete) => $concrete, // scalar/primitive value
            default => $concrete,
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