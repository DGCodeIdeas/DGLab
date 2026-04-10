# Asset Pipeline (ESM-First)

## Overview
The Asset Pipeline is the evolution of the legacy `AssetBundler`. It is a pure-PHP solution for managing frontend assets with a focus on modern ES Modules (ESM) and a "bundless" development experience.

## Core Features
1. **ESM-First**: Promotes serving individual ES modules to leverage browser caching and HTTP/2.
2. **Import Maps**: Uses native browser import maps for bare specifier resolution.
3. **Pure PHP**: No Node.js, NPM, or external build tools required.
4. **Hybrid Mode**: Supports both `esm` (bundless) and `bundle` (legacy concatenation) modes.

## Configuration
Located in `config/assets.php`:

```php
return [
    'pipeline' => [
        'mode' => 'esm', // or 'bundle'
        'entries' => [
            'app' => 'resources/js/app.js',
        ],
        // ...
    ]
];
```

## Service Architecture
- **AssetPipelineService**: The primary orchestrator for processing assets.
- **DependencyResolver**: Recursively identifies all required files for an entry point.
- **ImportMapGenerator**: Generates the `<script type="importmap">` for the browser.

## Build Process
Run the build script to process and optimize assets:
```bash
php cli/build-assets.php
```

In `esm` mode, files are processed individually and placed in `public/assets/js/` preserving their structure. In `bundle` mode, they are concatenated into a single hashed file.
