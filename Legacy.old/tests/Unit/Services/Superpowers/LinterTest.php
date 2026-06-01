<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Parser\Linter;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;
use DGLab\Tests\TestCase;

class LinterTest extends \DGLab\Tests\TestCase
{
    private Linter $linter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linter = new Linter();
    }

    public function test_valid_template_passes()
    {
        $content = "@if (true)\n<div>@foreach(items as item) {{item}} @endforeach</div>\n@endif";
        $this->assertTrue($this->linter->lint($content));
    }

    public function test_unclosed_directive_throws_exception()
    {
        $content = "@if (true)\n<div>Hello</div>";
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unclosed directive: @if");
        $this->linter->lint($content);
    }

    public function test_mismatched_directive_throws_exception()
    {
        $content = "@if (true)\n@endforeach";
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Mismatched closing directive: expected @endif, got @endforeach");
        $this->linter->lint($content);
    }

    public function test_unclosed_component_throws_exception()
    {
        $content = "<s:card>\n<p>Content</p>";
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unclosed component: <s:card>");
        $this->linter->lint($content);
    }
}
