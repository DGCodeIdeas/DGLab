<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Core\Exceptions\ContainerException;

class DependencyA {}
class DependencyB {
    public function __construct(public DependencyA $a) {}
}

class AutoWiringTest extends TestCase
{
    public function test_it_can_auto_wire_simple_class()
    {
        $app = Application::getInstance();
        $instance = $app->get(DependencyA::class);
        $this->assertInstanceOf(DependencyA::class, $instance);
    }

    public function test_it_can_auto_wire_recursive_dependencies()
    {
        $app = Application::getInstance();
        $instance = $app->get(DependencyB::class);
        $this->assertInstanceOf(DependencyB::class, $instance);
        $this->assertInstanceOf(DependencyA::class, $instance->a);
    }
}
