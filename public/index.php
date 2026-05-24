<?php
/**
 * DGLab PWA - Front Controller (Sovereign Edition)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Core\Http\Request;

try {
    // Initialize application
    $app = new Application(realpath(__DIR__ . '/..'));
$app->addMiddleware(\DGLab\Middleware\TestMiddleware::class);

    // Register routes
    require_once __DIR__ . '/../routes/web.php';

    // Create Sovereign Request
    $request = Request::fromGlobals();

    // Handle request
    $response = $app->handle($request);

    // Send response
    $response->send();
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Sovereign Core Error</h1>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
