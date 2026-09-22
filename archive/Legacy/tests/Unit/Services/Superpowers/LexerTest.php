<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Lexer\Token;
use DGLab\Tests\TestCase;

class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new Lexer();
    }

    public function testTokenizeText()
    {
        $tokens = $this->lexer->tokenize('Hello World');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_TEXT, $tokens[0]->type);
        $this->assertEquals('Hello World', $tokens[0]->value);
    }

    public function testTokenizeExpressionEscaped()
    {
        $tokens = $this->lexer->tokenize('{{ $myVar }}');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_EXPRESSION_ESCAPED, $tokens[0]->type);
        $this->assertEquals('$myVar', $tokens[0]->value);
    }

    public function testTokenizeExpressionRaw()
    {
        $tokens = $this->lexer->tokenize('{!! $myVar !!}');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_EXPRESSION_RAW, $tokens[0]->type);
        $this->assertEquals('$myVar', $tokens[0]->value);
    }

    public function testTokenizeDirectives()
    {
        $tokens = $this->lexer->tokenize('@if($cond)');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_DIRECTIVE, $tokens[0]->type);
        $this->assertEquals('@if($cond)', $tokens[0]->value);
    }

    public function testTokenizeComponentSelfClosing()
    {
        $tokens = $this->lexer->tokenize('<s:ui.button variant="primary" />');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_COMPONENT_SELF_CLOSING, $tokens[0]->type);
        $this->assertEquals('<s:ui.button variant="primary" />', $tokens[0]->value);
    }

    public function testTokenizeComponentOpenClose()
    {
        $tokens = $this->lexer->tokenize('<s:card>Content</s:card>');
        $this->assertCount(3, $tokens);
        $this->assertEquals(Token::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertEquals(Token::T_TEXT, $tokens[1]->type);
        $this->assertEquals(Token::T_COMPONENT_CLOSE, $tokens[2]->type);
    }

    public function testTokenizeLifecycleBlocks()
    {
        $template = "~setup { \$foo = 'bar'; } ~";
        $tokens = $this->lexer->tokenize($template);
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_SETUP_BLOCK, $tokens[0]->type);
        $this->assertStringContainsString("\$foo = 'bar';", $tokens[0]->value);
    }

    public function testTokenizeNestedLifecycleBlocks()
    {
        $template = "~setup { if(true) { \$bar = 1; } } ~";
        $tokens = $this->lexer->tokenize($template);
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_SETUP_BLOCK, $tokens[0]->type);
        $this->assertStringContainsString("if(true) { \$bar = 1; }", $tokens[0]->value);
    }

    public function testTokenizeReactiveTag()
    {
        $tokens = $this->lexer->tokenize('<div @click="run">');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_REACTIVE_TAG, $tokens[0]->type);
        $this->assertEquals('<div @click="run">', $tokens[0]->value);
    }

    public function testTokenizeGenericCloseTag()
    {
        $tokens = $this->lexer->tokenize('</div>');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_TAG_CLOSE, $tokens[0]->type);
        $this->assertEquals('</div>', $tokens[0]->value);
    }

    public function testLineNumberTracking()
    {
        $template = "Line 1\nLine 2\n{{ \$myVar }}\nLine 4";
        $tokens = $this->lexer->tokenize($template);

        $this->assertEquals(1, $tokens[0]->line, "Token 0 line should be 1");

        $expToken = null;
        foreach($tokens as $t) {
            if ($t->type === Token::T_EXPRESSION_ESCAPED) {
                $expToken = $t;
                break;
            }
        }

        $this->assertNotNull($expToken);
        $this->assertEquals(3, $expToken->line, "Expression token should be on line 3");
    }

    public function testTokenizeAllLifecycleBlocks()
    {
        $template = "~setup { s } ~ ~mount { m } ~ ~rendered { r } ~ ~cleanup { c } ~";
        $tokens = $this->lexer->tokenize($template);
        $this->assertCount(4, $tokens);
        $this->assertEquals(Token::T_SETUP_BLOCK, $tokens[0]->type);
        $this->assertEquals(Token::T_MOUNT_BLOCK, $tokens[1]->type);
        $this->assertEquals(Token::T_RENDERED_BLOCK, $tokens[2]->type);
        $this->assertEquals(Token::T_CLEANUP_BLOCK, $tokens[3]->type);
    }

    public function testTokenizeLegacyLifecycle()
    {
        $template = "~setup my legacy code ~";
        $tokens = $this->lexer->tokenize($template);
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::T_SETUP_BLOCK, $tokens[0]->type);
        $this->assertEquals("my legacy code", $tokens[0]->value);
    }
}
