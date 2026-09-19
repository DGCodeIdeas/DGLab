# DGLab Wheel Architecture
## Structure 06: Boot & Provider Architecture

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Provider-Driven Boot

---

## 1. The Boot Principle

The DGLab wheel does not "start" as a monolithic application. It **assembles itself** at runtime through a provider-driven boot sequence. Each component — Core, Hub, and Spoke — ships a `ServiceProvider` that declares:
- What services it **registers** (bindings in the container)
- What services it **provides** (interfaces it implements)
- What it needs to **boot** (event listeners, routes, scheduled tasks)

**Rule:** No component is loaded unless its provider is registered. The Kernel does not know about Hub services or Spokes directly — it only knows about providers.

---

## 2. The Boot Sequence

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BOOT PHASE DIAGRAM                                  │
│                                                                             │
│   Phase 1: CONSTRUCT                                                        │
│   ├──► Create empty Container (CORE-02)                                     │
│   └──► Load environment + config files (CORE-10)                            │
│                                                                             │
│   Phase 2: DISCOVER                                                         │
│   ├──► Scan configured provider directories                                 │
│   ├──► Instantiate all ServiceProvider classes                              │
│   └──► Sort by priority (lowest first)                                      │
│                                                                             │
│   Phase 3: REGISTER                                                         │
│   ├──► For each provider: call register($container)                         │
│   │         └──► Bind interfaces to implementations                         │
│   │         └──► Declare singletons                                         │
│   │         └──► Register config keys                                       │
│   │         └──► NO service resolution allowed here                         │
│   └──► All providers registered                                             │
│                                                                             │
│   Phase 4: COMPILE                                                          │
│   ├──► Run compiler passes                                                  │
│   ├──► Resolve circular dependencies                                        │
│   ├──► Freeze container (no more bindings)                                  │
│   └──► Container is now immutable                                           │
│                                                                             │
│   Phase 5: BOOT                                                             │
│   ├──► For each provider: call boot($container)                             │
│   │         └──► Resolve services (container is ready)                      │
│   │         └──► Attach event listeners (HUB-09)                            │
│   │         └──► Register routes (CORE-06)                                  │
│   │         └──► Register health checks (HUB-15)                            │
│   │         └──► Register scheduled tasks (HUB-24)                          │
│   └──► All providers booted                                                 │
│                                                                             │
│   Phase 6: LISTEN                                                           │
│   ├──► HTTP: Start request loop (public/index.php)                          │
│   ├──► CLI: Run command (bin/console)                                       │
│   └──► Worker: Start job consumer (bin/worker)                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. ServiceProvider Contract

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Providers;

use SovereignStack\Core\Container\ContainerInterface;

interface ServiceProviderInterface
{
    /**
     * REGISTER phase: Bind services into the container.
     * 
     * RULE: Do NOT resolve services here. The container is not compiled.
     *        Only bind() and singleton() calls are allowed.
     */
    public function register(ContainerInterface $container): void;

    /**
     * BOOT phase: Initialize the provider after container compilation.
     * 
     * RULE: Services may be resolved here. The container is frozen and ready.
     *        Attach listeners, register routes, start background tasks.
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Boot priority. Lower numbers boot earlier.
     * Core providers: -100 to -50
     * Hub providers: 0 to 50
     * Spoke providers: 100 to 200
     */
    public function priority(): int;

