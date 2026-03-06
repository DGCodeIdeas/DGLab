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

$app->singleton(\DGLab\Services\AssetService::class, function () {
    return new \DGLab\Services\AssetService();
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
} catch (\DGLab\Core\Exceptions\RouteNotFoundException $e) {
    // Fallback: Check if it's a physical file in the public directory
    $path = $request->getPath();
    $publicPath = realpath(__DIR__);
    $filePath = realpath($publicPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));

    if ($filePath && strpos($filePath, $publicPath . DIRECTORY_SEPARATOR) === 0 && is_file($filePath)) {
        // Security: Do not serve .php files
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            throw $e;
        }

        // Improved MIME type detection
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'otf'  => 'font/otf',
        ];

        $mimeType = $mimeTypes[$extension] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

        // Caching headers
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);

        header("Content-Type: {$mimeType}");
        header("Content-Length: " . filesize($filePath));
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
        header("ETag: \"{$etag}\"");
        header("Cache-Control: public, max-age=31536000");

        // Check If-None-Match or If-Modified-Since
        if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) ||
            (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified)) {
            http_response_code(304);
            exit;
        }

        readfile($filePath);
        exit;
    }

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
