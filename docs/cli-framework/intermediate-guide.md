# Intermediate Guide: Routing, Middleware & Validation

## Overview

This guide builds on the [Beginner Guide](./beginner-guide.md) fundamentals to cover advanced CLI patterns used by production scripts like [`cli/test.php`](../Legacy.old/cli/test.php) and [`cli/super.php`](../Legacy.old/cli/super.php). You'll learn command-map routing, input validation, middleware patterns, event dispatch, and test scaffolding.

> **Prerequisites**: [Beginner Guide](./beginner-guide.md) — Basic `$argv` parsing, exit codes, output conventions
>
> **Complexity Tier**: 🟡 Intermediate

---

## 1. Command-Map Routing

The most common pattern in DGLab CLI scripts is the **command-map**: an associative array that maps command names to their descriptions and behavior.

### Pattern from [`cli/test.php`](../Legacy.old/cli/test.php)

```php
private array $commands = [
    'run'                   => 'Execute tests. Filters: --unit, --integration, --group=X',
    'make:test'             => 'Scaffold a new unit test. Usage: make:test <name>',
    'make:component-test'   => 'Scaffold a new component test. Usage: make:component-test <name>',
    'make:integration-test' => 'Scaffold a new integration test. Usage: make:integration-test <name>',
    'make:browser-test'     => 'Scaffold a new browser test. Usage: make:browser-test <name>',
    'coverage'              => 'Generate and display code coverage summary.',
    'watch'                 => 'Watch for file changes and re-run tests.',
    'health'                => 'Generate a health dashboard report in storage/reports/.',
];
```

### Pattern from [`cli/super.php`](../Legacy.old/cli/super.php)

```php
private array $commands = [
    'compile:all'     => 'Compile all .super.php views.',
    'cache:clear'     => 'Clear compiled view cache.',
    'lint'            => 'Analyze views for syntax errors.',
    'make:component'  => 'Create a new component. Usage: make:component <name> [--props=a,b] [--reactive]',
    'make:view'       => 'Create a new view. Usage: make:view <name> [--layout=app]',
    'make:partial'    => 'Create a new partial. Usage: make:partial <name>',
    'make:layout'     => 'Create a new layout. Usage: make:layout <name>',
    'state:list'      => 'List values in global state store.',
    'state:clear'     => 'Clear global state store.',
    'list:components' => 'List all available components.',
    'view:info'       => 'Show detailed metadata about a view.',
    'view:analyze'    => 'Analyze a view for performance or nesting issues.',
];
```

### Routing Engine Template

```php
abstract class BaseCLI
{
    protected array $commands = [];
    protected array $argv = [];

    public function run(array $argv): void
    {
        $this->argv = $argv;
        $command = $argv[1] ?? 'help';

        if ($command === 'help' || $command === '--help' || $command === '-h') {
            $this->displayHelp();
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->handleInvalidCommand($command);
            return;
        }

        $this->execute($command, array_slice($argv, 2));
    }

    protected function execute(string $command, array $args): void
    {
        // Override in subclasses with a switch/case or dispatch table
        throw new \RuntimeException("execute() not implemented for: {$command}");
    }

    protected function displayHelp(): void
    {
        echo "\033[1m" . static::class . "\033[0m\n";
        echo "Usage: php cli/" . basename($this->argv[0]) . " <command> [arguments]\n\n";
        echo "Commands:\n";
        foreach ($this->commands as $name => $description) {
            printf("  %-25s %s\n", $name, $description);
        }
        echo "\nOptions:\n";
        echo "  --help, -h     Show this help message\n";
        echo "  --verbose, -v  Show detailed output\n";
        exit(0);
    }

    protected function handleInvalidCommand(string $command): void
    {
        echo "\033[31mUnknown command: {$command}\033[0m\n";
        echo "Run 'php cli/" . basename($this->argv[0]) . " help' for usage.\n";
        exit(127);
    }
}
```

### Subcommands (Namespaced Commands)

Use colon-separated namespacing for related commands:

```php
$this->commands = [
    'state:list'  => 'List values in global state store.',
    'state:clear' => 'Clear global state store.',
    'make:test'   => 'Scaffold unit test.',
    'make:view'   => 'Scaffold view template.',
];
```

