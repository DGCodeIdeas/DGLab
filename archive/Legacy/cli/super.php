<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\View;
use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Parser\Parser;
use DGLab\Services\Superpowers\Compiler\Compiler;
use DGLab\Services\Superpowers\Parser\Linter;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Parser\Nodes\Node;
use DGLab\Services\Superpowers\Parser\Nodes\DirectiveNode;
use DGLab\Services\Superpowers\Parser\Nodes\ComponentNode;
use DGLab\Services\Superpowers\Parser\Nodes\ExpressionNode;

/**
 * Superpowers CLI - Entry Point
 */
class SuperCLI {
    private Application $app;
    private array $commands = [];

    public function __construct() {
        $this->app = Application::getInstance();
        $this->registerCommands();
    }

    private function registerCommands() {
        $this->commands = [
            'compile:all'     => 'Compile all .super.php views.',
            'cache:clear'     => 'Clear compiled view cache.',
            'lint'            => 'Analyze views for syntax errors.',
            'make:component'  => 'Create a new component. Usage: make:component <name> [--props=a,b] [--reactive]',
            'make:view'       => 'Create a new view. Usage: make:view <name> [--layout=app]',
            'make:partial'    => 'Create a new partial. Usage: make:partial <name>',
            'make:layout'     => 'Create a new layout. Usage: make:layout <name>',
            'migrate:views'   => 'Bulk migrate legacy .php views.',
            'state:list'      => 'List values in global state store.',
            'state:clear'     => 'Clear global state store.',
            'list:components' => 'List all available components.',
            'list:views'      => 'List all root views.',
            'view:info'       => 'Show detailed metadata about a view.',
            'view:analyze'    => 'Analyze a view for performance or nesting issues.'
        ];
    }

    public function run(array $argv) {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        if ($command === 'help' || $command === '--help' || $command === '-h') {
            $this->displayHelp();
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->handleInvalidCommand($command);
            return;
        }

        $this->execute($command, $args);
    }

    private function execute(string $command, array $args) {
        $lexer = new Lexer();
        $parser = new Parser();
        $compiler = new Compiler();
        $linter = new Linter();
        $view = new View();

        $force = in_array('--force', $args) || in_array('-f', $args);
        $dryRun = in_array('--dry-run', $args);
        $name = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')))[0] ?? null;

        switch ($command) {
            case 'compile:all': $this->compileAll($lexer, $parser, $compiler); break;
            case 'cache:clear': $this->clearCache(); break;
            case 'lint': $this->lintAll($linter); break;
            case 'make:component': $this->makeComponent($name, $args, $force); break;
            case 'make:view': $this->makeView($name, $args, $force); break;
            case 'make:partial': $this->makePartial($name, $force); break;
            case 'make:layout': $this->makeLayout($name, $force); break;
            case 'migrate:views': $this->migrateViews($dryRun); break;
            case 'state:list': $this->listState(); break;
            case 'state:clear': $this->clearState(); break;
            case 'list:components': $this->listComponents(); break;
            case 'list:views': $this->listViews(); break;
            case 'view:info': $this->viewInfo($name, $lexer, $parser); break;
            case 'view:analyze': $this->viewAnalyze($name, $lexer, $parser); break;
        }
    }

