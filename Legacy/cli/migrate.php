<?php
/**
 * DGLab Migration CLI Tool
 * 
 * Run database migrations from command line.
 * 
 * Usage:
 *   php cli/migrate.php [command] [options]
 * 
 * Commands:
 *   run       Run pending migrations (default)
 *   rollback  Rollback last batch
 *   reset     Rollback all migrations
 *   refresh   Rollback and re-run all migrations
 *   status    Show migration status
 *   create    Create a new migration file
 * 
 * Options:
 *   --dry-run  Show what would be executed without running
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

use DGLab\Database\Connection;
use DGLab\Database\Migration;

// Parse arguments
$command = $argv[1] ?? 'run';
$options = [
    'dry-run' => in_array('--dry-run', $argv),
];

// Initialize database connection
$config = require __DIR__ . '/../config/database.php';
$db = new Connection($config);

// Initialize migration
$migration = new Migration($db, __DIR__ . '/../database/migrations');

echo "DGLab Migration Tool\n";
echo "====================\n\n";

try {
    switch ($command) {
        case 'run':
        case 'up':
            echo "Running pending migrations...\n";
            
            if ($options['dry-run']) {
                $pending = $migration->getPendingMigrations();
                if (empty($pending)) {
                    echo "No pending migrations.\n";
                } else {
                    echo "Would run:\n";
                    foreach ($pending as $m) {
                        echo "  - {$m}\n";
                    }
                }
            } else {
                $ran = $migration->run();
                
                if (empty($ran)) {
                    echo "Nothing to migrate.\n";
                } else {
                    echo "Migrated:\n";
                    foreach ($ran as $m) {
                        echo "  ✓ {$m}\n";
                    }
                }
            }
            break;
            
        case 'rollback':
            $steps = isset($argv[2]) && is_numeric($argv[2]) ? (int) $argv[2] : null;
            
            echo "Rolling back migrations...\n";
            
            if ($options['dry-run']) {
                echo "Would rollback " . ($steps ?? 'all') . " migration(s) from last batch.\n";
            } else {
                $rolledBack = $migration->rollback($steps);
                
                if (empty($rolledBack)) {
                    echo "Nothing to rollback.\n";
                } else {
                    echo "Rolled back:\n";
                    foreach ($rolledBack as $m) {
                        echo "  ✓ {$m}\n";
                    }
                }
            }
            break;
            
        case 'reset':
            echo "Resetting all migrations...\n";
            
            if ($options['dry-run']) {
                echo "Would rollback all migrations.\n";
            } else {
                $rolledBack = $migration->reset();
                
                echo "Rolled back:\n";
                foreach ($rolledBack as $m) {
                    echo "  ✓ {$m}\n";
                }
            }
            break;
            
        case 'refresh':
            echo "Refreshing migrations...\n";
            
            if ($options['dry-run']) {
                echo "Would rollback and re-run all migrations.\n";
            } else {
                $migration->refresh();
                echo "Migrations refreshed.\n";
            }
            break;
            
        case 'status':
            $status = $migration->status();
            
            echo "Migration Status\n";
            echo "----------------\n";
            echo "Total migrations: {$status['total']}\n";
            echo "Ran: " . count($status['ran']) . "\n";
            echo "Pending: " . count($status['pending']) . "\n\n";
            
            if (!empty($status['pending'])) {
                echo "Pending migrations:\n";
                foreach ($status['pending'] as $m) {
                    echo "  - {$m}\n";
                }
            }
            break;
            
        case 'create':
            $name = $argv[2] ?? null;
            
            if ($name === null) {
                echo "Error: Migration name required.\n";
                echo "Usage: php cli/migrate.php create <name>\n";
                exit(1);
            }
            
            $filepath = $migration->create($name);
            echo "Created migration: {$filepath}\n";
            break;
            
        default:
            echo "Unknown command: {$command}\n";
            echo "\nAvailable commands:\n";
            echo "  run       Run pending migrations\n";
            echo "  rollback  Rollback last batch\n";
            echo "  reset     Rollback all migrations\n";
            echo "  refresh   Rollback and re-run all migrations\n";
            echo "  status    Show migration status\n";
            echo "  create    Create a new migration file\n";
            exit(1);
    }
    
    echo "\nDone.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
