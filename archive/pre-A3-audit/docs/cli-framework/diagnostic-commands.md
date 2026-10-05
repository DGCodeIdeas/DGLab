# Diagnostic Commands

## Overview

The DGLab CLI framework includes diagnostic capabilities for verifying configuration, checking dependency health, and troubleshooting common issues. This document covers the `dglab diagnose-setup` command concept, configuration verification patterns, dependency health checks, and a comprehensive troubleshooting guide.

> **Prerequisites**: [Intermediate Guide](./intermediate-guide.md) — CLI routing, middleware, config loading
>
> **Complexity Tier**: 🟡 Intermediate / 🔴 Advanced

---

## 1. `dglab diagnose-setup` Command Concept

The unified diagnostic command inspects the entire DGLab environment and reports issues. Below is the conceptual design:

```php
<?php
/**
 * dglab diagnose-setup — Unified Diagnostic Command
 *
 * Usage: php cli/diagnose.php [--verbose] [--json] [--fix]
 */

class DiagnoseCLI
{
    private array $results = [];
    private array $fixes = [];

    public function run(array $argv): void
    {
        $verbose = in_array('--verbose', $argv);
        $jsonOutput = in_array('--json', $argv);
        $autoFix = in_array('--fix', $argv);

        echo "\033[1mDGLab Environment Diagnostic\033[0m\n";
        echo "============================\n\n";

        // Run all checks
        $this->checkPHPEnvironment();
        $this->checkExtensions();
        $this->checkConfiguration();
        $this->checkDirectories();
        $this->checkDatabase();
        $this->checkEvents();
        $this->checkDependencies();

        // Report results
        if ($jsonOutput) {
            echo json_encode($this->results, JSON_PRETTY_PRINT);
        } else {
            $this->displayReport();
        }

        // Auto-fix if requested
        if ($autoFix && !empty($this->fixes)) {
            $this->applyFixes();
        }

        // Exit code: 0 = all healthy, 1 = warnings, 2 = errors
        $hasErrors = count(array_filter($this->results, fn($r) => $r['status'] === 'error')) > 0;
        $hasWarnings = count(array_filter($this->results, fn($r) => $r['status'] === 'warning')) > 0;
        exit($hasErrors ? 2 : ($hasWarnings ? 1 : 0));
    }

    private function addResult(string $check, string $status, string $message, ?callable $fix = null): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $status, // 'pass', 'warning', 'error'
            'message' => $message,
        ];
        if ($fix) {
            $this->fixes[] = ['check' => $check, 'fix' => $fix];
        }
    }

    private function displayReport(): void
    {
        $passed = 0;
        foreach ($this->results as $r) {
            $icon = match ($r['status']) {
                'pass' => "\033[32m✓\033[0m",
                'warning' => "\033[33m!\033[0m",
                'error' => "\033[31m✗\033[0m",
            };
            echo "{$icon} {$r['check']}: {$r['message']}\n";
            if ($r['status'] === 'pass') $passed++;
        }

        echo "\n\033[1mSummary:\033[0m {$passed}/" . count($this->results) . " checks passed\n";
    }

    // ... check methods ...
}
```

### Expected Output

```
DGLab Environment Diagnostic
============================

✓ PHP Version: 8.2.0 (required: 8.0+)
✓ Extensions: All 6 required extensions loaded
✓ Config app.php: Valid, all required keys present
✓ Config database.php: Valid, database path exists
! Storage directory: Exists but not writable
✗ Database connection: Failed — cannot connect to MySQL
✓ Event dispatcher: Registered with 12 listeners
✓ Composer dependencies: All installed, no conflicts
! Migration status: 3 pending migrations

Summary: 6/8 checks passed
```

---

## 2. Configuration Verification Patterns

### File Existence & Parse Validation

