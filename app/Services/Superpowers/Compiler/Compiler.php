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

        $code = "<?php\n";
        $code .= "\$__ctx = [];\n";
        $code .= "if (isset(\$__state)) \$this->getEngine('super.php')->getInterpreter()->getState()->import(\$__state);\n";
        $code .= "foreach (\$data as \$__k => \$__v) if (substr(\$__k, 0, 2) !== '__') \$__ctx[\$__k] = \$__v;\n";

        $lifecycle = $this->cLife($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);
        $code .= $lifecycle;

        $code .= "if (isset(\$__action)) {\n";
        $code .= "  (function() use (&\$__ctx, \$__action) {\n";
        $code .= "    extract(\$__ctx, EXTR_REFS);\n";
        $code .= "    if (isset(\$\$__action) && is_callable(\$\$__action)) {\n";
        $code .= "      \$\$__action();\n";
        $code .= "      \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) ";
        $code .= "if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__action', '__vars'])) ";
        $code .= "  \$__ctx[\$__k] = \$__v;\n";
        $code .= "    }\n";
        $code .= "  })->call(\$this);\n";
        $code .= "}\n";

        $code .= "ob_start();\n";
        $code .= $this->cN($ast);
        $code .= "\$__output = ob_get_clean();\n";

        $rendered = $this->cLife($ast, [RenderedNode::class]);
        $code .= "ob_start();\n" . $rendered . "\$__output .= ob_get_clean();\n";

        if ($this->reac) {
            $code .= "\$__state_obj = \$this->getEngine('super.php')->getInterpreter()->getState();\n";
            $code .= "foreach (\$__ctx as \$__k => \$__v) if (!is_callable(\$__v)) \$__state_obj->set(\$__k, \$__v);\n";
            $code .= "\$__enc = \$__state_obj->export();\n";
            $code .= "\$__view_attr = isset(\$__view) ? \" s-view='\$__view'\" : \"\";\n";
            $code .= "\$__output = \"<div s-data='\$__enc' s-id='\" . uniqid() . \"'\$__view_attr>\$__output</div>\";\n";
        }

        $code .= "if (isset(\$__extendedLayout)) return \$this->render(\$__extendedLayout, array_merge(\$data, \$__ctx), null);\n";
        $code .= "return \$__output;\n";

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
                        $c .= "/* line:$n->line */ (function() use (&\$__ctx) {\n";
                        $c .= "  extract(\$__ctx, EXTR_REFS);\n";
                        $c .= "  " . $this->processDirectivesInPHP($n->code) . "\n";
                        $c .= "  \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) ";
                        $c .= "if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__vars'])) ";
                        $c .= "  \$__ctx[\$__k] = \$__v;\n";
                        $c .= "})->call(\$this);\n";
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
        // Replace @global('key', 'var') with PHP code
        return preg_replace_callback('/@global\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*[\'"](.*?)[\'"]\s*)?\)/', function($m) {
            $key = $m[1];
            $var = $m[2] ?? $key;
            return "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStore::class); \${$var} = \$__g->get('{$key}');";
        }, $code);
    }

    private function cN(array $nodes): string
    {
        $c = "";
        foreach ($nodes as $n) {
            $c .= $this->cOne($n);
        }
        return $c;
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
                $val = $n->expression ? "(" . $this->tr->transpile($n->expression, '$__ctx') . ")" : "'true'";
                return "/* line:$n->line */ echo ' data-prefetch=\"' . \\DGLab\\Core\\View::e((string)$val) . '\"';\n";
            }
            if ($n->name === 'transition') {
                $val = $n->expression ? "(" . $this->tr->transpile($n->expression, '$__ctx') . ")" : "'fade'";
                return "/* line:$n->line */ echo ' data-transition=\"' . \\DGLab\\Core\\View::e((string)$val) . '\"';\n";
            }
            if ($n->name === 'if') {
                $expr = $this->tr->transpile($n->expression, '$__ctx');
                $html = "/* line:$n->line */ if ($expr): \n";
                foreach ($n->children as $child) {
                    if ($child instanceof DirectiveNode && $child->name === 'else') {
                        $html .= " else: \n";
                    } elseif ($child instanceof DirectiveNode && $child->name === 'elseif') {
                        $html .= " elseif (" . $this->tr->transpile($child->expression, '$__ctx') . "): \n";
                    } else {
                        $html .= $this->cOne($child);
                    }
                }
                $html .= " endif;\n";
                return $html;
            }
            if ($n->name === 'foreach') {
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $n->expression, $m);
                $expr = $this->tr->transpile($m[1], '$__ctx');
                $itemVar = trim($m[2], '$ ');
                $c = "/* line:$n->line */ if (is_iterable($expr)): ";
                $c .= "foreach ($expr as \$__key => \$__val): ";
                // Push local scope
                $c .= "\$__old_val = \$__ctx['$itemVar'] ?? null; \$__ctx['$itemVar'] = &\$__val; ";
                $c .= $this->cN($n->children);
                // Pop local scope
                $c .= "\$__ctx['$itemVar'] = \$__old_val; ";
                $c .= "endforeach; endif;\n";
                return $c;
            }
            if ($n->name === 'global') {
                $p = explode(',', $n->expression);
                $key = trim($p[0], "'\" ");
                $var = isset($p[1]) ? trim($p[1], "'\"$ ") : $key;
                $c = "/* line:$n->line */ \$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStore::class);\n";
                $c .= "\$__ctx['$var'] = \$__g->get('$key');\n";
                return $c;
            }
        }
        if ($n instanceof ComponentNode) {
            $vn = str_starts_with($n->tagName, 'layout:') ? 'layouts/' . substr($n->tagName, 7) : $n->tagName;
            $this->deps[] = $vn;
            $c = "/* line:$n->line */ \$__p = [];\n";
            foreach ($n->props as $name => $p) {
                $val = $p['dynamic'] ? "(" . $this->tr->transpile($p['value'], '$__ctx') . ")" : var_export($p['value'], true);
                $c .= "\$__p['$name'] = $val;\n";
            }
            $c .= "\$__p['slot'] = (function() use (&\$__ctx, &\$__p) { \n";
            $c .= "  ob_start();\n";
            $default_slot_content = "";
            foreach ($n->children as $child) {
                if ($child instanceof SlotNode) {
                    $c .= "  \$__p['$child->name'] = (function() use (&\$__ctx) { ob_start(); " . $this->cN($child->children) . " return ob_get_clean(); })->call(\$this);\n";
                } else {
                    $default_slot_content .= $this->cOne($child);
                }
            }
            $c .= $default_slot_content;
            $c .= "  return ob_get_clean();\n";
            $c .= "})->call(\$this);\n";
            $c .= "echo \$this->render('$vn', \$__p, null);\n";
            return $c;
        }
        if ($n instanceof ReactiveNode) {
            $this->reac = true;
            $c = "/* line:$n->line */ echo '<$n->tagName';\n";
            foreach ($n->attributes as $name => $val) {
                $val_expr = var_export($val, true);
                $c .= "echo ' $name=\"' . \\DGLab\\Core\\View::e((string)$val_expr) . '\"';\n";
            }
            foreach ($n->reactiveAttributes as $ev => $ac) {
                $ac_expr = var_export($ac, true);
                $c .= "echo ' s-on:$ev=\"' . \\DGLab\\Core\\View::e((string)$ac_expr) . '\"';\n";
            }
            $c .= "echo \">\"; " . $this->cN($n->children) . " echo \"</$n->tagName>\";\n";
            return $c;
        }
        return "";
    }
}
