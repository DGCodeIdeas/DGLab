# HUB-03.md

## Phase ID

`HUB-03`

## Tier

`Hub`

## Component Name and Description

**Authorization Engine** – Provides fine‑grained permission checks based on RBAC and ABAC models. Exposes a policy evaluation API used by Core services to enforce access control.

## Context7 Research

- **PHP Best Practices**: Use immutable policy objects, type‑safe enums, and avoid magic strings.
- **PSR‑11**: Service container registration of `AuthorizationEngineInterface`.
- **PSR‑14**: Dispatches `PermissionChecked` events for audit trails.
- **Design Patterns**: Strategy for policy evaluation, Composite for permission hierarchies, and Decorator for caching results.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Authz;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14

interface AuthorizationEngineInterface
{
    public function isAllowed(int $userId, string $resource, string $action): bool;
}

final class AuthorizationEngine implements AuthorizationEngineInterface
{
    private PolicyProviderInterface $provider;
    private PolicyEvaluatorInterface $evaluator;
    private EventDispatcherInterface $dispatcher;
    private CacheInterface $cache; // PSR‑16

    public function __construct(
        PolicyProviderInterface $provider,
        PolicyEvaluatorInterface $evaluator,
        EventDispatcherInterface $dispatcher,
        CacheInterface $cache
    ) {
        $this->provider = $provider;
        $this->evaluator = $evaluator;
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
    }

    public function isAllowed(int $userId, string $resource, string $action): bool
    {
        $key = "authz:$userId:$resource:$action";
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            $this->dispatcher->dispatch(new PermissionCheckedEvent($userId, $resource, $action, $cached, true));
            return $cached;
        }
        $policy = $this->provider->getPolicyForUser($userId);
        $allowed = $this->evaluator->evaluate($policy, $resource, $action);
        $this->cache->set($key, $allowed, 300);
        $this->dispatcher->dispatch(new PermissionCheckedEvent($userId, $resource, $action, $allowed, false));
        return $allowed;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component AuthorizationEngine {
        +isAllowed(int, string, string): bool
    }
    component PolicyProvider <<interface>>
    component PolicyEvaluator <<interface>>
    component Cache <<interface>>
    component EventDispatcher <<interface>>
    AuthorizationEngine --> PolicyProvider
    AuthorizationEngine --> PolicyEvaluator
    AuthorizationEngine --> Cache
    AuthorizationEngine --> EventDispatcher
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Controllers and services retrieve `AuthorizationEngineInterface` to guard actions. Permission check events are consumed by Core logging listeners (`CORE-07`).

## CI Verification Criteria

- Unit test coverage ≥ 92% for `AuthorizationEngine`.
- Integration tests verify cache hit/miss behavior.
- Latency: permission check ≤ 2 ms on cache hit, ≤ 5 ms on miss.
- Security: policies are immutable and validated against a JSON schema.

## SemVer Impact

**Minor** – Introduces a new public service and events, requiring dependent code to adopt the new authorization checks.
