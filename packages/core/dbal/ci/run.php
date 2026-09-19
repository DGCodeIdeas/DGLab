#!/usr/bin/env php
<?php
/**
 * CI runner for core/dbal package.
 * Runs PHPUnit + PHPStan from the package's own composer.json.
 */
$root = dirname(__DIR__, 3);
$pkg = __DIR__;
$php = PHP_BINARY;

// 1. Install dependencies at the package level
passthru("cd {$pkg} && composer install --no-interaction --no-progress 2>&1", $code);
if ($code !== 0) {
    // Fallback: use root vendor
    echo "Package install failed, using root vendor\n";
}

// 2. Run PHPStan
echo "=== PHPStan ===\n";
passthru("{$php} {$root}/vendor/bin/phpstan analyse --no-progress 2>&1", $code);
if ($code !== 0) {
    exit(1);
}

// 3. Run PHPUnit
echo "=== PHPUnit ===\n";
$phpunit = "{$root}/vendor/bin/phpunit";
if (!file_exists($phpunit)) {
    $phpunit = "{$pkg}/vendor/bin/phpunit";
}
passthru("{$php} {$phpunit} --testdox 2>&1", $code);
exit($code === 0 ? 0 : 1);
