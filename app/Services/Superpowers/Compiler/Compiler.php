<?php

namespace DGLab\Services\Superpowers\Compiler;

use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\SlotNode;
use DGLab\Services\Superpowers\Parser\Nodes\SectionNode;
use DGLab\Services\Superpowers\Parser\Nodes\YieldNode;
use DGLab\Services\Superpowers\Parser\Nodes\FragmentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode;
use DGLab\Services\Superpowers\Parser\Nodes\SetupNode;
use DGLab\Services\Superpowers\Parser\Nodes\MountNode;
use DGLab\Services\Superpowers\Parser\Nodes\RenderedNode;
use DGLab\Services\Superpowers\Parser\Nodes\CleanupNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
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

        $c = "<?php\n";
        $c .= "\$__ctx = [];\n";
        $c .= "\$__persisted = [];\n";
        $c .= "if (isset(\$__state)) { if (is_string(\$__state)) \$this->getEngine('super.php')->getInterpreter()->getState()->import(\$__state); else \$this->getEngine('super.php')->getInterpreter()->getState()->merge(\$__state); }\n";
        $c .= "foreach (\$data as \$__k => \$__v) if (substr(\$__k, 0, 2) !== '__') \$__ctx[\$__k] = \$__v;\n";

        $extends = null;
        $filteredAst = [];
        foreach ($ast as $n) {
            if ($n instanceof ExtendsNode) {
                $extends = $n->layout;
            } else {
                $filteredAst[] = $n;
            }
        }

        if ($extends) {
            $c .= "/* line:1 */ \$__extendedLayout = " . var_export($extends, true) . ";\n";
        }

        foreach ($filteredAst as $n) {
            if ($n instanceof SetupNode) {
                $c .= "/* line:$n->line */ (function() use (&\$__ctx, &\$__persisted) {\n";
                $c .= "  extract(\$__ctx, EXTR_REFS);\n";
                $c .= "  " . $this->processDirectivesInPHP($n->code) . "\n";
                $c .= "  \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__vars', '__persisted'])) \$__ctx[\$__k] = \$__v;\n";
                $c .= "})->call(\$this);\n";
            }
        }

        $c .= "if (isset(\$__action)) {\n";
        $c .= "  (function() use (&\$__ctx, \$__action, &\$__persisted) {\n";
        $c .= "    extract(\$__ctx, EXTR_REFS);\n";
        $c .= "    if (isset(\$\$__action) && is_callable(\$\$__action)) {\n";
        $c .= "      \$\$__action();\n";
        $c .= "      \$__vars = get_defined_vars(); foreach (\$__vars as \$__k => \$__v) if (!in_array(\$__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', \$__action, '__vars', '__persisted'])) \$__ctx[\$__k] = \$__v;\n";
        $c .= "    }\n";
        $c .= "  })->call(\$this);\n";
        $c .= "}\n";

        $c .= "\$this->trigger('before_render', \$__ctx);\n";
        $c .= "ob_start();\n";
        $c .= $this->cLife($filteredAst, ['mount', 'rendered', 'cleanup']);
        $c .= "echo (function() use (&\$__ctx) { ob_start(); \n";
        $c .= "extract(\$__ctx, EXTR_REFS);\n";
        $c .= $this->cN($filteredAst);
        $c .= "return ob_get_clean(); })->call(\$this);\n";

        $c .= "\$__output = ob_get_clean();\n";
        $c .= "ob_start();\n";
        $c .= "\$__output .= ob_get_clean();\n";
        $c .= "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class);\n";
        $c .= "foreach (\$__persisted as \$__pvar) if (array_key_exists(\$__pvar, \$__ctx)) if (\$__g->get(\$__pvar, '__ABSENT__') !== \$__ctx[\$__pvar]) \$__g->set(\$__pvar, \$__ctx[\$__pvar]);\n";

        $c .= "\$__st = \$this->getEngine('super.php')->getInterpreter()->getState();\n";
        $c .= "if (\$__st->isModified()) {\n";
        $c .= "  \$enc = \$__st->export();\n";
        $c .= "  \$vn = \$data['__view'] ?? null;\n";
        $c .= "  \$va = \$vn ? \" s-view='{\$vn}'\" : \"\";\n";
        $c .= "  \$__output = \"<div s-data='{\$enc}' s-id='\" . uniqid() . \"'{\$va}>{\$__output}</div>\";\n";
        $c .= "}\n";

        $c .= "if (isset(\$__extendedLayout)) return \$this->render(\$__extendedLayout, array_merge(\$data, \$__ctx), null);\n";
        $c .= "return \$__output;\n";

        return $c;
    }

    public function getDependencies(): array
    {
        return $this->deps;
    }

    private function cLife(array $nodes, array $types): string
    {
        $c = "";
        foreach ($nodes as $n) {
            foreach ($types as $t) {
                $class = "DGLab\\Services\\Superpowers\\Parser\\Nodes\\" . ucfirst($t) . "Node";
                if ($n instanceof $class) {
                    $c .= "\$this->on('$t', (function() use (&\$__ctx) { extract(\$__ctx, EXTR_REFS); " . $this->processDirectivesInPHP($n->code) . " })->bindTo(\$this));\n";
                }
            }
            if (isset($n->children) && is_array($n->children)) {
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
            return "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class); \${$v} = \$__g->get('{$k}'); \$__ctx['{$v}'] = \${$v}; \$this->getEngine('super.php')->getInterpreter()->getState()->set('{$v}', \${$v});";
        }, $code);
        return preg_replace_callback('/@persist\s*\(\s*\$(.*?)\s*\)/', function ($m) {
            $v = $m[1];
            return "\$__g = \\DGLab\\Core\\Application::getInstance()->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStoreInterface::class); \$__persisted[] = '{$v}'; if (\$__g->get('{$v}', '__ABSENT__') !== '__ABSENT__') { \${$v} = \$__g->get('{$v}'); \$__ctx['{$v}'] = \${$v}; \$this->getEngine('super.php')->getInterpreter()->getState()->set('{$v}', \${$v}); }";
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
        if ($n instanceof SetupNode || $n instanceof MountNode || $n instanceof RenderedNode || $n instanceof CleanupNode) {
            return "";
        }

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
            $c .= "echo (function() use (&\$__ctx) { ob_start(); extract(\$__ctx, EXTR_REFS); " . $this->cN($n->children) . " return ob_get_clean(); })->call(\$this);\n";
            $c .= "\$this->endSection();\n";
            return $c;
        }
        if ($n instanceof YieldNode) {
            return "/* line:$n->line */ echo \$this->yield(" . var_export($n->name, true) . ", " . var_export($n->default, true) . ");\n";
        }
        if ($n instanceof DirectiveNode) {
            if ($n->name === 'global') {
                return "/* line:$n->line */ " . $this->processDirectivesInPHP("@global($n->expression)") . " \$this->getEngine('super.php')->getInterpreter()->getState()->markModified();\n";
            }
            if ($n->name === 'persist') {
                return "/* line:$n->line */ " . $this->processDirectivesInPHP("@persist($n->expression)") . " \$this->getEngine('super.php')->getInterpreter()->getState()->markModified();\n";
            }

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
                $items = $this->tr->transpile($m[1], '$__ctx');
                $as = $m[2];
                $asParts = explode('=>', $as);
                $asName = trim(end($asParts), '$ ');
                $keyName = count($asParts) > 1 ? trim($asParts[0], '$ ') : null;

                $loop = "/* line:$n->line */ if (is_iterable($items)) foreach ($items as $as): \n";
                if ($keyName) {
                    $loop .= " \$__ctx['$keyName'] = " . trim($asParts[0]) . ";\n";
                }
                $loop .= " \$__ctx['$asName'] = " . trim(end($asParts)) . ";\n";
                $loop .= $this->cN($n->children) . " endforeach;\n";
                return $loop;
            }
        }
        if ($n instanceof ComponentNode) {
            $tName = str_replace(':', '/', $n->tagName);
            $vn = str_starts_with($tName, 'layout/') ? 'layouts/' . substr($tName, 7) : (str_contains($tName, '.') ? $tName : 'components/' . $tName);
            $this->deps[] = $vn;
            $c = "/* line:$n->line */ \$__p = [];\n";
            foreach ($n->props as $name => $p) {
                $c .= "\$__p['$name'] = " . ($p['dynamic'] ? "(" . $this->tr->transpile($p['value'], '$__ctx') . ")" : var_export($p['value'], true)) . ";\n";
            }
            $c .= "\$__p['slot'] = (function() use (&\$__ctx, &\$__p) { ob_start(); extract(\$__ctx, EXTR_REFS); \n";
            $ds = "";
            foreach ($n->children as $child) {
                if ($child instanceof SlotNode) {
                    $c .= "\$__p['$child->name'] = (function() use (&\$__ctx) { ob_start(); extract(\$__ctx, EXTR_REFS); " . $this->cN($child->children) . " return ob_get_clean(); })->call(\$this);\n";
                } else {
                    $ds .= $this->cOne($child);
                }
            }
            return $c . $ds . " return ob_get_clean(); })->call(\$this);\necho \$this->render('$vn', \$__p, null);\n";
        }
        if ($n instanceof ReactiveNode) {
            $this->reac = true;
            $c = "/* line:$n->line */ \$this->getEngine('super.php')->getInterpreter()->getState()->markModified();\n";
            $c .= "echo '<$n->tagName';\n";
            foreach ($n->attributes as $name => $v) {
                $c .= "echo ' $name=\"' . \\DGLab\\Core\\View::e((string)" . var_export($v, true) . ") . '\"';\n";
            }
            foreach ($n->reactiveAttributes as $e => $a) {
                $c .= "echo ' s-on:$e=\"' . \\DGLab\\Core\\View::e((string)" . var_export($a, true) . ") . '\"';\n";
            }
            return $c . " echo \">\"; " . $this->cN($n->children) . " echo \"</$n->tagName>\";\n";
        }
        return "";
    }
}
