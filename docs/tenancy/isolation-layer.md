# Tenancy Isolation Layer Architecture

> **Navigation:** [Tenancy Home](index.md) | [Audit Logging](tenant-audit-logging.md) | [Isolation Test Suite](isolation-test-suite.md) | [Team Training](team-training.md)
>
> **Related:** [Tenancy Service Specification](../../architecture/origin/TENANCY_SERVICE.md) | [`TenancyService`](../../Legacy.old/app/Services/Tenancy/TenancyService.php) | [Tenant Migrations](../../Legacy.old/database/migrations/2026_03_14_000001_create_tenants_tables.php)

---

## Overview

The **Tenancy Isolation Layer** provides framework-enforced boundaries that guarantee multi-tenant data isolation at the data access layer. Rather than relying on developer discipline to include `WHERE tenant_id = ?` clauses, this layer **automatically intercepts every query** and injects the current tenant context.

This addresses **Weakness 5: Tenancy Isolation Relies on Developer Discipline** by replacing manual responsibility with framework enforcement.

### Design Goals

| Goal | Mechanism | Verification |
|------|-----------|-------------|
| **100% query filtering** | Global scopes + query macros | Audit log of un-scoped queries |
| **Zero developer overhead** | Automatic tenant context injection | New models inherit scoping by default |
| **Explicit bypass only** | `withoutTenantScope()` must be intentional | Code review checklist item |
| **Audit trail** | All isolation violations logged | Tenant audit dashboard |

---

## Architecture

```mermaid
graph TD
    subgraph Request Flow
        R[HTTP Request] --> TM[TenantContextMiddleware]
        TM --> TV[TenantValidator]
        TV --> CT{Has Tenant?}
        CT -->|Yes| CP[Context Propagated]
        CT -->|No| E1[Throw TenantContextRequired]
    end

    subgraph Application Layer
        CP --> CO[Controller / Handler]
        CO --> SR[Service Layer]
        SR --> QF[Query Filter Layer]
    end

    subgraph Query Filter Layer
        QF --> GS[Global Scope<br/>WHERE tenant_id = ?]
        QF --> QM[Query Macros<br/>whereTenant(), withoutTenant()]
        QF --> QI[Relation Scoping<br/>BelongsTo tenant]
    end

    subgraph Enforcement
        GS --> DB[(Database)]
        QM --> DB
        QI --> DB
        DB -.-> AU[Audit Logger]
        AU -.-> AL[(Audit Logs)]
    end

    subgraph Bypass Methods
        BA[Admin Override] --> BO[bool $bypassIsolation = true]
        BA --> BM[withoutTenantScope() macro]
    end

    classDef middleware fill:#e1f5fe,stroke:#0288d1
    classDef layer fill:#f3e5f5,stroke:#7b1fa2
    classDef query fill:#e8f5e9,stroke:#2e7d32
    classDef audit fill:#fff3e0,stroke:#f57c00
    classDef bypass fill:#ffebee,stroke:#c62828

    class TM,TV,CT middleware
    class CO,SR,QF layer
    class GS,QM,QI query
    class AU audit
    class BA,BO,BM bypass
```

---

## Layer Components

### 1. Tenant Context Middleware

The [`TenantContextMiddleware`](../../Legacy.old/app/Services/Tenancy/TenancyService.php) runs early in the request lifecycle and is responsible for:

1. **Resolving** the current tenant via header (`X-Tenant-Id`), domain (`HTTP_HOST`), or session
2. **Validating** that a resolved tenant exists for tenant-scoped routes
3. **Propagating** the tenant context into the DI container for downstream injection

```php
<?php

namespace DGLab\Core\Middleware;

use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Tenancy\TenancyService;

/**
 * Middleware that resolves, validates, and propagates tenant context.
 *
 * Position: Before routing (after auth middleware if applicable)
 */
class TenantContextMiddleware
{
    private TenancyService $tenancy;

    public function __construct(TenancyService $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    public function handle(Request $request, callable $next): Response
    {
        $tenant = $this->tenancy->getCurrentTenant();

        if ($request->isTenantScoped() && $tenant === null) {
            throw new TenantContextRequiredException(
                'This endpoint requires an active tenant context.'
            );
        }

        // Set tenant ID on the request for downstream use
        if ($tenant !== null) {
            $request->setAttribute('tenant_id', (int)$tenant->id);
        }

        return $next($request);
    }
}
```

#### Exception Classes

