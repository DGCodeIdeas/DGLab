<?php

/**
 * DGLab Helper Functions
 */

use DGLab\Core\Application;
use DGLab\Core\Response;
use DGLab\Core\ResponseFactoryInterface;
use DGLab\Core\View;
use DGLab\Services\Download\Download;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\MangaScript\MangaScriptService;

function app(): Application
{
    return Application::getInstance();
}

function config(string|array $key, mixed $default = null): mixed
{
    if (is_array($key)) {
        foreach ($key as $k => $v) {
            Application::getInstance()->setConfig($k, $v);
        }
        return null;
    }
    return Application::getInstance()->config($key, $default);
}

function view(string $template, array $data = [], ?string $layout = 'master'): Response
{
    $view = app()->get(View::class);
    $content = $view->render($template, $data, $layout);
    return app()->get(ResponseFactoryInterface::class)->create($content);
}

function global_state(?string $key = null, mixed $value = null): mixed
{
    $store = app()->get(GlobalStateStore::class);
    if ($key === null) {
        return $store;
    }
    if ($value === null) {
        return $store->get($key);
    }
    $store->set($key, $value);
    return null;
}

function json(array $data, int $status = 200): Response
{
    return app()->get(ResponseFactoryInterface::class)->json($data, $status);
}
function redirect(string $url, int $status = 302): Response
{
    return app()->get(ResponseFactoryInterface::class)->redirect($url, $status);
}
function route(string $name, array $parameters = []): string
{
    return app()->get(\DGLab\Core\Router::class)->url($name, $parameters);
}

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

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function str_random(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

function slug(string $text): string
{
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
    return strtolower(trim($text, '-'));
}

function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    if ($value === null) {
        return $default;
    }
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
    if (preg_match('/^"(.*)"$/', $value, $matches)) {
        return $matches[1];
    }
    return $value;
}

function log_message(string $level, string $message, array $context = []): void
{
    app()->get(\Psr\Log\LoggerInterface::class)->log($level, $message, $context);
}

if (!function_exists('dd')) {
    function dd(...$args): never
    {
        foreach ($args as $arg) {
            var_dump($arg);
        } die();
    }
}

if (!function_exists('dump')) {
    function dump(...$args): void
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }
    }
}

function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function expects_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'application/json') !== false || is_ajax();
}

function base_url(string $path = ''): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function cache(string $key, callable $callback, int $ttl = 3600): mixed
{
    $cachePath = app()->getBasePath() . '/storage/cache';
    $file = $cachePath . '/' . md5($key) . '.cache';
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        return unserialize(file_get_contents($file));
    }
    $data = $callback();
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
    }
    file_put_contents($file, serialize($data), LOCK_EX);
    return $data;
}

function clear_cache(?string $pattern = null): void
{
    $cachePath = app()->getBasePath() . '/storage/cache';
    if (!is_dir($cachePath)) {
        return;
    }
    foreach (glob($cachePath . '/*.cache') as $file) {
        if ($pattern === null || strpos(basename($file), $pattern) !== false) {
            @unlink($file);
        }
    }
}

function download(string $path, ?string $name = null, array $headers = [], ?string $driver = null): Response
{
    return Download::file($path, $name, $headers, $driver);
}

function event(\DGLab\Core\Contracts\EventInterface|string $event, array $payload = []): void
{
    if (is_string($event)) {
        $event = new \DGLab\Core\GenericEvent($event, $payload);
    }
    \DGLab\Facades\Event::dispatch($event);
}

function auth(): \DGLab\Services\Auth\AuthManager
{
    return Application::getInstance()->get(\DGLab\Services\Auth\AuthManager::class);
}
function mangascript(): MangaScriptService
{
    return Application::getInstance()->get(MangaScriptService::class);
}
