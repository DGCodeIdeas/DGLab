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

    public function testParseYield()
    {
        $tokens = $this->lexer->tokenize('@yield("content")');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\YieldNode::class, $ast[0]);
    }

    public function testParseSection()
    {
        $tokens = $this->lexer->tokenize('@section("sidebar")Side@endsection');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\SectionNode::class, $ast[0]);
    }

    public function testParseFragment()
    {
        $tokens = $this->lexer->tokenize('@fragment("my-frag")Frag Content@endfragment');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\FragmentNode::class, $ast[0]);
        $this->assertEquals('my-frag', $ast[0]->id);
    }

    public function testParseExtends()
    {
        $tokens = $this->lexer->tokenize('@extends("layouts.app")');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode::class, $ast[0]);
        $this->assertEquals('layouts.app', $ast[0]->layout);
    }

    public function testParseSlot()
    {
        $tokens = $this->lexer->tokenize('<s:slot:header>Header Content</s:slot:header>');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\SlotNode::class, $ast[0]);
        $this->assertEquals('header', $ast[0]->name);
    }

    public function testParseOtherDirectives()
    {
        $tokens = $this->lexer->tokenize('@foreach($items as $item)Item@endforeach');
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode::class, $ast[0]);
        $this->assertEquals('foreach', $ast[0]->name);
    }

    public function testParseLifecycleBlocks()
    {
        $tokens = $this->lexer->tokenize("~setup { s } ~ ~mount { m } ~ ~rendered { r } ~ ~cleanup { c } ~");
        $ast = $this->parser->parse($tokens);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\SetupNode::class, $ast[0]);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\MountNode::class, $ast[1]);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\RenderedNode::class, $ast[2]);
        $this->assertInstanceOf(\DGLab\Services\Superpowers\Parser\Nodes\CleanupNode::class, $ast[3]);
    }
}