This groups related functionality under a common prefix (`state:*`, `make:*`) without requiring nested routing logic.

---

## 2. Argument Validation

### Required Parameter Enforcement

```php
private function validateRequired(array $args, string $name): string
{
    if (empty($args[0])) {
        echo "\033[31mError: Missing required argument '{$name}'\033[0m\n";
        echo "Usage: php cli/script.php make:test <{$name}>\n";
        exit(2);
    }
    return $args[0];
}
```

### Type Coercion

```php
private function parseIntArg(?string $value, int $default = 10): int
{
    if ($value === null) return $default;
    if (!ctype_digit($value)) {
        echo "\033[31mError: Expected integer, got '{$value}'\033[0m\n";
        exit(2);
    }
    return (int) $value;
}
```

### Pattern from [`cli/test.php`](../Legacy.old/cli/test.php)

```php
case 'make:test':
    $this->makeTest($argv[2] ?? null, 'unit', $argv);
    break;

private function makeTest(?string $name, string $type, array $argv): void
{
    if (!$name) {
        echo "\033[31mError: Test name required\033[0m\n";
        echo "Usage: php cli/test.php make:test <name>\n";
        exit(2);
    }
    // ... generate test file ...
}
```

### Validation Library

```php
trait ValidatesInput
{
    protected function requireArg(array $args, int $index, string $label): string
    {
        if (!isset($args[$index]) || trim($args[$index]) === '') {
            $this->fail("Missing required argument: {$label}", 2);
        }
        return trim($args[$index]);
    }

    protected function validateOneOf(string $value, array $allowed, string $label): string
    {
        if (!in_array($value, $allowed)) {
            $this->fail(
                "Invalid {$label}: '{$value}'. Allowed: " . implode(', ', $allowed),
                2
            );
        }
        return $value;
    }

    protected function validatePath(string $path, string $label): string
    {
        if (!file_exists($path)) {
            $this->fail("{$label} not found: {$path}", 1);
        }
        return realpath($path);
    }

    protected function fail(string $message, int $code = 1): void
    {
        echo "\033[31mError: {$message}\033[0m\n";
        exit($code);
    }
}
```

---

## 3. Middleware Patterns

Middleware in CLI scripts runs **before** (pre-hooks) and **after** (post-hooks) command execution.

### Pre-Execution Middleware

From [`cli/deploy.php`](../Legacy.old/cli/deploy.php):

```php
private function runPreChecks(): void
{
    echo "[0/9] Running pre-deployment checks...\n";

    // PHP version check
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '8.0.0', '<')) {
        throw new \Exception("PHP 8.0+ required. Current: {$phpVersion}");
    }
    echo "  ✓ PHP version: {$phpVersion}\n";

    // Extension checks
    $requiredExtensions = ['pdo', 'pdo_mysql', 'zip', 'json', 'mbstring', 'fileinfo'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            throw new \Exception("Required extension missing: {$ext}");
        }
    }
    echo "  ✓ All required extensions loaded\n";

    // File structure check
    $requiredDirs = ['config', 'database/migrations', 'storage'];
    foreach ($requiredDirs as $dir) {
        if (!is_dir($this->basePath . '/' . $dir)) {
            throw new \Exception("Required directory missing: {$dir}");
        }
    }
    echo "  ✓ Directory structure valid\n";
}
```

### Post-Execution Middleware

```php
private function runPostHooks(\Throwable $error = null): void
{
    if ($error) {
        echo "\033[31mDeployment failed: {$error->getMessage()}\033[0m\n";
        $this->logFailure($error);
        exit(1);
    }

    echo "\n\033[32m✓ Deployment completed successfully\033[0m\n";
    $elapsed = round(microtime(true) - $this->startTime, 2);
    echo "Duration: {$elapsed}s\n";
    $this->logSuccess();
}
```

### Middleware Chain Pattern

```php
private function executeWithMiddleware(callable $command): void
{
    try {
        $this->beforeCommand();
        $command();
        $this->afterCommand();
    } catch (\Throwable $e) {
        $this->onError($e);
    }
}

protected function beforeCommand(): void
{
    $this->startTime = microtime(true);
    // Auth check, environment validation, lock acquisition
}

protected function afterCommand(): void
{
    // Cleanup, logging, cache flush, lock release
    $elapsed = round(microtime(true) - $this->startTime, 2);
    echo "Duration: {$elapsed}s\n";
}

protected function onError(\Throwable $e): void
{
    echo "\033[31mError: {$e->getMessage()}\033[0m\n";
    if (in_array('--verbose', $this->argv ?? [])) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
```

