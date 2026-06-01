<?php
/**
 * DGLab Database Configuration
 */

return [
    /**
     * Default Database Connection
     */
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    
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
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_COMPRESS => true,
            ],
        ],
        
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
            'database' => $_ENV['DB_DATABASE'] ?? 'dglab_pwa',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? dirname(__DIR__) . '/storage/database.sqlite',
            'prefix' => '',
        ],

        'kafka' => [
            'driver' => 'kafka',
            'brokers' => $_ENV['KAFKA_BROKERS'] ?? 'localhost:9092',
            'group_id' => $_ENV['KAFKA_GROUP_ID'] ?? 'dglab_pwa',
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
