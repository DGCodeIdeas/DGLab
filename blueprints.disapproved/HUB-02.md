# HUB-02.md

## Phase ID

`HUB-02`

## Tier

`Hub`

## Component Name and Description

**Authentication Gateway** – Centralizes authentication mechanisms (session, token, OAuth) providing a unified interface for Core services. Handles credential validation, token issuance, and integrates with external identity providers.

## Context7 Research

- **PHP Best Practices**: Leverage strong typing, avoid global state, and use dependency injection.
- **PSR‑7**: Handles incoming request objects for login flows.
- **PSR‑11**: Service container registration of `AuthGatewayInterface`.
- **PSR‑14**: Dispatches `AuthenticationSucceeded` and `AuthenticationFailed` events.
- **PSR‑15**: Middleware for protecting routes.
- **Design Patterns**: Strategy for authentication methods, Adapter for external providers, and Facade for simplified API.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14
use Psr\Http\Message\ServerRequestInterface; // PSR‑7

interface AuthGatewayInterface
{
    public function authenticate(ServerRequestInterface $request): AuthResult;
    public function logout(int $userId): void;
}

final class AuthGateway implements AuthGatewayInterface
{
    private AuthStrategyInterface $strategy;
    private EventDispatcherInterface $dispatcher;

    public function __construct(AuthStrategyInterface $strategy, EventDispatcherInterface $dispatcher)
    {
        $this->strategy = $strategy;
        $this->dispatcher = $dispatcher;
    }

    public function authenticate(ServerRequestInterface $request): AuthResult
    {
        $result = $this->strategy->authenticate($request);
        $event = $result->isSuccess()
            ? new AuthenticationSucceededEvent($result->userId())
            : new AuthenticationFailedEvent($request);
        $this->dispatcher->dispatch($event);
        return $result;
    }

    public function logout(int $userId): void
    {
        // Invalidate session / token
        $this->dispatcher->dispatch(new UserLoggedOutEvent($userId));
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component AuthGateway {
        +authenticate(ServerRequestInterface): AuthResult
        +logout(int): void
    }
    component AuthStrategy <<interface>>
    component EventDispatcher <<interface>>
    AuthGateway --> AuthStrategy
    AuthGateway --> EventDispatcher
```

## Integration Strategy

Registered via the Core DI container (`CORE-02` – Dependency Injection Container). Controllers retrieve `AuthGatewayInterface` to protect endpoints. Events flow to Core listeners (`CORE-07`). Supports plug‑in adapters for OAuth providers defined in the Core tier.

## CI Verification Criteria

- Unit test coverage ≥ 92% for `AuthGateway`.
- Integration tests ensure correct event dispatch on success/failure.
- Latency: authentication flow ≤ 5 ms for credential verification.
- Security: token generation complies with RFC 7519 and uses SHA‑256 HMAC with < 200 µs signing time.

## SemVer Impact

**Minor** – Adds a new authentication façade and related events, requiring consumer updates.
