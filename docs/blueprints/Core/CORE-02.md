# PHASE CORE-02: Dependency Injection Container

## Tier
Core

## Component Name
Reactive DI Container

## Description
A PSR-11 compliant dependency injection container that supports recursive reflection-based auto-wiring and PHP 8.3 Attributes for explicit service configuration. Designed for high performance with a sub-1ms resolution time for pre-compiled service maps.

## Context7 Research
- **PSR Compliance**: PSR-11 (ContainerInterface).
- **PHP 8.3 Features**: Uses `Constructor Promotion` and `Attributes` for injection metadata.
- **Research Reference**: `/php-fig/container` documentation.

## Architectural Design
- **Container**: Implements `Psr\Container\ContainerInterface`.
- **DefinitionResolver**: Uses `ReflectionClass` to determine dependencies.
- **CompilerPass**: (Optional) Analyzes the graph to generate an optimized, flat PHP array for production environments.

### Interfaces:
```php
interface ContainerInterface extends \Psr\Container\ContainerInterface {
    public function bind(string $id, mixed $concrete = null, bool $singleton = false): void;
    public function make(string $id, array $parameters = []): mixed;
}
```

## Integration Strategy
The Container is the first object instantiated in the `Kernel`. All subsequent Core components (Router, Event Dispatcher) are resolved through this container.

## CI Verification Criteria
- **Resolution Speed**: < 0.5ms for a 3-level deep dependency tree.
- **Memory Footprint**: < 1MB for container overhead.
- **Compliance**: Must pass the standard PSR-11 compatibility test suite.

## SemVer Impact
**Major**. Changes the fundamental way objects are instantiated across the entire stack.