```php
private function checkConfiguration(): void
{
    // Config file paths
    $configFiles = [
        'app' => dirname(__DIR__) . '/config/app.php',
        'database' => dirname(__DIR__) . '/config/database.php',
        'events' => dirname(__DIR__) . '/config/events.php',
        'routing' => dirname(__DIR__) . '/config/routing.php',
        'services' => dirname(__DIR__) . '/config/services.php',
    ];

    foreach ($configFiles as $name => $path) {
        if (!file_exists($path)) {
            $this->addResult(
                "Config {$name}.php",
                'error',
                "File not found: {$path}",
                fn() => file_put_contents($path, "<?php\n\nreturn [\n    // {$name} configuration\n];\n")
            );
            continue;
        }

        // Verify PHP parse
        $output = [];
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->addResult("Config {$name}.php", 'error', "Parse error: " . implode(' ', $output));
            continue;
        }

        // Verify it returns an array
        $config = require $path;
        if (!is_array($config)) {
            $this->addResult("Config {$name}.php", 'error', "Must return an array");
            continue;
        }

        $this->addResult("Config {$name}.php", 'pass', 'Valid, parsed successfully');
    }

    // Check required config keys
    $this->checkRequiredConfigKeys();
}

private function checkRequiredConfigKeys(): void
{
    $appConfig = require dirname(__DIR__) . '/config/app.php';

    $requiredKeys = ['name', 'env', 'debug', 'url', 'timezone'];
    $missing = [];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $appConfig)) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        $this->addResult(
            'Required config keys',
            'warning',
            'Missing: ' . implode(', ', $missing)
        );
    } else {
        $this->addResult('Required config keys', 'pass', 'All present');
    }
}
```

### Environment Variable Verification

```php
private function checkEnvironmentVariables(): void
{
    $requiredEnvVars = [
        'APP_ENV' => 'local',
        'APP_DEBUG' => 'true',
        'DB_CONNECTION' => 'sqlite',
    ];

    $optionalEnvVars = [
        'REDIS_HOST' => '127.0.0.1',
        'MAIL_HOST' => null,
        'QUEUE_CONNECTION' => 'sync',
    ];

    foreach ($requiredEnvVars as $var => $default) {
        $value = getenv($var) ?: $default;
        if ($value === null) {
            $this->addResult("ENV: {$var}", 'error', "Not set and no default");
        } else {
            $this->addResult("ENV: {$var}", 'pass', "= {$value}");
        }
    }

    foreach ($optionalEnvVars as $var => $default) {
        $value = getenv($var) ?: $default;
        if ($value === null) {
            $this->addResult("ENV: {$var}", 'warning', "Not set (optional)");
        } else {
            $this->addResult("ENV: {$var}", 'pass', "= {$value}");
        }
    }
}
```

---

## 3. Dependency Health Check Patterns

### PHP Version Check

```php
private function checkPHPEnvironment(): void
{
    $phpVersion = phpversion();
    $requiredVersion = '8.0.0';

    if (version_compare($phpVersion, $requiredVersion, '>=')) {
        $this->addResult('PHP Version', 'pass', "{$phpVersion} (required: {$requiredVersion}+)");
    } else {
        $this->addResult(
            'PHP Version',
            'error',
            "{$phpVersion} — upgrade to {$requiredVersion}+ required"
        );
    }

    // Memory limit
    $memoryLimit = ini_get('memory_limit');
    if ($memoryLimit === '-1' || (int)$memoryLimit >= 128) {
        $this->addResult('PHP Memory Limit', 'pass', $memoryLimit);
    } else {
        $this->addResult(
            'PHP Memory Limit',
            'warning',
            "{$memoryLimit} — consider 128M+ for production"
        );
    }

    // Execution time
    $maxExecTime = ini_get('max_execution_time');
    if ($maxExecTime === '0' || (int)$maxExecTime >= 30) {
        $this->addResult('PHP Max Execution', 'pass', "{$maxExecTime}s");
    } else {
        $this->addResult(
            'PHP Max Execution',
            'warning',
            "{$maxExecTime}s — CLI scripts may timeout"
        );
    }
}
```

