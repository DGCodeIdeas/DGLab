<?php

namespace DGLab\Services\Superpowers\Compiler;

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
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;

/**
 * Class Compiler
 *
 * Compiles SuperPHP AST into optimized PHP code.
 */
class Compiler
{
    private ExpressionTranspiler $transpiler;
    private array $dependencies = [];
    private bool $inlineExpressions;
    private bool $isReactive = false;

    public function __construct()
    {
        $this->transpiler = new ExpressionTranspiler();
        $this->inlineExpressions = (bool) Application::config('superpowers.inline_expressions', true);
    }

    public function compile(array $ast): string
    {
        $this->dependencies = [];
        $this->isReactive = false;

        $body = $this->compileNodes($ast);
        $lifecycle = $this->compileLifecycle($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);
        $rendered = $this->compileLifecycle($ast, [RenderedNode::class]);

        $code = "<?php\n";
        $code .= "// Compiled SuperPHP Template\n";

        $code .= "if (isset(\$__state)) {\n";
        $code .= "    \$this->getEngine()->getInterpreter()->getState()->import(\$__state);\n";
        $code .= "}\n";

        $code .= "foreach (\$data as \$k => \$v) { if (substr(\$k, 0, 2) !== '__') \$this->getEngine()->getInterpreter()->getState()->set(\$k, \$v); }\n";

        $code .= $lifecycle;

        $code .= "if (isset(\$__action)) {\n";
        $code .= "    \$__scope = \$this->getEngine()->getInterpreter()->getState()->all();\n";
        $code .= "    if (isset(\$__scope[\$__action]) && is_callable(\$__scope[\$__action])) {\n";
        $code .= "        \$__scope[\$__action]();\n";
        $code .= "        foreach (\$__scope as \$k => \$v) \$this->getEngine()->getInterpreter()->getState()->set(\$k, \$v);\n";
        $code .= "    }\n";
        $code .= "}\n";

        $code .= "ob_start();\n";
        $code .= $body;
        $code .= "\$__output = ob_get_clean();\n";

        $code .= "ob_start();\n";
        $code .= $rendered;
        $code .= "\$__output .= ob_get_clean();\n";

        if ($this->isReactive) {
             $code .= "\$__encrypted = \$this->getEngine()->getInterpreter()->getState()->export();\n";
             $code .= "\$__viewAttr = isset(\$__view) ? \" s-view='{\$__view}'\" : \"\";\n";
             $code .= "echo \"<div s-data='{\$__encrypted}' s-id='\" . uniqid() . \"'{\$__viewAttr}>{\$__output}</div>\";\n";
        } else {
             $code .= "echo \$__output;\n";
        }

        $code .= "if (isset(\$__extendedLayout)) {\n";
        $code .= "    return \$this->render(\$__extendedLayout, array_merge(\$data, \$this->getEngine()->getInterpreter()->getState()->all()), null);\n";
        $code .= "}\n";

        return $code;
    }

    public function getDependencies(): array
    {
        return array_unique($this->dependencies);
    }

    private function compileLifecycle(array $nodes, array $types): string
    {
        $code = "";
        foreach ($nodes as $node) {
            foreach ($types as $type) {
                if ($node instanceof $type) {
                    if ($node instanceof ExtendsNode) {
                        $code .= "\$__extendedLayout = '{$node->layout}';\n";
                    } else {
                        $code .= "extract(\$this->getEngine()->getInterpreter()->getState()->all());\n";
                        $code .= $node->code . "\n";
                        $code .= "\$__vars = get_defined_vars();\n";
                        $code .= "foreach (\$__vars as \$k => \$v) { if (!in_array(\$k, ['this', 'data', 'code', 'vars'])) \$this->getEngine()->getInterpreter()->getState()->set(\$k, \$v); }\n";
                    }
                }
            }
            if (isset($node->children)) {
                $code .= $this->compileLifecycle($node->children, $types);
            }
        }
        return $code;
    }

