#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CORE-01 CI Runner
 *
 * Executes all quality tools in order and exits with code 0 only if all pass.
 */

const PASS = 'PASS';
const FAIL = 'FAIL';

const ANSI_GREEN = "\033[32m";
const ANSI_RED   = "\033[31m";
const ANSI_RESET = "\033[0m";

function runTool(string $command, string $label): bool
{
    echo "\n━━━ Running: {$label} ━━━\n";

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        echo ANSI_RED . '  [' . FAIL . "] Failed to launch: {$label}" . ANSI_RESET . "\n";
        return false;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($stdout !== false && $stdout !== '') {
        echo $stdout;
    }
    if ($stderr !== false && $stderr !== '') {
        echo $stderr;
    }

    if ($exitCode === 0) {
        echo ANSI_GREEN . '  [' . PASS . "] {$label}" . ANSI_RESET . "\n";
        return true;
    }

    echo ANSI_RED . '  [' . FAIL . "] {$label} (exit code: {$exitCode})" . ANSI_RESET . "\n";
    return false;
}

$rootDir = __DIR__ . DIRECTORY_SEPARATOR . '..';
$phpBin = PHP_BINARY;

$tools = [
    [
        'command' => sprintf(
            '%s %s analyse --configuration=%s --no-progress --error-format=raw',
            $phpBin,
            escapeshellarg($rootDir . '/vendor/bin/phpstan'),
            escapeshellarg($rootDir . '/phpstan.neon')
        ),
        'label' => 'PHPStan',
    ],
    [
        'command' => sprintf(
            '%s %s --configuration=%s --no-coverage',
            $phpBin,
            escapeshellarg($rootDir . '/vendor/bin/phpunit'),
            escapeshellarg($rootDir . '/phpunit.xml.dist')
        ),
        'label' => 'PHPUnit',
    ],
    [
        'command' => sprintf(
            '%s %s fix --dry-run --diff --using-cache=no',
            $phpBin,
            escapeshellarg($rootDir . '/vendor/bin/php-cs-fixer')
        ),
        'label' => 'PHP CS Fixer',
    ],
    [
        'command' => 'composer validate --no-check-all --strict',
        'label' => 'Composer Validate',
    ],
];

$allPassed = true;

foreach ($tools as $tool) {
    $ok = runTool($tool['command'], $tool['label']);
    if (!$ok) {
        $allPassed = false;
    }
}

echo "\n━━━ Summary ━━━\n";

if ($allPassed) {
    echo ANSI_GREEN . '  All checks passed.' . ANSI_RESET . "\n";
    exit(0);
}

echo ANSI_RED . '  Some checks failed.' . ANSI_RESET . "\n";
exit(1);
