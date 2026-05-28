# Phase 3: The Schema Architect (Visual Modeler)

## Goals
Design a visual, no-code environment for modeling dynamic content structures. This phase introduces the "Architect App" for defining the blueprints of every content type, powered by the reactive **SuperPHP** engine.

## 3.1 Schema Manager Service
Responsibilities:
- **Field Definitions**: Primitive types (`string`, `text`, `number`, `boolean`, `date`) and complex types (`rich-text`, `media-reference`, `content-reference`).
- **Validation Engine**: Enforce rules defined in the Content Type schema (e.g., `required`, `regex`, `min/max`).
- `SchemaService::validate($data, $schema)` integrated with `AuditService` for validation failures.

## 3.2 Database Schema: Hybrid EAV Foundation
- **`content_types`**: ID, tenant_id, name, slug, description, version, is_published, created_at.
- **`content_type_fields`**: ID, content_type_id, name, slug, field_type, validation_rules (JSON), sort_order, is_translatable.
- **`content_entries`**: ID, content_type_id, tenant_id, status (draft, published, archived), version_id, created_by, updated_at.
- **`content_values`**: ID, entry_id, field_id, value (TEXT), created_at.
- **`content_meta`**: ID, entry_id, metadata (JSON) for overflow and plugin data.

## 3.3 Visual Architect (SuperPHP Reactive Components)
- **SuperPHP "Canvas" Components**:
    - `<s:architect:canvas>`: A reactive modeling environment with `@persist($schema)`.
    - `<s:architect:field-toolbox>`: Drag-and-drop library of field types.
    - `<s:architect:field-config>`: Sidebar for setting validation rules and translation flags.
- **Real-time Previews**: `<s:architect:preview>` updates on-the-fly as the schema is modified.

## 3.4 Event-Driven Schema Mutation
- **Events**:
    - `cms.schema.created`: Fired when a new content type is defined.
    - `cms.schema.mutated`: Fired on every field update (High Severity Audit).
    - `cms.schema.released`: Fired when a version is locked for production.
- **Versioning**: Every mutation increments the `version` in `content_types`, archiving the previous `content_type_fields` set.

## 3.5 Security & Tenancy
- **Tenancy**: Strictly uses `TenancyService::requireTenant()` for all CRUD operations.
- **Authorization**: Requires `architect` role or `cms.schema.manage` permission.