    /**
     * Deferred providers are only booted when one of their provided
     * services is first requested. Return empty array for eager providers.
     * 
     * @return array<int, string> Service IDs this provider provides.
     */
    public function provides(): array;
}
```

### 3.1 Register vs. Boot: The Critical Distinction

```php
// CORRECT: Register phase — only bindings
class IdentityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        // ✅ Bind interface to implementation
        $container->singleton(
            IdentityServiceInterface::class,
            fn() => new IdentityService(
                // ✅ Lazy: dependencies resolved at first use, not now
                dbal: $container->get(DbalInterface::class),
                cache: $container->get(CacheInterface::class),
            )
        );

        // ✅ Register config schema
        $container->get(ConfigRepositoryInterface::class)->define(
            key: 'identity.password_min_length',
            type: 'int',
            default: 12,
        );
    }

    public function boot(ContainerInterface $container): void
    {
        // ✅ Resolve services (container is compiled)
        $events = $container->get(EventBusInterface::class);
        $service = $container->get(IdentityServiceInterface::class);

        // ✅ Attach event listeners
        $events->listen(
            eventType: 'user.password_changed',
            listener: new InvalidateUserSessionsListener($service),
        );

        // ✅ Register health check
        $container->get(HealthRegistryInterface::class)->register(
            new DatabaseHealthCheck($container->get(DbalInterface::class))
        );
    }
}
```

```php
// WRONG: Register phase resolving services
class BadProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        // ❌ WRONG: Resolving a service during register()
        // This may fail if the dependency's provider hasn't registered yet
        $dbal = $container->get(DbalInterface::class);

        $container->singleton(
            MyService::class,
            fn() => new MyService($dbal)
        );
    }
}
```

---

## 4. Provider Discovery

### 4.1 Discovery Sources

Providers are discovered from multiple sources, in priority order:

```php
// config/providers.php
return [
    // Core providers (always loaded first)
    'core' => [
        \SovereignStack\Core\Providers\ConfigProvider::class,      // priority: -100
        \SovereignStack\Core\Providers\LoggingProvider::class,    // priority: -90
        \SovereignStack\Core\Providers\ErrorHandlerProvider::class, // priority: -80
        \SovereignStack\Core\Providers\CryptoProvider::class,     // priority: -70
        \SovereignStack\Core\Providers\HttpProvider::class,       // priority: -60
        \SovereignStack\Core\Providers\RoutingProvider::class,    // priority: -50
        \SovereignStack\Core\Providers\DatabaseProvider::class,   // priority: -40
        \SovereignStack\Core\Providers\EventProvider::class,      // priority: -30
    ],

    // Hub providers (loaded from hub/ directory scan)
    'hub' => 'auto-discover://hub/src/*/ServiceProvider.php',

    // Spoke providers (loaded from spokes/ directory scan)
    'spokes' => 'auto-discover://spokes/*/ServiceProvider.php',

    // Bridge provider (loaded conditionally based on entry point)
    'bridge' => \SovereignStack\Bridge\Vanguard\VanguardServiceProvider::class,
];
```

### 4.2 Auto-Discovery

```php
final class ProviderDiscovery
{
    /**
     * Scan a directory for ServiceProvider classes.
     */
    public function discover(string $directory): array
    {
        $providers = [];

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        ) as $file) {
            if ($file->getFilename() === 'ServiceProvider.php') {
                $class = $this->inferClassFromPath($file->getPathname());
                if (is_a($class, ServiceProviderInterface::class, true)) {
                    $providers[] = $class;
                }
            }
        }

        return $providers;
    }
}
```

**Production optimization:** Discovery is cached to `storage/bootstrap/providers.php` at deploy time. The Kernel loads from cache — no filesystem scan at runtime.

---

## 5. Provider Priority & Ordering

### 5.1 Default Priority Map

| Priority Range | Tier | Examples |
|---|---|---|
| -100 to -51 | Core foundation | Config, Logging, Error Handler, Crypto |
| -50 to -1 | Core HTTP | PSR-7, Middleware, Router, DBAL |
| 0 to 49 | Hub infrastructure | Identity, Cache, RBAC, Audit, Event Bus |
| 50 to 99 | Hub services | Search, Notify, Queue, Billing, Reporter |
| 100 to 149 | Inner Spokes | Admin, SOC, Ops, Content Studio |
| 150 to 199 | Outer Spokes | Public CMS, API, Account Hub, Forum |
| 200+ | Bridge / Edge | Vanguard, CDN integration |

### 5.2 Dependency-Based Ordering

If Provider B depends on Provider A, but B has a higher priority number (boots later), the system is safe — A's `register()` runs before B's `register()`.

If Provider B depends on Provider A, but B has a **lower** priority number (boots earlier), this is an error:

```
ProviderB (priority: 10) tries to get ServiceA
    └──► ServiceA not yet registered (ProviderA has priority: 20)
    └──► \SovereignStack\Core\Container\NotFoundException
