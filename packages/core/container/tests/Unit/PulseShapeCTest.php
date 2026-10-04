<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests;

use Fiber;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\ContainerException;
use SovereignStack\Core\Container\ServiceDefinition;
use stdClass;
use Throwable;

/**
 * Shape C contract tests for {@see Container::pulse()} and the
 * corresponding make() step-0 resolution path.
 *
 * Per the A3-C revised contract (S-048):
 *   pulse() accepts materialized values literally. Objects and scalar
 *   values — including strings — are bound literally to the current
 *   Fiber. Closures are rejected because they are executable factories.
 *   pulse() never performs dependency resolution. Invalid Pulse state
 *   fails closed and never falls through to global definitions.
 *
 * Each test runs a tiny Fiber to provide the Pulse scope that pulse()
 * requires (except {@see testOutsideFiberThrowsContainerException}, which
 * proves the Fiber guard fires correctly).
 *
 * @package SovereignStack\Core\Container\Tests
 */
final class PulseShapeCTest extends TestCase
{
    /**
     * 1. An object value bound via pulse() is returned unchanged by make()
     *    in the same Fiber (Shape C: the value IS the instance).
     */
    public function testObjectValueReturnedUnchanged(): void
    {
        $container = new Container();
        $value = new stdClass();
        $value->marker = 'object-value';

        $observed = null;
        $fiber = new Fiber(function () use ($container, $value, &$observed): void {
            $container->pulse('svc.object', $value);
            $observed = $container->make('svc.object');
        });
        $fiber->start();

        self::assertSame(
            $value,
            $observed,
            'pulse() with an object MUST bind that exact object literally; '
            . 'make() MUST return the same identity (Shape C).',
        );
    }

    /**
     * 2. A string value bound via pulse() is returned literally — no
     *    class_exists() check, no class-string interpretation. (S-048 rule 3.)
     */
    public function testStringValueReturnedLiterally(): void
    {
        $container = new Container();
        $value = 'some-arbitrary-string-not-a-class';

        $observed = null;
        $fiber = new Fiber(function () use ($container, $value, &$observed): void {
            $container->pulse('svc.string', $value);
            $observed = $container->make('svc.string');
        });
        $fiber->start();

        self::assertSame(
            $value,
            $observed,
            'pulse() with a string MUST return that exact string literally — '
            . 'no class-string interpretation, no class_exists() check.',
        );
    }

    /**
     * 3. An integer value bound via pulse() is returned literally.
     */
    public function testIntegerValueReturnedLiterally(): void
    {
        $container = new Container();
        $value = 42;

        $observed = null;
        $fiber = new Fiber(function () use ($container, $value, &$observed): void {
            $container->pulse('svc.int', $value);
            $observed = $container->make('svc.int');
        });
        $fiber->start();

        self::assertSame(
            $value,
            $observed,
            'pulse() with an integer MUST return that exact integer literally.',
        );
    }

    /**
     * 4. A null value bound via pulse() is returned literally (Shape C
     *    rule 5 — null is bound literally, not treated as "use $id").
     */
    public function testNullValueBoundLiterally(): void
    {
        $container = new Container();
        $observed = 'sentinel';

        $fiber = new Fiber(function () use ($container, &$observed): void {
            $container->pulse('svc.null', null);
            $observed = $container->make('svc.null');
        });
        $fiber->start();

        self::assertNull(
            $observed,
            'pulse() with null MUST bind null literally; make() MUST return null '
            . '(not fall through to global definitions, not treat as $id).',
        );
    }

    /**
     * 5. pulse() with a Closure throws ContainerException (Shape C rule 4 —
     *    Closures are factories, not materialized values). The Fiber guard
     *    fires first (so this test MUST be inside a Fiber to reach the
     *    Closure check).
     */
    public function testClosureThrowsContainerException(): void
    {
        $container = new Container();
        $factory = static function (): stdClass {
            return new stdClass();
        };

        /** @var ContainerException|null $caught */
        $caught = null;
        $fiber = new Fiber(function () use ($container, $factory, &$caught): void {
            try {
                $container->pulse('svc.closure', $factory);
            } catch (ContainerException $e) {
                $caught = $e;
            }
        });
        $fiber->start();

        self::assertInstanceOf(
            ContainerException::class,
            $caught,
            'pulse() with a Closure MUST throw ContainerException (Closures '
            . 'are factories; use bind()/singleton() for factory registration).',
        );
    }

    /**
     * 6. pulse() called from the main (non-Fiber) context throws
     *    ContainerException — there is no Pulse scope to bind to.
     */
    public function testOutsideFiberThrowsContainerException(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->pulse('svc.outside-fiber', new stdClass());
    }

    /**
     * 7. A pulse() binding shadows a worker-scoped singleton for the
     *    CURRENT Fiber only. The singleton's cached object is not returned
     *    while the pulse binding is in effect for this Fiber.
     */
    public function testPulseShadowsSingletonForCurrentFiber(): void
    {
        $container = new Container();
        $singleton = new stdClass();
        $singleton->id = 'singleton';
        $container->singleton('svc.shadowed', $singleton);

        $pulseValue = new stdClass();
        $pulseValue->id = 'pulse-value';

        $observed = null;
        $fiber = new Fiber(function () use ($container, $pulseValue, &$observed): void {
            $container->pulse('svc.shadowed', $pulseValue);
            $observed = $container->make('svc.shadowed');
        });
        $fiber->start();

        self::assertSame(
            $pulseValue,
            $observed,
            'pulse() MUST shadow a worker-scoped singleton for the current '
            . 'Fiber — make() returns the pulse value, not the cached singleton.',
        );
        self::assertNotSame(
            $singleton,
            $observed,
            'The pulse value MUST NOT be the singleton object identity.',
        );
    }

