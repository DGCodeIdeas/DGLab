<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;

$app = Application::getInstance();
$view = new View();
$lexer = new Lexer();
$parser = new Parser();
$compiler = new Compiler();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'compile:all':
        echo "Compiling all SuperPHP views...\n";
        $viewPath = $app->getBasePath() . '/resources/views';
        $cachePath = $app->config('superpowers.cache_path');

        if (!is_dir($cachePath)) @mkdir($cachePath, 0777, true);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), '.super.php') !== false) {
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

    default:
        echo "Superpowers CLI\n";
        echo "Usage: php cli/superpowers.php [command]\n";
        echo "Commands:\n";
        echo "  compile:all    Compile all .super.php files\n";
        echo "  cache:clear    Clear all compiled files\n";
        break;
}
