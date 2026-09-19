# Implementation Guide: DI Container Setup

## Overview
This guide walks through setting up the Dependency Injection Container for the Sovereign Stack. The DI Container ([CORE-02](/docs/blueprints/Core/CORE-02.md)) is the first object instantiated in the Kernel and serves as the wiring hub for all subsequent components.

**Reference**: [ADR-001 DI Container Design](/docs/architecture/decisions/ADR-001-di-container-design.md)

## Prerequisites
- PHP 8.3+ installed
- Composer dependencies loaded
- Basic understanding of PSR-11

## Step 1: Install the Container Package

```bash
composer require sovereign/core-container
```

Verify installation:
```bash
php -r "echo interface_exists('Psr\\Container\\ContainerInterface') ? 'PSR-11 OK' : 'Missing';"
```

## Step 2: Create the Container Instance

Create `bootstrap/container.php`:

```php
<?php
// bootstrap/container.php

use Sovereign\Core\Container\Container;

$container = new Container();

// Bind the container to itself
$container->bind(ContainerInterface::class, $container);

return $container;
```

Verification:
```bash
php -r "
\$container = require 'bootstrap/container.php';
echo \$container instanceof Psr\\Container\\ContainerInterface ? 'Container ready' : 'Error';
"
```

## Step 3: Register Core Services

Add service bindings to `bootstrap/services.php`:

```php
<?php
// bootstrap/services.php

use Sovereign\Core\Config\ConfigRepository;
use Sovereign\Core\Logging\Logger;

/** @var \Sovereign\Core\Container\Container $container */

// Simple binding (new instance each time)
$container->bind(Logger::class);

// Singleton binding (same instance every time)
$container->bind(ConfigRepository::class, null, true);

// Concrete binding (custom implementation)
$container->bind(
    \Psr\Log\LoggerInterface::class,
    \Sovereign\Core\Logging\Logger::class,
    true
);
```

## Step 4: Test Auto-Wiring

Create a test service `app/Services/GreetingService.php`:

```php
<?php
namespace App\Services;

use Psr\Log\LoggerInterface;

class GreetingService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function greet(string $name): string
    {
        $this->logger->info("Greeting user: {$name}");
        return "Hello, {$name}!";
    }
}
```

Test from CLI:

```php
<?php
// test_greeting.php
$container = require 'bootstrap/container.php';
require 'bootstrap/services.php';

$greeter = $container->make(\App\Services\GreetingService::class);
echo $greeter->greet('World'); // Outputs: Hello, World!
```

```bash
php test_greeting.php
```

## Step 5: Generate Production Compiled Map

```bash
php s-forge container:cache
```

This generates `storage/framework/container/compiled.php`. The container will automatically use the compiled map when the file exists.

## Step 6: Verify Performance

```bash
php s-forge container:benchmark
```

Expected output:
```
Resolution time (3-level tree): 0.32ms ✓
Memory footprint: 0.7MB ✓
PSR-11 compliance: PASS ✓
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `ContainerException: Class not found` | Missing `use` import or composer autoload | Run `composer dump-autoload` |
| `Cannot resolve parameter \$config` | Scalar parameter without binding | Add explicit `bind()` for the parameter value |
| `Circular reference detected` | Service A depends on B which depends on A | Use setter injection or event-based initialization |

## Verification Checklist
- [ ] Container implements `Psr\Container\ContainerInterface`
- [ ] Reflection auto-wiring resolves 3-level deep dependency trees
- [ ] Compiled map reduces resolution time below 0.5ms
- [ ] Singleton bindings return the same instance
- [ ] Custom bindings override auto-wired resolutions

## Next Steps
After completing DI Container setup, proceed to [Plugin Registration](./plugin-registration.md) to wire up your Service Providers.