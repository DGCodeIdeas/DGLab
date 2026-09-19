# Advanced Guide: Custom Commands, Event Hooks & Service Integration

## Overview

This guide covers advanced CLI patterns used by infrastructure engineers and plugin authors building complex command-line tools. You'll learn DI container integration, plugin command registration, daemon/worker processes, progress reporting, formatted output, and integration with DGLab services (events, queue, database, cache).

> **Prerequisites**: [Intermediate Guide](./intermediate-guide.md) — Command-map routing, validation, middleware, events
>
> **Complexity Tier**: 🔴 Advanced

---

## 1. DI Container Integration in CLI

The Application container provides service resolution, configuration, and lifecycle management for CLI commands.

### Pattern from [`cli/test.php`](../Legacy.old/cli/test.php)

```php
use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Database\Connection;
use Psr\Log\LoggerInterface;

class TestCLI
{
    private Application $app;

    public function __construct()
    {
        $this->app = new Application(dirname(__DIR__));
        $this->registerServices();
        $this->registerCommands();
    }

    private function registerServices(): void
    {
        // Bind services for CLI context
        $this->app->singleton(Connection::class, function () use ($app) {
            $dbConfig = require $this->app->getBasePath() . '/config/database.php';
            return new Connection($dbConfig);
        });

        if (!$this->app->has(DispatcherInterface::class)) {
            $this->app->singleton(DispatcherInterface::class, function ($app) {
                return new EventDispatcher($app);
            });
        }
    }
}
```

### Accessing Services in Commands

```php
case 'worker':
    $dispatcher = $this->app->get(DispatcherInterface::class);
    $logger = $this->app->get(LoggerInterface::class);
    $this->runWorker($dispatcher, $logger);
    break;
```

### Singleton vs Factory Binding

```php
// Singleton — one instance shared across all uses
$this->app->singleton(Connection::class, fn() => new Connection($config));

// Factory — new instance each time
$this->app->bind(ReportGenerator::class, fn() => new ReportGenerator());
```

---

## 2. Plugin Command Registration

Architecture for extensible commands that plugins can register dynamically.

### Plugin Command Interface

```php
// app/Contracts/CliCommandInterface.php
namespace App\Contracts;

interface CliCommandInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args, array $options): int;
}
```

### Plugin Registry Pattern

```php
class PluginCommandRegistry
{
    private array $plugins = [];

    public function register(CliCommandInterface $command): void
    {
        $name = $command->getName();
        $this->plugins[$name] = $command;
    }

    public function getCommand(string $name): ?CliCommandInterface
    {
        return $this->plugins[$name] ?? null;
    }

    public function getCommands(): array
    {
        $result = [];
        foreach ($this->plugins as $name => $command) {
            $result[$name] = $command->getDescription();
        }
        return $result;
    }
}
```

### Integration with CLI

```php
class ExtensibleCLI
{
    private Application $app;
    private PluginCommandRegistry $pluginRegistry;
    private array $builtInCommands = [];

    public function __construct()
    {
        $this->app = new Application(dirname(__DIR__));
        $this->pluginRegistry = new PluginCommandRegistry();
        $this->registerBuiltInCommands();
        $this->loadPluginCommands();
    }

    private function loadPluginCommands(): void
    {
        // Scan for plugins that provide CLI commands
        $pluginDirs = glob($this->app->getBasePath() . '/plugins/*/cli.php');
        foreach ($pluginDirs as $pluginFile) {
            $plugin = require $pluginFile;
            if ($plugin instanceof CliCommandInterface) {
                $this->pluginRegistry->register($plugin);
                echo "\033[34m[INFO]\033[0m Loaded plugin command: {$plugin->getName()}\n";
            }
        }
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        // Check built-in commands first
        if (isset($this->builtInCommands[$command])) {
            $this->executeBuiltIn($command, array_slice($argv, 2));
            return;
        }

        // Check plugin commands
        $pluginCommand = $this->pluginRegistry->getCommand($command);
        if ($pluginCommand !== null) {
            $args = array_filter(array_slice($argv, 2), fn($a) => !str_starts_with($a, '-'));
            $options = $this->parseOptions(array_slice($argv, 2));
            exit($pluginCommand->execute($args, $options));
        }

        echo "\033[31mUnknown command: {$command}\033[0m\n";
        exit(127);
    }
}
```

