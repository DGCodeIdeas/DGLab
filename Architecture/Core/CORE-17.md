# CORE-17: Service Provider System

## Tier
Core (Foundational Infrastructure)

## Resolves
- **Finding 2** (evaluation-layer mislabeling) — The evaluation layer records CORE-17 as "Testing Framework" (per `01_MASTER_INDEX.md` §2, footnote on stale evaluation-layer mapping). The canonical identity is **Service Provider System** in namespace `SovereignStack\Core\Providers`. This blueprint locks the canonical mapping in code.
- **Finding 4** (thin blueprints) — The approved `CORE-17.md` is 1,381 bytes: five prose sections, no interfaces, no compilable code, no diagrams, and a bare "< 5ms" target. Replaced with real PHP 8.3 interfaces, a complete compilable `ProviderRegistry`, a Kernel-boot sequence diagram, a provider-lifecycle state diagram, a methodology-grounded benchmark table, and explicit security invariants.
- **Finding 10** (bare performance targets) — The "Loading 50 service providers must take < 5ms" assertion is replaced with a benchmark table whose every row names the harness (PHPUnit `--group performance`), baseline (GitHub Actions `ubuntu-latest`, PHP 8.3, opcache enabled, no Xdebug), and load model (10 / 50 / 100 providers, 100 iterations each, `microtime(true)` wall-clock). All absolute numbers are marked "provisional, unverified".

## Component Name
Service Provider System — `SovereignStack\Core\Providers` (PSR-4 mapped to `packages/core/providers/src/` in a new `sovereign-stack/core-providers` package; see Migration Notes).

## Description
CORE-17 is the boot-time registration mechanism that wires every Hub and Spoke package into the CORE-02 DI Container. Each package that wants its bindings to appear in the container ships a `ServiceProvider` class annotated with `#[AsProvider]`. At kernel boot, the `ProviderRegistry` discovers every annotated provider in the configured scan directories, sorts them by priority (higher first; ties broken alphabetically by class FQCN for determinism), instantiates each through the container, calls `register()` on each (binding phase), then calls `boot()` on each (post-setup phase), and finally hands control back to the kernel which calls `Container::compile()` to freeze the service graph. The pattern is the Laravel service-provider pattern, narrowed to three hard constraints: providers cannot mutate the container after `compile()`, provider discovery is restricted to explicitly registered directories (no global classmap scans), and any `boot()` exception is fatal — the kernel aborts rather than continue with a partially wired graph.

