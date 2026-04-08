<?php

use DGLab\Core\EventDrivers\SyncDriver;
use DGLab\Core\EventDrivers\BroadcastDriver;

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
        'job.progress' => BroadcastDriver::class,
        'console.log' => BroadcastDriver::class,
        'user.notification' => BroadcastDriver::class,
    ],
];
