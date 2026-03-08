<?php
/**
 * DGLab Asset Build Tool
 *
 * Pre-compiles and obfuscates assets for production.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Services\AssetService;

// Bootstrap application
$app = Application::getInstance();

echo "DGLab Asset Builder\n";
echo "===================\n\n";

// Ensure Node.js is available for obfuscation
$nodeVersion = shell_exec('node -v');
if (!$nodeVersion) {
    echo "⚠ Warning: Node.js not found. Obfuscation will be skipped and fallback to basic minification.\n";
    $hasNode = false;
} else {
    echo "✓ Node.js found: " . trim($nodeVersion) . "\n";
    $hasNode = true;

    // Check if terser is installed
    $terserPath = __DIR__ . '/../node_modules/.bin/terser';
    if (!file_exists($terserPath)) {
        echo "⚠ Warning: Terser not found. Running 'npm install'...\n";
        exec('npm install terser --save-dev');
        if (!file_exists($terserPath)) {
            echo "✗ Error: Failed to install Terser. Skipping obfuscation.\n";
            $hasNode = false;
        }
    }
}

$assetService = new class extends AssetService {
    public function buildJS(string $sourcePath) {
        $filename = basename($sourcePath);
        $hash = substr(md5_file($sourcePath), 0, 8);
        $cacheFile = Application::getInstance()->getBasePath() . "/storage/cache/assets/" . str_replace('.js', ".{$hash}.js", $filename);

        echo "Building: {$filename} -> " . basename($cacheFile) . "\n";

        $this->compileObfuscated($sourcePath, $cacheFile);
    }

    protected function compileObfuscated(string $sourcePath, string $cacheFile) {
        $terserPath = Application::getInstance()->getBasePath() . '/node_modules/.bin/terser';

        if (file_exists($terserPath)) {
            $mapUrl = basename($cacheFile) . '.map';
            $helperPath = __DIR__ . '/obfuscate.js';
            $command = sprintf(
                'node %s %s %s 2>&1',
                escapeshellarg($helperPath),
                escapeshellarg($sourcePath),
                escapeshellarg($cacheFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                echo "  ✓ Obfuscated with Terser\n";
                return;
            } else {
                echo "  ✗ Terser failed: " . implode("\n", $output) . "\n";
            }
        }

        // Fallback
        $this->compile($sourcePath, $cacheFile, 'js');
        echo "  ✓ Minified (Fallback)\n";
    }
};

$jsFiles = glob(__DIR__ . '/../resources/js/*.js');
foreach ($jsFiles as $file) {
    $assetService->buildJS($file);
}

// Handle vendor JS if any
if (is_dir(__DIR__ . '/../resources/js/vendor')) {
    $vendorFiles = glob(__DIR__ . '/../resources/js/vendor/*.js');
    foreach ($vendorFiles as $file) {
        $assetService->buildJS($file);
    }
}

echo "\nDone.\n";
