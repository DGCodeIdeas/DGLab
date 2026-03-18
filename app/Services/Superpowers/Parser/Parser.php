<?php

namespace DGLab\Services\Superpowers\Parser;

use DGLab\Services\Superpowers\Lexer\Token;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\SetupNode;
use DGLab\Services\Superpowers\Parser\Nodes\MountNode;
use DGLab\Services\Superpowers\Parser\Nodes\RenderedNode;
use DGLab\Services\Superpowers\Parser\Nodes\CleanupNode;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\SlotNode;

/**
 * Class Parser
 *
 * Transforms a stream of tokens into an Abstract Syntax Tree (AST).
 */
class Parser
{
    /**
     * @var Token[]
     */
    private array $tokens;
    private int $pos = 0;

    /**
     * Parse the stream of tokens.
     *
     * @param Token[] $tokens
     * @return Node[]
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->pos = 0;
        $ast = [];

        while (!$this->isAtEnd()) {
            $ast[] = $this->parseNode();
        }

        return $ast;
    }

    private function parseNode(): Node
    {
        $token = $this->peek();

        switch ($token->type) {
            case Token::T_TEXT:
                $this->advance();
                return new TextNode($token->value, $token->line);
            case Token::T_EXPRESSION_ESCAPED:
                $this->advance();
                return new ExpressionNode($token->value, true, $token->line);
            case Token::T_EXPRESSION_RAW:
                $this->advance();
                return new ExpressionNode($token->value, false, $token->line);
            case Token::T_SETUP_BLOCK:
                $this->advance();
                return new SetupNode($token->value, $token->line);
            case Token::T_MOUNT_BLOCK:
                $this->advance();
                return new MountNode($token->value, $token->line);
            case Token::T_RENDERED_BLOCK:
                $this->advance();
                return new RenderedNode($token->value, $token->line);
            case Token::T_CLEANUP_BLOCK:
                $this->advance();
                return new CleanupNode($token->value, $token->line);
            case Token::T_DIRECTIVE:
                return $this->parseDirective();
            case Token::T_COMPONENT_OPEN:
            case Token::T_COMPONENT_SELF_CLOSING:
                return $this->parseComponent();
            case Token::T_COMPONENT_CLOSE:
                throw new \RuntimeException("Unexpected closing tag: {$token->value} at line {$token->line}");
            default:
                throw new \RuntimeException("Unknown token type: {$token->type} at line {$token->line}");
        }
    }

    private function parseDirective(): DirectiveNode
    {
        $token = $this->advance();

        $name = '';
        $expression = null;

        if (preg_match('/^@([a-zA-Z]+)(?:\s*\((.*)\))?/s', $token->value, $matches)) {
            $name = $matches[1];
            if (isset($matches[2]) && $matches[2] !== '') {
                 $expression = trim($matches[2]);
            }
        } else {
             $name = ltrim($token->value, '@');
        }

        $node = new DirectiveNode($name, $expression, $token->line);

        // Check if this directive is a block starter
        if ($this->isBlockDirective($name)) {
            $terminator = '@end' . $name;
            $node->children = $this->parseUntilDirective($terminator);
        }

        return $node;
    }

    private function parseComponent(): Node
    {
        $token = $this->advance();
        $isSelfClosing = ($token->type === Token::T_COMPONENT_SELF_CLOSING);

        preg_match('/^<s:([a-zA-Z0-9\-\.]+)\s*([^>]*?)(\/?)>/s', $token->value, $matches);
        $tagName = $matches[1];
        $propsString = $matches[2] ?? '';
        $props = $this->parseProps($propsString);

        if ($tagName === 'slot') {
            $name = $props['name']['value'] ?? 'default';
            $node = new SlotNode($name, $token->line);
            if (!$isSelfClosing) {
                $node->children = $this->parseUntilComponentClose($tagName);
            }
            return $node;
        }

        $node = new ComponentNode($tagName, $props, $token->line);

        if (!$isSelfClosing) {
            $node->children = $this->parseUntilComponentClose($tagName);
        }

        return $node;
    }

    private function parseProps(string $propsString): array
    {
        $props = [];
        // Matches: name="value", :name="$dynamic", name
        preg_match_all('/(?<dynamic>:)?(?<name>[a-zA-Z0-9\-\._]+)(?:\s*=\s*(?:"(?<value>[^"]*)"|(?<unquoted>[^\s>]+)))?/', $propsString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match['name'];
            $isDynamic = !empty($match['dynamic']);

            $value = '';
            if (isset($match['value'])) {
                $value = $match['value'];
            } elseif (isset($match['unquoted'])) {
                $value = $match['unquoted'];
            } else {
                $value = 'true';
            }

            $props[$name] = [
                'value' => $value,
                'dynamic' => $isDynamic
            ];
        }

        return $props;
    }

    private function parseUntilDirective(string $terminator): array
    {
        $children = [];
        while (!$this->isAtEnd()) {
            $token = $this->peek();
            if ($token->type === Token::T_DIRECTIVE && str_starts_with($token->value, $terminator)) {
                $this->advance(); // Consume @end...
                return $children;
            }
            $children[] = $this->parseNode();
        }
        throw new \RuntimeException("Missing expected terminator: {$terminator}");
    }

    private function parseUntilComponentClose(string $tagName): array
    {
        $children = [];
        while (!$this->isAtEnd()) {
            $token = $this->peek();
            if ($token->type === Token::T_COMPONENT_CLOSE) {
                preg_match('/^<\/s:([a-zA-Z0-9\-\.]+)\s*>/s', $token->value, $matches);
                if ($matches[1] === $tagName) {
                    $this->advance(); // Consume closing tag
                    return $children;
                }
            }
            $children[] = $this->parseNode();
        }
        throw new \RuntimeException("Missing expected closing tag for component: {$tagName}");
    }

    private function isBlockDirective(string $name): bool
    {
        return in_array($name, ['if', 'foreach', 'auth', 'guest', 'section', 'error', 'switch']);
    }

    private function isAtEnd(): bool
    {
        return $this->pos >= count($this->tokens);
    }

    private function peek(): Token
    {
        return $this->tokens[$this->pos];
    }

    private function advance(): Token
    {
        return $this->tokens[$this->pos++];
    }
}
