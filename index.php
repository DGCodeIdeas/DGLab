<?php

/**
 * DGLab PWA - Entry Point
 *
 * This file is the main entry point for all requests.
 */

// 1. Register PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'DGLab\\';
    $base_dirs = [
        'Core\\' => __DIR__ . '/core/',
        'App\\'  => __DIR__ . '/app/',
    ];

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    foreach ($base_dirs as $sub_prefix => $base_dir) {
        if (strncmp($sub_prefix, $relative_class, strlen($sub_prefix)) === 0) {
            $relative_class_path = substr($relative_class, strlen($sub_prefix));
            $file = $base_dir . str_replace('\\', '/', $relative_class_path) . '.php';

            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

use DGLab\Core\App;
use DGLab\Core\Request;

// 2. Instantiate the Application
$app = App::getInstance(__DIR__);

// 3. Define Routes (usually this would be in config/routes.php)
$app->routes(function ($router) {
    require __DIR__ . '/config/routes.php';
});

// 4. Handle Request
$request = Request::capture();
$response = $app->handle($request);

// 5. Terminate (Send Response)
$app->terminate($response);
