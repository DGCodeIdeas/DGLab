#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Services\Nexus\NexusServer;
use DGLab\Services\Nexus\SwooleConnectionManager;
use DGLab\Services\Nexus\HandshakeValidator;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\Repositories\UserRepository;
use Psr\Log\LoggerInterface;

// Bootstrap application
$app = new Application(dirname(__DIR__));
$app->boot();

// Check for Swoole extension
if (!extension_loaded('swoole')) {
    echo "Error: Swoole extension is required to run Nexus.\n";
    exit(1);
}

// CLI Configuration
$action = $argv[1] ?? 'start';
$host = getenv('NEXUS_HOST') ?: '0.0.0.0';
$port = (int)(getenv('NEXUS_PORT') ?: 8080);
$pidFile = dirname(__DIR__) . '/storage/nexus.pid';

switch ($action) {
    case 'start':
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (posix_getpgid($pid)) {
                echo "Nexus is already running (PID: $pid)\n";
                exit(1);
            }
            unlink($pidFile);
        }

        echo "Starting Nexus WebSocket Server on $host:$port...\n";

        // Manually assemble dependencies for Phase One
        $connectionManager = new SwooleConnectionManager();
        $jwtService = new JWTService();
        $userRepository = new UserRepository();

        $validator = new HandshakeValidator(
            $jwtService,
            $userRepository,
            config('auth.jwt.secret'),
            config('auth.jwt.algo', 'HS256')
        );

        $server = new NexusServer(
            $connectionManager,
            $validator,
            $app->get(LoggerInterface::class),
            $host,
            $port
        );

        // Store PID in a way Swoole can manage if needed, or manually here
        file_put_contents($pidFile, getmypid());

        try {
            $server->start();
        } finally {
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        }
        break;

    case 'stop':
        if (!file_exists($pidFile)) {
            echo "Nexus is not running.\n";
            exit(1);
        }

        $pid = (int)file_get_contents($pidFile);
        if (posix_kill($pid, SIGTERM)) {
            echo "Sent SIGTERM to Nexus (PID: $pid)\n";
            // Wait for it to stop
            for ($i = 0; $i < 5; $i++) {
                if (!posix_getpgid($pid)) {
                    echo "Nexus stopped.\n";
                    @unlink($pidFile);
                    exit(0);
                }
                sleep(1);
            }
            echo "Nexus did not stop gracefully. Consider using kill -9 $pid\n";
        } else {
            echo "Failed to stop Nexus.\n";
            @unlink($pidFile);
        }
        break;

    case 'status':
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (posix_getpgid($pid)) {
                echo "Nexus is running (PID: $pid)\n";
                exit(0);
            }
            echo "Nexus PID file exists but process is dead.\n";
            exit(1);
        }
        echo "Nexus is stopped.\n";
        break;

    default:
        echo "Usage: php cli/nexus.php {start|stop|status}\n";
        echo "Environment Variables:\n";
        echo "  NEXUS_HOST    Default: 0.0.0.0\n";
        echo "  NEXUS_PORT    Default: 8080\n";
        exit(1);
}
