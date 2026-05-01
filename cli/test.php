<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Application.php';

use DGLab\Core\Application;
use DGLab\Events\TestSuite\TestFailed;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Listeners\TestSuite\LogTestFailure;

class TestCLI {
    private $app;
    private $commands = [
        'run'     => 'Run tests (unit, integration, browser)',
        'make'    => 'Scaffold a new test file',
        'parallel'=> 'Run tests in parallel (local)',
        'split'   => 'Split tests into chunks for CI (returns file list)',
        'coverage'=> 'Generate code coverage report',
        'watch'   => 'Watch for changes and re-run tests',
        'health'  => 'Generate a full health report',
        'check'   => 'Full suite check (Analysis + Tests + Health)',
        'help'    => 'Display help information'
    ];

    public function __construct() {
        $this->app = new Application(__DIR__ . '/..');
        $this->app->boot();

        // Manual registration for CLI
        try {
            $dispatcher = $this->app->get(DispatcherInterface::class);
            $dispatcher->listen('test_suite.failed', function($event) {
                $listener = new LogTestFailure($this->app->get(\Psr\Log\LoggerInterface::class));
                $listener->handle($event);
            });
        } catch (\Exception $e) {
            // Event system not fully ready
        }
    }

    public function run(array $argv) {
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'run':
                $this->executeTests($argv);
                break;
            case 'make':
                $this->makeTest($argv[2] ?? null, $argv[3] ?? 'unit', $argv);
                break;
            case 'parallel':
                $this->runParallel(array_slice($argv, 2));
                break;
            case 'split':
                $this->splitTests($argv);
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
            case 'check':
                $this->runFullCheck($argv);
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

        $cmd = "vendor/bin/phpunit --colors=always " . implode(' ', $args);
        passthru($cmd, $resultCode);

        if ($resultCode !== 0) {
            $this->notifyFailure('PHPUnit Tests', 'Tests failed with exit code ' . $resultCode);
            exit($resultCode);
        }
    }

    private function splitTests(array $argv) {
        $total = (int)$this->getOption($argv, 'total') ?: 1;
        $index = (int)$this->getOption($argv, 'index') ?: 0;

        $files = $this->getTestFiles();
        sort($files);

        if (empty($files)) return;

        $chunks = array_chunk($files, ceil(count($files) / $total));
        $chunk = $chunks[$index] ?? [];

        echo implode(' ', array_map('escapeshellarg', $chunk));
    }

