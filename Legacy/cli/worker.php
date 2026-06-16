<?php
/**
 * DGLab Event Worker
 *
 * Processes asynchronous events from the database queue with retry logic.
 *
 * Usage:
 *   php cli/worker.php [--once] [--sleep=3]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\EventAuditService;
use DGLab\Database\Connection;

// Initialize Application
$app = Application::getInstance();

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

// Load Event config
$eventConfig = $app->config('events');
$retryLimit = $eventConfig['queue']['retry_limit'] ?? 3;
$retryDelay = $eventConfig['queue']['retry_delay'] ?? 60;

echo "DGLab Event Worker started...\n";

while (true) {
    $job = $db->selectOne(
        "SELECT * FROM event_queue WHERE status IN ('pending', 'retrying') AND (available_at IS NULL OR available_at <= ?) ORDER BY id LIMIT 1",
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

    $start = microtime(true);
    $payload = json_decode($job['payload'], true);
    $auditId = $payload['audit_id'] ?? null;
    $auditService = $app->get(EventAuditService::class);

    try {
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

        if ($auditId) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            $auditService->logExecution($auditId, $payload['listener'], 'worker', 'success', $latency);
        }

        echo "  ✓ Completed.\n";

    } catch (\Throwable $e) {
        $attempts = $job['attempts'] + 1;
        $latency = (int) ((microtime(true) - $start) * 1000);

        if ($attempts < $retryLimit) {
            // Schedule retry with exponential backoff
            $backoff = $retryDelay * pow(2, $attempts - 1);
            $availableAt = date('Y-m-d H:i:s', time() + $backoff);

            $db->update(
                "UPDATE event_queue SET status = 'retrying', attempts = ?, available_at = ?, error = ?, updated_at = ? WHERE id = ?",
                [$attempts, $availableAt, $e->getMessage(), date('Y-m-d H:i:s'), $job['id']]
            );

            if ($auditId) {
                $auditService->logExecution($auditId, $payload['listener'], 'worker', 'retrying', $latency, $e->getMessage(), $e->getTraceAsString());
            }

            echo "  ✗ Failed (Attempt $attempts). Scheduled retry at $availableAt.\n";
        } else {
            // Move to Dead Letter (failed)
            $db->update(
                "UPDATE event_queue SET status = 'failed', attempts = ?, error = ?, updated_at = ? WHERE id = ?",
                [$attempts, $e->getMessage(), date('Y-m-d H:i:s'), $job['id']]
            );

            if ($auditId) {
                $auditService->logExecution($auditId, $payload['listener'], 'worker', 'dead_letter', $latency, $e->getMessage(), $e->getTraceAsString());
            }

            echo "  ✗ Failed (Final Attempt). Moved to dead letter.\n";
        }
    }

    if ($once) break;
}
