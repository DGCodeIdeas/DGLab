<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Core\Application;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;

class PersistenceTest extends \DGLab\Tests\TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
        Application::getInstance()->set(GlobalStateStoreInterface::class, function () {
            return new GlobalStateStore();
        });
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function test_persist_directive_saves_to_store()
    {
        file_put_contents('resources/views/test_persist.super.php', "~setup { \$count = 10; @persist(\$count); \$count++; } ~ <div>{{\$count}}</div>");
        $this->view->render('test_persist', [], null);
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $this->assertEquals(11, $store->get('count'));
    }

    public function test_persist_directive_loads_from_store()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('theme', 'dark');
        file_put_contents('resources/views/test_load.super.php', "~setup { \$theme = 'light'; @persist(\$theme); } ~ <div>{{\$theme}}</div>");
        $output = $this->view->render('test_load', [], null);
        $this->assertStringContainsString('<div>dark</div>', $output);
    }

    public function test_persist_with_nested_arrays()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('settings', ['notifications' => true]);
        file_put_contents('resources/views/test_nested.super.php', "~setup { @persist(\$settings); \$settings['notifications'] = false; } ~");
        $this->view->render('test_nested', [], null);
        $updated = $store->get('settings');
        $this->assertFalse($updated['notifications']);
    }

    public function test_strict_type_persistence()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('version', 1);
        file_put_contents('resources/views/test_types.super.php', "~setup { @persist(\$version); \$version = '1'; } ~");
        $this->view->render('test_types', [], null);
        $this->assertSame('1', $store->get('version'));
    }

    public function test_null_vs_absent_keys()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        file_put_contents('resources/views/test_absent.super.php', "~setup { \$val = 'default'; @persist(\$val); } ~ <div>{{\$val}}</div>");
        $output = $this->view->render('test_absent', [], null);
        $this->assertStringContainsString('<div>default</div>', $output);
        file_put_contents('resources/views/test_null.super.php', "~setup { @persist(\$val); \$val = null; } ~");
        $this->view->render('test_null', [], null);
        $this->assertNull($store->get('val'));
        file_put_contents('resources/views/test_load_null.super.php', "~setup { \$val = 'not-null'; @persist(\$val); } ~ <div>{{\$val === null ? 'is-null' : 'not-null'}}</div>");
        $output = $this->view->render('test_load_null', [], null);
        $this->assertStringContainsString('<div>is-null</div>', $output);
    }

    public function test_serialization_validation()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $this->expectException(\InvalidArgumentException::class);
        $store->set('resource', fopen('php://memory', 'r'));
    }

    protected function tearDown(): void
    {
        @unlink('resources/views/test_persist.super.php');
        @unlink('resources/views/test_load.super.php');
        @unlink('resources/views/test_nested.super.php');
        @unlink('resources/views/test_types.super.php');
        @unlink('resources/views/test_absent.super.php');
        @unlink('resources/views/test_null.super.php');
        @unlink('resources/views/test_load_null.super.php');
        parent::tearDown();
    }
}
