# PHASE CORE-02: Dependency Injection Container

## Tier
Core (Foundational Infrastructure)

## Resolves
`00_CRITIQUE.md` Finding 8 — this is the component with zero implementation
(`packages/core/container/src/` contains only `.gitkeep`) that everything in the Hub tier transitively
depends on. This blueprint is written to be directly implementable, not just descriptive, so the gap
can close without a second design pass.

## Component Name
Reactive DI Container — `SovereignStack\Core\Container` (namespace matches
`packages/core/container/composer.json`'s existing PSR-4 mapping — no change needed there)

## Description
A PSR-11-compliant dependency injection container with constructor autowiring via reflection, compiler
passes for build-time optimization, and circular-dependency detection at resolution time. Every Hub
blueprint (`HUB-01`, `HUB-02`, and by extension the rest of the tier per
`docs/hub-taxonomy/hub-blueprint-taxonomy.md`) is blocked on this component existing. It is the single
highest-priority build item in the entire system (see `01_MASTER_INDEX.md` §5, item 1).

## Dependency Status
- **Upward:** none within the Core tier — this is a foundational leaf. It depends only on
  `psr/container: ^2.0` (already declared in `composer.json`).
- **Downward:** `CORE-10` (Config), `CORE-17` (Service Provider System), and effectively every
  Hub/Spoke blueprint that resolves services from a container.

## Architectural Design

### Interface Contracts

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind a concrete implementation, factory closure, or instance to an abstract identifier.
     *
     * @param string $abstract Interface or class name.
     * @param \Closure|class-string|mixed $concrete
     * @param bool $shared If true, resolves to the same instance on every call (singleton).
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /** Convenience wrapper for bind($abstract, $concrete, shared: true). */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /** Bind an already-constructed instance directly. */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve $abstract, autowiring constructor dependencies via reflection where no
     * explicit binding exists.
     *
     * @throws NotFoundException If $abstract has no binding and is not an instantiable class.
     * @throws CircularDependencyException If resolving $abstract re-enters itself.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /** True if a binding or an autowirable class exists for $abstract. */
    public function has(string $id): bool;

    /** Register a compiler pass to run during compile(). */
    public function addCompilerPass(CompilerPassInterface $pass): void;

    /**
     * Freeze the container: run all compiler passes, then reject further bind() calls.
     * Call this once, after all service providers have registered their bindings
     * (see CORE-17: Service Provider System).
     */
    public function compile(): void;
}
```

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * A compiler pass inspects/mutates the container's binding graph at compile() time —
 * e.g., to validate that every tagged interface has at least one implementation,
 * or to pre-resolve singletons eagerly in production builds.
 */
interface CompilerPassInterface
{
    public function process(ContainerBuilderInterface $builder): void;
}

interface ContainerBuilderInterface
{
    /** @return array<string, ServiceDefinition> */
    public function getDefinitions(): array;

    public function getDefinition(string $abstract): ServiceDefinition;

    public function hasDefinition(string $abstract): bool;
}
```

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Container;

