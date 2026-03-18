<?php

namespace DGLab\Services\Superpowers;

use DGLab\Core\Contracts\ViewEngineInterface;
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

    public function __construct()
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->interpreter = new Interpreter();
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

        // TODO: In Phase 6, we will implement Compiled mode.
        // For Phase 1, we use the Interpreted mode (required for dev/transient).

        $tokens = $this->lexer->tokenize($content);
        $ast = $this->parser->parse($tokens);

        return $this->interpreter->interpret($ast, $data);
    }
}
