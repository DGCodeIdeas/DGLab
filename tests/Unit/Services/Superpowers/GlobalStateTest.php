<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;

class GlobalStateTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($_SESSION)) $_SESSION = [];

        $app = Application::getInstance();
        $app->setConfig('superpowers.mode', 'interpreted');
        $app->singleton(GlobalStateStore::class, function() {
            return new GlobalStateStore();
        });

        $this->view = new View();
    }

    public function test_global_directive_injects_state()
    {
        $g = Application::getInstance()->get(GlobalStateStore::class);
        $g->set('site_name', 'My DGLab');

        file_put_contents('resources/views/test_global.super.php', "@global('site_name', 'name')<h1>{{ \$name }}</h1>");
        $output = $this->view->render('test_global', [], null);

        $this->assertStringContainsString('<h1>My DGLab</h1>', $output);
    }

    protected function tearDown(): void
    {
        @unlink('resources/views/test_global.super.php');
        parent::tearDown();
    }
}
