<?php

use DGLab\Core\Router;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Application;

/** @var Router $router */

// Home Route
$router->get('/', function (Request $request) {
    $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
    $services = $registry->all();

    return view('home', ['services' => $services]);
})->name('home');

// API Routes
$router->group(['prefix' => 'api'], function (Router $router) {

    // Service discovery
    $router->get('/services', function (Request $request) {
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        return Response::json($registry->all());
    });

    // Service detail
    $router->get('/services/{id}', function (Request $request) {
        $id = $request->route('id');
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        $service = $registry->get($id);

        if ($service === null) {
            return Response::json(['error' => 'Service not found'], 404);
        }

        return Response::json($service->getMetadata());
    });

    // Process service request
    $router->post('/services/{id}/process', function (Request $request) {
        $id = $request->route('id');
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        $service = $registry->get($id);

        if ($service === null) {
            return Response::json(['error' => 'Service not found'], 404);
        }

        try {
            $input = $service->validate($request->all());
            $result = $service->process($input);
            return Response::json($result);
        } catch (\DGLab\Core\Exceptions\ValidationException $e) {
            return Response::json(['error' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    });

    // Chunked upload endpoints
    $router->group(['prefix' => 'chunk'], function (Router $router) {

        // Initialize chunked upload
        $router->post('/init', function (Request $request) {
            $serviceId = $request->post('service_id');
            $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
            $service = $registry->get($serviceId);

            if ($service === null || !$service instanceof \DGLab\Services\Contracts\ChunkedServiceInterface) {
                return Response::json(['error' => 'Service does not support chunked upload'], 400);
            }

            $result = $service->initializeChunkedProcess([
                'filename' => $request->post('filename'),
                'file_size' => (int) $request->post('file_size'),
                'font' => $request->post('font'),
                'target_elements' => $request->post('target_elements'),
            ]);

            return Response::json($result);
        });

        // Upload chunk
        $router->post('/upload', function (Request $request) {
            $sessionId = $request->post('session_id');
            $chunkIndex = (int) $request->post('chunk_index');

            // Get chunk data from file upload
            $chunkFile = $request->file('chunk_data');

            if ($chunkFile === null || !$chunkFile->isValid()) {
                // Try base64 encoded data
                $chunkData = base64_decode($request->post('chunk_data') ?? '', true);
                if ($chunkData === false) {
                    return Response::json(['error' => 'Invalid chunk data'], 400);
                }
            } else {
                $chunkData = file_get_contents($chunkFile->getPathname());
            }

            // Find service from session
            $session = \DGLab\Database\UploadChunk::findBySessionId($sessionId);

            if ($session === null) {
                return Response::json(['error' => 'Session not found'], 404);
            }

            $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
            $service = $registry->get($session->service_id);

            if ($service === null) {
                return Response::json(['error' => 'Service not found'], 404);
            }

            $result = $service->processChunk($sessionId, $chunkIndex, $chunkData);

            return Response::json($result);
        });

        // Finalize chunked upload
        $router->post('/finalize', function (Request $request) {
            $sessionId = $request->post('session_id');

            $session = \DGLab\Database\UploadChunk::findBySessionId($sessionId);

            if ($session === null) {
                return Response::json(['error' => 'Session not found'], 404);
            }

            $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
            $service = $registry->get($session->service_id);

            if ($service === null) {
                return Response::json(['error' => 'Service not found'], 404);
            }

            try {
                $result = $service->finalizeChunkedProcess($sessionId);

                return Response::json($result);
            } catch (\Exception $e) {
                return Response::json(['error' => $e->getMessage()], 500);
            }
        });

        // Get chunk status
        $router->get('/status/{session_id}', function (Request $request) {
            $sessionId = $request->route('session_id');

            $session = \DGLab\Database\UploadChunk::findBySessionId($sessionId);

            if ($session === null) {
                return Response::json(['error' => 'Session not found'], 404);
            }

            $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
            $service = $registry->get($session->service_id);

            if ($service === null) {
                return Response::json(['error' => 'Service not found'], 404);
            }

            $result = $service->getChunkedStatus($sessionId);

            return Response::json($result);
        });

        // Cancel chunked upload
        $router->delete('/cancel/{session_id}', function (Request $request) {
            $sessionId = $request->route('session_id');

            $session = \DGLab\Database\UploadChunk::findBySessionId($sessionId);

            if ($session === null) {
                return Response::json(['error' => 'Session not found'], 404);
            }

            $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
            $service = $registry->get($session->service_id);

            if ($service === null) {
                return Response::json(['error' => 'Service not found'], 404);
            }

            $service->cancelChunkedProcess($sessionId);

            return Response::json(['cancelled' => true]);
        });
    });

    // Legacy Download endpoint (now handled by DownloadController)
    $router->get('/download/{filename}', [\DGLab\Controllers\DownloadController::class, 'legacyDownload'])->name('download');
});

// PWA Manifest
$router->get('/manifest.json', function (Request $request) {
    $config = Application::getInstance()->config('app.pwa');

    $manifest = [
        'name' => $config['name'],
        'short_name' => $config['short_name'],
        'description' => 'Digital Lab Tools - A collection of web-based utilities for file processing',
        'start_url' => $config['start_url'],
        'display' => $config['display'],
        'background_color' => $config['background_color'],
        'theme_color' => $config['theme_color'],
        'orientation' => $config['orientation'],
        'scope' => $config['scope'],
        'icons' => $config['icons'],
        'lang' => 'en',
        'dir' => 'ltr',
        'categories' => ['productivity', 'utilities'],
    ];

    return Response::json($manifest);
});

// Service Worker
$router->get('/sw.js', function (Request $request) {
    $swPath = __DIR__ . '/../public/sw.js';

    if (!file_exists($swPath)) {
        return new Response('// Service Worker not built yet', 200, [
            'Content-Type' => 'application/javascript',
        ]);
    }

    return new Response(file_get_contents($swPath), 200, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache',
    ]);
});

// Health check
$router->get('/health', function (Request $request) {
    $app = Application::getInstance();
    $basePath = $app->getBasePath();

    // 1. Database Check
    $dbStatus = 'unknown';
    try {
        $db = $app->get(\DGLab\Database\Connection::class);
        $dbStatus = $db->ping() ? 'connected' : 'disconnected';
    } catch (\Exception $e) {
        $dbStatus = 'error: ' . $e->getMessage();
    }

    // 2. Disk Availability (100MB threshold)
    $freeSpace = disk_free_space($basePath);
    $diskOk = $freeSpace > 100 * 1024 * 1024; // 100MB

    // 3. Memory Usage
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');

    // 4. Environment Variables Presence
    $requiredEnv = ['APP_NAME', 'APP_ENV', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    $missingEnv = [];
    foreach ($requiredEnv as $env) {
        if (!isset($_ENV[$env]) || $_ENV[$env] === '') {
            $missingEnv[] = $env;
        }
    }

    // 5. Filesystem Writability
    $writablePaths = [
        'storage/' => is_writable($basePath . '/storage'),
        'public/assets/' => is_writable($basePath . '/public/assets'),
        'storage/cache/assets/' => is_writable($basePath . '/storage/cache/assets'),
    ];

    // 6. System Info
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
    $uptime = 'unknown';
    if (PHP_OS_FAMILY === 'Linux') {
        $uptimeStr = @file_get_contents('/proc/uptime');
        if ($uptimeStr) {
            $uptime = (int)explode(' ', $uptimeStr)[0];
        }
    }

    $status = ($dbStatus === 'connected' && $diskOk && empty($missingEnv)) ? 'ok' : 'warning';

    return Response::json([
        'status' => $status,
        'timestamp' => date('c'),
        'version' => $app->config('app.version'),
        'database' => [
            'status' => $dbStatus,
        ],
        'disk' => [
            'free_space' => $freeSpace,
            'free_space_human' => round($freeSpace / (1024 * 1024), 2) . ' MB',
            'ok' => $diskOk,
        ],
        'memory' => [
            'usage' => $memoryUsage,
            'usage_human' => round($memoryUsage / (1024 * 1024), 2) . ' MB',
            'limit' => $memoryLimit,
        ],
        'environment' => [
            'env' => $app->config('app.env'),
            'debug' => $app->config('app.debug'),
            'missing_variables' => $missingEnv,
        ],
        'filesystem' => [
            'writable' => $writablePaths,
        ],
        'system' => [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'load_average' => $load,
            'uptime_seconds' => $uptime,
        ],
    ]);
});

// Assets Route
$router->get('/assets/{type:css|js}/{file}', function (Request $request) {
    $type = $request->route('type');
    $file = $request->route('file');
    $assetService = Application::getInstance()->get(\DGLab\Services\AssetService::class);
    $assetService->serveAsset($type, $file);
});

// Webfonts Route
$router->get('/assets/webfonts/{file}', function (Request $request) {
    $file = $request->route('file');
    $assetService = Application::getInstance()->get(\DGLab\Services\AssetService::class);
    $assetService->serveAsset('webfonts', $file);
});

// Download Routes (Phase 2 & 5)
$router->get('/s/{signature}', [\DGLab\Controllers\DownloadController::class, 'signedDownload']);
$router->get('/dl/{token}', [\DGLab\Controllers\DownloadController::class, 'tokenDownload']);
