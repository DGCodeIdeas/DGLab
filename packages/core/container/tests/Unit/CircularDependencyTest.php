<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests;

use SovereignStack\Core\Container\CircularDependencyException;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\Tests\Fixtures\A;
use SovereignStack\Core\Container\Tests\Fixtures\B;
use SovereignStack\Core\Container\Tests\Fixtures\NoConstructor;

class CircularDependencyTest extends \PHPUnit\Framework\TestCase
{
    public function testCycleDetectionThrowsException(): void
    {
        $container = new Container();
        $container->bind('A', A::class);
        $container->bind('B', B::class);

        $this->expectException(CircularDependencyException::class);
        $container->make('A');
    }

    public function testExceptionContainsResolutionChain(): void
    {
        $container = new Container();
        $container->bind('A', A::class);
        $container->bind('B', B::class);

        try {
            $container->make('A');
            $this->fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            $chain = $e->getResolutionChain();
            $this->assertContains(A::class, $chain);
            $this->assertContains(B::class, $chain);
        }
    }

    public function testCycleDetectionDoesNotLeakState(): void
    {
        $container = new Container();
        $container->bind('A', A::class);
        $container->bind('B', B::class);

        try {
            $container->make('A');
            $this->fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            // Expected - after exception, stack should be clean
        }

        // Resolving a different service should work fine
        $service = $container->make(NoConstructor::class);
        $this->assertInstanceOf(NoConstructor::class, $service);
    }
}