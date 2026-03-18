<?php
// Compiled SuperPHP Template
if (isset($__state)) {
    $this->getEngine()->getInterpreter()->getState()->import($__state);
}
foreach ($data as $k => $v) { if (substr($k, 0, 2) !== '__') $this->getEngine()->getInterpreter()->getState()->set($k, $v); }
if (isset($__action)) {
    $__scope = $this->getEngine()->getInterpreter()->getState()->all();
    if (isset($__scope[$__action]) && is_callable($__scope[$__action])) {
        $__scope[$__action]();
        foreach ($__scope as $k => $v) $this->getEngine()->getInterpreter()->getState()->set($k, $v);
    }
}
ob_start();
echo '<h1>';
echo \DGLab\Core\View::e((string)((function() { $scope = $this->getEngine()->getInterpreter()->getState()->all(); extract($scope); return $title; })->call($this)));
echo '</h1>';
$__output = ob_get_clean();
ob_start();
$__output .= ob_get_clean();
echo $__output;
if (isset($__extendedLayout)) {
    return $this->render($__extendedLayout, array_merge($data, $this->getEngine()->getInterpreter()->getState()->all()), null);
}
