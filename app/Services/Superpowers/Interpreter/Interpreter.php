<?php

namespace DGLab\Services\Superpowers\Interpreter;

use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\SetupNode;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;

/**
 * Class Interpreter
 *
 * Executes the AST nodes to produce HTML.
 */
class Interpreter
{
    /**
     * @var array
     */
    private array $scope = [];

    /**
     * @var ExpressionTranspiler
     */
    private ExpressionTranspiler $transpiler;

    public function __construct()
    {
        $this->transpiler = new ExpressionTranspiler();
    }

    /**
     * Interpret the AST.
     *
     * @param Node[] $ast
     * @param array $initialData
     * @return string
     */
    public function interpret(array $ast, array $initialData = []): string
    {
        $this->scope = $initialData;
        $output = '';

        foreach ($ast as $node) {
            $output .= $this->evaluate($node);
        }

        return $output;
    }

    private function evaluate(Node $node): string
    {
        if ($node instanceof TextNode) {
            return $node->content;
        }

        if ($node instanceof SetupNode) {
            $this->executeSetup($node->code);
            return '';
        }

        if ($node instanceof ExpressionNode) {
            return $this->evaluateExpression($node);
        }

        if ($node instanceof DirectiveNode) {
            return $this->evaluateDirective($node);
        }

        if ($node instanceof ComponentNode) {
            return $this->evaluateComponent($node);
        }

        return '';
    }

    private function executeSetup(string $code): void
    {
        extract($this->scope);
        eval($code);

        // Update scope with newly defined variables
        $newScope = get_defined_vars();
        unset($newScope['code'], $newScope['this']);
        $this->scope = array_merge($this->scope, $newScope);
    }

    private function evaluateExpression(ExpressionNode $node): string
    {
        $result = $this->evaluatePhp($node->expression);
        return $node->escaped ? View::e((string)$result) : (string)$result;
    }

    private function evaluateDirective(DirectiveNode $node): string
    {
        switch ($node->name) {
            case 'if':
                if ($this->evaluatePhp($node->expression)) {
                    return $this->evaluateNodes($node->children);
                }
                return '';
            case 'foreach':
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $node->expression, $matches);
                if (!$matches) throw new \RuntimeException("Invalid @foreach syntax at line {$node->line}");

                $items = $this->evaluatePhp($matches[1]);
                $as = $matches[2];
                $output = '';

                if ($items === null || !is_iterable($items)) {
                    return '';
                }

                foreach ($items as $key => $item) {
                    // Push local scope
                    $originalScope = $this->scope;
                    if (strpos($as, '=>') !== false) {
                         [$keyName, $valName] = array_map(function($s) { return trim($s, '$ '); }, explode('=>', $as));
                         $this->scope[$keyName] = $key;
                         $this->scope[$valName] = $item;
                    } else {
                        $this->scope[trim($as, '$ ')] = $item;
                    }

                    $output .= $this->evaluateNodes($node->children);
                    $this->scope = $originalScope;
                }
                return $output;
            default:
                // Handle non-block directives (like @include) or placeholders
                return '';
        }
    }

    private function evaluateComponent(ComponentNode $node): string
    {
        $props = [];
        foreach ($node->props as $name => $prop) {
            $props[$name] = $prop['dynamic'] ? $this->evaluatePhp($prop['value']) : $prop['value'];
        }

        $slot = $this->evaluateNodes($node->children);
        $props['slot'] = $slot;

        $view = Application::getInstance()->get(View::class);
        return $view->render("components/{$node->tagName}", $props, null);
    }

    private function evaluateNodes(array $nodes): string
    {
        $output = '';
        foreach ($nodes as $node) {
            $output .= $this->evaluate($node);
        }
        return $output;
    }

    private function evaluatePhp(string $code)
    {
        $this->transpiler->validate($code);
        $transpiled = $this->transpiler->transpile($code);

        extract($this->scope);
        return eval("return {$transpiled};");
    }
}
