# HUB-12.md

## Phase ID

`HUB-12`

## Tier

`Hub`

## Component Name and Description

**API Gateway** – Exposes a unified HTTP entry point for external clients, handling request routing, authentication, rate limiting, and response transformation. Implements PSR‑7 request/response handling and forwards to Core services via internal dispatch.

## Context7 Research

- **PHP Best Practices**: Keep gateway stateless, use middleware pipeline, validate inputs early.
- **PSR‑7**: All inbound/outbound traffic represented as request/response objects.
- **PSR‑11**: Service container resolves route handlers and middleware.
- **PSR‑15**: Middleware stack for authentication, rate limiting, logging, etc.
- **Design Patterns**: Front Controller, Adapter (to Core services), Strategy (routing algorithms).
- **Performance**: Target < 5 ms routing overhead per request.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Gateway;

use Psr\Http\Message\ServerRequestInterface; // PSR‑7
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface; // PSR‑15
use Psr\Container\ContainerInterface; // PSR‑11

final class ApiGateway
{
    /** @var MiddlewareInterface[] */
    private array $middlewareStack;
    private ContainerInterface $container;

    public function __construct(array $middlewareStack, ContainerInterface $container)
    {
        $this->middlewareStack = $middlewareStack;
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = function (ServerRequestInterface $req) {
            // Resolve route handler from container based on request path
            $route = $req->getUri()->getPath();
            $handlerClass = $this->container->get('router')->match($route);
            return $handlerClass->handle($req);
        };
        // Apply middleware stack (simplified)
        foreach (array_reverse($this->middlewareStack) as $middleware) {
            $next = $handler;
            $handler = fn(ServerRequestInterface $req) => $middleware->process($req, $next);
        }
        return $handler($request);
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component ApiGateway {
        +handle(ServerRequestInterface): ResponseInterface
    }
    component Middleware <<interface>>
    component Router <<interface>>
    ApiGateway --> Middleware
    ApiGateway --> Router
```

## Integration Strategy

Registered as the entry point in the web server configuration. Middleware stack includes `AuthGateway` (`HUB-02`), `RateLimitMiddleware` (`HUB-08`), `LoggingMiddleware` (`HUB-07`), and `MetricsMiddleware` (`HUB-11`). Routes are defined in Core (`CORE-03`).

## CI Verification Criteria

- End‑to‑end tests verify that a request passes through all middleware and reaches the correct Core controller.
- Performance benchmark: total request processing ≤ 15 ms for a simple GET.
- Security: unauthenticated requests are rejected with 401.

## SemVer Impact

**Minor** – Introduces a new public entry point affecting external integration contracts.
