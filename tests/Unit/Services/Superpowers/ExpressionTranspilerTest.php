<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;
use DGLab\Tests\TestCase;

class ExpressionTranspilerTest extends TestCase
{
    private ExpressionTranspiler $transpiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transpiler = new ExpressionTranspiler();
    }

    public function testTranspileSimpleVariable()
    {
        $this->assertEquals('($__ctx[\'name\'] ?? null)', $this->transpiler->transpile('$name'));
    }

    public function testTranspileReservedVariables()
    {
        $this->assertEquals('$this', $this->transpiler->transpile('$this'));
        $this->assertEquals('$_GET', $this->transpiler->transpile('$_GET'));
        $this->assertEquals('$__ctx', $this->transpiler->transpile('$__ctx'));
    }

    public function testTranspileDotNotation()
    {
        // $user.name -> \DGLab\Services\Superpowers\Runtime\Runtime::access(($__ctx['user'] ?? null), 'name', false)
        $result = $this->transpiler->transpile('$user.name');
        $this->assertStringContainsString('Runtime::access(($__ctx[\'user\'] ?? null), \'name\', false)', $result);
    }

    public function testTranspileNullSafeDotNotation()
    {
        // $user?.name -> \DGLab\Services\Superpowers\Runtime\Runtime::access(($__ctx['user'] ?? null), 'name', true)
        $result = $this->transpiler->transpile('$user?.name');
        $this->assertStringContainsString('Runtime::access(($__ctx[\'user\'] ?? null), \'name\', true)', $result);
    }

    public function testTranspileDeepDotNotation()
    {
        $result = $this->transpiler->transpile('$user.profile.name');
        $this->assertStringContainsString("'profile', false)", $result);
        $this->assertStringContainsString("'name', false)", $result);
    }

    public function testValidateValidExpression()
    {
        $this->assertTrue($this->transpiler->validate('$name'));
        $this->assertTrue($this->transpiler->validate('$user.profile?.email'));
    }

    public function testTranspileDoesNotTouchStrings()
    {
        $this->assertEquals("'keep \$name as is'", $this->transpiler->transpile("'keep \$name as is'"));
    }
}
