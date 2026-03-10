<?php
/**
 * DGLab Service Registry Configuration
 * 
 * This file registers all available services in the application.
 * Services are lazy-loaded through the ServiceRegistry.
 */

return [
    /**
     * Registered Services
     * 
     * Format: 'service_id' => ServiceClass::class
     */
    'services' => [
        'epub-font-changer' => \DGLab\Services\EpubFontChanger\EpubFontChanger::class,
        'webpack' => \DGLab\Services\AssetPacker\WebpackService::class,
    ],
    
    /**
     * Service Defaults
     */
    'defaults' => [
        'chunked_upload' => true,
        'max_file_size' => 52428800, // 50MB
        'chunk_size' => 1048576, // 1MB
        'session_lifetime' => 86400, // 24 hours
        'cleanup_interval' => 3600, // 1 hour
    ],
    
    /**
     * Service-Specific Configuration
     */
    'epub-font-changer' => [
        'max_file_size' => 104857600, // 100MB for EPUB files
        'allowed_extensions' => ['epub'],
        'allowed_mime_types' => ['application/epub+zip', 'application/zip'],
        'default_fonts_path' => __DIR__ . '/../app/Services/EpubFontChanger/assets/default-fonts',
        'temp_extract_path' => __DIR__ . '/../storage/uploads/temp/epub',
        'fonts' => [
            'opendyslexic' => [
                'name' => 'OpenDyslexic',
                'family' => 'OpenDyslexic',
                'description' => 'Font designed for dyslexic readers',
                'license' => 'OFL',
                'files' => [
                    'regular' => 'OpenDyslexic-Regular.woff2',
                    'italic' => 'OpenDyslexic-Italic.woff2',
                    'bold' => 'OpenDyslexic-Bold.woff2',
                    'bold_italic' => 'OpenDyslexic-BoldItalic.woff2',
                ],
            ],
            'merriweather' => [
                'name' => 'Merriweather',
                'family' => 'Merriweather',
                'description' => 'Elegant serif font for reading',
                'license' => 'OFL',
                'files' => [
                    'regular' => 'Merriweather-Regular.woff2',
                    'italic' => 'Merriweather-Italic.woff2',
                    'bold' => 'Merriweather-Bold.woff2',
                    'bold_italic' => 'Merriweather-BoldItalic.woff2',
                ],
            ],
            'fira-sans' => [
                'name' => 'Fira Sans',
                'family' => 'Fira Sans',
                'description' => 'Modern sans-serif font',
                'license' => 'OFL',
                'files' => [
                    'regular' => 'FiraSans-Regular.woff2',
                    'italic' => 'FiraSans-Italic.woff2',
                    'bold' => 'FiraSans-Bold.woff2',
                    'bold_italic' => 'FiraSans-BoldItalic.woff2',
                ],
            ],
        ],
        'target_elements' => [
            'body' => 'Body text',
            'h1' => 'Heading 1',
            'h2' => 'Heading 2',
            'h3' => 'Heading 3',
            'h4' => 'Heading 4',
            'h5' => 'Heading 5',
            'h6' => 'Heading 6',
            'blockquote' => 'Blockquotes',
            'code' => 'Code blocks',
            'pre' => 'Preformatted text',
        ],
    ],

    /**
     * Webpack Asset Bundler Configuration
     */
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
