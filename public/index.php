<?php
/**
 * DGLab PWA - Front Controller
 * 
 * Entry point for all HTTP requests.
 */

// Report all errors in development
if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Start session
session_start();

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            $_SERVER[trim($key)] = trim($value);
        }
    }
}

use DGLab\Core\Application;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Router;
use DGLab\Database\Connection;
use DGLab\Services\ServiceRegistry;

// Initialize application
$app = Application::getInstance();

// Register core services
$app->singleton(Connection::class, function () {
    return new Connection(require __DIR__ . '/../config/database.php');
});

$app->singleton(Router::class, function () {
    return new Router();
});

$app->singleton(ServiceRegistry::class, function () {
    return new ServiceRegistry();
});

$app->singleton(\DGLab\Core\View::class, function () {
    return new \DGLab\Core\View();
});

// Get router
$router = $app->get(Router::class);

// Register routes
require_once __DIR__ . '/../routes/web.php';

// Handle request
try {
    $request = Request::createFromGlobals();
    $response = $router->dispatch($request);
    
    if ($response instanceof Response) {
        $response->send();
    } else {
        echo $response;
    }
} catch (\DGLab\Core\RouteNotFoundException $e) {
    http_response_code(404);
    if ($request->expectsJson()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found', 'message' => $e->getMessage()]);
    } else {
        echo '<h1>404 Not Found</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
} catch (\Exception $e) {
    http_response_code(500);
    if ($request->expectsJson()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
    } else {
        echo '<h1>500 Internal Server Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
