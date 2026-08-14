# PHASE HUB-05: RBAC & Permission Engine

## Tier
Hub

## Component Name
Sovereign Guardian

## Description
A fine-grained Role-Based Access Control (RBAC) and Permission Engine. It builds on `HUB-04` (Identity) to define what an authenticated user is allowed to do. It supports Roles, Permissions, and dynamic "Abilities" (Policies) based on resource ownership or attributes.

## Context7 Research
- **Depends on**: `HUB-04: Identity`, `CORE-19: DBAL`, `HUB-02: Cache`.
- **Patterns**: Access Control List (ACL), Policy-Based Access Control (PBAC).
- **Optimization**: Heavily utilizes `HUB-02` to cache user permissions and prevent repetitive DB queries.

## Architectural Design
- **Gate**: The primary entry point for authorization checks.
- **PolicyRegistry**: Maps resource types (e.g., `Document`) to specific `Policy` classes.
- **RoleManager**: Handles assignments of permissions to roles and roles to users.
- **PermissionLoader**: Eager-loads all relevant permissions for a user upon authentication.

### Authorization Example
```php
namespace Sovereign\Hub\Auth;

class DocumentPolicy
{
    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->author_id || $user->hasRole('admin');
    }
}
```

## Interface Contracts

### GateInterface
```php
namespace Sovereign\Hub\Contracts;

interface GateInterface
{
    /**
     * Check if the user has a specific permission or ability.
     */
    public function allows(string $ability, mixed $arguments = []): bool;

    /**
     * Define a simple closure-based ability.
     */
    public function define(string $ability, callable $callback): void;

    /**
     * Register a resource policy class.
     */
    public function policy(string $class, string $policy): void;

    /**
     * Authorize an action or throw an AccessDeniedException.
     */
    public function authorize(string $ability, mixed $arguments = []): void;
}
```

## Integration Strategy
- **Upward**: Depends on `HUB-04` for the authenticated user context.
- **Downward**: Spoke applications use the `@can('edit', $post)` SuperPHP directive (extending CORE-12) which calls this engine.
- **Database**: Stores roles and permissions in a set of Hub-managed tables (`roles`, `permissions`, `role_user`, etc.).

## CI Verification Criteria
- **Deny by Default**: Any check for an undefined permission must return `false`.
- **Cache Invalidation**: Changing a user's role in the DB must immediately reflect in the `allows()` check (cache clear logic).
- **Depth Test**: Must handle nested roles (Role A inherits from Role B) with < 1ms overhead.

## SemVer Impact
**Major**. Completes the security and authorization framework.