### Extension Loaded Check

Using the pattern from [`Legacy.old/cli/deploy.php`](../Legacy.old/cli/deploy.php):

```php
private function checkExtensions(): void
{
    $required = ['pdo', 'pdo_sqlite', 'pdo_mysql', 'zip', 'json', 'mbstring', 'fileinfo'];
    $optional = ['redis', 'xsl', 'curl', 'gd', 'intl', 'bcmath'];
    $allPassed = true;

    foreach ($required as $ext) {
        if (extension_loaded($ext)) {
            $this->addResult("Extension: {$ext}", 'pass', 'Loaded');
        } else {
            $this->addResult("Extension: {$ext}", 'error', "Not loaded — required");
            $allPassed = false;
        }
    }

    foreach ($optional as $ext) {
        if (extension_loaded($ext)) {
            $this->addResult("Extension: {$ext}", 'pass', 'Loaded');
        } else {
            $this->addResult("Extension: {$ext}", 'warning', "Not loaded (optional)");
        }
    }
}
```

### Class/Function Availability Check

```php
private function checkDependencies(): void
{
    // Check critical classes
    $requiredClasses = [
        'DGLab\\Core\\Application',
        'DGLab\\Core\\EventDispatcher',
        'DGLab\\Core\\Router',
        'DGLab\\Database\\Connection',
    ];

    foreach ($requiredClasses as $class) {
        if (class_exists($class)) {
            $this->addResult("Class: {$class}", 'pass', 'Found');
        } else {
            $this->addResult(
                "Class: {$class}",
                'error',
                'Not found — run composer dump-autoload'
            );
        }
    }

    // Check critical functions
    $requiredFunctions = ['event', 'config', 'app'];
    foreach ($requiredFunctions as $fn) {
        if (function_exists($fn)) {
            $this->addResult("Function: {$fn}()", 'pass', 'Available');
        } else {
            $this->addResult("Function: {$fn}()", 'error', 'Not defined');
        }
    }

    // Check pcntl for parallel execution (Linux/Mac only)
    if (PHP_OS_FAMILY !== 'Windows') {
        $pcntlAvailable = function_exists('pcntl_fork') && function_exists('pcntl_waitpid');
        $this->addResult(
            'PCNTL (Parallel)',
            $pcntlAvailable ? 'pass' : 'warning',
            $pcntlAvailable ? 'Available' : 'Not available — parallel execution disabled'
        );
    }
}
```

### Binary Availability Check

```php
private function checkBinary(string $name, string $checkCommand = '--version'): void
{
    $output = [];
    exec("{$name} {$checkCommand} 2>&1", $output, $exitCode);

    if ($exitCode === 0) {
        $this->addResult("Binary: {$name}", 'pass', trim(implode(' ', $output)));
    } else {
        $this->addResult("Binary: {$name}", 'warning', "Not found in PATH");
    }
}
```

### Service Reachability Check

```php
private function checkDatabase(): void
{
    $dbConfig = require dirname(__DIR__) . '/config/database.php';
    $default = $dbConfig['default'] ?? 'sqlite';
    $connection = $dbConfig['connections'][$default] ?? [];

    if ($default === 'sqlite') {
        $database = $connection['database'] ?? ':memory:';
        if ($database === ':memory:') {
            $this->addResult('Database', 'pass', 'Using in-memory SQLite');
            return;
        }
        if (file_exists($database)) {
            $this->addResult('Database', 'pass', "SQLite database found: {$database}");
        } else {
            $this->addResult(
                'Database',
                'error',
                "SQLite database not found: {$database}",
                fn() => touch($database)
            );
        }
        return;
    }

    if (in_array($default, ['mysql', 'pgsql'])) {
        try {
            $dsn = "{$default}:host={$connection['host']};dbname={$connection['database']}";
            new \PDO($dsn, $connection['username'] ?? '', $connection['password'] ?? '', [
                \PDO::ATTR_TIMEOUT => 3,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $this->addResult('Database', 'pass', "Connected to {$default}");
        } catch (\PDOException $e) {
            $this->addResult('Database', 'error', "Cannot connect: {$e->getMessage()}");
        }
    }
}

private function checkCache(): void
{
    $cachePath = dirname(__DIR__) . '/storage/cache';
    if (!is_dir($cachePath)) {
        $this->addResult(
            'Cache Directory',
            'error',
            "Not found: {$cachePath}",
            fn() => mkdir($cachePath, 0775, true)
        );
        return;
    }
    if (!is_writable($cachePath)) {
        $this->addResult(
            'Cache Directory',
            'error',
            "Not writable: {$cachePath}",
            fn() => chmod($cachePath, 0775)
        );
        return;
    }
    $this->addResult('Cache Directory', 'pass', 'Writable');
}
```

