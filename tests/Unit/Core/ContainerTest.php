<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Core\Exceptions\EntryNotFoundException;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    public function test_it_implements_psr11_interface()
    {
        $app = Application::getInstance();
        $this->assertInstanceOf(ContainerInterface::class, $app);
    }

    public function test_it_throws_entry_not_found_exception()
    {
        $app = Application::getInstance();
        $this->expectException(EntryNotFoundException::class);
        $app->get('non_existent_service');
    }

    public function test_it_can_resolve_registered_services()
    {
        $app = Application::getInstance();
        $app->set('test_service', fn() => 'hello');
        $this->assertEquals('hello', $app->get('test_service'));
    }
}
