<?php

namespace DGLab\Services\Superpowers\Interpreter;

use DGLab\Core\View;
use DGLab\Core\Application;
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
use DGLab\Services\Superpowers\Runtime\StateContainer;
use DGLab\Services\Superpowers\Runtime\DebugCollector;
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;

/**
 * Class Interpreter
 *
 * Executes AST nodes and generates output.
 */
class Interpreter
{
    private StateContainer $state;
    private View $view;
    private ?DebugCollector $debugCollector = null;
    private ExpressionTranspiler $transpiler;

    public function __construct(View $view)
    {
        $this->state = new StateContainer();
        $this->view = $view;
        $this->transpiler = new ExpressionTranspiler();
        if (Application::config('app.debug')) {
            $this->debugCollector = DebugCollector::getInstance();
        }
    }

    public function getState(): StateContainer
    {
        return $this->state;
    }

    public function interpret(array $ast, array $data = []): string
    {
        if (isset($data['__state'])) {
            $this->state->import($data['__state']);
        }

        foreach ($data as $k => $v) {
            if (substr($k, 0, 2) !== '__') {
                $this->state->set($k, $v);
            }
        }

        $this->processLifecycle($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);

        if (isset($data['__action'])) {
            $action = $data['__action'];
            $scope = $this->state->all();
            if (isset($scope[$action]) && is_callable($scope[$action])) {
                $scope[$action]();
                foreach ($scope as $k => $v) {
                    $this->state->set($k, $v);
                }
            }
        }

        $output = $this->interpretNodes($ast);
        $output .= $this->processLifecycle($ast, [RenderedNode::class]);

        if ($this->state->isModified()) {
             $encrypted = $this->state->export();
             $viewName = $data['__view'] ?? null;
             $viewAttr = $viewName ? " s-view='{$viewName}'" : "";
             $output = "<div s-data='{$encrypted}' s-id='" . uniqid() . "'{$viewAttr}>{$output}</div>";
        }

        $layout = $this->state->get('__extendedLayout');
        if ($layout) {
             $this->state->remove('__extendedLayout');
             return $this->view->render($layout, array_merge($data, $this->state->all()));
        }

        return $output;
    }

    public function processLifecycle(array $nodes, array $types): string
    {
        $output = "";
        foreach ($nodes as $node) {
            foreach ($types as $type) {
                if ($node instanceof $type) {
                    if ($node instanceof ExtendsNode) {
                         $this->state->set('__extendedLayout', $node->layout);
                    } else {
                        $this->executeLifecycle($node->code);
                    }
                }
            }
            if (isset($node->children)) {
                $output .= $this->processLifecycle($node->children, $types);
            }
        }
        return $output;
    }

    private function executeLifecycle(string $code): void
    {
        $scope = $this->state->all();
        extract($scope);

        eval($code);

        $vars = get_defined_vars();
        foreach ($vars as $k => $v) {
            if (!in_array($k, ['this', 'code', 'scope', 'vars'])) {
                $this->state->set($k, $v);
            }
        }
    }

    public function interpretNodes(array $nodes): string
    {
        $output = "";
        foreach ($nodes as $node) {
            $output .= $this->interpretNode($node);
        }
        return $output;
    }

    private function interpretNode(Node $node): string
    {
        if ($node instanceof TextNode) {
            return $node->content;
        }

        if ($node instanceof SetupNode || $node instanceof MountNode || $node instanceof RenderedNode || $node instanceof CleanupNode || $node instanceof ExtendsNode) {
            return "";
        }

        if ($node instanceof ExpressionNode) {
            $val = $this->evaluate($node->expression);
            return $node->escaped ? View::e((string)$val) : (string)$val;
        }

        if ($node instanceof DirectiveNode) {
            return $this->interpretDirective($node);
        }

        if ($node instanceof ComponentNode) {
            return $this->interpretComponent($node);
        }

        if ($node instanceof ReactiveNode) {
             return $this->interpretReactive($node);
        }

        if ($node instanceof SectionNode) {
             $this->view->section($node->name);
             $this->view->getEngine()->getInterpreter()->interpretNodes($node->children);
             $this->view->endSection();
             return "";
        }

        if ($node instanceof YieldNode) {
             return $this->view->yield($node->name, $node->default);
        }

        if ($node instanceof SlotNode) {
             return $this->interpretNodes($node->children);
        }

        return "";
    }

    private function interpretDirective(DirectiveNode $node): string
    {
        switch ($node->name) {
            case 'if':
                if ($this->evaluate($node->expression)) {
                    return $this->interpretNodes($node->children);
                }
                return "";
            case 'foreach':
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $node->expression, $matches);
                $items = $this->evaluate($matches[1]);
                $as = $matches[2];
                $output = "";
                if (is_iterable($items)) {
                    foreach ($items as $item) {
                        $this->state->set(trim($as, '$'), $item);
                        $output .= $this->interpretNodes($node->children);
                    }
                }
                return $output;
            default:
                return "";
        }
    }

    private function interpretComponent(ComponentNode $node): string
    {
        $tagName = $node->tagName;
        if (str_starts_with($tagName, 'layout:')) {
            $viewName = 'layouts/' . substr($tagName, 7);
        } else {
            $viewName = $tagName;
        }

        $props = [];
        foreach ($node->props as $name => $prop) {
            $props[$name] = $prop['dynamic'] ? $this->evaluate($prop['value']) : $prop['value'];
        }

        $defaultSlot = "";
        foreach ($node->children as $child) {
            if ($child instanceof SlotNode) {
                $props[$child->name] = $this->interpretNodes($child->children);
            } else {
                $defaultSlot .= $this->interpretNode($child);
            }
        }
        $props['slot'] = $defaultSlot;

        if ($this->debugCollector) {
            $this->debugCollector->recordComponent($viewName, $props);
        }

        return $this->view->render($viewName, $props, null);
    }

    private function interpretReactive(ReactiveNode $node): string
    {
        $this->state->markModified();
        $html = "<{$node->tagName}";
        foreach ($node->attributes as $name => $value) {
            $html .= " {$name}=\"" . View::e((string)$value) . "\"";
        }
        foreach ($node->reactiveAttributes as $event => $action) {
            $html .= " s-on:{$event}=\"" . View::e((string)$action) . "\"";
        }
        $html .= ">";
        $html .= $this->interpretNodes($node->children);
        $html .= "</{$node->tagName}>";
        return $html;
    }

    private function evaluate(string $expression)
    {
        $transpiled = $this->transpiler->transpile($expression);
        $scope = $this->state->all();
        extract($scope);
        try {
            return eval("return {$transpiled};");
        } catch (\Throwable $e) {
             return null;
        }
    }
}
