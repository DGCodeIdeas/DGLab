# HUB-18.md

## Phase ID

`HUB-18`

## Tier

`Hub`

## Component Name and Description

**Service Mesh Integration Layer** – Introduces a lightweight service‑mesh abstraction for intra‑service communication, handling request tracing, retries, and circuit‑breaking. Provides a PSR‑15 middleware that wraps outbound HTTP client calls with mesh policies.

## Context7 Research

- **PHP Best Practices**: Keep mesh logic out of business code, use composition over inheritance, and prefer immutable policy objects.
- **PSR‑7**: Outbound requests are represented as `ServerRequestInterface` objects.
- **PSR‑11**: Service container registers `MeshPolicyProviderInterface` and `MeshHttpClientInterface`.
- **PSR‑15**: Middleware applies mesh policies to incoming requests.
- **Design Patterns**: Proxy for HTTP client, Strategy for retry/circuit‑breaker policies, Decorator for tracing headers.
- **Performance**: Middleware overhead < 3 ms per request; retry latency bounded by exponential back‑off (max 200 ms total).

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Mesh;

use Psr\Http\Message\ServerRequestInterface; // PSR‑7
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientInterface; // PSR‑18 HTTP client
use Psr\Http\Server\MiddlewareInterface; // PSR‑15
use Psr\Container\ContainerInterface; // PSR‑11

interface MeshPolicyProviderInterface
{
    public function getRetryPolicy(string $service): RetryPolicy;
    public function getCircuitBreaker(string $service): CircuitBreaker;
    public function getTracingPolicy(string $service): TracingPolicy;
}

final class MeshHttpClient implements ClientInterface
{
    private ClientInterface $inner;
    private MeshPolicyProviderInterface $policies;

    public function __construct(ClientInterface $inner, MeshPolicyProviderInterface $policies)
    {
        $this->inner = $inner;
        $this->policies = $policies;
    }

    public function sendRequest(ServerRequestInterface $request): ResponseInterface
    {
        $service = $request->getUri()->getHost();
        $retry = $this->policies->getRetryPolicy($service);
        $circuit = $this->policies->getCircuitBreaker($service);
        $tracing = $this->policies->getTracingPolicy($service);

        // Apply tracing headers
        $request = $tracing->injectHeaders($request);

        // Circuit‑breaker check
        if (!$circuit->allow()) {
            throw new \RuntimeException('Circuit open for service ' . $service);
        }

        // Retry loop (simplified)
        $attempt = 0;
        do {
            try {
                $response = $this->inner->sendRequest($request);
                $circuit->recordSuccess();
                return $response;
            } catch (\Throwable $e) {
                $circuit->recordFailure();
                $attempt++;
                if ($attempt > $retry->maxAttempts()) {
                    throw $e;
                }
                usleep($retry->backoffDelay($attempt) * 1000);
            }
        } while (true);
    }
}

final class MeshMiddleware implements MiddlewareInterface
{
    private MeshHttpClient $meshClient;
    public function __construct(MeshHttpClient $meshClient) { $this->meshClient = $meshClient; }
    public function process(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        // Example: forward request to internal service via mesh client
        $response = $this->meshClient->sendRequest($request);
        return $response;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component MeshHttpClient {
        +sendRequest(ServerRequestInterface): ResponseInterface
    }
    component MeshPolicyProvider <<interface>>
    component MeshMiddleware {
        +process(ServerRequestInterface, callable): ResponseInterface
    }
    MeshHttpClient --> MeshPolicyProvider
    MeshMiddleware --> MeshHttpClient
```

## Integration Strategy

The mesh layer is registered in the Core DI container (`CORE-02`). `MeshMiddleware` is added to the API gateway stack (`HUB-12`) to handle outbound calls from Core services. Policy definitions are stored in the configuration service (`HUB-16`). Existing Core HTTP clients are replaced with `MeshHttpClient` via a PSR‑11 factory.

## CI Verification Criteria

- Unit test coverage ≥ 93% for retry, circuit‑breaker, and tracing logic.
- Integration tests against a mock HTTP server verify that retries occur up to the configured limit and that circuit‑breaker opens after consecutive failures.
- Latency overhead ≤ 3 ms for successful calls; total retry latency capped at 200 ms.
- Reliability: under a simulated failure scenario, the circuit‑breaker prevents more than 5 % of traffic from reaching the failing service.

## SemVer Impact

**Minor** – Adds a mesh abstraction that changes how services perform outbound HTTP calls, requiring updates to client injection points.
