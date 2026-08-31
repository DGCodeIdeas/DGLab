# PHASE HUB-27: Cross-Origin (CORS) & Security Header Management

## Tier
Hub (Shared Services)

## Component Name
Sovereign Sentinel (Headers)

## Description
A centralized service for managing HTTP security headers and Cross-Origin Resource Sharing (CORS) policies. It provides a flexible configuration for allowed origins, methods, and headers, ensuring the Hub and Spokes are protected against common web attacks.

## Sequencing Rationale
Must be integrated into the `HUB-08` Gateway to protect all inbound traffic.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-08: API Gateway`, `HUB-01: Global Config`.
- **Transitive Core Dependencies**: `CORE-04: HTTP Message`, `CORE-05: Middleware`.
- **Standards**: OWASP Secure Headers Project, W3C CORS Specification.

## Architectural Design
- **HeaderManager**: Injects security headers (CSP, HSTS, X-Frame-Options) into every response.
- **CorsEngine**: Evaluates preflight OPTIONS requests and injects appropriate `Access-Control-*` headers.
- **PolicyRegistry**: Stores per-tenant or per-service security policies.
- **CspGenerator**: Dynamically generates Content Security Policy hashes for inline scripts (if any).

## Interface Contracts

### SentinelInterface
```php
namespace Sovereign\Hub\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface SentinelInterface
{
    /**
     * Apply security headers and CORS policies to a response.
     */
    public function apply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}
```

## Integration Strategy
- **Upward**: Registered as a global Middleware in `HUB-08`.
- **Downward**: Automatically covers all Spoke requests that route through the Gateway.
- **Contract**: Uses `CORE-10` to define global defaults and `HUB-01` for tenant-level overrides.

## CI Verification Criteria
- **Preflight Success**: Must correctly respond to an OPTIONS request with a 204 status and valid CORS headers.
- **CSP Integrity**: Response must include a `Content-Security-Policy` header with `default-src 'self'`.
- **Strict Transport**: `Strict-Transport-Security` must be present and correctly configured (max-age, includeSubDomains).

## SemVer Impact
**Minor**. Hardens the security posture of the entire stack.