```

**Detection:** A static analysis pass validates provider priorities against their declared dependencies.

---

## 6. Deferred Providers

Deferred providers are **lazy-loaded** — they are only booted when one of their services is first requested.

```php
// HUB-22 (Billing) — rarely used, so defer it
class BillingServiceProvider implements ServiceProviderInterface
{
    public function provides(): array
    {
        return [
            BillingServiceInterface::class,
            InvoiceRepositoryInterface::class,
            SubscriptionManagerInterface::class,
        ];
    }

    public function priority(): int
    {
        return 50; // Will be skipped during eager boot
    }

    public function register(ContainerInterface $container): void
    {
        $container->singleton(BillingServiceInterface::class, fn() => new BillingService(...));
        // ... other bindings
    }

    public function boot(ContainerInterface $container): void
    {
        // Only called when BillingServiceInterface is first resolved
        $container->get(EventBusInterface::class)->listen(
            'subscription.renewed',
            new GenerateInvoiceListener(...)
        );
    }
}
```

**Benefit:** If no Pulse ever touches billing functionality, the Billing provider never boots. Memory and CPU are saved.

---

## 7. Conditional Providers

Some providers are only loaded in specific environments or entry points:

```php
// Bridge provider — only loaded for HTTP entry
class VanguardServiceProvider implements ServiceProviderInterface
{
    public function shouldRegister(string $entryPoint): bool
    {
        return $entryPoint === 'http'; // Not loaded for CLI or Worker
    }
}

// Testing harness — only loaded in test environment
class TestingServiceProvider implements ServiceProviderInterface
{
    public function shouldRegister(string $environment): bool
    {
        return $environment === 'testing' || $environment === 'development';
    }
}

// Worker-specific provider — only loaded for queue workers
class WorkerMonitoringProvider implements ServiceProviderInterface
{
    public function shouldRegister(string $entryPoint): bool
    {
        return $entryPoint === 'worker';
    }
}
```

---

## 8. The Kernel Boot Implementation

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

final class Kernel implements KernelInterface
{
    private bool $booted = false;
    private ContainerInterface $container;
    private ProviderRegistry $providers;

    public function __construct(
        private readonly string $basePath,
        private readonly Environment $environment,
        private readonly string $entryPoint = 'http', // 'http' | 'cli' | 'worker'
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Phase 1: Construct
        $this->container = new Container();
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(KernelInterface::class, $this);

        // Phase 2: Discover
        $this->providers = new ProviderRegistry(
            configPath: $this->basePath . '/config/providers.php',
            cachePath: $this->basePath . '/storage/bootstrap/providers.php',
            environment: $this->environment->value,
            entryPoint: $this->entryPoint,
        );

        $providerClasses = $this->providers->discover();

        // Phase 3: Register
        foreach ($providerClasses as $class) {
            $provider = new $class();

            if (method_exists($provider, 'shouldRegister') 
                && !$provider->shouldRegister($this->entryPoint)) {
                continue;
            }

            $provider->register($this->container);
            $this->providers->track($provider);
        }

        // Phase 4: Compile
        $this->container->compile();

        // Phase 5: Boot (eager providers only)
        foreach ($this->providers->eager() as $provider) {
            $provider->boot($this->container);
        }

        // Phase 6: Ready
        $this->booted = true;

        // Log boot completion
        $this->container->get(LoggerInterface::class)->info('Kernel booted', [
            'environment' => $this->environment->value,
            'entry_point' => $this->entryPoint,
            'providers' => count($providerClasses),
        ]);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->boot();

        $pipeline = $this->container->get(PipelineInterface::class);
        return $pipeline->handle($request);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            throw new \RuntimeException('Kernel not booted');
        }
        return $this->container;
    }
}
```

---

## 9. Provider Examples by Tier

### 9.1 Core Provider Example

```php
// CORE-09 (Logging) Provider
class LoggingProvider implements ServiceProviderInterface
{
    public function priority(): int { return -90; }

    public function provides(): array { return []; } // Always eager

    public function register(ContainerInterface $container): void
    {
        $container->singleton(LoggerInterface::class, function ($c) {
            $config = $c->get(ConfigRepositoryInterface::class);

            $logger = new Logger(channel: 'app');
            $logger->addHandler(new StreamHandler(
                stream: $config->get('logging.path', 'php://stderr'),
                level: $config->get('logging.level', 'debug'),
            ));

            return $logger;
        });
    }

    public function boot(ContainerInterface $container): void
    {
        // Attach request ID processor after container is ready
        $logger = $container->get(LoggerInterface::class);
        $logger->pushProcessor(new RequestIdProcessor());
    }
}
```

