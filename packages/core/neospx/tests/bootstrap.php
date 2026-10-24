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
require '/home/dgci/www/DGLab/vendor/autoload.php';
