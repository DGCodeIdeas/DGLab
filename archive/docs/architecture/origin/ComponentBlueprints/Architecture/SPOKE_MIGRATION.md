# Spoke Migration Strategy

## 1. Directory Structure
All independent web applications and domain-specific services will be migrated to the `app/Spokes/` directory.

```
app/Spokes/
└── {SpokeName}/
    ├── {SpokeName}Service.php (Extends BaseService)
    ├── {SpokeName}Controller.php (Internal use only, no direct routing)
    ├── Models/ (Domain-specific models)
    ├── Events/ (Domain-specific events)
    └── UI/
        └── {SpokeName}Component.super.php (Specialized SuperPHP component)
```

## 2. Refactoring Steps

### Step A: Logic Encapsulation
- Move the core service (e.g., `MangaScriptService`) from `app/Services/` to `app/Spokes/{SpokeName}/`.
- Ensure it extends `DGLab\Services\BaseService`.
- Remove any direct route dependencies from the service.

### Step B: Controller Demotion
- If the spoke has a dedicated controller (e.g., `ServicesController`'s EPUB methods), move those methods into the `{SpokeName}Service`.
- Delete the original web routes in `routes/web.php`.

### Step C: UI Componentization
- Migrate the spoke's views (e.g., `resources/views/services/epub-font-changer.super.php`) into a SuperPHP Component.
- Place the component in `app/Spokes/{SpokeName}/UI/` or `resources/views/components/spokes/{spoke_name}/`.
- The component should be "Pure Reactive," relying on `~setup` blocks for state and calling the Spoke Service for heavy lifting.

### Step D: Hub Integration
- Register the Spoke Service in the `ServiceRegistry`.
- In the CMS Studio Hub (the central SPA), create a "Dispatcher" route or use the `ActionController` to delegate requests to the Spoke.
- Use the SPA fragment system to load the Spoke's UI component into the main shell.

## 3. Specific Candidates for Migration

### MangaScript (The AI Spoke)
- **Status**: Mostly encapsulated in `app/Services/MangaScript/`.
- **Action**: Move to `app/Spokes/MangaScript/`. Formalize the `Workspace` component as the primary entry point.

### EpubFontChanger (The Utility Spoke)
- **Status**: Logic in `app/Services/EpubFontChanger/`, Controller logic in `ServicesController`.
- **Action**: Create `app/Spokes/EpubFontChanger/`. Move service logic and merge `ServicesController` EPUB logic into it. Convert view to a component.
