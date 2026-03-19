# Phase 3: The Schema Architect (Visual Modeler)

## Goals
Design a visual, no-code environment for modeling dynamic content structures. This phase introduces the "Architect App" for defining the blueprints of every content type, powered by the reactive **SuperPHP** engine.

## 3.1 Schema Manager Service
Responsibilities:
- **Field Definitions**: Primitive types (`string`, `text`, `number`, `boolean`, `date`) and complex types (`rich-text`, `media-reference`, `content-reference`).
- **Validation Engine**: Enforce rules defined in the Content Type schema (e.g., `required`, `regex`, `min/max`).
- **Relational Field Manager**: Links one `ContentEntry` to another.

## 3.2 Database Schema: Structural Foundation
- **`content_type_fields`**: ID, content_type_id, name, slug, field_type, validation_rules (JSON), sort_order, is_translatable.
- **`content_values`**: ID, entry_id, field_id, value (TEXT), created_at.
- **`Hybrid Storage`**: Utilize a **Hybrid EAV approach** for maximum compatibility and granularity, while allowing a `meta` field for overflow JSON data.

## 3.3 Visual Architect (SuperPHP Reactive Canvas)
- **SuperPHP "Canvas" Components**:
    - `<s:architect-canvas>`: A reactive, node-based modeling environment.
    - `<s:architect-field-toolbox>`: A drag-and-drop library of field types with real-time configuration sidebars.
- **Canvas-Driven Modeling**: Visualize the relationships between different content types.
- **Schema Simulation**: A "Preview" button to see how the generated form will look in the Content App in real-time.

## 3.4 Schema Versioning & Mutation
- **Structural Immutability**: Once a content type is "Released," structural changes (like changing a field type from `number` to `text`) are locked or require a formal migration.
- **Versioned Blueprints**: Every change to a `ContentType` schema creates a new version, allowing for historical data integrity.
- **Mutation Tracking**: Every schema change is dispatched as an `Event` to the `EventDispatcher` for full auditability.

## 3.5 Security & Governance
- **Role-Based Architecture**: Only users with the `architect` role can access this app.
- **Tenant Isolation**: Schemas are tenant-specific or globally shared based on the `tenant_id` context.
