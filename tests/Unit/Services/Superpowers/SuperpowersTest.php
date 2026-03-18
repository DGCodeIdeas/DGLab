<?php

namespace Tests\Unit\Services\Superpowers;

use PHPUnit\Framework\TestCase;
use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\SuperpowersEngine;

class SuperpowersTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();

        @mkdir('resources/views/components', 0777, true);
    }

    public function test_basic_rendering()
    {
        file_put_contents('resources/views/test_basic.super.php', '<h1>{{ $title }}</h1>');
        $output = $this->view->render('test_basic', ['title' => 'Hello World'], null);
        $this->assertEquals('<h1>Hello World</h1>', $output);
    }

    public function test_setup_block()
    {
        file_put_contents('resources/views/test_setup.super.php', "~setup { \$name = 'Jules'; }<p>{{ \$name }}</p>");
        $output = $this->view->render('test_setup', [], null);
        $this->assertEquals('<p>Jules</p>', $output);
    }

    public function test_directives()
    {
        file_put_contents('resources/views/test_directives.super.php', "@if(true)Yes@endif");
        $output = $this->view->render('test_directives', [], null);
        $this->assertEquals('Yes', $output);
    }

    public function test_components()
    {
        file_put_contents('resources/views/components/card.super.php', "<div class='card'>{{ \$title }}{!! \$slot !!}</div>");
        file_put_contents('resources/views/test_comp.super.php', "<s:card title=\"My Card\">Content</s:card>");
        $output = $this->view->render('test_comp', [], null);
        $this->assertEquals("<div class='card'>My CardContent</div>", $output);
    }

    protected function tearDown(): void
    {
        @unlink('resources/views/test_basic.super.php');
        @unlink('resources/views/test_setup.super.php');
        @unlink('resources/views/test_directives.super.php');
        @unlink('resources/views/test_comp.super.php');
        @unlink('resources/views/components/card.super.php');
        parent::tearDown();
    }
}
