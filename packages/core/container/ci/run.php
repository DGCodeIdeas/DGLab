<?php

/**
 * CI runner for CORE-02: Dependency Injection Container
 *
 * Runs linting, static analysis, and tests in sequence.
 * Exit code 0 = all passed, non-zero = failure.
 */

declare(strict_types=1);

$root = __DIR__ . '/..';

echo "=== CORE-02 Container CI ===\n\n";

// 1. PHP CS Fixer (dry-run)
echo "[1/3] Running PHP CS Fixer...\n";
passthru("cd \"$root\" && php vendor/bin/php-cs-fixer fix --dry-run 2>&1", $lintExit);
if ($lintExit !== 0) {
    echo "❌ Linting failed.\n";
    exit($lintExit);
}
echo "✅ Linting passed.\n\n";

// 2. PHPStan
echo "[2/3] Running PHPStan...\n";
passthru("cd \"$root\" && php vendor/bin/phpstan analyse 2>&1", $analyseExit);
if ($analyseExit !== 0) {
    echo "❌ Static analysis failed.\n";
    exit($analyseExit);
}
echo "✅ Static analysis passed.\n\n";

// 3. PHPUnit
echo "[3/3] Running PHPUnit...\n";
passthru("cd \"$root\" && php vendor/bin/phpunit 2>&1", $testExit);
if ($testExit !== 0) {
    echo "❌ Tests failed.\n";
    exit($testExit);
}
echo "✅ All tests passed.\n\n";

echo "=== CI gate passed ===\n";