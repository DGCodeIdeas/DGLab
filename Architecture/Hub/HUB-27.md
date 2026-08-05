# PHASE HUB-27: Cross-Origin (CORS) & Security Header Management

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and clarifies precedence against `HUB-01`'s tenant
config overrides, which this blueprint already assumed but didn't formally define the precedence
order for.

## Component Name
Sovereign Sentinel (Headers)

## Description
Centralized HTTP security-header and CORS-policy management: allowed origins/methods/headers, guarding
the Hub and Spokes against common web attacks.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway) and `HUB-01` (Config) — neither implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-01`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-04`, `CORE-05`.

## Architectural Design
- **HeaderManager** — injects security headers (CSP, HSTS, X-Frame-Options) on every response.
- **CorsEngine** — evaluates preflight `OPTIONS`, injects `Access-Control-*` headers.
- **PolicyRegistry** — per-tenant or per-service security policies.
- **CspGenerator** — dynamic CSP hashes for inline scripts, if any exist.

```php
namespace SovereignStack\Hub\Contracts;

interface SentinelInterface
{
    public function apply(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface;
}
```

## Config Precedence (clarified)
Global defaults come from `CORE-10`; tenant-level overrides come from `HUB-01`. Precedence: a
tenant-specific policy in `PolicyRegistry` **may only narrow** the global default (e.g., a stricter CSP
`default-src`), never broaden it (e.g., a tenant cannot add `unsafe-inline` if the global policy
forbids it). This mirrors `HUB-01`'s own "tenant overrides may only add/replace known keys" rule and
prevents a misconfigured tenant policy from weakening the platform's baseline security posture.

## Integration Strategy
- **Upward:** registered as global middleware in `HUB-08`.
- **Downward:** automatically covers all Spoke requests routed through the Gateway.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Preflight correctness | Integration test: send an `OPTIONS` preflight for an allowed origin/method combination; assert `204` with correct `Access-Control-*` headers, and a separate test for a disallowed origin asserting rejection. |
| CSP presence | Integration test asserting `Content-Security-Policy` header with `default-src 'self'` (or the configured baseline) on every response type (HTML, JSON API, error pages) — not just the happy-path route. |
| HSTS correctness | Integration test asserting `Strict-Transport-Security` includes `max-age` and `includeSubDomains` with the configured values, not just presence of the header. |
| Tenant-narrowing-only enforcement | Integration test: configure a tenant policy attempting to *broaden* the global CSP; assert `PolicyRegistry` rejects it at write time, per the Config Precedence rule above. |

## CI Verification Criteria
- Preflight allow/deny test pair, blocking.
- CSP-on-every-response-type test, blocking.
- Tenant-narrowing-only enforcement test, blocking — new, makes the precedence rule a checked
  property.

## SemVer Impact
**Minor.** Hardens the security posture of the entire stack.
