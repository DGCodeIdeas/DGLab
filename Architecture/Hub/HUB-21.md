# PHASE HUB-21: Multi-tenancy Coordination Layer

## Tier
Hub (Shared Services)

## Component Name
Sovereign Nexus (Tenancy)

## Description
The coordination layer for multi-tenant applications. It manages tenant resolution (via domain, header, or user), database connection switching, and scope isolation for shared Hub services. It ensures that data from Tenant A never leaks into Tenant B.

## Sequencing Rationale
Relies on `HUB-01` (Config) and `HUB-04` (Identity). It must be in place before building any tenant-aware Spoke applications.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-01: Global Config`, `HUB-04: Identity`, `HUB-08: API Gateway`.
- **Transitive Core Dependencies**: `CORE-19: DBAL`, `CORE-10: Config`, `CORE-02: DI Container`.
- **Patterns**: Database-per-tenant, Schema-per-tenant, or Column-based isolation. Implements Column-based as default with support for DB-per-tenant.

## Architectural Design
- **TenantResolver**: Identifies the current tenant context from the Request.
- **TenantScope**: A global state object that holds the current tenant's ID and configuration.
- **ConnectionSwitcher**: Automatically points `CORE-19` to the tenant's specific database if configured.
- **StorageIsolation**: Ensures `HUB-11` file paths are prefixed with the Tenant ID.

## Interface Contracts

### TenancyInterface
```php
namespace Sovereign\Hub\Contracts;

interface TenancyInterface
{
    /**
     * Get the current active tenant.
     */
    public function current(): ?Tenant;

    /**
     * Execute a callback within the context of a specific tenant.
     */
    public function runAs(string $tenantId, callable $callback): mixed;
}
```

## Integration Strategy
- **Upward**: Registered as a Middleware (`CORE-05`) in the `HUB-08` Gateway.
- **Downward**: Spoke applications inject `TenancyInterface` to filter queries and scoped storage.
- **Contract**: Global models must implement a `BelongsToTenant` trait that automatically applies scoping.

## CI Verification Criteria
- **Leak Prevention**: A query for "Users" while Tenant A is active must return 0 results if all users belong to Tenant B.
- **Resolution Speed**: Tenant resolution from a host header must take < 0.1ms.
- **Isolation**: Tenant-specific cache keys (`HUB-02`) must be prefixed with the Tenant ID.

## SemVer Impact
**Major**. Transforms the stack into a multi-tenant platform.
