# CORE-02: Dependency Injection Container

A production-ready PSR-11 compliant Dependency Injection Container for the Sovereign Stack. Provides autowiring, compiler passes for container modification during compilation, and circular dependency detection.

## Features

- **PSR-11 Compliant**: Implements `Psr\Container\ContainerInterface` with `has()` and `get()`
- **Autowiring**: Automatic constructor dependency resolution using reflection
- **Compiler Passes**: Pre-compilation hooks for modifying service definitions
- **Circular Dependency Detection**: Prevents infinite recursion during resolution
- **Primitive Binding**: Supports binding scalar values and non-class parameters
- **Interface-to-Implementation Mapping**: Configure interfaces to their concrete implementations

## Reference

Blueprint: [CORE-02](../../docs/blueprints/Core/CORE-02.md)

## Installation

```bash
composer require sovereign-stack/core-container
```

## Quick Start

```php
use SovereignStack\Core\Container\Container;

$container = new Container();

// Bind an interface to an implementation
$container->bind(LoggerInterface::class, FileLogger::class);

// Bind a primitive value
$container->bind('config.debug', true);

// Resolve with autowiring
$service = $container->get(MyService::class);
```

## License

MIT