<?php
declare(strict_types=1);

// Autoload for NeoSPX package (without composer dump-autoload)
spl_autoload_register(function (string $class): void {
    $prefix = 'SovereignStack\\Core\\NeoSPX\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Also load vendor autoloader for PHPUnit
<<<<<<< HEAD
=======
// Load root vendor autoload (relative path — portable across machines/CI).
>>>>>>> origin/migration-stage-0-ci-preflight
$rootVendorAutoload = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($rootVendorAutoload)) {
    require $rootVendorAutoload;
}
