<?php
return [
    'defaults' => [
        'guard' => 'web',
        'provider' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
        'jwt' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'database',
            'model' => \DGLab\Models\User::class,
        ],
    ],
    'hashing' => [
        'driver' => 'argon2id',
        'argon2id' => [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ],
    ],
    'validation' => [
        'username' => '/^[a-zA-Z0-9_-]{3,100}$/',
        'phone' => '/^\+?[0-9]{7,20}$/',
    ],
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'base64:7vG6jR9uK8vN3bL1vC7vG6jR9uK8vN3b',
        'algo' => 'HS256',
        'key_name' => 'auth_jwt',
        'ttl' => 60,
    ],
    'ip_whitelist' => [],
    'ip_blacklist' => [],
    'key_storage_path' => dirname(__DIR__) . '/storage/keys',
];
