<?php
if (isset($__state)) $this->getEngine()->getInterpreter()->getState()->import($__state);
foreach ($data as $k => $v) if (substr($k, 0, 2) !== '__') $this->getEngine()->getInterpreter()->getState()->set($k, $v);
if (isset($__action)) { $s = $this->getEngine()->getInterpreter()->getState()->all(); if (isset($s[$__action]) && is_callable($s[$__action])) { $s[$__action](); foreach ($s as $k => $v) $this->getEngine()->getInterpreter()->getState()->set($k, $v); } }
ob_start();
/* line:1 */ echo '<h1>';
/* line:1 */ echo \DGLab\Core\View::e((string)((function() { extract($this->getEngine()->getInterpreter()->getState()->all()); return $title; })->call($this)));
/* line:1 */ echo '</h1>';

$__output = ob_get_clean();
ob_start();

$__output .= ob_get_clean();
echo $__output;
if (isset($__extendedLayout)) return $this->render($__extendedLayout, array_merge($data, $this->getEngine()->getInterpreter()->getState()->all()), null);
