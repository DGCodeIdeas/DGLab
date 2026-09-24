<?php

declare(strict_types=1);

/**
 * DGLab — Public Web Entry Point (thin, post-ApplicationFactory extraction).
 *
 * Per SPEC-001 §44 (Phase 3 — Composition Root):
 *   "public/index.php SHOULD become a thin executable entry point.
 *    It SHOULD primarily:
 *      1. load the application factory;
 *      2. build the application;
 *      3. invoke the kernel;
 *      4. allow the existing global error/shutdown handling to remain authoritative."
 *
 * Steps 1 + 2 happen below. Steps 3 + 4 happen inside ApplicationFactory::run().
 * All composition logic — container construction, service registration, route
 * registration, middleware wiring, kernel boot, frankenphp worker loop — now
 * lives in app/ApplicationFactory.php (the composition boundary per SPEC §3).
 *
 * FrankenPHP worker mode: when running under FrankenPHP's worker{} directive,
 * this file is loaded ONCE as the worker bootstrap. The ApplicationFactory
 * creates + boots the Kernel once, then ApplicationFactory::run() enters the
 * frankenphp_handle_request() loop. Under PHP-FPM, falls back to single-request
 * handling.
 *
 * Depth-2 scope: the Vanguard enforces contract lookup (default-deny 403)
 * and WAF inspection. JWT verification, rate limiting, and HUB-06 audit are
 * pass-through stubs. When HUB-02/HUB-04/HUB-06 land, the stubs are replaced
 * — the chain order and this entry point are unchanged.
 *
 * @see \App\ApplicationFactory The composition root this entry point delegates to.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\ApplicationFactory;

// 1. Load the application factory (this file).
// 2. Build the application (composition happens here).
// 3. Invoke the kernel + run the worker/request loop (inside run()).
// 4. Global error/shutdown handling is wired inside ApplicationFactory::run(),
//    preserving the existing ErrorHandler + structured logging behavior.
$application = ApplicationFactory::create();
$application->run();