    private function compileNodes(array $nodes): string
    {
        $code = "";
        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }
        return $code;
    }

    private function compileNode(Node $node): string
    {
        if ($node instanceof TextNode) {
            return "echo " . var_export($node->content, true) . ";\n";
        }

        if ($node instanceof SetupNode || $node instanceof MountNode || $node instanceof RenderedNode || $node instanceof CleanupNode || $node instanceof ExtendsNode) {
            return "";
        }

        if ($node instanceof ExpressionNode) {
            $transpiled = $this->transpiler->transpile($node->expression);
            if ($node->escaped) {
                return "echo \DGLab\Core\View::e((string)((function() { \$scope = \$this->getEngine()->getInterpreter()->getState()->all(); extract(\$scope); return {$transpiled}; })->call(\$this)));\n";
            }
            return "echo (string)((function() { \$scope = \$this->getEngine()->getInterpreter()->getState()->all(); extract(\$scope); return {$transpiled}; })->call(\$this));\n";
        }

        if ($node instanceof DirectiveNode) {
            return $this->compileDirective($node);
        }

        if ($node instanceof ComponentNode) {
            return $this->compileComponent($node);
        }

        if ($node instanceof ReactiveNode) {
             return $this->compileReactive($node);
        }

        if ($node instanceof SectionNode) {
             $code = "\$this->section('{$node->name}');\n";
             $code .= $this->compileNodes($node->children);
             $code .= "\$this->endSection();\n";
             return $code;
        }

        if ($node instanceof YieldNode) {
             return "echo \$this->yield('{$node->name}', " . var_export($node->default ?? '', true) . ");\n";
        }

        if ($node instanceof SlotNode) {
             return $this->compileNodes($node->children);
        }

        return "";
    }

    private function compileDirective(DirectiveNode $node): string
    {
        switch ($node->name) {
            case 'if':
                $transpiled = $this->transpiler->transpile($node->expression);
                $code = "if ((function(){ \$scope = \$this->getEngine()->getInterpreter()->getState()->all(); extract(\$scope); return {$transpiled}; })->call(\$this)):\n";
                $code .= $this->compileNodes($node->children);
                $code .= "endif;\n";
                return $code;
            case 'foreach':
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $node->expression, $matches);
                $itemsExpr = $this->transpiler->transpile($matches[1]);
                $as = $matches[2];
                $code = "\$__items = (function(){ \$scope = \$this->getEngine()->getInterpreter()->getState()->all(); extract(\$scope); return {$itemsExpr}; })->call(\$this);\n";
                $code .= "if (isset(\$__items) && is_iterable(\$__items)): foreach (\$__items as {$as}):\n";
                $code .= $this->compileNodes($node->children);
                $code .= "endforeach; endif;\n";
                return $code;
            default:
                return "";
        }
    }

    private function compileComponent(ComponentNode $node): string
    {
        $tagName = $node->tagName;
        if (str_starts_with($tagName, 'layout:')) {
            $viewName = 'layouts/' . substr($tagName, 7);
        } else {
            $viewName = $tagName;
        }

        $this->dependencies[] = $viewName;

        $code = "\$__props = [];\n";
        foreach ($node->props as $name => $prop) {
            if ($prop['dynamic']) {
                $val = $this->transpiler->transpile($prop['value']);
                $code .= "\$__props['{$name}'] = (function(){ \$scope = \$this->getEngine()->getInterpreter()->getState()->all(); extract(\$scope); return {$val}; })->call(\$this);\n";
            } else {
                $code .= "\$__props['{$name}'] = " . var_export($prop['value'], true) . ";\n";
            }
        }

        $code .= "ob_start();\n";
        $code .= $this->compileNodes($node->children);
        $code .= "\$__props['slot'] = ob_get_clean();\n";

        $code .= "echo \$this->render('{$viewName}', \$__props, null);\n";

        return $code;
    }

    private function compileReactive(ReactiveNode $node): string
    {
        $this->isReactive = true;
        $code = "echo '<{$node->tagName}';\n";
        foreach ($node->attributes as $name => $value) {
            $code .= "echo ' {$name}=\"'. \DGLab\Core\View::e((string)(" . var_export($value, true) . ")) . '\"';\n";
        }
        foreach ($node->reactiveAttributes as $event => $action) {
            $code .= "echo ' s-on:{$event}=\"{$action}\"';\n";
        }
        $code .= "echo '>';\n";
        $code .= $this->compileNodes($node->children);
        $code .= "echo '</{$node->tagName}>';\n";
        return $code;
    }
}
