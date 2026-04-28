<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Facades\Event;
use DGLab\Core\GenericEvent;
use DGLab\Database\Model;
use DGLab\Database\Connection;
use DGLab\Services\Download\DownloadManager;

class IsolationTest extends TestCase
{
    public static $leakedState = null;

    public function testStaticStateDoesNotLeak()
    {
        $this->app->setConfig('isolation_test', 'dirty');
        $this->app->singleton('dirty_service', fn() => (object)['status' => 'dirty']);
        $this->app->get('dirty_service');

        Event::listen('isolation.test', function() {
            self::$leakedState = 'leaked';
        });

        // Set a static connection in Model
        $conn = $this->app->get(Connection::class);
        Model::setConnection($conn);

        // Initialize DownloadManager singleton
        DownloadManager::getInstance();

        $this->assertTrue(true);
    }

    public function testStaticStateIsClean()
    {
        $this->assertNull($this->app->config('isolation_test'));

        $this->expectException(\InvalidArgumentException::class);
        $this->app->get('dirty_service');
    }

    public function testEventsAreIsolated()
    {
        self::$leakedState = null;
        Event::dispatch(new GenericEvent('isolation.test'));
        $this->assertNull(self::$leakedState);
    }

    public function testModelConnectionIsCleared()
    {
        // Reflection to check private static $connection in Model
        $refl = new \ReflectionClass(Model::class);
        $prop = $refl->getProperty('connection');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue());
    }

    public function testDownloadManagerIsReset()
    {
        $refl = new \ReflectionClass(DownloadManager::class);
        $prop = $refl->getProperty('instance');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue());
    }

    public function testFilesystemIsIsolated()
    {
        $path = $this->app->config('storage.path') . '/isolation_test.txt';
        file_put_contents($path, 'isolated');
        $this->assertFileExists($path);

        // On next test run, this directory should be different and empty
    }

    public function testFilesystemIsClean()
    {
        $path = $this->app->config('storage.path') . '/isolation_test.txt';
        $this->assertFileDoesNotExist($path);
    }
}