The component is intentionally thin. It does not introduce a new abstraction over the container; providers call `bind()` / `singleton()` / `instance()` / `addCompilerPass()` on `ContainerInterface` directly. It does not implement deferred providers (Laravel's `DeferrableProvider`); the container is compiled once at boot and preloads via OPcache, so deferred resolution would not pay for its complexity. It does not own dependency ordering between providers beyond the explicit `priority` field — providers that depend on another provider's bindings lower their own priority or resolve through the container at `boot()` time.

What CORE-17 is **not**: not a plugin system (registry closes after `bootAll()`); not a module loader (no manifest format); not a service locator (providers receive the container only during `register()` / `boot()`); not a configuration source (the `env` field filters which providers load, but does not itself read configuration — that is CORE-10's job).

### Repo state (verified 2026-08-04)
- `packages/core/providers/` — does not exist. New package (see Migration Notes).
- `scripts/repo_cache/docs__blueprints__Core__CORE-17.md` — stale approved blueprint, 1,381 bytes, prose-only. This file replaces it.
- `01_MASTER_INDEX.md` §5 DAG — `C02 --> C17` (depends on CORE-02), `C17 --> C13` (CORE-13 CLI depends on CORE-17). Build Sequence Step 7 lands CORE-17 parallel with CORE-13 and CORE-20.

## Build Status
📝 **Not started.** 🔴 **Blocked on:**
- **CORE-02 (DI Container)** — `registerAll()` calls `ContainerInterface::bind()` / `singleton()` / `instance()` on each provider; the container must exist before any provider can register.
- **CORE-10 (Config & Environment Loader)** — `#[AsProvider]`'s `env` field is typed against CORE-10's `Environment` enum; the env filter reads `ConfigInterface::getEnvironment()`.
- **CORE-09 (PSR-3 Logging Service)** — the registry logs every discovery / register / boot event at `debug` level and every failure at `error` level so boot failures are diagnosable in production.

Once CORE-02, CORE-10, and CORE-09 land (Build Sequence Steps 1–2), CORE-17 is parallelizable with CORE-13 and CORE-20 (Step 7).

## Dependency Status
- **Upward:** CORE-02 (DI Container — `ContainerInterface`, `CompilerPassInterface`), CORE-10 (Config — `Environment` enum, `ConfigInterface`), CORE-09 (Logging — `Psr\Log\LoggerInterface`).
- **Downward:** CORE-18 (Kernel — calls `ProviderRegistry::discover()` / `registerAll()` / `bootAll()` in its `boot()` phase), and transitively **every** Hub and Spoke package (each ships at least one `ServiceProvider` class annotated with `#[AsProvider]`).
- **Runtime:** PHP 8.3 (`#[\Attribute]`, readonly classes, constructor promotion, `match`, enums), `psr/container: ^2.0`, `psr/log: ^3.0`. Dev: `phpunit/phpunit ^10.5`, `phpstan/phpstan ^1.10`, `friendsofphp/php-cs-fixer ^3.48`.

## Architectural Design

### Class Map

| Class | Kind | Responsibility |
|---|---|---|
| `ServiceProviderInterface` | interface | Contract every provider implements. Two methods: `register(ContainerInterface $container): void` (bind services) and `boot(ContainerInterface $container): void` (post-registration setup). |
| `ServiceProvider` | `abstract class` | Convenience base. Implements `boot()` as a no-op. Subclasses override `register()` and optionally `boot()`. |
| `AsProvider` | `#[\Attribute]` | Auto-discovery marker. Fields: `name` (string), `priority` (int, default 0, higher runs first), `env` (list of `Environment` cases, empty = all envs). |
| `ProviderRegistry` | `final class` | Discovers providers in registered directories, filters by env, sorts by priority then FQCN, instantiates each via the container, and runs `register()` / `boot()` in the correct phase. Closes after `bootAll()`. |
| `BootException` | `final class extends \RuntimeException` | Wraps any `\Throwable` raised inside a provider's `register()` or `boot()`. Carries the provider's FQCN and phase (`'register'` or `'boot'`). Kernel treats as fatal — no partial boot. |
| `ProviderDiscoveryException` | `final class extends \RuntimeException` | Thrown by `discover()` on unreadable directory, reflection failure, or non-SPI class carrying `#[AsProvider]`. |

### Interface Contracts

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Providers;

use SovereignStack\Core\Container\ContainerInterface;

/**
 * Contract every Sovereign Stack service provider implements.
 *
 * A service provider is the only sanctioned place where a package
 * mutates the {@see ContainerInterface} binding table. Two phases:
 *
 *   1. register() — called for every provider, in priority order,
 *      before any boot(). The provider MUST only call bind() /
 *      singleton() / instance() / addCompilerPass() on $container.
 *      It MUST NOT call make()/get() (resolution against an
 *      incomplete graph is a bug).
 *
 *   2. boot() — called for every provider, in the same order, after
 *      the last register() has returned. The provider MAY call
 *      make()/get() to resolve services and MAY register late
 *      bindings (container still open). Typical use: subscribing
 *      to CORE-03 events, registering CORE-06 routes.
 *
 * After the last boot() returns, the kernel calls compile() which
 * freezes the container; further bind() calls throw \LogicException.
 */
interface ServiceProviderInterface
{
    /**
     * Bind this provider's services into the container.
     *
     * Called in priority order before any boot(). MUST NOT resolve
     * services from $container.
     *
     * @param ContainerInterface $container The open, pre-compile
     *     container. Same instance passed to register() and boot().
     *
     * @throws \Throwable On binding failure. The registry wraps the
     *     throwable in {@see BootException} with phase='register'
     *     and re-throws; the kernel aborts.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Run post-registration setup against the (still open) container.
     *
     * Called in priority order after every register() has returned.
     * MAY resolve via make()/get(). MAY register late bindings.
     * Default in {@see ServiceProvider} is a no-op.
     *
     * @throws \Throwable On boot failure. The registry wraps the
     *     throwable in {@see BootException} with phase='boot' and
     *     re-throws; the kernel aborts.
     */
    public function boot(ContainerInterface $container): void;
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Providers;

use SovereignStack\Core\Config\Environment;

/**
 * Marks a class as a service provider eligible for auto-discovery
 * by {@see ProviderRegistry::discover()}. Required on concrete
 * providers; without it, the class is invisible to discovery
 * (still registrable via {@see ProviderRegistry::addProvider()}).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsProvider
{
    /**
     * @param string            $name     Human-readable id for logs.
     * @param int               $priority Higher runs first. Default 0.
     *                                    Ties broken alphabetically by FQCN.
     * @param list<Environment> $env      Allowed envs. Empty = all envs.
     */
    public function __construct(
        public string $name = '',
        public int $priority = 0,
        public array $env = [],
    ) {
    }
}
```

`ServiceProvider` is a trivial abstract base implementing `ServiceProviderInterface` with a no-op `boot()`; subclasses override `register()` and optionally `boot()`. Concrete providers MUST be declared `final`. (6-line class in `src/ServiceProvider.php`; omitted for brevity.)

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Providers;

/**
 * Wraps any \Throwable raised inside a provider's register() or
 * boot() phase. The kernel treats this as fatal — no partial boot.
 * Carries the provider's FQCN and phase for diagnostics.
 */
final class BootException extends \RuntimeException
{
    /**
     * @param class-string $providerClass FQCN of the failing provider.
     * @param string       $phase         'register' or 'boot'.
     * @param \Throwable   $previous      The original throwable.
     */
    public function __construct(
        private readonly string $providerClass,
        private readonly string $phase,
        \Throwable $previous,
    ) {
        parent::__construct(
            sprintf('Service provider [%s] failed during %s phase: %s',
                $providerClass, $phase, $previous->getMessage()),
            0, $previous,
        );
    }

    public function getProviderClass(): string { return $this->providerClass; }

    /** @return 'register'|'boot' */
    public function getPhase(): string { return $this->phase; }
}
```

`ProviderDiscoveryException` extends `\RuntimeException` with an empty body — a named type so callers can catch discovery failures (missing directory, unreadable file, non-SPI annotated class, reflection error) separately from boot failures. Source: `src/ProviderDiscoveryException.php`, 4 lines.

### Reference Implementation

The following `ProviderRegistry` class is the complete, copy-pasteable implementation. It compiles against PHP 8.3 with only `psr/container: ^2.0`, `psr/log: ^3.0`, and the CORE-02 / CORE-10 interfaces as runtime dependencies. Drop it into `packages/core/providers/src/ProviderRegistry.php` and `composer dump-autoload` will pick it up unchanged.

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Providers;

use SovereignStack\Core\Config\Environment;
use SovereignStack\Core\Config\ConfigInterface;
use SovereignStack\Core\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Discovers, sorts, instantiates, and runs every #[AsProvider]-
 * annotated provider in the registered scan directories.
 *
 * Lifecycle: discover() → registerAll($c) → bootAll($c).
 * Closes after bootAll(); subsequent calls throw \LogicException.
 * Ordering is DETERMINISTIC (see Security Properties §3).
 */
final class ProviderRegistry
{
    /** @var list<class-string<ServiceProviderInterface>> */
    private array $discoveredClasses = [];

    /** @var array<int, ServiceProviderInterface> */
    private array $instantiated = [];

    /** @var list<string> */
    private array $scanDirectories = [];

    private bool $closed = false;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Register a directory to be scanned for #[AsProvider] classes.
     *
     * @throws ProviderDiscoveryException If $directory does not exist.
     */
    public function addScanDirectory(string $directory): void
    {
        $this->assertOpen();
        if (!is_dir($directory)) {
            throw new ProviderDiscoveryException(
                "Scan directory [$directory] does not exist or is not a directory."
            );
        }
        $this->scanDirectories[] = realpath($directory);
    }

    /**
     * Escape hatch: register a provider class explicitly, bypassing
     * discovery. Used by tests and by the kernel for always-on Core
     * providers that must run before any package provider.
     *
     * @param class-string<ServiceProviderInterface> $class
     */
    public function addProvider(string $class): void
    {
        $this->assertOpen();
        if (!is_a($class, ServiceProviderInterface::class, true)) {
            throw new ProviderDiscoveryException(
                "Class [$class] does not implement ServiceProviderInterface."
            );
        }
        $this->discoveredClasses[] = $class;
    }

    /**
     * Walk every scan directory, reflect every #[AsProvider] class,
     * filter by env, sort by priority. Idempotent within a boot cycle.
     *
     * @throws ProviderDiscoveryException On unreadable dir, reflection
     *     failure, or non-SPI annotated class.
     */
    public function discover(): void
    {
        $this->assertOpen();
        $found = [];

        foreach ($this->scanDirectories as $dir) {
            foreach ($this->findPhpFiles($dir) as $file) {
                foreach ($this->classesInFile($file) as $class) {
                    try {
                        $reflection = new \ReflectionClass($class);
                    } catch (\ReflectionException $e) {
                        throw new ProviderDiscoveryException(
                            "Failed to reflect [$class] from [$file]: " . $e->getMessage(),
                            0, $e,
                        );
                    }

                    $attributes = $reflection->getAttributes(AsProvider::class);
                    if ($attributes === []) {
                        continue;
                    }

                    if (!$reflection->implementsInterface(ServiceProviderInterface::class)) {
                        throw new ProviderDiscoveryException(
                            "Class [$class] has #[AsProvider] but does not implement ServiceProviderInterface."
                        );
                    }

                    /** @var AsProvider $attr */
                    $attr = $attributes[0]->newInstance();

                    if (!$this->envAllows($attr)) {
                        $this->logger->debug(
                            'Provider {provider} skipped (env restriction)',
                            ['provider' => $class]
                        );
                        continue;
                    }

                    $found[] = ['class' => $class, 'priority' => $attr->priority];
                }
            }
        }

        // Deterministic sort: priority DESC, then FQCN ASC.
        usort($found, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }
            return strcmp($a['class'], $b['class']);
        });

        /** @var list<class-string<ServiceProviderInterface>> $ordered */
        $ordered = array_column($found, 'class');
        $this->discoveredClasses = array_values(array_unique($ordered));

        $this->logger->debug(
            'Discovered {count} service providers (scan dirs: {dirs})',
            ['count' => count($this->discoveredClasses), 'dirs' => $this->scanDirectories]
        );
    }

    /**
     * Instantiate each discovered provider via $container and call
     * register($container) on each. Container MUST be open (pre-compile).
     *
     * @throws BootException If any provider's register() throws.
     */
    public function registerAll(ContainerInterface $container): void
    {
        $this->assertOpen();

        foreach ($this->discoveredClasses as $class) {
            try {
                /** @var ServiceProviderInterface $provider */
                $provider = $container->make($class);
            } catch (\Throwable $e) {
                throw new BootException($class, 'register', $e);
            }

            $this->instantiated[] = $provider;

            try {
                $provider->register($container);
            } catch (\Throwable $e) {
                throw new BootException($class, 'register', $e);
            }

            $this->logger->debug('Provider {provider} registered', ['provider' => $class]);
        }
    }

    /**
     * Call boot($container) on every provider instantiated by
     * registerAll(), in the same order.
     *
     * @throws BootException If any provider's boot() throws.
     */
    public function bootAll(ContainerInterface $container): void
    {
        $this->assertOpen();

        foreach ($this->instantiated as $provider) {
            $class = $provider::class;
            try {
                $provider->boot($container);
            } catch (\Throwable $e) {
                throw new BootException($class, 'boot', $e);
            }
            $this->logger->debug('Provider {provider} booted', ['provider' => $class]);
        }

        $this->closed = true;
    }

    // ----- Internals -----

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \LogicException(
                'ProviderRegistry is closed; the boot cycle has already completed.'
            );
        }
    }

    private function envAllows(AsProvider $attr): bool
    {
        if ($attr->env === []) {
            return true;
        }
        $current = $this->config->getEnvironment();
        foreach ($attr->env as $allowed) {
            if ($allowed === $current) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> Absolute paths to *.php files under $dir. */
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }
        return $files;
    }

    /**
     * Extract class FQCNs from a PHP file without executing it.
     * Uses token_get_all() — the security boundary (see Security
     * Properties §2). No include(), no require(), no eval().
     *
     * @return list<class-string>
     */
    private function classesInFile(string $file): array
    {
        $tokens = token_get_all(file_get_contents($file));
        $classes = [];
        $namespace = '';
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                $i++;
                while ($i < $count) {
                    $t = $tokens[$i];
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $namespace .= $t[1];
                    } elseif ($t === '{' || $t === ';') {
                        break;
                    }
                    $i++;
                }
            }

            if (is_array($token) && $token[0] === T_CLASS) {
                $i++;
                while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $short = $tokens[$i][1];
                    $classes[] = ($namespace !== '' ? $namespace . '\\' : '') . $short;
                }
            }

            $i++;
        }

        return $classes;
    }
}
```

### SQL DDL
Not applicable. CORE-17 holds no persistent state. The discovered-provider list, instantiated providers, and `closed` flag all live in process memory for the duration of a single kernel boot. A future revision may cache the sorted provider list to skip the directory scan on warm boots; that cache lives under CORE-15 (Cache Abstraction), not here.

### Sequence Diagram

```mermaid
sequenceDiagram
    participant Kernel as CORE-18 Kernel
    participant Registry as ProviderRegistry
    participant Scan as Filesystem scan
    participant Reflector as Reflection
    participant Container as CORE-02 Container
    participant Provider as ServiceProvider #1..N

    Kernel->>Registry: addScanDirectory(packages/hub/)
    Kernel->>Registry: addScanDirectory(packages/spoke/)
    Kernel->>Registry: discover()
    Registry->>Scan: walk directories, tokenise *.php
    Scan-->>Registry: list of class FQCNs
    loop each discovered class
        Registry->>Reflector: ReflectionClass(class)
        Reflector-->>Registry: #[AsProvider] attr? implements SPI?
        Registry->>Registry: env filter (ConfigInterface)
    end
    Registry->>Registry: sort by priority DESC, FQCN ASC
    Kernel->>Registry: registerAll(container)
    loop each provider (priority order)
        Registry->>Container: make(ProviderClass)
        Container-->>Registry: Provider instance
        Registry->>Provider: register(container)
        Provider->>Container: bind() / singleton() / instance() / addCompilerPass()
        Provider-->>Registry: (void)
    end
    Kernel->>Registry: bootAll(container)
    loop each provider (same order)
        Registry->>Provider: boot(container)
        Provider->>Container: make() / get() (resolve dependencies)
        Provider-->>Registry: (void)
    end
    Registry-->>Kernel: (void; registry now closed)
    Kernel->>Container: compile()
    Note over Container: Compiler passes run; container freezes.
    Container-->>Kernel: (void; container now Compiled)
