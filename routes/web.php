<?php

use DGLab\Core\Router;
use DGLab\Controllers\Superpowers\ActionController;
use DGLab\Core\Application;

$router = Application::getInstance()->get(Router::class);

$router->post('/_superpowers/action', [ActionController::class, 'handle'], 'superpowers.action');

// Existing routes...
$router->get('/', [\DGLab\Controllers\HomeController::class, 'index'], 'home');
$router->get('/login', [\DGLab\Controllers\AuthController::class, 'showLogin'], 'login');
