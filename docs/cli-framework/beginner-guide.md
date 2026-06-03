# Beginner Guide: Your First CLI Command

## What is the DGLab CLI?

The DGLab CLI framework is a collection of PHP scripts in the [`cli/`](../Legacy.old/cli/) directory that provide command-line tools for developers. Each script is self-contained, follows consistent conventions, and can be invoked via:

```bash
php cli/script.php <command> [arguments] [--options]
```

Current CLI scripts include:

| Script | Purpose | Commands |
|--------|---------|----------|
| [`cli/test.php`](../Legacy.old/cli/test.php) | Test runner | `run`, `make:test`, `coverage`, `watch`, `health` |
| [`cli/super.php`](../Legacy.old/cli/super.php) | SuperPHP template tools | `compile:all`, `lint`, `make:component`, `state:*` |
| [`cli/deploy.php`](../Legacy.old/cli/deploy.php) | Deployment automation | Multi-step pipeline |
| [`cli/events.php`](../Legacy.old/cli/events.php) | Event management | `list`, `worker` |
| [`cli/migrate.php`](../Legacy.old/cli/migrate.php) | Database migrations | Migration runner |
| [`cli/worker.php`](../Legacy.old/cli/worker.php) | Queue worker | Long-running worker |
| [`cli/cleanup.php`](../Legacy.old/cli/cleanup.php) | Cleanup tasks | One-off maintenance |
| [`cli/build-assets.php`](../Legacy.old/cli/build-assets.php) | Asset building | Asset pipeline |
| [`cli/nexus.php`](../Legacy.old/cli/nexus.php) | Nexus orchestration | Service coordination |

> **Complexity Tier**: 🟢 Essential — No prerequisites

---

## Quick Start: Your First Command

Create a new CLI script that greets the user:

### Step 1: Create the script file

Create [`cli/greet.php`](../Legacy.old/cli/greet.php):

```php
#!/usr/bin/env php
<?php
/**
 * DGLab Greeting CLI
 *
 * Usage: php cli/greet.php <name>
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Parse the command (first argument after the script name)
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'help':
        displayHelp();
        break;
    case 'greet':
        greetUser($argv);
        break;
    default:
        echo "\033[31mUnknown command: {$command}\033[0m\n";
        exit(1);
}
```

### Step 2: Add the handler functions

Add these functions to [`cli/greet.php`](../Legacy.old/cli/greet.php):

```php
function displayHelp(): void
{
    echo "\033[1mDGLab Greeting CLI\033[0m\n";
    echo "Usage: php cli/greet.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  greet <name>  Say hello to someone\n";
    echo "  help          Show this help message\n\n";
    echo "Options:\n";
    echo "  --verbose     Show detailed output\n";
    exit(0);
}

function greetUser(array $argv): void
{
    $name = $argv[2] ?? 'World';
    $verbose = in_array('--verbose', $argv);

    if ($verbose) {
        echo "\033[34m[INFO]\033[0m Greeting user: {$name}\n";
    }

    echo "Hello, {$name}!\n";
    exit(0);
}
```

### Step 3: Run it

```bash
php cli/greet.php greet Alice
# Output: Hello, Alice!

php cli/greet.php greet --verbose Bob
# Output: [INFO] Greeting user: Bob
#         Hello, Bob!

php cli/greet.php help
# Output: DGLab Greeting CLI
#         Usage: ...
```

---

## Understanding `$argv` and `$argc`

PHP CLI scripts receive arguments through two superglobals:

- **`$argv`**: Array of arguments (`$argv[0]` is the script name, `$argv[1]` is the first argument)
- **`$argc`**: Count of arguments (number of elements in `$argv`)

```php
// php cli/greet.php greet Alice --verbose

$argv[0]; // 'cli/greet.php'
$argv[1]; // 'greet'
$argv[2]; // 'Alice'
$argv[3]; // '--verbose'
$argc;    // 4
```

### Common Parsing Patterns

```php
// Get the command (with default fallback)
$command = $argv[1] ?? 'help';

// Get a positional argument (with default)
$name = $argv[2] ?? null;
$count = isset($argv[2]) ? (int)$argv[2] : 10;

// Check for boolean flags
$verbose = in_array('--verbose', $argv);
$force   = in_array('--force', $argv);
$dryRun  = in_array('--dry-run', $argv);

// Get a named option value
$configPath = null;
foreach ($argv as $i => $arg) {
    if ($arg === '--config' && isset($argv[$i + 1])) {
        $configPath = $argv[$i + 1];
        break;
    }
}
```

---

## Exit Codes

Exit codes communicate success/failure to the shell and CI pipelines:

| Code | Meaning | When to Use |
|:----:|---------|-------------|
| `0` | Success | Command completed successfully |
| `1` | General error | Validation failure, runtime exception |
| `2` | Misuse | Invalid arguments, missing required params |
| `127` | Command not found | Unknown subcommand |

```php
// Success
exit(0);

// Error with message
echo "\033[31mError: File not found\033[0m\n";
exit(1);

// Misuse (wrong arguments)
echo "\033[33mUsage: php cli/greet.php <name>\033[0m\n";
exit(2);

// Unknown command
echo "\033[31mUnknown command: {$command}\033[0m\n";
exit(127);
```

