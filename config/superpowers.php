<?php

return [
    /**
     * Execution Mode
     * 'interpreted' - Parses and executes AST nodes on the fly (best for dev)
     * 'compiled' - Compiles AST to PHP files and caches them (best for prod)
     * 'auto' - Uses compiled in production, interpreted in debug mode
     */
    'mode' => getenv('SUPERPHP_MODE') ?: 'auto',

    /**
     * Cache path for compiled views
     */
    'cache_path' => dirname(__DIR__) . '/storage/cache/views',

    /**
     * Inline Expressions
     * If enabled, expressions are transpiled and inlined directly into echo statements.
     */
    'inline_expressions' => true,

    /**
     * Check Dependencies
     * If enabled, the engine will check if any included components have changed.
     */
    'check_dependencies' => true,

    /**
     * Reactivity Settings (Phase 7)
     */
    'reactivity' => [
        'enabled' => true,
        'storage' => 'dom',
        'action_route' => '/_superpowers/action',
        'inject_runtime' => true,
    ],

    /**
     * DX & Observability (Phase 9)
     */
    'errors' => [
        'context_lines' => 3,
    ],

    'debug_overlay' => [
        'enabled' => true,
    ],

    'linter' => [
        'on_render' => true,
    ],
];