### Event System Health Check

```php
private function checkEvents(): void
{
    if (!class_exists('DGLab\\Core\\EventDispatcher')) {
        $this->addResult('Event Dispatcher', 'error', 'EventDispatcher class not found');
        return;
    }

    $app = new \DGLab\Core\Application(dirname(__DIR__));

    if (!$app->has(\DGLab\Core\Contracts\DispatcherInterface::class)) {
        $this->addResult('Event Dispatcher', 'warning', 'Not registered in container');
        return;
    }

    $dispatcher = $app->get(\DGLab\Core\Contracts\DispatcherInterface::class);
    $listeners = $dispatcher->getListeners();
    $count = count($listeners);

    $this->addResult('Event Dispatcher', 'pass', "Registered with {$count} listener(s)");
}
```

---

## 4. Filesystem Permissions Check

```php
private function checkDirectories(): void
{
    $writableDirs = [
        'storage' => dirname(__DIR__) . '/storage',
        'storage/logs' => dirname(__DIR__) . '/storage/logs',
        'storage/cache' => dirname(__DIR__) . '/storage/cache',
        'storage/reports' => dirname(__DIR__) . '/storage/reports',
        'bootstrap/cache' => dirname(__DIR__) . '/bootstrap/cache',
    ];

    foreach ($writableDirs as $name => $path) {
        if (!is_dir($path)) {
            $this->addResult(
                "Directory: {$name}",
                'error',
                "Not found: {$path}",
                function () use ($path) {
                    mkdir($path, 0775, true);
                    echo "  → Created {$path}\n";
                }
            );
            continue;
        }

        if (!is_writable($path)) {
            $this->addResult(
                "Directory: {$name}",
                'error',
                "Not writable: {$path}",
                function () use ($path) {
                    chmod($path, 0775);
                    echo "  → Fixed permissions on {$path}\n";
                }
            );
            continue;
        }

        // Check for disk space
        $freeSpace = disk_free_space($path);
        if ($freeSpace !== false && $freeSpace < 104857600) { // < 100MB
            $this->addResult(
                "Directory: {$name}",
                'warning',
                "Low disk space: " . round($freeSpace / 1048576) . "MB free"
            );
        } else {
            $this->addResult("Directory: {$name}", 'pass', 'Exists and writable');
        }
    }
}
```

---

## 5. Troubleshooting Guide

### Common Issues & Solutions

