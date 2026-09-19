<?php

namespace DGLab\Tests\Unit\Services\Superpowers\Transpiler;

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
        $this->assertEquals('$_SERVER', $this->transpiler->transpile('$_SERVER'));
        $this->assertEquals('$_SESSION', $this->transpiler->transpile('$_SESSION'));
        $this->assertEquals('$_GET', $this->transpiler->transpile('$_GET'));
        $this->assertEquals('$_POST', $this->transpiler->transpile('$_POST'));
        $this->assertEquals('$GLOBALS', $this->transpiler->transpile('$GLOBALS'));
        $this->assertEquals('$__ctx', $this->transpiler->transpile('$__ctx'));
        $this->assertEquals('$__persisted', $this->transpiler->transpile('$__persisted'));
        $this->assertEquals('$__g', $this->transpiler->transpile('$__g'));
    }

    public function testTranspileDotNotation()
    {
        $result = $this->transpiler->transpile('$user.name');
        $this->assertStringContainsString('Runtime::access(($__ctx[\'user\'] ?? null), \'name\', false)', $result);

        $result = $this->transpiler->transpile('$this.name');
        $this->assertStringContainsString('Runtime::access($this, \'name\', false)', $result);
    }

    public function testTranspileNullSafeDotNotation()
    {
        $result = $this->transpiler->transpile('$user?.name');
        $this->assertStringContainsString('Runtime::access(($__ctx[\'user\'] ?? null), \'name\', true)', $result);

        $result = $this->transpiler->transpile('$this?.name');
        $this->assertStringContainsString('Runtime::access($this, \'name\', true)', $result);
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
        $this->assertEquals('"keep $name as is"', $this->transpiler->transpile('"keep $name as is"'));
    }
}
