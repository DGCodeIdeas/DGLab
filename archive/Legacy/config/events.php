<?php

use DGLab\Core\EventDrivers\SyncDriver;

/**
 * Event Dispatcher Configuration
 */
return [
    /**
     * Default execution driver.
     * Use SyncDriver for immediate execution or QueueDriver for deferred background tasks.
     */
    'default_driver' => SyncDriver::class,

    /**
     * Queue settings for asynchronous events.
     */
    'queue' => [
        'table' => 'event_queue',
        'retry_limit' => 3,
        'retry_delay' => 60, // seconds
    ],

    /**
     * Optional: Map specific events to specific drivers.
     */
    'map' => [
        // 'UserRegistered' => QueueDriver::class,
    ],
];
