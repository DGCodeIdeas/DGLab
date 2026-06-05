# Phase 2: Tenancy & Core Foundation

## Goals
Implement the physical and logical isolation strategy for multi-tenant data management. This phase establishes the "Hub" (the Studio Home) and the base "Spoke" (the Content Registry).

## 2.1 Multi-Tenant Data Architecture (BACKEND COMPLETED)
- **`tenants` table**: ID, identifier (e.g., 'primary_site'), domain, status.
- **`tenant_data` table**: Polymorphic container for site-specific configurations and global settings.
- **`TenancyService`**: Singleton responsible for detecting current tenant context from request headers (`X-Tenant-ID`) or hostnames.
- **Physical Isolation**: All database queries are automatically scoped to a `tenant_id` via the `QueryBuilder`.

## 2.2 Core Service: TenancyService (BACKEND COMPLETED)
Responsibilities:
- **Identification**: Detecting the current tenant context.
- **Context Injection**: Providing a globally accessible `Tenant` object.
- **Isolation Enforcement**: Automatically appending `WHERE tenant_id = ?` to queries.

## 2.3 The Content Registry (CORE INTEGRATION)
- **`ContentType` Registry**: Tracks structural metadata for content models (e.g., 'Article', 'Product').
- **`ContentEntry` Foundation**: The base record for every item in the system.
- **`Hybrid Storage Strategy`**:
    - **EAV (Entity-Attribute-Value)**: For maximum compatibility across SQL databases, granular field tracking, and easy search.
    - **JSONB Meta**: For overflow, non-structural metadata, and rapid JSON serialization.

## 2.4 User Interface: The "Studio Home" (PENDING)
- **SuperPHP "Hub" Dashboard**: A centralized "Single Pane of Glass" showing:
    - **Global Activity**: A timeline of recent content edits and server alerts fed by `EventDispatcher`.
    - **Tenant Switcher**: A SuperPHP reactive dropdown for admins managing multiple sites.
    - **App Launcher**: Quick-access icons for Architect, Content, Pulse, Media, and Search.
- **Aesthetic**: "Command Center" feel with real-time status indicators (glowing green/red) for each tenant.

## 2.5 Security & Integrity (CORE INTEGRATION)
- **Non-Mass Assignable IDs**: Ensure `tenant_id` is never mass-assignable to prevent cross-tenant data leakage.
- **TenantMemberMiddleware**: Middleware to ensure authenticated users have access to the current tenant context.
- **Recursive Audit Guards**: `Connection` class protects against infinite loops during database query auditing.