---

## 4. Event Dispatch from CLI

CLI commands can integrate with DGLab's event system for observability and audit.

### Pattern from [`cli/test.php`](../Legacy.old/cli/test.php)

```php
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Events\TestSuite\TestSuiteStarted;
use DGLab\Events\TestSuite\TestSuiteFinished;
use DGLab\Events\TestSuite\TestSuiteFailed;

private function dispatch($event): void
{
    if ($this->app->has(DispatcherInterface::class)) {
        $this->app->get(DispatcherInterface::class)->dispatch($event);
    }
}

private function executeTests(array $argv): void
{
    $suiteName = 'Full Suite';
    if ($this->hasOption($argv, '--unit')) $suiteName = 'Unit';
    if ($this->hasOption($argv, '--integration')) $suiteName = 'Integration';

    // Dispatch event BEFORE execution
    $this->dispatch(new TestSuiteStarted($suiteName, ['argv' => $argv]));

    // ... run tests ...

    // Dispatch event AFTER execution
    $success = $resultCode === 0;
    $this->dispatch(new TestSuiteFinished($suiteName, $success, ['exit_code' => $resultCode]));

    if (!$success) {
        $this->dispatch(new TestSuiteFailed($suiteName, "PHPUnit exited with code {$resultCode}"));
        exit($resultCode);
    }
}
```

### Setting Up Event Listeners in CLI Bootstrap

```php
private function setupNotifications(): void
{
    if ($this->app->has(DispatcherInterface::class)) {
        $dispatcher = $this->app->get(DispatcherInterface::class);
        $logger = $this->app->get(LoggerInterface::class);

        $subscriber = new TestNotificationSubscriber($logger);
        $dispatcher->listen(TestSuiteStarted::class, [$subscriber, 'onTestStarted']);
        $dispatcher->listen(TestSuiteFinished::class, [$subscriber, 'onTestFinished']);
        $dispatcher->listen(TestSuiteFailed::class, [$subscriber, 'onTestFailed']);
    }
}
```

### Event Lifecycle for CLI Commands

```
Command Start → dispatch(CommandStarted) → Execute Logic → dispatch(CommandFinished)
                                           ↘ On Error → dispatch(CommandFailed)
```

---

## 5. Configuration Loading

CLI scripts load configuration from the Application container:

```php
use DGLab\Core\Application;

$app = new Application(dirname(__DIR__));

// Load configuration
$app->loadConfig();
$dbConfig = $app->config('database');
$appConfig = $app->config('app');

// Override config for CLI context
$app->setConfig('app.env', 'cli');

// Access specific values
$dbDriver = $dbConfig['default'] ?? 'sqlite';
$debug = $appConfig['debug'] ?? false;
```

### Direct Config File Loading

For scripts that don't need the full application boot:

```php
$config = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';
```

---

## 6. Test Scaffolding Commands

The `make:*` pattern generates files from templates. This is used extensively in [`cli/test.php`](../Legacy.old/cli/test.php) and [`cli/super.php`](../Legacy.old/cli/super.php).

### Scaffolding Template Pattern

```php
private function makeTest(?string $name, string $type, array $argv): void
{
    if (!$name) {
        echo "\033[31mError: Test name required\033[0m\n";
        exit(2);
    }

    $className = str_replace('/', '', $name);
    $namespace = 'DGLab\\Tests\\' . ucfirst($type);
    $baseClass = match ($type) {
        'unit' => 'TestCase',
        'integration' => 'IntegrationTestCase',
        'browser' => 'BrowserTestCase',
        default => 'TestCase',
    };
    $directory = $this->app->getBasePath() . '/tests/' . ucfirst($type);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    // Template
    $content = <<<PHP
<?php

namespace {$namespace};

use DGLab\\Tests\\{$baseClass};

class {$className}Test extends {$baseClass}
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_something(): void
    {
        \$this->assertTrue(true);
    }
}

PHP;

    $path = $directory . '/' . $className . 'Test.php';

    if (file_exists($path) && !in_array('--force', $argv)) {
        echo "\033[33mFile already exists: {$path}\033[0m\n";
        echo "Use --force to overwrite.\n";
        exit(1);
    }

    file_put_contents($path, $content);
    echo "\033[32m✓ Created: {$path}\033[0m\n";
}
```