    private function compileAll($lexer, $parser, $compiler) {
        echo "Compiling all SuperPHP views...\n";
        $viewPath = $this->app->getBasePath() . '/resources/views';
        $cachePath = $this->app->config('superpowers.cache_path');
        if (!is_dir($cachePath)) @mkdir($cachePath, 0777, true);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false) {
                echo "Compiling: {$file->getFilename()}\n";
                $content = file_get_contents($file->getPathname());
                $tokens = $lexer->tokenize($content);
                $ast = $parser->parse($tokens);
                $code = $compiler->compile($ast);
                $hash = md5($file->getPathname() . $file->getMTime());
                $compiledFile = $cachePath . '/' . $file->getFilename() . '.' . $hash . '.php';
                file_put_contents($compiledFile, $code);
                file_put_contents($compiledFile . '.deps', json_encode($compiler->getDependencies()));
            }
        }
        echo "Done.\n";
    }

    private function clearCache() {
        echo "Clearing compiled views...\n";
        $cachePath = $this->app->config('superpowers.cache_path');
        if (is_dir($cachePath)) {
            foreach (glob($cachePath . '/*') as $file) if (is_file($file)) unlink($file);
        }
        echo "Done.\n";
    }

    private function lintAll($linter) {
        echo "Linting all SuperPHP views...\n";
        $viewPath = $this->app->getBasePath() . '/resources/views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        $errors = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false) {
                try {
                    $linter->lint(file_get_contents($file->getPathname()), $file->getPathname());
                } catch (SyntaxException $e) {
                    echo "[\033[31mERROR\033[0m] {$file->getFilename()} on line {$e->getViewLine()}: {$e->getMessage()}\n";
                    $errors++;
                }
            }
        }
        if ($errors === 0) echo "[\033[32mOK\033[0m] All views passed linting.\n";
        else { echo "\033[31mFound {$errors} linting errors.\033[0m\n"; exit(1); }
    }

    private function makeComponent($name, $args, $force) {
        if (!$name) { echo "Usage: php cli/super.php make:component <name> [--props=a,b] [--reactive] [--force]\n"; exit(1); }
        list($dir, $filename) = $this->parseName($name);
        $path = $this->app->getBasePath() . "/resources/views/components/" . ($dir ? "$dir/" : "") . "{$filename}.super.php";

        $props = $this->getOption($args, 'props');
        $isReactive = in_array('--reactive', $args) || in_array('-r', $args);

        $data = ['name' => $filename, 'setup_extra' => '', 'reactive_example' => ''];
        $setup = "";
        if ($props) { foreach (explode(',', $props) as $p) $setup .= "    \${$p} = null; // @prop\n"; }
        if ($isReactive) {
            $setup .= "    \$count = 0;\n    \$increment = function() { \$this->count++; };\n";
            $data['reactive_example'] = "<button @click=\"increment\">Count: {{ \$count }}</button>";
        }
        $data['setup_extra'] = trim($setup);

        $content = $this->getStub('component', $data);
        if ($this->writeFile($path, $content, $force)) {
            echo "[\033[32mOK\033[0m] Created component: resources/views/components/" . ($dir ? "$dir/" : "") . "{$filename}.super.php\n";
        }
    }

    private function makeView($name, $args, $force) {
        if (!$name) { echo "Usage: php cli/super.php make:view <name> [--layout=app] [--force]\n"; exit(1); }
        list($dir, $filename) = $this->parseName($name);
        $path = $this->app->getBasePath() . "/resources/views/" . ($dir ? "$dir/" : "") . "{$filename}.super.php";
        $layout = $this->getOption($args, 'layout', 'app');
        $content = $this->getStub('view', ['name' => $filename, 'layout' => $layout]);
        if ($this->writeFile($path, $content, $force)) {
            echo "[\033[32mOK\033[0m] Created view: resources/views/" . ($dir ? "$dir/" : "") . "{$filename}.super.php\n";
        }
    }

    private function makePartial($name, $force) {
        if (!$name) { echo "Usage: php cli/super.php make:partial <name> [--force]\n"; exit(1); }
        list($dir, $filename) = $this->parseName($name);
        $path = $this->app->getBasePath() . "/resources/views/partials/" . ($dir ? "$dir/" : "") . "{$filename}.super.php";
        $content = $this->getStub('partial', ['name' => $filename]);
        if ($this->writeFile($path, $content, $force)) {
            echo "[\033[32mOK\033[0m] Created partial: resources/views/partials/" . ($dir ? "$dir/" : "") . "{$filename}.super.php\n";
        }
    }

    private function makeLayout($name, $force) {
        if (!$name) { echo "Usage: php cli/super.php make:layout <name> [--force]\n"; exit(1); }
        list($dir, $filename) = $this->parseName($name);
        $path = $this->app->getBasePath() . "/resources/views/layouts/" . ($dir ? "$dir/" : "") . "{$filename}.super.php";
        $content = $this->getStub('layout', ['name' => $filename]);
        if ($this->writeFile($path, $content, $force)) {
            echo "[\033[32mOK\033[0m] Created layout: resources/views/layouts/" . ($dir ? "$dir/" : "") . "{$filename}.super.php\n";
        }
    }

    private function migrateViews($dryRun) {
        echo "Migrating legacy .php views to .super.php...\n";
        if ($dryRun) echo "\033[34m[DRY RUN MODE]\033[0m\n";
        $viewPath = $this->app->getBasePath() . '/resources/views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), '.super.php') === false && strpos($file->getPathname(), '/storage/') === false) {
                $newPath = str_replace('.php', '.super.php', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                $content = preg_replace('/<\?php echo\s+(.*?);?\s*\?>/i', '{{ $1 }}', $content);
                $content = preg_replace('/<\?=\s*(.*?);?\s*\?>/i', '{{ $1 }}', $content);
                $content = preg_replace('/\$this->yield\(\s*\'(.*?)\'\s*(?:,\s*\'(.*?)\')?\s*\)/i', '@yield(\'$1\', \'$2\')', $content);
                $content = preg_replace('/\$this->section\(\s*\'(.*?)\'\s*\)/i', '@section(\'$1\')', $content);
                $content = preg_replace('/\$this->endSection\(\)/i', '@endsection', $content);
                $content = preg_replace('/\$this->partial\(\s*\'(.*?)\'\s*(?:,\s*(.*?))?\s*\)/i', '<s:partial:$1 />', $content);
                if (!$dryRun) file_put_contents($newPath, $content);
                echo "Migrated: {$file->getFilename()} -> " . basename($newPath) . "\n";
            }
        }
        echo "Done.\n";
    }

    private function listState() {
        $store = $this->app->get(GlobalStateStore::class);
        $state = $store->all();
        echo "\033[1mGlobal State Store\033[0m\n";
        if (empty($state)) echo "No global state stored.\n";
        else foreach ($state as $k => $v) echo "  \033[33m{$k}\033[0m: " . json_encode($v) . "\n";
    }

    private function clearState() {
        $this->app->get(GlobalStateStore::class)->clear();
        echo "[\033[32mOK\033[0m] Global state cleared.\n";
    }

    private function listComponents() {
        echo "\033[1mAvailable Components\033[0m\n";
        $path = $this->app->getBasePath() . '/resources/views/components';
        if (!is_dir($path)) { echo "No components found.\n"; return; }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false) {
                $name = str_replace([$path . '/', '.super.php', '/'], ['', '', '.'], $file->getPathname());
                echo "  - \033[33m{$name}\033[0m\n";
            }
        }
    }

    private function listViews() {
        echo "\033[1mAvailable Superpowers Views\033[0m\n";
        $path = $this->app->getBasePath() . '/resources/views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && strpos($file->getFilename(), '.super.php') !== false && strpos($file->getPathname(), '/components/') === false && strpos($file->getPathname(), '/layouts/') === false && strpos($file->getPathname(), '/partials/') === false) {
                $name = str_replace([$path . '/', '.super.php', '/'], ['', '', '.'], $file->getPathname());
                echo "  - \033[33m{$name}\033[0m\n";
            }
        }
    }

    private function viewInfo($name, $lexer, $parser) {
        if (!$name) { echo "Usage: php cli/super.php view:info <name>\n"; exit(1); }
        $path = $this->app->getBasePath() . "/resources/views/" . str_replace('.', '/', $name) . ".super.php";
        if (!file_exists($path)) { echo "Error: View not found at {$path}\n"; exit(1); }

        $content = file_get_contents($path);
        $tokens = $lexer->tokenize($content);
        $ast = $parser->parse($tokens);

        echo "\033[1mView Information: {$name}\033[0m\n";
        echo "  Path: {$path}\n";
        echo "  Size: " . strlen($content) . " bytes\n";

        $stats = ['directives' => 0, 'components' => 0, 'expressions' => 0];
        $this->crawlAst($ast, $stats);

        echo "  AST Summary:\n";
        echo "    - Directives:  {$stats['directives']}\n";
        echo "    - Components:  {$stats['components']}\n";
        echo "    - Expressions: {$stats['expressions']}\n";
    }

    private function viewAnalyze($name, $lexer, $parser) {
        if (!$name) { echo "Usage: php cli/super.php view:analyze <name>\n"; exit(1); }
        $path = $this->app->getBasePath() . "/resources/views/" . str_replace('.', '/', $name) . ".super.php";
        if (!file_exists($path)) { echo "Error: View not found at {$path}\n"; exit(1); }

        $content = file_get_contents($path);
        $tokens = $lexer->tokenize($content);
        $ast = $parser->parse($tokens);

        echo "\033[1mAnalysis: {$name}\033[0m\n";
        $maxDepth = $this->getAstDepth($ast);
        echo "  - Maximum Nesting Depth: {$maxDepth} " . ($maxDepth > 5 ? "\033[31m(High)\033[0m" : "\033[32m(Optimal)\033[0m") . "\n";

        $stats = ['directives' => 0, 'components' => 0, 'expressions' => 0];
        $complexExpressions = 0;
        $this->crawlAst($ast, $stats, function($node) use (&$complexExpressions) {
            if ($node instanceof ExpressionNode && strlen($node->expression) > 50) $complexExpressions++;
        });
        echo "  - Complex Expressions: {$complexExpressions} " . ($complexExpressions > 3 ? "\033[33m(Consider moving to ~setup)\033[0m" : "\033[32m(Clean)\033[0m") . "\n";
    }

    private function crawlAst(array $ast, &$stats, ?callable $callback = null) {
        foreach ($ast as $node) {
            if ($node instanceof DirectiveNode) $stats['directives']++;
            if ($node instanceof ComponentNode) $stats['components']++;
            if ($node instanceof ExpressionNode) $stats['expressions']++;
            if ($callback) $callback($node);
            if (isset($node->children)) $this->crawlAst($node->children, $stats, $callback);
        }
    }

    private function getAstDepth(array $ast): int {
        $maxChildDepth = 0;
        foreach ($ast as $node) {
            if (isset($node->children) && !empty($node->children)) {
                $maxChildDepth = max($maxChildDepth, $this->getAstDepth($node->children));
            }
        }
        return 1 + $maxChildDepth;
    }

    private function displayHelp() {
        echo "\033[1mSuperpowers CLI Help\033[0m\n";
        echo "Usage: php cli/super.php [command] [args]\n\n";
        echo "Available Commands:\n";
        foreach ($this->commands as $c => $desc) echo "  \033[33m" . str_pad($c, 18) . "\033[0m {$desc}\n";
    }

    private function handleInvalidCommand($command) {
        $bestMatch = null; $minDist = 999;
        foreach (array_keys($this->commands) as $c) {
            $d = levenshtein($command, $c);
            if ($d < $minDist) { $minDist = $d; $bestMatch = $c; }
        }
        if ($minDist <= 3) echo "\033[31mCommand '{$command}' not found.\033[0m Did you mean \033[33m" . $bestMatch . "\033[0m?\n\n";
        $this->displayHelp();
    }

    private function getStub($name, $data = []) {
        $stubPath = dirname(__DIR__) . "/resources/stubs/{$name}.stub";
        $content = file_exists($stubPath) ? file_get_contents($stubPath) : $this->getDefaultStub($name);
        foreach ($data as $k => $v) { $content = str_replace(["{{ {$k} }}", "{{{$k}}}"], $v, $content); }
        return $content;
    }

    private function getDefaultStub($name) {
        switch($name) {
            case 'component': return "~setup {\n    \$title = '{{ name }}';\n    {{ setup_extra }}\n}\n\n<div class=\"{{ name }}\">\n    <h2>{{ \$title }}</h2>\n    {!! \$slot !!}\n    {{ reactive_example }}\n</div>\n";
            case 'view': return "~setup {\n    \$title = '{{ name }}';\n}\n\n<s:layout:{{ layout }}>\n    <h1>{{ \$title }}</h1>\n</s:layout:{{ layout }}>\n";
            case 'partial': return "<div class=\"partial-{{ name }}\">\n    <p>Partial Content</p>\n</div>\n";
            case 'layout': return "<html><body>{!! \$slot !!}</body></html>";
            default: return "";
        }
    }

    private function parseName($name) {
        $parts = explode('.', $name);
        $filename = array_pop($parts);
        $dir = implode('/', $parts);
        return [$dir, $filename];
    }

    private function getOption($args, $key, $default = null) {
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, "--$key=")) return substr($arg, strlen("--$key="));
            if ($arg === "--$key" && isset($args[$i+1])) return $args[$i+1];
        }
        return $default;
    }

    private function writeFile($path, $content, $force = false) {
        if (file_exists($path) && !$force) {
            echo "\033[31mError: File already exists at {$path}. Use --force to overwrite.\033[0m\n";
            return false;
        }
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
        return true;
    }
}

(new SuperCLI())->run($argv);
