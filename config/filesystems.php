<?php

return [
    /**
     * Default Disk
     */
    'default' => 'local',

    /**
     * Disks Configuration
     */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => dirname(__DIR__) . '/storage/app',
        ],
        'public' => [
            'driver' => 'local',
            'root' => dirname(__DIR__) . '/public/assets',
        ],
        'temp' => [
            'driver' => 'local',
            'root' => dirname(__DIR__) . '/storage/uploads/temp',
        ],
    ],
];
