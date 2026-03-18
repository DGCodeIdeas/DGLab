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

    public function __construct()
    {
        $this->transpiler = new ExpressionTranspiler();
        $this->inlineExpressions = (bool) Application::config('superpowers.inline_expressions', true);
    }

    /**
     * Compile the AST into a PHP string.
     *
     * @param Node[] $ast
     * @return string
     */
    public function compile(array $ast): string
    {
        $this->dependencies = [];
        $code = "<?php\n";
        $code .= "// Compiled SuperPHP Template\n";
        $code .= "// Dependencies: " . json_encode($this->dependencies) . "\n\n";

        // Prioritized blocks (~setup, ~mount, @extends)
        $code .= $this->compileLifecycle($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);

        // Main content
        $code .= $this->compileNodes($ast);

        // Rendered hooks
        $code .= $this->compileLifecycle($ast, [RenderedNode::class]);

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
                        // We'll handle @extends by setting a flag that View will check,
                        // or by wrapping at the end. For compiled code, it's easier to set a section.
                        $code .= "\$this->section('content'); ob_start();\n";
                        $code .= "\$__extendedLayout = '{$node->layout}';\n";
                    } else {
                        $code .= "// Hook line {$node->line}\n";
                        $code .= $node->code . "\n";
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
                return "echo \DGLab\Core\View::e((string)({$transpiled}));\n";
            }
            return "echo (string)({$transpiled});\n";
        }

        if ($node instanceof DirectiveNode) {
            return $this->compileDirective($node);
        }

        if ($node instanceof ComponentNode) {
            return $this->compileComponent($node);
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
                $code = "if ({$transpiled}):\n";
                $code .= $this->compileNodes($node->children);
                $code .= "endif;\n";
                return $code;
            case 'foreach':
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $node->expression, $matches);
                $items = $this->transpiler->transpile($matches[1]);
                $as = $matches[2];
                $code = "if (isset({$items}) && is_iterable({$items})): foreach ({$items} as {$as}):\n";
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
            $viewName = 'components/' . str_replace('.', '/', $tagName);
        }

        $this->dependencies[] = $viewName;

        $code = "\$__props = [];\n";
        foreach ($node->props as $name => $prop) {
            if ($prop['dynamic']) {
                $val = $this->transpiler->transpile($prop['value']);
                $code .= "\$__props['{$name}'] = {$val};\n";
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
}
