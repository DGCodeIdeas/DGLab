<?php

namespace DGLab\Services\Superpowers;

use DGLab\Core\Contracts\ViewEngineInterface;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Interpreter\Interpreter;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;

/**
 * Class SuperpowersEngine
 *
 * The primary entry point for the SuperPHP engine.
 */
class SuperpowersEngine implements ViewEngineInterface
{
    private Lexer $lexer;
    private Parser $parser;
    private Interpreter $interpreter;
    private View $view;

    public function __construct(View $view)
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->view = $view;
        $this->interpreter = new Interpreter($view);
    }

    /**
     * Render the .super.php view file.
     *
     * @param string $path
     * @param array $data
     * @return string
     */
    public function render(string $path, array $data = []): string
    {
        $content = file_get_contents($path);

        $tokens = $this->lexer->tokenize($content);
        $ast = $this->parser->parse($tokens);

        return $this->interpreter->interpret($ast, $data);
    }
}
