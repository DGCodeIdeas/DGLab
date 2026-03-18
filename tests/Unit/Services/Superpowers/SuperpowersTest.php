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

    public function test_dot_notation()
    {
        $user = ['profile' => (object) ['name' => 'Jules']];
        file_put_contents('resources/views/test_dot.super.php', '<h1>{{ $user.profile.name }}</h1>');
        $output = $this->view->render('test_dot', ['user' => $user], null);
        $this->assertEquals('<h1>Jules</h1>', $output);
    }

    public function test_null_safe_dot_notation()
    {
        file_put_contents('resources/views/test_null_dot.super.php', '<h1>{{ $user.profile.name ?? "Guest" }}</h1>');
        $output = $this->view->render('test_null_dot', ['user' => null], null);
        $this->assertEquals('<h1>Guest</h1>', $output);
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

    public function test_foreach_with_dot_notation()
    {
        $data = ['users' => [['name' => 'A'], ['name' => 'B']]];
        file_put_contents('resources/views/test_foreach_dot.super.php', "@foreach(\$data.users as \$u){{ \$u.name }}@endforeach");
        $output = $this->view->render('test_foreach_dot', ['data' => $data], null);
        $this->assertEquals('AB', $output);
    }

    public function test_components_basic()
    {
        file_put_contents('resources/views/components/card.super.php', "<div class='card'>{{ \$title }}{!! \$slot !!}</div>");
        file_put_contents('resources/views/test_comp.super.php', "<s:card title=\"My Card\">Content</s:card>");
        $output = $this->view->render('test_comp', [], null);
        $this->assertEquals("<div class='card'>My CardContent</div>", $output);
    }

    public function test_components_named_slots()
    {
        file_put_contents('resources/views/components/modal.super.php', "<div class='modal'><header>{!! \$header !!}</header><body>{!! \$slot !!}</body></div>");
        file_put_contents('resources/views/test_modal.super.php', "<s:modal><s:slot name=\"header\">Title</s:slot>Content</s:modal>");
        $output = $this->view->render('test_modal', [], null);
        $this->assertEquals("<div class='modal'><header>Title</header><body>Content</body></div>", $output);
    }

    public function test_recursive_components()
    {
        file_put_contents('resources/views/components/item.super.php', "<li>{{ \$name }}@if(!empty(\$children))<ul>@foreach(\$children as \$child)<s:item :name=\"\$child.name\" :children=\"\$child.children ?? []\" />@endforeach</ul>@endif</li>");
        $tree = [['name' => 'A', 'children' => [['name' => 'A1']]], ['name' => 'B']];
        file_put_contents('resources/views/test_recursive.super.php', "<ul>@foreach(\$tree as \$node)<s:item :name=\"\$node.name\" :children=\"\$node.children ?? []\" />@endforeach</ul>");
        $output = $this->view->render('test_recursive', ['tree' => $tree], null);
        $this->assertEquals("<ul><li>A<ul><li>A1</li></ul></li><li>B</li></ul>", str_replace(["\n", " "], "", $output));
    }

    public function test_lifecycle_hooks()
    {
        file_put_contents('resources/views/test_hooks.super.php', "~setup { \$val = 1; } ~mount { \$val++; } <div>{{ \$val }}</div>");
        $output = $this->view->render('test_hooks', [], null);
        $this->assertStringContainsString('<div>2</div>', $output);
    }

    protected function tearDown(): void
    {
        @unlink('resources/views/test_basic.super.php');
        @unlink('resources/views/test_dot.super.php');
        @unlink('resources/views/test_null_dot.super.php');
        @unlink('resources/views/test_setup.super.php');
        @unlink('resources/views/test_directives.super.php');
        @unlink('resources/views/test_foreach_dot.super.php');
        @unlink('resources/views/test_comp.super.php');
        @unlink('resources/views/test_modal.super.php');
        @unlink('resources/views/test_recursive.super.php');
        @unlink('resources/views/test_hooks.super.php');
        @unlink('resources/views/components/card.super.php');
        @unlink('resources/views/components/modal.super.php');
        @unlink('resources/views/components/item.super.php');
        parent::tearDown();
    }
}
