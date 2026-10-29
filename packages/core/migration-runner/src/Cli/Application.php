<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Cli;

use SovereignStack\Core\Database\Connection;
use SovereignStack\Core\MigrationRunner\Runner\MigrationRunner;

/**
 * CLI entry point for the migration runner.
 *
 * Commands:
 *   install   — Install the runner's metadata schema.
 *   apply     — Apply the next pending migration.
 *   rollback  — Roll back the latest applied migration (MVP: latest only).
 *   status    — Show the current migration state.
 *   dry-run   — Validate all migrations without executing any SQL.
 *   reconcile — Run the reconciliation pass (startup recovery).
 *
 * Exit codes: 0 = success; 1 = failure; 2 = uncertain outcome (operator action required).
 *
 * @package SovereignStack\Core\MigrationRunner\Cli
 */
final class Application
{
    public function run(array $argv): int
    {
        if (count($argv) < 2) {
            $this->printUsage();
            return 1;
        }

        $command = $argv[1];
        $options = $this->parseOptions(array_slice($argv, 2));

        try {
            $config = $this->loadConfig($options);
            $workConn = new Connection($config['dsn'], $config['user'], $config['pass']);
            $gateConn = new Connection($config['dsn'], $config['user'], $config['pass']);

            $runner = new MigrationRunner($config['schema'], $workConn, $gateConn);

            if (isset($options['migrations-dir'])) {
                $runner->discoverMigrations($options['migrations-dir']);
            }

            return match ($command) {
                'install' => $this->cmdInstall($runner),
                'apply' => $this->cmdApply($runner),
                'rollback' => $this->cmdRollback($runner),
                'status' => $this->cmdStatus($runner),
                'dry-run' => $this->cmdDryRun($runner, $options),
                'reconcile' => $this->cmdReconcile($runner),
                default => $this->unknownCommand($command),
            };
        } catch (\Throwable $e) {
            fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
            return 1;
        }
    }

    private function cmdInstall(MigrationRunner $runner): int
    {
        $runner->installSchema();
        echo "Migration runner schema installed.\n";
        return 0;
    }

    private function cmdApply(MigrationRunner $runner): int
    {
        // Always reconcile before applying (recovery-before-action).
        $runner->reconcileAll();
        $result = $runner->applyNext();

        echo $result->message . "\n";
        return $result->succeeded ? 0 : 1;
    }

    private function cmdRollback(MigrationRunner $runner): int
    {
        $runner->reconcileAll();
        $result = $runner->rollbackLatest();

        echo $result->message . "\n";
        return $result->succeeded ? 0 : 1;
    }

    private function cmdStatus(MigrationRunner $runner): int
    {
        $status = $runner->status();
        echo "Schema: {$status['schema']}\n";
        echo "Current level: " . ($status['current_level'] ?? 'none') . "\n";
        echo "\nMigrations:\n";
        foreach ($status['migrations'] as $id => $info) {
            $marker = $info['is_current_level'] ? ' *' : '  ';
            echo "{$marker} [{$info['chain_position']}] {$id}: {$info['state']}\n";
        }
        return 0;
    }

    private function cmdDryRun(MigrationRunner $runner, array $options): int
    {
        if (!isset($options['migrations-dir'])) {
            fwrite(STDERR, "ERROR: --migrations-dir is required for dry-run\n");
            return 1;
        }
        $runner->discoverMigrations($options['migrations-dir']);
        $results = $runner->dryRun();
        foreach ($results as $id => $msg) {
            echo "{$id}: {$msg}\n";
        }
        return 0;
    }

    private function cmdReconcile(MigrationRunner $runner): int
    {
        $results = $runner->reconcileAll();
        $uncertain = false;
        foreach ($results as $id => $result) {
            echo "{$id}: [{$result->outcome}] {$result->message}\n";
            if ($result->outcome === 'uncertain' || $result->outcome === 'quarantined') {
                $uncertain = true;
            }
        }
        // Exit 2 if any migration is uncertain (operator action required).
        return $uncertain ? 2 : 0;
    }

    /**
     * @return array<string, string>
     */
    private function parseOptions(array $args): array
    {
        $opts = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $opts[$parts[0]] = $parts[1] ?? '';
            }
        }
        return $opts;
    }

    /**
     * @return array{dsn: string, user: string, pass: string, schema: string}
     */
    private function loadConfig(array $options): array
    {
        $host = $options['host'] ?? (getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : '127.0.0.1');
        $port = $options['port'] ?? (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : '3306');
        $name = $options['db'] ?? (getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : '');
        $user = $options['user'] ?? (getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : '');
        $pass = $options['pass'] ?? (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '' ? getenv('DB_PASS') : '');

        if ($name === '' || $user === '') {
            throw new \RuntimeException(
                "Database configuration required: use --db, --user, --pass or DB_NAME, DB_USER, DB_PASS environment variables"
            );
        }

        return [
            'dsn' => "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            'user' => $user,
            'pass' => $pass,
            'schema' => $name,
        ];
    }

    private function printUsage(): void
    {
        echo "Usage: migrate <command> [options]\n";
        echo "Commands: install, apply, rollback, status, dry-run, reconcile\n";
        echo "Options: --host, --port, --db, --user, --pass, --migrations-dir\n";
        echo "Environment: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS\n";
    }

    private function unknownCommand(string $command): int
    {
        fwrite(STDERR, "Unknown command: {$command}\n");
        $this->printUsage();
        return 1;
    }
}
