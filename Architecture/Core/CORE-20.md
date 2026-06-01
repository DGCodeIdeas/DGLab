# PHASE CORE-20: Developer CLI Toolchain

## Tier
Core

## Component Name
Sovereign Forge (DevTools)

## Description
The concrete developer-facing toolchain built atop the `CORE-13` CLI Framework. "The Forge" provides automation for rapid development, including code generation (scaffolding), database migration management, system health diagnostics, and environment synchronization.

## Context7 Research
- **Scaffolding Patterns**: Uses a "Stub-based" approach where template files (with placeholders like `{{CLASS_NAME}}`) are hydrated and written to the filesystem.
- **Migration Logic**: Implements a sequential versioning system for database schemas (Up/Down/Status).
- **UX**: Uses ANSI colors, progress bars, and interactive prompts to provide high-quality developer feedback.

## Architectural Design
- **CommandRegistry**: A specialized discovery engine that registers all "Forge" commands into the main `s-cli` binary.
- **StubEngine**: A lightweight template processor for generating PHP classes.
- **MigrationRunner**: Orchestrates database schema changes using `CORE-19`.
- **HealthChecker**: A diagnostic suite that verifies file permissions, environment variables, and connection health.

### Implementation Example: Scaffolder
```php
namespace Sovereign\Core\Console\Commands;

use Sovereign\Core\Console\Command;
use Sovereign\Core\Filesystem\FilesystemInterface;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller {name : The name of the controller class}';
    protected string $description = 'Create a new controller from a stub';

    public function handle(FilesystemInterface $fs): int
    {
        $name = $this->argument('name');
        $path = "app/Http/Controllers/{$name}.php";

        if ($fs->exists($path)) {
            $this->error("Controller already exists at {$path}");
            return 1;
        }

        $stub = $fs->get('stubs/controller.stub');
        $content = str_replace('{{CLASS_NAME}}', $name, $stub);

        $fs->put($path, $content);
        $this->info("Controller created successfully: {$path}");

        return 0;
    }
}
```

## Interface Contracts

### CommandInterface (Referencing CORE-13)
```php
namespace Sovereign\Core\Console;

interface CommandInterface
{
    public function handle(): int;
    public function argument(string $key): mixed;
    public function option(string $key): mixed;
    public function info(string $message): void;
    public function error(string $message): void;
}
```

### MigrationInterface
```php
namespace Sovereign\Core\Database\Migrations;

interface MigrationInterface
{
    public function up(): void;
    public function down(): void;
}
```

## Integration Strategy
- **Upward**: Built on `CORE-13` (CLI Framework).
- **Downward**: Used by developers throughout the Hub and Spoke tiers to generate boilerplate and manage infrastructure.
- **Dependencies**: Injects `CORE-14` (Filesystem) for stub reading and file writing, and `CORE-19` (DBAL) for migrations.

## CI Verification Criteria
- **Scaffolding Accuracy**: A generated Controller must pass PSR-12 linting immediately without manual adjustment.
- **Migration Rollback**: A "fresh" migration suite (10+ files) must be able to `up` and `down` repeatedly without leaving orphaned tables.
- **Discovery Speed**: Registering 100 commands must not slow down CLI boot time beyond the 20ms threshold defined in `CORE-13`.

## SemVer Impact
**Minor**. Enhances developer experience without altering runtime application logic.