### Available Scaffold Commands Reference

| Command | Generates | Base Class | Location |
|---------|-----------|------------|----------|
| `make:test <name>` | Unit test | `TestCase` | `tests/Unit/` |
| `make:integration-test <name>` | Integration test | `IntegrationTestCase` | `tests/Integration/` |
| `make:browser-test <name>` | Browser test | `BrowserTestCase` | `tests/Browser/` |
| `make:component-test <name>` | Component test | `ComponentTestCase` | `tests/Component/` |
| `make:component <name>` | SuperPHP component | — | `resources/views/components/` |
| `make:view <name>` | SuperPHP view | — | `resources/views/` |

---

## 7. Parallel Execution Pattern

From [`cli/test.php`](../Legacy.old/cli/test.php), the parallel execution pattern splits test files across child processes:

```php
private function runParallel(array $args): bool
{
    echo "\033[34m[PARALLEL MODE]\033[0m Splitting tests...\n";

    $files = [];
    $testPath = $this->app->getBasePath() . '/tests';
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($testPath));

    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
            $files[] = $file->getRealPath();
        }
    }

    if (empty($files)) {
        echo "\033[33mNo test files found.\033[0m\n";
        return true;
    }

    // Split into chunks for each child process
    $numWorkers = min(4, count($files));
    $chunks = array_chunk($files, ceil(count($files) / $numWorkers));
    $children = [];

    foreach ($chunks as $chunk) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            echo "\033[31mFork failed\033[0m\n";
            return false;
        }

        if ($pid === 0) {
            // Child process
            $fileArgs = implode(' ', array_map('escapeshellarg', $chunk));
            passthru("vendor/bin/phpunit --colors=always {$fileArgs}", $exitCode);
            exit($exitCode);
        }

        $children[] = $pid;
    }

    // Parent waits for all children
    $success = true;
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            $success = false;
        }
    }

    return $success;
}
```

> **Note**: `pcntl_fork` is only available on Linux/Mac. Windows users will run in serial mode.

---

## Complete Example: Tool CLI

