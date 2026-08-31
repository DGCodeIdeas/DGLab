# HUB-05.md

## Phase ID

`HUB-05`

## Tier

`Hub`

## Component Name and Description

**Event Bus** – Central publish/subscribe mechanism that enables decoupled communication between Core and Hub components. Implements PSR‑14 `EventDispatcherInterface` and supports async dispatch via queue workers.

## Context7 Research

- **PHP Best Practices**: Prefer immutable event objects, avoid side‑effects in listeners.
- **PSR‑14**: Standard event dispatcher contract.
- **Design Patterns**: Observer, Mediator, and Async Queue Adapter.
- **Performance**: Use Symfony Messenger or Laravel Queue for async handling, aiming for sub‑millisecond dispatch latency.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

final class HubEventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event)
    {
        $eventClass = $event::class;
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }
        return $event;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component HubEventDispatcher {
        +addListener(string, callable): void
        +dispatch(object): object
    }
    component Listener <<interface>>
    HubEventDispatcher --> Listener
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). Core services inject `EventDispatcherInterface` to publish domain events. Async listeners are configured in the Core queue subsystem (`CORE-12`).

## CI Verification Criteria

- Unit test coverage ≥ 95% for dispatcher logic.
- Integration test ensures listeners receive events in correct order.
- Performance: synchronous dispatch ≤ 1 ms; async queue enqueue ≤ 2 ms.
- Reliability: 99.9% event delivery under load of 10 k events/sec.

## SemVer Impact

**Minor** – Introduces a new event infrastructure that other components depend on.
