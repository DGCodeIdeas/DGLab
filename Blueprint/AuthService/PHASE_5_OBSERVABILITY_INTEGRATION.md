# Phase 5: Global Integration & Audit

## Goals
- Finalize the Audit Logging system.
- Integrate `AuthService` events with the `EventDispatcher`.
- Refactor legacy code and provide developer documentation.

## Audit Logging System
We implement a hybrid approach: high-performance dedicated logs for security, and generic events for observability.

### 1. Dedicated Security Logs
```sql
CREATE TABLE auth_audit_logs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NULL,
    event_type VARCHAR(50), -- 'login.success', 'login.failed', 'mfa.triggered', 'password.reset'
    identifier VARCHAR(255), -- The email/username used
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON, -- Detailed context (e.g., failure reason, tenant_id)
    created_at TIMESTAMP
);
```

### 2. EventDispatcher Integration
The `AuthService` will emit the following core events:
- `Auth.Login.Success`
- `Auth.Login.Failed`
- `Auth.Logout`
- `Auth.Password.Changed`
- `Auth.Mfa.Enabled`
- `Auth.Tenant.AccessDenied`

## Legacy Refactoring Plan

### 1. Base Controller Migration
Refactor `app/Core/Controller.php` to delegate all auth methods to the `AuthService`.
```php
// Old
protected function isAuthenticated(): bool {
    return isset($_SESSION['user']);
}

// New
protected function isAuthenticated(): bool {
    return Auth::check();
}
```

### 2. Middleware Cleanup
Replace custom session checks in existing middlewares (e.g., for the Admin Panel) with the unified `AuthMiddleware` and `PermissionMiddleware`.

## Developer Documentation
- **Auth Facade**: Usage of `Auth::user()`, `Auth::check()`, `Auth::login()`.
- **Authorization**: How to define Policies and use `@can` directives in views.
- **Extending**: How to add new OAuth providers or custom Auth Guards.

## Deliverables
1. `auth_audit_logs` migration and `AuditService` integration.
2. Full event suite registration in `EventDispatcher`.
3. Refactored `BaseController` and existing middlewares.
4. Comprehensive developer guide (Markdown).
