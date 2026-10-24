<?php
declare(strict_types=1);

namespace SovereignStack\Core\NeoSPX\Tests\Unit\Lexer;

use SovereignStack\Core\NeoSPX\Lexer\NeoSPXLexer;
use SovereignStack\Core\NeoSPX\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

/**
 * NeoSPX Lexer unit tests.
 *
 * Test IDs: LEX-001 through LEX-XXX
 * Linked to grammar sections in NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md
 */
class NeoSPXLexerTest extends TestCase
{
    // LEX-001: Simple component tag
    public function testSimpleComponentTag(): void
    {
        $lexer = new NeoSPXLexer('<s:ui:button label="Click me" />');
        $tokens = $lexer->tokenize();
        $this->assertCount(6, $tokens); // open, attr_name, equals, string, self_close, eof
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_ATTR_NAME, $tokens[1]->type);
        $this->assertSame(TokenType::T_ATTR_EQUALS, $tokens[2]->type);
        $this->assertSame(TokenType::T_STRING, $tokens[3]->type);
        $this->assertSame(TokenType::T_SELF_CLOSE, $tokens[4]->type);
    }

    // LEX-002: Paired component tags
    public function testPairedComponentTags(): void
    {
        $lexer = new NeoSPXLexer('<s:ui:button>Click me</s:ui:button>');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[1]->type);
        $this->assertSame(TokenType::T_HTML, $tokens[2]->type);
        $this->assertSame(TokenType::T_COMPONENT_CLOSE, $tokens[3]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[4]->type);
    }

    // LEX-003: Raw HTML passthrough
    public function testRawHtmlPassthrough(): void
    {
        $lexer = new NeoSPXLexer('<div class="container"><p>Hello</p></div>');
        $tokens = $lexer->tokenize();
        $this->assertCount(2, $tokens); // T_HTML + T_EOF
        $this->assertSame(TokenType::T_HTML, $tokens[0]->type);
    }

    // LEX-004: Output expression
    public function testOutputExpression(): void
    {
        $lexer = new NeoSPXLexer('Hello, {{ $name }}!');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_HTML, $tokens[0]->type);
        $this->assertSame(TokenType::T_OUTPUT_OPEN, $tokens[1]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[2]->type);
        $this->assertSame('$name', $tokens[2]->value);
        $this->assertSame(TokenType::T_OUTPUT_CLOSE, $tokens[3]->type);
    }

    // LEX-005: Raw output expression
    public function testRawOutputExpression(): void
    {
        $lexer = new NeoSPXLexer('{!! $html !!}');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_RAW_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
        $this->assertSame(TokenType::T_RAW_CLOSE, $tokens[2]->type);
    }

    // LEX-006: @if directive
    public function testIfDirective(): void
    {
        $lexer = new NeoSPXLexer('@if ($user->isAdmin())');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-007: @foreach directive
    public function testForeachDirective(): void
    {
        $lexer = new NeoSPXLexer('@foreach ($items as $item)');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-008: @for directive
    public function testForDirective(): void
    {
        $lexer = new NeoSPXLexer('@for ($i = 0; $i < 10; $i++)');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-009: @while directive
    public function testWhileDirective(): void
    {
        $lexer = new NeoSPXLexer('@while ($hasMore)');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-010: @switch directive
    public function testSwitchDirective(): void
    {
        $lexer = new NeoSPXLexer('@switch ($status)');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-011: @break directive
    public function testBreakDirective(): void
    {
        $lexer = new NeoSPXLexer('@break');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
    }

    // LEX-012: @continue directive
    public function testContinueDirective(): void
    {
        $lexer = new NeoSPXLexer('@continue');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
    }

    // LEX-013: @persist directive
    public function testPersistDirective(): void
    {
        $lexer = new NeoSPXLexer('@persist $counter');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-014: @global directive
    public function testGlobalDirective(): void
    {
        $lexer = new NeoSPXLexer('@global $user');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[1]->type);
    }

    // LEX-015: @include directive
    public function testIncludeDirective(): void
    {
        $lexer = new NeoSPXLexer("@include 'partials/header.neospx'");
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_STRING, $tokens[1]->type);
    }

    // LEX-016: @php directive
    public function testPhpDirective(): void
    {
        $lexer = new NeoSPXLexer("@php\n  \$x = 1;\n@endphp");
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_CODE, $tokens[1]->type);
    }

    // LEX-017: ~setup block
    public function testSetupBlock(): void
    {
        $lexer = new NeoSPXLexer("~setup\n  \$x = 1;\n~setup");
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_SETUP_START, $tokens[0]->type);
        $this->assertSame(TokenType::T_PHP_CODE, $tokens[1]->type);
        $this->assertSame(TokenType::T_SETUP_END, $tokens[2]->type);
    }

    // LEX-018: Source location tracking
    public function testSourceLocationTracking(): void
    {
        $lexer = new NeoSPXLexer("<s:ui:button />\n<p>Hello</p>");
        $tokens = $lexer->tokenize();
        $this->assertSame(1, $tokens[0]->location->line);
        $this->assertSame(1, $tokens[0]->location->column);
        $this->assertSame(0, $tokens[0]->location->offset);
    }

    // LEX-019: Unknown directive produces error
    public function testUnknownDirectiveProducesError(): void
    {
        $lexer = new NeoSPXLexer('@unknown $foo');
        $lexer->tokenize();
        $errors = $lexer->getErrors();
        $this->assertNotEmpty($errors);
    }

    // LEX-020: Unterminated ~setup produces error
    public function testUnterminatedSetupProducesError(): void
    {
        $lexer = new NeoSPXLexer("~setup\n  \$x = 1;");
        $lexer->tokenize();
        $errors = $lexer->getErrors();
        $this->assertNotEmpty($errors);
    }

    // LEX-021: Nested component tags
    public function testNestedComponentTags(): void
    {
        $lexer = new NeoSPXLexer('<s:layout:page><s:ui:header title="My Page" /></s:layout:page>');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[1]->type);
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[2]->type);
        $this->assertSame(TokenType::T_ATTR_NAME, $tokens[3]->type);
        $this->assertSame(TokenType::T_ATTR_EQUALS, $tokens[4]->type);
        $this->assertSame(TokenType::T_STRING, $tokens[5]->type);
        $this->assertSame(TokenType::T_SELF_CLOSE, $tokens[6]->type);
        $this->assertSame(TokenType::T_COMPONENT_CLOSE, $tokens[7]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[8]->type);
    }

    // LEX-022: HTML passthrough (standard HTML tags, comments, text)
    public function testHtmlPassthrough(): void
    {
        $lexer = new NeoSPXLexer('<!-- comment --><div class="container"><p>Hello</p></div>');
        $tokens = $lexer->tokenize();
        $this->assertCount(2, $tokens); // T_HTML + T_EOF
        $this->assertSame(TokenType::T_HTML, $tokens[0]->type);
    }

    // LEX-023: Empty template
    public function testEmptyTemplate(): void
    {
        $lexer = new NeoSPXLexer('');
        $tokens = $lexer->tokenize();
        $this->assertCount(1, $tokens); // T_EOF only
        $this->assertSame(TokenType::T_EOF, $tokens[0]->type);
    }

    // LEX-024: Multiple top-level elements
    public function testMultipleTopLevelElements(): void
    {
        $lexer = new NeoSPXLexer('<s:a /><s:b /><s:c />');
        $tokens = $lexer->tokenize();
        // 3 self-closing components = 4 tokens each (open, name, self_close) + EOF
        $this->assertGreaterThan(3, count($tokens));
    }

    // LEX-025: Attribute with PHP expression (unquoted)
    public function testAttributeWithPhpExpression(): void
    {
        $lexer = new NeoSPXLexer('<s:user:profile name=$user->name age=$user->age />');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_ATTR_NAME, $tokens[1]->type);
        $this->assertSame('name', $tokens[1]->value);
        $this->assertSame(TokenType::T_ATTR_EQUALS, $tokens[2]->type);
        $this->assertSame(TokenType::T_PHP_EXPR, $tokens[3]->type);
        $this->assertSame('$user->name', $tokens[3]->value);
    }

    // LEX-026: Mismatched component tags produce tokens (tag matching is parser concern)
    public function testMismatchedComponentTagsProduceTokens(): void
    {
        $lexer = new NeoSPXLexer('<s:ui:button></s:ui:link>');
        $tokens = $lexer->tokenize();
        $this->assertSame(TokenType::T_COMPONENT_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[1]->type);
        $this->assertSame(TokenType::T_COMPONENT_CLOSE, $tokens[2]->type);
        $this->assertSame(TokenType::T_TAG_CLOSE, $tokens[3]->type);
    }

    // LEX-027: Unterminated @php produces error
    public function testUnterminatedPhpProducesError(): void
    {
        $lexer = new NeoSPXLexer("@php\n  \$x = 1;");
        $lexer->tokenize();
        $errors = $lexer->getErrors();
        $this->assertNotEmpty($errors);
    }
}
