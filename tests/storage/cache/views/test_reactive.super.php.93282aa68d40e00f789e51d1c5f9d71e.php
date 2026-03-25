<?php

$__ctx = [];
if (isset($__state)) {
    $this->getEngine('super.php')->getInterpreter()->getState()->import($__state);
}
foreach ($data as $__k => $__v) {
    if (substr($__k, 0, 2) !== '__') {
        $__ctx[$__k] = $__v;
    }
}
if (isset($__action)) {
    (function () use (&$__ctx, $__action) {
        extract($__ctx, EXTR_REFS);
        if (isset($$__action) && is_callable($$__action)) {
            $$__action();
            $__vars = get_defined_vars();
            foreach ($__vars as $__k => $__v) {
                if (!in_array($__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__action', '__vars'])) {
                          $__ctx[$__k] = $__v;
                }
            }
        }
    })->call($this);
}
ob_start();
/* line:1 */ echo '<button';
echo ' s-loading.class="' . \DGLab\Core\View::e((string)'busy') . '"';
echo ' s-optimistic="' . \DGLab\Core\View::e((string)'hide:#msg') . '"';
echo ' s-on:click="' . \DGLab\Core\View::e((string)'increment') . '"';
echo ">";
/* line:1 */ echo 'Click';
 echo "</button>";
/* line:1 */ echo '<div id="msg">Msg</div>';
$__output = ob_get_clean();
ob_start();
$__output .= ob_get_clean();
$__state_obj = $this->getEngine('super.php')->getInterpreter()->getState();
foreach ($__ctx as $__k => $__v) {
    if (!is_callable($__v)) {
        $__state_obj->set($__k, $__v);
    }
}
$__enc = $__state_obj->export();
$__view_attr = isset($__view) ? " s-view='$__view'" : "";
$__output = "<div s-data='$__enc' s-id='" . uniqid() . "'$__view_attr>$__output</div>";
if (isset($__extendedLayout)) {
    return $this->render($__extendedLayout, array_merge($data, $__ctx), null);
}
return $__output;
