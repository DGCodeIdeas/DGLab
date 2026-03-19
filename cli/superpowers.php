<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;
use DGLab\Services\Superpowers\Parser\Linter;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;

$app = Application::getInstance();
$view = new View();
$lexer = new Lexer();
$parser = new Parser();
$compiler = new Compiler();
$linter = new Linter();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'compile:all':
        echo "Compiling all SuperPHP views...\n";
        $viewPath = $app->getBasePath() . '/resources/views';
        $cachePath = $app->config('superpowers.cache_path');

        if (!is_dir($cachePath)) @mkdir($cachePath, 0777, true);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false) {
                echo "Compiling: {$file->getRelativePathname()}\n";
                $content = file_get_contents($file->getPathname());
                $tokens = $lexer->tokenize($content);
                $ast = $parser->parse($tokens);
                $code = $compiler->compile($ast);

                $hash = md5($file->getPathname() . $file->getMTime());
                $compiledFile = $cachePath . '/' . $file->getFilename() . '.' . $hash . '.php';
                file_put_contents($compiledFile, $code);

                $depsFile = $compiledFile . '.deps';
                file_put_contents($depsFile, json_encode($compiler->getDependencies()));
            }
        }
        echo "Done.\n";
        break;

    case 'cache:clear':
        echo "Clearing compiled views...\n";
        $cachePath = $app->config('superpowers.cache_path');
        if (is_dir($cachePath)) {
            $files = glob($cachePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
        }
        echo "Done.\n";
        break;

    case 'lint':
        echo "Linting all SuperPHP views...\n";
        $viewPath = $app->getBasePath() . '/resources/views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        $errors = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false) {
                try {
                    $content = file_get_contents($file->getPathname());
                    $linter->lint($content, $file->getPathname());
                } catch (SyntaxException $e) {
                    echo "[\033[31mERROR\033[0m] {$file->getRelativePathname()} on line {$e->getViewLine()}: {$e->getMessage()}\n";
                    $errors++;
                }
            }
        }
        if ($errors === 0) {
            echo "[\033[32mOK\033[0m] All views passed linting.\n";
        } else {
            echo "\033[31mFound {$errors} linting errors.\033[0m\n";
            exit(1);
        }
        break;

    default:
        echo "Superpowers CLI\n";
        echo "Usage: php cli/superpowers.php [command]\n";
        echo "Commands:\n";
        echo "  compile:all    Compile all .super.php files\n";
        echo "  cache:clear    Clear all compiled files\n";
        echo "  lint           Lint all .super.php files\n";
        break;
}
