# PHASE HUB-08: API Gateway Abstraction Layer

## Tier
Hub

## Component Name
Sovereign Gateway

## Description
A unified entry point for all API traffic in the Sovereign Stack. It serves as both an internal service mesh (governing Spoke-to-Hub communication) and a public-facing gateway for external consumers. It handles request routing, authentication translation, unified error responses, and protocol bridging.

## Context7 Research
- **Depends on**: `CORE-06: Router`, `HUB-04: Identity`, `HUB-07: Rate Limiter`, `CORE-04: HTTP Message`.
- **Patterns**: API Gateway, Proxy, Facade.
- **Protocol**: Primarily REST/JSON, but designed to bridge to internal RPC or Event streams.

## Architectural Design
- **GatewayController**: A specialized controller that intercepts cross-tier requests.
- **RequestTranslator**: Converts external request formats into internal service calls.
- **ServiceRegistry**: Maps "Service Names" (e.g., `identity-service`) to internal URLs or class identifiers.
- **ResponseAggregator**: Combines data from multiple Hub services into a single unified JSON response.

### Internal vs External Logic
- **Internal**: Uses secure, high-speed class resolution or internal IPC; bypasses public throttling.
- **External**: Enforces `HUB-04` (Auth) and `HUB-07` (Throttling); maps external tokens to internal user contexts.

## Interface Contracts

### GatewayInterface
```php
namespace Sovereign\Hub\Contracts;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface GatewayInterface
{
    /**
     * Dispatch a request to an internal Hub service.
     */
    public function dispatchInternal(string $service, string $action, array $params = []): mixed;

    /**
     * Proxy an external request to the appropriate Spoke or Hub handler.
     */
    public function proxy(ServerRequestInterface $request): ResponseInterface;
}
```

## Integration Strategy
- **Upward**: Built on the `CORE-06` Router and `CORE-18` Kernel.
- **Downward**: All Spoke applications communicate with the Hub via this Gateway to ensure auditability (`HUB-06`) and security.
- **Security**: The Gateway is the only component that handles SSL termination and CORS validation for the Hub tier.

## CI Verification Criteria
- **Latency**: Proxying a request through the gateway must add < 2ms of overhead.
- **Error Consistency**: An error in a background Hub service must be translated into a standardized `{"error": "...", "code": 500}` response by the Gateway.
- **Isolation**: A failure in the Gateway must not bring down the individual Hub services (circuit breaker pattern).

## SemVer Impact
**Major**. Defines the communication interface for the entire stack.
