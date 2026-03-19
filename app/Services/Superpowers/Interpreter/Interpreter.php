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
use DGLab\Services\Superpowers\Parser\Nodes\SectionNode;
use DGLab\Services\Superpowers\Parser\Nodes\YieldNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
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
    private StateContainer $state;
    private ExpressionTranspiler $transpiler;
    private ?string $extendedLayout = null;
    private View $view;
    private bool $isReactive = false;
    private ?string $currentView = null;

    public function __construct(View $view)
    {
        $this->transpiler = new ExpressionTranspiler();
        $this->state = new StateContainer();
        $this->view = $view;
    }

    public function getState(): StateContainer
    {
        return $this->state;
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
        $this->extendedLayout = null;
        $this->currentView = $initialData['__view'] ?? null;

        // Phase 7: Hydration
        if (isset($initialData['__state'])) {
             $this->state->import($initialData['__state']);
        }

        // Prioritized execution
        $this->executeLifecycle($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);

        // Phase 7: Action Execution
        if (isset($initialData['__action'])) {
             $action = $initialData['__action'];
             $this->executeAction($action);
        }

        $output = '';
        foreach ($ast as $node) {
            $output .= $this->evaluate($node);
        }

        ob_start();
        $this->executeLifecycle($ast, [RenderedNode::class]);
        $output .= ob_get_clean();

        $this->registerCleanup($ast);

        // Phase 7: Reactive Boundary
        if ($this->isReactive || isset($initialData['__state'])) {
             $encryptedState = $this->state->export();
             $viewAttr = $this->currentView ? " s-view=\"{$this->currentView}\"" : "";
             $output = "<div s-data=\"{$encryptedState}\" s-id=\"" . uniqid() . "\"{$viewAttr}>{$output}</div>";
        }

        if ($this->extendedLayout) {
             if (!$this->view->hasSection('content')) {
                 $this->view->section('content');
                 echo $output;
                 $this->view->endSection();
             }
             return $this->view->render($this->extendedLayout, $this->state->all(), null);
        }

        return $output;
    }

    private function executeAction(string $action): void
    {
        $scope = $this->state->all();
        if (isset($scope[$action]) && is_callable($scope[$action])) {
             $scope[$action]();
             // Re-sync scope
             foreach ($scope as $k => $v) {
                  $this->state->set($k, $v);
             }
        }
    }

    private function executeLifecycle(array $nodes, array $types): void
    {
        foreach ($nodes as $node) {
            foreach ($types as $type) {
                if ($node instanceof $type) {
                    if ($node instanceof ExtendsNode) {
                        $this->extendedLayout = $node->layout;
                    } else {
                        $this->executeCode($node->code);
                    }
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
                $view = $this->view;
                CleanupManager::getInstance()->register(function() use ($code, $state, $view) {
                    $scope = $state->all();
                    (function() use ($code, $scope) {
                        extract($scope);
                        eval($code);
                    })->call($view);
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

        if ($node instanceof SetupNode || $node instanceof MountNode || $node instanceof RenderedNode || $node instanceof CleanupNode || $node instanceof ExtendsNode) {
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

        if ($node instanceof SectionNode) {
            $this->view->section($node->name);
            echo $this->evaluateNodes($node->children);
            $this->view->endSection();
            return '';
        }

        if ($node instanceof YieldNode) {
            ob_start();
            $this->view->yield($node->name, $node->default ?? '');
            return ob_get_clean();
        }

        if ($node instanceof ReactiveNode) {
             return $this->evaluateReactive($node);
        }

        return '';
    }

    private function evaluateReactive(ReactiveNode $node): string
    {
        $this->isReactive = true;
        $attrs = "";
        foreach ($node->attributes as $name => $value) {
            $attrs .= " {$name}=\"{$value}\"";
        }
        foreach ($node->reactiveAttributes as $event => $action) {
            $attrs .= " s-on:{$event}=\"{$action}\"";
        }

        return "<{$node->tagName}{$attrs}>" . $this->evaluateNodes($node->children) . "</{$node->tagName}>";
    }

    private function executeCode(string $code): void
    {
        $view = $this->view;
        $scope = $this->state->all();

        $callback = (function() use ($code, $scope) {
            extract($scope);
            eval($code);
            return get_defined_vars();
        });

        $vars = $callback->call($view);
        unset($vars['code'], $vars['this'], $vars['scope'], $vars['view'], $vars['callback']);
        $this->state->merge($vars);
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

        $tagName = $node->tagName;
        if (str_starts_with($tagName, 'layout:')) {
            $tagName = 'layouts/' . substr($tagName, 7);
        }

        return $this->view->render($tagName, $props, null);
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

        $view = $this->view;
        $scope = $this->state->all();
        $callback = (function() use ($transpiled, $scope) {
            extract($scope);
            return eval("return {$transpiled};");
        });

        return $callback->call($view);
    }
}
