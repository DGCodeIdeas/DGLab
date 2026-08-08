<?php

declare(strict_types=1);

namespace Anvil\Web;

use Anvil\Web\Api\Router;

// The PHP built-in server can serve static files directly if the router script
// returns false for existing files, so we short-circuit here before loading the
// application router.
if (php_sapi_name() === 'cli-server') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    $file = __DIR__ . $path;

    // Never treat the front controller itself as a static asset.
    if ($path !== '/' && basename($path) !== 'index.php' && is_file($file)) {
        return false;
    }
}

// Front controller / router for the Anvil Web UI skin.
// Serves the SPA shell at "/" and dispatches "/api/*" (or ?route=api/*) to the
// Api handlers.
require_once __DIR__ . '/../src/Api/Router.php';

$router = new Router();
$router->handle();
