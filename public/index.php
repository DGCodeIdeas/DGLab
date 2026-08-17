<?php
/**
 * DGLab — Public Web Entry Point
 *
 * Bootstraps the Sovereign Stack application.
 * All HTTP requests enter here and are dispatched through the Bridge.
 *
 * Milestone 0: Placeholder — returns 503 until the kernel (CORE-18) and
 * Bridge (BRIDGE-01) are implemented.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// TODO: Bootstrap kernel (CORE-18) and dispatch to Bridge (BRIDGE-01)
//       This placeholder will be replaced during Milestone 0.
http_response_code(503);
header('Content-Type: text/plain');
echo "Sovereign Stack — DGLab
";
echo "Status: Milestone 0 in progress.
";
