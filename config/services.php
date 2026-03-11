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
        'novel-to-manga-script' => \DGLab\Services\NovelToMangaScript\NovelToMangaScript::class,
        // Add new services here following the same pattern
        // 'image-resizer' => \DGLab\Services\ImageResizer\ImageResizer::class,
        // 'pdf-converter' => \DGLab\Services\PdfConverter\PdfConverter::class,
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
          
    /*
     * NovelToMangaScript Service Configuration
     */
    'novel-to-manga-script' => [
        'max_file_size' => 10485760, // 10MB for text files
        'allowed_extensions' => ['txt', 'epub', 'pdf', 'docx', 'md'],
        'allowed_mime_types' => [
            'text/plain',
            'application/epub+zip',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/markdown',
        ],
        'max_chapters_per_batch' => 10,
        'max_tokens_per_chapter' => 100000,
        'default_output_format' => 'json',
        'supported_output_formats' => ['json', 'fountain', 'fdx', 'markdown'],
        'temp_processing_path' => __DIR__ . '/../storage/uploads/temp/manga-script',
        
        // AI Provider Settings (references llm_unified.php)
        'llm_config' => 'llm_unified',
        'default_routing_strategy' => 'intelligent', // intelligent, round_robin, priority, failover
        'enable_fallback' => true,
        'max_retries' => 3,
        'retry_delay_ms' => 1000,
        
        // Pipeline Settings
        'pipeline' => [
            'stages' => ['parse', 'analyze', 'segment', 'visualize', 'format'],
            'parallel_stages' => ['analyze', 'segment'],
            'cache_intermediate' => true,
            'cache_ttl' => 3600,
        ],
        
        // Output Templates
        'templates' => [
            'panel_description' => 'Visual description for manga panel generation',
            'dialogue_format' => 'Manga-style dialogue with speaker identification',
            'action_format' => 'Concise action descriptions for visual adaptation',
        ],
    ],
];
