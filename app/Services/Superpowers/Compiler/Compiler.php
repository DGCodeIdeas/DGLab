<?php

namespace DGLab\Services\Superpowers\Compiler;

use DGLab\Core\Application;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\SlotNode;
use DGLab\Services\Superpowers\Parser\Nodes\SectionNode;
use DGLab\Services\Superpowers\Parser\Nodes\YieldNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode;
use DGLab\Services\Superpowers\Parser\Nodes\FragmentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\SetupNode;
use DGLab\Services\Superpowers\Parser\Nodes\MountNode;
use DGLab\Services\Superpowers\Parser\Nodes\RenderedNode;
use DGLab\Services\Superpowers\Parser\Nodes\CleanupNode;
use DGLab\Services\Superpowers\Transpiler\ExpressionTranspiler;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;

class Compiler
{
    private ExpressionTranspiler $tr;
    private array $deps = [];
    private bool $reac = false;

    public function __construct()
    {
        $this->tr = new ExpressionTranspiler();
    }

    public function compile(array $ast): string
    {
        $this->deps = [];
        $this->reac = false;
        $code = "<?php\n\$__ctx = [];\n\$__persisted = [];\n";
        $code .= "if (isset(\$__state)) \$this->getEngine('super.php')->getInterpreter()->getState()->import(\$__state);\n";
        $code .= "foreach (\$data as \$__k => \$__v) if (substr(\$__k, 0, 2) !== '__') \$__ctx[\$__k] = \$__v;\n";
        $code .= $this->cLife($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);
        $code .= "if (isset(\$__action)) {\n  (function() use (&\$__ctx, \$__action, &\$__persisted) {\n    extract(\$__ctx, EXTR_REFS);\n";
        $code .= "    if (isset(\$\$__action) && is_callable(\$\$__action)) {\n      \$\$__action();\n      \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) ";
        $code .= "if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__action', '__vars', '__persisted'])) \$__ctx[\$__k] = \$__v;\n    }\n  })->call(\$this);\n}\n";
        $code .= "ob_start();\n" . $this->cN($ast) . "\$__output = ob_get_clean();\n";
        $code .= "ob_start();\n" . $this->cLife($ast, [RenderedNode::class]) . "\$__output .= ob_get_clean();\n";
        if ($this->reac) {
            $code .= "\$__state_obj = \$this->getEngine('super.php')->getInterpreter()->getState();\n";
            $code .= "foreach (\$__ctx as \$__k => \$__v) if (!is_callable(\$__v)) \$__state_obj->set(\$__k, \$__v);\n";
            $code .= "\$__enc = \$__state_obj->export();\n";
            $code .= "\$__view_attr = isset(\$__view) ? \" s-view='\$__view'\" : \"\";\n";
            $code .= "\$__output = \"<div s-data='\$__enc' s-id='\" . uniqid() . \"'\$__view_attr>\$__output</div>\";\n";
        }
        $code .= "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class);\n";
        $code .= "foreach (\$__persisted as \$__pvar) if (array_key_exists(\$__pvar, \$__ctx)) if (\$__g->get(\$__pvar, '__ABSENT__') !== \$__ctx[\$__pvar]) \$__g->set(\$__pvar, \$__ctx[\$__pvar]);\n";
        $code .= "if (isset(\$__extendedLayout)) return \$this->render(\$__extendedLayout, array_merge(\$data, \$__ctx), null);\nreturn \$__output;\n";
        return $code;
    }

    public function getDependencies(): array
    {
        return array_unique($this->deps);
    }

    private function cLife(array $nodes, array $types): string
    {
        $c = "";
        foreach ($nodes as $n) {
            foreach ($types as $t) {
                if ($n instanceof $t) {
                    if ($n instanceof ExtendsNode) {
                        $c .= "/* line:$n->line */ \$__extendedLayout = " . var_export($n->layout, true) . ";\n";
                    } else {
                        $c .= "/* line:$n->line */ (function() use (&\$__ctx, &\$__persisted) {\n  extract(\$__ctx, EXTR_REFS);\n";
                        $c .= "  " . $this->processDirectivesInPHP($n->code) . "\n";
                        $c .= "  \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) ";
                        $c .= "if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__vars', '__persisted'])) \$__ctx[\$__k] = \$__v;\n})->call(\$this);\n";
                    }
                }
            }
            if (isset($n->children)) {
                $c .= $this->cLife($n->children, $types);
            }
        }
        return $c;
    }

    private function processDirectivesInPHP(string $code): string
    {
        $code = preg_replace_callback('/@global\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*[\'"](.*?)[\'"]\s*)?\)/', function ($m) {
            $k = $m[1];
            $v = $m[2] ?? $k;
            return "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class); \${$v} = \$__g->get('{$k}');";
        }, $code);
        return preg_replace_callback('/@persist\s*\(\s*\$(.*?)\s*\)/', function ($m) {
            $v = $m[1];
            return "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class); \$__persisted[] = '{$v}'; if (\$__g->get('{$v}', '__ABSENT__') !== '__ABSENT__') \${$v} = \$__g->get('{$v}');";
        }, $code);
    }

