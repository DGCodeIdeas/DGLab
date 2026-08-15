<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container\Tests;

use SovereignStack\Core\Container\CompilerPassInterface;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\ContainerBuilderInterface;
use SovereignStack\Core\Container\Tests\Fixtures\Service;

class CompileTest extends \PHPUnit\Framework\TestCase
{
    public function testCompileIdempotent(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->compile();
        $container->compile(); // Second call should be no-op
        $this->assertTrue($container->hasDefinition(Service::class));
    }

    public function testBindAfterCompileThrowsLogicException(): void
    {
        $container = new Container();
        $container->bind(Service::class);
        $container->compile();
        $this->expectException(\LogicException::class);
        $container->bind(Service::class);
    }

    public function testCompileRunsCompilerPasses(): void
    {
        $container = new Container();
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilderInterface $builder): void
            {
                // ContainerBuilderInterface is read-only; cast to ContainerInterface to mutate
                if ($builder instanceof \SovereignStack\Core\Container\ContainerInterface) {
                    $builder->bind('CompiledService', Service::class);
                }
            }
        });
        $container->compile();
        $service = $container->make('CompiledService');
        $this->assertInstanceOf(Service::class, $service);
    }
}