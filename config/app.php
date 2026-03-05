<?php
/**
 * DGLab Application Configuration
 * 
 * This file contains the main application configuration settings.
 * Values can be overridden via environment variables in .env file.
 */

return [
    /**
     * Application Identity
     */
    'name' => $_ENV['APP_NAME'] ?? 'DGLab',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    
    /**
     * Version Information
     */
    'version' => '1.0.0',
    'release_date' => '2024-01-01',
    
    /**
     * Paths
     */
    'paths' => [
        'base' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'config' => dirname(__DIR__) . '/config',
        'resources' => dirname(__DIR__) . '/resources',
        'storage' => dirname(__DIR__) . '/storage',
        'public' => dirname(__DIR__) . '/public',
        'vendor' => dirname(__DIR__) . '/vendor',
    ],
    
    /**
     * Feature Flags
     */
    'features' => [
        'chunked_upload' => true,
        'service_worker' => true,
        'push_notifications' => false,
        'background_sync' => true,
        'offline_mode' => true,
    ],
    
    /**
     * Security Settings
     */
    'security' => [
        'csrf_lifetime' => (int) ($_ENV['CSRF_TOKEN_LIFETIME'] ?? 7200),
        'password_cost' => (int) ($_ENV['PASSWORD_HASH_COST'] ?? 10),
        'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? null,
        'session_secure' => filter_var($_ENV['SESSION_SECURE'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'session_http_only' => filter_var($_ENV['SESSION_HTTP_ONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'session_same_site' => $_ENV['SESSION_SAME_SITE'] ?? 'lax',
    ],
    
    /**
     * Upload Settings
     */
    'upload' => [
        'max_size' => (int) ($_ENV['MAX_UPLOAD_SIZE'] ?? 52428800), // 50MB
        'chunk_size' => (int) ($_ENV['CHUNK_SIZE'] ?? 1048576), // 1MB
        'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'epub,txt,pdf'),
        'temp_path' => dirname(__DIR__) . '/storage/uploads/temp',
        'chunks_path' => dirname(__DIR__) . '/storage/uploads/chunks',
    ],
    
    /**
     * PWA Settings
     */
    'pwa' => [
        'name' => $_ENV['PWA_NAME'] ?? 'DGLab',
        'short_name' => $_ENV['PWA_SHORT_NAME'] ?? 'DGLab',
        'theme_color' => $_ENV['PWA_THEME_COLOR'] ?? '#0d6efd',
        'background_color' => $_ENV['PWA_BACKGROUND_COLOR'] ?? '#ffffff',
        'display' => $_ENV['PWA_DISPLAY'] ?? 'standalone',
        'start_url' => '/',
        'scope' => '/',
        'orientation' => 'any',
        'icons' => [
            ['src' => '/assets/images/icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/assets/images/icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ],
];