```php
<?php
/**
 * MyTool CLI - Complete Intermediate Example
 *
 * Usage: php cli/mytool.php <command> [arguments] [--options]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use Psr\Log\LoggerInterface;

class MyToolCLI
{
    private Application $app;
    private array $commands = [];
    private float $startTime;

    public function __construct()
    {
        $this->app = new Application(dirname(__DIR__));
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->commands = [
            'process'      => 'Process items. Usage: process <file> [--limit=N]',
            'validate'     => 'Validate a configuration file. Usage: validate <path>',
            'report'       => 'Generate a report. Usage: report [--format=json|table]',
            'make:config'  => 'Scaffold a config file. Usage: make:config <name>',
        ];
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        if (in_array($command, ['help', '--help', '-h'])) {
            $this->displayHelp();
            return;
        }

        if (!isset($this->commands[$command])) {
            echo "\033[31mUnknown command: {$command}\033[0m\n";
            exit(127);
        }

        $this->startTime = microtime(true);
        $args = array_slice($argv, 2);

        try {
            $this->executeWithMiddleware(function () use ($command, $args) {
                $this->dispatch('tool.command.started', ['command' => $command]);
                $this->handleCommand($command, $args);
                $this->dispatch('tool.command.finished', ['command' => $command]);
            });
        } catch (\Throwable $e) {
            $this->dispatch('tool.command.failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            echo "\033[31mError: {$e->getMessage()}\033[0m\n";
            exit(1);
        }
    }

    private function handleCommand(string $command, array $args): void
    {
        $verbose = in_array('--verbose', $args);

        match ($command) {
            'process' => $this->processItems($args),
            'validate' => $this->validateConfig($args),
            'report' => $this->generateReport($args),
            'make:config' => $this->scaffoldConfig($args),
        };
    }

    private function processItems(array $args): void
    {
        $file = $args[0] ?? null;
        if (!$file) {
            echo "\033[31mError: Missing required argument 'file'\033[0m\n";
            exit(2);
        }
        if (!file_exists($file)) {
            echo "\033[31mError: File not found: {$file}\033[0m\n";
            exit(1);
        }

        $limit = $this->getIntOption($args, 'limit', 100);
        echo "\033[34m[INFO]\033[0m Processing {$file} with limit {$limit}...\n";
        // ... process logic ...
        echo "\033[32m✓ Done\033[0m\n";
    }

    private function validateConfig(array $args): void
    {
        $path = $args[0] ?? null;
        $this->requireArg($path, 'path');
        $this->validatePath($path, 'Config');
        echo "\033[32m✓ Config valid: {$path}\033[0m\n";
    }

    private function generateReport(array $args): void
    {
        $format = $this->getOption($args, 'format', 'table');
        $this->validateOneOf($format, ['json', 'table'], 'format');
        // ... report logic ...
    }

    private function scaffoldConfig(array $args): void
    {
        $name = $args[0] ?? null;
        $this->requireArg($name, 'name');
        $path = dirname(__DIR__) . '/config/' . $name . '.php';
        if (file_exists($path) && !in_array('--force', $args)) {
            echo "\033[33mFile exists: {$path}. Use --force to overwrite.\033[0m\n";
            exit(1);
        }
        file_put_contents($path, "<?php\n\nreturn [\n    // {$name} configuration\n];\n");
        echo "\033[32m✓ Created: {$path}\033[0m\n";
    }

    private function dispatch(string $event, array $data = []): void
    {
        // Event dispatch integration placeholder
    }

    private function executeWithMiddleware(callable $fn): void
    {
        echo "\033[1mStarting...\033[0m\n";
        $fn();
        $elapsed = round(microtime(true) - $this->startTime, 2);
        echo "Completed in {$elapsed}s\n";
    }

    // --- Helpers ---

    private function displayHelp(): void
    {
        echo "\033[1mMyTool CLI\033[0m\n";
        echo "Usage: php cli/mytool.php <command> [arguments] [--options]\n\n";
        echo "Commands:\n";
        foreach ($this->commands as $name => $desc) {
            printf("  %-20s %s\n", $name, $desc);
        }
        exit(0);
    }

    private function requireArg(?string $value, string $label): void
    {
        if (!$value) {
            echo "\033[31mError: Missing required argument '{$label}'\033[0m\n";
            exit(2);
        }
    }

    private function validatePath(string $path, string $label): string
    {
        if (!file_exists($path)) {
            echo "\033[31mError: {$label} not found: {$path}\033[0m\n";
            exit(1);
        }
        return realpath($path);
    }

    private function validateOneOf(string $value, array $allowed, string $label): void
    {
        if (!in_array($value, $allowed)) {
            echo "\033[31mError: Invalid {$label}. Allowed: " . implode(', ', $allowed) . "\033[0m\n";
            exit(2);
        }
    }

    private function getOption(array $args, string $name, string $default = null): ?string
    {
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
            if ($arg === "--{$name}" && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
        }
        return $default;
    }

    private function getIntOption(array $args, string $name, int $default): int
    {
        $value = $this->getOption($args, $name);
        return $value !== null ? (int) $value : $default;
    }
}

// Entry point
$cli = new MyToolCLI();
$cli->run($argv);
```

---

## Next Steps

Proceed to the [Advanced Guide](./advanced-guide.md) to learn:

- DI container integration for complex CLI commands
- Plugin command registration architecture
- Daemon/worker processes with signal handling
- Progress bars and formatted output (JSON, table)
- Integration with queue, cache, and database services

> **Complexity Tier**: 🟡 Intermediate ✅
> **Estimated completion**: 1–2 days

---

## See Also

- [Beginner Guide](./beginner-guide.md) — Foundation concepts
- [Advanced Guide](./advanced-guide.md) — Next step in the learning path
- [Complexity Tiers Map](./complexity-tiers-map.md) — Where this fits
- [Diagnostic Commands](./diagnostic-commands.md) — Troubleshooting
- [ADR-005 Event System Design](../architecture/decisions/ADR-005-event-system-design.md) — Event architecture
- [Implementation Guide: Event System Wiring](../implementation-guides/event-system-wiring.md) — Event setup