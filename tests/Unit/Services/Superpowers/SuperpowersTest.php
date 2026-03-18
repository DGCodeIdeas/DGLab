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
        putenv("SUPERPHP_MODE=interpreted");

        $this->view = new View();

        Application::getInstance()->setConfig('app.debug', true);
        Application::getInstance()->setConfig('superpowers.mode', 'interpreted');
        Application::getInstance()->setConfig('superpowers.cache_path', dirname(__DIR__, 3) . '/storage/cache/views');
        Application::getInstance()->setConfig('superpowers.reactivity.inject_runtime', false); // Disable for unit tests to avoid noise

        @mkdir('resources/views/components', 0777, true);
        @mkdir('resources/views/layouts', 0777, true);
        @mkdir('storage/cache/views', 0777, true);
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

    public function test_component_based_layout()
    {
        file_put_contents('resources/views/layouts/app.super.php', "<html><body>{!! \$slot !!}</body></html>");
        file_put_contents('resources/views/home.super.php', "<s:layout:app>Welcome</s:layout:app>");
        $output = $this->view->render('home', [], null);
        $this->assertEquals("<html><body>Welcome</body></html>", str_replace(["\n", " "], "", $output));
    }

    public function test_legacy_layout_extends()
    {
        file_put_contents('resources/views/layouts/legacy.php', "START <?php \$this->yield('content'); ?> END");
        file_put_contents('resources/views/legacy_view.super.php', "@extends('layouts.legacy') @section('content')Hello@endsection");
        $output = $this->view->render('legacy_view', [], null);
        $this->assertEquals("START Hello END", trim(str_replace("\n", "", $output)));
    }

    public function test_compiled_rendering()
    {
        Application::getInstance()->setConfig('superpowers.mode', 'compiled');
        file_put_contents('resources/views/test_compiled.super.php', '<h1>{{ $title }}</h1>');

        $cachePath = Application::config('superpowers.cache_path');
        @mkdir($cachePath, 0777, true);
        array_map('unlink', glob("$cachePath/*"));

        $output = $this->view->render('test_compiled', ['title' => 'Compiled'], null);
        $this->assertEquals('<h1>Compiled</h1>', $output);

        $this->assertNotEmpty(glob("$cachePath/test_compiled.super.php.*.php"));
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
        @unlink('resources/views/home.super.php');
        @unlink('resources/views/legacy_view.super.php');
        @unlink('resources/views/test_compiled.super.php');
        @unlink('resources/views/components/card.super.php');
        @unlink('resources/views/components/modal.super.php');
        @unlink('resources/views/components/item.super.php');
        @unlink('resources/views/layouts/app.super.php');
        @unlink('resources/views/layouts/legacy.php');
        parent::tearDown();
    }
}
