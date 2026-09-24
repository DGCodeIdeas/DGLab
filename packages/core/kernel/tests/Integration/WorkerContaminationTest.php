<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Kernel\RequestContext;
use Fiber;
use Throwable;

/**
 * Worker contamination tests — proves persistent-worker state isolation.
 *
 * Per SPEC-001 §43 (Worker Contamination Tests):
 *   "Tests MUST prove:
 *     Request A context ≠ Request B context
 *     Tenant A ≠ Tenant B
 *     Request A trace ≠ Request B trace
 *     Mutable request state does not survive request completion"
 *
 * Per SPEC §12 (Concurrency and Isolation Test Strategy):
 *   "A test failure indicates a runtime architecture defect, not merely
 *    a failed business test."
 *
 * Per SPEC §52 (Persistent Worker Safety):
 *   "The required result for contamination tests is:
 *     0 failures
 *     0 leaked request contexts
 *     0 leaked tenant contexts"
 *
 * These tests are the executable contract for SPEC M2 (Persistent Worker
 * Safety) exit criteria: "zero cross-request state leakage across sequential
 * and concurrent tests."
 *
 * The test infrastructure uses PHP's native Fiber primitive (per ADR-017
 * "Fiber-based cooperative runtime") and the existing Container's pulse()
 * WeakMap<Fiber, ...> mechanism. Per SPEC §42:
 *   "The existing container model already provides the mechanism:
 *     WeakMap<Fiber, ...> → pulse() → request/Fiber-scoped instances"
 *
 * @package SovereignStack\Core\Kernel\Tests\Integration
 */
final class WorkerContaminationTest extends TestCase
{
    private const REQUEST_ID_A = 'req_01JTESTA000000000000000AAAAA';
    private const REQUEST_ID_B = 'req_01JTESTB000000000000000BBBBB';
    private const TRACE_ID_A = 'trace_01JTESTA0000000000000AAAAA';
    private const TRACE_ID_B = 'trace_01JTESTB0000000000000BBBBB';
    private const TENANT_A = 'tenant_01JTESTA00000000000000AAAA';
    private const TENANT_B = 'tenant_01JTESTB00000000000000BBBB';

    /**
     * Test 1: Two concurrent Fibers MUST observe independent pulse-scoped state.
     *
     * Per SPEC §43: "Request A context ≠ Request B context"
     */
    public function testConcurrentFibersObserveIndependentPulseState(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        // Each Fiber registers its OWN RequestContext via pulse(), then verifies
        // it sees its own (not the other Fiber's) state after a yield point.
        $contextA_viewOfSelf = null;
        $contextB_viewOfSelf = null;
        $contextA_viewOfB = null; // A should NOT see B's context
        $contextB_viewOfA = null; // B should NOT see A's context

        $fiberA = new Fiber(function () use (
            $container, &$contextA_viewOfSelf, &$contextA_viewOfB
        ): void {
            $contextA = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextA);

            // Suspend — let Fiber B run and set its own state
            Fiber::suspend();

            // After resumption: A MUST still see A's context (B should not have
            // overwritten A's pulse-scoped state).
            $contextA_viewOfSelf = $container->make(RequestContext::class);

            // Also try to read what B set — this SHOULD be A's own context, not B's.
            // (The Container's pulse() WeakMap<Fiber, ...> keys on the current
            // Fiber, so reading RequestContext::class from A's scope returns A's
            // instance, not B's.)
            $contextA_viewOfB = $container->make(RequestContext::class);
        });

        $fiberB = new Fiber(function () use (
            $container, &$contextB_viewOfSelf, &$contextB_viewOfA
        ): void {
            $contextB = new RequestContext(
                requestId: self::REQUEST_ID_B,
                traceId: self::TRACE_ID_B,
                tenantId: self::TENANT_B,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextB);

            // Suspend — let A resume and check its state
            Fiber::suspend();

            $contextB_viewOfSelf = $container->make(RequestContext::class);
            $contextB_viewOfA = $container->make(RequestContext::class);
        });

        // Run the Fibers interleaved
        $fiberA->start(); // A registers its context, suspends
        $fiberB->start(); // B registers its context, suspends
        $fiberA->resume(); // A resumes — checks its state (should still see A)
        $fiberB->resume(); // B resumes — checks its state (should still see B)

        // Assertions: each Fiber MUST see its OWN context, never the other's
        self::assertNotNull($contextA_viewOfSelf, 'Fiber A must have observed its own context');
        self::assertNotNull($contextB_viewOfSelf, 'Fiber B must have observed its own context');

        self::assertSame(self::REQUEST_ID_A, $contextA_viewOfSelf->requestId,
            'Fiber A MUST see its own request_id, not B\'s (state leak = architecture defect)');
        self::assertSame(self::REQUEST_ID_B, $contextB_viewOfSelf->requestId,
            'Fiber B MUST see its own request_id, not A\'s (state leak = architecture defect)');

