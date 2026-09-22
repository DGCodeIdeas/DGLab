# Phase 1: Core Engine & Renaming

## Goal
To formally transition from the legacy `NovelToMangaScript` to the modern `MangaScript` service, establishing a clean, modular foundation that aligns with the DGLab framework standards.

## Key Components

### 1. Namespace & Service Renaming
- Transition from `DGLab\Services\NovelToMangaScript` to `DGLab\Services\MangaScript`.
- Update `SERVICE_ID` to `manga-script`.
- Base class: `DGLab\Services\MangaScript\MangaScriptService` extending `DGLab\Services\BaseService`.

### 2. Modernized MangaScriptInterface
Define a more robust contract for the service:
- `public function process(array $input): array`
- `public function processAsync(array $input): string` (Returns a Job/Event ID)
- `public function getStatus(string $processId): array`
- `public function stream(array $input): iterable`

### 3. Service Registration & Config
- Update `config/services.php` to reflect the new service structure.
- Leverage `config/llm_unified.php` for provider and model definitions, removing the need for a separate provider repository.

### 4. Legacy Compatibility Layer (Alias)
- Implement a `class_alias` bridge to ensure existing calls to `NovelToMangaScript` continue to function.
- Log deprecation warnings for any legacy entry points to the unified audit log.

### 5. SuperPHP UI Foundation (Studio App)
- Initialize the `MangaScript` Studio App as a standalone component in the CMS Studio ecosystem.
- Create the foundational `<s:mangascript-workspace>` component in `resources/views/components/mangascript/workspace.super.php`.

## Technical Requirements
- **Namespace**: `DGLab\Services\MangaScript`
- **Base Class**: Extends `BaseService` and implements `ChunkedServiceInterface`.
- **Dependency Injection**: Use the framework's container for instantiating the `RoutingEngine`.

## Success Criteria
- The service is accessible via `MangaScript` facade or helper.
- All existing tests for `NovelToMangaScript` pass when targeting the new `MangaScript` implementation.
- The Studio App skeleton is visible in the CMS Studio dashboard.
