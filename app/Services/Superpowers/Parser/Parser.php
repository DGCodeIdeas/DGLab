<?php

namespace DGLab\Services\Superpowers\Parser;

use DGLab\Services\Superpowers\Lexer\Token;
use DGLab\Services\Superpowers\Parser\Nodes\CleanupNode;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\MountNode;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\RenderedNode;
use DGLab\Services\Superpowers\Parser\Nodes\SetupNode;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\SlotNode;
use DGLab\Services\Superpowers\Parser\Nodes\SectionNode;
use DGLab\Services\Superpowers\Parser\Nodes\YieldNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;

/**
 * Class Parser
 *
 * Transforms a stream of tokens into an Abstract Syntax Tree (AST).
 */
class Parser
{
    private array $tokens;
    private int $pos = 0;

    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->pos = 0;
        $ast = [];

        while ($this->pos < count($this->tokens)) {
            $ast[] = $this->parseNode();
        }

        return $ast;
    }

    private function parseNode(): Node
    {
        $token = $this->tokens[$this->pos];

        switch ($token->type) {
            case Token::T_TEXT:
                $this->pos++;
                return new TextNode($token->value, $token->line);
            case Token::T_EXPRESSION_ESCAPED:
                $this->pos++;
                return new ExpressionNode($token->value, true, $token->line);
            case Token::T_EXPRESSION_RAW:
                $this->pos++;
                return new ExpressionNode($token->value, false, $token->line);
            case Token::T_SETUP_BLOCK:
                $this->pos++;
                return new SetupNode($token->value, $token->line);
            case Token::T_MOUNT_BLOCK:
                $this->pos++;
                return new MountNode($token->value, $token->line);
            case Token::T_RENDERED_BLOCK:
                $this->pos++;
                return new RenderedNode($token->value, $token->line);
            case Token::T_CLEANUP_BLOCK:
                $this->pos++;
                return new CleanupNode($token->value, $token->line);
            case Token::T_DIRECTIVE:
                return $this->parseDirective();
            case Token::T_COMPONENT_OPEN:
            case Token::T_COMPONENT_SELF_CLOSING:
                return $this->parseComponent();
            case Token::T_REACTIVE_TAG:
                return $this->parseReactiveTag();
            case Token::T_COMPONENT_CLOSE:
                throw new SyntaxException("Unexpected closing tag: {$token->value}", null, $token->line);
            default:
                throw new SyntaxException("Unknown token type: {$token->type}", null, $token->line);
        }
    }

    private function parseDirective(): Node
    {
        $token = $this->tokens[$this->pos++];

        if (preg_match('/^@([a-zA-Z0-9]+)(?:\s*\((.*)\))?/s', $token->value, $matches)) {
            $name = $matches[1];
            $expression = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : null;
        } else {
             $name = ltrim($token->value, '@');
             $expression = null;
        }

        if ($name === 'section') {
            $sectionName = trim($expression, "'\"");
            $node = new SectionNode($sectionName, $token->line);
            $node->children = $this->parseUntil('@endsection');
            return $node;
        }

        if ($name === 'yield') {
             $parts = array_map('trim', explode(',', $expression));
             $yieldName = trim($parts[0], "'\"");
             $default = isset($parts[1]) ? trim($parts[1], "'\"") : null;
             return new YieldNode($yieldName, $default, $token->line);
        }

        if ($name === 'extends') {
            $layout = trim($expression, "'\"");
            return new ExtendsNode($layout, $token->line);
        }

        $node = new DirectiveNode($name, $expression, $token->line);

        if (in_array($name, ['if', 'foreach', 'auth', 'guest', 'error', 'switch'])) {
            $terminator = '@end' . $name;
            $node->children = $this->parseUntil($terminator);
        }

        return $node;
    }

    private function parseComponent(): Node
    {
        $token = $this->tokens[$this->pos++];
        $isSelfClosing = ($token->type === Token::T_COMPONENT_SELF_CLOSING);

        if (preg_match('/^<s:([a-zA-Z0-9\-\.\:]+)(.*?)(\/?)>/s', $token->value, $matches)) {
             $fullTagName = $matches[1];
             $propsString = $matches[2];
        } else {
             throw new SyntaxException("Invalid component tag: {$token->value}", null, $token->line);
        }

        $props = $this->parseProps($propsString);

        if ($fullTagName === 'slot') {
            $name = $props['name']['value'] ?? 'default';
            $node = new SlotNode($name, $token->line);
            if (!$isSelfClosing) {
                $node->children = $this->parseUntilComponentClose($fullTagName);
            }
            return $node;
        }

        $node = new ComponentNode($fullTagName, $props, $token->line);

        if (!$isSelfClosing) {
            $node->children = $this->parseUntilComponentClose($fullTagName);
        }

        return $node;
    }

    private function parseReactiveTag(): Node
    {
        $token = $this->tokens[$this->pos++];
        preg_match('/^<([a-zA-Z0-9]+)\s+(.*?)(\/?)>/s', $token->value, $matches);
        $tagName = $matches[1];
        $attributesString = $matches[2];
        $isSelfClosing = (isset($matches[3]) && $matches[3] === '/');

        $attributes = [];
        $reactiveAttributes = [];

        preg_match_all('/(?:(?P<prefix>@|s-)?(?P<name>[a-zA-Z0-9\-\._:]+))(?:\s*=\s*(?:"(?P<v1>[^"]*)"|\'(?P<v2>[^\']*)\'|(?P<v3>[^\s>]+)))?/i', $attributesString, $attrMatches, PREG_SET_ORDER);

        foreach ($attrMatches as $match) {
            $prefix = $match['prefix'] ?? '';
            $name = $match['name'];
            $value = isset($match['v1']) && $match['v1'] !== '' ? $match['v1'] : (isset($match['v2']) && $match['v2'] !== '' ? $match['v2'] : (isset($match['v3']) && $match['v3'] !== '' ? $match['v3'] : true));

            if ($prefix === '@') {
                $reactiveAttributes[$name] = $value;
            } elseif ($prefix === 's-') {
                $attributes["s-{$name}"] = $value;
            } else {
                $attributes[$name] = $value;
            }
        }

        $node = new ReactiveNode($tagName, $attributes, $reactiveAttributes, $token->line);
        if (!$isSelfClosing) {
             $node->children = $this->parseUntilTagClose($tagName);
        }

        return $node;
    }

    private function parseUntilTagClose(string $tagName): array
    {
         $children = [];
        while ($this->pos < count($this->tokens)) {
             $token = $this->tokens[$this->pos];
            if ($token->type === Token::T_TEXT && strpos($token->value, "</{$tagName}>") !== false) {
                 $pos = strpos($token->value, "</{$tagName}>");
                if ($pos > 0) {
                    $children[] = new TextNode(substr($token->value, 0, $pos), $token->line);
                }
                 $token->value = substr($token->value, $pos + strlen("</{$tagName}>"));
                if ($token->value === '') {
                    $this->pos++;
                }
                 return $children;
            }
             $children[] = $this->parseNode();
        }
         throw new SyntaxException("Missing </{$tagName}>", null, 0);
    }

    private function parseProps(string $propsString): array
    {
        $props = [];
        preg_match_all('/(?<dynamic>:)?(?<special>@|s-)?(?<name>[a-zA-Z0-9\-\._]+)(?:\s*=\s*(?:"(?<value>[^"]*)"|(?<unquoted>[^\s>]+)))?/', $propsString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match['name'];
            $isDynamic = !empty($match['dynamic']);
            $special = $match['special'] ?? '';

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
                'dynamic' => $isDynamic,
                'special' => $special
            ];
        }

        return $props;
    }

    private function parseUntil(string $terminator): array
    {
        $children = [];
        while ($this->pos < count($this->tokens)) {
            $token = $this->tokens[$this->pos];
            if ($token->type === Token::T_DIRECTIVE && str_starts_with($token->value, $terminator)) {
                $this->pos++;
                return $children;
            }
            $children[] = $this->parseNode();
        }
        throw new SyntaxException("Missing expected terminator: {$terminator}", null, 0);
    }

    private function parseUntilComponentClose(string $tagName): array
    {
        $children = [];
        while ($this->pos < count($this->tokens)) {
            $token = $this->tokens[$this->pos];
            if ($token->type === Token::T_COMPONENT_CLOSE) {
                if (preg_match('/^<\/s:([a-zA-Z0-9\-\.\:]+)\s*>/s', $token->value, $matches)) {
                    if ($matches[1] === $tagName) {
                        $this->pos++;
                        return $children;
                    }
                }
            }
            $children[] = $this->parseNode();
        }
        throw new SyntaxException("Missing expected closing tag for component: <s:{$tagName}>", null, 0);
    }
}
