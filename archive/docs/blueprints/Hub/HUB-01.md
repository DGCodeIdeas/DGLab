# PHASE HUB-01: Global Configuration & Feature Flags

## Tier
Hub (Shared Services)

## Component Name
Sovereign Hub Config & Flags

## Description
A global configuration management service that extends `CORE-10` to support multi-tenant configurations, dynamic feature flags, and remote settings. This allows Hub-level services and Spoke applications to toggle functionality in real-time without code redeployments.

## Sequencing Rationale
This is the first Hub-tier phase because all subsequent Hub services (Identity, Cache, Asset Pipeline) require a unified way to retrieve shared settings and toggle environment-specific features.

## Context7 Research
- **Depends on**: `CORE-10: Config & Env Loader`, `CORE-02: DI Container`.
- **Feature Flag Patterns**: Implements "Kill Switches," "Release Toggles," and "Percentage Rollouts."
- **Storage**: Uses `CORE-10` for static files and `CORE-19` (Database) for dynamic tenant-specific overrides.

## Architectural Design
- **HubConfigRegistry**: Extends the Core registry to merge global defaults with tenant-specific overrides.
- **FeatureFlagManager**: Evaluates toggle states based on context (User, Tenant, Environment).
- **FlagEvaluator**: Handles complex rules (e.g., "Enabled for 10% of users in region 'US'").

### Feature Flag Example
```php
namespace Sovereign\Hub\Config;

interface FeatureManagerInterface
{
    public function isEnabled(string $flag, ?Context $context = null): bool;
    public function getVariant(string $flag, ?Context $context = null): string;
}
```

## Interface Contracts

### GlobalConfigInterface
```php
namespace Sovereign\Hub\Contracts;

interface GlobalConfigInterface
{
    /**
     * Get a configuration value with tenant-aware fallback.
     */
    public function get(string $key, mixed $default = null, ?string $tenantId = null): mixed;

    /**
     * Check if a feature flag is active for the current context.
     */
    public function feature(string $flag): bool;
}
```

## Integration Strategy
- **Upward**: Consumes `CORE-10`.
- **Downward**: Injected as a singleton into all Hub and Spoke service providers.
- **Contract**: Spoke applications use the `GlobalConfigInterface` to access both static settings and dynamic feature states.

## CI Verification Criteria
- **Merge Logic**: Tenant overrides must never "leak" into the global configuration pool.
- **Performance**: Flag evaluation for a simple boolean toggle must take < 0.005ms.
- **Consistency**: A change in the database for a dynamic flag must reflect in the service within 1 second (cache invalidation check).

## SemVer Impact
**Minor**. Extends the configuration capabilities of the stack.
