# Phase 3: The Schema Architect (Visual Modeler)

## Goals
Design a visual, no-code environment for modeling dynamic content structures. This phase introduces the "Architect App" for defining the blueprints of every content type.

## 3.1 Hybrid Storage Strategy (EAV + JSONB)
- **Hybrid Storage**:
    - **EAV (Entity-Attribute-Value)**: For maximum compatibility across SQL databases, granular field tracking, and easy search.
    - **JSONB Meta**: For overflow, non-structural metadata, and rapid JSON serialization.
- **Dynamic Scaffolding**: Provide metadata required for the Studio to render forms on-the-fly.

## 3.2 Schema Manager Service
Responsibilities:
- **Field Definitions**:
    - Primitive types (`string`, `text`, `number`, `boolean`, `date`).
    - Complex types (`rich-text`, `media-reference`, `content-reference`).
- **Validation Engine**: Enforce rules defined in the Content Type schema (e.g., `required`, `regex`, `min/max`).
- **Relational Field Manager**: Links one `ContentEntry` to another (e.g., 'Author' linked to 'Article').

## 3.3 Database Schema: Structural Foundation
- **`content_type_fields`**:
    - ID, content_type_id, name, slug, field_type, validation_rules (JSON), sort_order, is_translatable.
- **`content_values`**:
    - ID, entry_id, field_id, value (TEXT), created_at.

## 3.4 User Interface: The "Architect App"
- **"Visual Architect" Vibe**: A node-based or drag-and-drop environment.
- **Canvas-Driven Modeling**: Visualize the relationships between different content types (e.g., an 'Article' has one 'Author' and many 'Tags').
- **Field Toolbox**: A drag-and-drop library of field types with real-time configuration sidebars.
- **Schema Simulation**: A "Preview" button to see how the generated form will look in the Content App.

## 3.5 Security & Governance
- **Structural Immutability**: Once a content type is "Released," specific structural changes (like changing a field type from `number` to `text`) are locked or require a formal migration.
- **Versioned Blueprints**: Every change to a `ContentType` schema creates a new version, allowing for historical data integrity.
