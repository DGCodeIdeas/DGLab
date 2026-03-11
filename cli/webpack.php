<?php
/**
 * DGLab Asset Bundler CLI
 *
 * Usage:
 *   php cli/webpack.php [entry]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Services\AssetPacker\WebpackService;
use DGLab\Core\Application;

echo "DGLab Asset Bundler (Pure PHP Webpack)\n";
echo "========================================\n\n";

$basePath = dirname(__DIR__);

// Bootstrap Application (needed for config and js path)
$app = Application::getInstance($basePath);

$webpack = new WebpackService();

$entries = $argv[1] ?? null;

if (!$entries) {
    // Read from config if no entry provided
    $config = require $basePath . '/config/assets.php';
    $entries = array_keys($config['webpack']['entries'] ?? []);
} else {
    $entries = [$entries];
}

if (empty($entries)) {
    echo "No entries found to process.\n";
    exit(1);
}

foreach ($entries as $entry) {
    echo "Processing entry: {$entry}\n";

    try {
        $result = $webpack->process(['entry' => $entry], function($percent, $message) {
            echo "  [{$percent}%] {$message}...\n";
        });

        if ($result['success']) {
            echo "\n  ✓ Dependency graph resolved successfully!\n";
            echo "  Found {$result['count']} files:\n";
            foreach ($result['dependencies'] as $dep) {
                echo "    - {$dep}\n";
            }
        }
    } catch (Exception $e) {
        echo "\n  ✗ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Done.\n";
