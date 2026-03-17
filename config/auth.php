<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Password Hashing Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how passwords are hashed using the Argon2id
    | algorithm. Sensible defaults are provided, but can be adjusted based
    | on server resources and security requirements.
    |
    */
    'hashing' => [
        'driver' => 'argon2id',
        'argon2id' => [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Validation Rules
    |--------------------------------------------------------------------------
    |
    | Regular expression patterns for validating different user identifiers.
    |
    */
    'validation' => [
        'username' => '/^[a-zA-Z0-9_-]{3,100}$/',
        'phone' => '/^\+?[0-9]{7,20}$/',
        // Email uses PHP's filter_var(FILTER_VALIDATE_EMAIL)
    ],
];
