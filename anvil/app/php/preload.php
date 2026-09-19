<?php
/**
 * anvil/app/php/preload.php — opcache preload template (ADR-010).
 *
 * This file is referenced by app/Caddyfile.blue's
 *   php_ini "opcache.preload" "__APP_ROOT__/config/preload.php"
 * and is symlinked into each release's config/ directory by the deploy
 * pipeline. It preloads the hot-path classes from Core + Hub so the first
 * request after a worker boots does not pay the compile cost.
 *
 * Implementation note: this file is intentionally framework-agnostic — it
 * does NOT bootstrap the full Symfony kernel (preload runs before workers
 * fork, and the kernel holds state that should be per-worker). It only
 * opcache-preloads a static list of classes that are pure data/logic.
 *
 * When a Spoke or the Hub grows classes that should be preloaded, append
 * them to $preload_classes. The list is deliberately curated — preloading
 * everything would bloat RSS without speeding up the hot path.
 */

declare(strict_types=1);

// Resolve the release root from this file's location: config/preload.php
// lives at <release>/config/preload.php, so the release root is one level up.
$releaseRoot = dirname(__DIR__);

$preload_classes = [
    // Core contracts (always loaded, never change shape at runtime).
    'SovereignStack\\Core\\Contracts\\PulseInterface',
    'SovereignStack\\Core\\Contracts\\SchedulerInterface',
    'SovereignStack\\Core\\Contracts\\TenantScopeInterface',
    'SovereignStack\\Core\\Fiber\\Pulse',
    'SovereignStack\\Core\\Fiber\\Scheduler',
    'SovereignStack\\Core\\Middleware\\Pipeline',
    'SovereignStack\\Core\\Http\\Request',
    'SovereignStack\\Core\\Http\\Response',
    'SovereignStack\\Core\\Http\\Kernel',
    // Hub (loaded only if present; release may omit the Hub tier).
    'SovereignStack\\Hub\\Hub',
    'SovereignStack\\Hub\\Registry',
];

foreach ($preload_classes as $class) {
    $relative = str_replace(['SovereignStack\\', '\\'], ['/', '/'], $class);
    $candidates = [
        $releaseRoot . '/packages/core/src' . $relative . '.php',
        $releaseRoot . '/packages/hub/src' . $relative . '.php',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            break;
        }
    }
}
