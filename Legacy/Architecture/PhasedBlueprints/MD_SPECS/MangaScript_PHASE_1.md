# MangaScript - Phase 1: Core Engine & Renaming

**Status**: COMPLETED
**Source**: `Blueprint/MangaScript/PHASE_1_CORE_ENGINE_RENAMING.md`

## Objectives
- [ ] Transition from `DGLab\Services\NovelToMangaScript` to `DGLab\Services\MangaScript`.
- [ ] Update `SERVICE_ID` to `manga-script`.
- [ ] Base class: `DGLab\Services\MangaScript\MangaScriptService` extending `DGLab\Services\BaseService`.
- [ ] `public function process(array $input): array`
- [ ] `public function processAsync(array $input): string` (Returns a Job/Event ID)
- [ ] `public function getStatus(string $processId): array`
- [ ] `public function stream(array $input): iterable`
- [ ] Update `config/services.php` to reflect the new service structure.
- [ ] Leverage `config/llm_unified.php` for provider and model definitions, removing the need for a separate provider repository.
- [ ] Implement a `class_alias` bridge to ensure existing calls to `NovelToMangaScript` continue to function.
- [ ] Log deprecation warnings for any legacy entry points to the unified audit log.
- [ ] Initialize the `MangaScript` Studio App as a standalone component in the CMS Studio ecosystem.
- [ ] Create the foundational `<s:mangascript-workspace>` component in `resources/views/components/mangascript/workspace.super.php`.
- [ ] The service is accessible via `MangaScript` facade or helper.
- [ ] All existing tests for `NovelToMangaScript` pass when targeting the new `MangaScript` implementation.
- [ ] The Studio App skeleton is visible in the CMS Studio dashboard.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
