# HUB-13.md

## Phase ID

`HUB-13`

## Tier

`Hub`

## Component Name and Description

**Feature Flag Service** – Manages runtime feature toggles per tenant, supporting gradual roll‑outs and A/B testing. Provides a PSR‑11 registered `FeatureFlagInterface` and a middleware to evaluate flags on each request.

## Context7 Research

- **PHP Best Practices**: Immutable flag definitions, cache flag state, avoid hard‑coded checks.
- **PSR‑11**: Service container registration of `FeatureFlagInterface`.
- **PSR‑14**: Emits `FeatureFlagChangedEvent` for observability.
- **Design Patterns**: Strategy for flag storage (Redis, DB), Proxy for lazy evaluation, Observer for change notifications.
- **Performance**: Flag lookup < 1 ms using cached values.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\FeatureFlag;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14

interface FeatureFlagInterface
{
    public function isEnabled(string $flag, ?string $tenantId = null): bool;
    public function set(string $flag, bool $enabled, ?string $tenantId = null): void;
}

final class RedisFeatureFlag implements FeatureFlagInterface
{
    private \Redis $client;
    private EventDispatcherInterface $dispatcher;
    public function __construct(\Redis $client, EventDispatcherInterface $dispatcher)
    {
        $this->client = $client;
        $this->dispatcher = $dispatcher;
    }
    public function isEnabled(string $flag, ?string $tenantId = null): bool
    {
        $key = $tenantId ? "ff:$tenantId:$flag" : "ff:global:$flag";
        $value = $this->client->get($key);
        return $value === '1';
    }
    public function set(string $flag, bool $enabled, ?string $tenantId = null): void
    {
        $key = $tenantId ? "ff:$tenantId:$flag" : "ff:global:$flag";
        $this->client->set($key, $enabled ? '1' : '0');
        $this->dispatcher->dispatch(new FeatureFlagChangedEvent($flag, $enabled, $tenantId));
    }
}

final class FeatureFlagMiddleware
{
    private FeatureFlagInterface $flags;
    public function __construct(FeatureFlagInterface $flags) { $this->flags = $flags; }
    public function __invoke($request, $handler)
    {
        // Example: expose flags to request attributes
        $request = $request->withAttribute('features', $this->flags);
        return $handler($request);
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component FeatureFlagMiddleware {
        +__invoke(ServerRequestInterface, callable): ResponseInterface
    }
    component FeatureFlag <<interface>>
    component RedisFeatureFlag <<interface>>
    FeatureFlagMiddleware --> FeatureFlag
    FeatureFlag --> RedisFeatureFlag
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Middleware is added to the API gateway stack (`HUB-12`). Controllers can query flags via `$container->get(FeatureFlagInterface::class)`.

## CI Verification Criteria

- Unit test coverage ≥ 94% for flag lookup and mutation.
- Integration test ensures middleware injects flag service into request attributes.
- Latency overhead ≤ 1 ms per request.
- Reliability: flag changes propagate within 200 ms across distributed nodes.

## SemVer Impact

**Minor** – Introduces a new runtime configuration mechanism affecting request handling.
