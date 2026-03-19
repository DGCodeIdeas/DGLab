<?php

namespace DGLab\Services\Superpowers;

use DGLab\Core\Application;
use DGLab\Core\Contracts\ViewEngineInterface;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Interpreter\Interpreter;
use DGLab\Services\Superpowers\Lexer\Token;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;
use DGLab\Services\Superpowers\Parser\Linter;
use DGLab\Services\Superpowers\Exceptions\ErrorReporter;
use DGLab\Services\Superpowers\Exceptions\SuperpowersException;
use DGLab\Services\Superpowers\Runtime\SourceMapResolver;
use DGLab\Services\Superpowers\Runtime\DebugCollector;

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
    private ErrorReporter $errorReporter;
    private SourceMapResolver $sourceMapResolver;
    private DebugCollector $debugCollector;
    private Linter $linter;

    public function __construct(View $view)
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->compiler = new Compiler();
        $this->view = $view;
        $this->interpreter = new Interpreter($view);
        $this->errorReporter = new ErrorReporter();
        $this->sourceMapResolver = new SourceMapResolver();
        $this->debugCollector = DebugCollector::getInstance();
        $this->linter = new Linter();
    }

    public function getInterpreter(): Interpreter
    {
        return $this->interpreter;
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
        try {
            $viewName = $this->extractViewName($path);
            $data['__view'] = $viewName;

            $mode = config('superpowers.mode', 'auto');
            if ($mode === 'auto') {
                $mode = config('app.debug') ? 'interpreted' : 'compiled';
            }

            if ($mode === 'interpreted') {
                if (config('app.debug')) {
                     $this->debugCollector->recordView($viewName, $path, $data);
                    if (config('superpowers.linter.on_render', true)) {
                         $this->linter->lint(file_get_contents($path), $path);
                    }
                }
                $content = file_get_contents($path);
                $tokens = $this->lexer->tokenize($content);
                $ast = $this->parser->parse($tokens);
                $output = $this->interpreter->interpret($ast, $data);
                return $this->processReactivity($output);
            }

            return $this->processReactivity($this->renderCompiled($path, $data));
        } catch (\Throwable $e) {
            if (config('app.debug') && !defined('PHPUNIT_RUNNING')) {
                $this->handleException($e, $path);
            }
            throw $e;
        }
    }

    private function handleException(\Throwable $e, string $path): void
    {
        $originalLine = null;
        if (!($e instanceof SuperpowersException)) {
            $originalLine = $this->sourceMapResolver->resolve($e->getFile(), $e->getLine());
        } else {
            $originalLine = $e->getViewLine();
            $path = $e->getViewPath() ?: $path;
        }

        $wrapped = new SuperpowersException(
            $e->getMessage(),
            $path,
            $originalLine,
            null,
            $e->getCode(),
            $e
        );

        echo $this->errorReporter->report($wrapped);
        die();
    }

    private function extractViewName(string $path): string
    {
         $viewPath = rtrim(Application::getInstance()->getBasePath(), '/') . '/resources/views/';
         $name = str_replace($viewPath, '', $path);
         return str_replace('.super.php', '', $name);
    }

    private function processReactivity(string $output): string
    {
        if (config('superpowers.reactivity.enabled', true) && config('superpowers.reactivity.inject_runtime', true)) {
            if (strpos($output, '<body') !== false && strpos($output, 'superpowers.js') === false) {
                 $script = '<script src="/assets/js/superpowers.js"></script>';
                 $output = str_replace('</body>', $script . '</body>', $output);
            }
        }

        if (!defined('PHPUNIT_RUNNING') && config('app.debug') && config('superpowers.debug_overlay.enabled', true)) {
            if (strpos($output, '<body') !== false) {
                 $meta = json_encode($this->debugCollector->getMetadata());
                 $overlay = "<div id='superpowers-debug-overlay' data-meta='{$meta}'></div>";
                 $output = str_replace('</body>', $overlay . '</body>', $output);
            }
        }

        return $output;
    }

    /**
     * Render a compiled view file.
     */
    private function renderCompiled(string $path, array $data): string
    {
        $cachePath = config('superpowers.cache_path');
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0777, true);
        }

        $hash = md5($path . filemtime($path));
        $compiledFile = $cachePath . '/' . basename($path) . '.' . $hash . '.php';
        $depsFile = $compiledFile . '.deps';

        $shouldRecompile = !file_exists($compiledFile);

        if (!$shouldRecompile && config('superpowers.check_dependencies', true)) {
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
                        } catch (\Exception $e) {
                        }
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

        if (config('app.debug')) {
             $viewName = $this->extractViewName($path);
             $this->debugCollector->recordView($viewName, $path, $data, $compiledFile);
            if (config('superpowers.linter.on_render', true)) {
                 $this->linter->lint(file_get_contents($path), $path);
            }
        }

        $view = $this->view;

        $render = (function () use ($compiledFile, $data) {
            $__action = $data['__action'] ?? null;
            $__state = $data['__state'] ?? null;
            $__view = $data['__view'] ?? null;

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
                    return $this->render($__extendedLayout, array_merge($data, $this->getEngine()->getInterpreter()->getState()->all()), null);
                }
            } catch (\Throwable $e) {
                ob_get_clean();
                throw $e;
            }
            return ob_get_clean();
        });

        return $render->call($view);
    }
}