| # | Symptom | Likely Cause | Diagnostic Check | Resolution |
|---|---------|-------------|-----------------|------------|
| 1 | `Class "DGLab\Core\Application" not found` | Composer autoload not generated | `php -r "require 'vendor/autoload.php';" ` | Run `composer dump-autoload` |
| 2 | `SQLSTATE[HY000] [1045] Access denied` | Database credentials incorrect | Check `config/database.php` | Update username/password in config |
| 3 | `file_put_contents(): Failed to open stream: Permission denied` | Storage directory not writable | `ls -la storage/` | `chmod -R 775 storage/` |
| 4 | `Call to undefined function pcntl_fork()` | PCNTL extension not installed (Windows) | `php -m \| grep pcntl` | Use serial mode; PCNTL not available on Windows |
| 5 | Event not dispatching | Listener not registered | `php cli/events.php list` | Register listener in `EventServiceProvider` |
| 6 | Route returns 404 | Route not registered or cache stale | `php s-forge route:list` | Run `php s-forge route:scan` to discover routes |
| 7 | `Maximum execution time exceeded` | Script too slow for configured limit | Check `max_execution_time` in `php.ini` | Increase limit or optimize script |
| 8 | `Allowed memory size exhausted` | Insufficient memory | `php -r "echo ini_get('memory_limit');"` | Set `memory_limit = 256M` in `php.ini` |
| 9 | `php: command not found` | PHP not in PATH | `where php` (Windows) / `which php` (Linux) | Add PHP to system PATH |
| 10 | Migration fails: "table already exists" | Migration ran partially | Check `migrations` table | Run `php cli/migrate.php --fresh` |
| 11 | Test fails in CI but passes locally | Missing service dependency | Check CI config vs local | Add Testcontainers or mock service |
| 12 | `Cannot modify header information` | Output before response | Check for unwanted whitespace/echo | Remove whitespace before `<?php` |
| 13 | Empty response from API | Exception caught silently | Check `storage/logs/` | Enable `APP_DEBUG=true` |
| 14 | `JSON_ERROR_UTF8` in API response | Non-UTF8 characters in data | Check input encoding | Sanitize input with `mb_convert_encoding()` |
| 15 | Slow page load | Database query N+1 | Check query log | Eager load relationships |
| 16 | Session expired immediately | Session driver misconfigured | Check `config/session.php` | Set correct `lifetime` value |
| 17 | CORS errors in browser | CORS middleware not configured | Check response headers | Add CORS middleware to route group |
| 18 | Asset URLs return 404 | Asset version mismatch | Check `config/assets.php` | Run `php cli/build-assets.php` |

### Quick Diagnostic Commands

```bash
# PHP environment
php -v                              # PHP version
php -m                              # Loaded extensions
php -i | grep memory_limit          # Memory limit
php -i | grep max_execution_time    # Max execution time

# DGLab specific
php cli/events.php list             # Registered event listeners
php cli/test.php health             # Test suite health report
php s-forge route:list              # All registered routes

# Composer
composer dump-autoload              # Regenerate autoloader
composer check-platform-reqs        # Verify platform requirements

# Database
php -r "echo extension_loaded('pdo_sqlite') ? '✓' : '✗';"  # SQLite availability

# Permissions
ls -la storage/                     # Check storage permissions
```

### Health Report Generation

From [`cli/test.php`](../Legacy.old/cli/test.php):

```php
private function generateHealthReport(): void
{
    $reportPath = $this->app->getBasePath() . '/storage/reports';
    if (!is_dir($reportPath)) {
        mkdir($reportPath, 0775, true);
    }

    $report = [
        'generated_at' => date('c'),
        'php_version' => phpversion(),
        'environment' => [
            'os' => PHP_OS,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ],
        'extensions' => get_loaded_extensions(),
        'database' => [
            'default' => config('database.default'),
            'connection' => $this->testDatabaseConnection(),
        ],
        'event_system' => [
            'dispatcher_registered' => $this->app->has(\DGLab\Core\Contracts\DispatcherInterface::class),
        ],
        'storage' => [
            'writable' => is_writable($this->app->getBasePath() . '/storage'),
            'free_space_mb' => round(disk_free_space($this->app->getBasePath() . '/storage') / 1048576),
        ],
    ];

    $filename = 'health-report-' . date('Y-m-d-His') . '.json';
    file_put_contents($reportPath . '/' . $filename, json_encode($report, JSON_PRETTY_PRINT));

    echo "\033[32m✓ Health report generated: {$filename}\033[0m\n";
}
```

