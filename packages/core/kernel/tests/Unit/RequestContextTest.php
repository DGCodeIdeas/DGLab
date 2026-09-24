<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Kernel\RequestContext;

/**
 * Unit tests for the immutable RequestContext value object.
 *
 * Per SPEC-001 §8: RequestContext follows the immutable-value-object
 * approach established by TenantContext. Immutability is enforced via
 * `final readonly class` — any attempt to mutate the state MUST produce
 * a new instance via the with*() methods.
 *
 * Per SPEC §8: "If a tenant is established later in request processing,
 * the context SHOULD be replaced with a new immutable instance rather
 * than mutated."
 *
 * Per SPEC §13: lifetime is Pulse/Fiber — MUST never cross Fibers.
 * (Cross-Fiber isolation is tested in WorkerContaminationTest, not here.)
 *
 * @package SovereignStack\Core\Kernel\Tests\Unit
 */
final class RequestContextTest extends TestCase
{
    private const SAMPLE_REQUEST_ID = 'req_01HABCDTEST1234567890ABCDEFG';
    private const SAMPLE_TRACE_ID = 'trace_01HABCDTEST1234567890ABCDEFG';
    private const SAMPLE_TENANT_ID = 'tenant_01HABCDTEST1234567890ABC';
    private const SAMPLE_ROUTE_NAME = 'hello.world';

    public function testConstructionStoresAllFields(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-24T12:00:00Z');
        $context = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: self::SAMPLE_TENANT_ID,
            startedAt: $startedAt,
            routeName: self::SAMPLE_ROUTE_NAME,
        );

