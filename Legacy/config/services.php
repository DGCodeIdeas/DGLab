<?php
return [
    'services' => [
        'download-service' => \DGLab\Services\Download\DownloadService::class,
        'epub-font-changer' => \DGLab\Services\EpubFontChanger\EpubFontChanger::class,
        'webpack' => \DGLab\Services\AssetPacker\WebpackService::class,
        'manga-script' => \DGLab\Services\MangaScript\MangaScriptService::class,
        'superpowers-global-state' => \DGLab\Services\Superpowers\Runtime\GlobalStateStore::class,
    ],
    'defaults' => [
        'chunked_upload' => true,
        'max_file_size' => 52428800,
        'chunk_size' => 1048576,
        'session_lifetime' => 86400,
        'cleanup_interval' => 3600,
    ],
    'manga-script' => [
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