> **CI Integration**: Exit codes are critical for CI/CD pipelines. A non-zero exit code will fail the build step.

---

## Output Conventions

### ANSI Color Codes

| Color | Code | Usage |
|-------|:----:|-------|
| **Bold** | `\033[1m` | Headers, titles |
| **Red** | `\033[31m` | Errors, failures |
| **Green** | `\033[32m` | Success messages |
| **Yellow** | `\033[33m` | Warnings, usage hints |
| **Blue** | `\033[34m` | Info, verbose details |
| **Cyan** | `\033[36m` | Debug, trace output |
| **Reset** | `\033[0m` | End colored section |

### Prefix Conventions

```php
echo "\033[32m  ✓ {$message}\033[0m\n";   // Success
echo "\033[31m  ✗ {$message}\033[0m\n";   // Failure
echo "\033[33m  ! {$message}\033[0m\n";   // Warning
echo "\033[34m  ℹ {$message}\033[0m\n";   // Info
echo "\033[1m{$title}\033[0m\n";           // Header
```

### Step Numbering for Multi-Step Commands

```php
echo "[1/5] Validating environment...\n";
// ... do step 1 ...
echo "[2/5] Running migrations...\n";
// ... do step 2 ...
```

Example from [`cli/deploy.php`](../Legacy.old/cli/deploy.php):

```php
echo "[0/9] Running critical tests...\n";
echo "  ✓ Critical tests passed\n";
echo "[1/9] Validating environment...\n";
echo "  ✓ PHP version: 8.2.0\n";
```

---

## Help System Convention

Every CLI script should implement a `help` command:

```php
case 'help':
case '--help':
case '-h':
    displayHelp();
    break;
```

Example help output format:

```
\033[1mScript Title\033[0m              ← Bold title
\033[34mDescription of what it does\033[0m  ← Optional description

Usage: php cli/script.php <command> [arguments] [--options]

Commands:
  run              Execute the main operation
  make:test        Create a new test file
  status           Show current status
  help             Show this help message

Options:
  --verbose        Show detailed output
  --dry-run        Simulate without making changes
  --json           Output in JSON format
```

---

## Helper Functions Library

These reusable helpers appear across multiple CLI scripts:

```php
/**
 * Check if a command-line option flag is present
 */
function hasOption(array $argv, string $option): bool
{
    return in_array($option, $argv);
}

/**
 * Get the value of a named option (--name=value or --name value)
 */
function getOption(array $argv, string $option): ?string
{
    foreach ($argv as $i => $arg) {
        if (str_starts_with($arg, "--{$option}=")) {
            return substr($arg, strlen("--{$option}="));
        }
        if ($arg === "--{$option}" && isset($argv[$i + 1])) {
            return $argv[$i + 1];
        }
    }
    return null;
}

/**
 * Filter positional arguments (exclude flags and option values)
 */
function getPositionalArgs(array $argv): array
{
    $args = array_slice($argv, 2);
    $filtered = [];
    for ($i = 0; $i < count($args); $i++) {
        if (str_starts_with($args[$i], '-')) {
            // Skip flag and its value
            if (!str_contains($args[$i], '=') && isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                $i++; // skip option value
            }
            continue;
        }
        $filtered[] = $args[$i];
    }
    return $filtered;
}
```

---

## Common Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| `php: command not found` | PHP not in PATH | Install PHP or use full path |
| `Class "DGLab/... not found` | Autoload not generated | Run `composer dump-autoload` |
| `No such file or directory` | Wrong working directory | Run from project root |
| Output shows `[31m` literally | Terminal doesn't support ANSI | Remove color codes or use `--no-ansi` |
| `Undefined array key 2` | Missing required argument | Add argument or use `??` default |
| Exit code 255 | Fatal PHP error | Check PHP error log |

---

## Real-World Examples

### Minimal Script ([`cli/cleanup.php`](../Legacy.old/cli/cleanup.php))

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv);

echo $dryRun ? "[DRY RUN] " : "";
echo "Cleaning up temporary files...\n";
// cleanup logic...
exit(0);
```

### Deploy Script ([`cli/deploy.php`](../Legacy.old/cli/deploy.php))

```php
// Parse skip flags
$skipTests = in_array('--skip-tests', $argv);
$force     = in_array('--force', $argv);

// Render URL option
$renderUrl = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--render-url=')) {
        $renderUrl = substr($arg, strlen('--render-url='));
    }
}
```

---

## Next Steps

Once you're comfortable with basic CLI scripts, proceed to the [Intermediate Guide](./intermediate-guide.md) which covers:

- Command-map routing for complex multi-command scripts
- Input validation and error handling
- Event dispatch from CLI commands
- Config loading and middleware patterns
- Test scaffolding commands

> **Complexity Tier**: 🟢 Essential ✅
> **Estimated completion**: 30–60 minutes

---

## See Also

- [Complexity Tiers Map](./complexity-tiers-map.md) — Where this guide fits in the big picture
- [Intermediate Guide](./intermediate-guide.md) — Next step in the learning path
- [Diagnostic Commands](./diagnostic-commands.md) — Troubleshooting your CLI setup
- [ADR-004 Routing Strategy](../architecture/decisions/ADR-004-routing-strategy.md) — Architectural background