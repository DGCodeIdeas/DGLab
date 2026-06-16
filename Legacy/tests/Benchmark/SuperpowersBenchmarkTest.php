<?php

namespace DGLab\Tests\Benchmark;

use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;

class SuperpowersBenchmarkTest extends BenchmarkTestCase
{
    private string $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = <<<'HTML'
<s:layout name="shell">
    <s:setup>
        $title = "Performance Test";
        $items = range(1, 20);
    </s:setup>

    <div class="container">
        <h1>{{ $title }}</h1>
        <ul>
            @foreach($items as $item)
                <li>Item {{ $item }}</li>
            @endforeach
        </ul>

        <s:ui_button type="primary" @click="console.log('clicked')">
            Click Me
        </s:ui_button>

        @if(count($items) > 10)
            <p>Lots of items!</p>
        @endif
    </div>
</s:layout>
HTML;
    }

    public function testLexerBenchmark()
    {
        $lexer = new Lexer();
        $this->benchmark('Superpowers Lexer', function() use ($lexer) {
            $lexer->tokenize($this->template);
        }, 100);

        // Assert it's reasonably fast (e.g., < 2ms for this small template)
        $this->assertExecutionTimeLessThan(2, function() use ($lexer) {
            $lexer->tokenize($this->template);
        });
    }

    public function testParserBenchmark()
    {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize($this->template);
        $parser = new Parser();

        $this->benchmark('Superpowers Parser', function() use ($parser, $tokens) {
            $parser->parse($tokens);
        }, 100);

        $this->assertExecutionTimeLessThan(5, function() use ($parser, $tokens) {
            $parser->parse($tokens);
        });
    }

    public function testCompilerBenchmark()
    {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize($this->template);
        $parser = new Parser();
        $ast = $parser->parse($tokens);
        $compiler = new Compiler();

        $this->benchmark('Superpowers Compiler', function() use ($compiler, $ast) {
            $compiler->compile($ast);
        }, 100);

        $this->assertExecutionTimeLessThan(5, function() use ($compiler, $ast) {
            $compiler->compile($ast);
        });
    }
}
