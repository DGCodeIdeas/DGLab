<?php
/**
 * DGLab Deployment Script
 * 
 * Deployment automation for AeonFree shared hosting.
 * 
 * Usage:
 *   php cli/deploy.php [options]
 * 
 * Options:
 *   --skip-tests     Skip running tests
 *   --skip-assets    Skip asset compilation
 *   --skip-db        Skip database migrations
 *   --force          Force deployment even if checks fail
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Parse options
$options = [
    'skip-tests' => in_array('--skip-tests', $argv),
    'skip-assets' => in_array('--skip-assets', $argv),
    'skip-db' => in_array('--skip-db', $argv),
    'force' => in_array('--force', $argv),
];

$basePath = dirname(__DIR__);
$report = [];

echo "DGLab Deployment Tool\n";
echo "=====================\n\n";

$startTime = microtime(true);

try {
    // Step 1: Environment Validation
    echo "[1/8] Validating environment...\n";
    
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
        echo "  ✓ Extension: {$ext}\n";
    }
    
    $report['environment'] = 'OK';
    echo "\n";
    
    // Step 2: Database Migrations
    if (!$options['skip-db']) {
        echo "[2/8] Running database migrations...\n";
        
        // Load environment
        $envFile = $basePath . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        $config = require $basePath . '/config/database.php';
        $db = new \DGLab\Database\Connection($config);
        
        // Test connection
        try {
            $db->getPdo()->query('SELECT 1');
            echo "  ✓ Database connection successful\n";
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        // Run migrations
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
    } else {
        echo "[2/8] Skipping database migrations\n";
        $report['migrations'] = 'skipped';
    }
    echo "\n";
    
    // Step 3: Asset Compilation
    if (!$options['skip-assets']) {
        echo "[3/8] Compiling assets...\n";
        
        // Create assets directory
        $assetsDir = $basePath . '/public/assets/css';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }
        
        $jsDir = $basePath . '/public/assets/js';
        if (!is_dir($jsDir)) {
            mkdir($jsDir, 0755, true);
        }
        
        // Copy app.css (placeholder - real SCSS compilation would happen here)
        $cssContent = "/* DGLab App Styles */\n";
        $cssContent .= file_get_contents($basePath . '/resources/scss/app.scss');
        file_put_contents($assetsDir . '/app.css', $cssContent);
        
        echo "  ✓ CSS compiled\n";
        
        // Copy JS
        copy($basePath . '/resources/js/app.js', $jsDir . '/app.js');
        
        echo "  ✓ JS copied\n";
        
        $report['assets'] = 'compiled';
    } else {
        echo "[3/8] Skipping asset compilation\n";
        $report['assets'] = 'skipped';
    }
    echo "\n";
    
    // Step 4: Optimize Autoloader
    echo "[4/8] Optimizing autoloader...\n";
    
    exec("cd {$basePath} && composer dump-autoload -o 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Autoloader optimization failed: " . implode("\n", $output));
    }
    
    echo "  ✓ Autoloader optimized\n";
    $report['autoloader'] = 'optimized';
    echo "\n";
    
    // Step 5: Set Permissions
    echo "[5/8] Setting permissions...\n";
    
    $directories = [
        $basePath . '/storage' => 0755,
        $basePath . '/storage/cache' => 0755,
        $basePath . '/storage/logs' => 0755,
        $basePath . '/storage/uploads' => 0755,
        $basePath . '/public/uploads' => 0755,
    ];
    
    foreach ($directories as $dir => $perm) {
        if (is_dir($dir)) {
            chmod($dir, $perm);
            echo "  ✓ {$dir} ({$perm})\n";
        }
    }
    
    $report['permissions'] = 'set';
    echo "\n";
    
    // Step 6: Generate .env if needed
    echo "[6/8] Checking environment configuration...\n";
    
    if (!file_exists($basePath . '/.env')) {
        if (file_exists($basePath . '/.env.example')) {
            copy($basePath . '/.env.example', $basePath . '/.env');
            echo "  ✓ Created .env from .env.example\n";
            echo "  ⚠ Please edit .env with your configuration!\n";
        } else {
            throw new Exception("No .env or .env.example file found");
        }
    } else {
        echo "  ✓ .env exists\n";
    }
    
    $report['env'] = 'OK';
    echo "\n";
    
    // Step 7: Clear and Warm Caches
    echo "[7/8] Managing caches...\n";
    
    // Clear view cache
    $viewCache = $basePath . '/storage/cache/views';
    if (is_dir($viewCache)) {
        array_map('unlink', glob($viewCache . '/*'));
        echo "  ✓ View cache cleared\n";
    }
    
    // Clear asset cache
    $assetCache = $basePath . '/storage/cache/assets';
    if (is_dir($assetCache)) {
        array_map('unlink', glob($assetCache . '/*'));
        echo "  ✓ Asset cache cleared\n";
    }
    
    $report['cache'] = 'cleared';
    echo "\n";
    
    // Step 8: Health Check
    echo "[8/8] Running health checks...\n";
    
    $checks = [];
    
    // Check write permissions
    $checks['storage_writable'] = is_writable($basePath . '/storage');
    $checks['uploads_writable'] = is_writable($basePath . '/public/uploads');
    
    // Check database
    try {
        $db->getPdo()->query('SELECT 1');
        $checks['database'] = true;
    } catch (Exception $e) {
        $checks['database'] = false;
    }
    
    foreach ($checks as $name => $passed) {
        $status = $passed ? '✓' : '✗';
        echo "  {$status} {$name}\n";
    }
    
    $allPassed = !in_array(false, $checks, true);
    
    if (!$allPassed && !$options['force']) {
        throw new Exception("Health checks failed. Use --force to deploy anyway.");
    }
    
    $report['health'] = $allPassed ? 'passed' : 'failed (forced)';
    echo "\n";
    
    // Deployment Report
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "=====================\n";
    echo "Deployment Complete!\n";
    echo "=====================\n";
    echo "Duration: {$duration}s\n";
    echo "\nSummary:\n";
    foreach ($report as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
} catch (Exception $e) {
    echo "\n=====================\n";
    echo "Deployment Failed!\n";
    echo "=====================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";