    /**
     * 8. A pulse() binding in Fiber A MUST NOT leak into Fiber B's make().
     *    Fiber B (which never called pulse() for this id) falls through to
     *    normal resolution — NotFoundException for an unbound, non-class id.
     */
    public function testPulseValueDoesNotLeakToOtherFiber(): void
    {
        $container = new Container();

        $valueA = new stdClass();
        $valueA->id = 'A';
        $observedFromA = null;
        $errorFromB = null;

        $fiberA = new Fiber(function () use ($container, $valueA, &$observedFromA): void {
            $container->pulse('svc.isolated', $valueA);
            $observedFromA = $container->make('svc.isolated');
        });
        $fiberB = new Fiber(function () use ($container, &$errorFromB): void {
            try {
                $container->make('svc.isolated');
            } catch (Throwable $e) {
                $errorFromB = $e;
            }
        });

        $fiberA->start();
        $fiberB->start();

        self::assertSame(
            $valueA,
            $observedFromA,
            'Fiber A MUST observe its own pulse value.',
        );
        self::assertNotNull(
            $errorFromB,
            'Fiber B MUST NOT see Fiber A\'s pulse value — Fiber-isolation '
            . 'invariant. Fiber B never pulsed, so make() falls through to '
            . 'normal resolution (NotFoundException for the unbound id).',
        );
    }

    /**
     * 9. Invalid Pulse state fails closed: if a Closure somehow ends up in
     *    pulseDefinitions (a contract violation — pulse() should have
     *    rejected it), make() throws ContainerException and does NOT fall
     *    through to global definitions.
     */
    public function testInvalidPulseStateFailsClosed(): void
    {
        $container = new Container();
        // Bind a global definition for the same id — make() MUST NOT fall
        // through to this if pulseDefinitions has a (corrupt) entry.
        $globalValue = new stdClass();
        $globalValue->id = 'global-value';
        $container->bind('svc.invalid-pulse', $globalValue);

        // Manually inject an Invalid Pulse state — a Closure as the pulse
        // concrete. This bypasses pulse()'s Closure guard (which would have
        // rejected it) to simulate a contract violation. make() MUST fail
        // closed and refuse to fall through to the global binding.
        $consumerFiber = new Fiber(function () use ($container, &$caught): void {
            try {
                $container->make('svc.invalid-pulse');
            } catch (ContainerException $e) {
                $caught = $e;
            }
        });
        /** @var ContainerException|null $caught */
        $caught = null;

        $injector = new Fiber(function () use ($container, $consumerFiber): void {
            $reflection = new \ReflectionClass($container);
            $pulseDefinitions = $reflection->getProperty('pulseDefinitions');
            $pulseDefinitions->setAccessible(true);
            /** @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, array<string, ServiceDefinition>> $map */
            $map = $pulseDefinitions->getValue($container);

            $closure = static function (): stdClass {
                return new stdClass();
            };
            $map[$consumerFiber] = [
                'svc.invalid-pulse' => new ServiceDefinition(
                    abstract: 'svc.invalid-pulse',
                    concrete: $closure,
                    shared: false,
                    tags: [],
                    pulseScoped: true,
                ),
            ];
        });
        $injector->start();

        // Now run the consumer Fiber. It SHOULD see the Invalid Pulse state
        // and fail closed.
        $consumerFiber->start();

        self::assertInstanceOf(
            ContainerException::class,
            $caught,
            'Invalid Pulse state (Closure in pulseDefinitions) MUST fail '
            . 'closed — make() throws ContainerException and never falls '
            . 'through to global definitions.',
        );
        self::assertStringContainsString(
            'Invalid Pulse state',
            $caught->getMessage(),
            'The fail-closed exception MUST clearly identify the Invalid Pulse state.',
        );
    }

    /**
     * 10. A pulse() binding does not affect the global binding: make() in
     *     the Fiber returns the pulse value, while make() in the main
     *     (non-Fiber) context returns the global binding's value.
     */
    public function testGlobalBindingUnaffectedByPulse(): void
    {
        $container = new Container();
        $globalValue = new stdClass();
        $globalValue->id = 'global';
        $container->bind('svc.shared', $globalValue);

        $pulseValue = new stdClass();
        $pulseValue->id = 'pulse';
        $observedFromFiber = null;

        $fiber = new Fiber(function () use ($container, $pulseValue, &$observedFromFiber): void {
            $container->pulse('svc.shared', $pulseValue);
            $observedFromFiber = $container->make('svc.shared');
        });
        $fiber->start();

        // In the Fiber: make() returns the pulse value.
        self::assertSame(
            $pulseValue,
            $observedFromFiber,
            'make() in the Fiber MUST return the pulse value, shadowing the global binding.',
        );

        // Outside the Fiber: make() returns the global binding's value —
        // the pulse binding MUST NOT have leaked into the global state.
        $observedFromMain = $container->make('svc.shared');
        self::assertSame(
            $globalValue,
            $observedFromMain,
            'make() in the main context MUST return the global binding\'s value — '
            . 'pulse() MUST NOT mutate the global definitions table.',
        );
    }
}
