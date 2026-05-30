# HUB-04.md

## Phase ID

`HUB-04`

## Tier

`Hub`

## Component Name and Description

**Tenant Isolation Layer** – Enforces data segregation per tenant using scoped repositories and query filters. Guarantees that all Core services operate within the tenant context.

## Context7 Research

- **PHP Best Practices**: Use middleware to inject tenant identifier, avoid hard‑coded tenant IDs.
- **PSR‑7**: Request carries tenant identifier via attributes.
- **PSR‑11**: Container provides `TenantContextInterface`.
- **PSR‑14**: Emits `TenantSwitched` events.
- **Design Patterns**: Decorator for repository scoping, Strategy for tenant resolution, and Facade for simplified API.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Tenant;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14
use Psr\Http\Message\ServerRequestInterface; // PSR‑7

interface TenantContextInterface
{
    public function getTenantId(): string;
    public function setTenantId(string $id): void;
}

final class TenantContext implements TenantContextInterface
{
    private string $tenantId = '';
    public function getTenantId(): string { return $this->tenantId; }
    public function setTenantId(string $id): void { $this->tenantId = $id; }
}

final class TenantMiddleware
{
    private TenantContextInterface $context;
    private EventDispatcherInterface $dispatcher;
    public function __construct(TenantContextInterface $context, EventDispatcherInterface $dispatcher)
    {
        $this->context = $context;
        $this->dispatcher = $dispatcher;
    }
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $tenantId = $request->getAttribute('tenant_id', 'default');
        $this->context->setTenantId($tenantId);
        $this->dispatcher->dispatch(new TenantSwitchedEvent($tenantId));
        return $next($request);
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component TenantMiddleware {
        +__invoke(ServerRequestInterface, callable): ResponseInterface
    }
    component TenantContext <<interface>>
    component EventDispatcher <<interface>>
    TenantMiddleware --> TenantContext
    TenantMiddleware --> EventDispatcher
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). The middleware is attached to the router (`CORE-03`) to run on every request, ensuring downstream services receive the correct tenant context.

## CI Verification Criteria

- Unit tests cover tenant resolution and context propagation ≥ 95%.
- Integration tests verify that repository queries are automatically scoped.
- Latency overhead ≤ 1 ms per request.
- Security: attempts to access another tenant's data must be blocked and logged.

## SemVer Impact

**Minor** – Adds tenant isolation APIs and middleware, affecting downstream services.