```

### State Diagram

```mermaid
stateDiagram-v2
    [*] --> Empty: new ProviderRegistry()
    Empty --> Scanning: addScanDirectory() + discover()
    Scanning --> Discovered: discover() returns
    Discovered --> Registering: registerAll(container)
    Registering --> Registered: every register() returned
    Registered --> Booting: bootAll(container)
    Booting --> Closed: every boot() returned (registry closes)
    Closed --> [*]: kernel proceeds to Container::compile()

    note right of Closed
        After Closed:
        - discover()         -> \LogicException
        - registerAll()      -> \LogicException
        - bootAll()          -> \LogicException
        - addScanDirectory() -> \LogicException
        - addProvider()      -> \LogicException
    end note

    note right of Booting
        If any boot() throws:
        BootException wraps the cause,
        registry state is abandoned,
        Kernel aborts (no compile()).
    end note
```

Provider instances themselves transition `Discovered → Registered → Booted → Compiled` as the boot cycle progresses. A provider cannot move backwards: once `boot()` has returned, its reference to the container is expected to be dropped.

## Integration Strategy

**Upward (what CORE-17 consumes).** The registry depends on three Core-tier contracts at runtime: `ContainerInterface` (CORE-02), `ConfigInterface` plus the `Environment` enum (CORE-10), and `Psr\Log\LoggerInterface` (CORE-09, optional — defaults to `NullLogger`). Discovery uses PHP's `RecursiveDirectoryIterator` and `token_get_all()`; no third-party filesystem abstraction is invoked.

**Downward (what consumes CORE-17).**

1. **CORE-18 (Kernel)** is the only direct consumer. The kernel's `boot()` method:
   ```php
   $registry = new ProviderRegistry($config, $logger);
   $registry->addScanDirectory(__DIR__ . '/../../../packages/hub');
   $registry->addScanDirectory(__DIR__ . '/../../../packages/spoke');
   $registry->discover();
   $registry->registerAll($container);
   $registry->bootAll($container);
   $container->compile();
   ```
   After `compile()`, the container is frozen and the registry is closed.

2. **Every Hub package** (HUB-01..30) ships at least one provider. Example for HUB-02 (Cache):
   ```php
   #[AsProvider(name: 'cache', priority: 100, env: [Environment::Dev, Environment::Production])]
   final class CacheServiceProvider extends ServiceProvider
   {
       public function register(ContainerInterface $c): void
       {
           $c->singleton(CacheInterface::class, RedisCache::class);
       }
       public function boot(ContainerInterface $c): void
       {
           $c->get(EventDispatcherInterface::class)
               ->subscribe(CacheInvalidated::class, new CacheMetricsListener());
       }
   }
   ```

3. **Every Spoke package** (ISPOKE-01..25, ESPOKE-01..15) ships its own provider. Convention: Core providers at priority ≥ 1000, Hub providers at priority ≥ 100, Spoke providers at 0–99 — ensuring Hub-tier services are available by the time each Spoke's `boot()` runs.

4. **CORE-13 (CLI Engine)** uses CORE-17 to discover console command providers: a `#[AsProvider]`-annotated `CommandServiceProvider` registers `#[AsCommand]`-attributed classes via a compiler pass. This is the explicit `C17 --> C13` edge in the master-index DAG.

