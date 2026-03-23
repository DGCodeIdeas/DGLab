<?php

namespace DGLab\Services\Superpowers\Compiler;

use DGLab\Core\Application;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\TextNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ReactiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\SectionNode;
use DGLab\Services\Superpowers\Parser\Nodes\YieldNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExtendsNode;
use DGLab\Services\Superpowers\Parser\Nodes\FragmentNode;
use DGLab\Services\Superpowers\Parser\Nodes\SlotNode;
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
        $body = $this->cN($ast);
        $lifecycle = $this->cLife($ast, [SetupNode::class, MountNode::class, ExtendsNode::class]);
        $rendered = $this->cLife($ast, [RenderedNode::class]);

        $code = "<?php\n";
        $code .= "if (isset(\$__state)) $this->getEngine()->getInterpreter()->getState()->import(\$__state);\n";
        $code .= "foreach (\$data as \$k => \$v) if (substr(\$k, 0, 2) !== '__') ";
        $code .= "$this->getEngine()->getInterpreter()->getState()->set(\$k, \$v);\n";
        $code .= $lifecycle;
        $code .= "if (isset(\$__action)) { ";
        $code .= "\$s = $this->getEngine()->getInterpreter()->getState()->all(); ";
        $code .= "if (isset(\$s[\$__action]) && is_callable(\$s[\$__action])) { ";
        $code .= "\$s[\$__action](); foreach (\$s as \$k => \$v) ";
        $code .= "$this->getEngine()->getInterpreter()->getState()->set(\$k, \$v); } }\n";
        $code .= "ob_start();\n$body\n\$__output = ob_get_clean();\n";
        $code .= "ob_start();\n$rendered\n\$__output .= ob_get_clean();\n";

        if ($this->reac) {
            $code .= "\$__enc = $this->getEngine()->getInterpreter()->getState()->export();\n";
            $code .= "\$__v = isset(\$__view) ? \" s-view='\$__view'\" : \"\";\n";
            $code .= "echo \"<div s-data='\$__enc' s-id='\" . uniqid() . \"'\$__v>\$__output</div>\";\n";
        } else {
            $code .= "echo \$__output;\n";
        }

        $code .= "if (isset(\$__extendedLayout)) return $this->render(\$__extendedLayout, ";
        $code .= "array_merge(\$data, $this->getEngine()->getInterpreter()->getState()->all()), null);\n";
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
                        $c .= "/* line:$n->line */ \$__extendedLayout = '$n->layout';\n";
                    } else {
                        $c .= "/* line:$n->line */ extract($this->getEngine()->getInterpreter()->getState()->all()); $n->code\n";
                        $c .= " $vars = get_defined_vars(); foreach ($vars as $k => $v) ";
                        $c .= "if (!in_array($k, ['this', 'data', 'code', 'vars'])) ";
                        $c .= "$this->getEngine()->getInterpreter()->getState()->set($k, $v);\n";
                    }
                }
            }
            if (isset($n->children)) {
                $c .= $this->cLife($n->children, $types);
            }
        }
        return $c;
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
            return "/* line:$n->line */ echo '<div data-fragment=\"' . \DGLab\Core\View::e($n->id) . '\">'; " . $this->cN($n->children) . " echo '</div>';
";
        }
        if ($n instanceof TextNode) {
            return "/* line:$n->line */ echo " . var_export($n->content, true) . ";\n";
        }
        if ($n instanceof ExpressionNode) {
            $t = $this->tr->transpile($n->expression);
            $ev = "((function() { extract($this->getEngine()->getInterpreter()->getState()->all()); return $t; })->call($this))";
            return "/* line:$n->line */ echo " . ($n->escaped ? "\\DGLab\\Core\\View::e((string)$ev)" : "(string)$ev") . ";\n";
        }
        if ($n instanceof DirectiveNode) {
                        if ($n->name === 'prefetch') {
                $val = $n->expression ? "((function() { extract($this->getEngine()->getInterpreter()->getState()->all()); return $n->expression; })->call($this))" : "'true'";
                return "/* line:$n->line */ echo ' data-prefetch=\"' . \DGLab\Core\View::e((string)$val) . '\"';
";
            }
            if ($n->name === 'transition') {
                $val = $n->expression ? "((function() { extract($this->getEngine()->getInterpreter()->getState()->all()); return $n->expression; })->call($this))" : "'fade'";
                return "/* line:$n->line */ echo ' data-transition=\"' . \DGLab\Core\View::e((string)$val) . '\"';
";
            }
            if ($n->name === 'if') {
                $expr = $this->tr->transpile($n->expression);
                return "/* line:$n->line */ if ((function() { ";
                $return = "extract($this->getEngine()->getInterpreter()->getState()->all()); return $expr; ";
                return "/* line:$n->line */ if ((function() { " . $return . "})->call($this)): " . $this->cN($n->children) . " endif;\n";
            }
            if ($n->name === 'foreach') {
                preg_match('/^\s*(.*?)\s+as\s+(.*?)\s*$/s', $n->expression, $m);
                $expr = $this->tr->transpile($m[1]);
                $c = "/* line:$n->line */ \$__items = (function() { ";
                $c .= "extract($this->getEngine()->getInterpreter()->getState()->all()); return $expr; ";
                $c .= "})->call($this);\n if (isset(\$__items) && is_iterable(\$__items)): ";
                $c .= "foreach (\$__items as $m[2]): " . $this->cN($n->children) . " endforeach; endif;\n";
                return $c;
            }
            if ($n->name === 'global') {
                $p = explode(',', $n->expression);
                $key = trim($p[0], "'\" ");
                $var = isset($p[1]) ? trim($p[1], "'\"$ ") : $key;
                $c = "/* line:$n->line */ \$__g = \\DGLab\\Core\\Application::getInstance()";
                $c .= "->get(\\DGLab\\Services\\Superpowers\\Runtime\\GlobalStateStore::class);\n ";
                $c .= "$this->getEngine()->getInterpreter()->getState()->set('$var', \$__g->get('$key'));\n";
                return $c;
            }
        }
        if ($n instanceof ComponentNode) {
            $vn = str_starts_with($n->tagName, 'layout:') ? 'layouts/' . substr($n->tagName, 7) : $n->tagName;
            $this->deps[] = $vn;
            $c = "/* line:$n->line */ \$__p = [];\n";
            foreach ($n->props as $name => $p) {
                if ($p['dynamic']) {
                    $val = $this->tr->transpile($p['value']);
                    $c .= "\$__p['$name'] = (function() { ";
                    $c .= "extract($this->getEngine()->getInterpreter()->getState()->all()); return $val; ";
                    $c .= "})->call($this);\n";
                } else {
                    $c .= "\$__p['$name'] = " . var_export($p['value'], true) . ";\n";
                }
            }
            $c .= "ob_start(); " . $this->cN($n->children) . " \$__p['slot'] = ob_get_clean();\n";
            $c .= "echo $this->render('$vn', \$__p, null);\n";
            return $c;
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
            $c .= "echo \">\"; " . $this->cN($n->children) . " echo \"</$n->tagName>\";\n";
            return $c;
        }
        return "";
    }
}
