<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests;

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\NotFoundException;
use SovereignStack\Core\Container\CircularDependencyException;
use SovereignStack\Core\Container\Tests\Fixtures\Service;
use SovereignStack\Core\Container\Tests\Fixtures\NoConstructor;
use SovereignStack\Core\Container\Tests\Fixtures\WithParams;
use SovereignStack\Core\Container\Tests\Fixtures\A;
use SovereignStack\Core\Container\Tests\Fixtures\B;

class ContainerTest extends \PHPUnit\Framework\TestCase
{
    public function testMakeResolvesBoundService(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $service = $container->make(Service::class);
        $this->assertInstanceOf(Service::class, $service);
    }

    public function testMakeResolvesSingleton(): void
    {
        $container = new Container();
        $instance = new Service();
        $container->instance(Service::class, $instance);
        $retrieved = $container->make(Service::class);
        $this->assertSame($instance, $retrieved);
    }

    public function testMakeResolvesClosure(): void
    {
        $container = new Container();
        $container->bind('closure.service', \Closure::fromCallable(
            static function (Container $container): Service {
                return new Service();
            }
        ));
        $service = $container->make('closure.service');
        $this->assertInstanceOf(Service::class, $service);
    }

    public function testMakeResolvesClassWithNoConstructor(): void
    {
        $container = new Container();
        $container->bind(NoConstructor::class);
        $service = $container->make(NoConstructor::class);
        $this->assertInstanceOf(NoConstructor::class, $service);
    }

    public function testMakeWithParameters(): void
    {
        $container = new Container();
        $container->bind(WithParams::class);
        $service = $container->make(WithParams::class, ['customParam' => 'customValue']);
        $this->assertInstanceOf(WithParams::class, $service);
    }

    public function testGetThrowsNotFoundException(): void
    {
        $container = new Container();
        $this->expectException(NotFoundException::class);
        $container->get('unbound.service');
    }

    public function testHasReturnsTrueForBoundService(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $this->assertTrue($container->has(Service::class));
    }

    public function testHasReturnsTrueForInstantiatedClass(): void
    {
        $container = new Container();
        $this->assertTrue($container->has(Service::class));
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $instance = new Service();
        $container->instance(Service::class, $instance);
        $retrieved = $container->make(Service::class);
        $this->assertSame($instance, $retrieved);
    }

    public function testCompileIsIdempotent(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->compile();
        $container->compile(); // Should not throw
        $this->assertTrue($container->hasDefinition(Service::class));
    }

    public function testBindAfterCompileThrows(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->compile();
        $this->expectException(\LogicException::class);
        $container->bind(Service::class);
    }

    public function testCycleDetection(): void
    {
        $container = new Container();
        $container->bind('A', A::class);
        $container->bind('B', B::class);
        $this->expectException(CircularDependencyException::class);
        $container->make('A');
    }

    public function testFinallyCleanupAfterCycle(): void
    {
        $container = new Container();
        $container->bind('A', A::class);
        $container->bind('B', B::class);
        try {
            $container->make('A');
            $this->fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            // Expected
        }
        // After exception, resolving stack should be clean - resolving a different service should work
        $service = $container->make(NoConstructor::class);
        $this->assertInstanceOf(NoConstructor::class, $service);
    }

    public function testSingleton(): void
    {
        $container = new Container();
        $container->singleton(Service::class);
        $service1 = $container->make(Service::class);
        $service2 = $container->make(Service::class);
        $this->assertSame($service1, $service2);
    }

    public function testSingletonViaGet(): void
    {
        $container = new Container();
        $container->singleton(Service::class);
        $service1 = $container->get(Service::class);
        $service2 = $container->get(Service::class);
        $this->assertSame($service1, $service2);
    }

    public function testGetDefinitionIdForConcrete(): void
    {
        $container = new Container();
        $container->bind('my-service', Service::class);
        $id = $container->getDefinitionIdForConcrete(Service::class);
        $this->assertSame('my-service', $id);
    }

    public function testGetDefinitionIdForConcreteReturnsNullForUnknown(): void
    {
        $container = new Container();
        $id = $container->getDefinitionIdForConcrete(Service::class);
        $this->assertNull($id);
    }

    public function testGetReturnsService(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $service = $container->get(Service::class);
        $this->assertInstanceOf(Service::class, $service);
    }

    public function testGetThrowsForUnknownService(): void
    {
        $container = new Container();
        $this->expectException(NotFoundException::class);
        $container->get('unknown.service');
    }

    public function testGetReturnsCachedInstance(): void
    {
        $container = new Container();
        $container->singleton(Service::class);
        $service1 = $container->get(Service::class);
        $service2 = $container->get(Service::class);
        $this->assertSame($service1, $service2);
    }

    public function testGetDefinitions(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $definitions = $container->getDefinitions();
        $this->assertArrayHasKey(Service::class, $definitions);
    }

    public function testGetDefinition(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $definition = $container->getDefinition(Service::class);
        $this->assertSame(Service::class, $definition->abstract);
    }

    public function testGetDefinitionThrowsForUnknown(): void
    {
        $container = new Container();
        $this->expectException(NotFoundException::class);
        $container->getDefinition('unknown.service');
    }

    public function testHasDefinition(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $this->assertTrue($container->hasDefinition(Service::class));
        $this->assertFalse($container->hasDefinition('unknown.service'));
    }

    public function testGetWithInstance(): void
    {
        $container = new Container();
        $instance = new Service();
        $container->instance(Service::class, $instance);
        $retrieved = $container->get(Service::class);
        $this->assertSame($instance, $retrieved);
    }

    public function testGetWithClosure(): void
    {
        $container = new Container();
        $container->bind('closure.service', \Closure::fromCallable(
            static function (Container $container): Service {
                return new Service();
            }
        ));
        $service = $container->get('closure.service');
        $this->assertInstanceOf(Service::class, $service);
    }

    public function testGetDefinitionsMultiple(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->bind(NoConstructor::class);
        $definitions = $container->getDefinitions();
        $this->assertCount(2, $definitions);
        $this->assertArrayHasKey(Service::class, $definitions);
        $this->assertArrayHasKey(NoConstructor::class, $definitions);
    }

    public function testGetDefinitionWithConcrete(): void
    {
        $container = new Container();
        $container->bind('my-service', Service::class);
        $definition = $container->getDefinition('my-service');
        $this->assertSame('my-service', $definition->abstract);
        $this->assertSame(Service::class, $definition->concrete);
    }

    public function testGetWithAutowiring(): void
    {
        $container = new Container();
        $container->bind(WithParams::class);
        $service = $container->get(WithParams::class);
        $this->assertInstanceOf(WithParams::class, $service);
    }

    public function testGetWithParameters(): void
    {
        $container = new Container();
        $container->bind(WithParams::class);
        $service = $container->get(WithParams::class, ['customParam' => 'customValue']);
        $this->assertInstanceOf(WithParams::class, $service);
    }

    public function testGetDefinitionIdForConcreteWithInstance(): void
    {
        $container = new Container();
        $instance = new Service();
        $container->instance('my-instance', $instance);
        $id = $container->getDefinitionIdForConcrete(Service::class);
        $this->assertSame('my-instance', $id);
    }
}