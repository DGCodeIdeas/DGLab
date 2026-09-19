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
        Application::getInstance()->setConfig('superpowers.check_dependencies', true);
        Application::getInstance()->setConfig('superpowers.linter.on_render', true);
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

        // Test without body
        file_put_contents($this->vPath . 'ut_cov_no_react.super.php', '<div>Hello</div>');
        $output = $this->view->render('ut_cov_no_react', [], null);
        $this->assertStringNotContainsString('superpowers.js', $output);

        // Test with reactivity disabled
        Application::getInstance()->setConfig('superpowers.reactivity.enabled', false);
        file_put_contents($this->vPath . 'ut_cov_react_off.super.php', '<body>Hello</body>');
        $output = $this->view->render('ut_cov_react_off', [], null);
        $this->assertStringNotContainsString('superpowers.js', $output);
        Application::getInstance()->setConfig('superpowers.reactivity.enabled', true);
    }

    public function testDependencyChecking()
    {
        file_put_contents($this->vPath . 'components/ut_cov_child.super.php', 'Child V1');
        file_put_contents($this->vPath . 'ut_cov_parent.super.php', '<s:ut_cov_child />');

        // First render to cache
        $this->view->render('ut_cov_parent', [], null);

        // Touch child to trigger recompile
        file_put_contents($this->vPath . 'components/ut_cov_child.super.php', 'Child V2');
        touch($this->vPath . 'components/ut_cov_child.super.php', time() + 10);

        $output = $this->view->render('ut_cov_parent', [], null);
        $this->assertStringContainsString('Child V2', $output);

        // Test missing dependency file in deps
        $cachePath = Application::getInstance()->getBasePath() . '/storage/cache/views';
        $hash = md5($this->vPath . 'ut_cov_parent.super.php' . filemtime($this->vPath . 'ut_cov_parent.super.php'));
        $depsFile = $cachePath . '/ut_cov_parent.super.php.' . $hash . '.php.deps';
        if (file_exists($depsFile)) {
            file_put_contents($depsFile, json_encode(['non_existent_view']));
            // Should not crash and should re-render or just skip missing dep
            $this->view->render('ut_cov_parent', [], null);
        }
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
        $this->assertEquals('$_SESSION', $transpiler->transpile('$_SESSION'));
        $this->assertEquals('$_GET', $transpiler->transpile('$_GET'));
        $this->assertEquals('$_POST', $transpiler->transpile('$_POST'));
        $this->assertEquals('$GLOBALS', $transpiler->transpile('$GLOBALS'));

        // Protected variables
        $this->assertEquals('$__ctx', $transpiler->transpile('$__ctx'));
        $this->assertEquals('$__persisted', $transpiler->transpile('$__persisted'));
        $this->assertEquals('$__g', $transpiler->transpile('$__g'));

        // Strings
        $this->assertEquals("'keep \$name'", $transpiler->transpile("'keep \$name'"));
        $this->assertEquals('"keep \$name"', $transpiler->transpile('"keep \$name"'));

        // Validate
        $this->assertTrue($transpiler->validate('$user.name'));
    }

    public function testPrivateMethodsViaReflection()
    {
        $refl = new \ReflectionClass($this->engine);

        // extractViewName
        $m = $refl->getMethod('extractViewName');
        $m->setAccessible(true);
        $testPath = Application::getInstance()->getBasePath() . '/resources/views/test.super.php';
        $this->assertEquals('test', $m->invoke($this->engine, $testPath));

        // Subdirectory test
        $subPath = Application::getInstance()->getBasePath() . '/resources/views/auth/login.super.php';
        $this->assertEquals('auth.login', $m->invoke($this->engine, $subPath));

        // processReactivity branches
        $m = $refl->getMethod('processReactivity');
        $m->setAccessible(true);

        // Already injected
        $input = '<body><script src="/assets/js/superpowers.js"></script></body>';
        $this->assertEquals($input, $m->invoke($this->engine, $input));

        // No body
        $input = '<div></div>';
        $this->assertEquals($input, $m->invoke($this->engine, $input));

        // Debug overlay
        Application::getInstance()->setConfig('superpowers.debug_overlay.enabled', true);
        // Note: we can't easily mock defined('PHPUNIT_RUNNING'), but we can check if it tries to inject something
        $input = '<body></body>';
        $output = $m->invoke($this->engine, $input);
        // If PHPUNIT_RUNNING is defined (which it is), debug overlay won't be injected by the engine's check
    }

    protected function tearDown(): void
    {
        @unlink($this->vPath . 'ut_cov_react.super.php');
        @unlink($this->vPath . 'ut_cov_no_react.super.php');
        @unlink($this->vPath . 'ut_cov_react_off.super.php');
        @unlink($this->vPath . 'ut_cov_parent.super.php');
        @unlink($this->vPath . 'components/ut_cov_child.super.php');
        parent::tearDown();
    }
}
