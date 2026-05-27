<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Events\TestSuite\TestSuiteStarted;
use DGLab\Events\TestSuite\TestSuiteFinished;
use DGLab\Events\TestSuite\TestSuiteFailed;
use DGLab\Listeners\TestSuite\TestNotificationSubscriber;
use Psr\Log\LoggerInterface;

/**
 * TestSuite CLI - Entry Point
 */
class TestCLI {
    private Application $app;
    private array $commands = [];

    public function __construct() {
        $this->app = new Application(dirname(__DIR__));
        $this->setupNotifications();
        $this->registerCommands();
    }

    private function setupNotifications() {
        if ($this->app->has(DispatcherInterface::class)) {
            $dispatcher = $this->app->get(DispatcherInterface::class);
            $logger = $this->app->get(LoggerInterface::class);
            $subscriber = new TestNotificationSubscriber($logger);
            $dispatcher->listen(TestSuiteStarted::class, [$subscriber, 'onTestStarted']);
            $dispatcher->listen(TestSuiteFinished::class, [$subscriber, 'onTestFinished']);
            $dispatcher->listen(TestSuiteFailed::class, [$subscriber, 'onTestFailed']);
        }
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

        $suiteName = 'Full Suite';
        if ($this->hasOption($argv, '--unit')) $suiteName = 'Unit';
        if ($this->hasOption($argv, '--integration')) $suiteName = 'Integration';
        if ($this->hasOption($argv, '--browser')) $suiteName = 'Browser';

        $this->dispatch(new TestSuiteStarted($suiteName, ['argv' => $argv]));

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
            $success = $this->runParallel($args);
            $this->dispatch(new TestSuiteFinished($suiteName, $success, ['mode' => 'parallel']));
            return;
        }

        $cmd = "vendor/bin/phpunit --colors=always " . implode(' ', $args);
        passthru($cmd, $resultCode);

        $success = $resultCode === 0;
        $this->dispatch(new TestSuiteFinished($suiteName, $success, ['exit_code' => $resultCode]));

        if (!$success) {
            $this->dispatch(new TestSuiteFailed($suiteName, "PHPUnit exited with code $resultCode"));
            exit($resultCode);
        }
    }

    private function dispatch($event) {
        if ($this->app->has(DispatcherInterface::class)) {
            $this->app->get(DispatcherInterface::class)->dispatch($event);
        }
    }

    private function runParallel(array $args): bool {
        echo "\033[34m[PARALLEL MODE]\033[0m Splitting tests...\n";

        $files = [];
        $testPath = $this->app->getBasePath() . '/tests';

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testPath));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $filePath = $file->getPathname();
                if (str_contains(implode(' ', $args), '--testsuite Unit') && !str_contains($filePath, '/Unit/')) continue;
                if (str_contains(implode(' ', $args), '--testsuite Integration') && !str_contains($filePath, '/Integration/')) continue;
                if (str_contains(implode(' ', $args), '--testsuite Browser') && !str_contains($filePath, '/Browser/')) continue;

                $files[] = $filePath;
            }
        }

        if (empty($files)) {
            echo "No tests found for parallel execution.\n";
            return false;
        }

        $workerCount = 4;
        $chunks = array_chunk($files, ceil(count($files) / $workerCount));
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
            return false;
        } else {
            echo "\n\033[32mALL PARALLEL TESTS PASSED\033[0m\n";
            return true;
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
        echo "\033[1mGenerating Phased Health Report...\033[0m\n";
        $reportDir = $this->app->getBasePath() . '/storage/reports';
        if (!is_dir($reportDir)) mkdir($reportDir, 0777, true);

        $suites = ['Unit', 'Integration', 'Browser'];
        $results = [];
        $allPass = true;

        foreach ($suites as $suite) {
            echo "Checking {$suite} suite... ";
            $output = [];
            $resultCode = 0;
            exec("vendor/bin/phpunit --testsuite {$suite} --testdox", $output, $resultCode);

            $suitePass = ($resultCode === 0);
            if (!$suitePass) $allPass = false;

            $summary = end($output);
            $results[$suite] = [
                'status' => $suitePass ? 'PASS' : 'FAIL',
                'summary' => $summary,
                'output' => $output
            ];

            if ($suitePass) echo "[\033[32mOK\033[0m]\n";
            else echo "[\033[31mFAIL\033[0m]\n";
        }

        // Try to get coverage if xdebug is available
        $coverage = 'Unknown (Xdebug missing)';
        if (extension_loaded('xdebug')) {
            echo "Generating coverage data... ";
            $covOutput = [];
            exec("XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text | grep 'Lines:'", $covOutput);
            if (!empty($covOutput)) {
                $coverage = trim($covOutput[0]);
                echo "[\033[32mOK\033[0m]\n";
            } else {
                echo "[\033[33mFAILED\033[0m]\n";
            }
        }

        $jsonReport = [
            'timestamp' => date('c'),
            'status' => $allPass ? 'healthy' : 'unhealthy',
            'suites' => $results,
            'coverage' => $coverage
        ];

        file_put_contents($reportDir . '/health.json', json_encode($jsonReport, JSON_PRETTY_PRINT));

        $txtReport = "DGLab TestSuite Health Report\n";
        $txtReport .= "============================\n";
        $txtReport .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $txtReport .= "Status: " . ($allPass ? 'HEALTHY' : 'UNHEALTHY') . "\n";
        $txtReport .= "Coverage: {$coverage}\n\n";

        foreach ($results as $name => $data) {
            $txtReport .= "[$name] Status: {$data['status']}\n";
            $txtReport .= "Summary: {$data['summary']}\n\n";
        }

        file_put_contents($reportDir . '/health.txt', $txtReport);

        echo "[\033[32mOK\033[0m] Report saved to storage/reports/health.txt and health.json\n";

        if (!$allPass) {
            $this->dispatch(new TestSuiteFailed('HealthCheck', 'One or more suites failed in health report'));
        }
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
        if (!file_exists($stubPath)) return "Stub not found: {$name}";
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
