<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;

/**
 * TestSuite CLI - Entry Point
 */
class TestCLI {
    private Application $app;
    private array $commands = [];

    public function __construct() {
        $this->app = new Application(dirname(__DIR__));
        $this->registerCommands();
    }

    private function registerCommands() {
        $this->commands = [
            'run'                   => 'Execute tests. Filters: --unit, --integration, --browser, --group=X. Flags: --stop-on-failure, --parallel',
            'make:test'             => 'Scaffold a new unit test. Usage: make:test <name>',
            'make:component-test'   => 'Scaffold a new component test. Usage: make:component-test <component>',
            'make:integration-test' => 'Scaffold a new integration test. Usage: make:integration-test <name>',
            'make:browser-test'     => 'Scaffold a new browser test. Usage: make:browser-test <name>',
            'coverage'              => 'Generate and display code coverage summary.',
            'watch'                 => 'Watch for file changes and re-run tests.',
            'health'                => 'Generate a health dashboard report in storage/reports/.'
        ];
    }

    public function run(array $argv) {
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'run':
                $this->executeTests($argv);
                break;
            case 'make:test':
                $this->makeTest($argv[2] ?? null, 'unit', $argv);
                break;
            case 'make:component-test':
                $this->makeTest($argv[2] ?? null, 'component', $argv);
                break;
            case 'make:integration-test':
                $this->makeTest($argv[2] ?? null, 'integration', $argv);
                break;
            case 'make:browser-test':
                $this->makeTest($argv[2] ?? null, 'browser', $argv);
                break;
            case 'coverage':
                $this->runCoverage($argv);
                break;
            case 'watch':
                $this->watchTests($argv);
                break;
            case 'health':
                $this->generateHealthReport();
                break;
            case 'help':
                $this->displayHelp();
                break;
            default:
                $this->handleInvalidCommand($command);
                break;
        }
    }

    private function executeTests(array $argv) {
        echo "\033[1mTestSuite: Running Tests\033[0m\n";

        $args = [];
        if ($this->hasOption($argv, '--unit')) $args[] = '--testsuite Unit';
        if ($this->hasOption($argv, '--integration')) $args[] = '--testsuite Integration';
        if ($this->hasOption($argv, '--browser')) $args[] = '--testsuite Browser';

        if ($group = $this->getOption($argv, 'group')) $args[] = "--group $group";

        $filter = $this->getOption($argv, 'filter');
        if (!$filter) {
            foreach (array_slice($argv, 2) as $arg) {
                if (!str_starts_with($arg, '-')) {
                    $filter = $arg;
                    break;
                }
            }
        }
        if ($filter) $args[] = "--filter '$filter'";

        if ($this->hasOption($argv, '--stop-on-failure') || $this->hasOption($argv, '--fail-fast')) $args[] = '--stop-on-failure';
        if ($this->hasOption($argv, '--testdox')) $args[] = '--testdox';

        if ($this->hasOption($argv, '--parallel') && function_exists('pcntl_fork')) {
            $this->runParallel($args);
            return;
        }

        $cmd = "vendor/bin/phpunit --colors=always " . implode(' ', $args);
        passthru($cmd);
    }

    private function runParallel(array $args) {
        echo "\033[34m[PARALLEL MODE]\033[0m Splitting Unit tests...\n";

        $files = [];
        $unitPath = $this->app->getBasePath() . '/tests/Unit';
        if (is_dir($unitPath)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($unitPath));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        if (empty($files)) {
            echo "No unit tests found for parallel execution.\n";
            return;
        }

        $chunks = array_chunk($files, ceil(count($files) / 4));
        $pids = [];

        foreach ($chunks as $i => $chunk) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                die("Could not fork");
            } else if ($pid) {
                $pids[] = $pid;
            } else {
                $workerId = $i + 1;
                $tempFile = sys_get_temp_dir() . "/phpunit_parallel_{$workerId}.log";
                $fileList = implode(' ', array_map('escapeshellarg', $chunk));
                $cmd = "vendor/bin/phpunit --colors=always " . implode(' ', $args) . " $fileList > $tempFile 2>&1";
                exec($cmd, $output, $resultCode);
                file_put_contents($tempFile, "\n\033[1mWorker {$workerId} Output:\033[0m\n" . file_get_contents($tempFile));
                exit($resultCode);
            }
        }

        $failed = false;
        foreach ($pids as $i => $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) !== 0) $failed = true;
            $workerId = $i + 1;
            echo file_get_contents(sys_get_temp_dir() . "/phpunit_parallel_{$workerId}.log");
            unlink(sys_get_temp_dir() . "/phpunit_parallel_{$workerId}.log");
        }

        if ($failed) {
            echo "\n\033[31mFAILURES DETECTED IN PARALLEL RUN\033[0m\n";
            exit(1);
        } else {
            echo "\n\033[32mALL PARALLEL TESTS PASSED\033[0m\n";
        }
    }

    private function makeTest(?string $name, string $type, array $argv) {
        if (!$name) {
            echo "\033[31mError: Name is required.\033[0m\n";
            exit(1);
        }

        $force = $this->hasOption($argv, '--force');
        $namespace = "DGLab\\Tests";
        $subPath = "";
        $stub = "unit-test";

        switch ($type) {
            case 'unit':
                $namespace .= "\\Unit";
                $subPath = "Unit";
                $stub = "unit-test";
                break;
            case 'integration':
                $namespace .= "\\Integration";
                $subPath = "Integration";
                $stub = "integration-test";
                break;
            case 'component':
                $namespace .= "\\Unit\\Components";
                $subPath = "Unit/Components";
                $stub = "component-test";
                break;
            case 'browser':
                $namespace .= "\\Browser";
                $subPath = "Browser";
                $stub = "browser-test";
                break;
        }

        $originalName = $name;
        if (!str_ends_with($name, 'Test')) $name .= 'Test';

        $parts = explode('/', str_replace(['.', '\\'], '/', $name));
        $className = $this->toStudlyCase(array_pop($parts));

        $namespacedParts = array_map([$this, 'toStudlyCase'], $parts);
        if (!empty($namespacedParts)) {
            $subPath .= '/' . implode('/', $namespacedParts);
            $namespace .= "\\" . implode("\\", $namespacedParts);
        }

        $path = $this->app->getBasePath() . "/tests/{$subPath}/{$className}.php";

        $stubData = [
            'namespace' => $namespace,
            'class' => $className,
            'component' => str_replace('Test', '', $originalName)
        ];

        $content = $this->getStub($stub, $stubData);

        if ($this->writeFile($path, $content, $force)) {
            echo "[\033[32mOK\033[0m] Created {$type} test: tests/{$subPath}/{$className}.php\n";
        }
    }

    private function toStudlyCase(string $value): string {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function runCoverage(array $argv) {
        echo "\033[1mTestSuite: Generating Coverage\033[0m\n";
        $cmd = "XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text";
        passthru($cmd);
    }

    private function watchTests(array $argv) {
        echo "\033[1mTestSuite: Watching files...\033[0m\n";
        $lastHash = "";
        while (true) {
            $hash = $this->getFilesystemHash();
            if ($hash !== $lastHash) {
                if ($lastHash !== "") {
                    echo "\n\033[33mChange detected. Re-running tests...\033[0m\n";
                    $this->executeTests($argv);
                }
                $lastHash = $hash;
            }
            sleep(2);
        }
    }

    private function generateHealthReport() {
        echo "Generating health report...\n";
        $reportDir = $this->app->getBasePath() . '/storage/reports';
        if (!is_dir($reportDir)) mkdir($reportDir, 0777, true);

        $output = [];
        exec("vendor/bin/phpunit --testdox", $output);
        $content = implode("\n", $output);
        file_put_contents($reportDir . '/health.txt', $content);

        $summary = end($output);
        $isHealthy = !preg_match('/FAILURES|ERRORS|Failures|Errors/i', $content);

        $jsonReport = [
            'timestamp' => date('c'),
            'summary' => $summary,
            'status' => $isHealthy ? 'healthy' : 'unhealthy'
        ];
        file_put_contents($reportDir . '/health.json', json_encode($jsonReport, JSON_PRETTY_PRINT));

        echo "[\033[32mOK\033[0m] Report saved to storage/reports/health.txt and health.json\n";
        if (!$isHealthy) echo "\033[31mStatus: UNHEALTHY\033[0m\n";
        else echo "\033[32mStatus: HEALTHY\033[0m\n";
    }

    private function getFilesystemHash() {
        $dirs = ['app', 'tests', 'resources/views'];
        $hashes = "";
        foreach ($dirs as $dir) {
            $path = $this->app->getBasePath() . '/' . $dir;
            if (!is_dir($path)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'super.php'])) {
                    $hashes .= $file->getPathname() . $file->getMTime();
                }
            }
        }
        return md5($hashes);
    }

    private function displayHelp() {
        echo "\033[1mTestSuite CLI Help\033[0m\n";
        echo "Usage: php cli/test.php [command] [args]\n\n";
        echo "Available Commands:\n";
        foreach ($this->commands as $c => $desc) echo "  \033[33m" . str_pad($c, 22) . "\033[0m {$desc}\n";
    }

    private function handleInvalidCommand($command) {
        echo "\033[31mCommand '{$command}' not found.\033[0m\n\n";
        $this->displayHelp();
    }

    private function getStub($name, $data = []) {
        $stubPath = dirname(__DIR__) . "/resources/stubs/{$name}.stub";
        $content = file_get_contents($stubPath);
        foreach ($data as $k => $v) { $content = str_replace(["{{ $k }}", "{{$k}}"], (string)$v, $content); }
        return $content;
    }

    private function hasOption($args, $option) {
        return in_array($option, $args);
    }

    private function getOption($args, $key) {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) return substr($arg, strlen("--{$key}="));
        }
        return null;
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

(new TestCLI())->run($argv);