```php
<?php

namespace DGLab\Core\Middleware;

class TenantContextRequiredException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 403)
    {
        parent::__construct($message ?: 'Tenant context is required for this operation.', $code);
    }
}

class TenantMismatchException extends \RuntimeException
{
    public function __construct(
        int $expectedTenantId,
        int $actualTenantId,
        string $resource = '',
        int $code = 403
    ) {
        $message = sprintf(
            'Tenant mismatch: expected %d, got %d%s',
            $expectedTenantId,
            $actualTenantId,
            $resource ? " for resource {$resource}" : ''
        );
        parent::__construct($message, $code);
    }
}
```

### 2. Tenant Scope Middleware

This middleware applies database-level scoping after the tenant context is resolved:

```php
<?php

namespace DGLab\Core\Middleware;

use DGLab\Database\Model;
use DGLab\Services\Tenancy\TenancyService;

/**
 * Middleware that applies automatic tenant scoping to all database queries.
 *
 * Position: After TenantContextMiddleware
 */
class TenantScopeMiddleware
{
    private TenancyService $tenancy;

    public function __construct(TenancyService $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    public function handle(Request $request, callable $next): Response
    {
        $tenantId = $this->tenancy->tenantId();

        if ($tenantId !== null) {
            // Enable global tenant scoping on all models
            Model::enableTenantScope($tenantId);
        }

        $response = $next($request);

        // Clean up after response is sent
        Model::disableTenantScope();

        return $response;
    }
}
```

### 3. Query Filter Interceptors

#### 3.1 Global Scope on Base Model

The global scope pattern automatically adds `WHERE tenant_id = ?` to **every query** on tenant-scoped models:

```php
<?php

namespace DGLab\Database;

/**
 * Global scope that automatically filters all queries by the current tenant.
 *
 * This is applied to all models that use the TenantScoped trait or extend
 * TenantScopedModel.
 */
class TenantGlobalScope implements ScopeInterface
{
    private static ?int $currentTenantId = null;
    private static bool $enabled = true;

    public static function setCurrentTenantId(?int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    public static function getCurrentTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (!self::$enabled || self::$currentTenantId === null) {
            return;
        }

        if (!$model->isTenantScoped()) {
            return;
        }

        $builder->where($model->getTenantColumn(), '=', self::$currentTenantId);
    }
}
```

#### 3.2 Base Tenant-Scoped Model

```php
<?php

namespace DGLab\Database;

/**
 * Base model for all tenant-scoped entities.
 *
 * Usage:
 *   class Document extends TenantScopedModel
 *   {
 *       protected string $tenantColumn = 'tenant_id';
 *   }
 *
 * All queries against this model will automatically include:
 *   WHERE tenant_id = <current_tenant_id>
 */
abstract class TenantScopedModel extends Model
{
    protected string $tenantColumn = 'tenant_id';

    /**
     * Boot the tenant scoping for this model.
     */
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantGlobalScope());
    }

    /**
     * Return the column name that holds the tenant identifier.
     */
    public function getTenantColumn(): string
    {
        return $this->tenantColumn;
    }

    /**
     * Determine if this model is tenant-scoped.
     */
    public function isTenantScoped(): bool
    {
        return true;
    }

    /**
     * Create a new record belonging to the current tenant.
     */
    public static function createForTenant(array $attributes = []): static
    {
        $tenantId = TenantGlobalScope::getCurrentTenantId();
        if ($tenantId !== null) {
            $attributes[static::instance()->getTenantColumn()] = $tenantId;
        }

        return static::create($attributes);
    }

    /**
     * Temporarily bypass the tenant scope for this query chain.
     *
     * Usage: Document::withoutTenantScope()->where('status', 'active')->get();
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantGlobalScope::class);
    }
}
```

#### 3.3 Query Builder Macros

