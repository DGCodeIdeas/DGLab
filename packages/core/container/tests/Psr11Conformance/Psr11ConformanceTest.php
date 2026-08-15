<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Psr11Conformance;

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\NotFoundException;
use SovereignStack\Core\Container\CircularDependencyException;
use SovereignStack\Core\Container\Tests\Fixtures\Service;
use SovereignStack\Core\Container\Tests\Fixtures\NoConstructor;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * PSR-11 conformance test suite for {@see Container}.
 *
 * Validates every clause of the PSR-11 specification (per `psr/container` 2.0)
 * against the Sovereign Stack container implementation. This is the
 * non-negotiable gate referenced in CORE-02.md's CI Verification Criteria and
 * in ADR-002 (PSR-11 container scope).
 *
 * The suite is self-contained — it does not vendor `container-interop/impl-test`
 * (which is deprecated) nor `psr/container`'s in-package tests (which do not
 * ship as a runnable suite). Instead, it asserts the contract clauses directly:
 *
 *   - Container implements Psr\Container\ContainerInterface
 *   - get(string $id): mixed
 *       - returns the registered entry on hit
 *       - throws NotFoundExceptionInterface on miss
 *       - MAY throw ContainerExceptionInterface for other errors
 *   - has(string $id): bool
 *       - returns true for known ids
 *       - returns false for unknown ids
 *       - has() returning true guarantees get() will succeed
 *   - Exception types:
 *       - NotFoundException implements NotFoundExceptionInterface
 *       - CircularDependencyException implements ContainerExceptionInterface
 *
 * If any of these tests fail, the container is NOT PSR-11 compliant and cannot
 * be tagged 1.0.0 per the blueprint's SemVer section.
 */
class Psr11ConformanceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Build a fresh container with a typical mix of bindings for use across
     * the conformance tests. Each test gets its own instance to avoid state
     * bleed between tests.
     */
    private function buildContainer(): Container
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->bind(NoConstructor::class);
        $container->instance('prebuilt.service', new Service());
        $container->singleton('shared.service', Service::class);
        $container->bind('closure.service', \Closure::fromCallable(
            static fn(): Service => new Service()
        ));
        $container->bind('primitive.value', 'UTC');
        return $container;
    }

    /**
     * Clause 1: The container MUST implement Psr\Container\ContainerInterface.
     */
    public function testContainerImplementsPsr11Interface(): void
    {
        $container = new Container();
        $this->assertInstanceOf(PsrContainerInterface::class, $container);
    }

    /**
     * Clause 2: get($id) returns the entry for the given id.
     */
    public function testGetReturnsRegisteredEntry(): void
    {
        $container = $this->buildContainer();
        $service = $container->get(Service::class);
        $this->assertInstanceOf(Service::class, $service);
    }

    /**
     * Clause 3: get($id) throws an exception implementing NotFoundExceptionInterface
     * when the id is not known to the container.
     */
    public function testGetThrowsNotFoundExceptionImplementingPsr11Interface(): void
    {
        $container = $this->buildContainer();

        try {
            $container->get('unknown.service.id');
            $this->fail('Expected NotFoundException for unknown id.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(NotFoundExceptionInterface::class, $e);
            $this->assertInstanceOf(NotFoundException::class, $e);
        }
    }

    /**
     * Clause 4: has($id) returns true for ids known to the container.
     */
    public function testHasReturnsTrueForKnownIds(): void
    {
        $container = $this->buildContainer();

        $this->assertTrue($container->has(Service::class));
        $this->assertTrue($container->has(NoConstructor::class));
        $this->assertTrue($container->has('prebuilt.service'));
        $this->assertTrue($container->has('shared.service'));
        $this->assertTrue($container->has('closure.service'));
        $this->assertTrue($container->has('primitive.value'));
    }

    /**
     * Clause 5: has($id) returns false for ids not known to the container.
     */
    public function testHasReturnsFalseForUnknownIds(): void
    {
        $container = $this->buildContainer();

        $this->assertFalse($container->has('unknown.service.id'));
        $this->assertFalse($container->has('another.unknown.id'));
    }

    /**
     * Clause 6: If has($id) returns true, a subsequent get($id) MUST succeed.
     * Per the blueprint's Security Property #6 — has() does not lie.
     */
    public function testHasTrueImpliesGetSucceeds(): void
    {
        $container = $this->buildContainer();

        foreach ([Service::class, NoConstructor::class, 'prebuilt.service', 'shared.service', 'closure.service', 'primitive.value'] as $id) {
            $this->assertTrue($container->has($id), "has($id) must return true");
            $value = $container->get($id);
            $this->assertNotNull($value, "get($id) must return a non-null value when has($id) is true");
        }
    }

    /**
     * Clause 7: NotFoundException implements NotFoundExceptionInterface.
     */
    public function testNotFoundExceptionImplementsPsr11Interface(): void
    {
        $exception = new NotFoundException('test message');
        $this->assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    /**
     * Clause 8: CircularDependencyException implements ContainerExceptionInterface
     * (per PSR-11, any container error other than "not found" must implement
     * ContainerExceptionInterface).
     */
    public function testCircularDependencyExceptionImplementsPsr11Interface(): void
    {
        $exception = CircularDependencyException::fromChain(['A', 'B', 'A']);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    /**
     * Clause 9: get() on a container with a circular dependency throws an
     * exception implementing ContainerExceptionInterface (cycle is a container
     * error, not a "not found" error).
     */
    public function testGetOnCircularDependencyThrowsContainerException(): void
    {
        $container = new Container();
        $container->bind('A', \SovereignStack\Core\Container\Tests\Fixtures\A::class);
        $container->bind('B', \SovereignStack\Core\Container\Tests\Fixtures\B::class);

        try {
            $container->get('A');
            $this->fail('Expected CircularDependencyException.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ContainerExceptionInterface::class, $e);
            $this->assertInstanceOf(CircularDependencyException::class, $e);
        }
    }

    /**
     * Clause 10: get($id) returns the same object identity for singleton bindings
     * across multiple calls.
     */
    public function testGetReturnsSameInstanceForSingletons(): void
    {
        $container = $this->buildContainer();

        $first = $container->get('shared.service');
        $second = $container->get('shared.service');
        $this->assertSame($first, $second, 'Singleton bindings must return the same object identity.');
    }

    /**
     * Clause 11: get($id) returns distinct instances for non-singleton bindings
     * across multiple calls.
     */
    public function testGetReturnsDistinctInstancesForNonSingletons(): void
    {
        $container = $this->buildContainer();

        $first = $container->get(Service::class);
        $second = $container->get(Service::class);
        $this->assertNotSame($first, $second, 'Non-singleton bindings must return distinct instances.');
    }

    /**
     * Clause 12: get($id) on a pre-registered instance returns that exact object.
     */
    public function testGetReturnsExactInstanceForPreRegisteredObjects(): void
    {
        $container = new Container();
        $instance = new Service();
        $container->instance('prebuilt', $instance);

        $this->assertSame($instance, $container->get('prebuilt'));
    }

    /**
     * Clause 13: has() on a class-string that exists returns true (autowiring
     * is supported), and get() on that class-string succeeds by autowiring it.
     */
    public function testHasReturnsTrueForExistingClassString(): void
    {
        $container = new Container();
        $this->assertTrue($container->has(Service::class));
        $this->assertInstanceOf(Service::class, $container->get(Service::class));
    }
}
