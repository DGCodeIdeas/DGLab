<?php
require_once 'vendor/autoload.php';
use DGLab\Core\Application;
use DGLab\Core\View;

$app = new Application(__DIR__);
$app->setConfig('superpowers.cache_path', __DIR__ . '/storage/cache/views');

$view = $app->get(View::class);
try {
    echo "Rendering auth.login...\n";
    $output = $view->render('auth.login');
    echo "Output length: " . strlen($output) . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