---

## 6. Self-Healing Commands

```php
private function applyFixes(): void
{
    echo "\n\033[1mApplying auto-fixes...\033[0m\n";

    foreach ($this->fixes as $fix) {
        echo "  Fixing {$fix['check']}... ";
        try {
            ($fix['fix'])();
            echo "\033[32m✓\033[0m\n";
        } catch (\Throwable $e) {
            echo "\033[31m✗ {$e->getMessage()}\033[0m\n";
        }
    }
}

// Example auto-fixes
private function getAutoFixActions(): array
{
    return [
        'Create storage directories' => function () {
            $dirs = ['storage/logs', 'storage/cache', 'storage/reports'];
            foreach ($dirs as $dir) {
                $path = dirname(__DIR__) . '/' . $dir;
                if (!is_dir($path)) {
                    mkdir($path, 0775, true);
                    echo "  → Created {$dir}\n";
                }
            }
        },
        'Fix storage permissions' => function () {
            $writable = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname(__DIR__) . '/storage')
            );
            foreach ($writable as $item) {
                chmod($item->getPathname(), 0775);
            }
            echo "  → Fixed permissions\n";
        },
        'Clear compiled view cache' => function () {
            $cacheDir = dirname(__DIR__) . '/storage/cache/views';
            if (is_dir($cacheDir)) {
                array_map('unlink', glob($cacheDir . '/*'));
                echo "  → Cleared view cache\n";
            }
        },
    ];
}
```

---

## 7. Verbose Mode Convention

```php
// Usage: php cli/diagnose.php -v (info), -vv (debug), -vvv (trace)
$verbosity = 0;
foreach ($argv as $arg) {
    if ($arg === '-v') $verbosity = max($verbosity, 1);
    if ($arg === '-vv') $verbosity = max($verbosity, 2);
    if ($arg === '-vvv') $verbosity = max($verbosity, 3);
}

function logDiagnostic(string $message, int $level): void
{
    global $verbosity;
    if ($verbosity >= $level) {
        echo "  " . $message . "\n";
    }
}

// Usage
logDiagnostic("Loading config from: {$path}", 2);    // -vv
logDiagnostic("Parsed config keys: " . implode(', ', array_keys($config)), 3); // -vvv
```

---

## Appendix: Diagnostic Checklist

Use this checklist when deploying or troubleshooting:

- [ ] PHP 8.0+ installed and in PATH
- [ ] Required extensions: pdo, pdo_sqlite, pdo_mysql, zip, json, mbstring, fileinfo
- [ ] `composer dump-autoload` run after any class changes
- [ ] Config files exist and parse correctly (`config/app.php`, `config/database.php`, etc.)
- [ ] Storage directories exist and are writable (`storage/logs`, `storage/cache`)
- [ ] Database reachable (SQLite file exists / MySQL server responding)
- [ ] Event dispatcher registered with expected listeners
- [ ] Routes discovered and cached (`php s-forge route:scan`)
- [ ] No pending migrations (`php cli/migrate.php`)
- [ ] Assets compiled (`php cli/build-assets.php`)
- [ ] Test suite passes (`php cli/test.php run --unit --integration`)

---

## See Also

- [Complexity Tiers Map](./complexity-tiers-map.md) — Where diagnostics fit
- [Beginner Guide](./beginner-guide.md) — CLI fundamentals
- [Intermediate Guide](./intermediate-guide.md) — Config loading patterns
- [Advanced Guide](./advanced-guide.md) — Service integration patterns
- [Testing Recipes](../testing/recipes.md) — Testing diagnostic commands
- [Implementation Guide: Routing Configuration](../implementation-guides/routing-configuration.md) — Route diagnostics
- [Implementation Guide: Event System Wiring](../implementation-guides/event-system-wiring.md) — Event diagnostics
- [Implementation Guide: DI Container Setup](../implementation-guides/di-container-setup.md) — Container diagnostics