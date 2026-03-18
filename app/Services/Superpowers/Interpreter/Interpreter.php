<?php

namespace DGLab\Services\Superpowers\Interpreter;

use DGLab\Core\Application;
use DGLab\Core\View;
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
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;
use DGLab\Services\Superpowers\Runtime\StateContainer;
use DGLab\Services\Superpowers\Runtime\CleanupManager;

/**
 * Class Interpreter
 *
 * Executes the AST nodes to produce HTML.
 */
class Interpreter
{
    /**
     * @var StateContainer
     */
    private StateContainer $state;

    /**
     * @var ExpressionTranspiler
     */
    private ExpressionTranspiler $transpiler;

    public function __construct()
    {
        $this->transpiler = new ExpressionTranspiler();
        $this->state = new StateContainer();
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
        $this->state->merge($initialData);

        // Phase 4: Prioritized Lifecycle execution
        $this->executeLifecycle($ast, [SetupNode::class, MountNode::class]);

        $output = '';
        foreach ($ast as $node) {
            $output .= $this->evaluate($node);
        }

        // We capture rendered output of the hooks but usually they are for side effects.
        // If they echo, it will be lost unless we buffer it.
        ob_start();
        $this->executeLifecycle($ast, [RenderedNode::class]);
        $output .= ob_get_clean();

        // Cleanup registration
        $this->registerCleanup($ast);

        return $output;
    }

    private function executeLifecycle(array $nodes, array $types): void
    {
        foreach ($nodes as $node) {
            foreach ($types as $type) {
                if ($node instanceof $type) {
                    $this->executeCode($node->code);
                }
            }
            if (isset($node->children)) {
                $this->executeLifecycle($node->children, $types);
            }
        }
    }

    private function registerCleanup(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof CleanupNode) {
                $code = $node->code;
                $state = clone $this->state;
                CleanupManager::getInstance()->register(function() use ($code, $state) {
                    $scope = $state->all();
                    extract($scope);
                    eval($code);
                });
            }
            if (isset($node->children)) {
                $this->registerCleanup($node->children);
            }
        }
    }

    private function evaluate(Node $node): string
    {
        if ($node instanceof TextNode) {
            return $node->content;
        }

        if ($node instanceof SetupNode || $node instanceof MountNode || $node instanceof RenderedNode || $node instanceof CleanupNode) {
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

        if ($node instanceof SlotNode) {
             return $this->evaluateNodes($node->children);
        }

        return '';
    }

    private function executeCode(string $code): void
    {
        $scope = $this->state->all();
        extract($scope);
        eval($code);

        // Update state with newly defined variables
        $newScope = get_defined_vars();
        unset($newScope['code'], $newScope['this'], $newScope['scope']);
        $this->state->merge($newScope);
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
                    $originalState = clone $this->state;

                    if (strpos($as, '=>') !== false) {
                         [$keyName, $valName] = array_map(function($s) { return trim($s, '$ '); }, explode('=>', $as));
                         $this->state->set($keyName, $key);
                         $this->state->set($valName, $item);
                    } else {
                        $this->state->set(trim($as, '$ '), $item);
                    }

                    $output .= $this->evaluateNodes($node->children);
                    $this->state = $originalState;
                }
                return $output;
            default:
                return '';
        }
    }

    private function evaluateComponent(ComponentNode $node): string
    {
        $props = [];
        foreach ($node->props as $name => $prop) {
            $props[$name] = $prop['dynamic'] ? $this->evaluatePhp($prop['value']) : $prop['value'];
        }

        $namedSlots = [];
        $defaultSlotChildren = [];

        foreach ($node->children as $child) {
            if ($child instanceof SlotNode) {
                $namedSlots[$child->name] = $this->evaluateNodes($child->children);
            } else {
                $defaultSlotChildren[] = $child;
            }
        }

        $props['slot'] = $this->evaluateNodes($defaultSlotChildren);
        $props = array_merge($props, $namedSlots);

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

        $scope = $this->state->all();
        extract($scope);
        return eval("return {$transpiled};");
    }
}
