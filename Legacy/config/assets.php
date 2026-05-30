<?php

/**
 * Asset Bundler Configuration
 */
return [
    'webpack' => [
        'entries' => [
            'app' => 'resources/js/app.js',
            'vendor' => 'resources/js/vendor.js',
        ],
        'output' => [
            'path' => 'public/assets',
            'filename' => '[name].[hash].js',
        ],
        'optimization' => [
            'minify' => true,
            'mangle' => true,
            'source_map' => true,
        ],
        'aliases' => [
            '@' => 'resources/js',
        ],
    ],
];