```php
<?php

namespace DGLab\Database;

/**
 * Query builder macros for tenant-specific operations.
 *
 * Register these in a service provider's boot() method:
 *   Builder::macro('whereTenant', [TenantMacros::class, 'whereTenant']);
 *   Builder::macro('allTenants', [TenantMacros::class, 'allTenants']);
 */
class TenantMacros
{
    /**
     * Scope the query to the current tenant.
     *
     * Usage: DB::table('documents')->whereTenant()->get();
     */
    public function whereTenant(Builder $builder): Builder
    {
        $tenantId = TenantGlobalScope::getCurrentTenantId();

        if ($tenantId === null) {
            throw new TenantContextRequiredException(
                'Cannot apply tenant scope without an active tenant context.'
            );
        }

        return $builder->where('tenant_id', $tenantId);
    }

    /**
     * Explicitly query across all tenants (requires bypass permission).
     *
     * Usage: DB::table('documents')->allTenants()->get();
     * This logs an audit event for the cross-tenant access.
     */
    public function allTenants(Builder $builder): Builder
    {
        if (!$this->hasBypassPermission()) {
            throw new \RuntimeException(
                'Cross-tenant query requires the bypass_tenant_isolation permission.'
            );
        }

        // Log the cross-tenant access attempt
        app(TenantAuditLogger::class)->logCrossTenantAccess(
            query: $builder->toSql(),
            reason: 'Admin override with bypass permission'
        );

        return $builder;
    }

    private function hasBypassPermission(): bool
    {
        // Check if current user has the bypass_tenant_isolation permission
        // Implemented via the existing RBAC system
        return false; // Placeholder - integrate with auth system
    }
}
```

### 4. Context Validators

#### 4.1 Request Validation

```php
<?php

namespace DGLab\Core\Validation;

use DGLab\Services\Tenancy\TenancyService;

/**
 * Validates that tenant context is consistent across request boundaries.
 */
class TenantContextValidator
{
    private TenancyService $tenancy;

    public function __construct(TenancyService $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Validate that a resource belongs to the current tenant.
     *
     * @throws TenantMismatchException
     */
    public function validateResourceOwnership(TenantScopedModel $resource): void
    {
        $tenantId = $this->tenancy->tenantId();

        if ($tenantId === null) {
            return; // No tenant context - skip validation (handled by middleware)
        }

        $resourceTenantId = (int)$resource->{$resource->getTenantColumn()};

        if ($resourceTenantId !== $tenantId) {
            throw new TenantMismatchException(
                $tenantId,
                $resourceTenantId,
                get_class($resource) . ':' . $resource->getKey()
            );
        }
    }
}
```

#### 4.2 Service Layer Integration

```php
<?php

namespace DGLab\Services\Tenancy;

/**
 * Trait for service classes that need tenant context validation.
 *
 * Usage:
 *   class DocumentService
 *   {
 *       use ValidatesTenantContext;
 *
 *       public function update(int $id, array $data): Document
 *       {
 *           $document = Document::findOrFail($id);
 *           $this->validateTenantAccess($document); // Throws if cross-tenant
 *           $document->update($data);
 *           return $document;
 *       }
 *   }
 */
trait ValidatesTenantContext
{
    protected function validateTenantAccess(TenantScopedModel $resource): void
    {
        $validator = app(TenantContextValidator::class);
        $validator->validateResourceOwnership($resource);
    }

    protected function requireTenant(): int
    {
        return app(TenancyService::class)->requireTenant()->id;
    }
}
```

---

## Middleware Chain Order

The middleware stack must be ordered to ensure tenant context exists before scoping is applied:

```php
<?php

namespace DGLab\Core;

/**
 * Middleware chain order for tenancy isolation.
 *
 * Correct order:
 *   1. RateLimitMiddleware (no tenant needed)
 *   2. AuthMiddleware (identifies user, may require tenant)
 *   3. TenantContextMiddleware (resolves current tenant)
 *   4. TenantScopeMiddleware (applies DB scoping)
 *   5. ValidationMiddleware (validates request)
 *   6. Controller / Route Handler
 *   7. ResponseMiddleware (cleanup)
 */
class Kernel
{
    protected array $middleware = [
        \DGLab\Core\Middleware\RateLimitMiddleware::class,
        \DGLab\Core\Middleware\AuthMiddleware::class,
        \DGLab\Core\Middleware\TenantContextMiddleware::class,  // New
        \DGLab\Core\Middleware\TenantScopeMiddleware::class,    // New
        \DGLab\Core\Middleware\ValidationMiddleware::class,
    ];
}
```

---

## Database Schema Requirements

Every tenant-scoped table must include a `tenant_id` column with a foreign key constraint:

```php
<?php

// Migration template for tenant-scoped tables
class CreateDocumentsTable
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamps();

            // Index for tenant-scoped queries
            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }
}
```

### Indexing Strategy

| Query Pattern | Index | Rationale |
|--------------|-------|-----------|
| `WHERE tenant_id = ?` | `(tenant_id)` | Primary tenant filter |
| `WHERE tenant_id = ? AND created_at BETWEEN ?` | `(tenant_id, created_at)` | Tenant + range queries |
| `WHERE tenant_id = ? AND status = ?` | `(tenant_id, status)` | Tenant + status filter |
| `WHERE tenant_id = ? ORDER BY id DESC LIMIT 20` | `(tenant_id, id)` | Pagination within tenant |