    private function runParallel(array $args) {
        if (!function_exists('pcntl_fork')) {
            echo "\033[31mError: pcntl extension is required for parallel execution.\033[0m\n";
            exit(1);
        }

        echo "\033[34m[PARALLEL MODE]\033[0m Splitting tests...\n";

        $files = $this->getTestFiles();

        if (empty($files)) {
            echo "No tests found for parallel execution.\n";
            return;
        }

        $workers = (int)$this->getOption($args, 'workers') ?: 4;
        $chunks = array_chunk($files, ceil(count($files) / $workers));
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
            $this->notifyFailure('Parallel Tests', 'One or more workers failed.');
            exit(1);
        } else {
            echo "\n\033[32mALL PARALLEL TESTS PASSED\033[0m\n";
        }
    }

    private function getTestFiles() {
        $files = [];
        $paths = [
            $this->app->getBasePath() . '/tests/Unit',
            $this->app->getBasePath() . '/tests/Integration'
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
                foreach ($it as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }
        return $files;
    }

    private function notifyFailure(string $type, string $message, array $details = []) {
        try {
            $dispatcher = $this->app->get(DispatcherInterface::class);
            $dispatcher->dispatch(new TestFailed($type, $message, $details));
        } catch (\Exception $e) {
            // Silently fail if event system is not fully ready
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

        $results = [
            'timestamp' => date('c'),
            'tests' => $this->runUnitAndIntegrationForHealth(),
            'static_analysis' => $this->runStaticAnalysisForHealth(),
            'coding_standards' => $this->runCSForHealth()
        ];

        $isHealthy = $results['tests']['passed'] &&
                     $results['static_analysis']['passed'] &&
                     $results['coding_standards']['passed'];

        $results['status'] = $isHealthy ? 'healthy' : 'unhealthy';

        file_put_contents($reportDir . '/health.json', json_encode($results, JSON_PRETTY_PRINT));

        $txtReport = "DGLab TestSuite Health Report\n";
        $txtReport .= "=============================\n";
        $txtReport .= "Timestamp: " . $results['timestamp'] . "\n";
        $txtReport .= "Status: " . strtoupper($results['status']) . "\n\n";

        $txtReport .= "Tests: " . ($results['tests']['passed'] ? "PASS" : "FAIL") . "\n";
        $txtReport .= "Static Analysis: " . ($results['static_analysis']['passed'] ? "PASS" : "FAIL") . "\n";
        $txtReport .= "Coding Standards: " . ($results['coding_standards']['passed'] ? "PASS" : "FAIL") . "\n";

        file_put_contents($reportDir . '/health.txt', $txtReport);

        echo "[\033[32mOK\033[0m] Report saved to storage/reports/health.txt and health.json\n";

        if (!$isHealthy) {
            echo "\033[31mStatus: UNHEALTHY\033[0m\n";
            $this->notifyFailure('Health Report', 'System is unhealthy based on automated checks.');
        } else {
            echo "\033[32mStatus: HEALTHY\033[0m\n";
        }
    }

    private function runUnitAndIntegrationForHealth() {
        $output = [];
        $phpunit = $this->app->getBasePath() . '/vendor/bin/phpunit';
        exec("$phpunit --exclude-group browser --testdox", $output, $resultCode);
        return [
            'passed' => $resultCode === 0,
            'summary' => end($output) ?: 'No tests found'
        ];
    }

    private function runStaticAnalysisForHealth() {
        $output = [];
        $phpstan = $this->app->getBasePath() . '/vendor/bin/phpstan';
        exec("$phpstan analyse app/ --level=5 --no-progress", $output, $resultCode);
        return [
            'passed' => $resultCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    private function runCSForHealth() {
        $output = [];
        $phpcs = $this->app->getBasePath() . '/vendor/bin/phpcs';
        exec("$phpcs --standard=PSR12 app/", $output, $resultCode);
        return [
            'passed' => $resultCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    private function runFullCheck(array $argv) {
        echo "\033[1mStarting Full Suite Check...\033[0m\n";
        $base = $this->app->getBasePath();

        $onlyAnalysis = $this->hasOption($argv, '--only-analysis');

        echo "\n\033[36m1. Running Static Analysis (PHPStan)...\033[0m\n";
        passthru("$base/vendor/bin/phpstan analyse app/ --level=5", $rc1);
        if ($rc1 !== 0) $this->notifyFailure('Static Analysis', 'PHPStan detected issues.');

        echo "\n\033[36m2. Checking Coding Standards (PHPCS)...\033[0m\n";
        passthru("$base/vendor/bin/phpcs --standard=PSR12 app/", $rc2);
        if ($rc2 !== 0) $this->notifyFailure('Coding Standards', 'PHPCS detected violations.');

        if ($onlyAnalysis) {
            if ($rc1 !== 0 || $rc2 !== 0) exit(1);
            return;
        }

        echo "\n\033[36m3. Running Unit & Integration Tests...\033[0m\n";
        passthru("$base/vendor/bin/phpunit --exclude-group browser", $rc3);
        if ($rc3 !== 0) $this->notifyFailure('PHPUnit Tests', 'Tests failed.');

        echo "\n\033[36m4. Generating Health Report...\033[0m\n";
        $this->generateHealthReport();

        if ($rc1 !== 0 || $rc2 !== 0 || $rc3 !== 0) exit(1);
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
        if (!file_exists($stubPath)) {
            return "<?php\n\nnamespace {{ namespace }};\n\nuse DGLab\Tests\TestCase;\n\nclass {{ class }} extends TestCase\n{\n    public function test_example()\n    {\n        \$this->assertTrue(true);\n    }\n}\n";
        }
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
