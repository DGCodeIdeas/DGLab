<?php
/**
 * DGLab Event Worker
 *
 * Processes asynchronous events from the database queue.
 *
 * Usage:
 *   php cli/worker.php [--once] [--sleep=3]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Database\Connection;

// Initialize Application
$app = Application::getInstance();

// Load environment (consistent with migrate.php)
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

// Re-initialize DB with correct env
$config = require __DIR__ . '/../config/database.php';
$db = new Connection($config);
$app->singleton(Connection::class, $db);

$once = in_array('--once', $argv);
$sleep = 3;
foreach ($argv as $arg) {
    if (strpos($arg, '--sleep=') === 0) {
        $sleep = (int) substr($arg, 8);
    }
}

echo "DGLab Event Worker started...\n";

while (true) {
    $job = $db->selectOne(
        "SELECT * FROM event_queue WHERE status = 'pending' AND (available_at IS NULL OR available_at <= ?) ORDER BY id LIMIT 1",
        [date('Y-m-d H:i:s')]
    );

    if (!$job) {
        if ($once) break;
        sleep($sleep);
        continue;
    }

    // Mark as processing
    $db->update("UPDATE event_queue SET status = 'processing', updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $job['id']]);

    echo "Processing event: {$job['event_alias']} (ID: {$job['id']})...\n";

    try {
        $payload = json_decode($job['payload'], true);
        $event = unserialize($payload['event']);
        $listenerStr = $payload['listener'];

        if (strpos($listenerStr, '@') !== false) {
            list($class, $method) = explode('@', $listenerStr);
            $instance = $app->get($class);
            $app->call([$instance, $method], ['event' => $event]);
        } else {
            $instance = $app->get($listenerStr);
            $app->call([$instance, 'handle'], ['event' => $event]);
        }

        $db->update(
            "UPDATE event_queue SET status = 'completed', updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $job['id']]
        );
        echo "  ✓ Completed.\n";

    } catch (\Throwable $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        $db->update(
            "UPDATE event_queue SET status = 'failed', error = ?, attempts = attempts + 1, updated_at = ? WHERE id = ?",
            [$e->getMessage(), date('Y-m-d H:i:s'), $job['id']]
        );
    }

    if ($once) break;
}