**Compiler-pass hook.** A provider that needs to rewrite the service graph (e.g., build a tagged iterator of all `#[AsCommand]` classes) calls `$container->addCompilerPass($pass)` inside `register()`. The pass runs during `Container::compile()`, after `bootAll()` has returned. The pass receives a `ContainerBuilderInterface` (read-only view) and may call back into the container's mutation API. This is the extension point that a future `CompiledContainerPass` will use to flatten the definition table into a cached PHP array (per CORE-02 Integration Strategy §5).

## Benchmark & Verification Methodology

| Target | Harness | Baseline | Load model | Status |
|---|---|---|---|---|
| Boot time per provider | PHPUnit `--group performance` | GitHub Actions `ubuntu-latest`, PHP 8.3, `opcache.enable_cli=1`, no Xdebug | 10 / 50 / 100 synthetic providers (each `register()` binds 5 singletons, each `boot()` is a no-op); 100 boot cycles per cohort; report median + p95 of `microtime(true)` deltas around `registerAll() + bootAll()` | **Provisional, unverified** until first CI measurement run |
| Linear scaling | Same | Same | Assert `T(100 providers) / T(10 providers) <= 12` (linear would be `<= 10`; allow 20 % slack for constant overhead) | **Provisional, unverified** |
| Discovery time | Same | Same | 1,000 PHP files in scan tree, 100 of which carry `#[AsProvider]`; assert `discover()` < 50 ms wall-clock | **Provisional, unverified** |
| Memory per provider | Same | Same | `memory_get_usage(true)` delta around `registerAll()` with 100 providers; assert < 1 MB | **Provisional, unverified** |
| Determinism | Same | Same | Two `discover()` calls against identical scan trees must produce byte-identical provider class lists (assert `===` on serialized array) | Required — must pass on every PR |

