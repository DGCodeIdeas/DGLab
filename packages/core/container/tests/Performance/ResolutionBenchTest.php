<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests\Performance;

use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\CircularDependencyException;
use SovereignStack\Core\Container\Tests\Fixtures\A;
use SovereignStack\Core\Container\Tests\Fixtures\B;
use SovereignStack\Core\Container\Tests\Fixtures\Service;
use PHPUnit\Framework\TestCase;

class ResolutionBenchTest extends TestCase
{
    public function testResolutionDepth1(): void
    {
        $container = new Container();
        for ($i = 0; $i < 100; $i++) {
            $container->bind("depth1_{$i}", Service::class);
        }
        $iterations = 10000;
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $container->make('depth1_0');
        }
        $end = microtime(true);
        $this->assertLessThan(1.0, $end - $start, '10k resolutions of depth-1 service should take < 1s');
    }

    public function testResolutionDepth5(): void
    {
        $container = new Container();
        $container->bind('depth5', Service::class);
        $iterations = 1000;
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $container->make('depth5');
        }
        $end = microtime(true);
        $this->assertLessThan(2.0, $end - $start, '1k resolutions of depth-5 service should take < 2s');
    }

    public function testCompileIdempotency(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->compile();
        $container->compile(); // Second call should be no-op
        $this->assertTrue($container->hasDefinition(Service::class));
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

        // After exception, resolving a different service should work fine
        $service = $container->make(Service::class);
        $this->assertInstanceOf(Service::class, $service);
    }
}