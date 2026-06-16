<?php

declare(strict_types=1);

$commands = [
    'composer validate --strict' => 1,
    'php vendor/phpstan/phpstan/phpstan analyse' => 2,
    'php vendor/phpunit/phpunit/phpunit --no-coverage' => 3,
    'php vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix --dry-run --diff --using-cache=no --allow-risky=yes' => 4,
];

$failed = false;

foreach ($commands as $command => $step) {
    echo "\n=== Step {$step}: {$command} ===\n";
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        echo "\nFAILED at step {$step}: {$command}\n";
        $failed = true;
        break;
    }
}

if ($failed) {
    exit(1);
}

echo "\n=== All checks passed ===\n";
exit(0);
