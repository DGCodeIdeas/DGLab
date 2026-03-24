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
use DGLab\Services\Superpowers\Parser\Nodes\FragmentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
use DGLab\Services\Superpowers\Runtime\StateContainer;
use DGLab\Services\Superpowers\Runtime\DebugCollector;
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;

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
        if (config('app.debug')) $this->debugCollector = DebugCollector::getInstance();
    }

    public function getState(): StateContainer { return $this->state; }

    public function interpret(array $ast, array $data = []): string
    {
        if (isset($data['__state'])) {
            if (is_string($data['__state'])) $this->state->import($data['__state']);
            else $this->state->merge($data['__state']);
        }
        foreach ($data as $k => $v) if (substr($k, 0, 2) !== '__') $this->state->set($k, $v);
        $this->processLifecycle($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);
        if (isset($data['__action'])) {
            $action = $data['__action'];
            $scope = $this->state->all();
            if (isset($scope[$action]) && is_callable($scope[$action])) {
                $scope[$action]();
                foreach ($scope as $k => $v) $this->state->set($k, $v);
            }
        }
        $output = $this->interpretNodes($ast);
        $output .= $this->processLifecycle($ast, [RenderedNode::class]);
        if ($this->state->isModified()) {
            $enc = $this->state->export();
            $vn = $data['__view'] ?? null;
            $va = $vn ? " s-view='{$vn}'" : "";
            $output = "<div s-data='{$enc}' s-id='" . uniqid() . "'{$va}>{$output}</div>";
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
                    if ($node instanceof ExtendsNode) $this->state->set('__extendedLayout', $node->layout);
                    else $this->executeLifecycle($node->code);
                }
            }
            if (isset($node->children)) $output .= $this->processLifecycle($node->children, $types);
        }
        return $output;
    }

    private function executeLifecycle(string $code): void
    {
        $scope = $this->state->all();
        $__persisted = [];
        $__g = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $code = preg_replace_callback('/@global\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*[\'"](.*?)[\'"]\s*)?\)/', function ($m) use ($__g, &$scope) {
            $k = $m[1]; $v = $m[2] ?? $k;
            $scope[$v] = $__g->get($k);
            return "";
        }, $code);
        $code = preg_replace_callback('/@persist\s*\(\s*\$(.*?)\s*\)/', function ($m) use ($__g, &$scope, &$__persisted) {
            $v = $m[1]; $__persisted[] = $v;
            $stored = $__g->get($v, '__ABSENT__');
            if ($stored !== '__ABSENT__') $scope[$v] = $stored;
            return "";
        }, $code);
        extract($scope);
        eval($code);
        $vars = get_defined_vars();
        foreach ($vars as $k => $v) {
            if (!in_array($k, ['this', 'code', 'scope', 'vars', '__persisted', '__g'])) {
                $this->state->set($k, $v);
                if (in_array($k, $__persisted)) if ($__g->get($k, '__ABSENT__') !== $v) $__g->set($k, $v);
            }
        }
    }

    public function interpretNodes(array $nodes): string
    {
        $o = "";
        foreach ($nodes as $n) $o .= $this->interpretNode($n);
        return $o;
    }

    private function interpretNode(Node $n): string
    {
        if ($n instanceof TextNode) return $n->content;
        if ($n instanceof SetupNode || $n instanceof MountNode || $n instanceof RenderedNode || $n instanceof CleanupNode || $n instanceof ExtendsNode) return "";
        if ($n instanceof ExpressionNode) {
            $v = $this->evaluate($n->expression);
            return $n->escaped ? View::e((string)$v) : (string)$v;
        }
        if ($n instanceof DirectiveNode) return $this->interpretDirective($n);
        if ($n instanceof ComponentNode) return $this->interpretComponent($n);
        if ($n instanceof ReactiveNode) return $this->interpretReactive($n);
        if ($n instanceof SectionNode) { $this->view->setSection($n->name, $this->interpretNodes($n->children)); return ""; }
        if ($n instanceof YieldNode) return $this->view->yield($n->name, (string)$n->default);
        if ($n instanceof FragmentNode) return '<div data-fragment="' . View::e($n->id) . '">' . $this->interpretNodes($n->children) . '</div>';
        if ($n instanceof SlotNode) return $this->interpretNodes($n->children);
        return "";
    }

    private function interpretDirective(DirectiveNode $n): string
    {
        switch ($n->name) {
            case 'if': return $this->evaluate($n->expression) ? $this->interpretNodes($n->children) : "";
            case 'foreach':
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $n->expression, $m);
                $items = $this->evaluate($m[1]);
                $as = trim($m[2], '$ ');
                $o = "";
                if (is_iterable($items)) {
                    foreach ($items as $i) { $this->state->set($as, $i); $o .= $this->interpretNodes($n->children); }
                }
                return $o;
            case 'prefetch': return ' data-prefetch="' . View::e((string)($n->expression ? $this->evaluate($n->expression) : 'true')) . '"';
            case 'transition': return ' data-transition="' . View::e((string)($n->expression ? $this->evaluate($n->expression) : 'fade')) . '"';
            case 'global':
                $p = explode(',', $n->expression);
                $k = trim($p[0], "'\" "); $v = isset($p[1]) ? trim($p[1], "'\"$ ") : $k;
                $this->state->set($v, Application::getInstance()->get(GlobalStateStoreInterface::class)->get($k));
                return "";
            default: return "";
        }
    }

    private function interpretComponent(ComponentNode $n): string
    {
        $vn = str_starts_with($n->tagName, 'layout:') ? 'layouts/' . substr($n->tagName, 7) : $n->tagName;
        $p = [];
        foreach ($n->props as $name => $prop) $p[$name] = $prop['dynamic'] ? $this->evaluate($prop['value']) : $prop['value'];
        $ds = "";
        foreach ($n->children as $c) {
            if ($c instanceof SlotNode) $p[$c->name] = $this->interpretNodes($c->children);
            else $ds .= $this->interpretNode($c);
        }
        $p['slot'] = $ds;
        if ($this->debugCollector) $this->debugCollector->recordComponent($vn, $p);
        return $this->view->render($vn, $p, null);
    }

    private function interpretReactive(ReactiveNode $n): string
    {
        $this->state->markModified();
        $h = "<{$n->tagName}";
        foreach ($n->attributes as $name => $v) $h .= " {$name}=\"" . View::e((string)$v) . "\"";
        foreach ($n->reactiveAttributes as $e => $a) $h .= " s-on:{$e}=\"" . View::e((string)$a) . "\"";
        return $h . ">" . $this->interpretNodes($n->children) . "</{$n->tagName}>";
    }

    private function evaluate(string $expr)
    {
        $t = $this->transpiler->transpile($expr);
        $s = $this->state->all();
        extract($s);
        try { return eval("return {$t};"); } catch (\Throwable $e) { return null; }
    }
}
