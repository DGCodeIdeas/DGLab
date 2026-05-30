# Phase 3: Tenant-Aware Authorization

## Goals
- Implement a Multi-Tenant RBAC (Role-Based Access Control) system.
- Allow users to have different roles and permissions across different tenants.
- Provide a fluent API for authorization checks (`can`, `hasRole`).

## Authorization Architecture
Identity is global, but **Permissions** are scoped. A user's ability to perform an action is determined by their role within the context of the **current tenant**.

### Database Schema

#### 1. Roles & Permissions
Global definitions of what roles and permissions exist in the system.
```sql
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100) UNIQUE, -- 'cms.content.create', 'admin.users.view'
    description TEXT
);

CREATE TABLE roles (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100) UNIQUE, -- 'admin', 'editor', 'viewer'
    description TEXT
);

CREATE TABLE role_permissions (
    role_id BIGINT REFERENCES roles(id),
    permission_id BIGINT REFERENCES permissions(id),
    PRIMARY KEY(role_id, permission_id)
);
```

#### 2. Tenant Role Mapping
The link between a global User, a Role, and a specific Tenant.
```sql
CREATE TABLE tenant_user_roles (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT REFERENCES tenants(id),
    user_id BIGINT REFERENCES users(id),
    role_id BIGINT REFERENCES roles(id),
    created_at TIMESTAMP,
    UNIQUE(tenant_id, user_id, role_id)
);
```

## Authorization Service
The `AuthorizationService` works in tandem with the `TenancyService`.

### Contextual Resolution
When `Auth::can('edit-post')` is called:
1. Retrieve the `current_tenant_id` from `TenancyService`.
2. Retrieve the `authenticated_user_id`.
3. Query `tenant_user_roles` to find all roles for that user in that tenant.
4. Expand roles into a flat list of permissions via `role_permissions`.
5. Check if the required permission exists in the list.

### Policy-Based Access Control (ACL)
For complex logic that depends on the resource (e.g., "A user can only edit their own posts"), we implement **Policies**.
```php
class PostPolicy {
    public function update(User $user, Post $post): bool {
        return $user->id === $post->author_id || $user->can('content.admin');
    }
}
```

## Integration with TenancyService
The `AuthService` will provide a middleware that ensures the user has access to the tenant they are trying to access.

```php
// Route protected by Tenant Authorization
Route::get('/cms/posts')->middleware('auth', 'tenant.member');
```

## Deliverables
1. Migrations for Roles, Permissions, and Tenant Role mappings.
2. `AuthorizationService` with `hasRole()` and `can()` methods.
3. `Gate` and `Policy` infrastructure.
4. `TenantMemberMiddleware` for access isolation.
