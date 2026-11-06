<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SovereignStack\Core\Database\Migration\DoctrineMigrationRunner;
use SovereignStack\Core\Database\Migration\MigrationRunnerInterface;

$command = $argv[1] ?? 'status';
$migrationsPath = $argv[2] ?? null;

if ($migrationsPath === null) {
    // Default: scan all component migration directories
    $migrationsPath = __DIR__ . '/../Spokes/Internal/Lms/migrations';
}

$runner = new DoctrineMigrationRunner($migrationsPath);

switch ($command) {
    case 'status':
        $status = $runner->status();
        foreach ($status as $m) {
            $state = $m['executed'] ? '✓' : '✗';
            echo sprintf("%s %s  %s  %s\n", $state, $m['version'], $m['description'], $m['executedAt']?->format('Y-m-d H:i:s') ?? '');
        }
        break;

    case 'migrate':
        $target = $argv[3] ?? null;
        $applied = $runner->migrate($target);
        foreach ($applied as $v) {
            echo "Applied: $v\n";
        }
        if (empty($applied)) {
            echo "No pending migrations.\n";
        }
        break;

    case 'rollback':
        try {
            $rolledBack = $runner->rollback($argv[3] ?? null);
            foreach ($rolledBack as $v) {
                echo "Rolled back: $v\n";
            }
        } catch (\RuntimeException $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    default:
        echo "Usage: php migrate.php [status|migrate|rollback] [migrations_path] [target_version]\n";
        exit(1);
}
