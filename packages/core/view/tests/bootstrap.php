<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'SovereignStack\\Core\\View\\';
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

$rootVendorAutoload = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($rootVendorAutoload)) {
    require $rootVendorAutoload;
}
