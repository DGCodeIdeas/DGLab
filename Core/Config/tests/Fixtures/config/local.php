<?php

declare(strict_types=1);

// Fixture: a local override file that overrides nested keys without
// redefining the whole tree. Used to verify recursive merge semantics.

return [
    'app' => [
        'debug' => true,
        'url' => 'https://staging.dglab.local',
    ],
    'db' => [
        'connections' => [
            'primary' => ['host' => 'staging-db-primary.internal'],
        ],
    ],
];
