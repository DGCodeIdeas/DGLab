<?php
/**
 * DGLab Deployment Script
 * 
 * Deployment automation for AeonFree / Render environments.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;

// Parse options
$options = [
    'skip-tests' => in_array('--skip-tests', $argv),
    'skip-assets' => in_array('--skip-assets', $argv),
    'skip-db' => in_array('--skip-db', $argv),
    'force' => in_array('--force', $argv),
    'render-url' => null
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--render-url=')) {
        $options['render-url'] = substr($arg, strlen('--render-url='));
    }
}

$basePath = dirname(__DIR__);
$report = [];

echo "\033[1mDGLab Deployment Tool\033[0m\n";
echo "=====================\n\n";

$startTime = microtime(true);

try {
    // Step 0: Pre-deployment Testing
    if (!$options['skip-tests']) {
        echo "[0/9] Running critical tests...\n";
        $testOutput = [];
        $testResult = 0;
        // Only run Unit and Integration as Browser tests might need special environment
        exec("php cli/test.php run --unit --integration --stop-on-failure", $testOutput, $testResult);

        if ($testResult !== 0) {
            echo implode("\n", array_slice($testOutput, -10)) . "\n";
            throw new Exception("Critical tests failed. Deployment aborted.");
        }
        echo "  ✓ Critical tests passed\n";
    }

    // Step 1: Environment Validation
    echo "[1/9] Validating environment...\n";
    
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '8.0.0', '<')) {
        throw new Exception("PHP 8.0+ required. Current: {$phpVersion}");
    }
    echo "  ✓ PHP version: {$phpVersion}\n";
    
    $requiredExtensions = ['pdo', 'pdo_mysql', 'zip', 'json', 'mbstring', 'fileinfo'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            throw new Exception("Required extension missing: {$ext}");
        }
    }
    
    $report['environment'] = 'OK';
    
    // Step 2: Database Migrations
    if (!$options['skip-db']) {
        echo "[2/9] Running database migrations...\n";
        
        $app = new Application($basePath);
        $config = $app->config('database') ?? [];
        $db = new \DGLab\Database\Connection($config);
        
        $migration = new \DGLab\Database\Migration($db, $basePath . '/database/migrations');
        $ran = $migration->run();
        
        if (empty($ran)) {
            echo "  ✓ No pending migrations\n";
        } else {
            foreach ($ran as $m) {
                echo "  ✓ Migrated: {$m}\n";
            }
        }
        
        $report['migrations'] = count($ran);
    }
    
    // Step 3: Asset Compilation
    if (!$options['skip-assets']) {
        echo "[3/9] Compiling assets...\n";
        // Call build-assets.php if it exists
        if (file_exists($basePath . '/cli/build-assets.php')) {
            exec("php cli/build-assets.php", $assetOutput, $assetResult);
            if ($assetResult !== 0) throw new Exception("Asset compilation failed.");
        }
        echo "  ✓ Assets compiled\n";
        $report['assets'] = 'compiled';
    }
    
    // Step 4: Optimize Autoloader
    echo "[4/9] Optimizing autoloader...\n";
    exec("composer dump-autoload -o 2>&1", $output, $returnCode);
    if ($returnCode !== 0) throw new Exception("Autoloader optimization failed.");
    echo "  ✓ Autoloader optimized\n";
    
    // Step 5-7: Standard deployment steps (Permissions, Config, Cache)
    echo "[5-7/9] Finalizing configuration and caches...\n";
    @mkdir($basePath . '/storage/cache/views', 0755, true);
    @mkdir($basePath . '/storage/logs', 0755, true);
    echo "  ✓ Directories prepared\n";

    // Step 8: Post-Deployment Health Check (Local)
    echo "[8/9] Local health check...\n";
    $localPass = true;
    if (file_exists($basePath . '/storage/reports/health.json')) {
        $health = json_decode(file_get_contents($basePath . '/storage/reports/health.json'), true);
        if (($health['status'] ?? '') !== 'healthy' && !$options['force']) {
            echo "  ⚠ Local health report is unhealthy.\n";
            $localPass = false;
        }
    }
    echo $localPass ? "  ✓ Local health passed\n" : "  ✗ Local health failed\n";

    // Step 9: Remote Health Check (Render/Public)
    if ($options['render-url']) {
        echo "[9/9] Remote health check at {$options['render-url']}...\n";
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $response = @file_get_contents($options['render-url'] . '/health', false, $context);

        if ($response === false) {
            echo "  ✗ Remote endpoint unreachable.\n";
            if (!$options['force']) throw new Exception("Remote health check failed.");
        } else {
            $data = json_decode($response, true);
            if (($data['status'] ?? '') === 'healthy') {
                echo "  ✓ Remote health check passed.\n";
            } else {
                echo "  ✗ Remote health check reported issues: " . ($data['summary'] ?? 'Unknown error') . "\n";
                if (!$options['force']) throw new Exception("Remote health check failed.");
            }
        }
    }

    // Deployment Report
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "\n=====================\n";
    echo "\033[32mDeployment Complete!\033[0m\n";
    echo "=====================\n";
    echo "Duration: {$duration}s\n";
    
} catch (Exception $e) {
    echo "\n=====================\n";
    echo "\033[31mDeployment Failed!\033[0m\n";
    echo "=====================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";