---

## 3. Event Hook System

Lifecycle hooks that fire before, during, and after command execution.

### Hook Registration

```php
class EventHookManager
{
    private array $hooks = [
        'before:command' => [],
        'after:command'  => [],
        'on:error'       => [],
        'before:each'    => [],
        'after:each'     => [],
    ];

    public function on(string $hook, callable $callback, int $priority = 0): void
    {
        $this->hooks[$hook][] = ['callback' => $callback, 'priority' => $priority];
        usort($this->hooks[$hook], fn($a, $b) => $b['priority'] - $a['priority']);
    }

    public function dispatch(string $hook, array $context = []): void
    {
        foreach ($this->hooks[$hook] ?? [] as $handler) {
            ($handler['callback'])($context);
        }
    }
}
```

### Usage in CLI Bootstrap

```php
class HooksExampleCLI
{
    private EventHookManager $hooks;

    public function __construct()
    {
        $this->hooks = new EventHookManager();
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        // Log all commands
        $this->hooks->on('before:command', function ($ctx) {
            echo "\033[34m[HOOK]\033[0m Starting: {$ctx['command']}\n";
        });

        // Validate environment before every command
        $this->hooks->on('before:each', function ($ctx) {
            if (!defined('PHPUNIT_RUNNING') && !defined('DG_CLI_RUNNING')) {
                define('DG_CLI_RUNNING', true);
            }
        });

        // Cleanup after every command
        $this->hooks->on('after:each', function ($ctx) {
            // Close database connections, release locks, etc.
        });

        // Error notification
        $this->hooks->on('on:error', function ($ctx) {
            $this->notifyError($ctx['error']);
        });
    }

    private function executeWithHooks(string $command, array $args, callable $fn): void
    {
        $this->hooks->dispatch('before:command', ['command' => $command]);
        $this->hooks->dispatch('before:each', ['command' => $command]);

        try {
            $fn();
            $this->hooks->dispatch('after:each', ['command' => $command, 'success' => true]);
        } catch (\Throwable $e) {
            $this->hooks->dispatch('on:error', ['command' => $command, 'error' => $e]);
            $this->hooks->dispatch('after:each', ['command' => $command, 'success' => false]);
            throw $e;
        }

        $this->hooks->dispatch('after:command', ['command' => $command]);
    }
}
```

---

## 4. Service Integration Patterns

### CLI + Event Dispatcher

From [`cli/events.php`](../Legacy.old/cli/events.php):

```php
$dispatcher = $app->get(DispatcherInterface::class);
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'list':
        listEvents($dispatcher);
        break;
    case 'worker':
        runWorker($app);
        break;
}

function listEvents(DispatcherInterface $dispatcher): void
{
    $listeners = $dispatcher->getListeners();
    echo "\033[1mRegistered Event Listeners\033[0m\n";
    foreach ($listeners as $event => $handlers) {
        printf("  %-40s %d listener(s)\n", $event, count($handlers));
    }
}
```

### CLI + Queue Worker

From [`cli/worker.php`](../Legacy.old/cli/worker.php):

```php
function runWorker(Application $app): void
{
    $db = $app->get(Connection::class);
    $maxRuntime = 3600; // 1 hour max
    $startTime = time();

    echo "\033[1mQueue Worker Started\033[0m\n";
    echo "Press Ctrl+C to stop.\n\n";

    // Signal handling for graceful shutdown
    $shutdown = false;
    pcntl_signal(SIGTERM, function () use (&$shutdown) {
        echo "\n\033[33mShutdown signal received. Finishing current job...\033[0m\n";
        $shutdown = true;
    });
    pcntl_signal(SIGINT, function () use (&$shutdown) {
        echo "\n\033[33mInterrupt received. Exiting...\033[0m\n";
        $shutdown = true;
    });

    while (!$shutdown) {
        pcntl_signal_dispatch();

        // Check runtime limit
        if (time() - $startTime > $maxRuntime) {
            echo "\033[33mMax runtime reached. Restarting...\033[0m\n";
            break;
        }

        // Poll for next job
        $job = $db->selectOne(
            "SELECT * FROM event_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 1"
        );

        if (!$job) {
            sleep(1); // No jobs, wait and retry
            continue;
        }

        // Process job
        $db->update("UPDATE event_queue SET status = 'processing' WHERE id = ?", [$job->id]);
        // ... process ...
        $db->update("UPDATE event_queue SET status = 'completed' WHERE id = ?", [$job->id]);

        echo "  ✓ Processed job #{$job->id}\n";
    }

    echo "Worker stopped.\n";
}
```

