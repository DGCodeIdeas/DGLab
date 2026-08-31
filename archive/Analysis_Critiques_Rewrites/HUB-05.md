# PHASE HUB-05: RBAC & Permission Engine

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and clarifies the cache-invalidation contract against
`HUB-02`'s tag-based invalidation (see `HUB-02.md`), rather than leaving "cache clear logic"
unspecified.

## Component Name
Sovereign Guardian

## Description
Fine-grained RBAC and permission engine, built on `HUB-04`, defining what an authenticated user may
do. Supports Roles, Permissions, and dynamic Abilities/Policies based on resource ownership or
attributes.

## Build Status
🔴 **Blocked** on `HUB-04` (Identity), `CORE-19` (DBAL), `HUB-02` (Cache) — none implemented.

## Dependency Status
- **Upward:** `HUB-04`, `CORE-19`, `HUB-02`. *(Matches taxonomy — no drift.)*
- **Downward:** `ISPOKE-01` (permission-leak CI criterion depends directly on this), every
  Spoke that gates UI/actions by role.

## Architectural Design
- **Gate** — primary entry point for authorization checks.
- **PolicyRegistry** — maps resource types to `Policy` classes.
- **RoleManager** — assigns permissions to roles, roles to users.
- **PermissionLoader** — eager-loads a user's permissions at authentication time.

```php
namespace SovereignStack\Hub\Auth;

class DocumentPolicy
{
    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->author_id || $user->hasRole('admin');
    }
}
```

```php
namespace SovereignStack\Hub\Contracts;

interface GateInterface
{
    public function allows(string $ability, mixed $arguments = []): bool;
    public function define(string $ability, callable $callback): void;
    public function policy(string $class, string $policy): void;
    public function authorize(string $ability, mixed $arguments = []): void;
}
```

## Integration Strategy
- **Upward:** depends on `HUB-04` for the authenticated user context.
- **Downward:** Spoke applications use `@can('edit', $post)` (extending `CORE-12`).
- **Cache invalidation contract:** `PermissionLoader`'s cached permission set for a user is stored
  under a `HUB-02` tag `permissions:user:{id}`. Any role/permission mutation calls
  `flushTags(["permissions:user:{$id}"])` — this is the concrete mechanism behind "changing a role
  must immediately reflect," not a vague promise; it composes directly with `HUB-02`'s
  invalidation-by-version strategy.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Deny by default | Unit test: query `allows()` for an undefined ability string; assert `false`, never an exception or implicit `true`. |
| Cache invalidation on role change | Integration test: grant then revoke a role mid-test; assert `allows()` reflects the change on the very next call (verifies the `flushTags` contract above actually fires, not just that eventual consistency happens). |
| Nested-role resolution overhead | Benchmark with a role depth of 1, 3, 6; state the reference environment before citing an absolute number — do not restate "< 1ms" until measured against a real implementation (Finding 10). |

## CI Verification Criteria
- Deny-by-default test, blocking.
- Cache-invalidation-on-mutation test, blocking — this is the test that makes the tag-based contract
  with `HUB-02` real rather than aspirational.
- Nested-role depth test with measured (not asserted) timing.

## SemVer Impact
**Major.** Completes the security and authorization framework.
