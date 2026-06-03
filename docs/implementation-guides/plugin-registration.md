# Implementation Guide: Plugin Registration (Service Providers)

## Overview
This guide walks through creating and registering Service Providers for the Sovereign Stack. Service Providers ([CORE-17](/ApprovedBlueprints/Core/CORE-17.md)) are the wiring mechanism that allows components to register services and perform boot logic without creating circular dependencies.

**Reference**: [ADR-002 Plugin System Architecture](/docs/architecture/decisions/ADR-002-plugin-system-architecture.md)

## Prerequisites
- DI Container configured and working ([DI Setup Guide](./di-container-setup.md))
- Your component's dependencies identified

## Step 1: Create a Service Provider

Create `app/Providers/AnalyticsServiceProvider.php`:

```php
<?php
namespace App\Providers;

use Sovereign\Core\Container\ServiceProvider;
use App\Services\Analytics\AnalyticsEngine;
use App\Services\Analytics\EventTracker;

class AnalyticsServiceProvider extends ServiceProvider
{
    // If true, this provider only loads when one of its services is requested
    protected bool $deferred = true;

    // Services this provider makes available (required when deferred = true)
    protected array $provides = [
        AnalyticsEngine::class,
        EventTracker::class,
    ];

    /**
     * Phase 1: Register bindings into the container.
     * Do NOT type-hint other services here — they may not be registered yet.
     */
    public function register(): void
    {
        $this->app->bind(AnalyticsEngine::class, null, true);  // singleton
        $this->app->bind(EventTracker::class);
    }

    /**
     * Phase 2: Boot logic — all services are registered.
     * Safe to resolve other services from the container now.
     */
    public function boot(): void
    {
        $engine = $this->app->make(AnalyticsEngine::class);

        // Register event listeners
        $dispatcher = $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $dispatcher->addListener('user.login', [$engine, 'onUserLogin']);
    }
}
```

## Step 2: Configure Provider Registration

Add your provider to `config/app.php`:

```php
<?php
return [
    'providers' => [
        // Core providers
        Sovereign\Core\Providers\RouterServiceProvider::class,
        Sovereign\Core\Providers\EventServiceProvider::class,

        // Application providers
        App\Providers\AnalyticsServiceProvider::class,

        // Hub/Spoke providers are auto-discovered
    ],
];
```

## Step 3: Enable Auto-Discovery (Alternative)

Instead of manual registration, providers in `app/Providers/` are auto-discovered:

```bash
php s-forge provider:discover
```

This scans `app/Providers/*.php`, identifies `ServiceProvider` subclasses, and updates `storage/framework/providers.php`.

## Step 4: Test Provider Loading Order

Verify that providers load in the correct order:

```bash
php s-forge provider:list
```

Expected output:
```
+--------------------------------------+-----------+----------+
| Provider                             | Tier      | Deferred |
+--------------------------------------+-----------+----------+
| Sovereign\Core\Providers\...         | Core      | No       |
| App\Providers\Analytics...           | App       | Yes      |
| ...                                  | ...       | ...      |
+--------------------------------------+-----------+----------+
```

## Step 5: Deferred Provider Behavior

When `deferred = true`, the provider is NOT loaded on every request:

```php
<?php
// Before resolving AnalyticsEngine
$loadedProviders = $container->make('config')->get('app.loaded_providers');
// AnalyticsServiceProvider is NOT in this list

// First time AnalyticsEngine is needed
$engine = $container->make(AnalyticsEngine::class);
// Now AnalyticsServiceProvider::register() is called
```

Verification:
```bash
php -r "
\$container = require 'bootstrap/container.php';
require 'bootstrap/services.php';
echo 'Provider not loaded yet' . PHP_EOL;
\$engine = \$container->make(AnalyticsEngine::class);
echo 'Provider was lazily loaded on resolution' . PHP_EOL;
"
```

## Step 6: Write a Provider Test

```php
<?php
// tests/Unit/Providers/AnalyticsServiceProviderTest.php

use Sovereign\Core\Container\Container;
use App\Providers\AnalyticsServiceProvider;

test('analytics provider registers analytics engine', function () {
    $container = new Container();
    $provider = new AnalyticsServiceProvider($container);

    $provider->register();

    expect($container->has(AnalyticsEngine::class))->toBeTrue();
});
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Service not found` in `boot()` | Another provider's service not registered yet | Move the dependency to a later provider or use lazy resolution |
| Provider not loaded | Deferred provider but service never requested | Check `$provides` array matches registered bindings |
| `Call to undefined method` in `register()` | Trying to use a service before it's registered | Move inter-service logic to `boot()` |

## Verification Checklist
- [ ] Provider extends `Sovereign\Core\Container\ServiceProvider`
- [ ] `register()` contains only binding calls
- [ ] `boot()` contains inter-service wiring
- [ ] Deferred providers declare `$provides` array
- [ ] Provider auto-discovery finds the provider without manual registration
- [ ] Provider unit test validates bindings are registered

## Next Steps
After mastering plugin registration, proceed to [Validation Framework Usage](./validation-framework-usage.md).