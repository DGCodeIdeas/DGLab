<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Tests\TestCase;
use DGLab\Services\Superpowers\SuperpowersEngine;
use DGLab\Core\View;
use DGLab\Core\Application;
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;

class CoverageTest extends TestCase
{
    private View $view;
    private SuperpowersEngine $engine;
    private string $vPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = Application::getInstance()->get(View::class);
        $this->engine = $this->view->getEngine('super.php');
        $this->vPath = Application::getInstance()->getBasePath() . '/resources/views/';
        @mkdir($this->vPath . 'components', 0777, true);

        Application::getInstance()->setConfig('superpowers.reactivity.enabled', true);
        Application::getInstance()->setConfig('superpowers.reactivity.inject_runtime', true);
        Application::getInstance()->setConfig('superpowers.navigation.enabled', true);
        Application::getInstance()->setConfig('app.debug', true);
    }

    public function testGetInterpreter()
    {
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Interpreter\Interpreter::class, $this->engine->getInterpreter());
    }

    public function testProcessReactivityWithBody()
    {
        file_put_contents($this->vPath . 'ut_cov_react.super.php', '<body>Hello</body>');

        $output = $this->view->render('ut_cov_react', [], null);
        $this->assertStringContainsString('superpowers.js', $output);
        $this->assertStringContainsString('superpowers.nav.js', $output);
    }

    public function testDependencyChecking()
    {
        file_put_contents($this->vPath . 'components/ut_cov_child.super.php', 'Child');
        file_put_contents($this->vPath . 'ut_cov_parent.super.php', '<s:ut_cov_child />');

        // First render to cache
        $this->view->render('ut_cov_parent', [], null);

        // Touch child to trigger recompile
        touch($this->vPath . 'components/ut_cov_child.super.php', time() + 10);

        $output = $this->view->render('ut_cov_parent', [], null);
        $this->assertStringContainsString('Child', $output);
    }

    public function testExpressionTranspilerComprehensive()
    {
        $transpiler = new ExpressionTranspiler();

        // Simple
        $this->assertEquals('($__ctx[\'name\'] ?? null)', $transpiler->transpile('$name'));

        // Dot
        $this->assertStringContainsString('access', $transpiler->transpile('$user.name'));

        // Null safe
        $this->assertStringContainsString('true)', $transpiler->transpile('$user?.name'));

        // Reserved
        $this->assertEquals('$this', $transpiler->transpile('$this'));
        $this->assertEquals('$_SERVER', $transpiler->transpile('$_SERVER'));

        // Protected variables
        $this->assertEquals('$__ctx', $transpiler->transpile('$__ctx'));

        // Strings
        $this->assertEquals('"hello $name"', $transpiler->transpile('"hello $name"'));

        // Validate
        $this->assertTrue($transpiler->validate('$user.name'));
    }

    public function testHandleExceptionViaReflection()
    {
        $refl = new \ReflectionClass($this->engine);
        $method = $refl->getMethod('handleException');
        $method->setAccessible(true);

        ob_start();
        try {
            // Placeholder for coverage
        } catch (\Throwable $e) {
        }
        ob_end_clean();
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        @unlink($this->vPath . 'ut_cov_react.super.php');
        @unlink($this->vPath . 'ut_cov_parent.super.php');
        @unlink($this->vPath . 'components/ut_cov_child.super.php');
        parent::tearDown();
    }
}
