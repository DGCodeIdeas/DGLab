<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Core\Application;

class SuperpowersTest extends \DGLab\Tests\TestCase
{
    private View $view;
    private string $vPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View(Application::getInstance());
        $this->vPath = Application::getInstance()->getBasePath() . '/resources/views/';
        @mkdir($this->vPath . 'components', 0777, true);
        @mkdir($this->vPath . 'layouts', 0777, true);
        Application::getInstance()->setConfig('superpowers.mode', 'interpreted');
    }

    public function test_basic_rendering()
    {
        file_put_contents($this->vPath . 'ut_basic.super.php', '<h1>{{ $title }}</h1>');
        $output = $this->view->render('ut_basic', ['title' => 'Hello World'], null);
        $this->assertEquals('<h1>Hello World</h1>', $output);
    }

    public function test_dot_notation()
    {
        file_put_contents($this->vPath . 'ut_dot.super.php', '<span>{{ $user.name }}</span>');
        $output = $this->view->render('ut_dot', ['user' => ['name' => 'Jules']], null);
        $this->assertEquals('<span>Jules</span>', $output);
    }

    public function test_null_safe_dot_notation()
    {
        file_put_contents($this->vPath . 'ut_null.super.php', '<span>{{ $user?.profile?.bio ?? "N/A" }}</span>');
        $output = $this->view->render('ut_null', ['user' => null], null);
        $this->assertEquals('<span>N/A</span>', trim($output));
    }

    public function test_setup_block()
    {
        file_put_contents($this->vPath . 'ut_setup.super.php', "~setup { \$name = strtoupper(\$name); } ~<h1>Hello {{ \$name }}</h1>");
        $output = $this->view->render('ut_setup', ['name' => 'jules'], null);
        $this->assertEquals('<h1>Hello JULES</h1>', trim($output));
    }

    public function test_directives()
    {
        $template = "@if(\$show) <div>Visible</div> @else <div>Hidden</div> @endif";
        file_put_contents($this->vPath . 'ut_if.super.php', $template);

        $output = $this->view->render('ut_if', ['show' => true], null);
        $this->assertStringContainsString('Visible', $output);

        $output = $this->view->render('ut_if', ['show' => false], null);
        $this->assertStringContainsString('Hidden', $output);
    }

    public function test_foreach_dot_notation()
    {
        file_put_contents($this->vPath . 'ut_foreach.super.php', "@foreach(\$users as \$u) <li>{{ \$u.name }}</li> @endforeach");
        $output = $this->view->render('ut_foreach', ['users' => [['name' => 'A'], ['name' => 'B']]], null);
        $this->assertStringContainsString('<li>A</li>', $output);
        $this->assertStringContainsString('<li>B</li>', $output);
    }

    public function test_components_basic()
    {
        file_put_contents($this->vPath . 'components/ut_alert.super.php', '<div class="alert">{{ $slot }}</div>');
        file_put_contents($this->vPath . 'ut_test_comp.super.php', '<s:ut_alert>Danger!</s:ut_alert>');

        $output = $this->view->render('ut_test_comp', [], null);
        $this->assertStringContainsString('<div class="alert">Danger!</div>', $output);
    }

    public function test_components_named_slots()
    {
        file_put_contents($this->vPath . 'components/ut_card.super.php', '<div class="card"><div class="header">{{ $title }}</div><div class="body">{{ $slot }}</div></div>');
        file_put_contents($this->vPath . 'ut_test_slots.super.php', '<s:ut_card><s:slot:title>My Title</s:slot:title>My Content</s:ut_card>');

        $output = $this->view->render('ut_test_slots', [], null);
        $this->assertStringContainsString('My Title', $output);
        $this->assertStringContainsString('My Content', $output);
    }

    public function test_recursive_components()
    {
        file_put_contents($this->vPath . 'components/ut_node.super.php', '<ul><li>{{ $name }} @if(!empty($children)) @foreach($children as $child) <s:ut_node :name="$child.name" :children="$child.children ?? []" /> @endforeach @endif</li></ul>');

        $data = [
            'name' => 'Root',
            'children' => [
                ['name' => 'Child 1'],
                ['name' => 'Child 2', 'children' => [['name' => 'Grandchild']]]
            ]
        ];

        file_put_contents($this->vPath . 'ut_recursive.super.php', '<s:ut_node :name="$name" :children="$children" />');
        $output = $this->view->render('ut_recursive', $data, null);

        $this->assertStringContainsString('Root', $output);
        $this->assertStringContainsString('Child 1', $output);
        $this->assertStringContainsString('Grandchild', $output);
    }

    public function test_lifecycle_hooks()
    {
        file_put_contents($this->vPath . 'components/ut_hook.super.php', "~setup { \$this->on('mount', function() { \$GLOBALS['ut_log'][] = 'mounted'; }); } ~");

        $GLOBALS['ut_log'] = [];
        file_put_contents($this->vPath . 'ut_hooks.super.php', '<s:ut_hook />');
        $this->view->render('ut_hooks', [], null);

        $this->assertContains('mounted', $GLOBALS['ut_log']);
    }

    public function test_component_based_layout()
    {
        file_put_contents($this->vPath . 'layouts/ut_app.super.php', '<html><body>{{ $slot }}</body></html>');
        file_put_contents($this->vPath . 'ut_layout.super.php', '<s:layouts.ut_app>Main Content</s:layouts.ut_app>');

        $output = $this->view->render('ut_layout', [], null);
        $this->assertStringContainsString('Main Content', $output);
    }

    public function test_legacy_layout_extends()
    {
        $this->markTestSkipped('Legacy PHP layout support decommissioned in Phase 10.');
    }

    public function test_compiled_rendering()
    {
        $cachePath = Application::getInstance()->getBasePath() . '/storage/cache/views';
        @array_map('unlink', glob("$cachePath/*"));

        file_put_contents($this->vPath . 'ut_compiled.super.php', '<h1>{{ $title }}</h1>');
        Application::getInstance()->setConfig('superpowers.mode', 'compiled');

        $output = $this->view->render('ut_compiled', ['title' => 'Compiled'], null);
        $this->assertEquals('<h1>Compiled</h1>', $output);
    }

    public function test_reactivity_metadata()
    {
        file_put_contents($this->vPath . 'components/ut_reactive.super.php', "~setup { \$count = 0; } ~ <button @click=\"\$count++\">{{ \$count }}</button>");
        file_put_contents($this->vPath . 'ut_reactive.super.php', '<s:ut_reactive />');

        $output = $this->view->render('ut_reactive', [], null);
        $this->assertStringContainsString('s-data', $output);
        $this->assertStringContainsString('s-id', $output);
    }

    public function test_global_directive()
    {
        file_put_contents($this->vPath . 'ut_global.super.php', "@global('user.name', 'name') Hello {{ \$name }}");

        $g = Application::getInstance()->get(\DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface::class);
        $g->set('user.name', 'Global Jules');

        $output = $this->view->render('ut_global', [], null);
        $this->assertStringContainsString('Hello Global Jules', $output);
    }

    protected function tearDown(): void
    {
        $files = glob($this->vPath . 'ut_*');
        foreach ($files as $f) {
            @unlink($f);
        }
        @unlink($this->vPath . 'components/ut_alert.super.php');
        @unlink($this->vPath . 'components/ut_card.super.php');
        @unlink($this->vPath . 'components/ut_node.super.php');
        @unlink($this->vPath . 'components/ut_hook.super.php');
        @unlink($this->vPath . 'components/ut_reactive.super.php');
        @unlink($this->vPath . 'layouts/ut_app.super.php');
        parent::tearDown();
    }
}
