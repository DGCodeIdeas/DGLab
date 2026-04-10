<?php

/**
 * Asset Pipeline Configuration
 */
return [
    'pipeline' => [
        'mode' => env('ASSET_MODE', 'esm'), // 'esm' (bundless) or 'bundle'
        'entries' => [
            'app' => 'resources/js/app.js',
            'LitTestList' => 'resources/js/components/LitTestList.js',
            'LitTestCounter' => 'resources/js/components/LitTestCounter.js',
            'LitTestFetch' => 'resources/js/components/LitTestFetch.js',
            'LitTestOffline' => 'resources/js/components/LitTestOffline.js',
            'lit_test_list' => 'resources/js/components/LitTestList.js',
            'lit_test_counter' => 'resources/js/components/LitTestCounter.js',
            'lit_test_fetch' => 'resources/js/components/LitTestFetch.js',
            'lit_test_offline' => 'resources/js/components/LitTestOffline.js',
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
    'vendor' => [
        'path' => 'public/vendor',
    ],
];
