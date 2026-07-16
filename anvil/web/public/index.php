<?php

declare(strict_types=1);

namespace Anvil\Web;

use Anvil\Web\Api\Router;

// Front controller / router for the Anvil Web UI skin.
// Serves the SPA shell at "/" and dispatches "/api/*" (or ?route=api/*) to the
// Api handlers. Static assets (app.js, assets/style.css) are served directly
// by the PHP built-in server and never reach this script.

require_once __DIR__ . '/../src/Api/Router.php';

$router = new Router();
$router->handle();
