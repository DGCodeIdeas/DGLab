<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;

class GlobalStateTest extends \DGLab\Tests\TestCase
{
    private View $view;
    private string $vPath;

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $app = Application::getInstance();
        $app->setConfig('superpowers.mode', 'interpreted');
        $this->vPath = $app->getBasePath() . '/resources/views/';

        $g = new GlobalStateStore();
        $app->singleton(GlobalStateStore::class, fn() => $g);
        $app->singleton(GlobalStateStoreInterface::class, fn() => $g);

        $this->view = new View();
    }

    public function test_global_directive_injects_state()
    {
        $g = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $g->set('site_name', 'My DGLab');

        file_put_contents($this->vPath . 'gst_global.super.php', "~setup { @global('site_name', 'name'); } ~<h1>{{ \$name }}</h1>");
        $output = $this->view->render('gst_global', [], null);

        $this->assertStringContainsString('My DGLab', $output);
    }

    protected function tearDown(): void
    {
        @unlink($this->vPath . 'gst_global.super.php');
        parent::tearDown();
    }
}
