<?php

namespace DGLab\Services\Superpowers;

use DGLab\Core\Application;
use DGLab\Core\Contracts\ViewEngineInterface;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Interpreter\Interpreter;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;

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
    private Compiler $compiler;
    private View $view;

    public function __construct(View $view)
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->compiler = new Compiler();
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
        $mode = Application::config('superpowers.mode', 'auto');

        if ($mode === 'auto') {
            $mode = Application::config('app.debug') ? 'interpreted' : 'compiled';
        }

        if ($mode === 'interpreted') {
            $content = file_get_contents($path);
            $tokens = $this->lexer->tokenize($content);
            $ast = $this->parser->parse($tokens);
            return $this->interpreter->interpret($ast, $data);
        }

        return $this->renderCompiled($path, $data);
    }

    /**
     * Render a compiled view file.
     */
    private function renderCompiled(string $path, array $data): string
    {
        $cachePath = Application::config('superpowers.cache_path');
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0777, true);
        }

        $hash = md5($path . filemtime($path));
        $compiledFile = $cachePath . '/' . basename($path) . '.' . $hash . '.php';
        $depsFile = $compiledFile . '.deps';

        $shouldRecompile = !file_exists($compiledFile);

        // Dependency invalidation check
        if (!$shouldRecompile && Application::config('superpowers.check_dependencies', true)) {
            if (file_exists($depsFile)) {
                $depsJson = file_get_contents($depsFile);
                $deps = json_decode($depsJson, true);
                if (is_array($deps)) {
                    foreach ($deps as $dep) {
                        try {
                             [$depPath, $engine] = $this->view->resolveView($dep);
                             if (filemtime($depPath) > filemtime($compiledFile)) {
                                 $shouldRecompile = true;
                                 break;
                             }
                        } catch (\Exception $e) {}
                    }
                }
            }
        }

        if ($shouldRecompile) {
            $content = file_get_contents($path);
            $tokens = $this->lexer->tokenize($content);
            $ast = $this->parser->parse($tokens);

            $code = $this->compiler->compile($ast);
            file_put_contents($compiledFile, $code);

            file_put_contents($depsFile, json_encode($this->compiler->getDependencies()));
        }

        $view = $this->view;

        $render = (function() use ($compiledFile, $data) {
            extract($data);
            ob_start();
            try {
                include $compiledFile;
                if (isset($__extendedLayout)) {
                    $content = ob_get_clean();
                    if (!$this->hasSection('content')) {
                         $this->section('content');
                         echo $content;
                         $this->endSection();
                    }
                    return $this->render($__extendedLayout, array_merge($data, get_defined_vars()), null);
                }
            } catch (\Exception $e) {
                ob_get_clean();
                throw $e;
            }
            return ob_get_clean();
        });

        return $render->call($view);
    }
}
