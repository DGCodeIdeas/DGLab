<?php
/**
 * DGLab PWA - Configuration File
 * 
 * This is an example configuration file. Copy this to config.php
 * and modify the values for your environment.
 * 
 * Platform: InfinityFree (or any PHP 8+ shared hosting)
 * 
 * @package DGLab\Config
 * @author DGLab Team
 * @version 1.0.0
 */

return [
    // =========================================================================
    // APPLICATION SETTINGS
    // =========================================================================
    'app' => [
        // Application name
        'name'        => 'DGLab PWA',
        
        // Application version
        'version'     => '1.0.0',
        
        // Environment: development, production, testing
        'env'         => 'production',
        
        // Debug mode (enable only in development)
        'debug'       => false,
        
        // Base URL (leave empty for auto-detection)
        'base_url'    => '',
        
        // Default timezone
        'timezone'    => 'UTC',
        
        // Default language
        'locale'      => 'en',
        
        // Supported locales
        'locales'     => ['en', 'es', 'fr', 'de', 'zh'],
    ],

    // =========================================================================
    // DATABASE SETTINGS
    // =========================================================================
    'database' => [
        // Enable database
        'enabled'     => true,
        
        // Database driver: mysql, sqlite, pgsql
        'driver'      => 'mysql',
        
        // Host (for MySQL/PostgreSQL)
        'host'        => 'localhost',
        
        // Port (for MySQL/PostgreSQL)
        'port'        => 3306,
        
        // Database name
        'database'    => 'your_database_name',
        
        // Username
        'username'    => 'your_username',
        
        // Password
        'password'    => 'your_password',
        
        // Charset (MySQL only)
        'charset'     => 'utf8mb4',
        
        // Path (SQLite only)
        'path'        => STORAGE_PATH . '/database.sqlite',
        
        // Persistent connections
        'persistent'  => false,
        
        // Log queries (for debugging)
        'log_queries' => false,
    ],

    // =========================================================================
    // UPLOAD SETTINGS
    // =========================================================================
    'upload' => [
        // Chunk size in bytes (1MB default)
        'chunk_size'      => 1024 * 1024,
        
        // Maximum file size in bytes (100MB default)
        'max_file_size'   => 100 * 1024 * 1024,
        
        // Allowed MIME types (empty array = all types)
        'allowed_mime_types' => [],
        
        // Allowed file extensions (empty array = all extensions)
        'allowed_extensions' => [],
        
        // Storage path
        'storage_path'    => STORAGE_PATH . '/uploads',
        
        // Chunks path
        'chunks_path'     => STORAGE_PATH . '/chunks',
    ],

    // =========================================================================
    // ASSET SETTINGS
    // =========================================================================
    'assets' => [
        // Enable minification
        'minify'  => true,
        
        // Enable caching
        'cache'   => true,
        
        // Cache path
        'cache_path' => CACHE_PATH . '/assets',
        
        // SCSS variables (injected into all SCSS)
        'scss_variables' => [
            'primary-color'   => '#4f46e5',
            'secondary-color' => '#7c3aed',
            'success-color'   => '#10b981',
            'warning-color'   => '#f59e0b',
            'error-color'     => '#ef4444',
        ],
    ],

    // =========================================================================
    // VIEW SETTINGS
    // =========================================================================
    'view' => [
        // Enable view caching
        'cache' => true,
        
        // Cache path
        'cache_path' => CACHE_PATH . '/views',
    ],

    // =========================================================================
    // SECURITY SETTINGS
    // =========================================================================
    'security' => [
        // CSRF token lifetime (seconds)
        'csrf_lifetime' => 3600,
        
        // Session lifetime (seconds)
        'session_lifetime' => 7200,
        
        // Maximum login attempts
        'max_login_attempts' => 5,
        
        // Login lockout time (seconds)
        'login_lockout' => 900,
    ],

    // =========================================================================
    // PWA SETTINGS
    // =========================================================================
    'pwa' => [
        // App name
        'name'        => 'DGLab PWA',
        
        // Short name
        'short_name'  => 'DGLab',
        
        // Description
        'description' => 'Digital Lab - Web Tools Platform',
        
        // Theme color
        'theme_color' => '#4f46e5',
        
        // Background color
        'background_color' => '#ffffff',
        
        // Display mode: standalone, fullscreen, minimal-ui, browser
        'display'     => 'standalone',
        
        // Start URL
        'start_url'   => '/',
        
        // Orientation: any, natural, landscape, portrait
        'orientation' => 'any',
        
        // Icons
        'icons'       => [
            [
                'src'   => '/assets/icons/icon-72x72.png',
                'sizes' => '72x72',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-96x96.png',
                'sizes' => '96x96',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-128x128.png',
                'sizes' => '128x128',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-144x144.png',
                'sizes' => '144x144',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-152x152.png',
                'sizes' => '152x152',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-384x384.png',
                'sizes' => '384x384',
                'type'  => 'image/png',
            ],
            [
                'src'   => '/assets/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type'  => 'image/png',
            ],
        ],
    ],

    // =========================================================================
    // TOOL-SPECIFIC SETTINGS
    // =========================================================================
    'tools' => [
        // EPUB Font Changer settings
        'epub_font_changer' => [
            // Maximum EPUB file size (MB)
            'max_file_size' => 100,
            
            // Default font
            'default_font'  => 'Merriweather',
            
            // Available fonts
            'fonts'         => [
                'Merriweather',
                'Lora',
                'Open Sans',
                'Roboto',
                'Lato',
            ],
        ],
    ],

    // =========================================================================
    // API SETTINGS
    // =========================================================================
    'api' => [
        // API version
        'version' => 'v1',
        
        // Rate limiting (requests per minute)
        'rate_limit' => 60,
        
        // Default response format: json, xml
        'format' => 'json',
    ],
];
