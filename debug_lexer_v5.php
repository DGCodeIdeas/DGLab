<?php
require_once 'vendor/autoload.php';
use DGLab\Services\Superpowers\Lexer\Lexer;
$content = "<s:layout:app>Welcome</s:layout:app>";
$lexer = new Lexer();
$tokens = $lexer->tokenize($content);
foreach ($tokens as $token) {
    echo "TYPE: $token->type, VALUE: $token->value\n";
}