**Iron rule (Finding 10 / Governance Rule 2):** absolute millisecond targets above are **provisional and unverified** until a measurement run is committed to CI. No performance regression of more than 20 % against the recorded baseline is acceptable on `main`.

## CI Verification Criteria

- **Branch coverage:** 100 % on `ProviderRegistry` — specifically on `discover()`, `registerAll()`, `bootAll()`, `envAllows()`, the `usort` comparator (both branches: priority-differs, priority-equal), and `classesInFile()` (T_NAMESPACE present / absent, T_CLASS present / absent, namespaced / global class). Measured via PHPUnit's coverage report against `src/`.
- **Static analysis:** `phpstan.neon` at `level: 8` over `src/` and `tests/`. CI runs `vendor/bin/phpstan analyse --error-format=github` and fails on any error. Zero baseline-ignored errors are permitted without an ADR.
- **Priority ordering test:** fixture with `PriorityTenProvider` (`priority: 10`) and `PriorityZeroProvider` (`priority: 0`). Assert `PriorityTenProvider::register()` runs before `PriorityZeroProvider::register()`; same order holds for `boot()`.
- **Priority tie-break test:** two providers with `priority: 0`, class names `AlphaProvider` and `BetaProvider`. Assert `AlphaProvider` runs first (alphabetical by FQCN).
- **Env restriction test:** a provider annotated `#[AsProvider(env: [Environment::Production])]`. Boot the registry with `ConfigInterface::getEnvironment()` returning `Environment::Dev`. Assert the provider is never instantiated (side-effect flag in constructor) and never appears in the discovered list.
- **Attribute discovery test:** point `addScanDirectory()` at a fixture directory with 3 providers annotated `#[AsProvider]` and 2 plain classes. Assert `discover()` returns exactly the 3 annotated classes, in priority order.
- **Circular provider dependency test:** Provider A's `boot()` calls `$container->get(B::class)`, B's constructor calls `$container->get(A::class)`, A's constructor calls `$container->get(B::class)`. Assert `registerAll()` succeeds (constructors don't run during `register()` for class-string bindings resolved via `make()`), then `bootAll()` throws `BootException` wrapping CORE-02's `CircularDependencyException`. Kernel aborts before `compile()`.
- **Partial-boot safety test:** Provider N out of 10 throws inside `register()`. Assert `registerAll()` throws `BootException` with `getProviderClass() === N::class` and `getPhase() === 'register'`, and providers N+1..10 were never instantiated (side-effect counters).
- **Registry closure test:** after `bootAll()` returns, assert `discover()`, `registerAll()`, `bootAll()`, `addScanDirectory()`, `addProvider()` all throw `\LogicException`.
- **Discovery failure test:** `addScanDirectory()` on a non-existent path throws `ProviderDiscoveryException`; a fixture class with `#[AsProvider]` that does not implement `ServiceProviderInterface` triggers `ProviderDiscoveryException` from `discover()`.
- **Dependency hygiene:** no new runtime dependency may be added beyond `psr/container: ^2.0`, `psr/log: ^3.0`, and the CORE-02 / CORE-10 packages without (a) an ADR documenting the justification and (b) an update to `01_MASTER_INDEX.md` §2.

