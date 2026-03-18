# Phase 8: Globalization & Localization

## Goals
Implement multi-language support and field-level translation tables for the entire Studio ecosystem. This phase establishes the "LocalizationService."

## 8.1 Localization Service
- **One-to-Many**: A `ContentEntry` has many `Translations`.
- **Fallback Logic**: Configurable language fallbacks (e.g., if 'fr' is missing, show 'en').

## 8.2 Translation Schema (Conceptual)
- **`[content_type]_translations`**: ID, entry_id, locale, field_slug, translated_value (TEXT).
- **Unique Constraint**: Ensures one translation per locale and field.

## 8.3 Integration Flow: CMS & Localization
1. User creates a content entry -> `ContentManager` saves the base entry.
2. `LocalizationService` creates translation records for any translatable fields.
3. On delivery, `LocalizationService` retrieves the requested translation (or fallback).

## 8.4 User Interface: The "Content App" (Translation View)
- **"Pro-Tool" Vibe**: A high-density, side-by-side translation editor.
- **Language Switcher**: Quick-switch between different locales for the current entry.
- **Translation Status Sidebar**: Tracking which fields are missing translations for the current locale.

## 8.5 Performance & Reliability
- **Translation Caching**: Cache translation records at the application and PWA levels (Phase 6).
- **Batch Translations**: Background tasks to update multiple translations at once.

## 8.6 Security & Isolation
- **Translation Isolation**: Ensure all translation data is strictly bound to its respective tenant context.
- **Role-Based Translation**: Define which roles can edit translations for specific locales.
