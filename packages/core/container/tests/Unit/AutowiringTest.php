<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests;

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\NotFoundException;
use SovereignStack\Core\Container\Tests\Fixtures\ClosureConcrete;
use SovereignStack\Core\Container\Tests\Fixtures\ObjectConcrete;
use SovereignStack\Core\Container\Tests\Fixtures\ClassString;
use SovereignStack\Core\Container\Tests\Fixtures\NoConstructor;
use SovereignStack\Core\Container\Tests\Fixtures\WithDefaults;
use SovereignStack\Core\Container\Tests\Fixtures\WithParams;
use SovereignStack\Core\Container\Tests\Fixtures\Unresolvable;
use SovereignStack\Core\Container\Tests\Fixtures\AbstractService;

class AutowiringTest extends \PHPUnit\Framework\TestCase
{
    public function testAutowiresClosureConcrete(): void
    {
        $container = new Container();
        $container->bind('closure.concrete', \Closure::fromCallable(
            static function (Container $container): ClosureConcrete {
                return new ClosureConcrete();
            }
        ));
        $service = $container->make('closure.concrete');
        $this->assertInstanceOf(ClosureConcrete::class, $service);
    }

    public function testAutowiresObjectConcrete(): void
    {
        $container = new Container();
        $instance = new ObjectConcrete();
        $container->instance('object.concrete', $instance);
        $service = $container->make('object.concrete');
        $this->assertSame($instance, $service);
    }

    public function testAutowiresClassStringConcrete(): void
    {
        $container = new Container();
        $container->bind('class.string', ClassString::class);
        $service = $container->make('class.string');
        $this->assertInstanceOf(ClassString::class, $service);
    }

    public function testAutowiresDefaultScalar(): void
    {
        $container = new Container();
        $container->bind('scalar.value', 'literal_string_value');
        $service = $container->make('scalar.value');
        $this->assertSame('literal_string_value', $service);
    }

    public function testAutowiresServiceWithNoConstructor(): void
    {
        $container = new Container();
        $container->bind(NoConstructor::class);
        $service = $container->make(NoConstructor::class);
        $this->assertInstanceOf(NoConstructor::class, $service);
    }

    public function testAutowiresServiceWithConstructorAndDefaults(): void
    {
        $container = new Container();
        $container->bind(WithDefaults::class);
        $service = $container->make(WithDefaults::class);
        $this->assertInstanceOf(WithDefaults::class, $service);
    }

    public function testAutowiresServiceWithProvidedParameters(): void
    {
        $container = new Container();
        $container->bind(WithParams::class);
        $service = $container->make(WithParams::class, ['customParam' => 'customValue']);
        $this->assertInstanceOf(WithParams::class, $service);
    }

    public function testNotFoundExceptionForUnknownClass(): void
    {
        $container = new Container();
        $this->expectException(NotFoundException::class);
        $container->make('NonExistent\\Class');
    }

    public function testNotFoundExceptionForAbstractClass(): void
    {
        $container = new Container();
        $this->expectException(NotFoundException::class);
        $container->make(AbstractService::class);
    }

    public function testNotFoundExceptionForUnresolvablePrimitive(): void
    {
        $container = new Container();
        $container->bind(Unresolvable::class);
        $this->expectException(NotFoundException::class);
        $container->make(Unresolvable::class);
    }
}