# Phase 8: Globalization & Localization

## Goals
Implement multi-language support and field-level translation tables for the entire Studio ecosystem. This phase establishes the "LocalizationService."

## 8.1 Localization Service
- **One-to-Many**: A `ContentEntry` has many `Translations`.
- **Fallback Logic**: Configurable language fallbacks (e.g., if 'fr' is missing, show 'en').
- **Integration Flow**: `LocalizationService` retrieves the requested translation (or fallback) on delivery.

## 8.2 Translation Schema (Conceptual)
- **`[content_type]_translations`**: ID, entry_id, locale, field_slug, translated_value (TEXT).
- **Unique Constraint**: Ensures one translation per locale and field.

## 8.3 User Interface: The "Content App" (SuperPHP Translation View)
- **"Pro-Tool" Vibe**: A high-density, side-by-side translation editor.
- **SuperPHP Reactive Components**:
    - `<s:localization-editor>`: A reactive, side-by-side translation editor with real-time feedback.
    - `<s:localization-language-switcher>`: Quick-switch between different locales for the current entry.
    - `<s:localization-status-sidebar>`: Tracking which fields are missing translations for the current locale.

## 8.4 Performance & Reliability
- **Translation Caching**: Cache translation records at the application and PWA levels (Phase 6).
- **Batch Translations**: Background tasks to update multiple translations at once.

## 8.5 Security & Isolation
- **Translation Isolation**: Ensure all translation data is strictly bound to its respective tenant context.
- **Role-Based Translation**: Define which roles can edit translations for specific locales via `User::can()`.
