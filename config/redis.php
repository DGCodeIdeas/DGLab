<?php
/**
 * Redis Configuration
 */

return [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DB', 0),
    ],
    'nexus' => [
        'host' => env('NEXUS_REDIS_HOST', env('REDIS_HOST', '127.0.0.1')),
        'port' => (int) env('NEXUS_REDIS_PORT', env('REDIS_PORT', 6379)),
        'password' => env('NEXUS_REDIS_PASSWORD', env('REDIS_PASSWORD', null)),
        'database' => (int) env('NEXUS_REDIS_DB', 1),
    ],
];
