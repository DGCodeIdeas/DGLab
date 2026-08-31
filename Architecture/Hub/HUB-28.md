# PHASE HUB-28: Hub API Versioning Strategy

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Versioner

## Description
Formal versioning strategy/implementation for the Hub API: URL-based, header-based, and Accept-header
schemes, routing requests to the correct service version.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway) and `HUB-15` (Health Check) — neither implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-15`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-06`, `CORE-18`.

## Architectural Design
- **VersionResolver** — determines requested API version from the incoming request.
- **RouteVersioner** — decorates `CORE-06` for versioned route groups (`/v1/`, `/v2/`).
- **DeprecationManager** — injects `Deprecation`/`Link` headers for sunsetting versions.
- **CompatibilityShim** — maps old-version requests to new logic with transformation.

```php
namespace SovereignStack\Hub\Contracts;

interface VersioningInterface
{
    public function defaultVersion(): string;
    public function deprecate(string $version, \DateTimeInterface $sunsetDate): void;
}
```

## Integration Strategy
- **Upward:** integrated into the `CORE-06` routing pipeline used by `HUB-08`.
- **Downward:** Spoke applications define versioned controllers/routes.
- **Contract:** unversioned requests default to the latest stable version unless configured otherwise.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Routing precision | Integration test: request `/v1/identity`, assert it never reaches a `/v2/` controller, and vice versa — including a deliberate near-miss case (e.g., a `/v1/identity-extra` path) to catch prefix-matching bugs. |
| Accept-header resolution | Integration test with `Accept: application/vnd.sovereign.v1+json`; assert correct version resolution, and a separate test for a malformed/unknown version string asserting a clean 4xx rather than a crash. |
| Deprecation warning | Integration test: mark a version deprecated with a sunset date; assert the `Warning`/`Deprecation` header appears on every response for that version, including error responses. |

## CI Verification Criteria
- Routing-precision test with a near-miss case, blocking.
- Accept-header resolution test including the malformed-input case, blocking.
- Deprecation-header-on-every-response test (including error paths), blocking.

## SemVer Impact
**Minor.** Provides long-term stability and evolution paths for the API.
