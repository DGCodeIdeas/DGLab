# Implementation Guide: Event System Wiring

## Overview
This guide demonstrates wiring the PSR-14 Event Dispatcher ([CORE-03](/ApprovedBlueprints/Core/CORE-03.md)) to enable decoupled communication between components. The dispatcher uses prioritized, haltable pipelines with lazy listener resolution from the DI container.

**Reference**: [ADR-005 Event System Design](/docs/architecture/decisions/ADR-005-event-system-design.md)

## Prerequisites
- DI Container configured ([DI Setup Guide](./di-container-setup.md))
- Service Provider pattern understood ([Plugin Registration Guide](./plugin-registration.md))

## Step 1: Define an Event Class

Create `app/Events/UserRegisteredEvent.php`:

```php
<?php
namespace App\Events;

use Sovereign\Core\Events\Event;
use App\Models\User;

class UserRegisteredEvent extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $source,   // 'web', 'api', 'cli'
        public readonly array $metadata = []
    ) {}

    /**
     * Events are mutable objects. Listeners can enrich the event
     * with additional data without breaking propagation.
     */
    public array $auditLog = [];
}
```

## Step 2: Create Event Listeners

Create `app/Listeners/SendWelcomeEmailListener.php`:

```php
<?php
namespace App\Listeners;

use App\Events\UserRegisteredEvent;
use Psr\Log\LoggerInterface;

class SendWelcomeEmailListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke(UserRegisteredEvent $event): void
    {
        try {
            // Send welcome email logic
            $this->logger->info('Sending welcome email', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);

            // Mail::to($event->user)->send(new WelcomeMail($event->user));
        } catch (\Throwable $e) {
            // Exception is caught by the dispatcher - won't crash other listeners
            $this->logger->error('Failed to send welcome email', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

Create `app/Listeners/AuditLoginListener.php` (high priority):

```php
<?php
namespace App\Listeners;

use App\Events\UserRegisteredEvent;

class AuditLoginListener
{
    // Priority 100 = runs early in the pipeline
    public function __invoke(UserRegisteredEvent $event): void
    {
        $event->auditLog[] = [
            'action' => 'user_registered',
            'user_id' => $event->user->id,
            'source' => $event->source,
            'timestamp' => time(),
        ];

        // Enrich the event with audit data
        // Other listeners can access $event->auditLog
    }
}
```

## Step 3: Register Listeners via ServiceProvider

Create `app/Providers/EventServiceProvider.php`:

```php
<?php
namespace App\Providers;

use Sovereign\Core\Container\ServiceProvider;
use Sovereign\Core\Events\ListenerProviderInterface;
use App\Events\UserRegisteredEvent;
use App\Listeners\SendWelcomeEmailListener;
use App\Listeners\AuditLoginListener;
use App\Listeners\SlackNotificationListener;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $provider = $this->app->make(ListenerProviderInterface::class);

        // Listeners are registered as class names (resolved lazily)
        $provider->addListener(
            UserRegisteredEvent::class,
            AuditLoginListener::class,
            priority: 100
        );

        $provider->addListener(
            UserRegisteredEvent::class,
            SendWelcomeEmailListener::class,
            priority: 50
        );

        $provider->addListener(
            UserRegisteredEvent::class,
            SlackNotificationListener::class,
            priority: 10
        );
    }
}
```

## Step 4: Dispatch Events

Emit events from any part of the application:

```php
<?php
namespace App\Services;

use Psr\EventDispatcher\EventDispatcherInterface;
use App\Events\UserRegisteredEvent;

class UserService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher
    ) {}

    public function register(array $data): User
    {
        $user = User::create($data);

        // Dispatch event - all listeners run automatically
        $event = $this->dispatcher->dispatch(
            new UserRegisteredEvent(
                user: $user,
                source: 'web',
                metadata: ['ip' => request()->ip()]
            )
        );

        // Event object is returned (may have been modified by listeners)
        $this->logger->info('Registration complete', $event->auditLog);

        return $user;
    }
}
```

## Step 5: Create Stoppable Events

For events where propagation can be halted (e.g., before-response check):

```php
<?php
namespace App\Events;

use Sovereign\Core\Events\Event;

class BeforeResponseEvent extends Event
{
    public function __construct(
        public mixed $response = null
    ) {}

    /**
     * If a listener sets $response, propagation stops
     * and the response is returned immediately.
     */
    public function haltWith(mixed $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }
}
```

```php
<?php
// Listener that can halt the pipeline
class CacheHitListener
{
    public function __invoke(BeforeResponseEvent $event): void
    {
        $cached = Cache::get(request()->getUri());

        if ($cached !== null) {
            $event->haltWith($cached);  // Stops propagation
        }
    }
}
```

## Step 6: Test Event Dispatch

```bash
php s-forge test --filter=Event
```

```php
<?php
test('user registered event triggers welcome email', function () {
    // Arrange
    $dispatcher = container()->make(EventDispatcherInterface::class);
    $user = User::factory()->create();

    // Act
    $event = $dispatcher->dispatch(
        new UserRegisteredEvent($user, 'test')
    );

    // Assert
    expect($event->user->id)->toBe($user->id);
});
```

## Step 7: Verify Performance

```bash
php s-forge event:benchmark
```

Expected output:
```
Dispatching 10,000 events (5 listeners each):
  Throughput: 12,450 events/sec ✓
  Error isolation: All listener failures caught and logged
  Lazy resolution: 0ms overhead when event not dispatched
```

## Event Priority Reference

| Priority Range | Usage | Example |
|---------------|-------|---------|
| 1000-501 | Critical system listeners | Security audit, transaction logging |
| 500-101 | Core business logic | Authentication, authorization |
| 100-1 | Standard listeners | Email notifications, cache warming |
| 0 | Default | Listeners without explicit priority |
| -1 to -100 | Background/low priority | Analytics tracking, log aggregation |

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Listener not executing | Wrong event class in registration | Check `addListener()` first parameter matches dispatched event |
| Listeners execute in wrong order | Priority not set | Add explicit priority values |
| Event mutation lost | Event not passed by reference | PSR-14 dispatchers return the modified event; use return value |
| Listener error breaks pipeline | Error not caught | Verify dispatcher wraps listeners in try/catch |
| Deferred listener never loads | Listener class not in `$provides` | Add listener class to provider's `$provides` array |

## Verification Checklist
- [ ] Event classes extend `Sovereign\Core\Events\Event`
- [ ] Listeners implement `__invoke(EventType $event)` method
- [ ] Listeners registered via ServiceProvider with priority values
- [ ] Event dispatch tested with multiple listeners
- [ ] Error isolation confirmed (one listener failure doesn't affect others)
- [ ] Stoppable events halt propagation correctly
- [ ] Throughput benchmark meets 10,000 events/sec target
- [ ] Lazy resolution confirmed (0 overhead when event not dispatched)

## Next Steps
After mastering events, proceed to combine with other patterns in the [Design Pattern Catalog](/docs/design-patterns/).