### CLI + Database Migration Runner

From [`cli/migrate.php`](../Legacy.old/cli/migrate.php):

```php
$app = new Application(dirname(__DIR__));
$config = $app->config('database') ?? [];
$db = new \DGLab\Database\Connection($config);

$migration = new \DGLab\Database\Migration($db, $app->getBasePath() . '/database/migrations');
$ran = $migration->run();

if (empty($ran)) {
    echo "Nothing to migrate.\n";
} else {
    foreach ($ran as $migration) {
        echo "  ✓ Ran: {$migration}\n";
    }
}
```

### CLI + Asset Pipeline Orchestration

From [`cli/build-assets.php`](../Legacy.old/cli/build-assets.php):

```php
$service = new \DGLab\Services\AssetPacker\WebpackService();
$steps = [
    'clean'    => fn() => $service->cleanOutput(),
    'compile'  => fn() => $service->compile(),
    'optimize' => fn() => $service->optimize(),
    'version'  => fn() => $service->version(),
];

$total = count($steps);
$current = 0;

foreach ($steps as $name => $step) {
    $current++;
    echo "[{$current}/{$total}] {$name}... ";
    $step();
    echo "\033[32m✓\033[0m\n";
}
```

---

## 5. Daemon/Worker Process Management

### Signal Handling

```php
// Register signal handlers
pcntl_signal(SIGTERM, $sigHandler);
pcntl_signal(SIGINT, $sigHandler);
pcntl_signal(SIGHUP, function () {
    echo "\033[34m[INFO]\033[0m Reloading configuration...\n";
    // reload config
});

// Dispatch pending signals (call in loop)
pcntl_signal_dispatch();
```

### Graceful Shutdown Pattern

```php
class DaemonProcess
{
    private bool $shouldStop = false;
    private int $processedCount = 0;

    public function __construct()
    {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
    }

    public function handleSignal(int $signal): void
    {
        $signalName = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT  => 'SIGINT',
            default => "Signal({$signal})",
        };
        echo "\n\033[33mReceived {$signalName}. Graceful shutdown...\033[0m\n";
        echo "Processed {$this->processedCount} items.\n";
        $this->shouldStop = true;
    }

    public function run(): void
    {
        echo "\033[1mDaemon Started\033[0m (PID: " . getmypid() . ")\n";

        while (!$this->shouldStop) {
            pcntl_signal_dispatch();

            // Do work...
            $this->processedCount++;

            // Check memory usage
            if (memory_get_usage(true) > 64 * 1024 * 1024) { // 64MB
                echo "\033[33mMemory threshold reached. Restarting...\033[0m\n";
                break;
            }

            usleep(100000); // 100ms
        }

        $this->cleanup();
    }

    private function cleanup(): void
    {
        echo "Cleaning up resources...\n";
        // Close connections, release locks, flush buffers
        echo "\033[32m✓ Shutdown complete\033[0m\n";
    }
}
```

### PID File Management

```php
class PidManager
{
    private string $pidFile;

    public function __construct(string $pidFile)
    {
        $this->pidFile = $pidFile;
    }

    public function acquire(): bool
    {
        if (file_exists($this->pidFile)) {
            $pid = (int) file_get_contents($this->pidFile);
            if ($pid && posix_kill($pid, 0)) {
                echo "\033[31mProcess already running (PID: {$pid})\033[0m\n";
                return false;
            }
            echo "\033[33mStale PID file found. Overwriting.\033[0m\n";
        }
        file_put_contents($this->pidFile, getmypid());
        return true;
    }

    public function release(): void
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }
}
```

---

## 6. Progress Reporting

### Progress Bar

