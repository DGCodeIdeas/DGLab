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
     * Optional: Map specific events to specific drivers.
     * This will be implemented in Phase 3.
     */
    'map' => [
        // 'UserRegistered' => QueueDriver::class,
    ],
];
