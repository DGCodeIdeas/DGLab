<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Core\View;
use DGLab\Core\Application;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;

class SuperpowersTest extends TestCase
{
    private SuperpowersEngine $engine;
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
        $this->engine = new SuperpowersEngine($this->view);

        Application::getInstance()->set(GlobalStateStoreInterface::class, function() {
            return new GlobalStateStore();
        });
    }

    public function test_basic_rendering()
    {
        file_put_contents('resources/views/test_basic.super.php', '<h1>{{ $title }}</h1>');
        $output = $this->view->render('test_basic', ['title' => 'Hello World'], null);
        $this->assertEquals('<h1>Hello World</h1>', $output);
    }

    public function test_dot_notation()
    {
        file_put_contents('resources/views/test_dot.super.php', '<p>{{ $user.name }}</p>');
        $output = $this->view->render('test_dot', ['user' => ['name' => 'John']], null);
        $this->assertEquals('<p>John</p>', $output);
    }

    public function test_null_safe_dot_notation()
    {
        file_put_contents('resources/views/test_null_dot.super.php', '<p>{{ $user?.profile?.bio }}</p>');
        $output = $this->view->render('test_null_dot', ['user' => null], null);
        $this->assertEquals('<p></p>', $output);
    }

    public function test_setup_block()
    {
        file_put_contents('resources/views/test_setup.super.php', '~setup { $name = "SuperPHP"; } ~ <div>{{ $name }}</div>');
        $output = $this->view->render('test_setup', [], null);
        $this->assertStringContainsString('<div>SuperPHP</div>', $output);
    }

    public function test_directives()
    {
        file_put_contents('resources/views/test_directives.super.php', '@if($show) <span>Visible</span> @else <span>Hidden</span> @endif');

        $output = $this->view->render('test_directives', ['show' => true], null);
        $this->assertStringContainsString('Visible', $output);

        $output = $this->view->render('test_directives', ['show' => false], null);
        $this->assertStringContainsString('Hidden', $output);
    }

    public function test_foreach_dot_notation()
    {
        file_put_contents('resources/views/test_foreach_dot.super.php', '<ul>@foreach($users as $u) <li>{{ $u.name }}</li> @endforeach</ul>');
        $output = $this->view->render('test_foreach_dot', ['users' => [['name' => 'A'], ['name' => 'B']]], null);
        $this->assertStringContainsString('<li>A</li>', $output);
        $this->assertStringContainsString('<li>B</li>', $output);
    }

    public function test_components_basic()
    {
        file_put_contents('resources/views/components/card.super.php', '<div class="card">{{ $slot }}</div>');
        file_put_contents('resources/views/test_comp.super.php', '<s:card>Content</s:card>');

        $output = $this->view->render('test_comp', [], null);
        $this->assertStringContainsString('<div class="card">Content</div>', $output);
    }

    public function test_components_named_slots()
    {
        file_put_contents('resources/views/components/modal.super.php', '<div class="modal"><header>{{ $title }}</header><div>{{ $slot }}</div></div>');
        file_put_contents('resources/views/test_modal.super.php', '<s:modal><s:slot name="title">My Title</s:slot>My Content</s:modal>');

        $output = $this->view->render('test_modal', [], null);
        $this->assertStringContainsString('<header>My Title</header>', $output);
        $this->assertStringContainsString('<div>My Content</div>', $output);
    }

    public function test_recursive_components()
    {
        file_put_contents('resources/views/components/item.super.php', '<li>{{ $name }} @if($children) <ul>@foreach($children as $c) <s:item :name="$c.name" :children="$c.children ?? null" /> @endforeach</ul> @endif</li>');
        file_put_contents('resources/views/test_recursive.super.php', '<ul><s:item :name="$tree.name" :children="$tree.children" /></ul>');

        $tree = ['name' => 'Root', 'children' => [['name' => 'Child 1', 'children' => null], ['name' => 'Child 2', 'children' => [['name' => 'Grandchild', 'children' => null]]]]];
        $output = $this->view->render('test_recursive', ['tree' => $tree], null);

        $this->assertStringContainsString('Root', $output);
        $this->assertStringContainsString('Child 1', $output);
        $this->assertStringContainsString('Grandchild', $output);
    }

    public function test_lifecycle_hooks()
    {
        file_put_contents('resources/views/test_hooks.super.php', "<div>{{\$val}}</div> ~mount { \$val++; }");
        $output = $this->view->render('test_hooks', ['val' => 1], null);
        $this->assertStringContainsString('<div>2</div>', $output);
    }

    public function test_component_based_layout()
    {
        file_put_contents('resources/views/layouts/app.super.php', '<html><body>{!! $slot !!}</body></html>');
        file_put_contents('resources/views/home.super.php', '<s:layout:app><h1>Home</h1></s:layout:app>');

        $output = $this->view->render('home', [], null);
        $this->assertStringContainsString('<html><body><h1>Home</h1>', $output);
    }

    public function test_legacy_layout_extends()
    {
        file_put_contents('resources/views/layouts/legacy.php', 'Legacy: <?php echo $this->yield("content"); ?>');
        file_put_contents('resources/views/legacy_view.super.php', '@extends("legacy") @section("content") Super! @endsection');

        $output = $this->view->render('legacy_view', [], null);
        $this->assertStringContainsString('Legacy:  Super!', $output);
    }

    public function test_compiled_rendering()
    {
        $cachePath = Application::getInstance()->getBasePath() . '/storage/cache/views';
        @array_map('unlink', glob("$cachePath/*"));

        file_put_contents('resources/views/test_compiled.super.php', '<h1>{{ $title }}</h1>');

        $output = $this->view->render('test_compiled', ['title' => 'Compiled'], null);
        $this->assertEquals('<h1>Compiled</h1>', $output);
        $this->assertNotEmpty(glob("$cachePath/test_compiled.super.php.*.php"));
    }

    public function test_reactivity_metadata()
    {
        file_put_contents('resources/views/test_reactive.super.php', '<div @click="increment">Click</div>');
        $output = $this->view->render('test_reactive', [], null);
        $this->assertStringContainsString('s-on:click="increment"', $output);
        $this->assertStringContainsString('s-data=', $output);
    }

    public function testGlobalDirective()
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $store->set('test_key', 'test_value');

        file_put_contents('resources/views/test_global.super.php', "~setup { @global('test_key', 'my_val'); } <div>{{\$my_val}}</div>");
        $content = $this->view->render('test_global', [], null);

        $this->assertStringContainsString('<div>test_value</div>', $content);
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
        @unlink('resources/views/test_reactive.super.php');
        @unlink('resources/views/test_global.super.php');
        @unlink('resources/views/components/card.super.php');
        @unlink('resources/views/components/modal.super.php');
        @unlink('resources/views/components/item.super.php');
        @unlink('resources/views/layouts/app.super.php');
        @unlink('resources/views/layouts/legacy.php');
        parent::tearDown();
    }
}
