<?php
/**
 * DGLab PWA - Main Entry Point
 * 
 * This is the front controller for the DGLab PWA application.
 * All requests are routed through this file for proper MVC handling.
 * 
 * Platform: InfinityFree (PHP 8+ compatible)
 * Architecture: MVC with OOP Framework
 * 
 * @package DGLabPWA
 * @author DGLab Team
 * @version 1.0.0
 * @license MIT
 */

// =============================================================================
// ERROR REPORTING CONFIGURATION
// =============================================================================
// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// =============================================================================
// SESSION INITIALIZATION
// =============================================================================
// Start session for user state management and CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================================================
// CONSTANTS DEFINITION
// =============================================================================
// Define essential path constants for the application

/** @var string ROOT_PATH Absolute path to project root */
define('ROOT_PATH', dirname(__DIR__));

/** @var string APP_PATH Absolute path to application directory */
define('APP_PATH', ROOT_PATH . '/app');

/** @var string CONFIG_PATH Absolute path to configuration directory */
define('CONFIG_PATH', ROOT_PATH . '/config');

/** @var string PUBLIC_PATH Absolute path to public directory */
define('PUBLIC_PATH', __DIR__);

/** @var string STORAGE_PATH Absolute path to storage directory */
define('STORAGE_PATH', ROOT_PATH . '/storage');

/** @var string ASSETS_PATH Absolute path to assets directory */
define('ASSETS_PATH', ROOT_PATH . '/assets');

/** @var string VIEWS_PATH Absolute path to views directory */
define('VIEWS_PATH', APP_PATH . '/Views');

/** @var string CACHE_PATH Absolute path to cache directory */
define('CACHE_PATH', STORAGE_PATH . '/cache');

/** @var string UPLOADS_PATH Absolute path to uploads directory */
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');

/** @var string CHUNKS_PATH Absolute path to chunked uploads directory */
define('CHUNKS_PATH', STORAGE_PATH . '/chunks');

/** @var string EXPORTS_PATH Absolute path to exports directory */
define('EXPORTS_PATH', STORAGE_PATH . '/exports');

/** @var string APP_VERSION Current application version */
define('APP_VERSION', '1.0.0');

/** @var string APP_NAME Application name */
define('APP_NAME', 'DGLab PWA');

// =============================================================================
// AUTOLOADER CONFIGURATION
// =============================================================================
// Register PSR-4 style autoloader for the application namespace

spl_autoload_register(function (string $class): void {
    // Define namespace mappings
    $prefixes = [
        'DGLab\\' => APP_PATH . '/',
    ];
    
    foreach ($prefixes as $prefix => $baseDir) {
        // Check if class uses this namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get relative class name
        $relativeClass = substr($class, $len);
        
        // Convert namespace separators to directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // Include file if it exists
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// =============================================================================
// LOAD CONFIGURATION
// =============================================================================
// Load environment-specific configuration

$configFile = CONFIG_PATH . '/config.php';
if (!file_exists($configFile)) {
    die('Configuration file not found. Please copy config.example.php to config.php');
}

/** @var array $config Global configuration array */
$config = require $configFile;

// =============================================================================
// TIMEZONE CONFIGURATION
// =============================================================================
// Set default timezone from configuration
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

// =============================================================================
// INITIALIZE APPLICATION
// =============================================================================
// Create and run the application router

try {
    // Initialize the router
    $router = new DGLab\Core\Router();
    
    // Load route definitions
    require APP_PATH . '/Config/routes.php';
    
    // Dispatch the current request
    $router->dispatch();
    
} catch (Exception $e) {
    // Handle any uncaught exceptions
    http_response_code(500);
    
    if ($config['app']['debug'] ?? false) {
        echo '<h1>Application Error</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Internal Server Error</h1>';
        echo '<p>An error occurred while processing your request.</p>';
    }
}
