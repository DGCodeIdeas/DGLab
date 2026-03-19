<?php
return [
    'services' => [
        'download-service' => \DGLab\Services\Download\DownloadService::class,
        'epub-font-changer' => \DGLab\Services\EpubFontChanger\EpubFontChanger::class,
        'webpack' => \DGLab\Services\AssetPacker\WebpackService::class,
        'novel-to-manga-script' => \DGLab\Services\NovelToMangaScript\NovelToMangaScript::class,
        'superpowers-global-state' => \DGLab\Services\Superpowers\Runtime\GlobalStateStore::class,
    ],
    'defaults' => [
        'chunked_upload' => true,
        'max_file_size' => 52428800,
        'chunk_size' => 1048576,
        'session_lifetime' => 86400,
        'cleanup_interval' => 3600,
    ],
    'epub-font-changer' => [
        'max_file_size' => 104857600,
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
        ],
        'target_elements' => [
            'body' => 'Body text',
            'h1' => 'Heading 1',
        ],
    ],
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
    'novel-to-manga-script' => \DGLab\Services\NovelToMangaScript\NovelToMangaScript::class,
        'superpowers-global-state' => \DGLab\Services\Superpowers\Runtime\GlobalStateStore::class,
        'max_file_size' => 10485760,
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
        'llm_config' => 'llm_unified',
        'default_routing_strategy' => 'intelligent',
        'enable_fallback' => true,
        'max_retries' => 3,
        'retry_delay_ms' => 1000,
        'pipeline' => [
            'stages' => ['parse', 'analyze', 'segment', 'visualize', 'format'],
            'parallel_stages' => ['analyze', 'segment'],
            'cache_intermediate' => true,
            'cache_ttl' => 3600,
        ],
        'templates' => [
            'panel_description' => 'Visual description for manga panel generation',
            'dialogue_format' => 'Manga-style dialogue with speaker identification',
            'action_format' => 'Concise action descriptions for visual adaptation',
        ],
    ],
];
