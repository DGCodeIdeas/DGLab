<?php

/**
 * DGLab Helper Functions
 *
 * Global helper functions for common operations.
 */

use DGLab\Core\Application;
use DGLab\Core\Response;
use DGLab\Core\View;

/**
 * Get the application instance
 */
function app(): Application
{
    return Application::getInstance();
}

/**
 * Get a config value
 */
function config(string $key, mixed $default = null): mixed
{
    return Application::getInstance()->config($key, $default);
}

/**
 * Render a view
 */
function view(string $template, array $data = [], ?string $layout = 'master'): Response
{
    $view = app()->get(View::class);
    $content = $view->render($template, $data, $layout);

    return new Response($content);
}

/**
 * Return a JSON response
 */
function json(array $data, int $status = 200): Response
{
    return Response::json($data, $status);
}

/**
 * Return a redirect response
 */
function redirect(string $url, int $status = 302): Response
{
    return Response::redirect($url, $status);
}

/**
 * Generate URL for named route
 */
function route(string $name, array $parameters = []): string
{
    $router = app()->get(\DGLab\Core\Router::class);

    return $router->url($name, $parameters);
}

/**
 * Get asset URL with cache busting
 */
function asset(string $path): string
{
    $manifestPath = app()->getBasePath() . '/public/assets/manifest.json';

    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (isset($manifest[$path])) {
            return '/assets/' . $manifest[$path];
        }
    }

    return '/assets/' . ltrim($path, '/');
}

/**
 * Escape HTML entities
 */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get old input value
 */
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

/**
 * Get CSRF token
 */
function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Get CSRF field HTML
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Flash a message to the session
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get flashed message
 */
function get_flash(string $type): ?string
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);

    return $message;
}

/**
 * Generate a random string
 */
function str_random(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Slugify a string
 */
function slug(string $text): string
{
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);

    // Remove leading/trailing hyphens
    $text = trim($text, '-');

    // Convert to lowercase
    return strtolower($text);
}

/**
 * Format file size
 */
function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get environment variable
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

    if ($value === null) {
        return $default;
    }

    // Convert string values
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    // Remove quotes if present
    if (preg_match('/^"(.*)"$/', $value, $matches)) {
        return $matches[1];
    }

    return $value;
}

/**
 * Log a message
 */
function log_message(string $level, string $message, array $context = []): void
{
    $logger = app()->get(\Psr\Log\LoggerInterface::class);
    $logger->log($level, $message, $context);
}

/**
 * Dump and die
 */
function dd(...$args): never
{
    foreach ($args as $arg) {
        var_dump($arg);
    }

    die();
}

/**
 * Dump without die
 */
function dump(...$args): void
{
    foreach ($args as $arg) {
        var_dump($arg);
    }
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/**
 * Check if request expects JSON
 */
function expects_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return strpos($accept, 'application/json') !== false || is_ajax();
}

/**
 * Get base URL
 */
function base_url(string $path = ''): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

/**
 * Generate a UUID v4
 */
function uuid(): string
{
    $data = random_bytes(16);

    // Set version (4) and variant bits
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Cache data for a specified time
 */
function cache(string $key, callable $callback, int $ttl = 3600): mixed
{
    $cachePath = app()->getBasePath() . '/storage/cache';
    $file = $cachePath . '/' . md5($key) . '.cache';

    // Check if cached and valid
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        return unserialize(file_get_contents($file));
    }

    // Generate and cache
    $data = $callback();

    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
    }

    file_put_contents($file, serialize($data), LOCK_EX);

    return $data;
}

/**
 * Clear cache
 */
function clear_cache(?string $pattern = null): void
{
    $cachePath = app()->getBasePath() . '/storage/cache';

    if (!is_dir($cachePath)) {
        return;
    }

    foreach (glob($cachePath . '/*.cache') as $file) {
        if ($pattern === null || strpos(basename($file), $pattern) !== false) {
            unlink($file);
        }
    }
}