## Security Properties

1. **Providers cannot register bindings after `Container::compile()`.** CORE-02's `bind()` / `singleton()` / `instance()` / `addCompilerPass()` each call `assertNotCompiled()` as their first line and throw `\LogicException` if the container is frozen. The kernel calls `compile()` only after `bootAll()` returns, and the registry closes itself at the end of `bootAll()` — there is no code path by which a provider can mutate the container post-compile. CORE-17 does not re-enforce this invariant; it relies on CORE-02.
2. **Provider discovery only scans explicitly registered directories.** `discover()` walks exactly the directories passed to `addScanDirectory()` plus the classes registered via `addProvider()`. No global classmap walk, no `vendor/` scan, no `spl_autoload_functions()` enumeration. The tokeniser (`token_get_all()`) reads file contents without `include`-ing them; the autoloader is only invoked later, for classes that passed the env filter and the `#[AsProvider]` check.
3. **Provider priority is deterministic.** Same scan directories + same `Environment` ⇒ byte-identical provider order, every boot. The comparator is `priority DESC, then strcmp(FQCN_a, FQCN_b) ASC` — no hash-order, no insertion order, no filesystem readdir order. This is a security property because indeterminate boot order is indistinguishable from a supply-chain injection at runtime.
4. **`boot()` exceptions are fatal — no partial boot.** Any `\Throwable` raised inside a provider's `register()` or `boot()` is wrapped in `BootException` and re-thrown. The kernel does not catch `BootException` (per CORE-18 spec); it propagates to CORE-08, which terminates the process with a non-zero exit code. There is no "skip the failing provider and continue" path: a partially booted service graph is worse than no service graph, because downstream code would silently resolve `null` from missing bindings.
5. **Env-restricted providers are not instantiated outside their env.** The env filter runs in `discover()` *before* the class is reflected (which would trigger autoload) or instantiated (which would run the constructor). A provider with `env: ['Production']` is never loaded in `Dev` — its file is tokenised (read-only), but `class_exists()` / `ReflectionClass` / `make()` are never called on it. This prevents development-only side effects (e.g. a provider that opens a production-only socket in its constructor) from leaking across environments.
6. **The registry stores no reference to the container after `bootAll()`.** The `instantiated` array is the only state retained between `registerAll()` and `bootAll()`; on `closed = true`, the kernel drops its reference to the registry and the array is GC'd. There is no static registry, no singleton `ProviderRegistry::instance()`, no globally-accessible list of providers. The container's frozen definition table is the single source of truth after boot.

