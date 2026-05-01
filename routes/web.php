<?php

use DGLab\Core\Router;
use DGLab\Controllers\Superpowers\ActionController;
use DGLab\Core\Application;
use DGLab\Controllers\HomeController;
use DGLab\Controllers\AuthController;
use DGLab\Controllers\ServicesController;

$router = Application::getInstance()->get(Router::class);

$router->post('/_superpowers/action', [ActionController::class, 'handle'], 'superpowers.action');

// Existing routes...
$router->get('/', [HomeController::class, 'index'], 'home');
$router->get('/login', [AuthController::class, 'showLogin'], 'login');
$router->get('/test/morph', [DGLabControllersTestController::class, 'morph']);

$router->get('/services', [ServicesController::class, 'index'], 'services.index');
$router->get('/services/{id}', [ServicesController::class, 'show'], 'services.show');

// Health check endpoint for CI/CD
$router->get('/health', function() {
    $reportFile = dirname(__DIR__) . '/storage/reports/health.json';
    $factory = \DGLab\Core\Application::getInstance()->get(\DGLab\Core\ResponseFactory::class);

    if (file_exists($reportFile)) {
        $data = json_decode(file_get_contents($reportFile), true);
        return $factory->json($data);
    }

    return $factory->json(['status' => 'unknown', 'message' => 'Health report not found'], 404);
});
