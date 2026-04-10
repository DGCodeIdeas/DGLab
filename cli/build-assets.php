<?php
/**
 * DGLab Asset Build Tool - Pure PHP Version
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Services\AssetPacker\AssetPipelineService;

$basePath = dirname(__DIR__);
$app = new Application($basePath);
require_once $basePath . '/app/Helpers/functions.php';

echo "DGLab Asset Builder (Node-Free)\n";
echo "===============================\n\n";

$pipeline = new AssetPipelineService();
$config = config('assets.pipeline');
$entries = array_keys($config['entries'] ?? []);

foreach ($entries as $entry) {
    echo "Building entry: {$entry}\n";
    try {
        $result = $pipeline->process(['entry' => $entry], function($percent, $message) {
            echo "  [{$percent}%] {$message}...\n";
        });
        if ($result['success']) {
            if (isset($result['output'])) {
                echo "  ✓ Built {$result['output']} ({$result['hash']})\n";
            } else {
                echo "  ✓ Processed {$result['count']} files in ESM mode\n";
            }
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Update Service Worker with new hashes
echo "Updating Service Worker cache manifest...\n";
$swPath = $basePath . '/public/sw.js';
if (file_exists($swPath)) {
    $swContent = file_get_contents($swPath);
    $manifestPath = $basePath . '/public/assets/manifest.json';
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        foreach ($manifest as $source => $hashed) {
            $swContent = preg_replace(
                '/([\'"])\/assets\/(css|js)\/' . preg_quote($source, '/') . '([\'"])/',
                "$1/assets/$hashed$3",
                $swContent
            );
        }
        file_put_contents($swPath, $swContent);
        echo "  ✓ sw.js updated with latest hashes.\n";
    }
}
echo "Done.\n";
