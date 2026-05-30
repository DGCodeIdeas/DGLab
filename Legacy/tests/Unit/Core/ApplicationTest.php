<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Application;
use DGLab\Tests\TestCase;

class ApplicationTest extends TestCase
{
    public function testSingletonInstance(): void
    {
        $app1 = Application::getInstance();
        $app2 = Application::getInstance();

        $this->assertSame($app1, $app2);
    }

    public function testLazyLoading(): void
    {
        $called = 0;
        $this->app->set('lazy.service', function () use (&$called) {
            $called++;
            return new \stdClass();
        });

        $this->assertEquals(0, $called);

        $service1 = $this->app->get('lazy.service');
        $this->assertEquals(1, $called);
        $this->assertInstanceOf(\stdClass::class, $service1);

        $service2 = $this->app->get('lazy.service');
        $this->assertEquals(1, $called); // Should NOT be called again
        $this->assertSame($service1, $service2);
    }

    public function testHas(): void
    {
        $this->app->set('test.exists', function () {
            return new \stdClass();
        });

        $this->assertTrue($this->app->has('test.exists'));
        $this->assertFalse($this->app->has('test.missing'));
    }

    public function testConfig(): void
    {
        $this->app->setConfig('test.foo', 'bar');
        $this->assertEquals('bar', $this->app->config('test.foo'));
    }
}
