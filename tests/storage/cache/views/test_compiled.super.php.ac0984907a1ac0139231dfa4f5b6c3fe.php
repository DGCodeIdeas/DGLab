<?php
$__ctx = [];
if (isset($__state)) $this->getEngine('super.php')->getInterpreter()->getState()->import($__state);
foreach ($data as $__k => $__v) if (substr($__k, 0, 2) !== '__') $__ctx[$__k] = $__v;
if (isset($__action)) {
  (function() use (&$__ctx, $__action) {
    extract($__ctx, EXTR_REFS);
    if (isset($$__action) && is_callable($$__action)) {
      $$__action();
      $__vars = get_defined_vars(); foreach ($__vars as $__k => $__v) if (!in_array($__k, ['this', 'data', 'code', 'vars', 'n', 't', 'c', 'nodes', 'types', '__ctx', '__action', '__vars']))   $__ctx[$__k] = $__v;
    }
  })->call($this);
}
ob_start();
/* line:1 */ echo '<h1>';
/* line:1 */ echo \DGLab\Core\View::e((string)(($__ctx['title'] ?? null)));
/* line:1 */ echo '</h1>';
$__output = ob_get_clean();
ob_start();
$__output .= ob_get_clean();
if (isset($__extendedLayout)) return $this->render($__extendedLayout, array_merge($data, $__ctx), null);
return $__output;
