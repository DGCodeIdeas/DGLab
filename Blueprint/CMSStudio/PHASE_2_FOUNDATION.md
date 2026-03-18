# Phase 2: Tenancy & Core Foundation

## Goals
Implement the physical and logical isolation strategy for multi-tenant data management. This phase establishes the "Hub" (the Studio Home) and the base "Spoke" (the Content Registry).

## 2.1 Multi-Tenant Data Architecture
- **`tenants` table**:
    - ID, identifier (e.g., 'primary_site'), domain, status.
    - `config` JSON for site-specific settings (e.g., logo, contact info).
- **`tenant_data` table**:
    - Polymorphic container for site-specific configurations and global settings that aren't strictly content-related.
    - Scope-based (`system`, `plugin_x`, etc.).
- **Physical Isolation**: All content and monitoring data are strictly bound to a `tenant_id`.

## 2.2 Core Service: TenancyService
Responsibilities:
- **Identification**: Detecting the current tenant context from request headers (`X-Tenant-ID`), hostnames, or API tokens.
- **Context Injection**: Providing a globally accessible `Tenant` object to all other services.
- **Isolation Enforcement**: Automatically appending `WHERE tenant_id = ?` to all database queries via global scopes in the `QueryBuilder`.

## 2.3 The Content Registry
- **`ContentType` Registry**: Tracks structural metadata for different content models (e.g., 'Article', 'Product').
    - Name, Slug, Description, Schema Version.
- **`ContentEntry` Foundation**: The base record for every item in the system.
    - ID, tenant_id, content_type_id, status (Draft/Published), current_version_id.

## 2.4 User Interface: The "Studio Home"
- **"Hub" Dashboard**: A centralized "Single Pane of Glass" showing:
    - **Global Activity**: A timeline of recent content edits and server alerts.
    - **Tenant Switcher**: A quick-switch dropdown for admins managing multiple sites.
    - **App Launcher**: Quick-access icons for Architect, Content, Pulse, Media, and Search.
- **Aesthetic**: "Command Center" feel with real-time status indicators (glowing green/red) for each tenant.

## 2.5 Security & Integrity
- **Non-Mass Assignable IDs**: Ensure `tenant_id` is never mass-assignable to prevent cross-tenant data leakage.
- **Data Integrity**: Foreign key constraints and transaction-based saves for all multi-table operations.