---

## Cache Key Isolation

Tenant context must also extend to cache keys to prevent cross-tenant cache leaks:

```php
<?php

namespace DGLab\Cache;

trait TenantAwareCacheKeys
{
    /**
     * Generate a tenant-aware cache key.
     *
     * This ensures cache keys are prefixed with the current tenant ID,
     * preventing one tenant from reading another tenant's cached data.
     *
     * Usage:
     *   $this->tenantKey('user_profile', $userId);
     *   // Result: "tenant_7_user_profile_42"
     */
    protected function tenantKey(string $key, mixed ...$params): string
    {
        $tenantId = app(\DGLab\Services\Tenancy\TenancyService::class)->tenantId();

        if ($tenantId === null) {
            return $this->buildKey($key, ...$params);
        }

        $prefix = "tenant_{$tenantId}";
        return $this->buildKey($prefix, $key, ...$params);
    }

    private function buildKey(string ...$parts): string
    {
        return implode('_', array_filter($parts));
    }
}
```

---

## Bypass Mechanism

Admin operations that legitimately cross tenant boundaries must use an explicit bypass:

```php
<?php

namespace DGLab\Services\Tenancy;

/**
 * Context for admin operations that require bypassing tenant isolation.
 *
 * Usage:
 *   $result = TenantIsolationBypass::run(function () use ($adminService) {
 *       return $adminService->generateGlobalReport();
 *   }, reason: 'Monthly global analytics report', adminId: $userId);
 */
class TenantIsolationBypass
{
    private static int $bypassDepth = 0;

    /**
     * Execute a callable with tenant isolation bypassed.
     *
     * All queries executed within the callable will NOT have tenant scoping.
     * This is logged as an audit event.
     */
    public static function run(callable $fn, string $reason, int $adminId): mixed
    {
        $tenantId = TenantGlobalScope::getCurrentTenantId();

        TenantGlobalScope::disable();
        self::$bypassDepth++;

        try {
            // Log the bypass
            app(TenantAuditLogger::class)->logAdminOverride(
                adminId: $adminId,
                reason: $reason,
                originalTenantId: $tenantId
            );

            return $fn();
        } finally {
            self::$bypassDepth--;
            if (self::$bypassDepth === 0) {
                TenantGlobalScope::enable();
                if ($tenantId !== null) {
                    TenantGlobalScope::setCurrentTenantId($tenantId);
                }
            }
        }
    }

    public static function isBypassed(): bool
    {
        return self::$bypassDepth > 0;
    }
}
```

---

## Relationship Scoping

Eloquent-style relationships must also respect tenant boundaries:

```php
<?php

namespace DGLab\Database\Relations;

/**
 * Tenant-scoped relationship trait.
 *
 * Automatically scopes related models to the same tenant as the parent.
 */
trait TenantScopedRelations
{
    /**
     * Define a tenant-scoped HasMany relationship.
     *
     * The related model is automatically filtered to the parent's tenant.
     */
    public function tenantHasMany(string $related, string $foreignKey = null, string $localKey = null): HasMany
    {
        $relation = $this->hasMany($related, $foreignKey, $localKey);

        // Ensure the related model's tenant scope matches the parent
        $parentTenantId = $this->getAttribute($this->getTenantColumn());
        if ($parentTenantId !== null) {
            $relation->where($relation->getRelated()->getTenantColumn(), $parentTenantId);
        }

        return $relation;
    }
}
```

---

## Success Metrics Verification

| Metric | Target | Verification Method |
|--------|--------|-------------------|
| Query filtering rate | 100% | All queries audited via TenantGlobalScope |
| Isolation violations | 0 | Audit log analysis across 1000+ requests |
| Bypass operations | Logged | Admin override audit entries |
| Developer overhead | Zero | New models inherit scoping automatically |

---

## References

- [`TenancyService`](../../Legacy.old/app/Services/Tenancy/TenancyService.php) — Existing tenant identification logic
- [Tenancy Service Specification](../../architecture/origin/TENANCY_SERVICE.md) — Original spec
- [Tenant Migrations](../../Legacy.old/database/migrations/2026_03_14_000001_create_tenants_tables.php)
- [Tenant Audit Logging](tenant-audit-logging.md) — Violation tracking
- [Isolation Test Suite](isolation-test-suite.md) — Verification patterns
- [Team Training](team-training.md) — Developer education