### 9.2 Hub Provider Example

```php
// HUB-04 (Identity) Provider
class IdentityServiceProvider implements ServiceProviderInterface
{
    public function priority(): int { return 10; }

    public function provides(): array { return []; } // Always eager

    public function register(ContainerInterface $container): void
    {
        $container->singleton(IdentityServiceInterface::class, function ($c) {
            return new IdentityService(
                dbal: $c->get(DbalInterface::class),
                cache: $c->get(CacheInterface::class),
                crypto: $c->get(EncryptionInterface::class),
                hasher: $c->get(PasswordHasherInterface::class),
            );
        });

        $container->singleton(TokenServiceInterface::class, function ($c) {
            return new JwtTokenService(
                signer: $c->get(SigningInterface::class),
                config: $c->get(ConfigRepositoryInterface::class),
            );
        });
    }

    public function boot(ContainerInterface $container): void
    {
        $events = $container->get(EventBusInterface::class);

        // Listen to password changes for session invalidation
        $events->listen('user.password_changed', function ($event) use ($container) {
            $container->get(CacheInterface::class)->flushTags([
                "user:{$event->tenantId}:sessions"
            ]);
        });

        // Register health check
        $container->get(HealthRegistryInterface::class)->register(
            new IdentityHealthCheck($container->get(DbalInterface::class))
        );
    }
}
```

### 9.3 Spoke Provider Example

```php
// ESPOKE-01 (Public CMS) Provider
class CmsServiceProvider implements ServiceProviderInterface
{
    public function priority(): int { return 150; }

    public function provides(): array { return []; }

    public function register(ContainerInterface $container): void
    {
        $container->singleton(CmsRepositoryInterface::class, function ($c) {
            return new CmsRepository(
                dbal: $c->get(DbalInterface::class),
                cache: $c->get(CacheInterface::class),
            );
        });
    }

    public function boot(ContainerInterface $container): void
    {
        // Register routes
        $router = $container->get(RouterInterface::class);
        $router->register(CmsController::class);

        // Register event listeners
        $events = $container->get(EventBusInterface::class);
        $events->listen('content.published', function ($event) use ($container) {
            $container->get(CacheInterface::class)->flushTags([
                "tenant:{$event->tenantId}:cms:pages"
            ]);
        });
    }
}
```

---

## 10. Boot Failure Modes

| Failure | Phase | Cause | Recovery |
|---|---|---|---|
| Provider not found | Discover | Class missing or misnamed | Fatal — fix provider class name |
| Register resolution | Register | Provider calls `get()` in `register()` | Fatal — refactor to lazy binding |
| Circular dependency | Compile | A depends on B, B depends on A | Fatal — refactor to interface segregation |
| Boot exception | Boot | Service throws during initialization | Fatal — fix service constructor |
| Missing config | Boot | Required config key not defined | Fatal — add config default or env var |
| Deferred provider fail | Runtime | Deferred provider's `boot()` throws | Graceful — service unavailable, log error |

---

## 11. Boot Performance

| Metric | Target | Measurement |
|---|---|---|
| Provider discovery | < 1ms (cached) | `storage/bootstrap/providers.php` mtime check |
| Register phase | < 50ms | Cumulative `register()` time |
| Compile phase | < 20ms | Container compilation |
| Boot phase | < 100ms | Cumulative `boot()` time |
| Total cold boot | < 200ms | `Kernel::boot()` wall-clock |
| Warm boot (opcache) | < 50ms | Second request with opcache |

**Optimization strategies:**
- Provider cache: `storage/bootstrap/providers.php` generated at deploy
- Config cache: `storage/bootstrap/config.php` frozen at deploy
- Route cache: Compiled route table in `storage/bootstrap/routes.php`
- OPcache preloading: `preload.php` loads all provider classes at startup

---

*End of Structure 06: Boot & Provider Architecture*
