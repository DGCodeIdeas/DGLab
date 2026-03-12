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
];
