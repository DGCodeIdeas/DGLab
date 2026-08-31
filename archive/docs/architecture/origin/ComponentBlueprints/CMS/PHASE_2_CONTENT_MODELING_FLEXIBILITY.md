# Phase 2: Content Modeling & Flexibility

## Goals
- Design a flexible, schema-less system for custom content types.
- Evaluate storage strategies (EAV vs. JSONB).
- Implement the `SchemaManagerService`.

## Storage Strategy Comparison

| Feature | Entity-Attribute-Value (EAV) | JSON-Based (JSONB) |
|---------|-----------------------------|--------------------|
| **DB Compatibility** | Highly compatible (works on all SQL DBs). | Requires modern DB (MySQL 5.7+, PostreSQL 9.4+). |
| **Query Performance** | Complex JOINs; can be slow for many fields. | Fast retrieval; can use JSON indexes. |
| **Flexibility** | Extremely high (each field is a row). | High (schema is a document). |
| **Data Integrity** | Easier to enforce via SQL constraints on `values` table. | Harder to enforce strictly without JSON Schema validation. |
| **Search** | Requires indexing many rows. | Integrated JSON search functions. |

**Decision**: The CMS will utilize a **Hybrid EAV approach** for maximum compatibility and granularity, while allowing a `meta` field for overflow JSON data.

## Schema Manager Service
Responsibilities:
- **Field Definitions**: Manage field types (`string`, `text`, `number`, `boolean`, `relation`, `media`).
- **Validation**: Enforce rules defined in the Content Type schema (e.g., `required`, `regex`, `min/max`).
- **Dynamic Scaffolding**: Provide the metadata required for CMS Studio to render forms.

### Database Schema (Conceptual)
```sql
CREATE TABLE content_type_fields (
    id BIGINT PRIMARY KEY,
    content_type_id BIGINT REFERENCES content_types(id),
    name VARCHAR(255),
    slug VARCHAR(255),
    field_type VARCHAR(50), -- text, number, relation, etc.
    validation_rules JSON,
    sort_order INT,
    is_translatable BOOLEAN DEFAULT FALSE
);

CREATE TABLE content_values (
    id BIGINT PRIMARY KEY,
    entry_id BIGINT REFERENCES content_entries(id),
    field_id BIGINT REFERENCES content_type_fields(id),
    value TEXT, -- Stores primitive values or serialized complex data
    created_at TIMESTAMP
);
```

## Custom Fields Support
- **Relational Fields**: Link one `ContentEntry` to another (e.g., 'Author' linked to 'Article').
- **Media Fields**: Store references to the `MediaLibraryService` assets.
- **Dynamic Validation**: Logic to parse `validation_rules` and apply them during the save process in `ContentManager`.