## Migration Notes

**Landing the implementation.** A new package is created at `packages/core/providers/`. Its `composer.json` declares `name: sovereign-stack/core-providers`, `php: ^8.3`, runtime deps `psr/container: ^2.0`, `psr/log: ^3.0`, `sovereign-stack/core-container: ^1.0`, `sovereign-stack/core-config: ^1.0`; dev deps `phpunit/phpunit ^10.5`, `phpstan/phpstan ^1.10`, `friendsofphp/php-cs-fixer ^3.48`; PSR-4 autoloads `SovereignStack\Core\Providers\` from `src/`. The implementation lands as six PHP files: `ServiceProviderInterface.php`, `ServiceProvider.php`, `AsProvider.php`, `BootException.php`, `ProviderDiscoveryException.php`, `ProviderRegistry.php`. Tests land in `tests/Unit/`, `tests/Integration/`, `tests/Performance/` (`@group performance`, 10/50/100 providers), and `tests/Fixtures/` (including a `NonProviderClass` carrying `#[AsProvider]` but no SPI, to trigger `DiscoveryException`). Until CORE-18 lands, the registry can be unit-tested in isolation against fakes for `ConfigInterface` and a fresh `Container` (CORE-02) per test.

**Downstream unblock.** Once CORE-17 lands on `main`, every Hub blueprint (HUB-01..30) becomes implementable to its full Integration Strategy — each Hub's `register()` block assumes a `ContainerInterface` with `bind()` / `singleton()` / `addCompilerPass()` and a registry that calls `register()` then `boot()`. CORE-13 (CLI) and CORE-20 (Forge) likewise become unblocked: Forge scaffolds a new Hub service by writing a `*ServiceProvider.php` file with the `#[AsProvider]` attribute already in place.

