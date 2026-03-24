<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Core\Application;

class GlobalDirectiveTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $app = Application::getInstance();
        $app->setConfig('app.debug', true);
        $app->setConfig('superpowers.mode', 'interpreted');

        $this->view = new View();
        @mkdir('resources/views', 0777, true);
    }

    public function testGlobalDirectiveWorks()
    {
        $store = Application::getInstance()->get(GlobalStateStore::class);
        $store->set('test_key', 'test_value');

        file_put_contents('resources/views/test_global.super.php', "~setup { \n @global('test_key', 'my_val')\n } ~\n<div>{{ \$my_val }}</div>");

        $content = $this->view->render('test_global', [], null);

        $this->assertStringContainsString('<div>test_value</div>', $content);

        @unlink('resources/views/test_global.super.php');
    }

    public function testGlobalDirectiveAlias()
    {
        $store = Application::getInstance()->get(GlobalStateStore::class);
        $store->set('system.toast', ['message' => 'Hello']);

        file_put_contents('resources/views/test_global_alias.super.php', "~setup { \n @global('system.toast', 'toast')\n } ~\n<div>{{ \$toast['message'] }}</div>");

        $content = $this->view->render('test_global_alias', [], null);

        $this->assertStringContainsString('<div>Hello</div>', $content);

        @unlink('resources/views/test_global_alias.super.php');
    }
}
