# PHASE HUB-28: Hub API Versioning Strategy

## Tier
Hub (Shared Services)

## Component Name
Sovereign Versioner

## Description
A formal strategy and implementation for versioning the Hub API. It supports multiple versioning schemes (URL-based, Header-based, Accept-header) and manages the routing of requests to the appropriate service version.

## Sequencing Rationale
Essential before the Hub is considered stable for third-party or Spoke consumption.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-08: API Gateway`, `HUB-15: Health Check`.
- **Transitive Core Dependencies**: `CORE-06: Router`, `CORE-18: Kernel`.
- **Patterns**: Versioning through routing, Semantic Versioning for APIs.

## Architectural Design
- **VersionResolver**: Determines the requested API version from the incoming request.
- **RouteVersioner**: Decorates the `CORE-06` Router to handle versioned route groups (e.g., `/v1/`, `/v2/`).
- **DeprecationManager**: Injects `Deprecation` and `Link` headers for sunsetting versions.
- **CompatibilityShim**: Allows mapping old version requests to new service logic with transformation.

## Interface Contracts

### VersioningInterface
```php
namespace Sovereign\Hub\Contracts;

interface VersioningInterface
{
    /**
     * Get the current default API version.
     */
    public function defaultVersion(): string;

    /**
     * Register a deprecated version and its sunset date.
     */
    public function deprecate(string $version, \DateTimeInterface $sunsetDate): void;
}
```

## Integration Strategy
- **Upward**: Integrated into the `CORE-06` routing pipeline used by `HUB-08`.
- **Downward**: Spoke applications define versioned controllers and routes.
- **Contract**: Requests without a version default to the latest stable version unless configured otherwise.

## CI Verification Criteria
- **Routing Precision**: A request to `/v1/identity` must never hit a `/v2/` controller.
- **Header Parsing**: Must correctly resolve version from `Accept: application/vnd.sovereign.v1+json`.
- **Deprecation Warning**: Deprecated versions must return a `Warning` header in the response.

## SemVer Impact
**Minor**. Provides long-term stability and evolution paths for the API.