final class ServiceDefinition
{
    public function __construct(
        public readonly string $abstract,
        public mixed $concrete,
        public bool $shared = false,
        /** @var array<int, string> Tags for compiler-pass discovery, e.g. 'event.listener' */
        public array $tags = [],
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Container;

final class NotFoundException extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {}

final class CircularDependencyException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface
{
    /** @param array<int, string> $chain The resolution chain that produced the cycle, in order. */
    public function __construct(array $chain)
    {
        parent::__construct('Circular dependency detected: ' . \implode(' -> ', $chain));
    }
}
```

### Resolution Algorithm (autowiring + cycle detection)

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Container;

final class Container implements ContainerInterface, ContainerBuilderInterface
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, mixed> Resolved singleton cache. */
    private array $resolved = [];

    /** @var array<int, string> Resolution stack, used for cycle detection. */
    private array $resolving = [];

    /** @var array<int, CompilerPassInterface> */
    private array $passes = [];

    private bool $compiled = false;

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $this->assertNotCompiled();
        $this->definitions[$abstract] = new ServiceDefinition($abstract, $concrete ?? $abstract, $shared);
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, shared: true);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->assertNotCompiled();
        $this->definitions[$abstract] = new ServiceDefinition($abstract, $instance, shared: true);
        $this->resolved[$abstract] = $instance;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (\in_array($abstract, $this->resolving, true)) {
            throw new CircularDependencyException([...$this->resolving, $abstract]);
        }

        $this->resolving[] = $abstract;

        try {
            $definition = $this->definitions[$abstract] ?? null;
            $concrete = $definition?->concrete ?? $abstract;

            $instance = match (true) {
                $concrete instanceof \Closure => $concrete($this, $parameters),
                \is_string($concrete) && \class_exists($concrete) => $this->autowire($concrete, $parameters),
                default => throw new NotFoundException("No binding or class found for [{$abstract}]."),
            };

            if ($definition?->shared) {
                $this->resolved[$abstract] = $instance;
            }

            return $instance;
        } finally {
            \array_pop($this->resolving);
        }
    }

    private function autowire(string $class, array $parameters): object
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new NotFoundException("[{$class}] is not instantiable (interface or abstract).");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            if (\array_key_exists($param->getName(), $parameters)) {
                $args[] = $parameters[$param->getName()];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new NotFoundException(
                "Cannot resolve parameter [\${$param->getName()}] for [{$class}]: " .
                "no binding, no type hint, and no default value."
            );
        }

        return $reflector->newInstanceArgs($args);
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || \class_exists($id);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function addCompilerPass(CompilerPassInterface $pass): void
    {
        $this->assertNotCompiled();
        $this->passes[] = $pass;
    }

    public function compile(): void
    {
        foreach ($this->passes as $pass) {
            $pass->process($this);
        }
        $this->compiled = true;
    }

    public function getDefinitions(): array { return $this->definitions; }
    public function getDefinition(string $abstract): ServiceDefinition { return $this->definitions[$abstract]; }
    public function hasDefinition(string $abstract): bool { return isset($this->definitions[$abstract]); }

    private function assertNotCompiled(): void
    {
        if ($this->compiled) {
            throw new \LogicException('Cannot register bindings after compile().');
        }
    }
}
```

This is a complete, minimal, dependency-free reference implementation — every class above compiles
against PHP 8.3 with only `psr/container` as a runtime dependency, matching what's already declared in
`packages/core/container/composer.json`. It is deliberately conservative (no attribute-based
autowiring hints, no lazy proxies) so it can land quickly and unblock the Hub tier; those are natural
`CompilerPassInterface` extensions for a later phase rather than blockers for this one.

### Cycle Detection Note
Cycle detection here is a resolution-time stack check (`$this->resolving`), not a build-time graph
analysis — it will correctly throw `CircularDependencyException` the first time a genuinely circular
`make()` call chain executes, but it will not proactively warn about a cycle that exists in bindings
which are never actually resolved. A build-time cycle scan (walking `getDefinitions()` for closures
that reference known abstracts) is a reasonable `CompilerPassInterface` addition and is tracked as a
follow-up, not a blocker.

## Integration Strategy
- `CORE-10` (Config) and `CORE-17` (Service Providers) both bind through this container; neither can
  be implemented against a real container until this lands.
- Hub-tier services register their bindings via a `ServiceProvider` (see `CORE-17`) which calls
  `bind()`/`singleton()` during the application's boot phase, then the Kernel (`CORE-18`) calls
  `compile()` once, after all providers have registered.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Autowiring a class with N constructor dependencies resolves in bounded time as N grows | PHPUnit `--group performance` test constructing a synthetic dependency chain of depth 1, 5, 20; assert wall-clock scales sub-quadratically via `microtime(true)`. No absolute millisecond target is claimed until this is actually measured on a reference runner (Governance Rule 2). |
| Circular dependency is always detected, never infinite-loops | Unit test: bind `A` to require `B`, `B` to require `A`; assert `CircularDependencyException` is thrown, not a stack overflow. |
| `compile()` is idempotent-safe against further mutation | Unit test: call `compile()`, then assert `bind()` throws `\LogicException`. |

## CI Verification Criteria
- 100% branch coverage on `make()`, `autowire()`, and `compile()` — these are the three methods every
  downstream tier depends on transitively; regressions here are systemic, not local.
- `phpstan.neon` at the level already configured in `packages/core/container/phpstan.neon` must pass
  with zero baseline-ignored errors introduced by this implementation.
- No dependency added beyond `psr/container` without a corresponding update to
  `01_MASTER_INDEX.md` (this container is meant to stay dependency-free by design — that's part of
  its contract with the rest of the Core tier).

## SemVer Impact
**Major** for the package itself (`sovereign-stack/core-container` `1.0.0` — first real release);
this is also the change that unblocks Hub-tier work from "documented but blocked" to "documented and
buildable," so it should be treated as unblocking the *program's* critical path, not just shipping one
package.
