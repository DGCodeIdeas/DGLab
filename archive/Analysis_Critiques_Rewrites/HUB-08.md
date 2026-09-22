# PHASE HUB-08: API Gateway Abstraction Layer

## Tier
Hub (Shared Services)

## Resolves
Ties this blueprint's "circuit breaker" claim to a concrete contract, and aligns its isolation
requirement with `BRIDGE-01`'s fail-closed policy (`BRIDGE-01.md` §5) so the two documents describe
one consistent failure-handling story instead of two separate, unlinked ones.

## Component Name
Sovereign Gateway

## Description
Unified entry point for all API traffic: internal service mesh for Spoke-to-Hub communication, and the
public-facing gateway for external consumers. Handles routing, auth translation, unified error
responses, and protocol bridging.

## Build Status
🔴 **Blocked** on `CORE-06` (Router), `HUB-04` (Identity), `HUB-07` (Rate Limiter), `CORE-04` (HTTP
Message) — none implemented. This is the component `BRIDGE-01` sits behind for all External Spoke
traffic, and the component that must implement the N+1/failover behavior described in `DEPLOY-01`.

## Dependency Status
- **Upward:** `CORE-06`, `HUB-04`, `HUB-07`, `CORE-04`. *(Matches taxonomy.)*
- **Downward:** every Spoke; `BRIDGE-01` is registered as gateway middleware, not a separate hop.

## Architectural Design
- **GatewayController** — intercepts cross-tier requests.
- **RequestTranslator** — converts external request formats into internal service calls.
- **ServiceRegistry** — maps service names to internal URLs/class identifiers, backed by `HUB-15`.
- **ResponseAggregator** — combines multiple Hub service responses into one unified JSON response.

**Internal vs. external:** internal traffic uses fast, in-process class resolution or internal IPC and
bypasses public throttling; external traffic enforces `HUB-04` auth and `HUB-07` throttling and maps
external tokens to internal user contexts.

```php
namespace SovereignStack\Hub\Contracts;

interface GatewayInterface
{
    public function dispatchInternal(string $service, string $action, array $params = []): mixed;
    public function proxy(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface;
}
```

## Failure Isolation Contract (tightened)
"A failure in the Gateway must not bring down individual Hub services" was previously stated with no
mechanism. This blueprint specifies: **per-downstream-service circuit breakers** (open after N
consecutive failures or a latency threshold within a rolling window; half-open probes on a timer), with
state kept in `HUB-02` so all Gateway instances share breaker state rather than each learning
independently. This is the same category of protection `BRIDGE-01` needs on its Internal-call leg — the
two should share an implementation, not each invent their own.

## Integration Strategy
- **Upward:** built on `CORE-06` and `CORE-18`.
- **Downward:** all Spokes communicate via this Gateway for auditability (`HUB-06`) and security.
- **Security:** sole component handling SSL termination and CORS validation for the Hub tier (see
  `HUB-27` for the header/CORS policy detail this delegates to).

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Proxy overhead | State environment before citing "< 2ms" — measure via a load-testing tool against a fixture backend once implementable (Finding 10). |
| Error-response consistency | Integration test: force a backend Hub service to throw; assert the Gateway returns the standardized `{"error": ..., "code": 500}` shape, never a raw stack trace or an inconsistent shape. |
| Circuit breaker isolation | Integration test: make one registered service fail continuously; assert the breaker opens after the configured threshold, subsequent calls fail fast without hitting the dead service, and *other* registered services remain unaffected throughout. |

## CI Verification Criteria
- Error-consistency test, blocking.
- Circuit-breaker isolation test, blocking — this is what makes "a failure in the Gateway must not
  bring down individual Hub services" a checked property instead of a design aspiration.
- Proxy overhead measured and reported with environment stated.

## SemVer Impact
**Major.** Defines the communication interface for the entire stack.
