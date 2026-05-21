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
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

use DGLab\Core\Application;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Router;
use DGLab\Database\Connection;
use DGLab\Services\ServiceRegistry;
use DGLab\Services\Auth\UUIDService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\AuthManager;
use DGLab\Controllers\AuthController;

// Initialize application
$app = new Application(realpath(__DIR__ . '/..'));

// Register additional services not in registerBaseServices
$app->singleton(\DGLab\Services\Encryption\EncryptionService::class, function ($app) {
    return new \DGLab\Services\Encryption\EncryptionService($app->config('app.security.encryption_key') ?: '12345678901234567890123456789012');
});
$app->singleton(\DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface::class, function ($app) {
    return $app->get(\DGLab\Services\Superpowers\Runtime\GlobalStateStore::class);
});
$app->singleton(\DGLab\Services\Superpowers\Runtime\GlobalStateStore::class, function () {
    return new \DGLab\Services\Superpowers\Runtime\GlobalStateStore();
});
$app->singleton(ServiceRegistry::class, function () {
    return new ServiceRegistry();
});
$app->singleton(\DGLab\Services\AssetService::class, function () {
    return new \DGLab\Services\AssetService();
});

// Register Auth Services
$app->singleton(UUIDService::class, function () { return new UUIDService(); });
$app->singleton(UserRepository::class, function ($app) { return new UserRepository($app->get(UUIDService::class)); });
$app->singleton(AuthManager::class, function ($app) { return new AuthManager($app); });
$app->singleton(AuthController::class, function ($app) { return new AuthController($app->get(UserRepository::class)); });

// Register routes
require_once __DIR__ . '/../routes/web.php';

// Handle request via Application for proper container setup
try {
    $request = Request::createFromGlobals();
    $response = $app->handle($request);
    
    if ($response instanceof Response) {
        $response->send();
    } else {
        echo (string)$response;
    }
} catch (\Throwable $e) {
    if (getenv('APP_DEBUG') === 'true') {
        echo "<h1>Fatal Error</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        http_response_code(500);
        echo "<h1>500 Internal Server Error</h1>";
    }
}