    private function cN(array $nodes): string
    {
        $c = "";
        foreach ($nodes as $n) {
            $c .= $this->cOne($n);
        } return $c;
    }

    private function cOne(Node $n): string
    {
        if ($n instanceof FragmentNode) {
            return "/* line:$n->line */ echo '<div data-fragment=\"' . \\DGLab\\Core\\View::e(" . var_export($n->id, true) . ") . '\">'; " . $this->cN($n->children) . " echo '</div>';\n";
        }
        if ($n instanceof TextNode) {
            return "/* line:$n->line */ echo " . var_export($n->content, true) . ";\n";
        }
        if ($n instanceof ExpressionNode) {
            $t = $this->tr->transpile($n->expression, '$__ctx');
            return "/* line:$n->line */ echo " . ($n->escaped ? "\\DGLab\\Core\\View::e((string)($t))" : "(string)($t)") . ";\n";
        }
        if ($n instanceof SectionNode) {
            $c = "/* line:$n->line */ \$this->section(" . var_export($n->name, true) . ");\n";
            $c .= "echo (function() use (&\$__ctx) { ob_start(); " . $this->cN($n->children) . " return ob_get_clean(); })->call(\$this);\n";
            $c .= "\$this->endSection();\n";
            return $c;
        }
        if ($n instanceof YieldNode) {
            return "/* line:$n->line */ echo \$this->yield(" . var_export($n->name, true) . ", " . var_export($n->default, true) . ");\n";
        }
        if ($n instanceof DirectiveNode) {
            if ($n->name === 'prefetch') {
                $v = $n->expression ? "(" . $this->tr->transpile($n->expression, '$__ctx') . ")" : "'true'";
                return "/* line:$n->line */ echo ' data-prefetch=\"' . \\DGLab\\Core\\View::e((string)$v) . '\"';\n";
            }
            if ($n->name === 'transition') {
                $v = $n->expression ? "(" . $this->tr->transpile($n->expression, '$__ctx') . ")" : "'fade'";
                return "/* line:$n->line */ echo ' data-transition=\"' . \\DGLab\\Core\\View::e((string)$v) . '\"';\n";
            }
            if ($n->name === 'if') {
                $html = "/* line:$n->line */ if (" . $this->tr->transpile($n->expression, '$__ctx') . "): \n";
                foreach ($n->children as $child) {
                    if ($child instanceof DirectiveNode && $child->name === 'else') {
                        $html .= " else: \n";
                    } elseif ($child instanceof DirectiveNode && $child->name === 'elseif') {
                        $html .= " elseif (" . $this->tr->transpile($child->expression, '$__ctx') . "): \n";
                    } else {
                        $html .= $this->cOne($child);
                    }
                }
                return $html . " endif;\n";
            }
            if ($n->name === 'foreach') {
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $n->expression, $m);
                $expr = $this->tr->transpile($m[1], '$__ctx');
                $itemVar = trim($m[2], '$ ');
                $c = "/* line:$n->line */ if (is_iterable($expr)): foreach ($expr as \$__key => \$__val): \$__old_val = \$__ctx['$itemVar'] ?? null; \$__ctx['$itemVar'] = &\$__val; ";
                $c .= $this->cN($n->children) . "\$__ctx['$itemVar'] = \$__old_val; endforeach; endif;\n";
                return $c;
            }
        }
        if ($n instanceof ComponentNode) {
            $vn = str_starts_with($n->tagName, 'layout:') ? 'layouts/' . substr($n->tagName, 7) : $n->tagName;
            $this->deps[] = $vn;
            $c = "/* line:$n->line */ \$__p = [];\n";
            foreach ($n->props as $name => $p) {
                $c .= "\$__p['$name'] = " . ($p['dynamic'] ? "(" . $this->tr->transpile($p['value'], '$__ctx') . ")" : var_export($p['value'], true)) . ";\n";
            }
            $c .= "\$__p['slot'] = (function() use (&\$__ctx, &\$__p) { ob_start(); \n";
            $ds = "";
            foreach ($n->children as $child) {
                if ($child instanceof SlotNode) {
                    $c .= "\$__p['$child->name'] = (function() use (&\$__ctx) { ob_start(); " . $this->cN($child->children) . " return ob_get_clean(); })->call(\$this);\n";
                } else {
                    $ds .= $this->cOne($child);
                }
            }
            return $c . $ds . " return ob_get_clean(); })->call(\$this);\necho \$this->render('$vn', \$__p, null);\n";
        }
        if ($n instanceof ReactiveNode) {
            $this->reac = true;
            $c = "/* line:$n->line */ echo '<$n->tagName';\n";
            foreach ($n->attributes as $name => $val) {
                $c .= "echo ' $name=\"' . \\DGLab\\Core\\View::e((string)" . var_export($val, true) . ") . '\"';\n";
            }
            foreach ($n->reactiveAttributes as $ev => $ac) {
                $c .= "echo ' s-on:$ev=\"' . \\DGLab\\Core\\View::e((string)" . var_export($ac, true) . ") . '\"';\n";
            }
            return $c . "echo \">\"; " . $this->cN($n->children) . " echo \"</$n->tagName>\";\n";
        }
        return "";
    }
}
