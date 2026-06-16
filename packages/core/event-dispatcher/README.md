# CORE-03: Event Dispatcher

A production-ready PSR-14 Event Dispatcher for the Sovereign Stack. Provides decoupled communication between framework components using prioritized, haltable listener pipelines with lazy resolution from the DI container.

## Features

- **PSR-14 Compliant**: Implements `Psr\EventDispatcher\EventDispatcherInterface` and `Psr\EventDispatcher\ListenerProviderInterface`
- **Prioritized Listeners**: Integer priority system (higher = runs first) with built-in ranges: CRITICAL (1000-501), NORMAL (500-0), BACKGROUND (-1 to -1000)
- **Stoppable Events**: Listeners can halt propagation via `StoppableEventInterface`
- **Lazy Resolution**: Listeners registered as class strings are resolved from the DI container only when their event fires
- **Error Isolation**: A failing listener never crashes the dispatcher — exceptions are caught and logged, the pipeline continues
- **Event Stamping**: Immutable audit trail via `stamp()` method on base Event class
- **High Throughput**: Designed for 10,000+ events/sec with minimal overhead

## Reference

Blueprint: [CORE-03](../../docs/blueprints/Core/CORE-03.md)  
ADR: [ADR-005 Event System Design](../../docs/architecture/decisions/ADR-005-event-system-design.md)

## Installation

```bash
composer require sovereignstack/event-dispatcher
```

## Quick Start

```php
use SovereignStack\Events\EventDispatcher;
use SovereignStack\Events\ListenerProvider;

// Create provider and register listeners
$provider = new ListenerProvider();
$provider->addListener(UserRegisteredEvent::class, SendWelcomeEmailListener::class, priority: 100);
$provider->addListener(UserRegisteredEvent::class, AuditLoginListener::class, priority: 50);

// Create dispatcher
$dispatcher = new EventDispatcher($provider);

// Dispatch
$event = $dispatcher->dispatch(new UserRegisteredEvent($user));
```

## License

MIT
