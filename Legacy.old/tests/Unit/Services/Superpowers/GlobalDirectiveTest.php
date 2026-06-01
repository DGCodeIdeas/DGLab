<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Core\Application;

class GlobalDirectiveTest extends \DGLab\Tests\TestCase
{
    private View $view;
    private string $vPath;

    protected function setUp(): void
    {
        parent::setUp();
        $app = Application::getInstance();
        $app->setConfig('app.debug', true);
        $app->setConfig('superpowers.mode', 'interpreted');
        $this->vPath = $app->getBasePath() . '/resources/views/';

        $this->view = new View(Application::getInstance());
        @mkdir($this->vPath, 0777, true);
    }

    public function testGlobalDirectiveWorks()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('test_key', 'test_value');

        file_put_contents($this->vPath . 'gdt_test.super.php', "~setup { \n @global('test_key', 'my_val')\n } ~\n<div>{{ \$my_val }}</div>");

        $content = $this->view->render('gdt_test', [], null);

        $this->assertStringContainsString('<div>test_value</div>', $content);

        @unlink($this->vPath . 'gdt_test.super.php');
    }

    public function testGlobalDirectiveAlias()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('system.toast', ['message' => 'Hello']);

        file_put_contents($this->vPath . 'gdt_alias.super.php', "~setup { \n @global('system.toast', 'toast')\n } ~\n<div>{{ \$toast['message'] }}</div>");

        $content = $this->view->render('gdt_alias', [], null);

        $this->assertStringContainsString('<div>Hello</div>', $content);

        @unlink($this->vPath . 'gdt_alias.super.php');
    }
}
