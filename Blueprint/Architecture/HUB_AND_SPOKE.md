# Hub-and-Spoke Architecture: CMS Studio

## Overview
The DGLab ecosystem is transitioning to a **Hub-and-Spoke** architecture. In this model, **CMS Studio** serves as the central Hub—the only web-accessible front-end service—while all independent applications (e.g., MangaScript, EpubFontChanger) are refactored into **Spokes**.

## The Hub: CMS Studio
The Hub is a single Superpowers SPA shell that manages:
- **Routing**: All browser-initiated navigation.
- **UI/UX**: Global layout, navigation bars, and the SuperPHP reactive component library.
- **State**: Centralized global state persistence.
- **Security**: Auth and Tenancy enforcement.

## The Spokes: app/Spokes/
Spokes are domain-specific services that inherit from the Hub's core infrastructure.
- **Location**: `app/Spokes/{SpokeName}/`
- **Responsibility**: Business logic, data processing, and internal API provision.
- **Web Isolation**: Spokes do **not** have direct web routes. They are consumed by the Hub via internal dependency injection or the Event Bus.
- **Inheritance**: All spokes extend `BaseService` and utilize `AuditService`, `AuthService`, and `TenancyService`.

## Interaction Pattern
1. **User Action**: A user interacts with the Hub's UI (e.g., clicks "Convert to Manga").
2. **Hub Delegation**: The Hub's controller (or a generic Spoke-Dispatcher) calls the relevant Spoke service method.
3. **Spoke Execution**: The Spoke performs the domain logic (e.g., AI orchestration, file processing).
4. **Reactive Update**: The Spoke returns data or status updates, which the Hub reflects in the UI using SuperPHP's reactive fragments.

## UI Synchronization
Spokes do not render views directly. Instead:
- They provide data to Hub-owned SuperPHP templates.
- If a Spoke requires a specialized UI, that UI is built as a **SuperPHP Component** within the Hub's component library, ensuring it inherits the Hub's styling and layout.
