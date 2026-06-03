# Isolation Test Suite

> **Navigation:** [Tenancy Home](index.md) | [Isolation Layer](isolation-layer.md) | [Audit Logging](tenant-audit-logging.md) | [Team Training](team-training.md)
>
> **Related:** [Testing Recipes](../testing/recipes.md) | [Isolation Test](../../Legacy.old/tests/Unit/Core/IsolationTest.php)

---

## Overview

The **Isolation Test Suite** verifies that tenancy boundaries are never violated, even under concurrent operations, error conditions, and edge cases. These tests provide automated verification for **Weakness 5: Tenancy Isolation Relies on Developer Discipline**.

### Testing Principles

1. **Every isolation boundary has a test** — If it can be bypassed, there's a test for it
2. **Tests simulate real attacks** — ID enumeration, concurrent access, direct SQL
3. **Skipped tests are violations** — Isolation tests must never be skipped
4. **Audit trail is verified** — Tests assert audit entries are created for violations

---

## Test Patterns

### Pattern 1: Cross-Tenant Data Isolation

Verifies that Tenant A cannot read, create, update, or delete Tenant B's data.

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\Tenant;
use DGLab\Models\Document;

/**
 * @group tenancy-isolation
 * @group tenancy
 */
class CrossTenantDataIsolationTest extends IntegrationTestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;
    private Document $tenantADocument;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['identifier' => 'tenant-a']);
        $this->tenantB = Tenant::factory()->create(['identifier' => 'tenant-b']);

        // Create a document belonging to Tenant A
        $this->actingAsTenant($this->tenantA);
        $this->tenantADocument = Document::factory()->create([
            'title' => 'Tenant A Secret Document',
        ]);
    }

    /** @test */
    public function tenant_b_cannot_read_tenant_a_document(): void
    {
        $this->actingAsTenant($this->tenantB);

        $response = $this->get("/api/documents/{$this->tenantADocument->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function tenant_b_cannot_update_tenant_a_document(): void
    {
        $this->actingAsTenant($this->tenantB);

        $response = $this->put("/api/documents/{$this->tenantADocument->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function tenant_b_cannot_delete_tenant_a_document(): void
    {
        $this->actingAsTenant($this->tenantB);

        $response = $this->delete("/api/documents/{$this->tenantADocument->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function tenant_b_cannot_list_tenant_a_documents(): void
    {
        $this->actingAsTenant($this->tenantB);

        $response = $this->get('/api/documents');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonMissing(['title' => 'Tenant A Secret Document']);
    }

    /** @test */
    public function cross_tenant_access_is_audited(): void
    {
        $this->actingAsTenant($this->tenantB);

        $this->get("/api/documents/{$this->tenantADocument->id}");

        // Assert that an audit entry was created for the violation
        $this->assertDatabaseHas('tenant_audit_logs', [
            'event_type' => 'TENANT_CROSS_ACCESS_DETECTED',
            'severity' => 'critical',
            'resource_type' => Document::class,
            'resource_id' => (string)$this->tenantADocument->id,
        ]);
    }
}
```

---

### Pattern 2: Concurrent Tenant Operations

Verifies that tenancy isolation holds under concurrent access from multiple tenants.

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\Tenant;
use DGLab\Models\Document;

/**
 * @group tenancy-isolation
 * @group tenancy-concurrent
 */
class ConcurrentTenantOperationsTest extends IntegrationTestCase
{
    /** @test */
    public function concurrent_requests_from_different_tenants_do_not_mix_data(): void
    {
        $tenantA = Tenant::factory()->create(['identifier' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['identifier' => 'tenant-b']);

        // Create 5 documents per tenant
        $tenantADocs = [];
        $tenantBDocs = [];

        $this->actingAsTenant($tenantA);
        for ($i = 0; $i < 5; $i++) {
            $tenantADocs[] = Document::factory()->create(['title' => "TenantA-Doc-{$i}"]);
        }

        $this->actingAsTenant($tenantB);
        for ($i = 0; $i < 5; $i++) {
            $tenantBDocs[] = Document::factory()->create(['title' => "TenantB-Doc-{$i}"]);
        }

        // Simulate concurrent access: 10 parallel requests from each tenant
        $promises = [];

        foreach ([$tenantA, $tenantB] as $tenant) {
            for ($i = 0; $i < 10; $i++) {
                $promises[] = $this->asyncRequest(
                    'GET',
                    '/api/documents',
                    [],
                    ['X-Tenant-Id' => $tenant->identifier]
                );
            }
        }

        $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        // Verify no data mixing occurred
        foreach ($responses as $index => $result) {
            if ($result['state'] === 'fulfilled') {
                $body = json_decode($result['value']->getBody(), true);
                $requestTenant = ($index < 10) ? 'tenant-a' : 'tenant-b';

                foreach ($body['data'] as $doc) {
                    if ($requestTenant === 'tenant-a') {
                        $this->assertStringStartsWith('TenantA-', $doc['title']);
                    } else {
                        $this->assertStringStartsWith('TenantB-', $doc['title']);
                    }
                }
            }
        }
    }

    /** @test */
    public function concurrent_writes_respect_tenant_boundaries(): void
    {
        $tenants = [];
        $documents = [];

        // Create 3 tenants with 10 documents each
        for ($t = 0; $t < 3; $t++) {
            $tenant = Tenant::factory()->create(['identifier' => "tenant-{$t}"]);
            $tenants[] = $tenant;

            $this->actingAsTenant($tenant);
            for ($d = 0; $d < 10; $d++) {
                $documents[] = Document::factory()->create([
                    'title' => "Tenant{$t}-Doc{$d}",
                    'sort_order' => $d,
                ]);
            }
        }

        // Reorder documents concurrently across tenants
        $promises = [];
        foreach ($tenants as $tenant) {
            $this->actingAsTenant($tenant);
            $tenantDocs = Document::where('tenant_id', $tenant->id)->get();

            foreach ($tenantDocs as $doc) {
                $promises[] = $this->asyncRequest(
                    'PATCH',
                    "/api/documents/{$doc->id}",
                    ['sort_order' => rand(0, 100)],
                    ['X-Tenant-Id' => $tenant->identifier]
                );
            }
        }

        \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        // Verify each tenant's document count remains correct
        foreach ($tenants as $tenant) {
            $this->actingAsTenant($tenant);
            $count = Document::where('tenant_id', $tenant->id)->count();
            $this->assertEquals(10, $count, "Tenant {$tenant->identifier} lost documents during concurrent writes");
        }
    }
}
```

---

### Pattern 3: Tenant Context Propagation

Verifies that tenant context flows correctly through the entire request lifecycle.

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\Tenant;

/**
 * @group tenancy-isolation
 */
class TenantContextPropagationTest extends IntegrationTestCase
{
    /** @test */
    public function tenant_context_propagates_through_middleware_chain(): void
    {
        $tenant = Tenant::factory()->create(['identifier' => 'test-tenant']);

        $response = $this->get('/api/debug/tenant-context', [
            'X-Tenant-Id' => $tenant->identifier,
        ]);

        $response->assertOk();
        $response->assertJson([
            'tenant_id' => $tenant->id,
            'tenant_identifier' => $tenant->identifier,
        ]);
    }

    /** @test */
    public function tenant_context_propagates_to_queue_jobs(): void
    {
        // This test verifies that queue jobs dispatched within a tenant
        // context retain that context when the job is processed.
        $tenant = Tenant::factory()->create(['identifier' => 'queue-tenant']);

        $this->actingAsTenant($tenant);

        $job = new ProcessDocumentJob(['document_id' => 42]);
        dispatch($job);

        // Process the queue
        $this->artisan('queue:work --once');

        // Assert the job was processed with the correct tenant context
        $this->assertDatabaseHas('job_logs', [
            'tenant_id' => $tenant->id,
            'job_class' => ProcessDocumentJob::class,
        ]);
    }

    /** @test */
    public function missing_tenant_context_on_tenant_route_returns_403(): void
    {
        $response = $this->get('/api/tenants/documents', [
            // No X-Tenant-Id header
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Tenant context is required for this operation.',
        ]);
    }
}
```

---

### Pattern 4: Query Scope Enforcement

Verifies that global scopes and query macros correctly filter by tenant.

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Database\Model;
use DGLab\Database\TenantGlobalScope;
use DGLab\Models\Tenant;
use DGLab\Models\Document;

/**
 * @group tenancy-isolation
 */
class QueryScopeEnforcementTest extends IntegrationTestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['identifier' => 'scope-a']);
        $this->tenantB = Tenant::factory()->create(['identifier' => 'scope-b']);

        // Seed documents across tenants
        $this->actingAsTenant($this->tenantA);
        Document::factory()->count(3)->create(['title' => 'Tenant A Doc']);

        $this->actingAsTenant($this->tenantB);
        Document::factory()->count(3)->create(['title' => 'Tenant B Doc']);
    }

    /** @test */
    public function global_scope_filters_by_active_tenant(): void
    {
        $this->actingAsTenant($this->tenantA);

        $documents = Document::all();

        $this->assertCount(3, $documents);
        foreach ($documents as $doc) {
            $this->assertEquals($this->tenantA->id, $doc->tenant_id);
        }
    }

    /** @test */
    public function without_tenant_scope_returns_all_records(): void
    {
        $this->actingAsTenant($this->tenantA);

        $documents = Document::withoutTenantScope()->get();

        $this->assertCount(6, $documents); // All tenants included
    }

    /** @test */
    public function where_tenant_macro_applies_correct_scope(): void
    {
        $this->actingAsTenant($this->tenantA);

        $count = DB::table('documents')
            ->whereTenant()
            ->count();

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function tenant_scope_does_not_affect_non_scoped_models(): void
    {
        $this->actingAsTenant($this->tenantA);

        // AuditLog is not tenant-scoped (global)
        $log = AuditLog::first();

        $this->assertNotNull($log);
        // No tenant_id check needed — global models are not scoped
    }

    /** @test */
    public function eager_loading_respects_tenant_boundaries(): void
    {
        $this->actingAsTenant($this->tenantA);

        $tenant = Tenant::find($this->tenantA->id);
        $documents = $tenant->documents; // HasMany relationship

        $this->assertCount(3, $documents);
        foreach ($documents as $doc) {
            $this->assertEquals($this->tenantA->id, $doc->tenant_id);
        }
    }
}
```

---

### Pattern 5: Admin Override & Bypass Testing

Verifies that admin bypass mechanisms work correctly and are properly audited.

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Tenancy\TenantIsolationBypass;
use DGLab\Models\Tenant;
use DGLab\Models\Document;

/**
 * @group tenancy-isolation
 */
class TenantBypassTest extends IntegrationTestCase
{
    /** @test */
    public function admin_bypass_allows_cross_tenant_access(): void
    {
        $tenantA = Tenant::factory()->create(['identifier' => 'bypass-a']);
        $tenantB = Tenant::factory()->create(['identifier' => 'bypass-b']);

        $this->actingAsTenant($tenantA);
        Document::factory()->create(['title' => 'Tenant A Doc']);

        $this->actingAsTenant($tenantB);
        Document::factory()->create(['title' => 'Tenant B Doc']);

        // Admin bypass allows viewing all documents
        $result = TenantIsolationBypass::run(function () {
            return Document::withoutTenantScope()->get();
        }, reason: 'Test bypass', adminId: 1);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function admin_bypass_is_audited(): void
    {
        $this->actingAsTenant(Tenant::factory()->create());

        TenantIsolationBypass::run(function () {
            Document::withoutTenantScope()->get();
        }, reason: 'Monthly audit report', adminId: 1);

        $this->assertDatabaseHas('tenant_audit_logs', [
            'event_type' => 'TENANT_ADMIN_OVERRIDE',
            'severity' => 'info',
        ]);
    }

    /** @test */
    public function bypass_scope_is_restored_after_exception(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenant($tenant);

        try {
            TenantIsolationBypass::run(function () {
                throw new \RuntimeException('Something went wrong');
            }, reason: 'Should fail', adminId: 1);
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Tenant scope should still be active after the exception
        $this->assertTrue(\DGLab\Database\TenantGlobalScope::isEnabled());
    }

    /** @test */
    public function non_admin_cannot_bypass_tenant_scope(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cross-tenant query requires the bypass_tenant_isolation permission.');

        // Simulate non-admin access
        DB::table('documents')->allTenants()->get();
    }
}
```

---

### Pattern 6: Boundary Verification — Bulk Operations

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\Tenant;
use DGLab\Models\Document;

/**
 * @group tenancy-isolation
 */
class BulkOperationIsolationTest extends IntegrationTestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->actingAsTenant($this->tenantA);
        Document::factory()->count(5)->create();

        $this->actingAsTenant($this->tenantB);
        Document::factory()->count(5)->create();
    }

    /** @test */
    public function bulk_update_respects_tenant_boundaries(): void
    {
        $this->actingAsTenant($this->tenantA);

        $updated = Document::where('id', '>', 0)->update(['status' => 'archived']);

        // Should only update Tenant A's documents
        $this->assertEquals(5, $updated);

        // Verify Tenant B's documents are unchanged
        $this->actingAsTenant($this->tenantB);
        $activeDocs = Document::where('status', '!=', 'archived')->count();
        $this->assertEquals(5, $activeDocs);
    }

    /** @test */
    public function bulk_delete_respects_tenant_boundaries(): void
    {
        $this->actingAsTenant($this->tenantA);

        $deleted = Document::where('id', '>', 0)->delete();

        $this->assertEquals(5, $deleted);

        // Verify Tenant B's documents still exist
        $this->actingAsTenant($this->tenantB);
        $this->assertEquals(5, Document::count());
    }

    /** @test */
    public function bulk_create_respects_tenant_context(): void
    {
        $this->actingAsTenant($this->tenantB);

        Document::factory()->count(3)->create();

        // All new documents belong to Tenant B
        $count = Document::withoutTenantScope()
            ->where('tenant_id', $this->tenantB->id)
            ->count();
        $this->assertEquals(8, $count); // 5 original + 3 new

        // Tenant A's count is unchanged
        $countA = Document::withoutTenantScope()
            ->where('tenant_id', $this->tenantA->id)
            ->count();
        $this->assertEquals(5, $countA);
    }
}
```

---

### Pattern 7: Cache Key Isolation

```php
<?php

namespace DGLab\Tests\Integration\Tenancy;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Cache\InMemoryCacheDriver;
use DGLab\Models\Tenant;

/**
 * @group tenancy-isolation
 */
class CacheKeyIsolationTest extends IntegrationTestCase
{
    /** @test */
    public function cache_keys_are_prefixed_by_tenant(): void
    {
        $cache = new InMemoryCacheDriver();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Tenant A caches a value
        $this->actingAsTenant($tenantA);
        $cache->set('user_preferences', ['theme' => 'dark']);

        // Tenant B reads the same key
        $this->actingAsTenant($tenantB);
        $value = $cache->get('user_preferences', 'not-cached');

        // Tenant B should NOT see Tenant A's cached data
        $this->assertEquals('not-cached', $value);
    }
}
```

---

## Test Runner Configuration

Run the isolation test suite with specific PHPUnit annotations:

```bash
# Run all tenancy isolation tests
php vendor/bin/phpunit --group=tenancy-isolation

# Run concurrent operation tests (slower)
php vendor/bin/phpunit --group=tenancy-concurrent

# Run tenancy tests with verbose output
php vendor/bin/phpunit --group=tenancy --verbose

# Exclude tenancy tests in fast CI mode
php vendor/bin/phpunit --exclude-group=tenancy-isolation
```

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<testsuites>
    <testsuite name="tenancy-isolation">
        <directory>tests/Integration/Tenancy</directory>
    </testsuite>
</testsuites>

<groups>
    <include>
        <group>tenancy-isolation</group>
    </include>
</groups>
```

---

## Test Data Factories

```php
<?php

namespace Database\Factories;

use DGLab\Database\Model;
use DGLab\Models\Tenant;

/**
 * Factory trait for tenant-scoped models.
 */
trait TenantScopedFactory
{
    /**
     * Associate the model with a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(function (array $attributes) use ($tenant) {
            return [
                'tenant_id' => $tenant->id,
            ];
        });
    }

    /**
     * Define the tenant relationship default state.
     */
    public function withTenant(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tenant_id' => Tenant::factory()->create()->id,
            ];
        });
    }
}
```

---

## Test Helper: `actingAsTenant()`

```php
<?php

namespace DGLab\Tests\Concerns;

use DGLab\Database\TenantGlobalScope;
use DGLab\Models\Tenant;

/**
 * Trait for test classes that need to simulate tenant context.
 */
trait ActsAsTenant
{
    /**
     * Set the current tenant context for the test.
     *
     * Usage in tests:
     *   $tenant = Tenant::factory()->create();
     *   $this->actingAsTenant($tenant);
     */
    protected function actingAsTenant(Tenant $tenant): void
    {
        TenantGlobalScope::setCurrentTenantId((int)$tenant->id);

        // Also set the tenancy service context if available
        if ($this->hasTenancyService()) {
            $this->tenancy->setCurrentTenant($tenant);
        }

        // Clear any cached query results from previous tenant context
        $this->flushQueryCache();
    }

    /**
     * Clear the tenant context (revert to un-scoped operations).
     */
    protected function withoutTenant(): void
    {
        TenantGlobalScope::setCurrentTenantId(null);
    }

    /**
     * Create a tenant-scoped model for a specific tenant.
     */
    protected function createForTenant(string $modelClass, Tenant $tenant, array $attributes = []): Model
    {
        $this->actingAsTenant($tenant);
        return $modelClass::factory()->create($attributes);
    }

    private function hasTenancyService(): bool
    {
        return isset($this->tenancy) && $this->tenancy !== null;
    }

    private function flushQueryCache(): void
    {
        // Clear any in-memory query caches between tenant context switches
        \DGLab\Database\Connection::flushQueryLog();
    }
}
```

---

## References

- [Isolation Layer Architecture](isolation-layer.md) — Framework-enforced boundaries
- [Tenant Auditing System](tenant-audit-logging.md) — Violation tracking
- [Team Training](team-training.md) — Developer education
- [Existing Isolation Test](../../Legacy.old/tests/Unit/Core/IsolationTest.php) — Current test patterns
- [Testing Recipes](../testing/recipes.md) — General testing patterns