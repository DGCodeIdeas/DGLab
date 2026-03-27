<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;
use DGLab\Tests\TestCase;

class ParserTest extends TestCase
{
    private Lexer $lexer;
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new Lexer();
        $this->parser = new Parser();
    }

    public function testParseText()
    {
        $tokens = $this->lexer->tokenize('Hello');
        $ast = $this->parser->parse($tokens);

        $this->assertCount(1, $ast);
        $this->assertInstanceOf(TextNode::class, $ast[0]);
        $this->assertEquals('Hello', $ast[0]->content);
    }

    public function testParseExpression()
    {
        $tokens = $this->lexer->tokenize('{{ $var }}');
        $ast = $this->parser->parse($tokens);

        $this->assertCount(1, $ast);
        $this->assertInstanceOf(ExpressionNode::class, $ast[0]);
        $this->assertEquals('$var', $ast[0]->expression);
        $this->assertTrue($ast[0]->escaped);
    }

    public function testParseDirective()
    {
        $tokens = $this->lexer->tokenize('@if(true)Yes@endif');
        $ast = $this->parser->parse($tokens);

        $this->assertCount(1, $ast);
        $this->assertInstanceOf(DirectiveNode::class, $ast[0]);
        $this->assertEquals('if', $ast[0]->name);
        $this->assertCount(1, $ast[0]->children);
        $this->assertInstanceOf(TextNode::class, $ast[0]->children[0]);
    }

    public function testParseComponent()
    {
        $tokens = $this->lexer->tokenize('<s:ui.button variant="primary">Click</s:ui.button>');
        $ast = $this->parser->parse($tokens);

        $this->assertCount(1, $ast);
        $this->assertInstanceOf(ComponentNode::class, $ast[0]);
        $this->assertEquals('ui.button', $ast[0]->tagName);
        $this->assertArrayHasKey('variant', $ast[0]->props);
        $this->assertEquals('primary', $ast[0]->props['variant']['value']);
        $this->assertCount(1, $ast[0]->children);
    }

    public function testParseReactiveNode()
    {
        $tokens = $this->lexer->tokenize('<div @click="doSomething">Content</div>');
        $ast = $this->parser->parse($tokens);

        $this->assertCount(1, $ast);
        $this->assertInstanceOf(ReactiveNode::class, $ast[0]);
        $this->assertEquals('div', $ast[0]->tagName);
        $this->assertArrayHasKey('click', $ast[0]->reactiveAttributes);
    }

    public function testParseUnclosedDirectiveThrowsException()
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing expected terminator: @endif');

        $tokens = $this->lexer->tokenize('@if(true)No closing');
        $this->parser->parse($tokens);
    }

    public function testParseUnexpectedClosingTagThrowsException()
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected closing tag');

        $tokens = $this->lexer->tokenize('</s:unknown>');
        $this->parser->parse($tokens);
    }
}