```php
function progressBar(int $current, int $total, string $label = '', int $width = 50): void
{
    $pct = round(($current / max($total, 1)) * 100);
    $filled = round(($pct / 100) * $width);
    $empty = $width - $filled;
    $bar = str_repeat('█', $filled) . str_repeat('░', $empty);

    printf("\r%s [%s] %d%% (%d/%d)", $label, $bar, $pct, $current, $total);

    if ($current === $total) {
        echo "\n";
    }
}

// Usage
$items = range(1, 100);
foreach ($items as $i => $item) {
    progressBar($i + 1, count($items), 'Processing');
    usleep(50000); // Simulate work
}
```

### Spinner for Indeterminate Progress

```php
class Spinner
{
    private string $chars = '⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏';
    private int $pos = 0;
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function tick(): void
    {
        echo "\r{$this->message} {$this->chars[$this->pos]}";
        $this->pos = ($this->pos + 1) % strlen($this->chars);
    }

    public function done(string $result = "✓"): void
    {
        echo "\r{$this->message} \033[32m{$result}\033[0m\n";
    }

    public function fail(string $result = "✗"): void
    {
        echo "\r{$this->message} \033[31m{$result}\033[0m\n";
    }
}

// Usage
$spinner = new Spinner("Downloading assets...");
for ($i = 0; $i < 20; $i++) {
    $spinner->tick();
    usleep(100000);
}
$spinner->done();
```

---

## 7. Formatted Output

### Table Output

```php
function renderTable(array $headers, array $rows): void
{
    // Calculate column widths
    $widths = [];
    foreach ($headers as $i => $header) {
        $widths[$i] = strlen($header);
        foreach ($rows as $row) {
            $widths[$i] = max($widths[$i], strlen((string)($row[$i] ?? '')));
        }
        $widths[$i] = min($widths[$i], 60); // cap column width
    }

    // Header
    $separator = '+';
    foreach ($widths as $w) {
        $separator .= str_repeat('-', $w + 2) . '+';
    }
    echo $separator . "\n";

    echo '|';
    foreach ($headers as $i => $header) {
        printf(" %-{$widths[$i]}s |", $header);
    }
    echo "\n" . $separator . "\n";

    // Rows
    foreach ($rows as $row) {
        echo '|';
        foreach ($row as $i => $cell) {
            printf(" %-{$widths[$i]}s |", $cell ?? '');
        }
        echo "\n";
    }

    echo $separator . "\n";
}

// Usage
renderTable(
    ['Command', 'Description', 'Status'],
    [
        ['build', 'Compile all assets', 'available'],
        ['deploy', 'Deploy to production', 'running'],
        ['test', 'Run test suite', 'available'],
    ]
);

// Output:
// +---------+----------------------+-----------+
// | Command | Description          | Status    |
// +---------+----------------------+-----------+
// | build   | Compile all assets   | available |
// | deploy  | Deploy to production | running   |
// | test    | Run test suite       | available |
// +---------+----------------------+-----------+
```

### JSON Output for Machine Readability

```php
if (in_array('--json', $argv)) {
    echo json_encode([
        'success' => true,
        'data' => [
            'commands_executed' => 5,
            'duration' => 12.34,
            'results' => $results,
        ],
    ], JSON_PRETTY_PRINT);
    exit(0);
}
```

### Verbosity Levels

```php
// Convention: -v, -vv, -vvv
$verbosity = 0;
foreach ($argv as $arg) {
    if ($arg === '-v') $verbosity = 1;
    if ($arg === '-vv') $verbosity = 2;
    if ($arg === '-vvv') $verbosity = 3;
}

function log(string $message, int $level = 1): void
{
    global $verbosity;
    if ($verbosity >= $level) {
        $prefix = match ($level) {
            1 => "\033[34m[INFO]\033[0m",
            2 => "\033[36m[DEBUG]\033[0m",
            3 => "\033[35m[TRACE]\033[0m",
        };
        echo "{$prefix} {$message}\n";
    }
}

// Usage
log("Processing items...", 1);    // Shows with -v
log("Processing item #42...", 2); // Shows with -vv
log("Memory: 12MB used", 3);      // Shows with -vvv
```

---

## 8. State Management

### Lock Files for Idempotent Commands

