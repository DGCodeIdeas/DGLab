<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude(['vendor', '.phpunit.cache', 'ci/cache', 'reports'])
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    )
;
