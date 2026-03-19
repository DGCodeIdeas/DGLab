<?php
/**
 * DGLab Asset Build Tool - Pure PHP Version
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Services\AssetPacker\WebpackService;

$basePath = dirname(__DIR__);
$app = new Application($basePath);
require_once $basePath . '/app/Helpers/functions.php';

echo "DGLab Asset Builder (Node-Free)\n";
echo "===============================\n\n";

$webpack = new WebpackService();
$config = require $basePath . '/config/assets.php';
$entries = array_keys($config['webpack']['entries'] ?? []);

foreach ($entries as $entry) {
    echo "Building entry: {$entry}\n";
    try {
        $result = $webpack->process(['entry' => $entry], function($percent, $message) {
            echo "  [{$percent}%] {$message}...\n";
        });
        if ($result['success']) {
            echo "  ✓ Built {$result['output']} ({$result['hash']})\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Done.\n";