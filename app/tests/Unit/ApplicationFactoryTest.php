<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Composition smoke tests for ApplicationFactory.
 *
 * Per SPEC-001 §44 (Phase 3 — Composition Root):
 *   "Move the composition currently performed by public/index.php into an
 *    ApplicationFactory or equivalent application-level composition module."
 *
 * Per SPEC §3: "The ApplicationFactory belongs at the application/infrastructure
 * composition boundary, NOT inside Core."
 *
 * Per SPEC §44: "The factory MUST NOT become a service locator."
 *
 * These tests verify:
 *   - create() returns an ApplicationFactory instance
 *   - create() does not throw during composition wiring (smoke test)
 *   - The factory exposes only create() + run() — no container leak
 *   - The factory is at the application tier (App namespace), not in Core
 *
 * Tests DO NOT call run() because run() enters the frankenphp_handle_request
 * loop or handles a real HTTP request — that's out-of-scope for unit tests
 * and is exercised by the integration smoke test (anvil/lib/deploy-smoke.sh).
 *
 * @package App\Tests\Unit
 */
final class ApplicationFactoryTest extends TestCase
{
    /**
     * create() MUST return an ApplicationFactory instance.
     */
    public function testCreateReturnsApplicationFactoryInstance(): void
    {
        $factory = ApplicationFactory::create();

        self::assertInstanceOf(ApplicationFactory::class, $factory);
    }

    /**
     * create() MUST NOT throw during composition wiring.
     *
     * This is a smoke test: if composition fails (e.g., a service can't be
     * constructed, a binding is missing), create() throws and this test fails.
     * Per SPEC §44, the factory composes Core/Bridge/App — any composition
     * error here is an architecture defect.
     */
    public function testCreateDoesNotThrowDuringComposition(): void
    {
        $factory = ApplicationFactory::create();

        // If we got here without exception, composition succeeded.
        self::assertNotNull($factory);
    }

    /**
     * create() MUST be reproducible (distinct instances per call).
     *
     * Each call composes a fresh Kernel/Container graph. If the same instance
     * were returned, the factory would be a singleton, which would violate
     * the per-worker recomposition contract.
     */
    public function testCreateIsReproducible(): void
    {
        $factory1 = ApplicationFactory::create();
        $factory2 = ApplicationFactory::create();

        self::assertInstanceOf(ApplicationFactory::class, $factory1);
        self::assertInstanceOf(ApplicationFactory::class, $factory2);
        self::assertNotSame($factory1, $factory2);
    }

    /**
     * The factory exposes only create() + run() — no container leak.
     *
     * Per SPEC §44: "The factory MUST NOT become a service locator."
     * This test verifies the public API surface is minimal: no getContainer(),
     * no getKernel(), no resolve() — those would leak the container and become
     * a service locator anti-pattern.
     */
    public function testPublicApiSurfaceIsMinimal(): void
    {
        $reflection = new ReflectionClass(ApplicationFactory::class);
        $publicMethods = array_map(
            fn(\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        // Allowed public methods (per SPEC §44 design)
        $allowed = ['create', 'run'];

        foreach ($publicMethods as $methodName) {
            self::assertContains(
                $methodName,
                $allowed,
                sprintf(
                    'ApplicationFactory exposes public method "%s" — only create() and run() ' .
                    'are allowed (per SPEC §44: "The factory MUST NOT become a service locator"). ' .
                    'Other methods would risk leaking the container.',
                    $methodName,
                ),
            );
        }
    }

    /**
     * The factory's private state MUST be readonly (immutable after construction).
     *
     * Per SPEC §44: the factory holds the composed services. If they were mutable,
     * a worker could accidentally re-assign the Kernel mid-request — a serious
     * architecture defect.
     */
    public function testPrivateStateIsReadonly(): void
    {
        $reflection = new ReflectionClass(ApplicationFactory::class);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        self::assertNotEmpty($properties, 'ApplicationFactory should hold private state');

        foreach ($properties as $property) {
            self::assertTrue(
                $property->isReadonly(),
                sprintf(
                    'Private property "%s" MUST be readonly (per SPEC §44 immutability invariant ' .
                    '— the composed services graph must not be mutated post-construction).',
                    $property->getName(),
                ),
            );
        }
    }

    /**
     * The factory lives in the App namespace (application tier), NOT in Core.
     *
     * Per SPEC §3: "The ApplicationFactory belongs at the application/infrastructure
     * composition boundary, NOT inside Core."
     * Per SPEC §15: "Contractors MUST NOT rebuild capabilities listed as CURRENT STATE."
     * Core MUST NOT become aware of application-specific implementations (per M02).
     */
    public function testFactoryLivesInAppNamespaceNotCore(): void
    {
        $reflection = new ReflectionClass(ApplicationFactory::class);
        $namespace = $reflection->getNamespaceName();

        self::assertStringStartsWith('App\\', $namespace,
            'ApplicationFactory MUST live in the App namespace (application tier per SPEC §3), ' .
            'NOT inside SovereignStack\\Core (which would invert the architecture per M02).');
        self::assertStringNotStartsWith('SovereignStack\\Core\\', $namespace,
            'ApplicationFactory MUST NOT live in Core (would invert dependency direction per ADR-004).');
    }
}
