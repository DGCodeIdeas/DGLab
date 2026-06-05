<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\DependencyGraph;

final class DependencyGraphTest extends TestCase
{
    public function testAddNode(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('core-db', 'core');
        $graph->addNode('hub-api', 'hub');
        $graph->addNode('spoke-ui', 'spoke');

        self::assertSame('core', $graph->getTier('core-db'));
        self::assertSame('hub', $graph->getTier('hub-api'));
        self::assertSame('spoke', $graph->getTier('spoke-ui'));
    }

    public function testAddNodeWithInvalidTier(): void
    {
        $graph = new DependencyGraph();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid tier');
        $graph->addNode('bad', 'invalid');
    }

    public function testResolutionOrder(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('spoke-ui', 'spoke');
        $graph->addNode('hub-api', 'hub');
        $graph->addNode('core-db', 'core');

        $order = $graph->getResolutionOrder();

        // Core must come first
        self::assertSame('core-db', $order[0]);
        // Hub comes second
        self::assertSame('hub-api', $order[1]);
        // Spoke comes last
        self::assertSame('spoke-ui', $order[2]);
    }

    public function testResolutionOrderWithDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('core-db', 'core');
        $graph->addNode('hub-api', 'hub');
        $graph->addNode('spoke-ui', 'spoke');

        $graph->addDependency('hub-api', 'core-db');
        $graph->addDependency('spoke-ui', 'hub-api');

        $order = $graph->getResolutionOrder();

        self::assertSame('core-db', $order[0]);
        self::assertSame('hub-api', $order[1]);
        self::assertSame('spoke-ui', $order[2]);
    }

    public function testCanEvaluate(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('core-db', 'core');
        $graph->addNode('hub-api', 'hub');
        $graph->addNode('spoke-ui', 'spoke');

        $graph->addDependency('hub-api', 'core-db');
        $graph->addDependency('spoke-ui', 'hub-api');

        // Core should be evaluable immediately (no deps)
        self::assertTrue($graph->canEvaluate('core-db', []));

        // Hub should not be evaluable until core passes
        self::assertFalse($graph->canEvaluate('hub-api', []));
        self::assertTrue($graph->canEvaluate('hub-api', ['core-db']));

        // Spoke should not be evaluable until hub passes
        self::assertFalse($graph->canEvaluate('spoke-ui', ['core-db']));
        self::assertTrue($graph->canEvaluate('spoke-ui', ['core-db', 'hub-api']));
    }

    public function testCircularDependency(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('a', 'core');
        $graph->addNode('b', 'core');

        $graph->addDependency('a', 'b');
        $graph->addDependency('b', 'a');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency');
        $graph->getResolutionOrder();
    }

    public function testCoreFirstOrder(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('service', 'hub');
        $graph->addNode('frontend', 'spoke');
        $graph->addNode('database', 'core');
        $graph->addNode('api-gateway', 'hub');
        $graph->addNode('cache', 'core');

        $order = $graph->getResolutionOrder();

        // All core repos must come before any hub repos
        $corePositions = \array_filter($order, fn(string $name): bool => $graph->getTier($name) === 'core');
        $hubPositions = \array_filter($order, fn(string $name): bool => $graph->getTier($name) === 'hub');
        $spokePositions = \array_filter($order, fn(string $name): bool => $graph->getTier($name) === 'spoke');

        $maxCore = \max(\array_keys($corePositions));
        $minHub = \min(\array_keys($hubPositions));

        self::assertLessThan($minHub, $maxCore);

        $maxHub = \max(\array_keys($hubPositions));
        $minSpoke = \min(\array_keys($spokePositions));

        self::assertLessThan($minSpoke, $maxHub);
    }

    public function testCoreCannotDependOnHubOrSpoke(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('core-repo', 'core');
        $graph->addNode('hub-repo', 'hub');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot depend on non-Core');
        $graph->addDependency('core-repo', 'hub-repo');
    }

    public function testGetTierForNonExistentNode(): void
    {
        $graph = new DependencyGraph();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');
        $graph->getTier('ghost');
    }

    public function testCanEvaluateForNonExistentNode(): void
    {
        $graph = new DependencyGraph();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');
        $graph->canEvaluate('ghost', []);
    }

    public function testAddDependencyForNonExistentNode(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('a', 'core');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');
        $graph->addDependency('a', 'ghost');
    }

    public function testSelfDependency(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('a', 'core');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot depend on itself');
        $graph->addDependency('a', 'a');
    }
}