```php
class LockManager
{
    private string $lockDir;

    public function __construct(string $lockDir = null)
    {
        $this->lockDir = $lockDir ?? sys_get_temp_dir() . '/dglab_locks';
        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0777, true);
        }
    }

    public function acquire(string $name): bool
    {
        $lockFile = $this->lockDir . '/' . md5($name) . '.lock';

        if (file_exists($lockFile)) {
            $pid = (int) file_get_contents($lockFile);
            if ($this->isProcessRunning($pid)) {
                return false; // Lock held by running process
            }
            // Stale lock
            unlink($lockFile);
        }

        file_put_contents($lockFile, getmypid());
        return true;
    }

    public function release(string $name): void
    {
        $lockFile = $this->lockDir . '/' . md5($name) . '.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            return count($output) > 1;
        }
        return posix_kill($pid, 0);
    }
}

// Usage
$lock = new LockManager();
if (!$lock->acquire('migration')) {
    echo "\033[33mMigration already running. Skipping.\033[0m\n";
    exit(0);
}
try {
    // ... run migration ...
} finally {
    $lock->release('migration');
}
```

### Status Files for Multi-Step Commands

```php
class StatusTracker
{
    private string $statusFile;

    public function __construct(string $name)
    {
        $this->statusFile = sys_get_temp_dir() . "/dglab_{$name}_status.json";
    }

    public function getStep(string $step): ?string
    {
        if (!file_exists($this->statusFile)) {
            return null;
        }
        $status = json_decode(file_get_contents($this->statusFile), true);
        return $status[$step] ?? null;
    }

    public function completeStep(string $step): void
    {
        $status = file_exists($this->statusFile)
            ? json_decode(file_get_contents($this->statusFile), true)
            : [];
        $status[$step] = 'completed';
        $status['updated_at'] = date('c');
        file_put_contents($this->statusFile, json_encode($status, JSON_PRETTY_PRINT));
    }

    public function allCompleted(): bool
    {
        if (!file_exists($this->statusFile)) return false;
        $status = json_decode(file_get_contents($this->statusFile), true);
        unset($status['updated_at']);
        return !in_array(null, $status, true);
    }

    public function reset(): void
    {
        if (file_exists($this->statusFile)) {
            unlink($this->statusFile);
        }
    }
}
```

---

## 9. Integration Cookbook

### CLI + Event Dispatcher Workflow

```php
// 1. CLI publishes event
$dispatcher->dispatch(new UserRegistered($userData));

// 2. Listeners respond (email, audit log, notification)
$dispatcher->listen(UserRegistered::class, [$emailService, 'sendWelcome']);
$dispatcher->listen(UserRegistered::class, [$auditService, 'logRegistration']);

// 3. CLI verifies outcome
$listeners = $dispatcher->getListeners(UserRegistered::class);
echo "Event dispatched to " . count($listeners) . " listeners.\n";
```

### CLI + Multi-Step Orchestration

```php
// Deploy pipeline pattern from cli/deploy.php
$steps = [
    'Environment Validation',
    'Database Migrations',
    'Asset Compilation',
    'Cache Clear',
    'Config Sync',
    'Route Cache',
    'Queue Restart',
    'Smoke Tests',
    'Health Check',
];

$results = [];
foreach ($steps as $i => $step) {
    echo "[{$i}/" . count($steps) . "] {$step}... ";
    try {
        // Execute step
        $results[$step] = 'passed';
        echo "\033[32m✓\033[0m\n";
    } catch (\Throwable $e) {
        $results[$step] = 'failed';
        echo "\033[31m✗ {$e->getMessage()}\033[0m\n";
        break;
    }
}
```

---

## Next Steps

Continue your learning path:

- [Testing Recipes](../testing/recipes.md) — Use your CLI knowledge alongside testing patterns
- [Diagnostic Commands](./diagnostic-commands.md) — Troubleshoot CLI and service configuration

> **Complexity Tier**: 🔴 Advanced ✅
> **Estimated completion**: 3–5 days

---

## See Also

- [Intermediate Guide](./intermediate-guide.md) — Preceding concepts
- [Complexity Tiers Map](./complexity-tiers-map.md) — Where this fits
- [Diagnostic Commands](./diagnostic-commands.md) — Troubleshooting
- [Testing Recipes](../testing/recipes.md) — Testing patterns
- [Implementation Guide: Routing Configuration](../implementation-guides/routing-configuration.md)
- [Implementation Guide: Event System Wiring](../implementation-guides/event-system-wiring.md)
- [Implementation Guide: DI Container Setup](../implementation-guides/di-container-setup.md)