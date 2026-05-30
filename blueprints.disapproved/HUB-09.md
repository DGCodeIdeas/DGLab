# HUB-09.md

## Phase ID

`HUB-09`

## Tier

`Hub`

## Component Name and Description

**Notification Service** – Publishes real‑time and email notifications to users based on domain events. Supports multiple channels (WebSocket, SMTP) and respects user preferences.

## Context7 Research

- **PHP Best Practices**: Use immutable event payloads, avoid blocking I/O in request cycle.
- **PSR‑14**: Listens to domain events such as `UserCreatedEvent`.
- **PSR‑11**: Service container registration of `NotificationServiceInterface`.
- **Design Patterns**: Observer for event subscription, Strategy for channel selection, Builder for message composition.
- **Performance**: Queue‑based dispatch to keep request latency < 5 ms.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Notification;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Container\ContainerInterface;

interface NotificationServiceInterface
{
    public function send(string $userId, string $type, array $payload): void;
}

final class NotificationService implements NotificationServiceInterface
{
    private ContainerInterface $channelContainer;
    private EventDispatcherInterface $dispatcher;

    public function __construct(ContainerInterface $channelContainer, EventDispatcherInterface $dispatcher)
    {
        $this->channelContainer = $channelContainer;
        $this->dispatcher = $dispatcher;
    }

    public function send(string $userId, string $type, array $payload): void
    {
        $channel = $this->channelContainer->get($type); // resolves strategy
        $channel->dispatch($userId, $payload);
        $this->dispatcher->dispatch(new NotificationSentEvent($userId, $type, $payload));
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component NotificationService {
        +send(string, string, array): void
    }
    component ChannelFactory <<interface>>
    component NotificationChannel <<interface>>
    NotificationService --> ChannelFactory
    NotificationService --> NotificationChannel
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Event listeners defined in `CORE-07` forward domain events to this service. Channels (e.g., `EmailChannel`, `WebSocketChannel`) are themselves services resolved via the container.

## CI Verification Criteria

- Unit test coverage ≥ 94% for service and channel adapters.
- Integration test verifies that a `UserCreatedEvent` results in an email notification.
- Latency: queuing a notification ≤ 5 ms.
- Reliability: 99.9% delivery success under load of 5 k notifications/sec.

## SemVer Impact

**Minor** – Adds a new notification infrastructure and related events, requiring consumers to configure channels.