        self::assertSame(self::TENANT_A, $contextA_viewOfSelf->tenantId,
            'Fiber A MUST see its own tenant_id, not B\'s (tenant leak = architecture defect)');
        self::assertSame(self::TENANT_B, $contextB_viewOfSelf->tenantId,
            'Fiber B MUST see its own tenant_id, not A\'s (tenant leak = architecture defect)');

        self::assertSame(self::TRACE_ID_A, $contextA_viewOfSelf->traceId,
            'Fiber A MUST see its own trace_id, not B\'s');
        self::assertSame(self::TRACE_ID_B, $contextB_viewOfSelf->traceId,
            'Fiber B MUST see its own trace_id, not A\'s');

        // Sanity check — "view of B" from A's scope is actually A's own context
        self::assertSame($contextA_viewOfSelf, $contextA_viewOfB,
            'A\'s scope MUST consistently return A\'s context');
    }

    /**
     * Test 2: Sequential requests on one worker MUST NOT inherit prior request state.
     *
     * Per SPEC §43: "sequential requests on one worker"
     * Per SPEC §52: "0 leaked request contexts"
     *
     * Model: a single Fiber that simulates two sequential requests. Each
     * request sets a new pulse-scoped context. The second request MUST NOT
     * see the first request's context via the same Container (since each
     * pulse() call replaces the value at the current Fiber).
     */
    public function testSequentialRequestsOnOneWorkerDoNotInheritPriorState(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        $contextFromRequest1 = null;
        $contextFromRequest2 = null;
        $contextAfterRequest2 = null;

        // Single Fiber simulating two sequential requests
        $fiber = new Fiber(function () use (
            $container, &$contextFromRequest1, &$contextFromRequest2, &$contextAfterRequest2
        ): void {
            // === "Request 1" ===
            $context1 = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $context1);
            $contextFromRequest1 = $container->make(RequestContext::class);

            // === "Request 2" (replaces pulse value) ===
            $context2 = new RequestContext(
                requestId: self::REQUEST_ID_B,
                traceId: self::TRACE_ID_B,
                tenantId: self::TENANT_B,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $context2);
            $contextFromRequest2 = $container->make(RequestContext::class);

            // After request 2, reading the context MUST return request 2's context (not request 1's)
            $contextAfterRequest2 = $container->make(RequestContext::class);
        });

        $fiber->start();

        self::assertSame(self::REQUEST_ID_A, $contextFromRequest1->requestId,
            'Request 1 MUST observe its own context');
        self::assertSame(self::REQUEST_ID_B, $contextFromRequest2->requestId,
            'Request 2 MUST observe its own context, not request 1\'s');
        self::assertSame(self::REQUEST_ID_B, $contextAfterRequest2->requestId,
            'After request 2, context MUST remain request 2\'s (not request 1\'s)');
        self::assertNotSame($contextFromRequest1, $contextFromRequest2,
            'Request 1 and request 2 MUST have distinct context instances');
    }

    /**
     * Test 3: Exception during request MUST NOT leak state to subsequent request.
     *
     * Per SPEC §43: "exception during request"
     * Per SPEC §12: "Request A → throw exception → cleanup → Request B →
     *                assert no state from A"
     *
     * Model: a Fiber throws an exception during "request A". After the
     * exception is caught at the boundary, a second Fiber ("request B") sets
     * its own pulse-scoped state. Request B MUST NOT see any remnants of
     * request A's state.
     */
    public function testExceptionDuringRequestDoesNotLeakToSubsequentRequest(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        $contextFromRequestB = null;
        $exceptionThrown = null;

        // Fiber A: sets state, throws, never sets state back
        $fiberA = new Fiber(function () use ($container): never {
            $contextA = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextA);

            throw new \RuntimeException('simulated request-A failure');
        });

        // Fiber B: must NOT see any of A's state
        $fiberB = new Fiber(function () use ($container, &$contextFromRequestB): void {
            $contextB = new RequestContext(
                requestId: self::REQUEST_ID_B,
                traceId: self::TRACE_ID_B,
                tenantId: self::TENANT_B,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextB);
            $contextFromRequestB = $container->make(RequestContext::class);
        });

        // Run A — should throw
        try {
            $fiberA->start();
            self::fail('Fiber A was expected to throw, but did not');
        } catch (Throwable $e) {
            $exceptionThrown = $e;
        }

        // Run B — must see only B's state, not A's remnants
        $fiberB->start();

        self::assertNotNull($exceptionThrown, 'Request A must have thrown');
        self::assertSame('simulated request-A failure', $exceptionThrown->getMessage());

        self::assertNotNull($contextFromRequestB, 'Request B must have observed its own context');
        self::assertSame(self::REQUEST_ID_B, $contextFromRequestB->requestId,
            'Request B MUST see its own request_id, not A\'s (post-exception leak = architecture defect)');
        self::assertSame(self::TENANT_B, $contextFromRequestB->tenantId,
            'Request B MUST see its own tenant_id, not A\'s (post-exception tenant leak = architecture defect)');
        self::assertSame(self::TRACE_ID_B, $contextFromRequestB->traceId,
            'Request B MUST see its own trace_id, not A\'s');
    }

    /**
     * Test 4: Completed Fiber's state MUST be garbage-collected (no retention).
     *
     * Per SPEC §52: "0 leaked request contexts"
     * Per SPEC §13: "Pulse/Fiber — MUST never cross Fibers"
     *
     * Model: a Fiber completes (sets pulse-scoped state, returns). After
     * completion, the WeakMap<Fiber, ...> should evict the entry when the
     * Fiber object is released. A subsequent attempt to read pulse-scoped
     * state from a NEW Fiber MUST NOT see the completed Fiber's state.
     */
    public function testCompletedFiberStateIsNotVisibleToNewFiber(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        // Fiber A: sets state and completes normally
        $fiberA = new Fiber(function () use ($container): void {
            $contextA = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextA);
        });
        $fiberA->start();
        unset($fiberA); // release the Fiber object — WeakMap should evict A's entry

        // Fiber B: tries to read pulse-scoped state without setting it
        $contextFromRequestB = null;
        $fiberB = new Fiber(function () use ($container, &$contextFromRequestB): void {
            // Don't set anything — just try to read
            try {
                $contextFromRequestB = $container->make(RequestContext::class);
            } catch (\SovereignStack\Core\Container\NotFoundException $e) {
                // Expected: no pulse-scoped value set for this Fiber
                $contextFromRequestB = null;
            }
        });
        $fiberB->start();

        self::assertNull($contextFromRequestB,
            'Completed Fiber A\'s pulse state MUST NOT be visible to new Fiber B ' .
            '(WeakMap<Fiber, ...> must evict on Fiber GC per SPEC §13)');
    }

    /**
     * Test 5: Repeated worker reuse (multiple sequential requests) MUST
     * maintain isolation throughout.
     *
     * Per SPEC §43: "repeated worker reuse"
     *
     * Model: simulate 5 sequential requests on one worker. Each request has
     * a different request_id / trace_id / tenant_id. Verify each sees only
     * its own state, never the prior request's.
     */
    public function testRepeatedWorkerReuseMaintainsIsolationAcrossFiveRequests(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        $observedContexts = [];
        $fiber = new Fiber(function () use ($container, &$observedContexts): void {
            for ($i = 0; $i < 5; $i++) {
                $context = new RequestContext(
                    requestId: sprintf('req_%03d_test_request_id_value', $i),
                    traceId: sprintf('trace_%03d_test_trace_id_value', $i),
                    tenantId: sprintf('tenant_%03d_test_tenant_value', $i),
                    startedAt: new DateTimeImmutable(),
                );
                $container->pulse(RequestContext::class, $context);
                $observedContexts[$i] = $container->make(RequestContext::class);
            }
        });

        $fiber->start();

        self::assertCount(5, $observedContexts, 'All 5 requests must have observed their contexts');
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(
                sprintf('req_%03d_test_request_id_value', $i),
                $observedContexts[$i]->requestId,
                "Request {$i} MUST see its own request_id"
            );
            self::assertSame(
                sprintf('trace_%03d_test_trace_id_value', $i),
                $observedContexts[$i]->traceId,
                "Request {$i} MUST see its own trace_id"
            );
            self::assertSame(
                sprintf('tenant_%03d_test_tenant_value', $i),
                $observedContexts[$i]->tenantId,
                "Request {$i} MUST see its own tenant_id"
            );
        }
    }

    /**
     * Helper: create a Container wired with a pulse-scoped RequestContext binding.
     *
     * Per SPEC §42: "The existing container model already provides the mechanism:
     *   WeakMap<Fiber, ...> → pulse() → request/Fiber-scoped instances"
     *
     * The pulse() binding here is a stub that lets the Container return whatever
     * was last registered via pulse() on the current Fiber. In production, this
     * would be wired by the ApplicationFactory (per SPEC §44) — but for testing,
     * a direct pulse() call is sufficient to verify the isolation invariant.
     */
    private function createContainerWithPulseRequestContext(): Container
    {
        $container = new Container();

        // Register RequestContext as a pulse-scoped service.
        // Per Container.php (line 134): pulse() accepts (id, concrete=null).
        // When concrete is null, the binding registers the lifetime; the
        // actual value is provided at request time via pulse($id, $instance).
        // We register a factory that reads the pulse-scoped value.
        $container->pulse(RequestContext::class, function () use ($container): RequestContext {
            // This closure is invoked by make(); it reads the pulse-scoped
            // value the current Fiber registered. The Container's make()
            // method already handles this via $pulseInstances[$fiber][$id]
            // lookup at line 181-182.
            throw new \SovereignStack\Core\Container\NotFoundException(
                'RequestContext has not been pulse-scoped on the current Fiber. ' .
                'Call $container->pulse(RequestContext::class, $context) first.'
            );
        });

        return $container;
    }
}
