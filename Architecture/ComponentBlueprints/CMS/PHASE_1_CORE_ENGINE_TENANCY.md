# Phase 1: Core Engine & Multi-Tenancy

## Goals
- Establish the `TenancyService` to manage multiple "sites" or "tenants".
- Implement a robust data isolation strategy using a shared database with dedicated `tenants` and `tenant_data` tables.
- Define the foundational models for content management.

## Tenancy Architecture
The system uses a **Single DB / Separate Data Tables** isolation strategy:
- **`tenants` table**: Stores metadata for each site (ID, Domain, Settings, Status).
- **`tenant_data` table**: Acts as a polymorphic container for site-specific configurations and global settings that aren't strictly content-related.
- **`ContentEntry` Isolation**: Every content record is strictly bound to a `tenant_id`.

### Database Schema (Conceptual)
```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY,
    identifier VARCHAR(255) UNIQUE, -- e.g. 'site_a'
    domain VARCHAR(255),
    config JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE tenant_data (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT REFERENCES tenants(id),
    key VARCHAR(255),
    value TEXT,
    scope VARCHAR(50), -- e.g. 'system', 'plugin_x'
    UNIQUE(tenant_id, key)
);
```

## Core Service: TenancyService
Responsibilities:
- **Identification**: Detecting the current tenant based on request host, header, or API token.
- **Context Injection**: Providing the `Tenant` object to other services globally.
- **Isolation Enforcement**: Automatically appending `WHERE tenant_id = ?` to all CMS-related queries.

## Foundational Content Models
1. **`ContentType`**: Defines the "Structure" (e.g., 'Article', 'Product', 'Video').
   - Contains: Name, Slug, Description, Schema Version.
2. **`ContentEntry`**: The actual "Data" record.
   - Contains: ID, tenant_id, content_type_id, status (linked to Phase 3), current_version_id.

## Security Considerations
- Ensure that `tenant_id` is never mass-assignable.
- Cross-tenant data leakage prevention via global query scopes in the `QueryBuilder`.
