<?php
/**
 * DGLab Database Configuration
 * 
 * Configuration for MySQL database connections optimized for shared hosting.
 */

return [
    /**
     * Default Database Connection
     */
    'default' => 'mysql',
    
    /**
     * Database Connections
     */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? 'dglab_pwa',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Use persistent connections
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_COMPRESS => true,
            ],
        ],
        
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => dirname(__DIR__) . '/storage/database.sqlite',
            'prefix' => '',
        ],
    ],
    
    /**
     * Connection Pool Settings (simulated for PDO)
     */
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 5,
        'max_idle_time' => 60,
    ],
    
    /**
     * Retry Configuration
     */
    'retry' => [
        'attempts' => 3,
        'delay' => 1000, // milliseconds
        'multiplier' => 2,
    ],
    
    /**
     * Query Logging
     */
    'logging' => [
        'enabled' => true,
        'slow_query_threshold' => 1000, // milliseconds
        'log_bindings' => false,
    ],
];