        self::assertSame(self::SAMPLE_REQUEST_ID, $context->requestId);
        self::assertSame(self::SAMPLE_TRACE_ID, $context->traceId);
        self::assertSame(self::SAMPLE_TENANT_ID, $context->tenantId);
        self::assertSame($startedAt, $context->startedAt);
        self::assertSame(self::SAMPLE_ROUTE_NAME, $context->routeName);
    }

    public function testRouteNameDefaultsToNullWhenOmitted(): void
    {
        $context = $this->createBaselineContext();

        self::assertNull($context->routeName);
        self::assertFalse($context->hasRouteName());
    }

    public function testTenantIdMayBeNullForUnauthenticatedRequest(): void
    {
        $context = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: new DateTimeImmutable(),
        );

        self::assertNull($context->tenantId);
        self::assertFalse($context->hasTenant());
    }

    public function testEmptyStringTenantIdTreatedAsAbsent(): void
    {
        $context = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: '',
            startedAt: new DateTimeImmutable(),
        );

        // Per hasTenant() contract: empty string is treated as absent
        self::assertFalse($context->hasTenant());
    }

    public function testWithTenantIdReturnsNewInstanceNotMutatingOriginal(): void
    {
        $original = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: new DateTimeImmutable(),
        );

        $updated = $original->withTenantId(self::SAMPLE_TENANT_ID);

        // Immutability invariant per SPEC §8
        self::assertNotSame($original, $updated, 'withTenantId MUST return a new instance, not mutate');
        self::assertNull($original->tenantId, 'Original instance MUST remain unchanged');
        self::assertFalse($original->hasTenant());
        self::assertSame(self::SAMPLE_TENANT_ID, $updated->tenantId);
        self::assertTrue($updated->hasTenant());
    }

    public function testWithTenantIdPreservesAllOtherFields(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-24T12:00:00Z');
        $original = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: $startedAt,
            routeName: self::SAMPLE_ROUTE_NAME,
        );

        $updated = $original->withTenantId(self::SAMPLE_TENANT_ID);

        self::assertSame($original->requestId, $updated->requestId);
        self::assertSame($original->traceId, $updated->traceId);
        self::assertSame($original->startedAt, $updated->startedAt);
        self::assertSame($original->routeName, $updated->routeName);
    }

    public function testWithRouteNameReturnsNewInstanceNotMutatingOriginal(): void
    {
        $original = $this->createBaselineContext();

        $updated = $original->withRouteName(self::SAMPLE_ROUTE_NAME);

        self::assertNotSame($original, $updated, 'withRouteName MUST return a new instance, not mutate');
        self::assertNull($original->routeName, 'Original instance MUST remain unchanged');
        self::assertFalse($original->hasRouteName());
        self::assertSame(self::SAMPLE_ROUTE_NAME, $updated->routeName);
        self::assertTrue($updated->hasRouteName());
    }

    public function testWithRouteNamePreservesAllOtherFields(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-24T12:00:00Z');
        $original = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: self::SAMPLE_TENANT_ID,
            startedAt: $startedAt,
        );

        $updated = $original->withRouteName(self::SAMPLE_ROUTE_NAME);

        self::assertSame($original->requestId, $updated->requestId);
        self::assertSame($original->traceId, $updated->traceId);
        self::assertSame($original->tenantId, $updated->tenantId);
        self::assertSame($original->startedAt, $updated->startedAt);
    }

    public function testChainedWithMethodsProduceAccumulatedState(): void
    {
        // Simulates real-world flow: create at request boundary (no tenant, no route),
        // then add tenant post-auth, then add route post-routing.
        $startedAt = new DateTimeImmutable();
        $step0 = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: $startedAt,
        );

        $step1 = $step0->withTenantId(self::SAMPLE_TENANT_ID);
        $step2 = $step1->withRouteName(self::SAMPLE_ROUTE_NAME);

        self::assertNotSame($step0, $step1);
        self::assertNotSame($step1, $step2);
        self::assertNull($step0->tenantId);
        self::assertNull($step0->routeName);
        self::assertSame(self::SAMPLE_TENANT_ID, $step1->tenantId);
        self::assertNull($step1->routeName);
        self::assertSame(self::SAMPLE_TENANT_ID, $step2->tenantId);
        self::assertSame(self::SAMPLE_ROUTE_NAME, $step2->routeName);

        // All instances share the immutable scalar fields
        foreach ([$step0, $step1, $step2] as $ctx) {
            self::assertSame(self::SAMPLE_REQUEST_ID, $ctx->requestId);
            self::assertSame(self::SAMPLE_TRACE_ID, $ctx->traceId);
            self::assertSame($startedAt, $ctx->startedAt);
        }
    }

    public function testHasTenantReturnsFalseWhenTenantIdIsNull(): void
    {
        $context = $this->createBaselineContext();
        self::assertFalse($context->hasTenant());
    }

    public function testHasTenantReturnsTrueWhenTenantIdIsNonEmptyString(): void
    {
        $context = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: self::SAMPLE_TENANT_ID,
            startedAt: new DateTimeImmutable(),
        );
        self::assertTrue($context->hasTenant());
    }

    public function testHasRouteNameReturnsFalseWhenRouteNameIsNull(): void
    {
        $context = $this->createBaselineContext();
        self::assertFalse($context->hasRouteName());
    }

    public function testHasRouteNameReturnsFalseWhenRouteNameIsEmptyString(): void
    {
        $context = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: new DateTimeImmutable(),
            routeName: '',
        );
        self::assertFalse($context->hasRouteName());
    }

    public function testTwoInstancesWithSameValuesAreNotSameObjectButEqualFields(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-24T12:00:00Z');
        $contextA = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: self::SAMPLE_TENANT_ID,
            startedAt: $startedAt,
            routeName: self::SAMPLE_ROUTE_NAME,
        );
        $contextB = new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: self::SAMPLE_TENANT_ID,
            startedAt: $startedAt,
            routeName: self::SAMPLE_ROUTE_NAME,
        );

        // Different instances
        self::assertNotSame($contextA, $contextB);

        // Same field values (immutability makes this safe)
        self::assertSame($contextA->requestId, $contextB->requestId);
        self::assertSame($contextA->traceId, $contextB->traceId);
        self::assertSame($contextA->tenantId, $contextB->tenantId);
        self::assertSame($contextA->startedAt, $contextB->startedAt);
        self::assertSame($contextA->routeName, $contextB->routeName);
    }

    private function createBaselineContext(): RequestContext
    {
        return new RequestContext(
            requestId: self::SAMPLE_REQUEST_ID,
            traceId: self::SAMPLE_TRACE_ID,
            tenantId: null,
            startedAt: new DateTimeImmutable(),
        );
    }
}
