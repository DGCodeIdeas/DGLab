<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;
use DGLab\Tests\TestCase;

class SyntaxErrorTest extends \DGLab\Tests\TestCase
{
    private Lexer $lexer;
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new Lexer();
        $this->parser = new Parser();
    }

    public function test_missing_endforeach_throws_syntax_exception()
    {
        $content = "@foreach (items as item)\n<div>{{item}}</div>";
        $tokens = $this->lexer->tokenize($content);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing expected terminator: @endforeach");

        $this->parser->parse($tokens);
    }

    public function test_unexpected_closing_tag_throws_syntax_exception()
    {
        $content = "</div>\n</s:component>";
        $tokens = $this->lexer->tokenize($content);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unexpected closing tag: </s:component>");

        $this->parser->parse($tokens);
    }
}
