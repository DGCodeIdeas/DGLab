# Phase 3: Lifecycle, Workflow & Versioning

## Goals
- Implement a formal Content Workflow (Draft, Review, Publish).
- Design a Versioning Engine for full historical tracking.
- Enable Schema Versioning to ensure data integrity during structural changes.

## Versioning Engine
Every change to a `ContentEntry` creates a new `ContentVersion`.
- **Snapshots**: Each version stores a full snapshot of all field values at that point in time.
- **Immutability**: Once a version is created and "finalized" (e.g., set to Published), it cannot be modified. Changes create a new Draft version.

### Versioning Schema (Conceptual)
```sql
CREATE TABLE content_versions (
    id BIGINT PRIMARY KEY,
    entry_id BIGINT REFERENCES content_entries(id),
    schema_version_id BIGINT, -- Link to structural version
    author_id BIGINT, -- User who made the change
    data JSON, -- Complete snapshot of the values
    status VARCHAR(50), -- draft, published, archived
    version_number INT,
    created_at TIMESTAMP
);
```

## Workflow States
The system enforces a strict state machine:
1. **Draft**: Content is being edited; not visible on the frontend.
2. **Under Review**: Content is locked; waiting for approval.
3. **Published**: Content is live and accessible via the API.
4. **Archived**: Content is hidden but preserved for historical reasons.

## Schema Versioning
When a `ContentType` is modified (e.g., adding a field), a new Schema Version is created.
- **Migration Logic**: When viewing an old `ContentVersion`, the system uses the `schema_version_id` to interpret the `data` correctly.
- **Rollbacks**: Reverting a content entry to an older version also ensures it is rendered against the correct structural schema.

## Observability
- **Audit Logs**: Every status transition and version creation is logged.
- **Diffing**: The CMS Studio will provide a visual diff between two versions.
