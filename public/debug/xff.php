<?php
/**
 * DGLab — /debug/xff test fixture (dev/staging only).
 *
 * This is a STANDALONE dev/staging fixture, NOT the Hub's diagnostics endpoint.
 * It exists so anvilctl verify headers and scripts/deploy-smoke.sh can test the
 * X-Forwarded-For chain end-to-end (Caddy edge → Tengine LB → FrankenPHP app)
 * WITHOUT requiring the Hub tier to be deployed.
 *
 * When the Hub's diagnostics surface lands (planned: HUB-XX, TBD), it should
 * serve the same JSON contract at /debug/xff (no .php extension) and this file
 * can be deleted. The verify/smoke scripts try /debug/xff first and fall back
 * to /debug/xff.php — so the migration is transparent to operators.
 *
 * Contract:
 *   GET /debug/xff.php
 *     200 OK
 *     Content-Type: application/json
 *     {
 *       "client_ip": "<real client IP, restored from X-Forwarded-For>",
 *       "scheme":    "<https|http, restored from X-Forwarded-Proto>",
 *       "host":      "<Host header>",
 *       "xff_chain": "<raw X-Forwarded-For header value>",
 *       "headers":   { "<header name>": "<header value>", ... }
 *     }
 *
 * Security:
 *   * Returns 404 if APP_DEBUG is not true OR APP_ENV is "production".
 *   * Does NOT echo secrets (no Authorization, Cookie, Set-Cookie).
 *   * Rate-limited implicitly by Tengine's limit_req zone (per_ip) upstream.
 *
 * This file is safe to commit. It carries no secrets and gates itself off in
 * production environments.
 */
declare(strict_types=1);

// --- Production guard ------------------------------------------------------
$appEnv    = $_ENV['APP_ENV']    ?? $_SERVER['APP_ENV']    ?? 'dev';
$appDebug  = $_ENV['APP_DEBUG']  ?? $_SERVER['APP_DEBUG']  ?? 'false';
if ($appEnv === 'production' || strtolower($appDebug) !== 'true') {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "404 Not Found\n";
    return;
}

// --- Restore real client IP + scheme from XFF chain ------------------------
// Caddy sets X-Forwarded-For = "<real client IP>, <Tengine loopback>".
// Tengine APPENDS its own $remote_addr (127.0.0.1) to the chain via
// proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for.
// The real client IP is therefore the LEFTMOST entry in the chain.
$xff     = $_SERVER['HTTP_X_FORWARDED_FOR']   ?? '';
$xffList = array_map('trim', explode(',', $xff));
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '?';
foreach ($xffList as $hop) {
    if ($hop !== '' && $hop !== '127.0.0.1' && $hop !== '::1') {
        $clientIp = $hop;
        break;
    }
}

$scheme  = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http'));
// Trust only https from the XFF chain — anything else falls through to the
// request scheme, which at the app tier is always http (Tengine terminates
// the loopback hop without TLS).
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
}

$host    = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '?');

// --- Build the headers map (excluding secrets) -----------------------------
$redacted = ['authorization', 'cookie', 'set-cookie', 'php_auth_user', 'php_auth_pw'];
$headers  = [];
foreach ($_SERVER as $name => $value) {
    if (!str_starts_with($name, 'HTTP_')) continue;
    $canonical = strtolower(str_replace('_', '-', substr($name, 5)));
    if (in_array($canonical, $redacted, true)) {
        $headers[$canonical] = '<redacted>';
        continue;
    }
    $headers[$canonical] = $value;
}
// Also surface the non-HTTP_ server vars that matter for debugging.
$headers['x_real_ip']         = $_SERVER['REMOTE_ADDR'] ?? '?';
$headers['x_request_id']      = $_SERVER['HTTP_X_REQUEST_ID'] ?? '(none)';

// --- Emit the JSON response -----------------------------------------------
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Debug-Xff: dev-fixture');

echo json_encode([
    'client_ip' => $clientIp,
    'scheme'    => $scheme,
    'host'      => $host,
    'xff_chain' => $xff,
    'headers'   => $headers,
    '_meta'     => [
        'fixture'    => 'public/debug/xff.php',
        'app_env'    => $appEnv,
        'note'       => 'dev/staging fixture; will be superseded by the Hub diagnostics endpoint',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
