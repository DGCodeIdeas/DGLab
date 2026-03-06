<?php
/**
 * DGLab PWA - Web Routes
 * 
 * Application route definitions.
 */

use DGLab\Core\Application;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Router;

/** @var Router $router */
$router = Application::getInstance()->get(Router::class);

// Home page
$router->get('/', function (Request $request) {
    $view = Application::getInstance()->get(\DGLab\Core\View::class);
    
    return new Response($view->render('home', [
        'title' => 'DGLab - Digital Lab Tools',
    ]));
});

// Service listing page
$router->get('/services', function (Request $request) {
    $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
    $view = Application::getInstance()->get(\DGLab\Core\View::class);
    
    return new Response($view->render('services/index', [
        'title' => 'Services - DGLab',
        'services' => $registry->all(),
    ]));
});

// Individual service page
$router->get('/services/{id}', function (Request $request) {
    $serviceId = $request->route('id');
    $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
    $service = $registry->get($serviceId);
    
    if ($service === null) {
        return Response::json(['error' => 'Service not found'], 404);
    }
    
    $view = Application::getInstance()->get(\DGLab\Core\View::class);
    
    return new Response($view->render('services/service', [
        'title' => $service->getName() . ' - DGLab',
        'service' => $service,
    ]));
});

// API Routes
$router->group(['prefix' => 'api'], function (Router $router) {
    
    // Service discovery
    $router->get('/services', function (Request $request) {
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        
        return Response::json([
            'services' => $registry->all(),
        ]);
    });
    
    // Get service details
    $router->get('/services/{id}', function (Request $request) {
        $serviceId = $request->route('id');
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        $service = $registry->get($serviceId);
        
        if ($service === null) {
            return Response::json(['error' => 'Service not found'], 404);
        }
        
        return Response::json([
            'id' => $service->getId(),
            'name' => $service->getName(),
            'description' => $service->getDescription(),
            'icon' => $service->getIcon(),
            'supports_chunking' => $service->supportsChunking(),
            'input_schema' => $service->getInputSchema(),
            'config' => $service->getConfig(),
            'metadata' => $service->getMetadata(),
        ]);
    });
    
    // Process service (direct)
    $router->post('/services/{id}/process', function (Request $request) {
        $serviceId = $request->route('id');
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        $service = $registry->get($serviceId);
        
        if ($service === null) {
            return Response::json(['error' => 'Service not found'], 404);
        }
        
        try {
            $input = $request->all();
            
            // Handle file upload
            $file = $request->file('file');
            if ($file !== null && $file->isValid()) {
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $file->getClientOriginalName();
                $file->move(dirname($tempPath), basename($tempPath));
                $input['file'] = $tempPath;
            }
            
            $result = $service->process($input, function ($percent, $message) {
                // Progress callback - could be used for WebSocket updates
            });
            
            return Response::json($result);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    });
    
    // Validate service input
    $router->post('/services/{id}/validate', function (Request $request) {
        $serviceId = $request->route('id');
        $registry = Application::getInstance()->get(\DGLab\Services\ServiceRegistry::class);
        $service = $registry->get($serviceId);
        
        if ($service === null) {
            return Response::json(['error' => 'Service not found'], 404);
        }
        
        try {
            $service->validate($request->json() ?? $request->all());
            
            return Response::json(['valid' => true]);
        } catch (\DGLab\Core\Exceptions\ValidationException $e) {
            return Response::json(['valid' => false, 'errors' => $e->getErrors()], 422);
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
    
    // Download endpoint
    $router->get('/download/{filename}', function (Request $request) {
        $filename = basename($request->route('filename'));
        $filePath = Application::getInstance()->getBasePath() . '/storage/uploads/temp/' . $filename;
        
        if (!file_exists($filePath)) {
            return Response::json(['error' => 'File not found'], 404);
        }
        
        return Response::download($filePath);
    })->name('download');
});

// PWA Manifest
$router->get('/manifest.json', function (Request $request) {
    $config = Application::getInstance()->config('app.pwa');
    
    $manifest = [
        'name' => $config['name'],
        'short_name' => $config['short_name'],
        'start_url' => $config['start_url'],
        'display' => $config['display'],
        'background_color' => $config['background_color'],
        'theme_color' => $config['theme_color'],
        'orientation' => $config['orientation'],
        'scope' => $config['scope'],
        'icons' => $config['icons'],
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
    return Response::json([
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => Application::getInstance()->config('app.version'),
    ]);
});

// Assets Route
$router->get('/assets/{type}/{file}', function (Request $request) {
    $type = $request->route('type');
    $file = $request->route('file');
    $assetService = Application::getInstance()->get(\DGLab\Services\AssetService::class);
    $assetService->serveAsset($type, $file);
});
