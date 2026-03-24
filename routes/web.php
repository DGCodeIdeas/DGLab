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

$router->get('/services', [ServicesController::class, 'index'], 'services.index');
$router->get('/services/{id}', [ServicesController::class, 'show'], 'services.show');
