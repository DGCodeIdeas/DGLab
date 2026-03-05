<?php
/**
 * Application Container Tests
 */

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Application;
use DGLab\Core\ServiceProviderInterface;
use DGLab\Tests\TestCase;

class ApplicationTest extends TestCase
{
    public function testSingletonInstance(): void
    {
        $app1 = Application::getInstance();
        $app2 = Application::getInstance();
        
        $this->assertSame($app1, $app2);
    }
    
    public function testBindAndResolve(): void
    {
        $this->app->bind('test.service', function () {
            return new \stdClass();
        });
        
        $service = $this->app->get('test.service');
        
        $this->assertInstanceOf(\stdClass::class, $service);
    }
    
    public function testSingletonBinding(): void
    {
        $this->app->singleton('test.singleton', function () {
            return new \stdClass();
        });
        
        $instance1 = $this->app->get('test.singleton');
        $instance2 = $this->app->get('test.singleton');
        
        $this->assertSame($instance1, $instance2);
    }
    
    public function testAutowiring(): void
    {
        $this->app->bind(Dependency::class);
        $this->app->bind(ServiceWithDependency::class);
        
        $service = $this->app->get(ServiceWithDependency::class);
        
        $this->assertInstanceOf(ServiceWithDependency::class, $service);
        $this->assertInstanceOf(Dependency::class, $service->dependency);
    }
    
    public function testAlias(): void
    {
        $this->app->bind(ConcreteService::class);
        $this->app->alias(ConcreteService::class, 'service');
        
        $service = $this->app->get('service');
        
        $this->assertInstanceOf(ConcreteService::class, $service);
    }
    
    public function testHas(): void
    {
        $this->app->bind('test.exists', function () {
            return new \stdClass();
        });
        
        $this->assertTrue($this->app->has('test.exists'));
        $this->assertFalse($this->app->has('test.missing'));
    }
    
    public function testServiceProvider(): void
    {
        $provider = new TestServiceProvider();
        
        $this->app->register($provider);
        $this->app->boot();
        
        $this->assertTrue($provider->registered);
        $this->assertTrue($provider->booted);
    }
    
    public function testConfig(): void
    {
        $value = $this->app->config('app.name', 'default');
        
        $this->assertNotNull($value);
    }
}

// Test classes
class Dependency {}

class ServiceWithDependency {
    public function __construct(public Dependency $dependency) {}
}

class ConcreteService {}

class TestServiceProvider implements ServiceProviderInterface
{
    public bool $registered = false;
    public bool $booted = false;
    
    public function register(Application $app): void
    {
        $this->registered = true;
    }
    
    public function boot(Application $app): void
    {
        $this->booted = true;
    }
}
