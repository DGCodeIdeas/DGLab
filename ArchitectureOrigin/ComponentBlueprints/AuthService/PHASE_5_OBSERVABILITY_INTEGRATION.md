# Phase 5: Global Integration & Unified Audit

## Goals
- Finalize the Unified Audit Logging system in the Core.
- Standardize `AuthService` events using dot-notation.
- Refactor legacy code and provide developer documentation.
- Integrate with the SuperPHP Debug Overlay for auth state inspection.

## Unified Audit System
Instead of service-specific audit logs, we implement a core `AuditService` that provides a unified interface for all security and performance tracking.

### 1. Core Audit Table
The `audit_logs` table serves as the central repository for all system activities.
```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NULL,
    user_id BIGINT NULL,
    category VARCHAR(50), -- 'auth', 'download', 'mangascript', 'system'
    event_type VARCHAR(50), -- 'login.success', 'file.downloaded', etc.
    identifier VARCHAR(255), -- Context-specific ID (email, file path, job ID)
    status_code INTEGER NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON, -- Flexible context payload
    latency_ms INTEGER NULL,
    created_at TIMESTAMP
);
```

### 2. EventDispatcher Standardization
All AuthService events are refactored to use lowercase dot-notation for consistency with the framework's pattern matching.
- `auth.login.success`
- `auth.login.failed`
- `auth.logout`
- `auth.password.reset.requested`
- `auth.password.changed`
- `auth.mfa.verified`
- `auth.tenant.access_denied`

## Refactoring Plan

### 1. Core AuditService Implementation
Develop `DGLab\Core\AuditService` to replace specialized auditors in Auth and Download services. It will support:
- `log(string $category, string $event, array $data)`
- Automatic capture of IP, User Agent, and Tenant Context.

### 2. AuthManager Update
Update `AuthManager` to dispatch the new dot-notation events and utilize the unified `AuditService`.

### 3. Base Controller & Middleware
- Delegate auth checks to the `Auth` facade.
- Ensure `TenantMemberMiddleware` properly logs access violations to the unified audit log.

## Developer Documentation
- **Unified Auditing**: How to use `Audit::log()` for custom service events.
- **Auth Events**: List of standard events for hook integration.
- **Debug Overlay**: Using the SuperPHP overlay to view real-time auth and audit telemetry.

## Deliverables
1. `audit_logs` migration and `Core\AuditService` implementation.
2. Refactored `AuthManager` and `AuthAuditService` (deprecated).
3. Standardization of all Auth events in `config/events.php`.
4. Comprehensive developer guide (Markdown).
