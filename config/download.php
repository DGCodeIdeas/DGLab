<?php

return [
    /**
     * Default Download Driver
     */
    'default' => 'local',

    /**
     * Storage Drivers
     */
    'drivers' => [
        'local' => [
            'driver' => \DGLab\Services\Download\Drivers\LocalDriver::class,
            'disk' => 'local',
        ],
    ],

    /**
     * Security Settings
     */
    'security' => [
        'enable_signed_urls' => true,
        'token_lifetime' => 3600, // 1 hour
    ],

    /**
     * Encryption Settings
     */
    'encryption' => [
        'key' => $_ENV['DOWNLOAD_SIGNING_KEY'] ?? '32-char-encryption-key-for-test!',
    ],

    /**
     * Cleanup Settings
     */
    'cleanup' => [
        'threshold' => 86400, // 24 hours in seconds
        'exclude' => [
            '/\.keep/',
            '/logo\.png/',
            '/favicon\.ico/',
        ],
        'temp_path' => dirname(__DIR__) . '/storage/uploads/temp',
    ],
];
