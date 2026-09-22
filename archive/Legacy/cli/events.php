<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\EventAuditService;
use DGLab\Database\Connection;
use DGLab\Core\EventDrivers\SyncDriver;
use DGLab\Core\EventDrivers\QueueDriver;

// Bootstrap application
$app = Application::getInstance();

// Explicitly register drivers and connection to avoid autowire issues with required constructor params in CLI
$app->singleton(Connection::class, function() use ($app) {
    $dbConfig = require $app->getBasePath() . '/config/database.php';
    return new Connection($dbConfig);
});

$app->singleton(SyncDriver::class, function($app) {
    return new SyncDriver($app);
});

$app->singleton(QueueDriver::class, function($app) {
    return new QueueDriver($app);
});

if (!$app->has(DispatcherInterface::class)) {
    $app->singleton(DispatcherInterface::class, function($app) {
        return new EventDispatcher($app);
    });
}

$dispatcher = $app->get(DispatcherInterface::class);
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'list':
        listEvents($dispatcher);
        break;
    case 'worker':
        runWorker($app);
        break;
    default:
        showHelp();
        break;
}

function listEvents(EventDispatcher $dispatcher) {
    echo "Registered Events and Listeners:\n";
    echo str_repeat("=", 50) . "\n";

    $reflection = new ReflectionClass($dispatcher);
    $listenersProperty = $reflection->getProperty('listeners');
    $listenersProperty->setAccessible(true);
    $allListeners = $listenersProperty->getValue($dispatcher);

    if (empty($allListeners)) {
        echo "No listeners registered.\n";
        return;
    }

    foreach ($allListeners as $event => $priorities) {
        echo "Event: $event\n";
        krsort($priorities);
        foreach ($priorities as $priority => $listeners) {
            foreach ($listeners as $data) {
                $type = $data['async'] ? '[ASYNC]' : '[SYNC]';
                $listener = $data['listener'];
                $listenerName = is_string($listener) ? $listener : (is_array($listener) ? get_class($listener[0]) . '@' . $listener[1] : 'Closure');
                echo "  - $type (Priority: $priority) $listenerName\n";
            }
        }
        echo "\n";
    }
}

function runWorker(Application $app) {
    echo "Starting Event Worker...\n";
    $db = $app->get(Connection::class);
    $auditService = $app->has(EventAuditService::class) ? $app->get(EventAuditService::class) : null;

    // Stub for future process control (SIGTERM, etc.)
    $running = true;

    while ($running) {
        try {
            $job = $db->selectOne(
                "SELECT * FROM event_queue WHERE status = 'pending' AND available_at <= ? ORDER BY id ASC LIMIT 1",
                [date('Y-m-d H:i:s')]
            );
        } catch (\Exception $e) {
            echo "Database error: " . $e->getMessage() . "\n";
            sleep(2);
            continue;
        }

        if ($job) {
            echo "Processing Job ID: {$job['id']} ({$job['event_alias']})...\n";

            // Mark as processing
            $db->update("UPDATE event_queue SET status = 'processing' WHERE id = ?", [$job['id']]);

            $payload = json_decode($job['payload'], true);
            $event = unserialize($payload['event']);
            $listener = $payload['listener'];
            $auditId = $payload['audit_id'];

            $start = microtime(true);
            try {
                executeListener($app, $listener, $event);

                $db->update("UPDATE event_queue SET status = 'completed', completed_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $job['id']]);

                if ($auditService && $auditId) {
                    $latency = (int) ((microtime(true) - $start) * 1000);
                    $auditService->logExecution($auditId, $listener, 'worker', 'success', $latency);
                }
                echo "Successfully processed Job ID: {$job['id']}\n";
            } catch (\Throwable $e) {
                $db->update("UPDATE event_queue SET status = 'failed', error = ? WHERE id = ?", [$e->getMessage(), $job['id']]);

                if ($auditService && $auditId) {
                    $latency = (int) ((microtime(true) - $start) * 1000);
                    $auditService->logExecution($auditId, $listener, 'worker', 'failed', $latency, $e->getMessage());
                }
                echo "Failed processing Job ID: {$job['id']}: " . $e->getMessage() . "\n";
            }
        } else {
            // Sleep for a bit if no jobs
            usleep(500000); // 0.5 seconds
        }
    }
}

function executeListener(Application $app, $listener, $event) {
    if (is_string($listener) && str_contains($listener, '@')) {
        [$class, $method] = explode('@', $listener);
        $instance = $app->get($class);
        $app->call([$instance, $method], ['event' => $event]);
    } else {
        // Class name (assume __invoke) or function name
        $instance = is_string($listener) ? $app->get($listener) : $listener;
        $app->call($instance, ['event' => $event]);
    }
}

function showHelp() {
    echo "Usage: php cli/events.php [command]\n\n";
    echo "Commands:\n";
    echo "  list    List all registered events and listeners\n";
    echo "  worker  Start the background event worker\n";
}
