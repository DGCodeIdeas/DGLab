<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Kernel\RequestContext;
use Fiber;
use stdClass;
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
     * Test 6: Fiber termination MUST clear that Fiber's pulse scope — a
     * subsequent Fiber's make() MUST NOT observe the terminated Fiber's value,
     * EVEN IF the terminated Fiber object has not been garbage-collected yet.
     *
     * Per CORE-02 edge case #7: "Fiber termination and GC: When a Fiber
     * terminates, its entries in the WeakMap<Fiber, ...> are eligible for GC."
     * Per CORE-02 edge case #9: child Fiber does NOT inherit parent's pulse
     * bindings — isolation goes both directions.
     *
     * The structural guarantee is the WeakMap<Fiber, ...> keying: a new Fiber
     * has a distinct key, so it queries a DIFFERENT bucket from the terminated
     * Fiber's. The new Fiber's bucket is empty (it never called pulse()), so
     * make() falls through to autowire which throws NotFoundException for
     * RequestContext's required constructor params.
     *
     * This test is stronger than testCompletedFiberStateIsNotVisibleToNewFiber
     * (Test 4): it does NOT call unset($fiberA) before checking. The isolation
     * guarantee does NOT depend on PHP's GC — it depends on the WeakMap keying.
     */
    public function testFiberTerminationClearsPulseScope(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        // Fiber A: pulses a value, then completes normally. NOTE: $fiberA is
        // NOT released here (no unset) — its WeakMap entry is technically
        // still alive. The point of this test is that isolation does NOT
        // depend on GC timing.
        $fiberA = new Fiber(function () use ($container): void {
            $contextA = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextA);
            // Fiber A completes — control returns to caller.
        });
        $fiberA->start();

        // New Fiber B: must NOT see A's pulse-scoped value (B has its own
        // WeakMap bucket, which is empty — make() falls through to autowire
        // which throws NotFoundException for RequestContext's required params).
        $contextFromRequestB = null;
        $fiberB = new Fiber(function () use ($container, &$contextFromRequestB): void {
            try {
                $contextFromRequestB = $container->make(RequestContext::class);
            } catch (\SovereignStack\Core\Container\NotFoundException $e) {
                $contextFromRequestB = null;
            }
        });
        $fiberB->start();

        self::assertNull($contextFromRequestB,
            'Fiber B MUST NOT observe Fiber A\'s pulse-scoped value, even ' .
            'before A is garbage-collected (per-Fiber WeakMap bucketing is ' .
            'the structural guarantee of pulse isolation)');
    }

    /**
     * Test 7: A new Fiber (created after an old Fiber set pulse state) MUST
     * NOT see the old Fiber's pulse state — explicit version with separate
     * Fiber objects and explicit assertions about identity.
     *
     * Per SPEC §43: "Mutable request state does not survive request completion"
     * Per CORE-02 edge case #8: "Fiber reuse: If a Fiber is reused (started
     * again after termination), it gets a fresh pulse scope."
     *
     * Distinct from Test 6: this test first verifies the OLD Fiber's
     * own-observation contract works (sanity), THEN verifies the NEW Fiber
     * does not see the old state.
     */
    public function testNewFiberDoesNotSeeOldFiberPulseState(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        // Fiber A: pulses a value, observes its own value, completes.
        $contextFromA = null;
        $fiberA = new Fiber(function () use ($container, &$contextFromA): void {
            $contextA = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $contextA);
            $contextFromA = $container->make(RequestContext::class);
        });
        $fiberA->start();

        // Sanity check: the OLD Fiber's own observation MUST be correct
        // (otherwise the test is meaningless — we'd be testing isolation
        // against a broken baseline).
        self::assertNotNull($contextFromA, 'Fiber A must have observed its own context');
        self::assertSame(self::REQUEST_ID_A, $contextFromA->requestId,
            'Fiber A MUST see its own request_id (sanity check before isolation)');

        // New Fiber B: tries to read A's pulse-scoped value. B has its own
        // WeakMap bucket, which is empty, so make() falls through to autowire
        // which throws NotFoundException.
        $contextFromB = null;
        $fiberB = new Fiber(function () use ($container, &$contextFromB): void {
            try {
                $contextFromB = $container->make(RequestContext::class);
            } catch (\SovereignStack\Core\Container\NotFoundException $e) {
                $contextFromB = null;
            }
        });
        $fiberB->start();

        self::assertNull($contextFromB,
            'New Fiber B MUST NOT see old Fiber A\'s pulse-scoped state — ' .
            'WeakMap<Fiber, ...> bucketing ensures per-Pulse isolation by ' .
            'construction, not by convention');
    }

    /**
     * Test 8: Nested Fibers MUST inherit global singleton bindings.
     *
     * Per CORE-02 edge case #9: "Child Fiber inherits: global singletons,
     * frozen bindings, compiler passes, container configuration."
     *
     * singleton() is worker-scoped (Shape A) — its instances are shared
     * across all Fibers, including child Fibers. This is correct behavior:
     * singletons are immutable boot-time configuration, NOT per-Pulse state.
     */
    public function testNestedFiberInheritsGlobalSingletons(): void
    {
        $container = new Container();

        // Register a singleton at boot time (outside any Fiber — OK for
        // singleton(), which is worker-scoped and not subject to the
        // Fiber-context requirement that pulse() has).
        $container->singleton(stdClass::class, new stdClass());

        $singletonFromParent = null;
        $singletonFromChild = null;

        $fiber = new Fiber(function () use (
            $container, &$singletonFromParent, &$singletonFromChild
        ): void {
            // Parent Fiber reads the singleton from the worker-scoped cache.
            $singletonFromParent = $container->make(stdClass::class);

            // A nested (child) Fiber MUST also see the SAME singleton — it is
            // worker-scoped, not per-Fiber. The child inherits the container's
            // global state (frozen bindings, singletons, compiler passes).
            $child = new Fiber(function () use ($container, &$singletonFromChild): void {
                $singletonFromChild = $container->make(stdClass::class);
            });
            $child->start();
        });
        $fiber->start();

        self::assertNotNull($singletonFromParent, 'Parent must resolve the singleton');
        self::assertNotNull($singletonFromChild, 'Child Fiber must also resolve the singleton');
        self::assertSame($singletonFromParent, $singletonFromChild,
            'Child Fiber MUST inherit the parent\'s singleton (worker-scoped, ' .
            'Shape A) — singletons are immutable boot-time configuration, ' .
            'NOT per-Pulse state. This is correct behavior.');
    }

    /**
     * Test 9: A nested child Fiber's pulse() MUST NOT affect the parent
     * Fiber's pulse scope — isolation is bidirectional.
     *
     * Per CORE-02 edge case #9: "Child Fiber does NOT inherit: parent's
     * pulse bindings, parent's pulse instances, parent's resolution stack."
     *
     * Symmetrically, the parent does NOT inherit the child's pulse bindings
     * either — pulse scope is strictly per-Fiber. A child's pulse() MUST NOT
     * bleed into the parent's scope (or vice versa).
     */
    public function testNestedFiberHasIndependentPulseScope(): void
    {
        $container = $this->createContainerWithPulseRequestContext();

        $contextFromParentBefore = null;
        $contextFromChild = null;
        $contextFromParentAfter = null;

        $fiber = new Fiber(function () use (
            $container,
            &$contextFromParentBefore,
            &$contextFromChild,
            &$contextFromParentAfter
        ): void {
            // Parent pulses its own context.
            $parentContext = new RequestContext(
                requestId: self::REQUEST_ID_A,
                traceId: self::TRACE_ID_A,
                tenantId: self::TENANT_A,
                startedAt: new DateTimeImmutable(),
            );
            $container->pulse(RequestContext::class, $parentContext);
            $contextFromParentBefore = $container->make(RequestContext::class);

            // Child Fiber pulses a DIFFERENT context — this MUST NOT bleed
            // into the parent's pulse scope.
            $child = new Fiber(function () use ($container, &$contextFromChild): void {
                $childContext = new RequestContext(
                    requestId: self::REQUEST_ID_B,
                    traceId: self::TRACE_ID_B,
                    tenantId: self::TENANT_B,
                    startedAt: new DateTimeImmutable(),
                );
                $container->pulse(RequestContext::class, $childContext);
                $contextFromChild = $container->make(RequestContext::class);
            });
            $child->start();

            // After the child returned: parent's pulse scope MUST still hold
            // the parent's context, not the child's. Isolation is bidirectional.
            $contextFromParentAfter = $container->make(RequestContext::class);
        });
        $fiber->start();

        self::assertSame(self::REQUEST_ID_A, $contextFromParentBefore->requestId,
            'Parent MUST see its own context before the child runs');
        self::assertSame(self::REQUEST_ID_B, $contextFromChild->requestId,
            'Child MUST see its own context, not the parent\'s (child does ' .
            'NOT inherit parent\'s pulse bindings)');
        self::assertSame(self::REQUEST_ID_A, $contextFromParentAfter->requestId,
            'Parent MUST STILL see its own context after the child returned — ' .
            'child\'s pulse() MUST NOT bleed into parent\'s scope (bidirectional ' .
            'isolation: parent does NOT inherit child\'s pulse bindings either)');
    }

    /**
     * Test 10: Worker-scoped singletons (singleton()) MUST be shared across
     * Fibers — the SAME object identity MUST be returned from any Fiber in
     * the worker.
     *
     * Per CORE-02 Shape A: singletons are worker-scoped (crosses Fiber
     * boundary — OK, by design). Per CORE-02 edge case #5: singleton() is
     * worker-scoped (global).
     *
     * This is the CORRECT behavior for singletons, contrasted with
     * pulse-scoped state which MUST NOT be shared (see
     * {@see testPulseScopedInstancesAreNotSharedAcrossFibers}).
     */
    public function testGlobalSingletonsAreSharedAcrossFibers(): void
    {
        $container = new Container();
        $container->singleton(stdClass::class, new stdClass());

        $singletonFromA = null;
        $singletonFromB = null;

        $fiberA = new Fiber(function () use ($container, &$singletonFromA): void {
            $singletonFromA = $container->make(stdClass::class);
        });
        $fiberB = new Fiber(function () use ($container, &$singletonFromB): void {
            $singletonFromB = $container->make(stdClass::class);
        });

        $fiberA->start();
        $fiberB->start();

        self::assertSame($singletonFromA, $singletonFromB,
            'Singletons MUST be shared across Fibers (worker-scoped, Shape A) — ' .
            'same object identity from any Fiber in the worker. This is the ' .
            'correct behavior for singletons; contrast with pulse() which ' .
            'MUST NOT be shared (see testPulseScopedInstancesAreNotSharedAcrossFibers)');
    }

    /**
     * Test 11: Pulse-scoped instances (pulse()) MUST NOT be shared across
     * Fibers — each Fiber MUST receive its own independent instance.
     *
     * Per CORE-02 Shape C: pulse-scoped bindings are Fiber-local — never
     * cross a Fiber boundary.
     * Per SPEC §13: "Pulse/Fiber — MUST never cross Fibers"
     *
     * This is THE load-bearing isolation invariant: per-Pulse state must
     * never leak across Fiber boundaries, even on a long-running FrankenPHP
     * worker that has served many sequential or concurrent Pulses.
     */
    public function testPulseScopedInstancesAreNotSharedAcrossFibers(): void
    {
        $container = new Container();

        $instanceFromA = null;
        $instanceFromB = null;
        $valueA = new stdClass();
        $valueB = new stdClass();

        $fiberA = new Fiber(function () use ($container, &$instanceFromA, $valueA): void {
            // Pulse-bind a fresh stdClass to Fiber A's scope only.
            $container->pulse(stdClass::class, $valueA);
            $instanceFromA = $container->make(stdClass::class);
        });

        $fiberB = new Fiber(function () use ($container, &$instanceFromB, $valueB): void {
            // Pulse-bind a DIFFERENT fresh stdClass to Fiber B's scope only.
            $container->pulse(stdClass::class, $valueB);
            $instanceFromB = $container->make(stdClass::class);
        });

        $fiberA->start();
        $fiberB->start();

        self::assertNotNull($instanceFromA, 'Fiber A must have observed its own pulse instance');
        self::assertNotNull($instanceFromB, 'Fiber B must have observed its own pulse instance');

        self::assertSame($valueA, $instanceFromA,
            'Fiber A MUST receive its own pulse-scoped value, not B\'s');
        self::assertSame($valueB, $instanceFromB,
            'Fiber B MUST receive its own pulse-scoped value, not A\'s');

        self::assertNotSame($instanceFromA, $instanceFromB,
            'Pulse-scoped instances MUST NOT be shared across Fibers — each ' .
            'Fiber receives its own independent instance (Shape C, SPEC §13). ' .
            'A shared instance here would be a Fiber-boundary violation — ' .
            'an architecture defect, not a failed business test');
    }

    /**
     * Helper: create a Container for use by per-test Fibers.
     *
     * Per SPEC §42: "The existing container model already provides the mechanism:
     *   WeakMap<Fiber, ...> -> pulse() -> request/Fiber-scoped instances"
     *
     * Per CORE-02 Shape C: pulse() is a request-time, Fiber-local value-binding
     * operation. It REQUIRES a current Fiber context; calling it from the main
     * (non-Fiber) context throws \SovereignStack\Core\Container\ContainerException.
     * Therefore the helper returns a bare Container — every test that uses it
     * MUST call pulse() from INSIDE a Fiber to establish the per-Pulse binding.
     *
     * Fibers that call make() WITHOUT first calling pulse() for that Fiber
     * fall through to normal resolution (worker-scoped instances, then global
     * definitions, then class-string autowire). For RequestContext, that means
     * autowire() throws NotFoundException because the required constructor
     * parameters (requestId, traceId, tenantId, startedAt) have no defaults.
     */
    private function createContainerWithPulseRequestContext(): Container
    {
        return new Container();
    }
}
