# Phase 1: Core Engine & Renaming

## Goal
To formally rename the service from `NovelToMangaScript` to `MangaScript` and establish a clean, modular foundation that aligns with the DGLab framework standards.

## Key Components

### 1. Namespace & Service Renaming
- Transition from `DGLab\Services\NovelToMangaScript` to `DGLab\Services\MangaScript`.
- Update `SERVICE_ID` to `manga-script`.
- Update class name to `MangaScriptService`.

### 2. Enhanced MangaScriptInterface
Define a more robust contract for the service:
- `public function process(array $input): array`
- `public function processAsync(array $input): string` (Returns a Job/Event ID)
- `public function getStatus(string $processId): array`
- `public function stream(array $input): iterable`

### 3. Service Registration & Config
- Update `config/services.php` to reflect the new service structure.
- Centralize MangaScript-specific settings in `config/manga_script.php`.

### 4. Legacy Compatibility Layer
- Implement a temporary bridge to ensure existing calls to `NovelToMangaScript` continue to function during the transition.
- Log deprecation warnings for any legacy entry points.

## Technical Requirements
- **Namespace**: `DGLab\Services\MangaScript`
- **Base Class**: Extends `BaseService` and implements `ChunkedServiceInterface`.
- **Dependency Injection**: Use the framework's container for instantiating the `ProviderRepository` and `RoutingEngine`.

## Success Criteria
- The service is accessible via `MangaScript` facade or helper.
- All existing tests for `NovelToMangaScript` pass when targeting the new `MangaScript` implementation.
- Namespace and file structure are clean and follow PSR-12.
