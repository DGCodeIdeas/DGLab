<?php

declare(strict_types=1);

// Fixture config file for ConfigBuilder tests.
// Returns a nested array simulating a typical app config.

return [
    'app' => [
        'name' => 'DGLab',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://dglab.local',
    ],
    'db' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 5432,
        'name' => 'dglab',
        'connections' => [
            'primary' => ['host' => 'db-primary.internal', 'port' => 5432],
            'replica' => ['host' => 'db-replica.internal', 'port' => 5433],
        ],
    ],
    'cache' => [
        'driver' => 'redis',
        'ttl' => 3600,
    ],
];