**Rollback.** Reverting the PR removes the `packages/core/providers/` directory and the `sovereign-stack/core-providers` entry from the root `composer.json`. Downstream packages continue to compile — their `*ServiceProvider` classes are simply never discovered. The kernel must be patched in the same revert to register bindings manually (a regression to the pre-CORE-17 monolithic `Kernel::registerBindings()` method); this is the explicit rollback cost and the reason the migration is **SemVer major** for the kernel package. No schema migration, no data migration, no production deployment to undo.

**Package SemVer.** First release of `sovereign-stack/core-providers`; tag `1.0.0`. Future changes to `ServiceProviderInterface` (added methods, signature changes) are SemVer major. Changes to `AsProvider`'s fields (new required field, removed field) are SemVer major; new optional fields with defaults are SemVer minor. Changes to `ProviderRegistry`'s sort comparator are SemVer major (callers depend on determinism). Bug fixes are SemVer patch.

## SemVer Impact
**Major.** Inaugural `1.0.0` release of `sovereign-stack/core-providers`. Introduces the `ServiceProviderInterface`, `ServiceProvider` abstract base, `AsProvider` attribute, `BootException`, `ProviderDiscoveryException`, and `ProviderRegistry` contracts that every Hub and Spoke package depends on. Any future change to these interfaces or to the sort comparator (which downstream packages depend on for determinism) is SemVer major. The `ProviderRegistry` private internals (`findPhpFiles`, `classesInFile`, `envAllows`) may change in minor releases; the public method signatures (`addScanDirectory`, `addProvider`, `discover`, `registerAll`, `bootAll`) are part of the published API surface and are SemVer-major-locked.
